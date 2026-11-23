<?php
/**
 * Fonctions utilitaires globales (échappement, URLs, formulaires…).
 */
declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;

/** Lit une valeur de configuration en notation pointée : config('api.key'). */
function config(string $key, mixed $default = null): mixed
{
    $value = App::config();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** Échappe une valeur pour l'affichage HTML. */
function e(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Construit l'URL d'une page de l'application. url('match/12', ['x' => 1]) */
function url(string $path = '', array $query = []): string
{
    $path = trim($path, '/');
    $base = App::basePath();
    if ($path === '') {
        $href = $base . '/';
    } elseif (App::prettyUrls()) {
        $href = $base . '/' . $path;
    } else {
        $href = $base . '/index.php/' . $path;
    }
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    if ($query) {
        $href .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return $href;
}

/** URL d'un fichier statique, avec numéro de version pour invalider le cache navigateur. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT_PATH . '/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return App::basePath() . '/' . $path . '?v=' . $version;
}

/** URL d'un fichier statique sans paramètre de version (manifest, service worker). */
function public_url(string $path): string
{
    return App::basePath() . '/' . ltrim($path, '/');
}

/** Redirection HTTP puis arrêt du script. */
function redirect(string $location, int $status = 302): never
{
    header('Location: ' . $location, true, $status);
    exit;
}

/** Champ caché contenant le jeton CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Valeur précédemment saisie (après une erreur de formulaire). */
function old(string $key, string $default = ''): string
{
    $old = Session::get('_old_input', []);
    return is_array($old) && isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default;
}

/** Chemin + requête de la page courante (pour revenir après connexion). */
function current_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return safe_redirect_target($uri, url());
}

/**
 * N'accepte que des chemins internes (« /scorelive/… ») comme cible de redirection,
 * pour éviter les redirections ouvertes vers un autre site.
 */
function safe_redirect_target(?string $target, string $fallback): string
{
    if (!is_string($target) || $target === '') {
        return $fallback;
    }
    if (preg_match('#[\r\n\\\\]#', $target) || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
        return $fallback;
    }
    $base = App::basePath();
    if ($base !== '' && $target !== $base && !str_starts_with($target, $base . '/')) {
        return $fallback;
    }
    return $target;
}

/** Balise <img> d'un écusson/logo, avec image de remplacement si le chargement échoue. */
function crest(?string $src, int $size = 24, string $class = 'crest', string $alt = ''): string
{
    $placeholder = asset('assets/img/crest-placeholder.svg');
    $src = is_string($src) && preg_match('#^https?://#i', $src) ? $src : $placeholder;
    return sprintf(
        '<img class="%s" src="%s" alt="%s" width="%d" height="%d" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-fallback="%s">',
        e($class),
        e($src),
        e($alt),
        $size,
        $size,
        e($placeholder)
    );
}

/** Pluriel simple : plural(2, 'match', 'matchs') → « 2 matchs ». */
function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count > 1 ? ($pluralForm ?? $singular . 's') : $singular);
}
