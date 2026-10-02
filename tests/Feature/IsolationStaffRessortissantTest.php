<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Ressortissant;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Garde-fous de sécurité de l'API :
 *
 *  1. Un token de ressortissant ne doit JAMAIS ouvrir une route du staff
 *     (et inversement). Avant correctif, GET /api/v1/messages répondait 200
 *     avec toutes les coordonnées des expéditeurs.
 *  2. Le rate limiting est actif sur les routes sensibles.
 *  3. Le login admin ne révèle pas quels emails ont un compte.
 *
 * Lancer :  php artisan test --filter IsolationStaffRessortissantTest
 * Base utilisée : SQLite en mémoire (voir phpunit.xml) — jamais la vraie base.
 */
class IsolationStaffRessortissantTest extends TestCase
{
    // Avant chaque test : base remise à zéro (migrations rejouées), puis
    // annulée à la fin du test. Chaque test part donc d'une base propre.
    use RefreshDatabase;

    private const EMAIL_EXPEDITEUR = 'visiteur.secret@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // CurrentEntity garde l'entité courante en mémoire statique : sans
        // ce reset, elle fuirait d'un test à l'autre.
        CurrentEntity::reset();
    }

    // ------------------------------------------------------------------
    // Outils : fabrique des comptes, des tokens et envoie les requêtes
    // ------------------------------------------------------------------

    private function creerStaff(string $role = 'agent', array $surcharge = []): User
    {
        // UserFactory fournit déjà un compte valide (mot de passe « password »).
        return User::factory()->create(array_merge(['role' => $role], $surcharge));
    }

    private function creerRessortissant(): Ressortissant
    {
        // Pas de factory pour ce modèle : on crée à la main. entity_id est
        // posé automatiquement (trait BelongsToEntity).
        return Ressortissant::create([
            'nom' => 'Mabiala',
            'prenom' => 'Jean',
            'email' => 'ressortissant@example.com',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
            'statut' => 'actif',
        ]);
    }

    private function creerMessage(): Message
    {
        return Message::create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'email' => self::EMAIL_EXPEDITEUR,
            'telephone' => '+22900000000',
            'objet' => 'question',
            'message' => 'Bonjour, une question sur mon dossier.',
        ]);
    }

    /** Vrai token Sanctum, comme celui que reçoit le frontend après login. */
    private function tokenDe(User|Ressortissant $compte): string
    {
        return $compte->createToken('test')->plainTextToken;
    }

    private function appeler(string $methode, string $url, ?string $token = null, array $donnees = [])
    {
        // Laravel mémorise l'utilisateur authentifié pendant tout le test :
        // sans cet oubli, une 2e requête avec un autre token réutiliserait
        // le 1er utilisateur et fausserait le résultat.
        $this->app['auth']->forgetGuards();

        $entetes = ['Accept' => 'application/json'];
        if ($token) {
            $entetes['Authorization'] = 'Bearer ' . $token;
        }

        return $this->json($methode, $url, $donnees, $entetes);
    }

    // ------------------------------------------------------------------
    // 1. Isolation staff / ressortissant
    // ------------------------------------------------------------------

    /**
     * Ce test est exécuté une fois pour CHAQUE ligne de routesReserveesAuStaff().
     * C'est le test qui aurait détecté la faille : avant le correctif, la
     * ligne « liste des messages » recevait 200 au lieu de 403.
     */
    #[DataProvider('routesReserveesAuStaff')]
    public function test_un_ressortissant_est_refuse_sur_les_routes_du_staff(string $methode, string $url): void
    {
        $message = $this->creerMessage();
        $token = $this->tokenDe($this->creerRessortissant());

        $reponse = $this->appeler($methode, str_replace('{id}', (string) $message->id, $url), $token);

        $reponse->assertStatus(403);
        // Ceinture et bretelles : même un 403 ne doit contenir aucune donnée.
        $reponse->assertJsonMissing(['email' => self::EMAIL_EXPEDITEUR]);
    }

    public static function routesReserveesAuStaff(): array
    {
        return [
            'liste des messages' => ['GET', '/api/v1/messages'],
            'détail d\'un message' => ['GET', '/api/v1/messages/{id}'],
            'statistiques messages' => ['GET', '/api/v1/messages/statistiques'],
            'statistiques actualités' => ['GET', '/api/v1/actualites/statistiques'],
            'profil staff' => ['GET', '/api/v1/auth/me'],
            'tableau de bord admin' => ['GET', '/api/v1/admin/dashboard'],
            'registre des ressortissants' => ['GET', '/api/v1/admin/ressortissants'],
            'demandes (admin)' => ['GET', '/api/v1/admin/demandes'],
        ];
    }

    public function test_un_agent_peut_toujours_lire_les_messages(): void
    {
        // Test « témoin » : prouve qu'on n'a pas tout verrouillé par erreur.
        $this->creerMessage();
        $token = $this->tokenDe($this->creerStaff('agent'));

        $this->appeler('GET', '/api/v1/messages', $token)
            ->assertOk()
            ->assertJsonFragment(['email' => self::EMAIL_EXPEDITEUR]);
    }

    public function test_sans_token_les_routes_du_staff_repondent_401(): void
    {
        $this->appeler('GET', '/api/v1/messages')->assertStatus(401);
    }

    public function test_un_compte_staff_est_refuse_sur_l_espace_ressortissant(): void
    {
        $token = $this->tokenDe($this->creerStaff('super_admin'));

        $this->appeler('GET', '/api/v1/ressortissant/demandes', $token)->assertStatus(403);
        $this->appeler('GET', '/api/v1/ressortissant/auth/me', $token)->assertStatus(403);
    }

    public function test_un_agent_ne_peut_pas_supprimer_un_message(): void
    {
        // La suppression est réservée au super_admin (middleware role:).
        $message = $this->creerMessage();
        $token = $this->tokenDe($this->creerStaff('agent'));

        $this->appeler('DELETE', '/api/v1/messages/' . $message->id, $token)->assertStatus(403);

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    // ------------------------------------------------------------------
    // 2. Rate limiting
    // ------------------------------------------------------------------

    public function test_le_login_est_limite_a_5_tentatives_par_minute(): void
    {
        $identifiants = ['email' => 'inconnu@example.com', 'password' => 'nimporte'];

        for ($i = 1; $i <= 5; $i++) {
            $this->appeler('POST', '/api/auth/login', null, $identifiants)->assertStatus(401);
        }

        // La 6e est bloquée AVANT même de regarder le mot de passe.
        $this->appeler('POST', '/api/auth/login', null, $identifiants)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_le_formulaire_de_contact_public_est_limite(): void
    {
        // Charge vide => 422 (validation). Le quota compte quand même.
        for ($i = 1; $i <= 5; $i++) {
            $this->appeler('POST', '/api/v1/messages', null, [])->assertStatus(422);
        }

        $this->appeler('POST', '/api/v1/messages', null, [])->assertStatus(429);
    }

    // ------------------------------------------------------------------
    // 3. Login admin uniforme
    // ------------------------------------------------------------------

    public function test_compte_inconnu_et_mauvais_mot_de_passe_donnent_la_meme_reponse(): void
    {
        $this->creerStaff('agent', ['email' => 'agent@example.com']);

        $inconnu = $this->appeler('POST', '/api/auth/login', null, [
            'email' => 'personne@example.com', 'password' => 'mauvais',
        ]);
        $mauvaisMdp = $this->appeler('POST', '/api/auth/login', null, [
            'email' => 'agent@example.com', 'password' => 'mauvais',
        ]);

        $inconnu->assertStatus(401);
        $mauvaisMdp->assertStatus(401);
        // L'attaquant ne peut pas distinguer les deux cas.
        $this->assertSame($inconnu->json('message'), $mauvaisMdp->json('message'));
    }

    public function test_un_compte_desactive_n_est_revele_qu_avec_le_bon_mot_de_passe(): void
    {
        $this->creerStaff('agent', ['email' => 'suspendu@example.com', 'est_actif' => false]);

        // Mauvais mot de passe : réponse banale, on ne dit pas « désactivé ».
        $this->appeler('POST', '/api/auth/login', null, [
            'email' => 'suspendu@example.com', 'password' => 'mauvais',
        ])->assertStatus(401);

        // Bon mot de passe (celui de la factory) : là seulement, on l'explique.
        $this->appeler('POST', '/api/auth/login', null, [
            'email' => 'suspendu@example.com', 'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_un_login_correct_fonctionne_toujours(): void
    {
        $this->creerStaff('admin', ['email' => 'admin@example.com']);

        $this->appeler('POST', '/api/auth/login', null, [
            'email' => 'admin@example.com', 'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'role']]]);
    }
}
