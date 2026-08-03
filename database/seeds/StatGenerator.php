<?php
declare(strict_types=1);
// Générateur de statistiques déterministe : à graine fixe, sortie reproductible.
final class StatGenerator
{
    public function __construct(int $seed = 2026)
    {
        mt_srand($seed);
    }

    /**
     * Tire $count unités entières au prorata de $weights (échantillonnage
     * pondéré déterministe, sans plancher ni plafond) : la somme rendue vaut
     * exactement $count. Implémentation unique utilisée à la fois par la
     * migration (répartition des buts non ancrés) et par les tests.
     * Note : un éventuel plafond par joueur (ex. « aucun buteur ne dépasse
     * 11 ») n'est pas imposé ici, c'est une invariance souple vérifiée en
     * aval par les tests d'intégrité (SeedIntegrityTest).
     * @param array<int,int> $weights player_id => poids
     * @return array<int,int> player_id => quantité tirée
     */
    public function distributeByWeight(int $count, array $weights): array
    {
        $result = array_fill_keys(array_keys($weights), 0);
        $ids = array_keys($weights);
        $sum = array_sum($weights);
        for ($i = 0; $i < $count; $i++) {
            $pick = mt_rand(1, $sum);
            $cursor = 0;
            foreach ($ids as $id) {
                $cursor += $weights[$id];
                if ($pick <= $cursor) {
                    $result[$id]++;
                    break;
                }
            }
        }
        return $result;
    }

    public function rating(int $goals, int $assists, int $minutes): float
    {
        $base = 6.0 + $goals * 0.7 + $assists * 0.4;
        $base += $minutes >= 60 ? 0.2 : 0.0;
        return round(min(10.0, $base), 1);
    }

    /**
     * @param array<int,bool> $lineup player_id => est_titulaire
     * @return array<int,int> player_id => minutes
     */
    public function minutesForMatch(array $lineup): array
    {
        $minutes = [];
        foreach ($lineup as $id => $starter) {
            $minutes[$id] = $starter ? mt_rand(60, 90) : mt_rand(1, 30);
        }
        return $minutes;
    }
}
