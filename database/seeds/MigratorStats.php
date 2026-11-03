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

// Résout des totaux saison vérifiés (verified/{clé}/players_l1_fbref.php ou
// players_ldc_fbref.php) vers les identifiants joueurs réels, indexés par
// player_id. Réutilisée par la L1 et la Ligue des Champions.
function migrator_totals_by_key(array $players, string $file): array
{
    $idByKey = [];
    foreach ($players as $p) {
        $idByKey[$p['key']] = $p['id'];
    }
    $totals = [];
    foreach (require $file as $key => $data) {
        if (!isset($idByKey[$key])) {
            throw new RuntimeException("statistiques fbref : joueur introuvable pour la clé {$key}");
        }
        $totals[$idByKey[$key]] = $data;
    }
    return $totals;
}

// Affecte les buts exacts de chaque joueur à des matchs précis, en respectant
// le total marqué par PSG dans chaque match.
//
// La somme des buts joueurs peut être inférieure à celle des buts d'équipe,
// l'écart venant des buts contre leur camp adverses : en 2025-26, 73 buts
// joueurs pour 74 buts d'équipe. Cet écart est mesuré, jamais supposé. Il
// valait exactement 1 pour la saison 2025-26 et cette unité était retranchée en
// dur, ce qui faisait disparaître un but réel dès qu'une saison n'avait aucun
// but contre son camp en sa faveur (2026-27 : 8 buts joueurs pour 8 buts
// d'équipe, et un but de Marquinhos perdu au passage, invisible au classement
// des buteurs).
//
// Les matchs les plus prolifiques absorbent l'écart en premier : c'est là qu'un
// but contre son camp adverse passe le plus inaperçu, et la capacité d'un match
// ne peut jamais devenir négative.
function migrator_spread_goals(array &$agg, array $matches, array $goalTotals): void
{
    $tokens = [];
    foreach ($goalTotals as $playerId => $count) {
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $playerId;
        }
    }
    $tokens = migrator_shuffle_tokens($tokens);

    // Capacité de chaque match, réduite de l'écart constaté entre buts d'équipe
    // et buts joueurs.
    $capacite = [];
    foreach ($matches as $match) {
        $capacite[$match['id']] = $match['psg_goals'];
    }
    $ecart = array_sum($capacite) - count($tokens);

    if ($ecart > 0) {
        // Du match le plus prolifique au moins prolifique, une unité à la fois :
        // un match à 6 buts absorbe avant un match à 1 but, et aucun ne descend
        // sous zéro.
        $ordre = $matches;
        usort($ordre, static fn (array $a, array $b): int => $b['psg_goals'] <=> $a['psg_goals']);
        while ($ecart > 0) {
            $place = false;
            foreach ($ordre as $match) {
                if ($ecart > 0 && $capacite[$match['id']] > 0) {
                    $capacite[$match['id']]--;
                    $ecart--;
                    $place = true;
                }
            }
            if (!$place) {
                break; // plus aucune capacité : sécurité, ne boucle pas à l'infini
            }
        }
    }

    // Garde-fou : la capacité restante doit pouvoir accueillir tous les buts
    // joueurs. Sans lui, le trop-plein est silencieusement tronqué par le
    // array_slice ci-dessous et le but disparaît du classement des buteurs sans
    // que rien ne le signale, comme les passes et la discipline le font déjà
    // plus bas.
    if (array_sum($capacite) !== count($tokens)) {
        throw new RuntimeException(
            'buts incohérents : ' . count($tokens) . ' buts joueurs pour '
            . array_sum($capacite) . ' places disponibles sur ' . count($matches) . ' matchs'
        );
    }

    $cursor = 0;
    foreach ($matches as $match) {
        $need = $capacite[$match['id']];
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

// Répartit un total entier (tirs, tirs cadrés, tacles, interceptions) sur des matchs selon des
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
// minutes réparties selon starts/subs réels, tirs/tirs cadrés/tacles/interceptions
// répartis au prorata des minutes (somme exacte = total FBref), note sur but+passe.
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
        $interceptionsList = migrator_distribute_count((int) ($totals[$playerId]['interceptions'] ?? 0), $minutesList);

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
                $goals, $assists, $shotsList[$i], $sotList[$i], $duelsList[$i], $interceptionsList[$i],
                $yellow, $red, $rating, $sourceId,
            ]);
        }
    }
}

