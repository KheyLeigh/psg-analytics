<?php
declare(strict_types=1);
// Les 34 matchs de Ligue 1 2025-26, point de vue PSG, recopiés verbatim
// depuis .superpowers/sdd/verified-l1-2025-26.md (source : captures d'écran
// fournies par Mathis). round_label != ordre chronologique (journées
// reportées J26-J31) : les deux champs sont donc conservés distinctement.
// Format : [round_label, date Y-m-d, adversaire, est_domicile, buts_psg, buts_adv, affluence]
return [
    ['J1',  '2025-08-17', 'Nantes',     false, 1, 0, null],
    ['J2',  '2025-08-22', 'Angers',     true,  1, 0, null],
    ['J3',  '2025-08-30', 'Toulouse',   false, 6, 3, null],
    ['J4',  '2025-09-14', 'Lens',       true,  2, 0, null],
    ['J5',  '2025-09-22', 'Marseille',  false, 0, 1, null],
    ['J6',  '2025-09-27', 'Auxerre',    true,  2, 0, null],
    ['J7',  '2025-10-05', 'Lille',      false, 1, 1, null],
    ['J8',  '2025-10-17', 'Strasbourg', true,  3, 3, null],
    ['J9',  '2025-10-25', 'Brest',      false, 3, 0, null],
    ['J10', '2025-10-29', 'Lorient',    false, 1, 1, null],
    ['J11', '2025-11-01', 'Nice',       true,  1, 0, null],
    ['J12', '2025-11-09', 'Lyon',       false, 3, 2, null],
    ['J13', '2025-11-22', 'Le Havre',   true,  3, 0, null],
    ['J14', '2025-11-29', 'Monaco',     false, 0, 1, null],
    ['J15', '2025-12-06', 'Rennes',     true,  5, 0, null],
    ['J16', '2025-12-13', 'Metz',       false, 3, 2, null],
    ['J17', '2026-01-04', 'Paris FC',   true,  2, 1, null],
    ['J18', '2026-01-16', 'Lille',      true,  3, 0, null],
    ['J19', '2026-01-23', 'Auxerre',    false, 1, 0, null],
    ['J20', '2026-02-01', 'Strasbourg', false, 2, 1, null],
    ['J21', '2026-02-08', 'Marseille',  true,  5, 0, null],
    ['J22', '2026-02-13', 'Rennes',     false, 1, 3, null],
    ['J23', '2026-02-21', 'Metz',       true,  3, 0, null],
    ['J24', '2026-02-28', 'Le Havre',   false, 1, 0, null],
    ['J25', '2026-03-06', 'Monaco',     true,  1, 3, null],
    ['J26', '2026-04-22', 'Nantes',     true,  3, 0, null],
    ['J27', '2026-03-21', 'Nice',       false, 4, 0, null],
    ['J28', '2026-04-03', 'Toulouse',   true,  3, 1, null],
    ['J29', '2026-05-13', 'Lens',       false, 2, 0, null],
    ['J30', '2026-04-19', 'Lyon',       true,  1, 2, null],
    ['J31', '2026-04-25', 'Angers',     false, 3, 0, null],
    ['J32', '2026-05-02', 'Lorient',    true,  2, 2, null],
    ['J33', '2026-05-10', 'Brest',      true,  1, 0, null],
    ['J34', '2026-05-17', 'Paris FC',   false, 1, 2, null],
];
