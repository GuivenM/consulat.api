# Consulat Congo-Bénin — API

Backend Laravel de la plateforme du **Consulat Honoraire de la République du Congo au Bénin** : site public (actualités, guide, contact), **espace consulaire** des ressortissants (inscription, demandes de carte consulaire / laissez-passer, suivi de dossier) et **espace d'administration** pour les agents du consulat. Sert le frontend [consulat.app](https://github.com/GuivenM/consulat.app) via une API REST versionnée (`/api/v1/...`).

> Le projet est né d'un clone de l'API de l'AJDCB. Il en reste quelques traces (voir « Points d'attention »).

## Stack

- **Laravel 12** / PHP 8.2+
- **Laravel Sanctum** — tokens d'API (admins/agents et ressortissants, deux espaces distincts)
- **intervention/image** — compression des images
- **fedapay/fedapay-php** — installé pour le futur paiement en ligne, **non utilisé** aujourd'hui (paiement au guichet uniquement)
- MySQL (les requêtes utilisent `FIELD()`, propre à MySQL/MariaDB)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# renseigner la base de données et le mail dans .env, puis :
php artisan migrate
php artisan db:seed                                    # tarifs et pièces requises
php artisan storage:link
php artisan serve
```

L'entité `congo-benin` est créée par la migration `create_entities_table`. Le seeding (`ConsulatCongoBeninSeeder`) ajoute la grille de tarifs et la liste des pièces requises ; les deux sont ensuite modifiables depuis l'écran **Configuration** de l'admin.

### Créer le premier super administrateur

Les comptes suivants se créent depuis l'admin (**Utilisateurs → Nouvel utilisateur**, réservé au `super_admin`, avec email d'activation). Le tout premier compte, lui, se crée à la main :

```bash
php artisan tinker
>>> \App\Models\User::create([
...   'nom' => 'NOM', 'prenom' => 'Prénom', 'email' => 'vous@exemple.com',
...   'password' => \Hash::make('un-mot-de-passe-solide'),
...   'role' => 'super_admin', 'est_actif' => true,
... ]);
```

> `DatabaseSeeder` ne crée aucun compte (il appelle seulement `ConsulatCongoBeninSeeder`) : sans danger en production. `TestAdminSeeder` crée un super admin de test au mot de passe connu (`Test1234!`) ; il refuse de s'exécuter en production, mais si un compte `test@ajdcb.org` existe déjà sur un serveur, supprimez-le.

## Variables d'environnement

| Variable | Rôle |
|---|---|
| `APP_URL` | Adresse réelle de l'API. Sert à générer les URLs de fichiers : un mauvais port ou domaine casse l'affichage des images. |
| `FRONTEND_URL` | URL du frontend ; utilisée dans les liens des emails (activation, vérification, suivi de dossier). |
| `CORS_ALLOWED_ORIGINS` | Origines autorisées, séparées par des virgules (défaut : `http://localhost:5173`). **À régler sur le domaine du site en production.** |
| `SANCTUM_STATEFUL_DOMAINS` | À adapter si le frontend n'est pas sur `localhost:5173`. |
| `CONSULAT_DEFAULT_ENTITY_SLUG` | Entité utilisée quand le contexte ne permet pas d'en déduire une autre (défaut : `congo-benin`). |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | Envoi des emails. En local, `MAIL_MAILER=log` écrit les mails dans `storage/logs/laravel.log` au lieu de les envoyer. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Expéditeur de tous les emails. |
| `MAIL_ADMIN_ADDRESS` | Destinataire des alertes « nouveau message de contact ». À défaut, l'email de l'entité est utilisé ; sans aucun des deux, l'alerte n'est pas envoyée (un avertissement est écrit dans le log). |
| `FACEBOOK_PAGE_ID` / `FACEBOOK_PAGE_ACCESS_TOKEN` | Facultatif : active le bouton « Publier sur Facebook » des actualités. |
| `FEDAPAY_*` | Réservé au futur paiement en ligne — inutile aujourd'hui. |

## Multi-entités

Toutes les données métier appartiennent à une **entité** (`entities`) : un consulat ou une ambassade. En V1 il n'en existe qu'une, mais le code est déjà prêt pour en accueillir d'autres :

