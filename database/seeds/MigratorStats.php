<?php
declare(strict_types=1);
// Génération des statistiques individuelles estimées (source estimated) :
// répartition des 74 buts L1 via StatGenerator, cartons vérifiés respectés
// à l'unité près, puis calcul et vérification du bilan final.

// Mélange déterministe (Fisher-Yates) reposant sur le flux mt_rand déjà
// initialisé par StatGenerator, pour rester reproductible à graine fixe.
function migrator_shuffle_tokens(array $items): array
{
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
    return $items;
}

// Répartit les buts L1 sur les buteurs ancrés (spec) et affecte chaque
// but à un match précis, en respectant le total marqué de ce match.
function migrator_assign_goals(PDOStatement $stmt, StatGenerator $gen, array $matches, array $players, int $sourceId): void
{
    $anchors = ['Barcola' => 11, 'Dembélé' => 10, 'Kvaratskhelia' => 8, 'Doué' => 7, 'Ramos' => 6];
    $weights = [];
    foreach ($players as $p) {
        if (isset($anchors[$p['key']])) {
            $weights[$p['id']] = $anchors[$p['key']];
        }
    }
    if (count($weights) !== count($anchors)) {
        throw new RuntimeException('buteurs ancrés introuvables dans l\'effectif');
    }

    $totalGoals = array_sum(array_column($matches, 'psg_goals'));
    $distribution = $gen->distributeGoals($totalGoals, $weights);

    $tokens = [];
    foreach ($distribution as $playerId => $count) {
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $playerId;
        }
    }
    $tokens = migrator_shuffle_tokens($tokens);

    $cursor = 0;
    foreach ($matches as $match) {
        $need = $match['psg_goals'];
        $slice = array_slice($tokens, $cursor, $need);
        $cursor += $need;
        foreach (array_count_values($slice) as $playerId => $goals) {
            $minutes = mt_rand(60, 90);
            $rating = $gen->rating($goals, 0, $minutes);
            $stmt->execute([(int) $playerId, $match['id'], $minutes, $goals, 0, 0, $rating, $sourceId]);
        }
    }
}

// Affecte à chaque joueur discipliné exactement ses cartons vérifiés,
// répartis sur des matchs distincts choisis de façon déterministe.
function migrator_assign_cards(PDOStatement $stmt, StatGenerator $gen, array $matches, array $players, int $sourceId): void
{
    $discipline = require __DIR__ . '/verified/discipline_l1.php';
    $matchIds = array_column($matches, 'id');

    foreach ($players as $p) {
        if (!isset($discipline[$p['key']])) {
            continue;
        }
        $yellow = $discipline[$p['key']]['yellow'];
        $red = $discipline[$p['key']]['red'];
        $needed = $yellow + $red;
        if ($needed > count($matchIds)) {
            throw new RuntimeException("discipline incohérente pour {$p['key']}");
        }
        $chosen = array_slice(migrator_shuffle_tokens($matchIds), 0, $needed);
        foreach ($chosen as $i => $matchId) {
            $isRed = $i >= $yellow;
            $minutes = $isRed ? mt_rand(20, 75) : mt_rand(60, 90);
            $rating = $gen->rating(0, 0, $minutes);
            $stmt->execute([$p['id'], $matchId, $minutes, 0, $isRed ? 0 : 1, $isRed ? 1 : 0, $rating, $sourceId]);
        }
    }
}

// Orchestre la génération des player_match_stats (buts puis cartons).
function migrator_generate_player_stats(PDO $pdo, array $matches, array $players, array $ref): void
{
    $gen = new StatGenerator(2026);
    $sourceId = $ref['source_ids']['stat_generator'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, yellow_cards, red_card, rating, source_id)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)'
    );
    migrator_assign_goals($stmt, $gen, $matches, $players, $sourceId);
    migrator_assign_cards($stmt, $gen, $matches, $players, $sourceId);
}

// Calcule le bilan Ligue 1 (V/N/D, buts) et le recoupe avec les buts
// individuels affectés, pour vérification d'intégrité.
function migrator_compute_report(PDO $pdo, array $ref): array
{
    $psgId = $ref['psg_id'];
    $compId = $ref['competition_ids']['ligue1'];
    $row = $pdo->query("SELECT
        SUM(CASE WHEN (home_team_id={$psgId} AND home_goals>away_goals) OR (away_team_id={$psgId} AND away_goals>home_goals) THEN 1 ELSE 0 END) w,
        SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END) d,
        SUM(CASE WHEN (home_team_id={$psgId} AND home_goals<away_goals) OR (away_team_id={$psgId} AND away_goals<home_goals) THEN 1 ELSE 0 END) l,
        SUM(CASE WHEN home_team_id={$psgId} THEN home_goals ELSE away_goals END) gf,
        SUM(CASE WHEN home_team_id={$psgId} THEN away_goals ELSE home_goals END) ga
        FROM matches WHERE competition_id={$compId}")->fetch();
    $individualGoals = (int) $pdo->query(
        "SELECT SUM(goals) FROM player_match_stats s JOIN matches m ON m.id = s.match_id WHERE m.competition_id={$compId}"
    )->fetchColumn();

    return [
        'matches' => (int) $pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
        'players' => (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn(),
        'l1_wins' => (int) $row['w'],
        'l1_draws' => (int) $row['d'],
        'l1_losses' => (int) $row['l'],
        'l1_goals_for' => (int) $row['gf'],
        'l1_goals_against' => (int) $row['ga'],
        'l1_individual_goals' => $individualGoals,
    ];
}

// Garde-fou : échoue explicitement si une identité vérifiée n'est pas
// respectée (attrape une erreur de transcription des données réelles).
function migrator_verify_identities(array $report): void
{
    $ok = $report['l1_wins'] === 24
        && $report['l1_draws'] === 4
        && $report['l1_losses'] === 6
        && $report['l1_goals_for'] === 74
        && $report['l1_goals_against'] === 29
        && $report['l1_individual_goals'] === $report['l1_goals_for'];

    if (!$ok) {
        throw new RuntimeException(sprintf(
            'Identité invalide : %dV %dN %dD (%d-%d), buts individuels %d',
            $report['l1_wins'], $report['l1_draws'], $report['l1_losses'],
            $report['l1_goals_for'], $report['l1_goals_against'], $report['l1_individual_goals']
        ));
    }
}
