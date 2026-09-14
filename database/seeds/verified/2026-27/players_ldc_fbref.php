<?php
declare(strict_types=1);
// Buts et passes décisives par joueur, Ligue des Champions 2026-27 (source
// fbref_players_ldc_2026-27, cf. sources.php). Saison en cours : un seul
// match joué à ce jour (PSG 6-1 Slovan Bratislava, 2026-09-09, cf.
// matches_other.php). Total buts joueurs (6) égal au total buts d'équipe (6) :
// aucun but contre son camp adverse sur ce match.
// Contrairement à players_l1_fbref.php (même saison), ce fichier n'est PAS
// régénéré par la tâche planifiée psg-analytics-collecte-2026-27 : à compléter
// manuellement au fil des prochains matchs de C1.
// Volontairement pas de minutes, tirs ni cartons ici (voir players_ldc_fbref.php
// 2025-26 pour le détail du choix).
// Clés = nom de famille (ou prénom pour les mononymes), identiques à la
// résolution migrator_player_key() de players.php.
return [
    'Torres'    => ['goals' => 3, 'assists' => 0],
    'Dembélé'   => ['goals' => 2, 'assists' => 2],
    'Ruiz'      => ['goals' => 1, 'assists' => 0],
    'Akliouche' => ['goals' => 0, 'assists' => 1],
];
