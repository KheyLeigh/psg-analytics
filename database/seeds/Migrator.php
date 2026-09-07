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
    $stmt = $pdo->prepare('INSERT INTO seasons (label, start_date, end_date, is_current) VALUES (?, ?, ?, ?)');
    $stmt->execute([$season['label'], $season['start_date'], $season['end_date'], (int) $season['is_current']]);
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

// Insère les 24 joueurs, résout leur people.id par nom/prénom (crée si absent),
// et renvoie leur identifiant, clé de résolution et poste.
function migrator_seed_players(PDO $pdo, int $seasonId): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO players (season_id, person_id, shirt_number, first_name, last_name, position, detailed_position, nationality, is_captain)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $players = [];
    foreach (require __DIR__ . '/verified/2025-26/players.php' as [$num, $first, $last, $pos, $detailed, $nat, $captain]) {
        $personId = migrator_resolve_person($pdo, $first, $last);
        $stmt->execute([$seasonId, $personId, $num, $first, $last, $pos, $detailed, $nat, (int) $captain]);
        $id = (int) $pdo->lastInsertId();
        $players[] = ['id' => $id, 'shirt' => $num, 'key' => migrator_player_key($first, $last), 'position' => $pos];
    }
    return $players;
}

// Retrouve la people.id d'un joueur par nom/prénom exact, ou en crée une nouvelle.
// C'est cette résolution qui relie les lignes players de plusieurs saisons entre elles.
function migrator_resolve_person(PDO $pdo, string $firstName, string $lastName): int
{
    $stmt = $pdo->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ?');
    $stmt->execute([$firstName, $lastName]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $insert = $pdo->prepare('INSERT INTO people (first_name, last_name) VALUES (?, ?)');
    $insert->execute([$firstName, $lastName]);
    return (int) $pdo->lastInsertId();
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
    foreach (require __DIR__ . '/verified/2025-26/player_season.php' as [$shirt, $apps, $starts, $goals, $assists, $yellow, $red]) {
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
    foreach (require __DIR__ . '/verified/2025-26/matches_l1.php' as [$round, $date, $opponent, $isHome, $psgGoals, $advGoals, $attendance, $possession]) {
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
    foreach (require __DIR__ . '/verified/2025-26/matches_other.php' as [
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

// Calcule le bilan Ligue 1 d'une saison (V/N/D, buts) et le recoupe avec les buts
// individuels affectés, pour vérification d'intégrité. COALESCE(...,0) : une saison
// sans matchs (ex. tout juste amorcée) ne doit jamais produire de NULL arithmétique.
function migrator_compute_report(PDO $pdo, int $seasonId, int $psgId, int $leagueCompId): array
{
    $row = $pdo->query("SELECT
        COALESCE(SUM(CASE WHEN (home_team_id={$psgId} AND home_goals>away_goals) OR (away_team_id={$psgId} AND away_goals>home_goals) THEN 1 ELSE 0 END), 0) w,
        COALESCE(SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END), 0) d,
        COALESCE(SUM(CASE WHEN (home_team_id={$psgId} AND home_goals<away_goals) OR (away_team_id={$psgId} AND away_goals<home_goals) THEN 1 ELSE 0 END), 0) l,
        COALESCE(SUM(CASE WHEN home_team_id={$psgId} THEN home_goals ELSE away_goals END), 0) gf,
        COALESCE(SUM(CASE WHEN home_team_id={$psgId} THEN away_goals ELSE home_goals END), 0) ga
        FROM matches WHERE competition_id={$leagueCompId} AND season_id={$seasonId}")->fetch();
    $individualGoals = (int) $pdo->query(
        "SELECT COALESCE(SUM(goals),0) FROM player_match_stats s JOIN matches m ON m.id = s.match_id
         WHERE m.competition_id={$leagueCompId} AND m.season_id={$seasonId}"
    )->fetchColumn();

    return [
        'matches' => (int) $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id={$seasonId}")->fetchColumn(),
        'players' => (int) $pdo->query("SELECT COUNT(*) FROM players WHERE season_id={$seasonId}")->fetchColumn(),
        'l1_wins' => (int) $row['w'],
        'l1_draws' => (int) $row['d'],
        'l1_losses' => (int) $row['l'],
        'l1_goals_for' => (int) $row['gf'],
        'l1_goals_against' => (int) $row['ga'],
        'l1_individual_goals' => $individualGoals,
    ];
}

// Garde-fou générique, toujours actif : ne suppose aucun total connu à l'avance,
// s'applique donc aussi bien à une saison terminée qu'en cours.
function migrator_verify_generic(PDO $pdo, int $seasonId): void
{
    $rows = $pdo->query(
        "SELECT m.competition_id,
                SUM(CASE WHEN m.home_team_id IN (SELECT id FROM teams WHERE is_psg=1) THEN m.home_goals ELSE m.away_goals END) team_goals,
                COALESCE((
                    SELECT SUM(s.goals) FROM player_match_stats s JOIN matches mm ON mm.id = s.match_id
                    WHERE mm.competition_id = m.competition_id AND mm.season_id = {$seasonId}
                ), 0) individual_goals
         FROM matches m
         WHERE m.season_id = {$seasonId}
           AND (m.home_team_id IN (SELECT id FROM teams WHERE is_psg=1) OR m.away_team_id IN (SELECT id FROM teams WHERE is_psg=1))
         GROUP BY m.competition_id"
    )->fetchAll();

    foreach ($rows as $row) {
        if ((int) $row['individual_goals'] > (int) $row['team_goals']) {
            throw new RuntimeException(sprintf(
                'Garde-fou générique : saison %d, compétition %d : buts individuels (%d) dépassent les buts d\'équipe (%d)',
                $seasonId, $row['competition_id'], $row['individual_goals'], $row['team_goals']
            ));
        }
    }

    $orphans = (int) $pdo->query(
        "SELECT COUNT(*) FROM player_match_stats s
         JOIN players p ON p.id = s.player_id
         JOIN matches m ON m.id = s.match_id
         WHERE m.season_id = {$seasonId} AND p.season_id <> m.season_id"
    )->fetchColumn();
    if ($orphans > 0) {
        throw new RuntimeException("Garde-fou générique : saison {$seasonId}, {$orphans} ligne(s) player_match_stats référencent un joueur d'une autre saison");
    }
}

// Totaux figés, optionnels par saison : vérifiés seulement si verified/{clé}/season_totals.php
// existe (saison terminée). Aucune vérification pour une saison en cours qui n'a pas ce fichier.
function migrator_verify_fixed_totals(string $seasonKey, array $report): void
{
    $file = __DIR__ . "/verified/{$seasonKey}/season_totals.php";
    if (!is_file($file)) {
        return;
    }
    $expected = require $file;
    $ok = $report['l1_wins'] === $expected['l1_wins']
        && $report['l1_draws'] === $expected['l1_draws']
        && $report['l1_losses'] === $expected['l1_losses']
        && $report['l1_goals_for'] === $expected['l1_goals_for']
        && $report['l1_goals_against'] === $expected['l1_goals_against']
        && $report['l1_individual_goals'] === $expected['l1_individual_goals'];

    if (!$ok) {
        throw new RuntimeException(sprintf(
            'Identité invalide (%s) : obtenu %dV %dN %dD (%d-%d) buts indiv. %d, attendu %dV %dN %dD (%d-%d) buts indiv. %d',
            $seasonKey,
            $report['l1_wins'], $report['l1_draws'], $report['l1_losses'],
            $report['l1_goals_for'], $report['l1_goals_against'], $report['l1_individual_goals'],
            $expected['l1_wins'], $expected['l1_draws'], $expected['l1_losses'],
            $expected['l1_goals_for'], $expected['l1_goals_against'], $expected['l1_individual_goals']
        ));
    }
}
