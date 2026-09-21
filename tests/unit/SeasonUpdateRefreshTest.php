<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateHelpers.php';

// Vérifie la mise à jour en place des matchs déjà connus : FBref complète ou
// corrige régulièrement une ligne après coup (affluence publiée en retard,
// possession revue, séance de tirs au but ajoutée). Les fonctions d'ajout
// (season_update_new_*) ignorent ces lignes par construction, d'où ces
// fonctions de rafraîchissement. Purement en mémoire, comme le reste de
// SeasonUpdateHelpers.
final class SeasonUpdateRefreshTest extends TestCase
{
    public function testRefreshL1RemplitUnChampResteNul(): void
    {
        $existing = [['J4', '2026-09-13', 'Brest', false, 1, 0, null, 59]];
        $scraped  = [['J4', '2026-09-13', 'Brest', false, 1, 0, 14944, 59]];

        $refresh = season_update_refresh_l1_matches($scraped, $existing);

        $this->assertSame(14944, $refresh['rows'][0][6]);
        $this->assertCount(1, $refresh['corrections']);
        $this->assertSame('affluence', $refresh['corrections'][0]['champ']);
        $this->assertSame(null, $refresh['corrections'][0]['avant']);
        $this->assertSame(14944, $refresh['corrections'][0]['apres']);
        $this->assertSame('J4 Brest', $refresh['corrections'][0]['match']);
    }

    public function testRefreshL1CorrigeUneValeurDivergente(): void
    {
        // FBref a revu la possession à la hausse après coup : la correction doit
        // se répercuter, pas être ignorée parce que la ligne existe déjà.
        $existing = [['J1', '2026-08-23', 'Rennes', false, 2, 2, 28255, 70]];
        $scraped  = [['J1', '2026-08-23', 'Rennes', false, 2, 2, 28255, 72]];

        $refresh = season_update_refresh_l1_matches($scraped, $existing);

        $this->assertSame(72, $refresh['rows'][0][7]);
        $this->assertCount(1, $refresh['corrections']);
        $this->assertSame('possession', $refresh['corrections'][0]['champ']);
        $this->assertSame(70, $refresh['corrections'][0]['avant']);
    }

    public function testRefreshL1NEffacePasUneValeurConnueAvecUnNul(): void
    {
        // La source omet l'affluence une semaine sur deux : un nul scrappé ne
        // doit jamais écraser une valeur déjà relevée.
        $existing = [['J4', '2026-09-13', 'Brest', false, 1, 0, 14944, 59]];
        $scraped  = [['J4', '2026-09-13', 'Brest', false, 1, 0, null, 59]];

        $refresh = season_update_refresh_l1_matches($scraped, $existing);

        $this->assertSame(14944, $refresh['rows'][0][6]);
        $this->assertSame([], $refresh['corrections']);
    }

    public function testRefreshL1SuitLaJourneeMemeSiLaDateChange(): void
    {
        // Match reporté : l'identité d'une ligne de Ligue 1 est la journée, pas
        // la date. Sans cela la ligne serait dupliquée au lieu d'être corrigée.
        $existing = [['J6', '2026-10-10', 'Le Mans', true, 0, 0, null, null]];
        $scraped  = [['J6', '2026-10-21', 'Le Mans', true, 3, 0, 47000, 64]];

        $refresh = season_update_refresh_l1_matches($scraped, $existing);

        $this->assertCount(1, $refresh['rows']);
        $this->assertSame('2026-10-21', $refresh['rows'][0][1]);
        $this->assertSame(3, $refresh['rows'][0][4]);
    }

    public function testRefreshL1NAjouteRienEtLaisseLesLignesSansCorrespondance(): void
    {
        // L'ajout reste le travail de season_update_new_l1_matches : le
        // rafraîchissement ne touche qu'aux lignes déjà présentes.
        $existing = [['J1', '2026-08-23', 'Rennes', false, 2, 2, 28255, 70]];
        $scraped  = [['J2', '2026-08-28', 'Lille', false, 2, 2, 47793, 73]];

        $refresh = season_update_refresh_l1_matches($scraped, $existing);

        $this->assertCount(1, $refresh['rows']);
        $this->assertSame('J1', $refresh['rows'][0][0]);
        $this->assertSame([], $refresh['corrections']);
    }

    public function testRefreshOtherRemplitLesChampsArrivesEnRetard(): void
    {
        $existing = [['ldc', 'League phase', '2026-09-09', 'home', 'Slovan Bratislava', 6, 1, 72, null, false, false, null]];
        $scraped  = [['ldc', 'League phase', '2026-09-09', 'home', 'Slovan Bratislava', 6, 1, 72, 47511, false, false, null]];

        $refresh = season_update_refresh_other_matches($scraped, $existing);

        $this->assertSame(47511, $refresh['rows'][0][8]);
        $this->assertCount(1, $refresh['corrections']);
        $this->assertSame('affluence', $refresh['corrections'][0]['champ']);
    }

    public function testRefreshOtherNeConfondPasLesDeuxManchesDunTour(): void
    {
        // Même compétition, même tour, même adversaire : seul le lieu distingue
        // l'aller du retour. Corriger l'un ne doit pas écraser l'autre.
        $existing = [
            ['ldc', 'Quarter-finals', '2027-04-07', 'home', 'Barcelona', 2, 1, 58, null, false, false, null],
            ['ldc', 'Quarter-finals', '2027-04-14', 'away', 'Barcelona', 0, 0, 47, null, false, false, null],
        ];
        $scraped = [
            ['ldc', 'Quarter-finals', '2027-04-07', 'home', 'Barcelona', 2, 1, 58, 47800, false, false, null],
            ['ldc', 'Quarter-finals', '2027-04-14', 'away', 'Barcelona', 0, 0, 47, 92000, true, true, '4-3'],
        ];

        $refresh = season_update_refresh_other_matches($scraped, $existing);

        $this->assertSame(47800, $refresh['rows'][0][8]);
        $this->assertSame(92000, $refresh['rows'][1][8]);
        $this->assertSame(true, $refresh['rows'][1][9], 'prolongation du match retour');
        $this->assertSame('4-3', $refresh['rows'][1][11]);
        $this->assertSame(null, $refresh['rows'][0][11], 'le match aller reste sans tirs au but');
    }
}
