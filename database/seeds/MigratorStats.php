<?php
declare(strict_types=1);
// Génération des statistiques individuelles estimées (source estimated) :
// buts L1 (ancrages vérifiés EXACTS + prior toutes comps pour le reste),
// cartons vérifiés respectés à l'unité, cumul par (joueur, match) pour éviter
// toute collision, puis calcul et vérification du bilan final.

// Mélange déterministe (Fisher-Yates) sur le flux mt_rand déjà initialisé par
// StatGenerator, pour rester reproductible à graine fixe.
function migrator_shuffle_tokens(array $items): array
{
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
    return $items;
}

// Buts toutes compétitions des marqueurs hors ancrés (relevé app, point de vue
// PSG). Sert de prior pour estimer leur part des buts L1 non vérifiés nominativement.
function migrator_scorer_priors(): array
{
    return [
        'Neves' => 7, 'Vitinha' => 7, 'Mayulu' => 6, 'Mendes' => 6, 'Lee' => 4,
        'Zaïre-Emery' => 3, 'Hakimi' => 3, 'Mbaye' => 3, 'Pacho' => 2,
        'Marquinhos' => 2, 'Beraldo' => 2, 'Ruiz' => 2, 'Zabarnyi' => 1,
        'Ndjantou' => 1, 'Fernández' => 1,
    ];
}

// Tire $count buts au prorata de $weights (échantillonnage pondéré déterministe,
// sans plancher) : la somme rendue vaut exactement $count.
function migrator_weighted_counts(int $count, array $weights): array
{
    $result = array_fill_keys(array_keys($weights), 0);
    $ids = array_keys($weights);
    $sum = array_sum($weights);
    for ($i = 0; $i < $count; $i++) {
        $pick = mt_rand(1, $sum);
        $cursor = 0;
        foreach ($ids as $id) {
            $cursor += $weights[$id];
            if ($pick <= $cursor) {
                $result[$id]++;
                break;
            }
        }
    }
    return $result;
}

// Totaux de buts L1 par joueur : ancrés à leur total vérifié EXACT (spec), buts
// restants répartis sur les autres marqueurs au prorata de leurs buts toutes comps.
function migrator_goal_totals(array $players, int $totalGoals): array
{
    $anchors = ['Barcola' => 11, 'Dembélé' => 10, 'Kvaratskhelia' => 8, 'Doué' => 7, 'Ramos' => 6];
    $idByKey = [];
    foreach ($players as $p) {
        $idByKey[$p['key']] = $p['id'];
    }

    $totals = [];
    foreach ($anchors as $key => $goals) {
        if (!isset($idByKey[$key])) {
            throw new RuntimeException("buteur ancré introuvable : {$key}");
        }
        $totals[$idByKey[$key]] = $goals;
    }

    $remaining = $totalGoals - array_sum($anchors);
    if ($remaining < 0) {
        throw new RuntimeException('total buts L1 inférieur aux ancrages vérifiés');
    }

    $weights = [];
    foreach (migrator_scorer_priors() as $key => $w) {
        if (!isset($idByKey[$key])) {
            throw new RuntimeException("marqueur pondéré introuvable : {$key}");
        }
        $weights[$idByKey[$key]] = $w;
    }
    foreach (migrator_weighted_counts($remaining, $weights) as $playerId => $count) {
        if ($count > 0) {
            $totals[$playerId] = ($totals[$playerId] ?? 0) + $count;
        }
    }
    return $totals;
}

// Affecte les buts de chaque joueur à des matchs précis, en respectant le total
// marqué par PSG dans chaque match. Alimente le cumul $agg par référence.
function migrator_spread_goals(array &$agg, array $matches, array $goalTotals): void
{
    $tokens = [];
    foreach ($goalTotals as $playerId => $count) {
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
            $agg[$playerId][$match['id']]['goals'] = ($agg[$playerId][$match['id']]['goals'] ?? 0) + $goals;
        }
    }
}

// Affecte à chaque joueur discipliné ses cartons vérifiés, sur des matchs
// distincts choisis de façon déterministe. Alimente le cumul $agg par référence.
function migrator_spread_cards(array &$agg, array $matches, array $players): void
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
            $field = $i < $yellow ? 'yellow' : 'red';
            $agg[$p['id']][$matchId][$field] = ($agg[$p['id']][$matchId][$field] ?? 0) + 1;
        }
    }
}

// Insère une ligne player_match_stats par (joueur, match) à partir du cumul,
// avec minutes (via StatGenerator) et note calculées. Un joueur qui marque ou
// prend un carton est considéré titulaire.
function migrator_insert_stats(PDOStatement $stmt, StatGenerator $gen, array $agg, int $sourceId): void
{
    foreach ($agg as $playerId => $byMatch) {
        foreach ($byMatch as $matchId => $line) {
            $goals = $line['goals'] ?? 0;
            $yellow = $line['yellow'] ?? 0;
            $red = $line['red'] ?? 0;
            $minutes = $gen->minutesForMatch([$playerId => true])[$playerId];
            $rating = $gen->rating($goals, 0, $minutes);
            $stmt->execute([(int) $playerId, (int) $matchId, $minutes, $goals, $yellow, $red, $rating, $sourceId]);
        }
    }
}

// Orchestre la génération des player_match_stats : totaux de buts, affectation
// aux matchs, cartons, puis insertion d'une ligne par (joueur, match).
function migrator_generate_player_stats(PDO $pdo, array $matches, array $players, array $ref): void
{
    $gen = new StatGenerator(2026);
    $sourceId = $ref['source_ids']['stat_generator'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, yellow_cards, red_card, rating, source_id)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)'
    );

    $totalGoals = array_sum(array_column($matches, 'psg_goals'));
    $goalTotals = migrator_goal_totals($players, $totalGoals);

    $agg = [];
    migrator_spread_goals($agg, $matches, $goalTotals);
    migrator_spread_cards($agg, $matches, $players);
    migrator_insert_stats($stmt, $gen, $agg, $sourceId);
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
