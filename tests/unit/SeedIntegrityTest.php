<?php
declare(strict_types=1);
// tests/unit/SeedIntegrityTest.php
require_once dirname(__DIR__, 1) . '/../database/migrate.php'; // expose run_migration()

final class SeedIntegrityTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testBilanLigue1(): void
    {
        $pdo = $this->migratedPdo();
        $psg = (int) $pdo->query("SELECT id FROM teams WHERE is_psg = 1")->fetchColumn();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $row = $pdo->query("SELECT
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals>away_goals) OR (away_team_id={$psg} AND away_goals>home_goals) THEN 1 ELSE 0 END) w,
            SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END) d,
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals<away_goals) OR (away_team_id={$psg} AND away_goals<home_goals) THEN 1 ELSE 0 END) l
            FROM matches WHERE competition_id = {$comp}")->fetch();
        $this->assertSame(24, (int) $row['w'], '24 victoires L1');
        $this->assertSame(4, (int) $row['d'], '4 nuls L1');
        $this->assertSame(6, (int) $row['l'], '6 défaites L1');
    }

    public function testEffectifComplet(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
        $this->assertSame(24, $n, '24 joueurs');
    }

    public function testButsIndividuelsEgalentButsCollectifsL1(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $indiv = (int) $pdo->query("SELECT SUM(goals) FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetchColumn();
        $this->assertSame(74, $indiv, 'somme buts individuels L1 = 74');
    }
}
