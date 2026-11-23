<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Mise en forme en français : dates, statuts, minutes de jeu, scores, libellés de l'API.
 */
final class Format
{
    public const LIVE_STATUSES     = ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT', 'LIVE'];
    public const FINISHED_STATUSES = ['FINISHED', 'AWARDED'];
    public const UPCOMING_STATUSES = ['SCHEDULED', 'TIMED'];

    private const DAYS        = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const DAYS_SHORT  = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    private const MONTHS      = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** Pays / zones renvoyés en anglais par l'API. */
    public const AREAS = [
        'Africa' => 'Afrique', 'Algeria' => 'Algérie', 'Argentina' => 'Argentine', 'Asia' => 'Asie',
        'Australia' => 'Australie', 'Austria' => 'Autriche', 'Belgium' => 'Belgique', 'Bolivia' => 'Bolivie',
        'Brazil' => 'Brésil', 'Cameroon' => 'Cameroun', 'Canada' => 'Canada', 'Chile' => 'Chili',
        'China PR' => 'Chine', 'Colombia' => 'Colombie', 'Croatia' => 'Croatie', 'Czech Republic' => 'Tchéquie',
        'Czechia' => 'Tchéquie', 'Denmark' => 'Danemark', 'Ecuador' => 'Équateur', 'Egypt' => 'Égypte',
        'England' => 'Angleterre', 'Europe' => 'Europe', 'Finland' => 'Finlande', 'France' => 'France',
        'Germany' => 'Allemagne', 'Ghana' => 'Ghana', 'Greece' => 'Grèce', 'Hungary' => 'Hongrie',
        'Iceland' => 'Islande', 'Iran' => 'Iran', 'Ireland' => 'Irlande', 'Republic of Ireland' => 'Irlande',
        'Italy' => 'Italie', 'Ivory Coast' => 'Côte d’Ivoire', "Côte d'Ivoire" => 'Côte d’Ivoire', 'Japan' => 'Japon',
        'Korea Republic' => 'Corée du Sud', 'South Korea' => 'Corée du Sud', 'Mexico' => 'Mexique',
        'Morocco' => 'Maroc', 'Netherlands' => 'Pays-Bas', 'Nigeria' => 'Nigeria', 'North America' => 'Amérique du Nord',
        'Northern Ireland' => 'Irlande du Nord', 'Norway' => 'Norvège', 'Paraguay' => 'Paraguay', 'Peru' => 'Pérou',
        'Poland' => 'Pologne', 'Portugal' => 'Portugal', 'Qatar' => 'Qatar', 'Romania' => 'Roumanie',
        'Russia' => 'Russie', 'Saudi Arabia' => 'Arabie saoudite', 'Scotland' => 'Écosse', 'Senegal' => 'Sénégal',
        'Serbia' => 'Serbie', 'Slovakia' => 'Slovaquie', 'Slovenia' => 'Slovénie', 'South America' => 'Amérique du Sud',
        'Spain' => 'Espagne', 'Sweden' => 'Suède', 'Switzerland' => 'Suisse', 'Tunisia' => 'Tunisie',
        'Turkey' => 'Turquie', 'Türkiye' => 'Turquie', 'Ukraine' => 'Ukraine', 'United States' => 'États-Unis',
        'USA' => 'États-Unis', 'Uruguay' => 'Uruguay', 'Venezuela' => 'Venezuela', 'Wales' => 'Pays de Galles',
        'World' => 'Monde',
    ];

    /** Noms d'usage en français pour quelques compétitions (par code). */
    public const COMPETITION_NAMES = [
        'CL'  => 'Ligue des champions',
        'EL'  => 'Ligue Europa',
        'ECL' => 'Ligue Conférence',
        'WC'  => 'Coupe du monde',
        'EC'  => 'Euro',
        'PD'  => 'LaLiga',
        'BSA' => 'Brasileirão Série A',
        'CLI' => 'Copa Libertadores',
    ];

    // ---------------------------------------------------------------------
    // Dates
    // ---------------------------------------------------------------------

