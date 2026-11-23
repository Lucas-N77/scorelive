<?php
/**
 * ScoreLive : modèle de configuration.
 *
 * 1. Copiez ce fichier en « config.php » (même dossier).
 * 2. Collez votre clé football-data.org dans api.key.
 * 3. Adaptez les accès MySQL si besoin (valeurs par défaut de MAMP ci-dessous).
 *
 * config.php est ignoré par git (.gitignore) : votre clé ne sera jamais publiée.
 */
return [
    'app' => [
        'name'        => 'ScoreLive',
        'timezone'    => 'Europe/Paris',
        // true : affiche le détail des erreurs PHP (à réserver au développement)
        'debug'       => false,
        // Chemin public de l'appli. Vide = détection automatique (ex. « /scorelive »)
        'base_path'   => '',
        // URLs propres sans « index.php » : 'auto' (si mod_rewrite est actif), true ou false
        'pretty_urls' => 'auto',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 8889,          // port MySQL de MAMP
        'name'    => 'scorelive',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    'api' => [
        // Votre clé personnelle football-data.org (https://www.football-data.org/client/register)
        'key'        => '',
        'base_url'   => 'https://api.football-data.org/v4/',
        // Durée de vie du cache (en secondes) des données qui bougent : scores, matchs, classements
        'cache_ttl'  => 30,
        // Durée de vie du cache des données quasi fixes : liste des compétitions, fiches équipes
        'static_ttl' => 86400,
        // Nombre de requêtes par minute autorisées par votre offre (offre gratuite : 10)
        'rate_limit' => 10,
        // Niveaux d'offre accessibles (offre gratuite : TIER_ONE = 12 compétitions)
        'plans'      => ['TIER_ONE'],
        // Délai maximal d'une requête HTTP vers l'API (secondes)
        'timeout'    => 10,
    ],
];
