<?php
declare(strict_types=1);
// Buts par joueur, Ligue des Champions 2025-26 (source fbref_players_ldc, cf.
// sources.php). Campagne complète (17 matchs, PSG vainqueur). Chaque total est
// exact pour la saison ; leur répartition match par match reste
// déterministe/estimée (cf. MigratorStats::migrator_generate_ldc_player_stats),
// même statut épistémique que la L1 (cf. players_l1_fbref.php).
// Le total buts joueurs (45) égale exactement le total buts d'équipe (45, cf.
// matches_other.php) : aucun but contre son camp adverse sur cette compétition,
// contrairement à la L1 (voir SeedIntegrityTest).
// Volontairement PAS de passes décisives, minutes, tirs ni cartons ici : seuls
// les buts sont nécessaires à la carte "Top buteurs · Ligue des Champions" du
// Dashboard, et seuls les buts ont été recoupés sur plusieurs sources
// indépendantes (StatMuse par joueur + total UEFA officiel de 45 buts).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
return [
    'Kvaratskhelia' => ['goals' => 10],
    'Dembélé'       => ['goals' => 8],
    'Vitinha'       => ['goals' => 6],
    'Doué'          => ['goals' => 5],
    'Pacho'         => ['goals' => 2],
    'Marquinhos'    => ['goals' => 2],
    'Mendes'        => ['goals' => 2],
    'Ramos'         => ['goals' => 2],
    'Mayulu'        => ['goals' => 2],
    'Barcola'       => ['goals' => 2],
    'Neves'         => ['goals' => 2],
    'Hakimi'        => ['goals' => 1],
    'Ruiz'          => ['goals' => 1],
];
