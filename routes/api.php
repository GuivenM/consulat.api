<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\ActionController;
use App\Http\Controllers\API\ActualiteController;
use App\Http\Controllers\API\MembreController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MembreAuthController;
use App\Http\Controllers\API\MembreEspaceController;
use App\Http\Controllers\API\EvenementController;
use App\Http\Controllers\API\GuideController;
use App\Http\Controllers\API\PartenaireController;
use App\Http\Controllers\API\PaiementController;
use App\Http\Controllers\API\NewsletterController;
use App\Http\Controllers\API\StatistiquesPubliquesController;
use App\Http\Controllers\API\UtilisateurController;
use App\Http\Controllers\API\JournalActiviteController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\API\RessortissantAuthController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\DemandeAdminController;
use App\Http\Controllers\API\RessortissantAdminController;
use App\Http\Controllers\API\TarifController;
use App\Http\Controllers\API\DocumentTypeRequisController;

// ==================== ROUTES PUBLIQUES ====================

// Test
Route::get('/test', function() {
    return response()->json([
        'success' => true,
        'message' => 'API AJDCB fonctionne correctement',
        'version' => '1.0.0',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Auth (espace admin)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/activer-compte-admin', [AuthController::class, 'activerCompteAdmin']);
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'motDePasseOublie']);
});

// Auth (espace membre) — distinct de l'espace admin ci-dessus
Route::prefix('v1/membre/auth')->group(function () {
    Route::post('/activer-compte', [MembreAuthController::class, 'activerCompte']);
    Route::post('/login', [MembreAuthController::class, 'login']);
    Route::post('/mot-de-passe-oublie', [MembreAuthController::class, 'motDePasseOublie']);
});

// Messages - Routes publiques (création et consultation publique)
Route::prefix('v1')->group(function () {
    // Créer un message (PUBLIC)
    Route::post('/messages', [MessageController::class, 'store']);
    
    // Voir un message spécifique (PUBLIC si vous voulez)
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    
    // Actualités - Routes publiques
    Route::get('/actualites', [ActualiteController::class, 'index']);
    Route::get('/actualites/type/{type}', [ActualiteController::class, 'getByType']);
    Route::get('/actualites/dernieres', [ActualiteController::class, 'dernieresActualites']);
    Route::get('/actualites/{id}', [ActualiteController::class, 'show']);
    
    // Actions - Routes publiques (consultation)
    Route::get('/actions', [ActionController::class, 'index']);
    Route::get('/actions/section/{section}', [ActionController::class, 'getBySection']);
    Route::get('/actions/{id}', [ActionController::class, 'show']);
    
    // Membres - Routes publiques
    Route::get('/membres', [MembreController::class, 'index']);
    Route::get('/membres/bureau', [MembreController::class, 'bureau']);
    Route::get('/membres/commissions', [MembreController::class, 'commissions']);
    Route::get('/membres/commission/{nom}', [MembreController::class, 'commission']);
    Route::get('/membres/postes-bureau', [MembreController::class, 'postesBureau']);
    Route::get('/membres/villes', [MembreController::class, 'villes']);
    Route::get('/membres/{id}', [MembreController::class, 'show']);

    // Événements - Routes publiques (consultation)
    Route::get('/evenements', [EvenementController::class, 'index']);
    Route::get('/evenements/{id}', [EvenementController::class, 'show']);

    // Guide - Routes publiques (arborescence sections > sous-sections > documents)
    Route::get('/guide', [GuideController::class, 'index']);
    Route::get('/guide/sections/{id}', [GuideController::class, 'showSection']);
    Route::post('/guide/documents/{id}/telecharger', [GuideController::class, 'telechargerDocument']);

    // Partenaires - Routes publiques (consultation)
    Route::get('/partenaires', [PartenaireController::class, 'index']);
    Route::get('/partenaires/{id}', [PartenaireController::class, 'show']);

    // Newsletter - Inscription publique (footer et autres pages)
    Route::post('/newsletter', [NewsletterController::class, 'store']);

    // Statistiques publiques - Chiffres clés réels pour la page d'accueil
    Route::get('/statistiques-publiques', [StatistiquesPubliquesController::class, 'index']);

    // Paiements FedaPay - Route publique (un visiteur ou un membre paie sans être connecté)
    Route::post('/paiements/evenements/{id}', [PaiementController::class, 'initierEvenement']);
});

