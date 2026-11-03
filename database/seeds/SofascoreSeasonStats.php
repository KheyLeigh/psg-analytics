<?php
declare(strict_types=1);
// Repli Sofascore pour les statistiques standard par joueur, Ligue 1 (spec
// 2026-11-03) : utilisé uniquement quand FBref est inaccessible (voir le
// SKILL.md de la tâche planifiée psg-analytics-collecte-2026-27, bloquée sur
// FBref depuis plusieurs semaines à cette date). Purement fonctionnel :
// aucun accès réseau ni fichier ici, le relevé brut est fourni par l'appelant.
//
// Contrairement à FBref, qui publie des tableaux déjà cumulés sur la saison,
// Sofascore renvoie directement le même total par un seul appel par joueur
// (endpoint /api/v1/player/{id}/unique-tournament/{tid}/season/{sid}/statistics/overall,
// champ 'statistics') : aucune agrégation match par match à faire ici,
// seulement une conversion de vocabulaire. Identifiants Ligue 1 2026-27
// vérifiés le 2026-11-03 : tournament = 34, season = 96127 (équipe PSG =
// 1644). Ne pas réutiliser l'identifiant de tournoi 7 : c'est celui de la
// Ligue des Champions sur Sofascore, pas celui de la Ligue 1 (collision de
// numérotation qui a trompé le relevé initial).

// Mapping champ seed (players_l1_fbref.php) => champ Sofascore.
const SOFASCORE_FIELD_MAP = [
    'mp'            => 'appearances',
    'starts'        => 'matchesStarted',
    'minutes'       => 'minutesPlayed',
    'goals'         => 'goals',
    'assists'       => 'assists',
    'pk'            => 'penaltyGoals',
    'yellow'        => 'yellowCards',
    'red'           => 'redCards',
    'shots'         => 'totalShots',
    'sot'           => 'shotsOnTarget',
    'tackles'       => 'tackles',
    'interceptions' => 'interceptions',
];

// Construit la table players_l1_fbref.php à partir des réponses Sofascore.
//
// $statsByPlayerId : [playerId Sofascore => champ 'statistics' décodé de la
//                    réponse statistics/overall de ce joueur].
// $players         : [playerId Sofascore => clé interne (nom de famille, ou
//                    prénom pour un mononyme)], cf.
//                    2026-27/sofascore-players.php.
//
// Renvoie ['totaux' => même format que players_l1_fbref.php, 'inconnus' =>
// identifiants Sofascore présents dans $statsByPlayerId sans correspondance
// dans $players]. Un joueur de $players absent de $statsByPlayerId (jamais
// convoqué en Ligue 1 cette saison) est simplement omis du résultat, comme
// FBref omettait déjà les joueurs n'ayant pas joué (voir note de
// players_l1_fbref.php : "uniquement les joueurs avec au moins un match
// joué"). Un joueur présent mais à 0 apparition (créé côté Sofascore sans
// minute jouée) est omis pour la même raison.
function sofascore_map_player_totals(array $statsByPlayerId, array $players): array
{
    $totaux = [];
    $inconnus = [];

    foreach ($statsByPlayerId as $playerId => $stats) {
        if (!isset($players[$playerId])) {
            $inconnus[] = $playerId;
            continue;
        }
        if ((int) ($stats['appearances'] ?? 0) === 0) {
            continue;
        }

        $cle = $players[$playerId];
        $ligne = [];
        foreach (SOFASCORE_FIELD_MAP as $champSeed => $champSource) {
            $ligne[$champSeed] = (int) ($stats[$champSource] ?? 0);
        }
        $totaux[$cle] = $ligne;
    }

    return ['totaux' => $totaux, 'inconnus' => $inconnus];
}

// Garde-fou avant écriture, pendant du garde-fou FotMob/FBref de l'étape 6 du
// SKILL.md. Différence importante : ce garde-fou recoupe Sofascore avec
// lui-même (somme des totaux joueurs contre le total d'équipe, même source),
// pas une source avec une autre. Il ne peut donc détecter une erreur de
// Sofascore, seulement un trou côté collecte (joueur manquant au mapping,
// mal filtré). $teamStats est le champ 'statistics' de
// /api/v1/team/{id}/unique-tournament/{tid}/season/{sid}/statistics/overall.
//
// Un écart de tirs doit toujours être nul : aucun mécanisme ne crédite un
// tir à l'équipe sans un tireur. Un écart de buts peut légitimement valoir 1
// but contre son camp adverse (crédité à l'équipe, jamais à un joueur PSG,
// cf. players_l1_fbref.php 2025-26) : ne pas le traiter comme une anomalie
// sans vérifier d'abord le calendrier de la semaine.
//
// Renvoie ['tirs' => delta, 'buts' => delta] (0 si tout correspond).
function sofascore_check_team_totals(array $totaux, array $teamStats): array
{
    $sommeTirs = 0;
    $sommeButs = 0;
    foreach ($totaux as $ligne) {
        $sommeTirs += $ligne['shots'];
        $sommeButs += $ligne['goals'];
    }

    return [
        'tirs' => (int) ($teamStats['shots'] ?? 0) - $sommeTirs,
        'buts' => (int) ($teamStats['goalsScored'] ?? 0) - $sommeButs,
    ];
}
