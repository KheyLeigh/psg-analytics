<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateWriters.php';

// Vérifie la synchronisation d'un fichier de matchs avec le relevé complet de
// la source : ajout des matchs nouveaux et mise à jour en place des anciens,
// en une seule écriture. Fichiers temporaires uniquement, jamais les vrais
// seeds du projet.
final class SeasonUpdateSyncTest extends TestCase
{
    private string $tmpDir;

    private function tmp(string $name): string
    {
        if (!isset($this->tmpDir)) {
            $this->tmpDir = sys_get_temp_dir() . '/psg_season_sync_test_' . uniqid();
            mkdir($this->tmpDir);
        }
        return $this->tmpDir . '/' . $name;
    }

    // Le lanceur maison (tests/run.php) n'appelle aucun hook setUp/tearDown :
    // nettoyage à la destruction de l'instance, comme SeasonUpdateWritersTest.
    public function __destruct()
    {
        if (!isset($this->tmpDir) || !is_dir($this->tmpDir)) {
            return;
        }
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function testSyncMatchesAjouteLesNouveauxEtCorrigeLesAnciens(): void
    {
        $path = $this->tmp('matches_l1.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n"
            . "    ['J4', '2026-09-13', 'Brest', false, 1, 0, null, 59],\n"
            . "];\n");

        $rapport = season_update_sync_matches($path, [
            ['J4', '2026-09-13', 'Brest', false, 1, 0, 14944, 59],
            ['J5', '2026-09-20', 'Marseille', false, 2, 1, null, 61],
        ]);

        $rows = require $path;
        $this->assertCount(2, $rows);
        $this->assertSame(14944, $rows[0][6], 'affluence de la J4 complétée en place');
        $this->assertSame('J5', $rows[1][0], 'la J5 est ajoutée à la suite');

        $this->assertCount(1, $rapport['nouveaux']);
        $this->assertSame('J5', $rapport['nouveaux'][0][0]);
        $this->assertCount(1, $rapport['corrections']);
        $this->assertSame('affluence', $rapport['corrections'][0]['champ']);
    }

    public function testSyncMatchesNeSignaleRienQuandLaSourceEstIdentique(): void
    {
        $path = $this->tmp('matches_l1_stable.php');
        $rows = [['J1', '2026-08-23', 'Rennes', false, 2, 2, 28255, 70]];
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($rows, true) . ";\n");

        $rapport = season_update_sync_matches($path, $rows);

        $this->assertSame([], $rapport['nouveaux']);
        $this->assertSame([], $rapport['corrections']);
        $this->assertSame($rows, require $path);
    }

    public function testSyncMatchesCreeLeFichierSiAbsent(): void
    {
        $path = $this->tmp('matches_l1_nouveau.php');

        $rapport = season_update_sync_matches($path, [['J1', '2026-08-23', 'Rennes', false, 2, 2, 28255, 70]]);

        $this->assertTrue(is_file($path));
        $this->assertCount(1, require $path);
        $this->assertCount(1, $rapport['nouveaux']);
    }

    public function testSyncOtherMatchesAjouteEtCorrige(): void
    {
        $path = $this->tmp('matches_other.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n"
            . "    ['ldc', 'League phase', '2026-09-09', 'home', 'Slovan Bratislava', 6, 1, 72, null, false, false, null],\n"
            . "];\n");

        $rapport = season_update_sync_other_matches($path, [
            ['ldc', 'League phase', '2026-09-09', 'home', 'Slovan Bratislava', 6, 1, 72, 47511, false, false, null],
            ['ldc', 'League phase', '2026-10-14', 'away', 'Manchester City', 1, 1, 48, 52000, false, false, null],
        ]);

        $rows = require $path;
        $this->assertCount(2, $rows);
        $this->assertSame(47511, $rows[0][8]);
        $this->assertSame('Manchester City', $rows[1][4]);
        $this->assertCount(1, $rapport['nouveaux']);
        $this->assertCount(1, $rapport['corrections']);
    }
}