// Webhook FedaPay (PUBLIC — appelé par les serveurs FedaPay, pas par le navigateur)
Route::post('/v1/paiements/webhook', [PaiementController::class, 'webhook']);

// Routes pour les images (PUBLIQUES - sans authentification)
Route::prefix('images')->group(function () {
    Route::get('/{type}/{filename}', [ImageController::class, 'show']);
    Route::get('/{path}', [ImageController::class, 'get'])->where('path', '.*');
});

// ==================== ROUTES PROTÉGÉES (NÉCESSITENT AUTH) ====================
// Rôles disponibles : super_admin, admin, moderateur (voir AuthController::getPermissionsByRole)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

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
        Route::get('/', [MessageController::class, 'index']);
        Route::get('/statistiques', [MessageController::class, 'statistiques']);
        Route::get('/{id}', [MessageController::class, 'show']);
        Route::put('/{id}', [MessageController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::post('/{id}/repondre', [MessageController::class, 'repondre'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [MessageController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== ACTUALITÉS ==========
    // Lecture : les 3 rôles. Créer : moderateur/admin/super_admin. Modifier : admin/super_admin.
    // Supprimer : super_admin uniquement.
    Route::prefix('actualites')->group(function () {
        Route::get('/statistiques', [ActualiteController::class, 'statistiques']);
        Route::post('/', [ActualiteController::class, 'store'])
            ->middleware('role:super_admin,admin,moderateur');
        Route::put('/{id}', [ActualiteController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [ActualiteController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== ACTIONS ==========
    // Lecture : les 3 rôles (déjà publique). Créer/modifier : admin/super_admin.
    // Supprimer : super_admin uniquement.
    Route::prefix('actions')->group(function () {
        Route::get('/statistiques', [ActionController::class, 'statistiques']);
        Route::post('/', [ActionController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::post('/{id}', [ActionController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [ActionController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

    // ========== MEMBRES ==========
    // Lecture de TOUS les membres (actifs + inactifs), réservé à l'espace admin.
    Route::get('/membres-admin/tous', [MembreController::class, 'tous']);
    Route::get('/membres-admin/export', [MembreController::class, 'export']);

    // Lecture (bureau, commissions, etc.) : déjà publique. Créer/modifier : admin/super_admin.
    // Supprimer : super_admin uniquement.
    Route::prefix('membres')->group(function () {
        Route::post('/', [MembreController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::put('/{id}', [MembreController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [MembreController::class, 'destroy'])
            ->middleware('role:super_admin');
        // Créer un accès admin depuis une fiche membre du bureau (voir
        // Membre::POSTES_ADMIN_ELIGIBLES) — création de comptes, réservé au super_admin.
        Route::post('/{id}/creer-acces-admin', [MembreController::class, 'creerAccesAdmin'])
            ->middleware('role:super_admin');
    });

    // ========== UTILISATEURS (comptes admin) ==========
    // Gestion des comptes déjà créés (via creer-acces-admin ou à la main) :
    // liste, changement de rôle/statut, suppression, renvoi d'activation.
    // Réservé au super_admin — ce sont des identifiants de connexion.
    Route::prefix('utilisateurs')->middleware('role:super_admin')->group(function () {
        Route::get('/', [UtilisateurController::class, 'index']);
        Route::put('/{id}', [UtilisateurController::class, 'update']);
        Route::delete('/{id}', [UtilisateurController::class, 'destroy']);
        Route::post('/{id}/renvoyer-activation', [UtilisateurController::class, 'renvoyerActivation']);
    });

    // ========== JOURNAL D'ACTIVITÉ ==========
    // Historique des actions sensibles (cotisations, membres, comptes admin,
    // adhésions). Réservé au super_admin.
    Route::get('/journal-activite', [JournalActiviteController::class, 'index'])
        ->middleware('role:super_admin');

    // ========== ÉVÉNEMENTS ==========
    // Lecture : déjà publique. Créer/modifier : admin/super_admin. Supprimer : super_admin uniquement.
    Route::prefix('evenements')->group(function () {
        Route::get('/statistiques', [EvenementController::class, 'statistiques'])
            ->middleware('role:super_admin,admin');
        Route::post('/', [EvenementController::class, 'store'])
            ->middleware('role:super_admin,admin');
        Route::put('/{id}', [EvenementController::class, 'update'])
            ->middleware('role:super_admin,admin');
        Route::delete('/{id}', [EvenementController::class, 'destroy'])
            ->middleware('role:super_admin');
    });

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

// ==================== ROUTES PROTÉGÉES — ESPACE MEMBRE ====================
// Séparées des routes admin ci-dessus : un token Membre ne peut pas accéder
// aux routes admin (elles vérifient $user->role, absent sur Membre), et le
// middleware 'membre' bloque symétriquement un token admin ici.
Route::middleware(['auth:sanctum', 'membre'])->prefix('v1/membre')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [MembreAuthController::class, 'logout']);
        Route::get('/me', [MembreAuthController::class, 'me']);
        Route::post('/change-password', [MembreAuthController::class, 'changePassword']);
    });

    Route::put('/profil', [MembreEspaceController::class, 'updateProfil']);
    Route::get('/evenements', [MembreEspaceController::class, 'evenements']);
    Route::post('/evenements/{id}/inscription', [MembreEspaceController::class, 'inscrire']);
    Route::delete('/evenements/{id}/inscription', [MembreEspaceController::class, 'desinscrire']);
});

Route::prefix('v1/ressortissant/auth')->group(function () {
    Route::post('/inscrire', [RessortissantAuthController::class, 'inscrire']);
    Route::post('/verifier-email', [RessortissantAuthController::class, 'verifierEmail']);
    Route::post('/renvoyer-verification', [RessortissantAuthController::class, 'renvoyerVerification']);
    Route::post('/login', [RessortissantAuthController::class, 'login']);
    Route::post('/mot-de-passe-oublie', [RessortissantAuthController::class, 'motDePasseOublie']);
    Route::post('/reinitialiser-mot-de-passe', [RessortissantAuthController::class, 'reinitialiserMotDePasse']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [RessortissantAuthController::class, 'logout']);
        Route::get('/me', [RessortissantAuthController::class, 'me']);
        Route::post('/change-password', [RessortissantAuthController::class, 'changePassword']);
    });
});

// Espace membre (ressortissant connecté)
Route::prefix('v1/ressortissant')->middleware('auth:sanctum')->group(function () {
    Route::get('/demandes/configuration/{type}', [DemandeController::class, 'configuration']);
    Route::get('/demandes', [DemandeController::class, 'index']);
    Route::post('/demandes', [DemandeController::class, 'store']);
    Route::get('/demandes/{id}', [DemandeController::class, 'show']);
    Route::post('/demandes/{id}/documents', [DemandeController::class, 'uploadDocument']);
    Route::delete('/demandes/{id}/documents/{documentId}', [DemandeController::class, 'supprimerDocument']);
});

// Espace admin/agent
Route::prefix('v1/admin/demandes')->middleware(['auth:sanctum', 'role:super_admin,admin,agent'])->group(function () {
    Route::get('/', [DemandeAdminController::class, 'index']);
    Route::get('/{id}', [DemandeAdminController::class, 'show']);
    Route::patch('/{id}/statut', [DemandeAdminController::class, 'changerStatut']);
    Route::patch('/documents/{documentId}', [DemandeAdminController::class, 'verifierDocument']);
});

// Registre consulaire — consultation admin/agent (l'inscription reste en
// self-service côté ressortissant, voir /v1/ressortissant/auth/inscrire).
Route::prefix('v1/admin/ressortissants')->middleware(['auth:sanctum', 'role:super_admin,admin,agent'])->group(function () {
    Route::get('/', [RessortissantAdminController::class, 'index']);
    Route::get('/statistiques', [RessortissantAdminController::class, 'statistiques']);
    Route::get('/export', [RessortissantAdminController::class, 'export']);
    Route::get('/{id}', [RessortissantAdminController::class, 'show']);
});

// Grille tarifaire et pièces requises par type de demande — configuration
// pure, réservée à admin/super_admin (un agent traite des dossiers, il ne
// change pas les tarifs).
Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->prefix('v1/admin')->group(function () {
    Route::apiResource('tarifs', TarifController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('document-types-requis', DocumentTypeRequisController::class)->only(['index', 'store', 'update', 'destroy']);
});