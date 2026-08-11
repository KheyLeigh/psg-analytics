<?php
declare(strict_types=1);
// Étapes internes de la migration : schéma, données de référence,
// effectif, matchs vérifiés, puis statistiques individuelles estimées.
// Regroupé ici pour garder database/migrate.php court et lisible.

// Charge et exécute le schéma SQLite sur la connexion fournie.
function migrator_apply_schema(PDO $pdo): void
{
    $sql = file_get_contents(dirname(__DIR__) . '/schema.sqlite.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sqlite.sql introuvable');
    }
    $pdo->exec($sql);
}

// Clé de résolution d'un joueur : nom de famille, ou prénom s'il est vide.
function migrator_player_key(string $firstName, string $lastName): string
{
    return $lastName !== '' ? $lastName : $firstName;
}

// Insère saison, équipes, compétitions et sources ; renvoie les identifiants
// nécessaires aux étapes suivantes.
function migrator_seed_reference(PDO $pdo): array
{
    $season = (require __DIR__ . '/verified/seasons.php')[0];
    $stmt = $pdo->prepare('INSERT INTO seasons (label, start_date, end_date) VALUES (?, ?, ?)');
    $stmt->execute([$season['label'], $season['start_date'], $season['end_date']]);
    $seasonId = (int) $pdo->lastInsertId();

    $teamIds = [];
    $psgId = null;
    $stmt = $pdo->prepare('INSERT INTO teams (name, short_name, country, is_psg) VALUES (?, ?, ?, ?)');
    foreach (require __DIR__ . '/verified/teams.php' as $team) {
        $stmt->execute([$team['name'], $team['short_name'], $team['country'], (int) $team['is_psg']]);
        $id = (int) $pdo->lastInsertId();
        $teamIds[$team['name']] = $id;
        if ($team['is_psg']) {
            $psgId = $id;
        }
    }

    $competitionIds = [];
    $stmt = $pdo->prepare('INSERT INTO competitions (name, type, scope) VALUES (?, ?, ?)');
    foreach (require __DIR__ . '/verified/competitions.php' as $comp) {
        $stmt->execute([$comp['name'], $comp['type'], $comp['scope']]);
        $competitionIds[$comp['key']] = (int) $pdo->lastInsertId();
    }

    $sourceIds = [];
    $stmt = $pdo->prepare('INSERT INTO data_sources (label, url, collected_at, confidence, note) VALUES (?, ?, ?, ?, ?)');
    foreach (require __DIR__ . '/verified/sources.php' as $src) {
        $stmt->execute([$src['label'], $src['url'], $src['collected_at'], $src['confidence'], $src['note']]);
        $sourceIds[$src['key']] = (int) $pdo->lastInsertId();
    }

    return [
        'season_id' => $seasonId,
        'team_ids' => $teamIds,
        'psg_id' => $psgId,
        'competition_ids' => $competitionIds,
        'source_ids' => $sourceIds,
    ];
}

// Insère les 24 joueurs et renvoie leur identifiant, clé de résolution et poste.
function migrator_seed_players(PDO $pdo, int $seasonId): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO players (season_id, shirt_number, first_name, last_name, position, detailed_position, nationality, is_captain)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $players = [];
    foreach (require __DIR__ . '/verified/players.php' as [$num, $first, $last, $pos, $detailed, $nat, $captain]) {
        $stmt->execute([$seasonId, $num, $first, $last, $pos, $detailed, $nat, (int) $captain]);
        $id = (int) $pdo->lastInsertId();
        $players[] = ['id' => $id, 'shirt' => $num, 'key' => migrator_player_key($first, $last), 'position' => $pos];
    }
    return $players;
}

