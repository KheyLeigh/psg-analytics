<?php
declare(strict_types=1);
// Buts et passes décisives par joueur, Ligue des Champions 2025-26 (source
// fbref_players_ldc, cf. sources.php). Campagne complète (17 matchs, PSG
// vainqueur). Chaque total est exact pour la saison ; leur répartition match
// par match reste déterministe/estimée (cf.
// MigratorStats::migrator_generate_ldc_player_stats), même statut
// épistémique que la L1 (cf. players_l1_fbref.php).
// Le total buts joueurs (45) égale exactement le total buts d'équipe (45, cf.
// matches_other.php) : aucun but contre son camp adverse sur cette compétition,
// contrairement à la L1 (voir SeedIntegrityTest). Le total passes décisives
// (33) recoupe le total d'équipe officiel de la campagne (StatMuse/FBref).
// Volontairement PAS de minutes, tirs ni cartons ici : non nécessaires aux
// cartes "Top buteurs" / "Top passeurs · Ligue des Champions" du Dashboard,
// et non recoupés sur plusieurs sources comme le sont buts et passes.
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
return [
    'Kvaratskhelia' => ['goals' => 10, 'assists' => 6],
    'Dembélé'       => ['goals' => 8,  'assists' => 2],
    'Vitinha'       => ['goals' => 6,  'assists' => 1],
    'Doué'          => ['goals' => 5,  'assists' => 4],
    'Pacho'         => ['goals' => 2,  'assists' => 1],
    'Marquinhos'    => ['goals' => 2,  'assists' => 0],
    'Mendes'        => ['goals' => 2,  'assists' => 2],
    'Ramos'         => ['goals' => 2,  'assists' => 0],
    'Mayulu'        => ['goals' => 2,  'assists' => 0],
    'Barcola'       => ['goals' => 2,  'assists' => 4],
    'Neves'         => ['goals' => 2,  'assists' => 3],
    'Hakimi'        => ['goals' => 1,  'assists' => 6],
    'Ruiz'          => ['goals' => 1,  'assists' => 1],
    'Lee'           => ['goals' => 0,  'assists' => 1],
    'Zaïre-Emery'   => ['goals' => 0,  'assists' => 1],
    'Ndjantou'      => ['goals' => 0,  'assists' => 1],
];
