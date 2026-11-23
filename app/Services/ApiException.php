<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Erreur de l'API football-data.org, avec un message clair en français.
 */
final class ApiException extends RuntimeException
{
    public const MISSING_KEY = 'missing_key';
    public const INVALID_KEY = 'invalid_key';
    public const QUOTA       = 'quota';
    public const RESTRICTED  = 'restricted';
    public const NOT_FOUND   = 'not_found';
    public const BAD_REQUEST = 'bad_request';
    public const NETWORK     = 'network';
    public const SERVER      = 'server';

    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly int $retryAfter = 0,
        public readonly ?string $apiMessage = null,
    ) {
        parent::__construct($message);
    }

    public static function missingKey(): self
    {
        return new self(self::MISSING_KEY, 'Aucune clé d’API football-data.org n’est configurée dans config.php.');
    }

    public static function invalidKey(?string $apiMessage = null): self
    {
        return new self(
            self::INVALID_KEY,
            'La clé d’API est refusée par football-data.org. Vérifiez qu’elle est copiée sans espace ni caractère en trop dans config.php.',
            0,
            $apiMessage
        );
    }

    public static function quota(int $retryAfter): self
    {
        $retryAfter = max(1, $retryAfter);
        return new self(
            self::QUOTA,
            sprintf('La limite de requêtes de l’API est atteinte (10 par minute avec l’offre gratuite). Nouvel essai possible dans %d s.', $retryAfter),
            $retryAfter
        );
    }

    public static function restricted(?string $apiMessage = null): self
    {
        return new self(
            self::RESTRICTED,
            'Ce contenu n’est pas inclus dans votre abonnement football-data.org (l’offre gratuite couvre 12 compétitions).',
            0,
            $apiMessage
        );
    }

    public static function notFound(?string $apiMessage = null): self
    {
        return new self(self::NOT_FOUND, 'Ressource introuvable sur football-data.org.', 0, $apiMessage);
    }

    public static function badRequest(?string $apiMessage = null): self
    {
        return new self(self::BAD_REQUEST, 'L’API a refusé la requête (paramètres invalides).', 0, $apiMessage);
    }

    public static function network(string $detail): self
    {
        return new self(self::NETWORK, 'Impossible de joindre l’API football-data.org. Vérifiez la connexion Internet du Mac.', 30, $detail);
    }

    public static function server(int $status): self
    {
        return new self(self::SERVER, sprintf('L’API football-data.org est momentanément indisponible (HTTP %d).', $status), 30);
    }

    public function title(): string
    {
        return match ($this->kind) {
            self::MISSING_KEY => 'Clé d’API requise',
            self::INVALID_KEY => 'Clé d’API invalide',
            self::QUOTA       => 'Limite de requêtes atteinte',
            self::RESTRICTED  => 'Contenu non inclus dans votre offre',
            self::NOT_FOUND   => 'Introuvable',
            self::BAD_REQUEST => 'Requête refusée',
            self::NETWORK     => 'API injoignable',
            default           => 'API indisponible',
        };
    }

    /** Code HTTP renvoyé au navigateur pour cette erreur. */
    public function pageStatus(): int
    {
        return match ($this->kind) {
            self::QUOTA       => 429,
            self::RESTRICTED  => 403,
            self::NOT_FOUND   => 404,
            self::BAD_REQUEST => 400,
            self::INVALID_KEY => 502,
            default           => 503,
        };
    }
}
