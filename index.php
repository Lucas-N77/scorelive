<?php
/**
 * ScoreLive : contrôleur frontal.
 * Toutes les pages passent par ce fichier (index.php/chemin ou URL réécrite).
 */
declare(strict_types=1);

define('ROOT_PATH', __DIR__);

require ROOT_PATH . '/app/bootstrap.php';

App\Core\App::run();
