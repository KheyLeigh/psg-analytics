<?php
declare(strict_types=1);
// Les 34 matchs de Ligue 1 2025-26, point de vue PSG. Score, adversaire et
// domicile/extérieur recopiés verbatim depuis verified-l1-2025-26.md
// (captures d'écran fournies par Mathis). Affluence et possession réelles
// recoupées depuis .superpowers/sdd/fbref-fixtures-raw.csv (FBref, source
// `fbref` dans sources.php), appariées par journée + adversaire + date.
// round_label != ordre chronologique (journées reportées J26-J31) : les
// deux champs sont donc conservés distinctement.
// Format : [round_label, date Y-m-d, adversaire, est_domicile, buts_psg, buts_adv, affluence, possession]
return [
    ['J1',  '2025-08-17', 'Nantes',     false, 1, 0, 34053, 71],
    ['J2',  '2025-08-22', 'Angers',     true,  1, 0, 47401, 83],
    ['J3',  '2025-08-30', 'Toulouse',   false, 6, 3, 31515, 76],
    ['J4',  '2025-09-14', 'Lens',       true,  2, 0, 47642, 68],
    ['J5',  '2025-09-22', 'Marseille',  false, 0, 1, 66190, 68],
    ['J6',  '2025-09-27', 'Auxerre',    true,  2, 0, 47792, 66],
    ['J7',  '2025-10-05', 'Lille',      false, 1, 1, 47355, 62],
    ['J8',  '2025-10-17', 'Strasbourg', true,  3, 3, 47754, 71],
    ['J9',  '2025-10-25', 'Brest',      false, 3, 0, 14978, 75],
    ['J10', '2025-10-29', 'Lorient',    false, 1, 1, 16706, 78],
    ['J11', '2025-11-01', 'Nice',       true,  1, 0, 47629, 77],
    ['J12', '2025-11-09', 'Lyon',       false, 3, 2, 58257, 72],
    ['J13', '2025-11-22', 'Le Havre',   true,  3, 0, 47694, 65],
    ['J14', '2025-11-29', 'Monaco',     false, 0, 1, 11546, 57],
    ['J15', '2025-12-06', 'Rennes',     true,  5, 0, 47823, 66],
    ['J16', '2025-12-13', 'Metz',       false, 3, 2, 28500, 59],
    ['J17', '2026-01-04', 'Paris FC',   true,  2, 1, 47000, 70],
    ['J18', '2026-01-16', 'Lille',      true,  3, 0, 46909, 61],
    ['J19', '2026-01-23', 'Auxerre',    false, 1, 0, 17254, 69],
    ['J20', '2026-02-01', 'Strasbourg', false, 2, 1, 31324, 68],
    ['J21', '2026-02-08', 'Marseille',  true,  5, 0, 47926, 58],
    ['J22', '2026-02-13', 'Rennes',     false, 1, 3, 28545, 68],
    ['J23', '2026-02-21', 'Metz',       true,  3, 0, 7501,  67],
    ['J24', '2026-02-28', 'Le Havre',   false, 1, 0, 23569, 68],
    ['J25', '2026-03-06', 'Monaco',     true,  1, 3, 46806, 72],
    ['J26', '2026-04-22', 'Nantes',     true,  3, 0, 47388, 70],
    ['J27', '2026-03-21', 'Nice',       false, 4, 0, 28815, 74],
    ['J28', '2026-04-03', 'Toulouse',   true,  3, 1, 47710, 72],
    ['J29', '2026-05-13', 'Lens',       false, 2, 0, 38139, 62],
    ['J30', '2026-04-19', 'Lyon',       true,  1, 2, 47926, 77],
    ['J31', '2026-04-25', 'Angers',     false, 3, 0, 14977, 71],
    ['J32', '2026-05-02', 'Lorient',    true,  2, 2, 47926, 69],
    ['J33', '2026-05-10', 'Brest',      true,  1, 0, 47752, 67],
    ['J34', '2026-05-17', 'Paris FC',   false, 1, 2, 19237, 63],
];
