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

/** Icône SVG en ligne (trait 24×24, couleur héritée du texte). */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'ball'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7.2l3.9 2.8-1.5 4.6H9.6L8.1 10z"/><path d="M12 7.2V3.3M15.9 10l3.7-1.3M14.4 14.6l2.3 3.3M9.6 14.6l-2.3 3.3M8.1 10L4.4 8.7"/>',
        'live'     => '<path d="M2.5 12h4l2.5-6.5 4.5 13 2.5-6.5h5.5"/>',
        'star'     => '<path d="M12 3.4l2.65 5.38 5.93.86-4.29 4.18 1.01 5.9L12 16.93l-5.3 2.79 1.01-5.9-4.29-4.18 5.93-.86z"/>',
        'search'   => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="M20 20l-4.6-4.6"/>',
        'user'     => '<circle cx="12" cy="8.3" r="3.6"/><path d="M4.8 20c1.3-3.7 4-5.6 7.2-5.6s5.9 1.9 7.2 5.6"/>',
        'sun'      => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.6v2.1M12 19.3v2.1M4.7 4.7l1.5 1.5M17.8 17.8l1.5 1.5M2.6 12h2.1M19.3 12h2.1M4.7 19.3l1.5-1.5M17.8 6.2l1.5-1.5"/>',
        'moon'     => '<path d="M19.8 14.6A8.2 8.2 0 0 1 9.4 4.2a8.2 8.2 0 1 0 10.4 10.4z"/>',
        'left'     => '<path d="M15 5.5L8.5 12l6.5 6.5"/>',
        'right'    => '<path d="M9 5.5l6.5 6.5L9 18.5"/>',
        'calendar' => '<rect x="3.8" y="5" width="16.4" height="15.2" rx="2.4"/><path d="M3.8 10h16.4M8.5 3v4M15.5 3v4"/>',
        'trophy'   => '<path d="M7.8 4h8.4v5.2a4.2 4.2 0 0 1-8.4 0z"/><path d="M7.8 6H4.6a3.2 3.2 0 0 0 3.4 4M16.2 6h3.2a3.2 3.2 0 0 1-3.4 4M12 13.4V17M8.4 20.4h7.2M9.8 17h4.4"/>',
        'shield'   => '<path d="M12 3l7.2 2.9v5.3c0 4.6-3.1 8.3-7.2 9.9-4.1-1.6-7.2-5.3-7.2-9.9V5.9z"/>',
        'stadium'  => '<ellipse cx="12" cy="7.8" rx="8.4" ry="3.2"/><path d="M3.6 7.8v8.2c0 1.8 3.8 3.2 8.4 3.2s8.4-1.4 8.4-3.2V7.8M12 11v8.2"/>',
        'swap'     => '<path d="M5 8.5h13.5L15 5M19 15.5H5.5L9 19"/>',
        'logout'   => '<path d="M14.5 4h3.3A2.2 2.2 0 0 1 20 6.2v11.6a2.2 2.2 0 0 1-2.2 2.2h-3.3"/><path d="M10 7.8L5.8 12l4.2 4.2M6 12h10"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.9h.01"/>',
        'alert'    => '<path d="M12 3.6L2.6 20h18.8z"/><path d="M12 9.8v4.6M12 17.2h.01"/>',
        'key'      => '<circle cx="7.8" cy="15.6" r="4.2"/><path d="M10.8 12.6l8.4-8.4M16.2 7.2l2.4 2.4M13.8 9.6l2.1 2.1"/>',
        'check'    => '<path d="M4.8 12.6l4.6 4.6L19.2 7.4"/>',
        'x'        => '<path d="M6.2 6.2l11.6 11.6M17.8 6.2L6.2 17.8"/>',
        'refresh'  => '<path d="M19.6 10.6A7.8 7.8 0 0 0 5.6 7.4L4 9M4 4.5V9h4.5M4.4 13.4a7.8 7.8 0 0 0 14 3.2L20 15M20 19.5V15h-4.5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>',
        'list'     => '<path d="M9 6.5h11M9 12h11M9 17.5h11M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01"/>',
        'whistle'  => '<circle cx="9" cy="14.5" r="5"/><path d="M11.5 10.2L20.5 6v4.4l-5.8 1.6M6.5 5.5l1 2M10.5 4.5l-.2 2.2"/>',
        'users'    => '<circle cx="9" cy="8.6" r="3.3"/><path d="M3 19.6c1-3.3 3.3-5 6-5s5 1.7 6 5"/><path d="M15.6 5.6a3.2 3.2 0 0 1 0 6.2M17.6 14.9c1.6.6 2.8 2.1 3.4 4.4"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3z"/>',
        'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
        'home'     => '<path d="M3.8 10.6L12 3.8l8.2 6.8v8.6a1 1 0 0 1-1 1h-4.6v-6H9.4v6H4.8a1 1 0 0 1-1-1z"/>',
    ];
    $svg = $paths[$name] ?? $paths['info'];
    $classes = trim('icon icon--' . $name . ' ' . $class);
    return '<svg class="' . e($classes) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/** Pluriel simple : plural(2, 'match', 'matchs') → « 2 matchs ». */
function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count > 1 ? ($pluralForm ?? $singular . 's') : $singular);
}
