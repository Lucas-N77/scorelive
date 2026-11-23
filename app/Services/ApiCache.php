<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Stockage MySQL du cache de l'API et du quota de requêtes.
 *
 * - api_cache    : réponses JSON (fraîches ou périmées, gardées 7 jours en secours)
 * - api_requests : horodatage de chaque appel sortant (fenêtre glissante de 60 s)
 * - api_state    : blocage temporaire après un HTTP 429 ou un compteur à zéro
 */
final class ApiCache
{
    private const QUOTA_LOCK = 'scorelive:quota';

    public function get(string $key): ?array
    {
        $row = Database::fetch(
            'SELECT endpoint, status_code, payload, fetched_at,
                    (expires_at > UTC_TIMESTAMP()) AS fresh,
                    TIMESTAMPDIFF(SECOND, fetched_at, UTC_TIMESTAMP()) AS age
               FROM api_cache WHERE cache_key = ?',
            [$key]
        );
        if ($row === null) {
            return null;
        }
        $row['fresh'] = (bool) $row['fresh'];
        $row['age'] = max(0, (int) $row['age']);
        $row['status_code'] = (int) $row['status_code'];
        return $row;
    }

    public function put(string $key, string $endpoint, int $status, string $payload, int $ttl): void
    {
        Database::run(
            'INSERT INTO api_cache (cache_key, endpoint, status_code, payload, fetched_at, expires_at)
             VALUES (:k, :e, :s, :p, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL :ttl SECOND)
             ON DUPLICATE KEY UPDATE endpoint = VALUES(endpoint), status_code = VALUES(status_code),
                 payload = VALUES(payload), fetched_at = VALUES(fetched_at), expires_at = VALUES(expires_at)',
            ['k' => $key, 'e' => substr($endpoint, 0, 255), 's' => $status, 'p' => $payload, 'ttl' => max(1, $ttl)]
        );
    }

    /** Verrou MySQL nommé : une seule requête HTTP à la fois pour une même ressource. */
    public function lock(string $key, int $timeout = 8): bool
    {
        return (int) Database::value('SELECT GET_LOCK(?, ?)', [$this->lockName($key), $timeout]) === 1;
    }

    public function unlock(string $key): void
    {
        Database::value('SELECT RELEASE_LOCK(?)', [$this->lockName($key)]);
    }

    private function lockName(string $key): string
    {
        return 'scorelive:' . substr($key, 0, 40);
    }

    /**
     * Réserve un créneau d'appel si le quota le permet.
     *
     * @return array{0:int,1:int} [secondes à attendre (0 = autorisé), id de l'appel enregistré]
     */
    public function reserveSlot(int $limitPerMinute, string $endpoint): array
    {
        $locked = (int) Database::value('SELECT GET_LOCK(?, 3)', [self::QUOTA_LOCK]) === 1;
        try {
            $wait = $this->secondsUntilAllowed($limitPerMinute);
            if ($wait > 0) {
                return [$wait, 0];
            }
            Database::run(
                'INSERT INTO api_requests (endpoint, requested_at) VALUES (?, UTC_TIMESTAMP(3))',
                [substr($endpoint, 0, 255)]
            );
            $id = Database::lastInsertId();
        } finally {
            if ($locked) {
                Database::value('SELECT RELEASE_LOCK(?)', [self::QUOTA_LOCK]);
            }
        }

        if (random_int(1, 50) === 1) {
            $this->purge();
        }
        return [0, $id];
    }

    /** Secondes avant le prochain appel autorisé (0 = tout de suite). */
    public function secondsUntilAllowed(int $limitPerMinute): int
    {
        $now = time();
        $blockedUntil = (int) $this->state('blocked_until', '0');
        if ($blockedUntil > $now) {
            return $blockedUntil - $now;
        }

        $row = Database::fetch(
            'SELECT COUNT(*) AS n,
                    TIMESTAMPDIFF(MICROSECOND, MIN(requested_at), UTC_TIMESTAMP(3)) AS oldest_us
               FROM api_requests
              WHERE requested_at > UTC_TIMESTAMP(3) - INTERVAL 60 SECOND'
        );
        $count = (int) ($row['n'] ?? 0);
        if ($count < $limitPerMinute) {
            return 0;
        }
        $elapsed = (int) floor(((int) ($row['oldest_us'] ?? 0)) / 1_000_000);
        return max(1, 60 - $elapsed);
    }

    /** Appels restants dans la fenêtre glissante de 60 s. */
    public function remainingSlots(int $limitPerMinute): int
    {
        if ((int) $this->state('blocked_until', '0') > time()) {
            return 0;
        }
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM api_requests WHERE requested_at > UTC_TIMESTAMP(3) - INTERVAL 60 SECOND'
        );
        return max(0, $limitPerMinute - $count);
    }

    public function recordStatus(int $requestId, int $status): void
    {
        if ($requestId > 0) {
            Database::run('UPDATE api_requests SET status_code = ? WHERE id = ?', [$status, $requestId]);
        }
    }

    /** Exploite les en-têtes de quota renvoyés par football-data.org. */
    public function applyRateHeaders(array $headers): void
    {
        $available = null;
        foreach ($headers as $name => $value) {
            if (preg_match('/^x-requests?-?available/', (string) $name)) {
                $available = (int) $value;
            }
        }
        $reset = isset($headers['x-requestcounter-reset']) ? (int) $headers['x-requestcounter-reset'] : 0;
        if ($available !== null && $available <= 0 && $reset > 0) {
            $this->blockFor(min($reset, 86400));
        }
    }

    public function blockFor(int $seconds): void
    {
        $this->setState('blocked_until', (string) (time() + max(1, $seconds)));
    }

    public function state(string $name, string $default = ''): string
    {
        $value = Database::value('SELECT value FROM api_state WHERE name = ?', [$name]);
        return $value === null ? $default : (string) $value;
    }

    public function setState(string $name, string $value): void
    {
        Database::run(
            'INSERT INTO api_state (name, value, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$name, $value]
        );
    }

    /** Ménage : journal d'appels > 1 jour, cache périmé depuis plus de 7 jours. */
    public function purge(): void
    {
        Database::run('DELETE FROM api_requests WHERE requested_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
        Database::run('DELETE FROM api_cache WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
        Database::run('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }
}