    public static function tz(): DateTimeZone
    {
        static $tz = null;
        return $tz ??= new DateTimeZone(date_default_timezone_get());
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::tz());
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', self::tz());
    }

    /** Date ISO de l'API (UTC) → date dans le fuseau de l'application. */
    public static function date(?string $iso): ?DateTimeImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($iso, new DateTimeZone('UTC')))->setTimezone(self::tz());
        } catch (Throwable) {
            return null;
        }
    }

    public static function time(?DateTimeInterface $d): string
    {
        return $d ? $d->format('H:i') : '--:--';
    }

    /** « lun. 23 nov. » */
    public static function dayShort(DateTimeInterface $d): string
    {
        return self::DAYS_SHORT[(int) $d->format('w')] . ' ' . $d->format('j') . ' ' . self::MONTHS_SHORT[(int) $d->format('n') - 1];
    }

    /** « lundi 23 novembre 2026 » */
    public static function dayLong(DateTimeInterface $d): string
    {
        $day = (int) $d->format('j');
        return self::DAYS[(int) $d->format('w')] . ' ' . ($day === 1 ? '1er' : $day) . ' ' . self::MONTHS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** « 23 nov. 2026 » */
    public static function dateMedium(DateTimeInterface $d): string
    {
        return $d->format('j') . ' ' . self::MONTHS_SHORT[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** « 23/11 » */
    public static function dayMonth(DateTimeInterface $d): string
    {
        return $d->format('d/m');
    }

    /** « Aujourd'hui », « Demain », « Hier » ou « lun. 23 nov. » */
    public static function relativeDay(DateTimeInterface $d): string
    {
        $diff = (int) self::today()->diff((new DateTimeImmutable($d->format('Y-m-d'), self::tz())))->format('%r%a');
        return match ($diff) {
            0       => 'Aujourd’hui',
            1       => 'Demain',
            -1      => 'Hier',
            default => ucfirst(self::dayShort($d)),
        };
    }

    public static function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 10    => 'à l’instant',
            $seconds < 60    => 'il y a ' . $seconds . ' s',
            $seconds < 3600  => 'il y a ' . intdiv($seconds, 60) . ' min',
            $seconds < 86400 => 'il y a ' . intdiv($seconds, 3600) . ' h',
            default          => 'il y a ' . intdiv($seconds, 86400) . ' j',
        };
    }

    public static function age(?string $dateOfBirth): ?int
    {
        if (!$dateOfBirth) {
            return null;
        }
        try {
            return (new DateTimeImmutable($dateOfBirth))->diff(self::today())->y;
        } catch (Throwable) {
            return null;
        }
    }

    /** Saison « 2026-2027 » (ou « 2026 ») à partir des dates de début et de fin. */
    public static function season(?array $season): string
    {
        $start = substr((string) ($season['startDate'] ?? ''), 0, 4);
        $end = substr((string) ($season['endDate'] ?? ''), 0, 4);
        if ($start === '') {
            return '';
        }
        return ($end !== '' && $end !== $start) ? $start . '-' . $end : $start;
    }

    // ---------------------------------------------------------------------
    // Statuts et scores
    // ---------------------------------------------------------------------

    /** live | finished | upcoming | off */
    public static function state(string $status): string
    {
        return match (true) {
            in_array($status, self::LIVE_STATUSES, true)     => 'live',
            in_array($status, self::FINISHED_STATUSES, true) => 'finished',
            in_array($status, self::UPCOMING_STATUSES, true) => 'upcoming',
            default                                          => 'off',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'SCHEDULED'        => 'Programmé',
            'TIMED'            => 'À venir',
            'IN_PLAY', 'LIVE'  => 'En cours',
            'PAUSED'           => 'Mi-temps',
            'EXTRA_TIME'       => 'Prolongations',
            'PENALTY_SHOOTOUT' => 'Tirs au but',
            'FINISHED'         => 'Terminé',
            'AWARDED'          => 'Sur tapis vert',
            'SUSPENDED'        => 'Suspendu',
            'POSTPONED'        => 'Reporté',
            'CANCELLED'        => 'Annulé',
            default            => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }

    /**
     * Minute de jeu sans apostrophe (« 67 », « 45+2 ») ou null.
     * Utilise la minute fournie par l'API ; à défaut, l'estime depuis le coup d'envoi.
     */
    public static function minute(array $m): ?string
    {
        $status = (string) ($m['status'] ?? '');
        if (!in_array($status, ['IN_PLAY', 'LIVE', 'EXTRA_TIME'], true)) {
            return null;
        }
        if (isset($m['minute']) && is_numeric($m['minute']) && (int) $m['minute'] > 0) {
            $minute = (int) $m['minute'];
            $injury = isset($m['injuryTime']) && is_numeric($m['injuryTime']) ? (int) $m['injuryTime'] : 0;
            return $injury > 0 ? $minute . '+' . $injury : (string) $minute;
        }
        if ($status === 'EXTRA_TIME') {
            return null;
        }
        $kickoff = self::date($m['utcDate'] ?? null);
        if ($kickoff === null) {
            return null;
        }
        $elapsed = intdiv(max(0, time() - $kickoff->getTimestamp()), 60) + 1;
        return match (true) {
            $elapsed <= 45 => (string) $elapsed,
            $elapsed <= 60 => '45+',
            $elapsed <= 105 => (string) ($elapsed - 15),
            default        => '90+',
        };
    }

    /** Ce qu'affiche la colonne « horloge » d'un match en direct : « 67' », « MT »… */
    public static function liveLabel(array $m): string
    {
        $minute = self::minute($m);
        if ($minute !== null) {
            return $minute . '’';
        }
        return match ((string) ($m['status'] ?? '')) {
            'PAUSED'           => isset($m['minute']) && (int) $m['minute'] > 90 ? 'Pause' : 'MT',
            'EXTRA_TIME'       => 'Prol.',
            'PENALTY_SHOOTOUT' => 'T.a.b.',
            default            => 'Live',
        };
    }

    /** Score affiché [domicile, extérieur] (hors tirs au but). */
    public static function scores(array $m): array
    {
        $score = is_array($m['score'] ?? null) ? $m['score'] : [];
        $home = $score['fullTime']['home'] ?? null;
        $away = $score['fullTime']['away'] ?? null;

        // L'API v4 peut cumuler les tirs au but dans fullTime : on recompose temps réglementaire + prolongation.
        if (($score['duration'] ?? '') === 'PENALTY_SHOOTOUT' && isset($score['regularTime']['home'], $score['regularTime']['away'])) {
            $home = (int) $score['regularTime']['home'] + (int) ($score['extraTime']['home'] ?? 0);
            $away = (int) $score['regularTime']['away'] + (int) ($score['extraTime']['away'] ?? 0);
        }
        return [$home === null ? null : (int) $home, $away === null ? null : (int) $away];
    }

    public static function halfTime(array $m): ?array
    {
        $ht = $m['score']['halfTime'] ?? null;
        return isset($ht['home'], $ht['away']) ? [(int) $ht['home'], (int) $ht['away']] : null;
    }

    public static function penalties(array $m): ?array
    {
        $p = $m['score']['penalties'] ?? null;
        return isset($p['home'], $p['away']) ? [(int) $p['home'], (int) $p['away']] : null;
    }

    /** V (victoire), N (nul), D (défaite) du point de vue de l'équipe $teamId. */
    public static function resultFor(array $m, int $teamId): ?string
    {
        if (self::state((string) ($m['status'] ?? '')) !== 'finished') {
            return null;
        }
        $winner = $m['score']['winner'] ?? null;
        if ($winner === null) {
            [$h, $a] = self::scores($m);
            if ($h === null || $a === null) {
                return null;
            }
            $winner = $h === $a ? 'DRAW' : ($h > $a ? 'HOME_TEAM' : 'AWAY_TEAM');
        }
        if ($winner === 'DRAW') {
            return 'N';
        }
        $isHome = (int) ($m['homeTeam']['id'] ?? 0) === $teamId;
        return ($winner === 'HOME_TEAM') === $isHome ? 'V' : 'D';
    }

    // ---------------------------------------------------------------------
    // Libellés
    // ---------------------------------------------------------------------

    public static function stage(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '';
        }
        $labels = [
            'FINAL' => 'Finale', 'THIRD_PLACE' => 'Match pour la 3e place', 'SEMI_FINALS' => 'Demi-finales',
            'QUARTER_FINALS' => 'Quarts de finale', 'LAST_16' => 'Huitièmes de finale', 'LAST_32' => 'Seizièmes de finale',
            'LAST_64' => '32es de finale', 'ROUND_4' => '4e tour', 'ROUND_3' => '3e tour', 'ROUND_2' => '2e tour',
            'ROUND_1' => '1er tour', 'GROUP_STAGE' => 'Phase de groupes', 'LEAGUE_STAGE' => 'Phase de ligue',
            'PRELIMINARY_ROUND' => 'Tour préliminaire', 'QUALIFICATION' => 'Qualifications',
            'QUALIFICATION_ROUND_1' => '1er tour de qualification', 'QUALIFICATION_ROUND_2' => '2e tour de qualification',
            'QUALIFICATION_ROUND_3' => '3e tour de qualification', 'PLAYOFF_ROUND_1' => 'Barrages (1er tour)',
            'PLAYOFF_ROUND_2' => 'Barrages (2e tour)', 'PLAYOFFS' => 'Barrages', 'REGULAR_SEASON' => 'Saison régulière',
            'CLAUSURA' => 'Clausura', 'APERTURA' => 'Apertura', 'CHAMPIONSHIP' => 'Poule pour le titre',
            'CHAMPIONSHIP_ROUND' => 'Poule pour le titre', 'RELEGATION' => 'Poule de relégation',
            'RELEGATION_ROUND' => 'Poule de relégation',
        ];
        return $labels[$stage] ?? ucfirst(strtolower(str_replace('_', ' ', $stage)));
    }

    public static function group(?string $group): string
    {
        if ($group === null || $group === '') {
            return '';
        }
        return preg_match('/^GROUP_([A-Z0-9]+)$/', $group, $m) ? 'Groupe ' . $m[1] : ucfirst(strtolower(str_replace('_', ' ', $group)));
    }

    public static function area(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        return self::AREAS[$name] ?? $name;
    }

    /** Noms anglais des zones dont le nom français correspond à la recherche. */
    public static function areasMatching(string $q): array
    {
        $needle = self::normalize($q);
        if (mb_strlen($needle) < 2) {
            return [];
        }
        $found = [];
        foreach (self::AREAS as $english => $french) {
            if (str_contains(self::normalize($french), $needle)) {
                $found[] = $english;
            }
        }
        return $found;
    }

    public static function competitionName(?array $competition): string
    {
        if (!$competition) {
            return '';
        }
        $code = (string) ($competition['code'] ?? '');
        return self::COMPETITION_NAMES[$code] ?? (string) ($competition['name'] ?? '');
    }

    /** Codes des compétitions dont le nom français correspond à la recherche. */
    public static function competitionCodesMatching(string $q): array
    {
        $needle = self::normalize($q);
        if (mb_strlen($needle) < 2) {
            return [];
        }
        $codes = [];
        foreach (self::COMPETITION_NAMES as $code => $name) {
            if (str_contains(self::normalize($name), $needle)) {
                $codes[] = $code;
            }
        }
        return $codes;
    }

    public static function teamName(?array $team, bool $short = true): string
    {
        if (!$team) {
            return 'À déterminer';
        }
        $name = $short ? ($team['shortName'] ?? $team['short_name'] ?? null) : null;
        $name = $name ?: ($team['name'] ?? null);
        return $name ? (string) $name : 'À déterminer';
    }

    public static function position(?string $position): string
    {
        if ($position === null || $position === '') {
            return '';
        }
        $labels = [
            'Goalkeeper' => 'Gardien', 'Defence' => 'Défenseur', 'Defender' => 'Défenseur', 'Centre-Back' => 'Défenseur central',
            'Left-Back' => 'Latéral gauche', 'Right-Back' => 'Latéral droit', 'Midfield' => 'Milieu', 'Midfielder' => 'Milieu',
            'Defensive Midfield' => 'Milieu défensif', 'Central Midfield' => 'Milieu central', 'Attacking Midfield' => 'Milieu offensif',
            'Left Midfield' => 'Milieu gauche', 'Right Midfield' => 'Milieu droit', 'Offence' => 'Attaquant', 'Attacker' => 'Attaquant',
            'Forward' => 'Attaquant', 'Left Winger' => 'Ailier gauche', 'Right Winger' => 'Ailier droit',
            'Centre-Forward' => 'Avant-centre', 'Second Striker' => 'Second attaquant',
        ];
        return $labels[$position] ?? $position;
    }

    /** Ligne de jeu (pour regrouper un effectif) : G, D, M, A ou ? */
    public static function positionGroup(?string $position): string
    {
        $p = strtolower((string) $position);
        return match (true) {
            $p === ''                                                     => '?',
            str_contains($p, 'goal')                                      => 'G',
            str_contains($p, 'back') || str_contains($p, 'defen')         => 'D',
            str_contains($p, 'midfield')                                  => 'M',
            str_contains($p, 'wing') || str_contains($p, 'forward')
                || str_contains($p, 'offence') || str_contains($p, 'striker')
                || str_contains($p, 'attack')                             => 'A',
            default                                                       => '?',
        };
    }

    public static function refereeRole(?string $type): string
    {
        return match ((string) $type) {
            'REFEREE'                    => 'Arbitre',
            'ASSISTANT_REFEREE_N1',
            'ASSISTANT_REFEREE_N2',
            'ASSISTANT_REFEREE_N3'       => 'Arbitre assistant',
            'FOURTH_OFFICIAL'            => 'Quatrième arbitre',
            'VIDEO_ASSISTANT_REFEREE_N1',
            'VIDEO_ASSISTANT_REFEREE_N2',
            'VIDEO_ASSISTANT_REFEREE_N3' => 'Arbitre vidéo (VAR)',
            default                      => 'Officiel',
        };
    }

    public static function statLabel(string $key): string
    {
        $labels = [
            'ball_possession' => 'Possession (%)', 'shots' => 'Tirs', 'shots_on_goal' => 'Tirs cadrés',
            'shots_off_goal' => 'Tirs non cadrés', 'corner_kicks' => 'Corners', 'free_kicks' => 'Coups francs',
            'goal_kicks' => 'Six mètres', 'offsides' => 'Hors-jeu', 'fouls' => 'Fautes', 'saves' => 'Arrêts',
            'throw_ins' => 'Touches', 'yellow_cards' => 'Cartons jaunes', 'yellow_red_cards' => 'Doubles jaunes',
            'red_cards' => 'Cartons rouges',
        ];
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** Minuscules sans accents, pour comparer des libellés. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                return (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
        }
        return strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'ù' => 'u',
            'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n',
        ]);
    }
}
