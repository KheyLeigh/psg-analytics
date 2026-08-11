<?php
declare(strict_types=1);
// Statistiques standard Ligue 1 2025-26 vérifiées par joueur (source
// fbref_players_l1, cf. sources.php ; export CSV
// database/seeds/verified/fbref-players-l1-2025-26.csv, FBref « squad
// standard stats »). Remplace l'estimation pondérée précédente
// (scorers_l1.php / discipline_l1.php, supprimés) : chaque total ci-dessous
// est exact pour la saison, pas une projection.
// Le total buts joueurs (73) est inférieur d'exactement 1 au total buts
// d'équipe L1 (74, cf. matches_l1.php) : un but contre son camp adverse,
// crédité à PSG, n'est attribué à aucun joueur (voir SeedIntegrityTest).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
// Format : mp (matchs joués), starts (titularisations), minutes, goals,
// assists, pk (penalties transformés, déjà inclus dans goals), yellow, red.
return [
    'Zaïre-Emery'   => ['mp' => 32, 'starts' => 27, 'minutes' => 2447, 'goals' => 3,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0],
    'Zabarnyi'      => ['mp' => 28, 'starts' => 26, 'minutes' => 2387, 'goals' => 1,  'assists' => 0, 'pk' => 0, 'yellow' => 6, 'red' => 0],
    'Vitinha'       => ['mp' => 29, 'starts' => 24, 'minutes' => 2119, 'goals' => 1,  'assists' => 7, 'pk' => 0, 'yellow' => 2, 'red' => 0],
    'Pacho'         => ['mp' => 23, 'starts' => 21, 'minutes' => 1914, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Barcola'       => ['mp' => 29, 'starts' => 21, 'minutes' => 1743, 'goals' => 11, 'assists' => 1, 'pk' => 0, 'yellow' => 2, 'red' => 0],
    'Mayulu'        => ['mp' => 26, 'starts' => 21, 'minutes' => 1672, 'goals' => 4,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0],
    'Hernandez'     => ['mp' => 25, 'starts' => 20, 'minutes' => 1747, 'goals' => 0,  'assists' => 3, 'pk' => 0, 'yellow' => 4, 'red' => 0],
    'Beraldo'       => ['mp' => 20, 'starts' => 18, 'minutes' => 1567, 'goals' => 2,  'assists' => 1, 'pk' => 0, 'yellow' => 2, 'red' => 0],
    'Lee'           => ['mp' => 27, 'starts' => 18, 'minutes' => 1519, 'goals' => 3,  'assists' => 4, 'pk' => 0, 'yellow' => 3, 'red' => 0],
    'Kvaratskhelia' => ['mp' => 28, 'starts' => 18, 'minutes' => 1479, 'goals' => 8,  'assists' => 4, 'pk' => 1, 'yellow' => 1, 'red' => 0],
    'Chevalier'     => ['mp' => 17, 'starts' => 17, 'minutes' => 1530, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Doué'          => ['mp' => 23, 'starts' => 16, 'minutes' => 1348, 'goals' => 7,  'assists' => 4, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Hakimi'        => ['mp' => 18, 'starts' => 15, 'minutes' => 1375, 'goals' => 2,  'assists' => 2, 'pk' => 0, 'yellow' => 3, 'red' => 1],
    'Safonov'       => ['mp' => 15, 'starts' => 15, 'minutes' => 1350, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Ramos'         => ['mp' => 30, 'starts' => 13, 'minutes' => 1318, 'goals' => 6,  'assists' => 1, 'pk' => 1, 'yellow' => 4, 'red' => 1],
    'Neves'         => ['mp' => 21, 'starts' => 13, 'minutes' => 1287, 'goals' => 5,  'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Mendes'        => ['mp' => 20, 'starts' => 13, 'minutes' => 1256, 'goals' => 4,  'assists' => 5, 'pk' => 1, 'yellow' => 1, 'red' => 0],
    'Ruiz'          => ['mp' => 20, 'starts' => 13, 'minutes' => 1131, 'goals' => 1,  'assists' => 4, 'pk' => 0, 'yellow' => 2, 'red' => 0],
    'Dembélé'       => ['mp' => 22, 'starts' => 11, 'minutes' => 1063, 'goals' => 10, 'assists' => 7, 'pk' => 2, 'yellow' => 0, 'red' => 0],
    'Marquinhos'    => ['mp' => 14, 'starts' => 11, 'minutes' => 1052, 'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Mbaye'         => ['mp' => 24, 'starts' => 10, 'minutes' => 1006, 'goals' => 3,  'assists' => 2, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Fernández'     => ['mp' => 12, 'starts' => 7,  'minutes' => 682,  'goals' => 1,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
    'Ndjantou'      => ['mp' => 10, 'starts' => 3,  'minutes' => 360,  'goals' => 1,  'assists' => 1, 'pk' => 0, 'yellow' => 1, 'red' => 0],
    'Marin'         => ['mp' => 2,  'starts' => 2,  'minutes' => 180,  'goals' => 0,  'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0],
];
