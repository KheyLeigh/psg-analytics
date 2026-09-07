<?php
declare(strict_types=1);
// Vérifie la résolution de la saison affichée : courante par défaut, par slug si
// valide, retombée sur la courante si le slug est invalide ou absent.
final class SeasonRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $pdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        return $pdo;
    }

    public function testCurrentRenvoieLaSaisonCourante(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->current()->label);
    }

    public function testBySlugRenvoieLaSaisonCorrespondante(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $s = $repo->bySlug('2025-26');
        $this->assertTrue($s instanceof Season);
        $this->assertSame(1, $s->id);
    }

    public function testBySlugRenvoieNullSiInconnue(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame(null, $repo->bySlug('1999-00'));
    }

    public function testResolveRetombeSurLaCouranteSiSlugAbsent(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->resolve(null)->label);
        $this->assertSame('2026-27', $repo->resolve('')->label);
    }

    public function testResolveRetombeSurLaCouranteSiSlugInvalide(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->resolve('nimportequoi')->label);
    }

    public function testResolveRenvoieLaSaisonDemandeeSiValide(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2025-26', $repo->resolve('2025-26')->label);
    }

    public function testAllRenvoieLesDeuxSaisonsTrieesParDate(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $all = $repo->all();
        $this->assertCount(2, $all);
        $this->assertSame('2025-26', $all[0]->label);
        $this->assertSame('2026-27', $all[1]->label);
    }

    public function testNavDataExposeLesSaisonsEtLaSelection(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $nav = $repo->navData($repo->bySlug('2025-26'));
        $this->assertSame('2025-26', $nav['selectedSeason']);
        $this->assertSame(['2025-26', '2026-27'], array_column($nav['seasons'], 'label'));
        $this->assertSame(false, $nav['seasons'][0]['isCurrent']);
        $this->assertSame(true, $nav['seasons'][1]['isCurrent']);
    }
}
