<?php
/**
 * ScoreLive : indexe d'un coup les équipes de toutes les compétitions accessibles,
 * pour que la recherche trouve n'importe quel club dès le départ.
 *
 * Usage (depuis le dossier du projet) :
 *   /Applications/MAMP/bin/php/php8.3.36/bin/php bin/sync-teams.php
 *
 * Le script respecte le quota de l'API (une requête toutes les ~7 s). Facultatif :
 * sans lui, l'index se remplit tout seul au fil de la navigation et des recherches.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/bootstrap.php';

use App\Core\App;
use App\Models\Competition;
use App\Models\Team;
use App\Services\ApiException;
use App\Services\FootballData;

App::boot();

$api = FootballData::instance();
if (!$api->hasKey()) {
    fwrite(STDERR, "Clé d'API manquante : renseignez api.key dans config.php.\n");
    exit(1);
}

foreach (Competition::accessible() as $competition) {
    if (empty($competition['code'])) {
        continue;
    }
    echo str_pad((string) $competition['name'], 34) . ' ';

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $data = $api->competitionTeams((string) $competition['code']);
            echo count($data['teams'] ?? []) . " équipes\n";
            break;
        } catch (ApiException $e) {
            if ($e->kind === ApiException::QUOTA && $attempt < 3) {
                echo "(quota atteint, pause de {$e->retryAfter} s) ";
                sleep($e->retryAfter + 1);
                continue;
            }
            echo 'ignorée : ' . $e->getMessage() . "\n";
            break;
        }
    }
    sleep(7);
}

echo 'Index de recherche : ' . Team::count() . " équipes.\n";
