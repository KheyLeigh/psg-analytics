<?php
declare(strict_types=1);
// Vérifie la normalisation 0-1 des axes du radar, sur la clé réelle du repo (duelsWon).
final class ComparisonServiceTest extends TestCase
{
    public function testNormalisationRadar(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $id): array {
                return $id === 1
                    ? ['goals'=>20,'assists'=>10,'minutes'=>3000,'shots'=>80,'duels_won'=>120,'rating'=>7.6]
                    : ['goals'=>10,'assists'=>5,'minutes'=>2000,'shots'=>40,'duels_won'=>60,'rating'=>7.0];
            }
        };
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player {
                return Player::fromRow(['id'=>$id,'season_id'=>1,'shirt_number'=>$id,'first_name'=>'J','last_name'=>"n{$id}",'position'=>'FW','detailed_position'=>'CF','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>180,'is_captain'=>0]);
            }
        };
        $svc = new ComparisonService($stats, $players);
        $res = $svc->compare(1, 2);
        $this->assertSame(1.0, $res['a']['normalized']['goals'], 'le max vaut 1');
        $this->assertSame(0.5, $res['b']['normalized']['goals'], '10/20 = 0.5');
    }

    public function testLitDuelsWonEnCamelCaseSurLaVraieSortieDuRepository(): void
    {
        // seasonTotalsByPlayer réel renvoie duelsWon (camelCase), pas duels_won.
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $id): array {
                return $id === 1
                    ? ['goals'=>0,'assists'=>0,'minutes'=>0,'shots'=>0,'duelsWon'=>40,'rating'=>0]
                    : ['goals'=>0,'assists'=>0,'minutes'=>0,'shots'=>0,'duelsWon'=>10,'rating'=>0];
            }
        };
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player {
                return Player::fromRow(['id'=>$id,'season_id'=>1,'shirt_number'=>$id,'first_name'=>'J','last_name'=>"n{$id}",'position'=>'FW','detailed_position'=>'CF','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>180,'is_captain'=>0]);
            }
        };
        $svc = new ComparisonService($stats, $players);
        $res = $svc->compare(1, 2);
        $this->assertSame(40.0, $res['a']['totals']['duels_won']);
        $this->assertSame(1.0, $res['a']['normalized']['duels_won']);
        $this->assertSame(0.25, $res['b']['normalized']['duels_won'], '10/40 = 0.25');
    }
}
