<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/FotmobShotmap.php';

// Vérifie la conversion des tirs FotMob vers le format shotmap du projet.
// Tout est en mémoire : aucune dépendance à FotMob ni au disque.
final class FotmobShotmapTest extends TestCase
{
    // Un tir contré arrive en AttemptSaved avec isBlocked : sans ce drapeau il
    // serait compté comme un arrêt du gardien, ce qui fausserait la lecture de
    // la carte (un contre n'est pas un arrêt).
    public function testResultatDistingueContreEtArret(): void
    {
        $this->assertSame('block', fotmob_shot_result('AttemptSaved', true));
        $this->assertSame('saved', fotmob_shot_result('AttemptSaved', false));
    }

    public function testResultatCouvreLeVocabulaireDuProjet(): void
    {
        $this->assertSame('goal', fotmob_shot_result('Goal', false));
        $this->assertSame('miss', fotmob_shot_result('Miss', false));
        $this->assertSame('post', fotmob_shot_result('Post', false));
    }

    public function testSituationRamenéeAuVocabulaireUnderstat(): void
    {
        $this->assertSame('OpenPlay', fotmob_shot_situation('RegularPlay'));
        $this->assertSame('DirectFreekick', fotmob_shot_situation('FreeKick'));
        $this->assertSame('SetPiece', fotmob_shot_situation('ThrowInSetPiece'));
        $this->assertSame('FromCorner', fotmob_shot_situation('FromCorner'), 'déjà commun aux deux sources');
        $this->assertSame('FastBreak', fotmob_shot_situation('FastBreak'), 'conservée, absente d\'Understat');
    }

    public function testCoordonneesRameneesEnZeroUn(): void
    {
        // Terrain 105 x 68 : un tir au point de penalty adverse (94 m, 34 m)
        // tombe pres de x=0.9 et au centre en largeur.
        [$x, $y] = fotmob_normalise_coords(94.5, 34.0);
        $this->assertSame(0.9, $x);
        $this->assertSame(0.5, $y);

        [$xb, $yb] = fotmob_normalise_coords(105.0, 68.0);
        $this->assertSame(1.0, $xb, 'ligne de but adverse');
        $this->assertSame(1.0, $yb, 'touche opposee');
    }

    private function joueurs(): array
    {
        return [
            881771 => ['id' => 43, 'name' => 'Ferran Torres'],
            267365 => ['id' => 30, 'name' => 'Marquinhos'],
        ];
    }

    public function testConstruitLesTotauxParJoueur(): void
    {
        $matches = [
            ['comp' => 'Ligue 1', 'opp' => 'Rennes', 'ha' => 'a', 'shots' => [
                [881771, 'Ferran Torres', 95.06, 30.05, 0.6369, 'Goal', 0, 71, 'RegularPlay', 'RightFoot'],
                [881771, 'Ferran Torres', 96.61, 35.30, 0.1247, 'Goal', 0, 82, 'RegularPlay', 'Header'],
            ]],
            ['comp' => 'Ligue des Champions', 'opp' => 'Slovan Bratislava', 'ha' => 'h', 'shots' => [
                [881771, 'Ferrán Torres', 91.90, 31.48, 0.0390, 'Miss', 0, 25, 'FromCorner', 'RightFoot'],
            ]],
        ];

        $build = fotmob_build_shotmap($matches, $this->joueurs(), ['season' => '2026-27']);
        $joueur = $build['shotmap']['players']['43'];

        $this->assertSame(3, $joueur['shots_total']);
        $this->assertSame(2, $joueur['goals']);
        $this->assertSame(0.8, $joueur['xg_total']);
        $this->assertSame([], $build['inconnus']);
    }

    // Le nom affiché vient de la base, jamais de la source : FotMob orthographie
    // le même joueur de deux façons d'un match à l'autre (Ferran / Ferrán).
    // S'y fier scinderait un joueur en deux identités.
    public function testLeNomVientDeLaBasePasDeLaSource(): void
    {
        $matches = [
            ['comp' => 'Ligue 1', 'opp' => 'Lille', 'ha' => 'a', 'shots' => [
                [881771, 'Ferrán Torres', 92.20, 30.32, 0.2319, 'Miss', 0, 18, 'RegularPlay', 'RightFoot'],
            ]],
        ];

        $build = fotmob_build_shotmap($matches, $this->joueurs(), []);

        $this->assertSame('Ferran Torres', $build['shotmap']['players']['43']['name']);
        $this->assertCount(1, $build['shotmap']['players'], 'un seul joueur, pas deux graphies');
    }

    // Understat renvoyait des joueurs étrangers à l'effectif : les rattacher au
    // hasard aurait pollué la saison. Un tireur inconnu est écarté ET signalé.
    public function testTireurInconnuEcarteEtSignale(): void
    {
        $matches = [
            ['comp' => 'Ligue 1', 'opp' => 'Brest', 'ha' => 'a', 'shots' => [
                [881771, 'Ferran Torres', 80.46, 33.31, 0.0409, 'Goal', 0, 5, 'RegularPlay', 'RightFoot'],
                [999999, 'Joueur Inconnu', 85.00, 34.00, 0.1000, 'Miss', 0, 60, 'RegularPlay', 'LeftFoot'],
            ]],
        ];

        $build = fotmob_build_shotmap($matches, $this->joueurs(), []);

        $this->assertCount(1, $build['shotmap']['players']);
        $this->assertSame(['999999' => 'Joueur Inconnu'], $build['inconnus']);
    }

    public function testChaqueTirPorteSaCompetitionEtSonAdversaire(): void
    {
        $matches = [
            ['comp' => 'Ligue des Champions', 'opp' => 'Slovan Bratislava', 'ha' => 'h', 'shots' => [
                [267365, 'Marquinhos', 101.59, 32.55, 0.7099, 'Goal', 0, 31, 'FromCorner', 'OtherBodyParts'],
            ]],
        ];

        $tir = fotmob_build_shotmap($matches, $this->joueurs(), [])['shotmap']['players']['30']['shots'][0];

        $this->assertSame('Ligue des Champions', $tir['comp']);
        $this->assertSame('Slovan Bratislava', $tir['opp']);
        $this->assertSame('h', $tir['ha']);
        $this->assertSame('goal', $tir['res']);
        $this->assertSame('FromCorner', $tir['sit']);
        $this->assertSame(31, $tir['min']);
    }

    public function testLesMetadonneesDeTeteSontConservees(): void
    {
        $meta = ['season' => '2026-27', 'source' => 'FotMob', 'competitions' => ['Ligue 1']];
        $build = fotmob_build_shotmap([], $this->joueurs(), $meta);

        $this->assertSame('2026-27', $build['shotmap']['season']);
        $this->assertSame('FotMob', $build['shotmap']['source']);
        $this->assertSame(['Ligue 1'], $build['shotmap']['competitions']);
        $this->assertSame([], $build['shotmap']['players']);
    }
}
