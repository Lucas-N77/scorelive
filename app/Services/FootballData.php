<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Client de l'API football-data.org v4, appelé uniquement côté serveur.
 *
 * Chaque ressource passe par le cache MySQL :
 *  1. réponse fraîche en cache → aucune requête HTTP ;
 *  2. sinon, verrou par ressource (une seule requête même si 50 visiteurs arrivent en même temps) ;
 *  3. contrôle du quota local (10 requêtes / 60 s glissantes par défaut) et des en-têtes de l'API ;
 *  4. en cas de quota dépassé, de réseau coupé ou d'API en panne : la dernière réponse connue
 *     est servie (marquée « périmée ») plutôt qu'une page d'erreur.
 */
final class FootballData
{
    private static ?self $instance = null;

    private ApiCache $cache;
    private string $key;
    private string $baseUrl;
    private int $liveTtl;
    private int $staticTtl;
    private int $rateLimit;
    private int $timeout;

    /** @var list<array{endpoint:string, age:int, reason:string, retryAfter:int, message:string}> */
    private array $stale = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function booted(): bool
    {
        return self::$instance !== null;
    }

    private function __construct()
    {
        $this->cache = new ApiCache();
        $this->key = trim((string) config('api.key', ''));
        $this->baseUrl = rtrim((string) config('api.base_url', 'https://api.football-data.org/v4/'), '/') . '/';
        $this->liveTtl = max(5, (int) config('api.cache_ttl', 30));
        $this->staticTtl = max(60, (int) config('api.static_ttl', 86400));
        $this->rateLimit = max(1, (int) config('api.rate_limit', 10));
        $this->timeout = max(2, (int) config('api.timeout', 10));
    }

    /** Une clé est-elle renseignée ? (les valeurs vides ou d'exemple sont ignorées) */
    public function hasKey(): bool
    {
        return preg_match('/^[A-Za-z0-9]{16,64}$/', $this->key) === 1;
    }

    public function liveTtl(): int
    {
        return $this->liveTtl;
    }

    public function cache(): ApiCache
    {
        return $this->cache;
    }

    public function remainingSlots(): int
    {
        return $this->cache->remainingSlots($this->rateLimit);
    }

    /** Réponses servies depuis le cache faute de pouvoir joindre l'API. */
    public function staleNotices(): array
    {
        return $this->stale;
    }

    // ---------------------------------------------------------------------
    // Ressources
    // ---------------------------------------------------------------------

