# ScoreLive

**ScoreLive** est une application web de résultats de football en direct : matchs du jour,
scores qui se mettent à jour tout seuls, classements, fiches équipes, favoris et recherche.
Elle s'installe sur l'écran d'accueil du téléphone comme une application (PWA).

Les données proviennent de l'API [football-data.org](https://www.football-data.org) (v4),
appelée **uniquement côté serveur** : la clé d'API ne quitte jamais le serveur.

---

## Fonctionnalités

| | |
|---|---|
| **Matchs du jour** | Matchs groupés par compétition, heure ou minute de jeu, logos, score, statut, badge **LIVE** animé, sélecteur hier / aujourd'hui / demain, filtres Tous / En direct / Terminés / À venir |
| **Direct** | Rafraîchissement automatique toutes les 30 s (fetch → endpoint JSON `/api/live`), la ligne clignote quand le score change, pause quand l'onglet est masqué |
| **Page match** | Score, buteurs, cartons, remplacements, compositions, statistiques, face-à-face, infos (stade, arbitre…) selon ce que fournit votre offre |
| **Page compétition** | Classement (groupes, forme des 5 derniers matchs) et matchs par journée |
| **Page équipe** | Forme, derniers résultats, prochains matchs, effectif, infos du club |
| **Comptes** | Inscription, connexion, déconnexion (`password_hash`, sessions PHP, jeton CSRF, anti force brute) |
| **Favoris** | Équipes et compétitions suivies, page « Mes favoris » avec les matchs du jour |
| **Recherche** | Équipes et compétitions, insensible aux accents, noms français reconnus (« Ligue des champions », « Angleterre »…) |
| **Thème** | Sombre par défaut, clair au choix, mémorisé dans le navigateur |
| **Mobile** | Onglets en bas de l'écran sur mobile, menu en haut sur ordinateur |
| **PWA** | `manifest.json`, service worker (page hors connexion), icônes SVG + PNG |

## Stack technique

- PHP 8 natif (sans framework ni Composer), structure MVC simple, PDO + requêtes préparées
- MySQL : utilisateurs, favoris, cache des réponses de l'API, quota, index de recherche
- HTML, CSS et JavaScript vanilla, responsive mobile-first
- API football-data.org v4 avec cache MySQL de 30 secondes

---

## Installation sous MAMP (Mac)

### 1. Placer le projet

Le dossier doit se trouver dans `/Applications/MAMP/htdocs/scorelive`.

```bash
cd /Applications/MAMP/htdocs
git clone https://github.com/Lucas-N77/scorelive.git
```

### 2. Démarrer MAMP

Ouvrez MAMP et cliquez sur **Start** (Apache sur le port 8888, MySQL sur le port 8889).

### 3. Créer la base de données

Le script crée la base `scorelive` (utf8mb4_unicode_ci) si elle n'existe pas, puis les tables :

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -h 127.0.0.1 -P 8889 -u root -proot < /Applications/MAMP/htdocs/scorelive/database.sql
```

> Selon votre version de MAMP, le client MySQL peut se trouver dans `/Applications/MAMP/Library/bin/mysql57/bin/mysql`.
> Vous pouvez aussi passer par phpMyAdmin (`http://localhost:8888/phpMyAdmin`) › **Importer** › `database.sql`.

### 4. Configurer

```bash
cd /Applications/MAMP/htdocs/scorelive
cp config.example.php config.php
```

Ouvrez `config.php` et collez votre clé d'API (voir ci-dessous) :

```php
'api' => [
    'key' => 'votre-clé-reçue-par-e-mail',
```

`config.php` est exclu de git (`.gitignore`) : votre clé ne sera jamais publiée.
Tant que la clé est vide, l'application affiche une page qui explique où la mettre.

### 5. Ouvrir l'application

<http://localhost:8888/scorelive>

### Obtenir une clé football-data.org

1. Créez un compte gratuit sur <https://www.football-data.org/client/register>.
2. La clé (« API token ») arrive par e-mail.
3. L'offre gratuite couvre 12 compétitions (Ligue 1, Premier League, LaLiga, Serie A, Bundesliga,
   Eredivisie, Primeira Liga, Championship, Brasileirão, Ligue des champions, Euro, Coupe du monde)
   avec 10 requêtes par minute.

---

## Configuration (`config.php`)

| Clé | Par défaut | Rôle |
|---|---|---|
| `app.timezone` | `Europe/Paris` | Fuseau horaire d'affichage des matchs |
| `app.debug` | `false` | `true` : affiche le détail des erreurs (développement uniquement) |
| `app.base_path` | `''` | Chemin public ; vide = détection automatique (`/scorelive`) |
| `app.pretty_urls` | `'auto'` | URLs sans `index.php` si `mod_rewrite` est actif |
| `db.*` | MAMP | Hôte `127.0.0.1`, port `8889`, base `scorelive`, `root` / `root` |
| `api.key` | `''` | **Votre clé football-data.org** |
| `api.cache_ttl` | `30` | Durée du cache (s) des scores, matchs et classements |
| `api.static_ttl` | `86400` | Durée du cache (s) des données quasi fixes (compétitions, fiches équipes) |
| `api.rate_limit` | `10` | Requêtes par minute autorisées par votre offre |
| `api.plans` | `['TIER_ONE']` | Niveaux d'offre accessibles (menu et recherche) |

## Ouvrir l'application sur votre téléphone

1. Le téléphone et le Mac doivent être sur **le même réseau Wi-Fi**.
2. Trouvez l'adresse IP du Mac :
   ```bash
   ipconfig getifaddr en0
   ```
   (ou Réglages Système › Wi-Fi › Détails…).
