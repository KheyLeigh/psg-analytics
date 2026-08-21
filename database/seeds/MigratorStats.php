<?php
declare(strict_types=1);
// Génération des statistiques individuelles Ligue 1 (source stat_generator
// pour l'attribution match par match) : les totaux saison par joueur (buts,
// passes, cartons, minutes) sont désormais EXACTS (source unique
// verified/players_l1_fbref.php, FBref) ; seule leur répartition sur des
// matchs précis reste déterministe/estimée, comme les minutes par match.

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

// Résout les totaux saison L1 vérifiés FBref (verified/players_l1_fbref.php)
// vers les identifiants joueurs réels, indexés par player_id.
function migrator_l1_totals(array $players): array
{
    $idByKey = [];
    foreach ($players as $p) {
        $idByKey[$p['key']] = $p['id'];
    }
    $totals = [];
    foreach (require __DIR__ . '/verified/players_l1_fbref.php' as $key => $data) {
        if (!isset($idByKey[$key])) {
            throw new RuntimeException("statistiques L1 fbref : joueur introuvable pour la clé {$key}");
        }
        $totals[$idByKey[$key]] = $data;
    }
    return $totals;
}

// Affecte les buts exacts de chaque joueur à des matchs précis, en respectant
// le total marqué par PSG dans chaque match. Le total buts joueurs (73) est
// inférieur d'exactement 1 au total buts d'équipe (74) : le match où PSG a
// marqué le plus de buts absorbe ce déficit d'une unité, sans qu'aucun
// joueur ne se voie attribuer ce but contre son camp adverse.
function migrator_spread_goals(array &$agg, array $matches, array $goalTotals): void
{
    $tokens = [];
    foreach ($goalTotals as $playerId => $count) {
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $playerId;
        }
    }
    $tokens = migrator_shuffle_tokens($tokens);

    $ownGoalMatchId = null;
    $maxGoals = -1;
    foreach ($matches as $match) {
        if ($match['psg_goals'] > $maxGoals) {
            $maxGoals = $match['psg_goals'];
            $ownGoalMatchId = $match['id'];
        }
    }

    $cursor = 0;
    foreach ($matches as $match) {
        $need = $match['id'] === $ownGoalMatchId ? $match['psg_goals'] - 1 : $match['psg_goals'];
        $slice = array_slice($tokens, $cursor, $need);
        $cursor += $need;
        foreach (array_count_values($slice) as $playerId => $goals) {
            $agg[$playerId][$match['id']]['goals'] = ($agg[$playerId][$match['id']]['goals'] ?? 0) + $goals;
        }
    }
}

// Affecte les passes décisives exactes de chaque joueur à des matchs distincts
// choisis de façon déterministe (aucun total collectif de référence par match
// n'est disponible pour les passes, contrairement aux buts).
function migrator_spread_assists(array &$agg, array $matches, array $assistTotals): void
{
    $matchIds = array_column($matches, 'id');
    foreach ($assistTotals as $playerId => $count) {
        if ($count <= 0) {
            continue;
        }
        if ($count > count($matchIds)) {
            throw new RuntimeException("passes décisives incohérentes pour le joueur {$playerId}");
        }
        $chosen = array_slice(migrator_shuffle_tokens($matchIds), 0, $count);
        foreach ($chosen as $matchId) {
            $agg[$playerId][$matchId]['assists'] = ($agg[$playerId][$matchId]['assists'] ?? 0) + 1;
        }
    }
}

// Affecte à chaque joueur discipliné ses cartons L1 exacts (FBref), sur des
// matchs distincts choisis de façon déterministe.
function migrator_spread_cards(array &$agg, array $matches, array $totals): void
{
    $matchIds = array_column($matches, 'id');
    foreach ($totals as $playerId => $data) {
        $yellow = $data['yellow'];
        $red = $data['red'];
        $needed = $yellow + $red;
        if ($needed === 0) {
            continue;
        }
        if ($needed > count($matchIds)) {
            throw new RuntimeException("discipline incohérente pour le joueur {$playerId}");
        }
        $chosen = array_slice(migrator_shuffle_tokens($matchIds), 0, $needed);
        foreach ($chosen as $i => $matchId) {
            $field = $i < $yellow ? 'yellow' : 'red';
            $agg[$playerId][$matchId][$field] = ($agg[$playerId][$matchId][$field] ?? 0) + 1;
        }
    }
}

// Complète, pour chaque joueur, le nombre d'apparitions jusqu'à son total
// vérifié (mp) : les matchs déjà présents via but/passe/carton comptent comme
// apparitions, les matchs manquants sont ajoutés sans événement (0 but, 0
// passe, 0 carton) uniquement pour porter des minutes réalistes.
function migrator_top_up_appearances(array &$agg, array $matches, array $totals): void
{
    $matchIds = array_column($matches, 'id');
    foreach ($totals as $playerId => $data) {
        $existing = array_keys($agg[$playerId] ?? []);
        $need = $data['mp'] - count($existing);
        if ($need <= 0) {
            continue;
        }
        $available = array_values(array_diff($matchIds, $existing));
        $add = array_slice(migrator_shuffle_tokens($available), 0, min($need, count($available)));
        foreach ($add as $matchId) {
            $agg[$playerId][$matchId] = $agg[$playerId][$matchId] ?? [];
        }
    }
}

