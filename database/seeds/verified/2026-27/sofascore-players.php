<?php
declare(strict_types=1);
// Correspondance identifiant joueur Sofascore vers la clé utilisée par
// players_l1_fbref.php (nom de famille, ou prénom pour un mononyme). Relevé
// le 2026-11-03 via /api/v1/team/1644/players, en croisant chaque nom avec
// l'effectif de players.php : la clé ci-dessous est toujours celle déjà en
// usage dans players_l1_fbref.php, jamais l'orthographe Sofascore (qui
// diffère parfois : "Ilya Zabarnyi" chez Sofascore contre "Illia Zabarnyi"
// dans nos seeds, même joueur).
//
// Pourquoi passer par un identifiant et non par le nom : même motif que
// fotmob-players.php, à la différence que la correspondance ci-dessous a pu
// être construite entièrement par recoupement de noms (effectif complet,
// pas de tir ambigu à résoudre un par un) plutôt que relevée match par match.
//
// Adam Mouak (identifiant Sofascore 1861688) apparaît dans l'effectif
// Sofascore de l'équipe mais PAS dans database/seeds/verified/2026-27/players.php :
// absent de la correspondance ci-dessous, à signaler dans le rapport
// (écart d'effectif) plutôt qu'à deviner.
//
// À compléter si l'effectif change : un identifiant Sofascore sans
// correspondance ici est écarté ET signalé par sofascore_map_player_totals(),
// jamais rattaché au hasard.
return [
    // playerId Sofascore => clé players_l1_fbref.php
    996953  => 'Chevalier',
    800749  => 'Safonov',
    1823902 => 'Longoni',
    814594  => 'Hakimi',
    1108441 => 'Beraldo',
    155995  => 'Marquinhos',
    1023567 => 'Zabarnyi',
    96538   => 'Digne',
    352370  => 'Hernandez',
    989768  => 'Mendes',
    979480  => 'Pacho',
    784655  => 'Ruiz',
    902029  => 'Vitinha',
    1473491 => 'Mayulu',
    1926085 => 'Fernández',
    1142672 => 'Zaïre-Emery',
    1190933 => 'Neves',
    889259  => 'Kvaratskhelia',
    855833  => 'Torres',
    818244  => 'Dembélé',
    1130939 => 'Akliouche',
    1154605 => 'Doué',
    1149146 => 'Godts',
    1861665 => 'Ndjantou',
];