// Insère le bilan de saison vérifié (toutes compétitions) des joueurs de champ.
// Les joueurs absents du fichier (gardiens) n'ont pas de ligne : donnée non
// disponible, jamais fabriquée.
function migrator_seed_player_season(PDO $pdo, array $players, array $ref): void
{
    $idByShirt = [];
    foreach ($players as $p) {
        $idByShirt[$p['shirt']] = $p['id'];
    }
    $sourceId = $ref['source_ids']['squad_screenshots'];
    $seasonId = $ref['season_id'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_season_stats (player_id, season_id, appearances, starts, goals, assists, yellow_cards, red_cards, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (require __DIR__ . '/verified/player_season.php' as [$shirt, $apps, $starts, $goals, $assists, $yellow, $red]) {
        if (!isset($idByShirt[$shirt])) {
            throw new RuntimeException("bilan saison : joueur au numéro {$shirt} introuvable");
        }
        $stmt->execute([$idByShirt[$shirt], $seasonId, $apps, $starts, $goals, $assists, $yellow, $red, $sourceId]);
    }
}

// Insère les 34 matchs de Ligue 1 réels (source fbref, verified) et renvoie,
// pour chacun, les buts PSG servant à la répartition des buts individuels.
function migrator_seed_matches(PDO $pdo, array $ref): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO matches (season_id, competition_id, round_label, played_at, home_team_id, away_team_id, home_goals, away_goals, venue, attendance, psg_possession, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $psgId = $ref['psg_id'];
    $compId = $ref['competition_ids']['ligue1'];
    $sourceId = $ref['source_ids']['fbref'];
    $matches = [];
    foreach (require __DIR__ . '/verified/matches_l1.php' as [$round, $date, $opponent, $isHome, $psgGoals, $advGoals, $attendance, $possession]) {
        $oppId = $ref['team_ids'][$opponent];
        [$homeId, $awayId, $homeGoals, $awayGoals] = $isHome
            ? [$psgId, $oppId, $psgGoals, $advGoals]
            : [$oppId, $psgId, $advGoals, $psgGoals];
        $venue = $isHome ? 'home' : 'away';
        $stmt->execute([$ref['season_id'], $compId, $round, $date, $homeId, $awayId, $homeGoals, $awayGoals, $venue, $attendance, $possession, $sourceId]);
        $matches[] = ['id' => (int) $pdo->lastInsertId(), 'psg_goals' => $psgGoals];
    }
    return $matches;
}

// Insère les matchs hors Ligue 1 (Supercoupe UEFA, Ligue des Champions,
// Trophée des Champions, Coupe de France ; source fbref, verified). Aucun
// but individuel n'est réparti pour ces matchs (pas de player_match_stats).
function migrator_seed_other_matches(PDO $pdo, array $ref): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO matches (season_id, competition_id, round_label, played_at, home_team_id, away_team_id, home_goals, away_goals, went_to_extra, penalty_shootout, penalty_score, venue, attendance, psg_possession, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $psgId = $ref['psg_id'];
    $sourceId = $ref['source_ids']['fbref'];
    foreach (require __DIR__ . '/verified/matches_other.php' as [
        $compKey, $round, $date, $venue, $opponent, $psgGoals, $advGoals,
        $possession, $attendance, $wentToExtra, $penaltyShootout, $penaltyScore,
    ]) {
        $compId = $ref['competition_ids'][$compKey];
        $oppId = $ref['team_ids'][$opponent];
        // Convention : PSG est toujours home_team_id pour les matchs sur terrain
        // neutre, home/away sinon, conformément au champ `venue`.
        [$homeId, $awayId, $homeGoals, $awayGoals] = $venue === 'away'
            ? [$oppId, $psgId, $advGoals, $psgGoals]
            : [$psgId, $oppId, $psgGoals, $advGoals];
        $stmt->execute([
            $ref['season_id'], $compId, $round, $date, $homeId, $awayId, $homeGoals, $awayGoals,
            (int) $wentToExtra, (int) $penaltyShootout, $penaltyScore, $venue, $attendance, $possession, $sourceId,
        ]);
    }
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
// l1_individual_goals = 73, pas 74 : le but d'équipe restant est le but
// contre son camp adverse, jamais attribué à un joueur PSG (cf. verified/players_l1_fbref.php).
function migrator_verify_identities(array $report): void
{
    $ok = $report['l1_wins'] === 24
        && $report['l1_draws'] === 4
        && $report['l1_losses'] === 6
        && $report['l1_goals_for'] === 74
        && $report['l1_goals_against'] === 29
        && $report['l1_individual_goals'] === 73;

    if (!$ok) {
        throw new RuntimeException(sprintf(
            'Identité invalide : %dV %dN %dD (%d-%d), buts individuels %d (attendu 73)',
            $report['l1_wins'], $report['l1_draws'], $report['l1_losses'],
            $report['l1_goals_for'], $report['l1_goals_against'], $report['l1_individual_goals']
        ));
    }
}