// Corps commun de migrator_generate_player_stats() (L1) et
// migrator_generate_ldc_player_stats() (Ligue des Champions), désormais
// strictement identiques dans leur forme depuis que les deux compétitions
// disposent d'un total saison complet (mp, starts, minutes, buts, passes,
// cartons, tirs, tacles, interceptions) : seuls le fichier de totaux et les
// fonctions de répartition des buts/passes diffèrent (migrator_spread_goals
// soustrait les buts contre son camp adverses connus en L1,
// migrator_spread_goals_exact non, faute d'un tel but identifié en C1 ; même
// logique pour les passes décisives, cf. leurs commentaires respectifs).
// Ne génère rien si la saison n'a pas encore de total vérifié ou pas de
// matchs : aucune statistique individuelle n'est jamais fabriquée sans total
// vérifié.
function migrator_generate_full_player_stats(
    PDO $pdo,
    array $matches,
    array $players,
    array $ref,
    string $totalsFile,
    callable $spreadGoals,
    callable $spreadAssists,
): void {
    if (!is_file($totalsFile) || $matches === [] || $players === []) {
        return;
    }

    $gen = new StatGenerator(2026);
    $sourceId = $ref['source_ids']['stat_generator'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, assists, shots, shots_on_target, duels_won, interceptions, yellow_cards, red_card, rating, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $totals = migrator_totals_by_key($players, $totalsFile);
    $goalTotals = array_map(static fn (array $t): int => $t['goals'], $totals);
    $assistTotals = array_map(static fn (array $t): int => $t['assists'], $totals);

    $agg = [];
    $spreadGoals($agg, $matches, $goalTotals);
    $spreadAssists($agg, $matches, $assistTotals);
    migrator_spread_cards($agg, $matches, $totals);
    migrator_top_up_appearances($agg, $matches, $totals);
    migrator_insert_stats($stmt, $gen, $agg, $totals, $sourceId);
}

// Orchestre la génération des player_match_stats de Ligue 1 : totaux exacts,
// affectation des buts/passes/cartons aux matchs, complément d'apparitions,
// puis insertion d'une ligne par (joueur, match).
function migrator_generate_player_stats(PDO $pdo, array $matches, array $players, array $ref, string $seasonKey): void
{
    migrator_generate_full_player_stats(
        $pdo, $matches, $players, $ref,
        __DIR__ . "/verified/{$seasonKey}/players_l1_fbref.php",
        'migrator_spread_goals',
        'migrator_spread_assists',
    );
}

// Affecte les buts LDC à des matchs précis, en respectant le total marqué par
// PSG dans chaque match. Contrairement à migrator_spread_goals (L1), ne
// soustrait jamais 1 but au match le plus prolifique : aucun but contre son
// camp adverse n'a été identifié sur cette compétition (le total buts joueurs
// égale exactement le total buts d'équipe, cf. players_ldc_fbref.php).
function migrator_spread_goals_exact(array &$agg, array $matches, array $goalTotals): void
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

// Affecte les passes décisives LDC à des matchs, sans la contrainte "un seul
// par match et par joueur" de migrator_spread_assists (L1, beaucoup de
// matchs) : sur un nombre de matchs LDC parfois très restreint (une seule
// rencontre en tout début de saison), un joueur peut légitimement cumuler
// plusieurs passes décisives sur le même match. Aucun total par match connu
// (comme pour les buts) : simple répartition déterministe, cyclique sur les
// matchs disponibles.
function migrator_spread_assists_exact(array &$agg, array $matches, array $assistTotals): void
{
    $tokens = [];
    foreach ($assistTotals as $playerId => $count) {
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $playerId;
        }
    }
    $tokens = migrator_shuffle_tokens($tokens);

    $matchIds = array_column($matches, 'id');
    $n = count($matchIds);
    foreach ($tokens as $i => $playerId) {
        $matchId = $matchIds[$i % $n];
        $agg[$playerId][$matchId]['assists'] = ($agg[$playerId][$matchId]['assists'] ?? 0) + 1;
    }
}

// Génère les player_match_stats de Ligue des Champions, répartis sur les
// vrais matchs de C1 (migrator_spread_goals_exact / migrator_spread_assists_exact :
// mêmes totaux exacts que la L1, mais sans soustraction pour un but contre
// son camp adverse, aucun n'étant identifié sur cette compétition).
//
// Deux formats cohabitent selon la saison, détectés sur la présence de la clé
// 'minutes' dans le fichier de totaux : les saisons récentes (2026-27 et
// suivantes) portent le même format complet que players_l1_fbref.php (mp,
// starts, minutes, tirs, tacles, cartons...), collecté via Sofascore ; les
// saisons antérieures (2025-26, campagne figée) ne portent que buts et
// passes décisives, le reste n'ayant jamais été recueilli à l'époque
// (volontairement laissé à 0, jamais fabriqué). Les deux formats restent
// pris en charge indéfiniment : revenir sur une saison figée pour la
// compléter n'est pas le travail de cette fonction.
function migrator_generate_ldc_player_stats(PDO $pdo, array $matches, array $players, array $ref, string $seasonKey): void
{
    $totalsFile = __DIR__ . "/verified/{$seasonKey}/players_ldc_fbref.php";
    if (!is_file($totalsFile) || $matches === [] || $players === []) {
        return;
    }

    $totaux = require $totalsFile;
    $isFullFormat = $totaux !== [] && array_key_exists('minutes', reset($totaux));

    if ($isFullFormat) {
        migrator_generate_full_player_stats(
            $pdo, $matches, $players, $ref,
            $totalsFile,
            'migrator_spread_goals_exact',
            'migrator_spread_assists_exact',
        );
        return;
    }

    // Format historique minimal (2025-26) : buts et passes décisives
    // seulement, le reste à 0 (défaut du schéma, jamais affiché).
    $sourceId = migrator_resolve_source_id($ref['source_ids'], 'fbref_players_ldc', $seasonKey);
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, assists, source_id)
         VALUES (?, ?, 0, 0, ?, ?, ?)'
    );

    $totals = migrator_totals_by_key($players, $totalsFile);
    $goalTotals = array_map(static fn (array $t): int => $t['goals'], $totals);
    $assistTotals = array_map(static fn (array $t): int => $t['assists'] ?? 0, $totals);

    $agg = [];
    migrator_spread_goals_exact($agg, $matches, $goalTotals);
    migrator_spread_assists_exact($agg, $matches, $assistTotals);

    foreach ($agg as $playerId => $byMatch) {
        foreach ($byMatch as $matchId => $line) {
            $stmt->execute([(int) $playerId, (int) $matchId, $line['goals'] ?? 0, $line['assists'] ?? 0, $sourceId]);
        }
    }
}

// migrator_compute_report(), migrator_verify_generic() et
// migrator_verify_fixed_totals() vivent dans Migrator.php (rapport final et
// garde-fous d'intégrité, pas génération).
