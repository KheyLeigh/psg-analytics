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
        'note'         => 'Bilan, scores, buteurs et discipline Ligue 1 2025-26, point de vue PSG.',
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
