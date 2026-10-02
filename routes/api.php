<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\ActualiteController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GuideController;
use App\Http\Controllers\API\PartenaireController;
use App\Http\Controllers\API\QuartierController;
use App\Http\Controllers\API\RealisationController;
use App\Http\Controllers\API\NewsletterController;
use App\Http\Controllers\API\StatistiquesPubliquesController;
use App\Http\Controllers\API\CartePubliqueController;
use App\Http\Controllers\API\ServicesPubliqueController;
use App\Http\Controllers\API\UtilisateurController;
use App\Http\Controllers\API\JournalActiviteController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\API\RessortissantAuthController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DemandeAdminController;
use App\Http\Controllers\API\PaiementAdminController;
use App\Http\Controllers\API\PaiementEnLigneController;
use App\Http\Controllers\API\RessortissantAdminController;
use App\Http\Controllers\API\TarifController;
use App\Http\Controllers\API\DocumentTypeRequisController;
use App\Models\Ressortissant;

// ==================== ROUTES PUBLIQUES ====================

// Test
Route::get('/test', function() {
    return response()->json([
        'success' => true,
        'message' => 'API Consulat fonctionne correctement',
        'version' => '1.0.0',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Auth (espace admin)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/activer-compte-admin', [AuthController::class, 'activerCompteAdmin'])->middleware('throttle:token-link');
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'motDePasseOublie'])->middleware('throttle:email-send');
});

// Messages - Routes publiques (création et consultation publique)
Route::prefix('v1')->group(function () {
    // Créer un message (PUBLIC)
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:public-form');
    
    // NB : la lecture d'un message (GET /messages/{id}) n'est volontairement
    // PAS publique — elle contient nom, email, téléphone et texte de
    // l'expéditeur. Elle est déclarée dans le groupe protégé plus bas.

    // Actualités - Routes publiques
    Route::get('/actualites', [ActualiteController::class, 'index']);
    Route::get('/actualites/type/{type}', [ActualiteController::class, 'getByType']);
    Route::get('/actualites/dernieres', [ActualiteController::class, 'dernieresActualites']);
    Route::get('/actualites/{id}', [ActualiteController::class, 'show']);

    // NOTE : pas de routes /evenements séparées. L'Agenda (V1) affiche les
    // Actualités de type "evenement" (voir ActualiteController::getByType) —
    // la table `evenements` (calendrier avec inscription/billetterie) était
    // une fonctionnalité V2 jamais branchée à un vrai flux d'inscription ;
    // retirée avec son admin.

    // Guide - Routes publiques (arborescence sections > sous-sections > documents)
    Route::get('/guide', [GuideController::class, 'index']);
    Route::get('/guide/sections/{id}', [GuideController::class, 'showSection']);
    Route::post('/guide/documents/{id}/telecharger', [GuideController::class, 'telechargerDocument']);

    // Partenaires - Routes publiques (consultation)
    Route::get('/partenaires', [PartenaireController::class, 'index']);
    Route::get('/partenaires/{id}', [PartenaireController::class, 'show']);

    // Réalisations de la communauté / Culture & patrimoine - Routes publiques
    // (entrées publiées uniquement ; filtre ?rubrique=communaute|culture_patrimoine)
    Route::get('/realisations', [RealisationController::class, 'index']);
    Route::get('/realisations/{id}', [RealisationController::class, 'show'])->whereNumber('id');

    // Newsletter - Inscription publique (footer et autres pages)
    Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:public-form');

    // Statistiques publiques - Chiffres clés réels pour la page d'accueil
    Route::get('/statistiques-publiques', [StatistiquesPubliquesController::class, 'index']);

    // Carte interactive - Vue publique (agrégation par ville uniquement)
    Route::get('/carte', [CartePubliqueController::class, 'index']);

    // Services consulaires - Vue publique : fourchette de prix par type de
    // demande + pièces requises. Ne jamais réutiliser TarifController ici,
    // dont la route reste volontairement privée (voir son commentaire).
    Route::get('/services', [ServicesPubliqueController::class, 'index']);

    // Liste de référence des 77 communes du Bénin, utilisée par le champ
    // VilleSelect (registre consulaire, admin comme futur formulaire
    // d'inscription public). Public et statique : aucune donnée
    // personnelle, remplace l'ancien /v1/membres/villes (AJDCB, supprimé).
    Route::get('/communes-benin', fn () => response()->json([
        'success' => true,
        'data' => Ressortissant::VILLES_BENIN,
    ]));

    // Quartiers d'une commune (?ville=Cotonou). Liste vide = pas de liste
    // pour cette commune (le formulaire retombe sur une saisie libre).
    Route::get('/quartiers', [QuartierController::class, 'index']);
});

