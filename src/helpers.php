<?php
declare(strict_types=1);

// ============================================================
//  Fonctions d'affichage partagées entre les pages publiques
//  (index.php, offre.php...). Centralisées ici pour éviter
//  la duplication et les divergences entre les pages.
// ============================================================

if (!function_exists('formatSalaire')) {
    function formatSalaire(?float $min, ?float $max): string
    {
        if (!$min && !$max) return 'Salaire non précisé';
        if ($min && $max)   return number_format($min, 0, ',', ' ') . ' – ' . number_format($max, 0, ',', ' ') . ' €';
        if ($min)           return 'À partir de ' . number_format($min, 0, ',', ' ') . ' €';
        return "Jusqu'à " . number_format($max, 0, ',', ' ') . ' €';
    }
}

if (!function_exists('badgeContrat')) {
    function badgeContrat(string $type): string
    {
        return match($type) {
            'CDI'        => 'badge-blue',
            'CDD'        => 'badge-blue',
            'Stage'      => 'badge-amber',
            'Alternance' => 'badge-amber',
            'Freelance'  => 'badge-purple',
            default      => 'badge-gray',
        };
    }
}

if (!function_exists('badgeTeletravail')) {
    function badgeTeletravail(string $t): string
    {
        return match($t) {
            'Full remote' => 'badge-green',
            'Hybride'     => 'badge-green',
            default       => 'badge-amber',
        };
    }
}

if (!function_exists('dateRelative')) {
    function dateRelative(string $date): string
    {
        $diff = time() - strtotime($date);
        if ($diff < 86400)    return "Aujourd'hui";
        if ($diff < 172800)   return "Hier";
        if ($diff < 604800)   return "Il y a " . round($diff / 86400)  . " jours";
        if ($diff < 2592000)  return "Il y a " . round($diff / 604800) . " semaines";
        return "Il y a " . round($diff / 2592000) . " mois";
    }
}
