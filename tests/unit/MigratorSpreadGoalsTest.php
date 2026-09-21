<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/MigratorStats.php';

// Vérifie la répartition des buts saison sur des matchs précis. Le total réparti
// doit toujours valoir la somme des totaux joueurs vérifiés : un but perdu ici
// disparaît du classement des buteurs sans que rien ne le signale.
final class MigratorSpreadGoalsTest extends TestCase
{
    private function totalReparti(array $agg): int
    {
        $total = 0;
        foreach ($agg as $parMatch) {
            foreach ($parMatch as $ligne) {
                $total += $ligne['goals'] ?? 0;
            }
        }
        return $total;
    }

    // Cas de la saison 2026-27 : aucun but contre son camp adverse, la somme des
    // buts joueurs égale celle des buts d'équipe. Aucun but ne doit être retiré.
    public function testSansDeficitTousLesButsSontRepartis(): void
    {
        mt_srand(2026);
        $matches = [
            ['id' => 1, 'psg_goals' => 2],
            ['id' => 2, 'psg_goals' => 2],
            ['id' => 3, 'psg_goals' => 1],
            ['id' => 4, 'psg_goals' => 1],
            ['id' => 5, 'psg_goals' => 2],
        ];
        $goalTotals = [10 => 4, 20 => 3, 30 => 1]; // 8 buts, comme l'équipe

        $agg = [];
        migrator_spread_goals($agg, $matches, $goalTotals);

        $this->assertSame(8, $this->totalReparti($agg), 'les 8 buts joueurs doivent tous être placés');
        foreach ($goalTotals as $playerId => $attendu) {
            $this->assertSame($attendu, array_sum(array_column($agg[$playerId] ?? [], 'goals')), "total du joueur {$playerId}");
        }
    }

    // Cas de la saison 2025-26 : 73 buts joueurs pour 74 buts d'équipe, l'écart
    // venant d'un but contre son camp adverse. Le match le plus prolifique
    // absorbe l'écart, et aucun joueur ne se voit attribuer ce but.
    public function testUnDeficitEstAbsorbeParLeMatchLePlusProlifique(): void
    {
        mt_srand(2026);
        $matches = [
            ['id' => 1, 'psg_goals' => 4],
            ['id' => 2, 'psg_goals' => 2],
            ['id' => 3, 'psg_goals' => 1],
        ];
        $goalTotals = [10 => 3, 20 => 3]; // 6 buts joueurs pour 7 buts d'équipe

        $agg = [];
        migrator_spread_goals($agg, $matches, $goalTotals);

        $this->assertSame(6, $this->totalReparti($agg));
        $parMatch = [];
        foreach ($agg as $joueur) {
            foreach ($joueur as $matchId => $ligne) {
                $parMatch[$matchId] = ($parMatch[$matchId] ?? 0) + ($ligne['goals'] ?? 0);
            }
        }
        $this->assertSame(3, $parMatch[1] ?? 0, 'le match à 4 buts n\'en reçoit que 3');
        $this->assertSame(2, $parMatch[2] ?? 0);
        $this->assertSame(1, $parMatch[3] ?? 0);
    }

    // Plus de buts joueurs que de buts d'équipe : donnée incohérente. Elle doit
    // faire échouer la migration, pas être tronquée en silence.
    public function testPlusDeButsJoueursQueDePlacesLeveUneErreur(): void
    {
        mt_srand(2026);
        $matches = [['id' => 1, 'psg_goals' => 1], ['id' => 2, 'psg_goals' => 1]];
        $goalTotals = [10 => 3];

        $this->assertThrows(
            static function () use ($matches, $goalTotals): void {
                $agg = [];
                migrator_spread_goals($agg, $matches, $goalTotals);
            },
            RuntimeException::class,
            'un excédent de buts joueurs doit être signalé'
        );
    }

    // Un écart de plus d'une unité doit se répartir sans jamais faire déborder un
    // match au-delà du nombre de buts que le PSG y a réellement marqués.
    public function testUnDeficitDePlusieursButsNeFaitDeborderAucunMatch(): void
    {
        mt_srand(2026);
        $matches = [
            ['id' => 1, 'psg_goals' => 3],
            ['id' => 2, 'psg_goals' => 2],
            ['id' => 3, 'psg_goals' => 2],
        ];
        $goalTotals = [10 => 3, 20 => 2]; // 5 buts joueurs pour 7 buts d'équipe

        $agg = [];
        migrator_spread_goals($agg, $matches, $goalTotals);

        $this->assertSame(5, $this->totalReparti($agg));
        foreach ($matches as $match) {
            $recu = 0;
            foreach ($agg as $joueur) {
                $recu += $joueur[$match['id']]['goals'] ?? 0;
            }
            $this->assertTrue(
                $recu <= $match['psg_goals'],
                "le match {$match['id']} reçoit {$recu} buts pour {$match['psg_goals']} marqués"
            );
        }
    }
}
