<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Erreur HTTP « métier » (404, 403, 405…) affichée proprement à l'utilisateur.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