// Webhook FedaPay pour le paiement en ligne des demandes consulaires (voir
// Paiement::CANAUX, FedaPayService, PaiementEnLigneController). Public et
// non authentifié par nature (appelé par FedaPay), la sécurité vient de la
// vérification de signature dans FedaPayService::verifierWebhook — jamais
// d'autre garde ici. Remplace l'ancienne intégration FedaPay des billets
// d'événement AJDCB, retirée avec PaiementController (cassée, hors
// périmètre V1 : l'Agenda est une simple liste sans billetterie).
Route::post('/webhooks/fedapay', [PaiementEnLigneController::class, 'webhook']);

// Routes pour les images (PUBLIQUES - sans authentification)
Route::prefix('images')->group(function () {
    Route::get('/{type}/{filename}', [ImageController::class, 'show']);
    Route::get('/{path}', [ImageController::class, 'get'])->where('path', '.*');
});

// ==================== ROUTES PROTÉGÉES (NÉCESSITENT AUTH) ====================
// Rôles disponibles : super_admin, admin, agent (voir User::ROLES)
// 'actor:staff' : sans lui, auth:sanctum accepte aussi les tokens des
// ressortissants (même mécanisme Sanctum) — ils pouvaient lire /messages.
Route::middleware(['auth:sanctum', 'actor:staff'])->prefix('v1')->group(function () {

    // Auth supplémentaires
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    // ========== MESSAGES ==========
    // Lecture : les 3 rôles. Répondre : admin/super_admin. Supprimer : super_admin uniquement.
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessageController::class, 'index'])
            ->middleware('role:super_admin,admin,agent');
        Route::get('/statistiques', [MessageController::class, 'statistiques'])
            ->middleware('role:super_admin,admin,agent');
        Route::get('/{id}', [MessageController::class, 'show'])
            ->middleware('role:super_admin,admin,agent');
        Route::put('/{id}', [MessageController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::post('/{id}/repondre', [MessageController::class, 'repondre'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [MessageController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== ACTUALITÉS ==========
    // Lecture : les 3 rôles. Créer : admin/super_admin. Modifier : admin/super_admin.
    // Supprimer : super_admin uniquement.
    Route::prefix('actualites')->group(function () {
        Route::get('/statistiques', [ActualiteController::class, 'statistiques'])
            ->middleware('role:super_admin,admin,agent');
        Route::post('/', [ActualiteController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::put('/{id}', [ActualiteController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [ActualiteController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== UTILISATEURS (comptes admin) ==========
    // Liste, création (avec email d'activation), changement de rôle/statut,
    // suppression, renvoi d'activation.
    // Réservé au super_admin — ce sont des identifiants de connexion.
    Route::prefix('utilisateurs')->middleware('role:super_admin')->group(function () {
        Route::get('/', [UtilisateurController::class, 'index']);
        Route::post('/', [UtilisateurController::class, 'store']);
        Route::put('/{id}', [UtilisateurController::class, 'update']);
        Route::delete('/{id}', [UtilisateurController::class, 'destroy']);
        Route::post('/{id}/renvoyer-activation', [UtilisateurController::class, 'renvoyerActivation']);
    });

    // ========== JOURNAL D'ACTIVITÉ ==========
    // Historique des actions sensibles (demandes, ressortissants, comptes
    // admin). Réservé au super_admin.
    Route::get('/journal-activite', [JournalActiviteController::class, 'index'])
        ->middleware('role:super_admin');

    // ========== GUIDE ==========
    // Lecture : déjà publique (y compris brouillons via ?all=1, réservé à l'admin
    // côté frontend). Créer/modifier/supprimer : admin/super_admin pour tout niveau
    // de la hiérarchie (sections, sous-sections, documents).
    Route::prefix('guide')->group(function () {
        Route::post('/sections', [GuideController::class, 'storeSection'])
            ->middleware('role:super_admin,admin');
        Route::put('/sections/{id}', [GuideController::class, 'updateSection'])
            ->middleware('role:super_admin,admin');
        Route::delete('/sections/{id}', [GuideController::class, 'destroySection'])
            ->middleware('role:super_admin');

        Route::post('/sous-sections', [GuideController::class, 'storeSousSection'])
            ->middleware('role:super_admin,admin');
        Route::put('/sous-sections/{id}', [GuideController::class, 'updateSousSection'])
            ->middleware('role:super_admin,admin');
        Route::delete('/sous-sections/{id}', [GuideController::class, 'destroySousSection'])
            ->middleware('role:super_admin');

        Route::post('/documents', [GuideController::class, 'storeDocument'])
            ->middleware('role:super_admin,admin');
        Route::post('/documents/{id}', [GuideController::class, 'updateDocument'])
            ->middleware('role:super_admin,admin');
        Route::delete('/documents/{id}', [GuideController::class, 'destroyDocument'])
            ->middleware('role:super_admin');
    });

    // ========== NEWSLETTER ==========
    // Inscription : déjà publique. Consultation de la liste et désinscription :
    // admin/super_admin uniquement.
    Route::prefix('newsletter')->group(function () {
        Route::get('/', [NewsletterController::class, 'index'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [NewsletterController::class, 'destroy'])
            ->middleware('role:super_admin,admin');
    });

    // ========== RÉALISATIONS ==========
    // Lecture : déjà publique. Créer/modifier : admin/super_admin.
    // Supprimer : super_admin uniquement.
    Route::prefix('realisations')->group(function () {
        Route::post('/', [RealisationController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::put('/{id}', [RealisationController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [RealisationController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== PARTENAIRES ==========
    // Lecture : déjà publique (partenaires actifs uniquement, sauf filtre explicite).
    // Créer/modifier : admin/super_admin. Supprimer : super_admin uniquement.
    Route::prefix('partenaires')->group(function () {
        Route::get('/statistiques', [PartenaireController::class, 'statistiques'])
            ->middleware('role:super_admin,admin');
        Route::post('/', [PartenaireController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::put('/{id}', [PartenaireController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [PartenaireController::class, 'destroy'])
            ->middleware('role:super_admin');
    });
});

// ==================== ESPACE MEMBRE (RESSORTISSANT) ====================

Route::prefix('v1/ressortissant/auth')->group(function () {
    Route::post('/inscrire', [RessortissantAuthController::class, 'inscrire'])->middleware('throttle:public-form');
    Route::post('/verifier-email', [RessortissantAuthController::class, 'verifierEmail'])->middleware('throttle:token-link');
    Route::post('/renvoyer-verification', [RessortissantAuthController::class, 'renvoyerVerification'])->middleware('throttle:email-send');
    Route::post('/login', [RessortissantAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/mot-de-passe-oublie', [RessortissantAuthController::class, 'motDePasseOublie'])->middleware('throttle:email-send');
    Route::post('/reinitialiser-mot-de-passe', [RessortissantAuthController::class, 'reinitialiserMotDePasse'])->middleware('throttle:token-link');

    Route::middleware(['auth:sanctum', 'actor:ressortissant'])->group(function () {
        Route::post('/logout', [RessortissantAuthController::class, 'logout']);
        Route::get('/me', [RessortissantAuthController::class, 'me']);
        Route::post('/change-password', [RessortissantAuthController::class, 'changePassword']);
    });
});

// Espace membre (ressortissant connecté)
Route::prefix('v1/ressortissant')->middleware(['auth:sanctum', 'actor:ressortissant'])->group(function () {
    Route::get('/demandes/configuration/{type}', [DemandeController::class, 'configuration']);
    Route::get('/demandes', [DemandeController::class, 'index']);
    Route::post('/demandes', [DemandeController::class, 'store']);
    Route::get('/demandes/{id}', [DemandeController::class, 'show']);
    Route::post('/demandes/{id}/documents', [DemandeController::class, 'uploadDocument']);
    Route::delete('/demandes/{id}/documents/{documentId}', [DemandeController::class, 'supprimerDocument']);

    // Paiement en ligne FedaPay. Le webhook (source de vérité) est en
    // dehors de ce groupe, plus bas, puisqu'il est appelé par FedaPay et
    // non par un ressortissant connecté — voir PaiementEnLigneController.
    Route::post('/demandes/{id}/paiement-en-ligne', [PaiementEnLigneController::class, 'initier']);
    Route::post('/paiements/{transactionId}/verifier', [PaiementEnLigneController::class, 'verifier']);
});

// Espace admin/agent
Route::get('/v1/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:super_admin,admin,agent']);

Route::prefix('v1/admin/demandes')->middleware(['auth:sanctum', 'role:super_admin,admin,agent'])->group(function () {
    Route::get('/', [DemandeAdminController::class, 'index']);
    Route::get('/{id}', [DemandeAdminController::class, 'show']);
    Route::get('/documents/{documentId}/fichier', [DemandeAdminController::class, 'telechargerDocument']);
    Route::patch('/{id}/statut', [DemandeAdminController::class, 'changerStatut']);
    Route::patch('/documents/{documentId}', [DemandeAdminController::class, 'verifierDocument']);
    Route::post('/{demandeId}/documents/valider-tout', [DemandeAdminController::class, 'validerToutesPieces']);

    // Encaissement au guichet : indispensable au workflow, un dossier ne
    // peut passer « prêt » qu'une fois paiement_statut = paye (voir
    // DemandeAdminController::changerStatut). Ouvert aux agents, c'est
    // eux qui tiennent le guichet.
    Route::post('/{demandeId}/paiement-guichet', [PaiementAdminController::class, 'encaisser']);
});

// Paiements : reçu imprimable et récapitulatif de caisse. Groupe séparé
// (plutôt que dans v1/admin/demandes) car un paiement se consulte aussi
// hors du contexte d'un dossier précis (clôture de caisse en fin de
// journée). Ouvert aux agents : ce sont eux qui tiennent le guichet et
// doivent pouvoir réimprimer un reçu ou clôturer leur caisse.
Route::prefix('v1/admin/paiements')->middleware(['auth:sanctum', 'role:super_admin,admin,agent'])->group(function () {
    Route::get('/recapitulatif', [PaiementAdminController::class, 'recapitulatif']);
    Route::get('/{id}', [PaiementAdminController::class, 'show']);
});

// Registre consulaire — consultation admin/agent (l'inscription reste en
// self-service côté ressortissant, voir /v1/ressortissant/auth/inscrire).
Route::prefix('v1/admin/ressortissants')->middleware(['auth:sanctum', 'role:super_admin,admin,agent'])->group(function () {
    Route::get('/', [RessortissantAdminController::class, 'index']);
    Route::get('/statistiques', [RessortissantAdminController::class, 'statistiques']);
    Route::get('/carte', [RessortissantAdminController::class, 'carte']);
    Route::get('/export', [RessortissantAdminController::class, 'export']);
    Route::get('/{id}', [RessortissantAdminController::class, 'show']);
    Route::get('/{id}/piece', [RessortissantAdminController::class, 'piece']);
    // Correction de fiche et changement de statut : réservé à admin/super_admin,
    // l'agent reste en lecture seule sur le registre (cohérent avec le menu).
    Route::patch('/{id}', [RessortissantAdminController::class, 'update'])
        ->middleware('role:super_admin,admin');
});

// Grille tarifaire et pièces requises par type de demande — configuration
// pure, réservée à admin/super_admin (un agent traite des dossiers, il ne
// change pas les tarifs).
Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->prefix('v1/admin')->group(function () {
    Route::apiResource('tarifs', TarifController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('document-types-requis', DocumentTypeRequisController::class)->only(['index', 'store', 'update', 'destroy']);
});