3. Sur le téléphone, ouvrez `http://ADRESSE-IP-DU-MAC:8888/scorelive`
   (par exemple `http://192.168.1.20:8888/scorelive`).
4. Si rien ne s'affiche : macOS peut bloquer les connexions entrantes. Acceptez la fenêtre
   « Autoriser httpd à accepter les connexions entrantes » ou ajoutez Apache (httpd) dans
   Réglages Système › Réseau › Coupe-feu › Options.
5. **Ajouter à l'écran d'accueil**
   - iPhone (Safari) : bouton Partager › **Sur l'écran d'accueil**
   - Android (Chrome) : menu ⋮ › **Ajouter à l'écran d'accueil**

> Les navigateurs n'activent le service worker (mode hors connexion) qu'en HTTPS ou sur `localhost`.
> Depuis le téléphone en `http://IP:8888`, l'application fonctionne normalement et s'ajoute à l'écran
> d'accueil ; seule la page « hors connexion » n'est pas disponible. Pour une installation PWA complète,
> servez le site en HTTPS (MAMP Pro, tunnel HTTPS, hébergement…).

---

## Respect du quota de l'API

L'offre gratuite limite à 10 requêtes par minute. ScoreLive :

- met chaque réponse en cache dans MySQL (`api_cache`) : 30 s pour les scores, 24 h pour les données fixes ;
- charge **hier, aujourd'hui et demain en une seule requête**, partagée par l'accueil, les favoris et le
  rafraîchissement en direct : quel que soit le nombre de visiteurs, au plus 2 requêtes par minute pour le direct ;
- empêche les requêtes simultanées sur une même ressource (verrou MySQL `GET_LOCK`) ;
- compte ses appels sur 60 s glissantes et lit les en-têtes de quota de l'API ;
- si la limite est atteinte (HTTP 429), si le réseau est coupé ou si l'API est en panne, affiche la dernière
  réponse connue avec un bandeau « données en cache » au lieu d'une erreur.

Les messages d'erreur sont clairs : clé manquante, clé invalide, quota dépassé, compétition hors abonnement.

## Recherche et index local

La recherche interroge un index MySQL (`teams`, `competitions`) alimenté automatiquement par chaque réponse
de l'API, sans requête supplémentaire. Les 12 compétitions gratuites sont préinstallées par `database.sql`.
L'index des équipes se complète au fil de la navigation et des recherches. Pour l'indexer d'un coup (≈ 1 min 30) :

```bash
cd /Applications/MAMP/htdocs/scorelive
/Applications/MAMP/bin/php/php8.3.36/bin/php bin/sync-teams.php
```

## Sécurité

- Requêtes préparées PDO partout (émulation désactivée)
- `htmlspecialchars` sur toutes les données affichées
- Jeton CSRF sur tous les formulaires et appels `fetch` en POST
- Mots de passe hachés (`password_hash` / `password_verify`), session régénérée à la connexion,
  cookie `HttpOnly` + `SameSite=Lax`, limitation à 5 échecs de connexion par IP sur 15 minutes
- En-têtes : Content-Security-Policy avec nonce, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
- Redirections internes uniquement, accès direct bloqué à `app/`, `storage/`, `bin/`, `config.php`, `database.sql`, `.git`
- Clé d'API uniquement côté serveur, `config.php` exclu de git

## Structure du projet

```
scorelive/
├── index.php              Contrôleur frontal
├── config.example.php     Modèle de configuration (config.php est ignoré par git)
├── database.sql           Schéma MySQL + 12 compétitions gratuites
├── manifest.json          Manifeste PWA
├── sw.js                  Service worker
├── offline.html           Page « hors connexion »
├── app/
│   ├── bootstrap.php      Autoloader
│   ├── helpers.php        e(), url(), csrf_field(), icon()…
│   ├── routes.php         Table de routage
│   ├── Core/              App, Router, Request, View, Database, Session, Csrf, Auth
│   ├── Controllers/       Accueil, Match, Compétition, Équipe, Recherche, Auth, Favoris, API JSON
│   ├── Models/            User, Favorite, Team, Competition, LoginAttempt
│   ├── Services/          FootballData (client API), ApiCache, MatchFeed, MatchPresenter, Format, Indexer
│   └── Views/             Gabarit, pages, fragments, pages d'erreur
├── assets/
│   ├── css/app.css        Styles (thèmes sombre et clair)
│   ├── js/app.js          Direct, thème, favoris, recherche, onglets, PWA
│   └── img/               Logo et icônes SVG/PNG
├── bin/sync-teams.php     Indexation complète des équipes (CLI)
└── storage/logs/          Journal des erreurs PHP
```

## URLs propres (facultatif)

Par défaut, les adresses sont de la forme `/scorelive/index.php/match/123`, ce qui fonctionne sans configuration.
Pour obtenir `/scorelive/match/123`, activez `mod_rewrite` dans MAMP (décommentez
`LoadModule rewrite_module modules/mod_rewrite.so` dans `/Applications/MAMP/conf/apache/httpd.conf`,
puis redémarrez). Avec `pretty_urls => 'auto'`, ScoreLive le détecte tout seul grâce au `.htaccess`.

## Limites connues

- Le niveau de détail dépend de votre offre football-data.org : l'offre gratuite fournit les scores, statuts,
  classements et calendriers ; les buteurs, compositions et statistiques peuvent ne pas être fournis
  (la page match l'indique alors clairement).
- Quand l'API ne donne pas la minute de jeu, ScoreLive l'estime à partir de l'heure du coup d'envoi.

## Crédits

Données : [football-data.org](https://www.football-data.org). ScoreLive est un projet indépendant ;
nom, logo et design sont originaux.
