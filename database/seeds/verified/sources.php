<?php
declare(strict_types=1);
// Sources de données, traçabilité obligatoire (spec section 3).
// `key` permet une résolution stable indépendante de l'ordre d'insertion.
// collected_at mis à jour automatiquement par la tâche planifiée (spec 2026-09-08).
return array (
  0 => 
  array (
    'key' => 'l1_screenshots',
    'label' => 'Captures d\'écran application de suivi de matchs (fournies par Mathis)',
    'url' => NULL,
    'collected_at' => '2026-07-20',
    'confidence' => 'verified',
    'note' => 'Bilan, scores, buteurs et discipline Ligue 1 2025-26, point de vue PSG. Score et bilan recoupés et confirmés par la source fbref (24V/4N/6D, 74-29).',
  ),
  1 => 
  array (
    'key' => 'fbref',
    'label' => 'FBref : journal des matchs PSG 2025-26 (toutes compétitions)',
    'url' => 'https://fbref.com/en/squads/e2d8892c/2025-2026/matchlogs/all_comps/schedule/',
    'collected_at' => '2026-08-11',
    'confidence' => 'verified',
    'note' => 'Export CSV FBref (database/seeds/verified/fbref-fixtures-2025-26.csv) : score, possession, affluence, tirs au but et prolongation pour chaque match de la saison, toutes compétitions confondues.',
  ),
  2 => 
  array (
    'key' => 'fbref_players_l1',
    'label' => 'FBref : statistiques standard par joueur, Ligue 1 2025-26',
    'url' => 'https://fbref.com/en/squads/e2d8892c/2025-2026/dom_lig/Paris-Saint-Germain-Ligue-1-Stats',
    'collected_at' => '2026-08-11',
    'confidence' => 'verified',
    'note' => 'Export CSV FBref (database/seeds/verified/fbref-players-l1-2025-26.csv) : matchs joués, titularisations, minutes, buts, passes décisives, penalties et cartons par joueur, Ligue 1 uniquement. Remplace les ancrages estimés de scorers_l1.php / discipline_l1.php (supprimés).',
  ),
  3 => 
  array (
    'key' => 'understat',
    'label' => 'Understat : tirs individuels avec xG, saison PSG',
    'url' => 'https://understat.com/team/Paris_Saint_Germain/2025',
    'collected_at' => '2026-08-11',
    'confidence' => 'verified',
    'note' => 'Export JSON Understat (database/seeds/verified/{saison}/understat-shots-{année}.json) : position, minute et xG de chaque tir, par joueur.',
  ),
  4 => 
  array (
    'key' => 'squad_screenshots',
    'label' => 'Captures d\'écran application de suivi (onglet Équipe, fournies par Mathis)',
    'url' => NULL,
    'collected_at' => '2026-08-03',
    'confidence' => 'verified',
    'note' => 'Bilan individuel toutes compétitions 2025-26 (apparitions, buts, passes, cartons), joueurs de champ.',
  ),
  5 => 
  array (
    'key' => 'stat_generator',
    'label' => 'StatGenerator (générateur déterministe, graine 2026)',
    'url' => NULL,
    'collected_at' => '2026-07-20',
    'confidence' => 'estimated',
    'note' => 'Répartition des buts, minutes et notes par match, calibrée sur les totaux vérifiés.',
  ),
  6 => 
  array (
    'key' => 'fbref_2026-27',
    'label' => 'FBref : journal des matchs PSG 2026-27 (toutes compétitions)',
    'url' => 'https://fbref.com/en/squads/e2d8892c/2026-2027/matchlogs/all_comps/schedule/',
    'collected_at' => '2026-10-12',
    'confidence' => 'verified',
    'note' => 'Alimenté automatiquement, saison en cours (voir tâche planifiée psg-analytics-collecte-2026-27) : matchs de la saison 2026-27, toutes compétitions confondues.',
  ),
  7 => 
  array (
    'key' => 'fbref_players_l1_2026-27',
    'label' => 'FBref : statistiques standard par joueur, Ligue 1 2026-27',
    'url' => 'https://fbref.com/en/squads/e2d8892c/2026-2027/dom_lig/Paris-Saint-Germain-Ligue-1-Stats',
    'collected_at' => '2026-10-12',
    'confidence' => 'verified',
    'note' => 'Alimenté automatiquement, saison en cours (voir tâche planifiée psg-analytics-collecte-2026-27) : matchs joués, titularisations, minutes, buts, passes décisives, penalties et cartons par joueur, Ligue 1 2026-27 uniquement.',
  ),
  8 => 
  array (
    'key' => 'fotmob_2026-27',
    'label' => 'FotMob : tirs individuels avec xG, saison PSG 2026-27',
    'url' => 'https://www.fotmob.com/teams/9847/fixtures/paris-saint-germain',
    'collected_at' => '2026-11-03',
    'confidence' => 'verified',
    'note' => 'Position, minute, xG et situation de chaque tir, par joueur, Ligue 1 et Ligue des Champions. Relevé par match (une page FotMob par match), résolu par identifiant de joueur FotMob et non par nom (voir 2026-27/fotmob-players.php). Remplace Understat à partir du 21 septembre 2026 : ses pages n\'exposent plus aucune donnée embarquée, et ses agrégats étaient contaminés par des joueurs étrangers à l\'effectif. Totaux Ligue 1 recoupés avec FBref : 106 tirs, 8 buts, et le compte de tirs de chacun des 15 tireurs.',
  ),
  9 => 
  array (
    'key' => 'fbref_players_ldc',
    'label' => 'FBref/StatMuse : buts et passes décisives par joueur, Ligue des Champions 2025-26',
    'url' => 'https://fbref.com/en/squads/e2d8892c/2025-2026/c8/Paris-Saint-Germain-Stats-Champions-League',
    'collected_at' => '2026-09-14',
    'confidence' => 'verified',
    'note' => 'Relevé manuel (campagne 2025-26 terminée, ne nécessite plus de mise à jour) : buts recoupés avec le but pour but des 17 matchs déjà en base (matches_other.php) et avec le total officiel UEFA de 45 buts sur la saison ; passes décisives recoupées avec le total d\'équipe de 33 sur la campagne.',
  ),
  10 => 
  array (
    'key' => 'fbref_players_ldc_2026-27',
    'label' => 'ESPN : buts et passes décisives par joueur, Ligue des Champions 2026-27',
    'url' => 'https://www.espn.com/soccer/team/stats/_/id/160/league/UEFA.CHAMPIONS',
    'collected_at' => '2026-09-14',
    'confidence' => 'verified',
    'note' => 'Relevé manuel, saison en cours : un seul match joué à ce jour (PSG 6-1 Slovan Bratislava, 2026-09-09), buts et passes décisives recoupés avec le score du match. Contrairement aux sources L1 2026-27, ce fichier n\'est PAS alimenté par la tâche planifiée psg-analytics-collecte-2026-27 : à mettre à jour manuellement au fil des matchs de C1.',
  ),
  11 => 
  array (
    'key' => 'sofascore_heatmap_2025',
    'label' => 'Sofascore : présence sur le terrain par joueur, saison 2025-26 (toutes compétitions)',
    'url' => 'https://www.sofascore.com/football/player/vitinha/902029',
    'collected_at' => '2026-09-21',
    'confidence' => 'verified',
    'note' => 'Relevé manuel via l\'API interne de Sofascore (points de densité de touches, grille 0-100 regroupée par pas de 12) : Ligue 1, Ligue des Champions, Trophée des Champions, Supercoupe UEFA et Coupe Intercontinentale. Chaque total de matchs recoupé avec les apparitions réelles déjà connues (ex. Vitinha 49 = 29 L1 + 17 LDC + 3 finales). Coupe de France 2025-26 non disponible sur Sofascore pour cette saison, jamais complétée par une donnée fabriquée. Campagne terminée, ne nécessite plus de mise à jour.',
  ),
  12 => 
  array (
    'key' => 'sofascore_2026-27',
    'label' => 'Sofascore : calendrier et résultats Ligue 1 PSG 2026-27 (repli FBref)',
    'url' => 'https://www.sofascore.com/api/v1/unique-tournament/34/season/96127/events/round/{n}',
    'collected_at' => '2026-11-03',
    'confidence' => 'verified',
    'note' => 'Repli utilisé par la tâche planifiée psg-analytics-collecte-2026-27 uniquement quand FBref est inaccessible (bloqué par une vérification Cloudflare qui ne se résout jamais depuis plusieurs semaines à cette date). Identifiants Ligue 1 2026-27 vérifiés le 2026-11-03 : unique-tournament 34, season 96127, équipe PSG 1644 (ne pas confondre avec le tournoi 7, qui est la Ligue des Champions sur Sofascore). Score, affluence et possession recoupés avec FotMob sur Le Havre 1-5 PSG du 2026-10-30 (23714 spectateurs, 29/71% de possession, identiques des deux côtés).',
  ),
  13 => 
  array (
    'key' => 'sofascore_players_l1_2026-27',
    'label' => 'Sofascore : statistiques standard par joueur, Ligue 1 2026-27 (repli FBref)',
    'url' => 'https://www.sofascore.com/api/v1/player/{id}/unique-tournament/34/season/96127/statistics/overall',
    'collected_at' => '2026-11-03',
    'confidence' => 'verified',
    'note' => 'Repli utilisé par la tâche planifiée psg-analytics-collecte-2026-27 uniquement quand FBref est inaccessible, en remplacement de fbref_players_l1_2026-27 pour la semaine concernée. Un appel par joueur (voir database/seeds/SofascoreSeasonStats.php et 2026-27/sofascore-players.php pour la correspondance identifiant/clé) renvoie déjà les totaux saison : aucune reconstruction match par match, à la différence de fotmob_2026-27. Mapping de champs vérifié le 2026-11-03 sur Désiré Doué (8 apparitions, 5 titularisations, 436 minutes, 24 tirs, 14 tacles en Ligue 1 à cette date).',
  ),
);
