<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateWriters.php';

// Vérifie les fonctions d'écriture de seeds utilisées par la tâche planifiée
// d'automatisation (spec 2026-09-08), sur des fichiers temporaires du
// scratchpad de test : aucune modification des vrais seeds du projet.
final class SeasonUpdateWritersTest extends TestCase
{
    private string $tmpDir;

    private function tmp(string $name): string
    {
        if (!isset($this->tmpDir)) {
            $this->tmpDir = sys_get_temp_dir() . '/psg_season_update_test_' . uniqid();
            mkdir($this->tmpDir);
        }
        return $this->tmpDir . '/' . $name;
    }

    // Le lanceur de tests maison (tests/run.php) n'appelle aucun hook
    // setUp/tearDown : le nettoyage du répertoire temporaire se fait donc ici,
    // à la destruction de l'instance (une seule par classe de test).
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

    public function testAppendMatchesAjouteALaFinDunFichierExistant(): void
    {
        $path = $this->tmp('matches_l1.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n    ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],\n];\n");

        season_update_append_matches($path, [['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55]]);

        $rows = require $path;
        $this->assertCount(2, $rows);
        $this->assertSame('J1', $rows[0][0]);
        $this->assertSame('J2', $rows[1][0]);
    }

    public function testAppendMatchesCreeLeFichierSiAbsent(): void
    {
        $path = $this->tmp('matches_l1_nouveau.php');
        season_update_append_matches($path, [['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60]]);

        $this->assertTrue(is_file($path));
        $rows = require $path;
        $this->assertCount(1, $rows);
    }

    public function testAppendOtherMatchesAjouteALaFin(): void
    {
        $path = $this->tmp('matches_other.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [];\n");

        season_update_append_other_matches($path, [['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null]]);

        $rows = require $path;
        $this->assertCount(1, $rows);
        $this->assertSame('Bayern', $rows[0][4]);
    }

    public function testReplaceTotalsRemplaceIntegralementLeContenu(): void
    {
        $path = $this->tmp('players_l1_fbref.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn ['Ancien' => ['mp' => 1]];\n");

        season_update_replace_totals($path, ['Barcola' => ['mp' => 10, 'goals' => 5]], 'Totaux mis à jour.');

        $totals = require $path;
        $this->assertSame(['Barcola' => ['mp' => 10, 'goals' => 5]], $totals);
    }

    public function testReplaceTotalsNeutraliseUnCommentaireMultiLignes(): void
    {
        // Un retour à la ligne dans $comment ferait sortir la suite du texte du
        // commentaire // et casserait le fichier généré (ou pire) : il doit être
        // neutralisé avant écriture.
        $path = $this->tmp('players_l1_fbref_comment.php');

        season_update_replace_totals($path, ['Barcola' => ['mp' => 10]], "Ligne 1\nreturn ['injecte' => true];");

        $totals = require $path;
        $this->assertSame(['Barcola' => ['mp' => 10]], $totals, 'le fichier reste un simple retour de tableau, rien injecté');
    }

    public function testReplaceTotalsNeutraliseUneFermetureDeBalisePhp(): void
    {
        // La balise de fermeture PHP termine un commentaire // sans le moindre
        // retour à la ligne (comportement du langage) : sans neutralisation,
        // tout ce qui suit dans $comment serait exécuté comme du code PHP au
        // require. Volontairement pas écrite en toutes lettres ici : le faire
        // dans un commentaire // du code source la déclencherait aussi.
        $path = $this->tmp('players_l1_fbref_fermeture.php');

        season_update_replace_totals($path, ['Barcola' => ['mp' => 10]], 'texte anodin ?><?php echo "INJECTE"; //');

        ob_start();
        $totals = require $path;
        $sortie = ob_get_clean();

        $this->assertSame('', $sortie, 'rien ne doit s\'exécuter depuis le commentaire');
        $this->assertSame(['Barcola' => ['mp' => 10]], $totals);
    }

    public function testTouchSourcesMetAJourUniquementLesClesDemandees(): void
    {
        $path = $this->tmp('sources.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n"
            . "    ['key' => 'fbref', 'label' => 'FBref', 'url' => null, 'collected_at' => '2026-01-01', 'confidence' => 'verified', 'note' => ''],\n"
            . "    ['key' => 'understat', 'label' => 'Understat', 'url' => null, 'collected_at' => '2026-01-01', 'confidence' => 'verified', 'note' => ''],\n"
            . "];\n");

        season_update_touch_sources($path, ['fbref' => '2026-09-15']);

        $sources = require $path;
        $byKey = [];
        foreach ($sources as $s) {
            $byKey[$s['key']] = $s;
        }
        $this->assertSame('2026-09-15', $byKey['fbref']['collected_at']);
        $this->assertSame('2026-01-01', $byKey['understat']['collected_at'], 'clé non demandée : inchangée');
        $this->assertSame('FBref', $byKey['fbref']['label'], 'les autres champs restent intacts');
    }
}