// Répartit $totalMinutes sur $starts titularisations et $subs entrées en jeu
// (poids indicatif 4:1, un titulaire jouant nettement plus qu'un remplaçant),
// bornée à [1, 90] par apparition, avec ajustement du dernier élément pour
// que la somme colle au total réel FBref (aux bornes près).
function migrator_distribute_minutes(int $totalMinutes, int $starts, int $subs): array
{
    $n = $starts + $subs;
    if ($n === 0) {
        return [];
    }
    $weightSum = $starts * 4 + $subs;
    $minutes = [];
    for ($i = 0; $i < $starts; $i++) {
        $minutes[] = max(1, min(90, intdiv($totalMinutes * 4, $weightSum)));
    }
    for ($i = 0; $i < $subs; $i++) {
        $minutes[] = max(1, min(90, intdiv($totalMinutes * 1, $weightSum)));
    }
    $diff = $totalMinutes - array_sum($minutes);
    $lastIdx = count($minutes) - 1;
    $minutes[$lastIdx] = max(1, min(90, $minutes[$lastIdx] + $diff));
    return $minutes;
}

// Répartit un total entier (tirs, tirs cadrés, tacles) sur des matchs selon des
// poids (les minutes jouées), avec somme finale exactement égale au total réel
// FBref : plancher proportionnel puis distribution du reste aux plus fortes parts
// fractionnaires (plus grands restes). Déterministe, sans aléa.
function migrator_distribute_count(int $total, array $weights): array
{
    $n = count($weights);
    if ($n === 0 || $total <= 0) {
        return array_fill(0, $n, 0);
    }
    $sum = array_sum($weights);
    if ($sum <= 0) {
        $base = intdiv($total, $n);
        $out = array_fill(0, $n, $base);
        for ($i = 0; $i < $total - $base * $n; $i++) {
            $out[$i]++;
        }
        return $out;
    }
    $floors = [];
    $frac = [];
    $used = 0;
    foreach ($weights as $i => $w) {
        $exact = $total * $w / $sum;
        $floors[$i] = (int) floor($exact);
        $frac[$i] = $exact - $floors[$i];
        $used += $floors[$i];
    }
    $order = array_keys($frac);
    usort($order, static fn ($a, $b): int => $frac[$b] <=> $frac[$a]);
    for ($i = 0; $i < $total - $used; $i++) {
        $floors[$order[$i]]++;
    }
    return $floors;
}

// Insère une ligne player_match_stats par (joueur, match) à partir du cumul :
// minutes réparties selon starts/subs réels, tirs/tirs cadrés/tacles répartis au
// prorata des minutes (somme exacte = total FBref), note calculée sur but+passe.
function migrator_insert_stats(PDOStatement $stmt, StatGenerator $gen, array $agg, array $totals, int $sourceId): void
{
    foreach ($agg as $playerId => $byMatch) {
        $matchIds = array_keys($byMatch);
        sort($matchIds);
        $n = count($matchIds);
        $starts = min($totals[$playerId]['starts'] ?? 0, $n);
        $subs = $n - $starts;
        $minutesList = migrator_distribute_minutes($totals[$playerId]['minutes'] ?? 0, $starts, $subs);

        $shotsList = migrator_distribute_count((int) ($totals[$playerId]['shots'] ?? 0), $minutesList);
        $sotList = migrator_distribute_count((int) ($totals[$playerId]['sot'] ?? 0), $minutesList);
        $duelsList = migrator_distribute_count((int) ($totals[$playerId]['tackles'] ?? 0), $minutesList);

        foreach ($matchIds as $i => $matchId) {
            $line = $byMatch[$matchId];
            $goals = $line['goals'] ?? 0;
            $assists = $line['assists'] ?? 0;
            $yellow = $line['yellow'] ?? 0;
            $red = $line['red'] ?? 0;
            $isStarter = $i < $starts;
            $minutes = $minutesList[$i];
            $rating = $gen->rating($goals, $assists, $minutes);
            $stmt->execute([
                (int) $playerId, (int) $matchId, (int) $isStarter, $minutes,
                $goals, $assists, $shotsList[$i], $sotList[$i], $duelsList[$i],
                $yellow, $red, $rating, $sourceId,
            ]);
        }
    }
}

// Orchestre la génération des player_match_stats : totaux exacts, affectation
// des buts/passes/cartons aux matchs, complément d'apparitions, puis
// insertion d'une ligne par (joueur, match).
function migrator_generate_player_stats(PDO $pdo, array $matches, array $players, array $ref): void
{
    $gen = new StatGenerator(2026);
    $sourceId = $ref['source_ids']['stat_generator'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, assists, shots, shots_on_target, duels_won, yellow_cards, red_card, rating, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $totals = migrator_l1_totals($players);
    $goalTotals = array_map(static fn (array $t): int => $t['goals'], $totals);
    $assistTotals = array_map(static fn (array $t): int => $t['assists'], $totals);

    $agg = [];
    migrator_spread_goals($agg, $matches, $goalTotals);
    migrator_spread_assists($agg, $matches, $assistTotals);
    migrator_spread_cards($agg, $matches, $totals);
    migrator_top_up_appearances($agg, $matches, $totals);
    migrator_insert_stats($stmt, $gen, $agg, $totals, $sourceId);
}

// migrator_compute_report() et migrator_verify_identities() vivent dans
// Migrator.php (rapport final et garde-fou d'identité, pas génération).
