<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateHelpers.php';

// Vérifie les fonctions déterministes de détection utilisées par la tâche
// planifiée d'automatisation (spec 2026-09-08), sur des données synthétiques :
// aucun accès réseau ni fichier, tout est en mémoire.
final class SeasonUpdateHelpersTest extends TestCase
{
    public function testNewL1MatchesDetecteUniquementLesMatchsAbsents(): void
    {
        $existing = [
            ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],
            ['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55],
        ];
        $scraped = [
            ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],
            ['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55],
            ['J3', '2026-08-30', 'Marseille', true, 3, 1, 47000, 65],
        ];
        $new = season_update_new_l1_matches($scraped, $existing);

        $this->assertCount(1, $new);
        $this->assertSame('J3', $new[0][0]);
        $this->assertSame('Marseille', $new[0][2]);
    }

    public function testNewL1MatchesRenvoieVideSiRienDeNouveau(): void
    {
        $rows = [['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60]];
        $this->assertSame([], season_update_new_l1_matches($rows, $rows));
    }

    public function testNewOtherMatchesDetecteUniquementLesMatchsAbsents(): void
    {
        $existing = [
            ['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null],
        ];
        $scraped = [
            ['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null],
            ['ldc', 'Phase de ligue J2', '2026-10-01', 'away', 'Barcelone', 1, 1, 50, 90000, 0, 0, null],
        ];
        $new = season_update_new_other_matches($scraped, $existing);

        $this->assertCount(1, $new);
        $this->assertSame('Barcelone', $new[0][4]);
    }

    public function testRosterDiffDetecteNouveauxEtAbsents(): void
    {
        $scraped = [
            ['first' => 'Bradley', 'last' => 'Barcola'],
            ['first' => 'Nouveau', 'last' => 'Recrue'],
        ];
        $existing = [
            [29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false],
            [17, 'Ancien', 'Parti', 'MF', 'CM', 'France', false],
        ];
        $diff = season_update_roster_diff($scraped, $existing);

        $this->assertSame(['Recrue'], $diff['nouveaux']);
        $this->assertSame(['Parti'], $diff['absents']);
    }

    public function testRosterDiffVideSiEffectifIdentique(): void
    {
        $scraped = [['first' => 'Bradley', 'last' => 'Barcola']];
        $existing = [[29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false]];
        $diff = season_update_roster_diff($scraped, $existing);

        $this->assertSame([], $diff['nouveaux']);
        $this->assertSame([], $diff['absents']);
    }

    public function testUnknownCompetitionsDetecteUneCompetitionInconnue(): void
    {
        $known = [
            ['key' => 'ligue1', 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic'],
            ['key' => 'ldc', 'name' => 'Ligue des Champions', 'type' => 'cup', 'scope' => 'european'],
        ];
        $rencontrees = ['Ligue 1', 'Ligue des Champions', 'Trophée des Champions'];

        $this->assertSame(['Trophée des Champions'], season_update_unknown_competitions($rencontrees, $known));
    }

    public function testUnknownCompetitionsVideSiTouteConnues(): void
    {
        $known = [['key' => 'ligue1', 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic']];
        $this->assertSame([], season_update_unknown_competitions(['Ligue 1'], $known));
    }
}
