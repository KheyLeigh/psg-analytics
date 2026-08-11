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
        'INSERT INTO matches (season_id, competition_id, round_label, played_at, home_team_id, away_team_id, home_goals, away_goals, attendance, psg_possession, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
        $stmt->execute([$ref['season_id'], $compId, $round, $date, $homeId, $awayId, $homeGoals, $awayGoals, $attendance, $possession, $sourceId]);
        $matches[] = ['id' => (int) $pdo->lastInsertId(), 'psg_goals' => $psgGoals];
    }
    return $matches;
}
