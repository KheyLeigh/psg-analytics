<?php
declare(strict_types=1);
// Buts et passes décisives par joueur, Ligue des Champions 2026-27 (source
// fbref_players_ldc_2026-27, cf. sources.php). Mis à jour le 2026-11-03 avec
// les buteurs/passeurs des deux matchs suivants (événements de but FotMob,
// même source déjà utilisée pour les tirs, cf. shots-2026.json) : Manchester
// City 2-4 PSG (2026-10-14) et PSG 4-2 Barcelona (2026-10-20), en plus du
// match déjà connu (PSG 6-1 Slovan Bratislava, 2026-09-09). Total buts
// joueurs (14) égal au total buts d'équipe sur les 3 matchs (6+4+4=14) :
// aucun but contre son camp adverse à ce stade.
// Contrairement à players_l1_fbref.php (même saison), ce fichier n'est PAS
// régénéré par la tâche planifiée psg-analytics-collecte-2026-27 : à compléter
// manuellement au fil des prochains matchs de C1.
// Volontairement pas de minutes, tirs ni cartons ici (voir players_ldc_fbref.php
// 2025-26 pour le détail du choix).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
return [
    'Dembélé'     => ['goals' => 3, 'assists' => 3],
    'Torres'      => ['goals' => 3, 'assists' => 0],
    'Doué'        => ['goals' => 2, 'assists' => 2],
    'Ruiz'        => ['goals' => 2, 'assists' => 1],
    'Vitinha'     => ['goals' => 2, 'assists' => 0],
    'Akliouche'   => ['goals' => 1, 'assists' => 1],
    'Hernandez'   => ['goals' => 1, 'assists' => 0],
    'Zaïre-Emery' => ['goals' => 0, 'assists' => 1],
    'Neves'       => ['goals' => 0, 'assists' => 1],
    'Hakimi'      => ['goals' => 0, 'assists' => 1],
];
