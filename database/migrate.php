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

// Point d'entrée réutilisable par les tests et par le mode CLI. Renvoie un
// rapport indexé par clé de saison (ex. '2025-26' => [...]).
function run_migration(PDO $pdo): array
{
    migrator_apply_schema($pdo);
    $catalog = migrator_seed_catalog($pdo);
    $seasons = migrator_seed_seasons($pdo);

    $reports = [];
    foreach ($seasons as $season) {
        $reports[$season['key']] = migrator_seed_season($pdo, $season, $catalog);
    }
    return $reports;
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
        $reports = run_migration($pdo);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "ÉCHEC migration : {$e->getMessage()}\n");
        exit(1);
    }

    foreach ($reports as $seasonKey => $report) {
        printf(
            "%s : matches %d, players %d, L1 %dV %dN %dD (%d-%d)\n",
            $seasonKey,
            $report['matches'],
            $report['players'],
            $report['l1_wins'],
            $report['l1_draws'],
            $report['l1_losses'],
            $report['l1_goals_for'],
            $report['l1_goals_against']
        );
    }
}
