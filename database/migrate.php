<?php
declare(strict_types=1);
// Recrée le schéma et peuple la base à partir des données vérifiées
// (database/seeds/verified/) et d'un générateur déterministe pour les
// statistiques individuelles. Échoue si les identités vérifiées de la
// saison de Ligue 1 (24V/4N/6D, 74 buts pour/29 contre) ne sont pas
// respectées par les données réellement insérées.
require_once __DIR__ . '/seeds/StatGenerator.php';
require_once __DIR__ . '/seeds/Migrator.php';
require_once __DIR__ . '/seeds/MigratorStats.php';

// Point d'entrée réutilisable par les tests et par le mode CLI.
function run_migration(PDO $pdo): array
{
    migrator_apply_schema($pdo);
    $ref = migrator_seed_reference($pdo);
    $players = migrator_seed_players($pdo, $ref['season_id']);
    $matches = migrator_seed_matches($pdo, $ref);
    migrator_seed_other_matches($pdo, $ref);
    migrator_generate_player_stats($pdo, $matches, $players, $ref);
    migrator_seed_player_season($pdo, $players, $ref);

    $report = migrator_compute_report($pdo, $ref);
    migrator_verify_identities($report);
    return $report;
}

// Mode CLI : recrée database/psg.sqlite depuis zéro et affiche le rapport.
if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $dbPath = __DIR__ . '/psg.sqlite';
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    try {
        $report = run_migration($pdo);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "ÉCHEC migration : {$e->getMessage()}\n");
        exit(1);
    }

    printf(
        "matches: %d, players: %d, L1: %dV %dN %dD (%d-%d) — OK\n",
        $report['matches'],
        $report['players'],
        $report['l1_wins'],
        $report['l1_draws'],
        $report['l1_losses'],
        $report['l1_goals_for'],
        $report['l1_goals_against']
    );
}