- le trait `BelongsToEntity` applique un scope global `entity` sur les modèles et renseigne `entity_id` à la création ;
- `App\Support\CurrentEntity` résout l'entité de la requête, dans cet ordre : valeur forcée (tests, artisan) → paramètre `?entity=<slug>` → entité de l'utilisateur authentifié → entité par défaut ;
- conséquence : un agent ne voit jamais les données d'une autre entité, même en devinant un ID. Pour un traitement volontairement multi-entités, utiliser `::withoutGlobalScope('entity')`.

## Rôles

| Rôle | Peut faire |
|---|---|
| `super_admin` | Tout, dont la gestion des **utilisateurs** et la consultation du **journal d'activité**. Non rattaché à une entité. |
| `admin` | Traiter les demandes, encaisser, gérer le contenu (actualités, guide, partenaires, messages), configurer tarifs et pièces requises. |
| `agent` | Traiter les demandes (vérifier les pièces, changer le statut), encaisser au guichet, consulter le registre. Lecture seule sur les messages, actualités, guide et partenaires. |
| ressortissant | Espace consulaire uniquement (`/v1/ressortissant/...`) : son profil et ses propres demandes. |

Contrôlé par le middleware `role:...` (`CheckRole`) sur les routes protégées.

## Le parcours d'une demande

1. **Inscription** du ressortissant (`/v1/ressortissant/auth/inscrire`) → email de vérification → compte actif dès la vérification, sans validation préalable par l'admin. Le numéro de registre (`COG-BEN-AAAA-NNNNN`) est attribué à cette vérification.
2. **Dépôt d'une demande** (carte consulaire ou laissez-passer) avec un délai (3 jours, 24 h, même jour). Le montant est déterminé par la grille de tarifs ; le formulaire de pièces est piloté par `document_types_requis`.
3. **Vérification des pièces** par un agent, une par une : validée ou rejetée avec un motif. Le ressortissant peut redéposer une pièce rejetée.
4. **Paiement au guichet** enregistré par l'agent (mode, n° de reçu). Le champ `demandes.paiement_statut` est un miroir de la table `paiements`, recalculé par `PaiementObserver` — jamais modifié à la main.
5. **Progression du statut** : `recu` → `en_traitement` → `pret` → `retire`, avec `rejete` possible depuis n'importe quelle étape non terminale. Le passage à `pret` exige **toutes les pièces obligatoires validées et le paiement complet**.

### Emails envoyés au ressortissant

| Événement | Email |
|---|---|
| Inscription / mot de passe oublié | Lien de vérification ou de réinitialisation |
| Dossier passé en `en_traitement`, `pret` ou `rejete` | `DemandeStatutChange` (avec motif pour un rejet) |
| Pièce rejetée | `PieceRejetee` (avec libellé et motif) |

Les notifications partent depuis des observers (`DemandeObserver`, `DemandeDocumentObserver`) via `App\Support\MailRessortissant`. Un échec d'envoi ne bloque jamais l'action de l'agent : il est journalisé. Chaque tentative laisse une ligne dans `storage/logs/laravel.log` (`Notification envoyée`, `ignorée` ou `Envoi email ressortissant échoué`) — **premier réflexe si un client dit n'avoir rien reçu**. Les envois sont synchrones (pas de worker de queue requis).