    /** Matchs de toutes les compétitions accessibles sur une plage de dates (Y-m-d). */
    public function matchesBetween(string $dateFrom, string $dateTo): array
    {
        return $this->get('matches', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo], $this->liveTtl);
    }

    public function match(int $id): array
    {
        return $this->get('matches/' . $id, [], $this->liveTtl);
    }

    public function headToHead(int $id, int $limit = 10): array
    {
        return $this->get('matches/' . $id . '/head2head', ['limit' => $limit], 3600);
    }

    public function competitions(): array
    {
        return $this->get('competitions', [], $this->staticTtl);
    }

    public function competition(string $code): array
    {
        return $this->get('competitions/' . rawurlencode($code), [], 3600);
    }

    public function standings(string $code): array
    {
        return $this->get('competitions/' . rawurlencode($code) . '/standings', [], $this->liveTtl);
    }

    public function competitionMatches(string $code, array $filters = []): array
    {
        return $this->get('competitions/' . rawurlencode($code) . '/matches', $filters, $this->liveTtl);
    }

    public function competitionTeams(string $code): array
    {
        return $this->get('competitions/' . rawurlencode($code) . '/teams', [], $this->staticTtl);
    }

    public function team(int $id): array
    {
        return $this->get('teams/' . $id, [], $this->staticTtl);
    }

    public function teamMatches(int $id, array $filters = []): array
    {
        return $this->get('teams/' . $id . '/matches', $filters, $this->liveTtl);
    }

    // ---------------------------------------------------------------------
    // Mécanique de cache / quota
    // ---------------------------------------------------------------------

    public function get(string $path, array $query = [], ?int $ttl = null): array
    {
        if (!$this->hasKey()) {
            throw ApiException::missingKey();
        }
        $ttl ??= $this->liveTtl;
        ksort($query);
        $endpoint = $path . ($query ? '?' . http_build_query($query) : '');
        $key = hash('sha256', $endpoint);

        $cached = $this->cache->get($key);
        if ($cached !== null && $cached['fresh']) {
            return $this->fromCache($cached);
        }

        $locked = $this->cache->lock($key);
        try {
            if ($locked) {
                // Pendant l'attente du verrou, une autre requête a pu rafraîchir la ressource.
                $again = $this->cache->get($key);
                if ($again !== null && $again['fresh']) {
                    return $this->fromCache($again);
                }
                $cached = $again ?? $cached;
            }
            return $this->fetch($endpoint, $key, $ttl, $cached);
        } finally {
            if ($locked) {
                $this->cache->unlock($key);
            }
        }
    }

    private function fetch(string $endpoint, string $key, int $ttl, ?array $cached): array
    {
        [$wait, $requestId] = $this->cache->reserveSlot($this->rateLimit, $endpoint);
        if ($wait > 0) {
            return $this->staleOr($cached, ApiException::quota($wait), $endpoint);
        }

        [$status, $body, $headers, $error] = $this->http($endpoint);
        $this->cache->recordStatus($requestId, $status);

        if ($error !== null) {
            error_log('[ScoreLive] API injoignable (' . $endpoint . ') : ' . $error);
            return $this->staleOr($cached, ApiException::network($error), $endpoint);
        }

        $this->cache->applyRateHeaders($headers);
        $data = json_decode($body, true);
        $apiMessage = is_array($data) && isset($data['message']) && is_string($data['message']) ? $data['message'] : null;

        if ($status === 200 && is_array($data)) {
            $this->cache->put($key, $endpoint, 200, $body, $ttl);
            Indexer::ingest($endpoint, $data);
            return $data;
        }

        if ($status === 429) {
            $retry = $this->retryAfter($headers, $apiMessage);
            $this->cache->blockFor($retry);
            return $this->staleOr($cached, ApiException::quota($retry), $endpoint);
        }

        $exception = $this->errorFor($status, $apiMessage);
        if ($exception->kind === ApiException::RESTRICTED || $exception->kind === ApiException::NOT_FOUND) {
            // Cache négatif : inutile de redemander tout de suite une ressource refusée.
            $this->cache->put($key, $endpoint, $status, $body !== '' ? $body : '{}', $status === 403 ? 600 : 300);
            throw $exception;
        }
        if ($exception->kind === ApiException::SERVER) {
            return $this->staleOr($cached, $exception, $endpoint);
        }
        throw $exception;
    }

    private function fromCache(array $row): array
    {
        $data = json_decode((string) $row['payload'], true);
        if ($row['status_code'] === 200 && is_array($data)) {
            return $data;
        }
        $message = is_array($data) && isset($data['message']) && is_string($data['message']) ? $data['message'] : null;
        throw $this->errorFor($row['status_code'], $message);
    }

    /** Sert la dernière bonne réponse connue, sinon propage l'erreur. */
    private function staleOr(?array $cached, ApiException $e, string $endpoint): array
    {
        if ($cached !== null && $cached['status_code'] === 200) {
            $data = json_decode((string) $cached['payload'], true);
            if (is_array($data)) {
                $this->stale[] = [
                    'endpoint'   => $endpoint,
                    'age'        => $cached['age'],
                    'reason'     => $e->kind,
                    'retryAfter' => $e->retryAfter,
                    'message'    => $e->getMessage(),
                ];
                return $data;
            }
        }
        throw $e;
    }

    private function errorFor(int $status, ?string $apiMessage): ApiException
    {
        $message = strtolower((string) $apiMessage);
        return match (true) {
            // 400 « Your API token is invalid. » ; un 403 signifie « hors abonnement », pas clé invalide.
            $status === 401,
            $status === 400 && str_contains($message, 'token') => ApiException::invalidKey($apiMessage),
            $status === 403 => ApiException::restricted($apiMessage),
            $status === 404 => ApiException::notFound($apiMessage),
            $status === 429 => ApiException::quota(60),
            $status === 400 => ApiException::badRequest($apiMessage),
            default         => ApiException::server($status),
        };
    }

    private function retryAfter(array $headers, ?string $apiMessage): int
    {
        if ($apiMessage !== null && preg_match('/wait (\d+) sec/i', $apiMessage, $m)) {
            return max(1, (int) $m[1]);
        }
        foreach (['retry-after', 'x-requestcounter-reset'] as $name) {
            if (isset($headers[$name]) && ctype_digit((string) $headers[$name])) {
                return max(1, min(3600, (int) $headers[$name]));
            }
        }
        return 60;
    }

    /** @return array{0:int,1:string,2:array<string,string>,3:?string} */
    private function http(string $endpoint): array
    {
        $headers = [];
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'X-Auth-Token: ' . $this->key,
                'Accept: application/json',
                'User-Agent: ScoreLive/1.0',
            ],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $error = $body === false ? (curl_error($ch) ?: 'erreur réseau') : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return [$status, is_string($body) ? $body : '', $headers, $error];
    }
}
