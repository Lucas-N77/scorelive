<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimaliste : motifs « /match/{id:\d+} » → [Contrôleur, méthode].
 * Toutes les requêtes POST sont protégées par le jeton CSRF.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, handler:array{0:class-string,1:string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $methodMatches = $route['method'] === $method || ($method === 'HEAD' && $route['method'] === 'GET');
            if (!$methodMatches) {
                $allowed[] = $route['method'];
                continue;
            }

            if ($method === 'POST') {
                Csrf::guard();
            }

            $params = array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class())->{$action}(...$params);
            return;
        }

        if ($allowed) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($allowed)));
            }
            throw new HttpException(405, 'Méthode non autorisée pour cette adresse.');
        }

        throw new HttpException(404, 'La page demandée n’existe pas.');
    }
}
