<?php
declare(strict_types=1);
// Statistiques Ligue 1 2025-26 vérifiées par joueur (source fbref_players_l1,
// cf. sources.php). Trois tables FBref « squad » agrégées : Standard Stats
// (matchs, minutes, buts, passes, cartons), Shooting (tirs, tirs cadrés) et
// Miscellaneous Stats (tacles gagnés). Chaque total ci-dessous est exact pour
// la saison, pas une projection ; seule leur répartition match par match reste
// déterministe/estimée (cf. MigratorStats).
// Le total buts joueurs (73) est inférieur d'exactement 1 au total buts d'équipe
// L1 (74, cf. matches_l1.php) : un but contre son camp adverse, crédité à PSG,
// n'est attribué à aucun joueur (voir SeedIntegrityTest).
// « tacles gagnés » (TklW) sert d'axe défensif au radar : FBref ne fournit pas
// les duels aériens ni les dribbles gagnés pour cette saison de L1, l'axe est
// donc nommé « Tacles gagnés » et non « Duels gagnés » (transparence).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
// Format : mp (matchs joués), starts (titularisations), minutes, goals, assists,
// pk (penalties transformés, déjà inclus dans goals), yellow, red, shots (tirs),
// sot (tirs cadrés), tackles (tacles gagnés).
return [
    'Zaïre-Emery'   => ['mp' => 32, 'starts' => 27, 'minutes' => 2447, 'goals' => 3,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0, 'shots' => 19, 'sot' => 7,  'tackles' => 29],
    'Zabarnyi'      => ['mp' => 28, 'starts' => 26, 'minutes' => 2387, 'goals' => 1,  'assists' => 0, 'pk' => 0, 'yellow' => 6, 'red' => 0, 'shots' => 4,  'sot' => 3,  'tackles' => 20],
    'Vitinha'       => ['mp' => 29, 'starts' => 24, 'minutes' => 2119, 'goals' => 1,  'assists' => 7, 'pk' => 0, 'yellow' => 2, 'red' => 0, 'shots' => 34, 'sot' => 10, 'tackles' => 20],
    'Pacho'         => ['mp' => 23, 'starts' => 21, 'minutes' => 1914, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 8,  'sot' => 1,  'tackles' => 26],
    'Barcola'       => ['mp' => 29, 'starts' => 21, 'minutes' => 1743, 'goals' => 11, 'assists' => 1, 'pk' => 0, 'yellow' => 2, 'red' => 0, 'shots' => 66, 'sot' => 30, 'tackles' => 16],
    'Mayulu'        => ['mp' => 26, 'starts' => 21, 'minutes' => 1672, 'goals' => 4,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0, 'shots' => 35, 'sot' => 11, 'tackles' => 31],
    'Hernandez'     => ['mp' => 25, 'starts' => 20, 'minutes' => 1747, 'goals' => 0,  'assists' => 3, 'pk' => 0, 'yellow' => 4, 'red' => 0, 'shots' => 11, 'sot' => 3,  'tackles' => 14],
    'Beraldo'       => ['mp' => 20, 'starts' => 18, 'minutes' => 1567, 'goals' => 2,  'assists' => 1, 'pk' => 0, 'yellow' => 2, 'red' => 0, 'shots' => 14, 'sot' => 4,  'tackles' => 21],
    'Lee'           => ['mp' => 27, 'starts' => 18, 'minutes' => 1519, 'goals' => 3,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0, 'shots' => 37, 'sot' => 15, 'tackles' => 9],
    'Kvaratskhelia' => ['mp' => 28, 'starts' => 18, 'minutes' => 1479, 'goals' => 8,  'assists' => 4, 'pk' => 1, 'yellow' => 1, 'red' => 0, 'shots' => 65, 'sot' => 29, 'tackles' => 13],
    'Chevalier'     => ['mp' => 17, 'starts' => 17, 'minutes' => 1530, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0,  'tackles' => 0],
    'Doué'          => ['mp' => 23, 'starts' => 16, 'minutes' => 1348, 'goals' => 7,  'assists' => 4, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 55, 'sot' => 19, 'tackles' => 20],
    'Hakimi'        => ['mp' => 18, 'starts' => 15, 'minutes' => 1375, 'goals' => 2,  'assists' => 2, 'pk' => 0, 'yellow' => 3, 'red' => 1, 'shots' => 27, 'sot' => 9,  'tackles' => 13],
    'Safonov'       => ['mp' => 15, 'starts' => 15, 'minutes' => 1350, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0,  'tackles' => 0],
    'Ramos'         => ['mp' => 30, 'starts' => 13, 'minutes' => 1318, 'goals' => 6,  'assists' => 1, 'pk' => 1, 'yellow' => 4, 'red' => 1, 'shots' => 58, 'sot' => 25, 'tackles' => 14],
    'Neves'         => ['mp' => 21, 'starts' => 13, 'minutes' => 1287, 'goals' => 5,  'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 27, 'sot' => 6,  'tackles' => 14],
    'Mendes'        => ['mp' => 20, 'starts' => 13, 'minutes' => 1256, 'goals' => 4,  'assists' => 5, 'pk' => 1, 'yellow' => 1, 'red' => 0, 'shots' => 30, 'sot' => 15, 'tackles' => 20],
    'Ruiz'          => ['mp' => 20, 'starts' => 13, 'minutes' => 1131, 'goals' => 1,  'assists' => 4, 'pk' => 0, 'yellow' => 2, 'red' => 0, 'shots' => 19, 'sot' => 4,  'tackles' => 11],
    'Dembélé'       => ['mp' => 22, 'starts' => 11, 'minutes' => 1063, 'goals' => 10, 'assists' => 7, 'pk' => 2, 'yellow' => 0, 'red' => 0, 'shots' => 45, 'sot' => 18, 'tackles' => 3],
    'Marquinhos'    => ['mp' => 14, 'starts' => 11, 'minutes' => 1052, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 3,  'sot' => 0,  'tackles' => 9],
    'Mbaye'         => ['mp' => 24, 'starts' => 10, 'minutes' => 1006, 'goals' => 3,  'assists' => 2, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 24, 'sot' => 10, 'tackles' => 7],
    'Fernández'     => ['mp' => 12, 'starts' => 7,  'minutes' => 682,  'goals' => 1,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 9,  'sot' => 3,  'tackles' => 5],
    'Ndjantou'      => ['mp' => 10, 'starts' => 3,  'minutes' => 360,  'goals' => 1,  'assists' => 1, 'pk' => 0, 'yellow' => 1, 'red' => 0, 'shots' => 9,  'sot' => 3,  'tackles' => 1],
    'Marin'         => ['mp' => 2,  'starts' => 2,  'minutes' => 180,  'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0,  'tackles' => 0],
];
