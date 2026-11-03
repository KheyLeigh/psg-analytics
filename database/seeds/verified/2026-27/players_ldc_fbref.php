<?php
declare(strict_types=1);
// Statistiques Ligue des Champions 2026-27 vérifiées par joueur (source
// sofascore_players_ldc_2026-27, cf. sources.php), 3 matchs joués à ce jour
// (PSG 6-1 Slovan Bratislava, Manchester City 2-4 PSG, PSG 4-2 Barcelona).
// Même format que players_l1_fbref.php (relevé le 2026-11-03 via l'API
// interne Sofascore, un appel par joueur, unique-tournament 7 / season
// 96518) : mp, starts, minutes, goals, assists, pk, yellow, red, shots, sot,
// tackles, interceptions. Remplace le fichier précédent (buts/passes
// décisives seuls, limité faute de minutes/tirs/cartons recueillis à
// l'époque) : voir sources.php pour la bascule.
// Total buts joueurs (14) et tirs (72) égaux aux totaux d'équipe Sofascore
// sur les 3 matchs : aucun but contre son camp adverse identifié à ce stade.
// Croisement indépendant : les buts et passes décisives obtenus ici via
// Sofascore (totaux saison) tombent exactement sur ceux relevés match par
// match via les événements de but FotMob le 2026-11-03 (mêmes 14 buts, mêmes
// 10 passes décisives, même répartition par joueur).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
return [
    'Dembélé'     => ['mp' => 3, 'starts' => 3, 'minutes' => 160, 'goals' => 3, 'assists' => 3, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 12, 'sot' => 5, 'tackles' => 0, 'interceptions' => 1],
    'Vitinha'     => ['mp' => 3, 'starts' => 3, 'minutes' => 270, 'goals' => 2, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 4,  'sot' => 4, 'tackles' => 2, 'interceptions' => 4],
    'Safonov'     => ['mp' => 3, 'starts' => 3, 'minutes' => 270, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 0, 'interceptions' => 0],
    'Ruiz'        => ['mp' => 3, 'starts' => 3, 'minutes' => 264, 'goals' => 2, 'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 10, 'sot' => 4, 'tackles' => 6, 'interceptions' => 5],
    'Hakimi'      => ['mp' => 3, 'starts' => 3, 'minutes' => 244, 'goals' => 0, 'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 7,  'sot' => 3, 'tackles' => 7, 'interceptions' => 2],
    'Pacho'       => ['mp' => 3, 'starts' => 3, 'minutes' => 244, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 2, 'interceptions' => 4],
    'Akliouche'   => ['mp' => 3, 'starts' => 2, 'minutes' => 197, 'goals' => 1, 'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 4,  'sot' => 1, 'tackles' => 5, 'interceptions' => 2],
    'Torres'      => ['mp' => 3, 'starts' => 1, 'minutes' => 157, 'goals' => 3, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 10, 'sot' => 6, 'tackles' => 1, 'interceptions' => 0],
    'Hernandez'   => ['mp' => 3, 'starts' => 1, 'minutes' => 115, 'goals' => 1, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 1,  'sot' => 1, 'tackles' => 3, 'interceptions' => 2],
    'Marquinhos'  => ['mp' => 2, 'starts' => 2, 'minutes' => 149, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 3, 'interceptions' => 0],
    'Mendes'      => ['mp' => 2, 'starts' => 2, 'minutes' => 154, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 3,  'sot' => 2, 'tackles' => 1, 'interceptions' => 3],
    'Doué'        => ['mp' => 2, 'starts' => 2, 'minutes' => 144, 'goals' => 2, 'assists' => 2, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 5,  'sot' => 3, 'tackles' => 4, 'interceptions' => 0],
    'Zabarnyi'    => ['mp' => 2, 'starts' => 1, 'minutes' => 121, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 0, 'interceptions' => 2],
    'Neves'       => ['mp' => 2, 'starts' => 1, 'minutes' => 104, 'goals' => 0, 'assists' => 1, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 1,  'sot' => 1, 'tackles' => 7, 'interceptions' => 2],
    'Zaïre-Emery' => ['mp' => 2, 'starts' => 1, 'minutes' => 96,  'goals' => 0, 'assists' => 1, 'pk' => 0, 'yellow' => 1, 'red' => 0, 'shots' => 2,  'sot' => 1, 'tackles' => 2, 'interceptions' => 0],
    'Kvaratskhelia' => ['mp' => 2, 'starts' => 1, 'minutes' => 83, 'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 5, 'sot' => 2, 'tackles' => 2, 'interceptions' => 1],
    'Mayulu'      => ['mp' => 2, 'starts' => 0, 'minutes' => 38,  'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 2,  'sot' => 2, 'tackles' => 3, 'interceptions' => 1],
    'Beraldo'     => ['mp' => 2, 'starts' => 0, 'minutes' => 28,  'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 1, 'interceptions' => 1],
    'Fernández'   => ['mp' => 1, 'starts' => 1, 'minutes' => 90,  'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 4,  'sot' => 2, 'tackles' => 3, 'interceptions' => 1],
    'Digne'       => ['mp' => 1, 'starts' => 0, 'minutes' => 26,  'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 0,  'sot' => 0, 'tackles' => 1, 'interceptions' => 1],
    'Godts'       => ['mp' => 1, 'starts' => 0, 'minutes' => 26,  'goals' => 0, 'assists' => 0, 'pk' => 0, 'yellow' => 0, 'red' => 0, 'shots' => 2,  'sot' => 1, 'tackles' => 1, 'interceptions' => 0],
];
