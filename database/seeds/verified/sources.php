<?php
declare(strict_types=1);
// Sources de données, traçabilité obligatoire (spec section 3).
// `key` permet une résolution stable indépendante de l'ordre d'insertion.
return [
    [
        'key'          => 'l1_screenshots',
        'label'        => "Captures d'écran application de suivi de matchs (fournies par Mathis)",
        'url'          => null,
        'collected_at' => '2026-07-20',
        'confidence'   => 'verified',
        'note'         => 'Bilan, scores, buteurs et discipline Ligue 1 2025-26, point de vue PSG. '
            . 'Score et bilan recoupés et confirmés par la source fbref (24V/4N/6D, 74-29).',
    ],
    [
        'key'          => 'fbref',
        'label'        => 'FBref : journal des matchs PSG 2025-26 (toutes compétitions)',
        'url'          => 'https://fbref.com/en/squads/e2d8892c/2025-2026/matchlogs/all_comps/schedule/',
        'collected_at' => '2026-08-11',
        'confidence'   => 'verified',
        'note'         => 'Export CSV FBref (database/seeds/verified/fbref-fixtures-2025-26.csv) : score, possession, '
            . 'affluence, tirs au but et prolongation pour chaque match de la saison, toutes compétitions confondues.',
    ],
    [
        'key'          => 'fbref_players_l1',
        'label'        => 'FBref : statistiques standard par joueur, Ligue 1 2025-26',
        'url'          => 'https://fbref.com/en/squads/e2d8892c/2025-2026/dom_lig/Paris-Saint-Germain-Ligue-1-Stats',
        'collected_at' => '2026-08-11',
        'confidence'   => 'verified',
        'note'         => 'Export CSV FBref (database/seeds/verified/fbref-players-l1-2025-26.csv) : matchs joués, '
            . 'titularisations, minutes, buts, passes décisives, penalties et cartons par joueur, Ligue 1 uniquement. '
            . 'Remplace les ancrages estimés de scorers_l1.php / discipline_l1.php (supprimés).',
    ],
    [
        'key'          => 'squad_screenshots',
        'label'        => "Captures d'écran application de suivi (onglet Équipe, fournies par Mathis)",
        'url'          => null,
        'collected_at' => '2026-08-03',
        'confidence'   => 'verified',
        'note'         => 'Bilan individuel toutes compétitions 2025-26 (apparitions, buts, passes, cartons), joueurs de champ.',
    ],
    [
        'key'          => 'stat_generator',
        'label'        => 'StatGenerator (générateur déterministe, graine 2026)',
        'url'          => null,
        'collected_at' => '2026-07-20',
        'confidence'   => 'estimated',
        'note'         => 'Répartition des buts, minutes et notes par match, calibrée sur les totaux vérifiés.',
    ],
];