Autres emails : accès admin/agent (activation, réinitialisation), et formulaire de contact public (confirmation à l'expéditeur, alerte à l'admin, réponse de l'admin).

## Modèle de données

| Modèle | Rôle |
|---|---|
| `Entity` | Consulat/ambassade : nom, coordonnées, slug, ville et pays d'accueil. |
| `User` | Comptes admin/agent (`super_admin`, `admin`, `agent`), avec activation par lien envoyé par email. |
| `Ressortissant` | Compte et fiche du ressortissant (état civil, pièce d'identité, adresse, contact d'urgence, `numero_registre`). |
| `Demande` | Une demande consulaire : type, délai, statut, montant, `paiement_statut`, dates de dépôt/disponibilité/retrait, `donnees_specifiques` (JSON). |
| `DemandeDocument` | Une pièce jointe d'une demande, avec son statut (`en_attente`, `valide`, `rejete`) et le motif éventuel. |
| `DocumentTypeRequis` | Pièces attendues par type de demande (obligatoire ou non, formats, taille max, nombre requis). |
| `Tarif` | Montant par type de demande et par délai. |
| `Paiement` | Encaissements d'une demande (canal `guichet`, mode, n° de reçu, payeur). |
| `JournalActivite` | Trace des actions des agents. |
| `Message` | Formulaire de contact public (objets : `question`, `service_consulaire`, `partenariat`, `urgence`, `autre`), avec réponse et suivi de lecture. |
| `Actualite` / `ActualitePhoto` | Articles du site. |
| `GuideSection` / `GuideSousSection` / `GuideDocument` | Guide pratique à trois niveaux, documents téléchargeables. |
| `Partenaire`, `NewsletterAbonne` | Partenaires du consulat ; abonnés à la newsletter. |

## Routes (`routes/api.php`)

- **Publiques** (`/api/v1/...`) : contact (`POST /messages`), lecture des actualités, du guide et des partenaires, inscription à la newsletter, statistiques publiques, carte et services publics.
- **Auth admin/agent** (`/api/auth/...`) : connexion, activation du compte, mot de passe oublié.
- **Auth ressortissant** (`/api/v1/ressortissant/auth/...`) : inscription, vérification d'email, connexion, mot de passe oublié/réinitialisation, profil.
- **Espace ressortissant** (`/api/v1/ressortissant/demandes...`) : configuration d'un type de demande, création, liste, détail, dépôt et suppression de pièces.
- **Admin/agent** (`/api/v1/admin/...`, rôles `super_admin`/`admin`/`agent`) : liste et détail des demandes, changement de statut, vérification des pièces, encaissement au guichet, registre des ressortissants (liste, statistiques, carte, export).
- **Admin/administrateur** (`super_admin`/`admin`) : tarifs et pièces requises (CRUD).
- **`super_admin` seul** : gestion des utilisateurs (`/api/v1/utilisateurs`) et journal d'activité.

Voir `php artisan route:list` pour le détail à jour.

## Stockage des fichiers

- **Pièces des dossiers** (pièces d'identité, photos…) : disque `local` **privé** (`storage/app/private/demandes/{entité}/{demande}/`). Jamais servies publiquement : elles ne sortent que par `GET /v1/admin/demandes/documents/{id}/fichier`, derrière l'authentification et le scope d'entité. Ne pas exposer ce dossier sur le web.
- **Images publiques** (actualités, partenaires, guide) : disque `public`, servies via `storage:link` et `ImageController`.

**Piège Windows/WAMP** : `storage:link` crée un lien symbolique ; s'il échoue silencieusement, relancer le terminal en administrateur ou activer le Mode développeur Windows.

## Mise en production

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

À vérifier avant d'ouvrir au public :

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` défini.
- `APP_URL`, `FRONTEND_URL` et `CORS_ALLOWED_ORIGINS` sur les vrais domaines.
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` et `MAIL_ADMIN_ADDRESS` renseignés (sinon l'expéditeur retombe sur les valeurs par défaut du code).
- Aucun compte de test (`test@ajdcb.org`) présent en base.
- Après un changement de `.env` avec le cache activé : `php artisan config:clear`.

## Points d'attention

- **Paiement en ligne non disponible.** Le `PaiementController` et le webhook FedaPay hérités de l'AJDCB (cotisations, billets d'événements) ont été retirés : ils référençaient des modèles supprimés. `FedaPayService` et `Paiement::CANAUX` sont conservés pour reconstruire le paiement en ligne des demandes consulaires. Seul l'encaissement au guichet fonctionne aujourd'hui.
- **Pas de notification push**, uniquement des emails.
- **Tests** : seuls les `ExampleTest` de Laravel existent. Le workflow statut/paiement/pièces et l'isolation entre entités sont à couvrir en priorité.
- **Reliquats de l'AJDCB** : il ne reste que des commentaires historiques dans certains modèles et migrations, et l'email du compte de `TestAdminSeeder`. Sans effet fonctionnel.
- **Conventions** : les modèles exposent leurs accesseurs calculés via `protected $appends` ; un accesseur absent de cette liste est invisible dans le JSON. Les contrôleurs construisent leurs données à partir des champs **validés** uniquement, jamais de `$request->all()`, pour empêcher l'injection de champs comme `statut` ou `traite_par`.
