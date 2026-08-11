<?php
declare(strict_types=1);
// Les matchs hors Ligue 1 de la saison 2025-26, point de vue PSG : Supercoupe
// UEFA, Ligue des Champions, Trophée des Champions, Coupe de France. Recopiés
// depuis .superpowers/sdd/fbref-fixtures-raw.csv (source `fbref`, verified).
// Pas de player_match_stats généré pour ces matchs (périmètre estimé réservé
// à la Ligue 1, cf. MigratorStats.php).
// Format : [competition_key, round_label, date Y-m-d, venue, adversaire,
//           buts_psg, buts_adv, possession, affluence,
//           prolongation, tirs_au_but, score_tab (ou null)]
return [
    ['supercoupe', 'UEFA Super Cup',              '2025-08-13', 'neutral', 'Tottenham',            2, 2, 74, 21025, false, true,  '4-3'],
    ['ldc',        'League phase',                '2025-09-17', 'home',    'Atalanta',             4, 0, 67, 47151, false, false, null],
    ['ldc',        'League phase',                '2025-10-01', 'away',    'Barcelona',            2, 1, 53, 50207, false, false, null],
    ['ldc',        'League phase',                '2025-10-21', 'away',    'Leverkusen',           7, 2, 71, 30210, false, false, null],
    ['ldc',        'League phase',                '2025-11-04', 'home',    'Bayern Munich',        1, 2, 71, 45747, false, false, null],
    ['ldc',        'League phase',                '2025-11-26', 'home',    'Tottenham',            5, 3, 67, 47574, false, false, null],
    ['ldc',        'League phase',                '2025-12-10', 'away',    'Athletic Club',        0, 0, 72, 51772, false, false, null],
    ['coupe_france','Round of 64',                '2025-12-20', 'away',    'Vendée Fontenay Foot', 4, 0, 77, 34599, false, false, null],
    ['trophee',    'Trophée des Champions',       '2026-01-08', 'neutral', 'Marseille',            2, 2, 54, 52215, true,  true,  '4-1'],
    ['coupe_france','Round of 32',                '2026-01-12', 'home',    'Paris FC',             0, 1, 70, 47000, false, false, null],
    ['ldc',        'League phase',                '2026-01-20', 'away',    'Sporting CP',          1, 2, 75, 51428, false, false, null],
    ['ldc',        'League phase',                '2026-01-28', 'home',    'Newcastle',            1, 1, 67, 47637, false, false, null],
    ['ldc',        'Knockout phase play-offs',    '2026-02-17', 'away',    'Monaco',               3, 2, 80, 10287, false, false, null],
    ['ldc',        'Knockout phase play-offs',    '2026-02-25', 'home',    'Monaco',               2, 2, 73, 47511, false, false, null],
    ['ldc',        'Round of 16',                 '2026-03-11', 'home',    'Chelsea',              5, 2, 58, 47566, false, false, null],
    ['ldc',        'Round of 16',                 '2026-03-17', 'away',    'Chelsea',              3, 0, 54, 35811, false, false, null],
    ['ldc',        'Quarter-finals',              '2026-04-08', 'home',    'Liverpool',            2, 0, 74, 47511, false, false, null],
    ['ldc',        'Quarter-finals',              '2026-04-14', 'away',    'Liverpool',            2, 0, 47, 59627, false, false, null],
    ['ldc',        'Semi-finals',                 '2026-04-28', 'home',    'Bayern Munich',        5, 4, 43, 47511, false, false, null],
    ['ldc',        'Semi-finals',                 '2026-05-06', 'away',    'Bayern Munich',        1, 1, 35, 75000, false, false, null],
    ['ldc',        'Final',                       '2026-05-30', 'neutral', 'Arsenal',              1, 1, 75, 61035, true,  true,  '4-3'],
];
