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
     * Répartit $total buts entiers sur des joueurs selon des poids,
     * en garantissant que la somme finale égale exactement $total
     * et que chaque poids fourni est un plancher (ancrage des buteurs connus).
     * @param array<int,int> $weights player_id => poids/plancher
     * @return array<int,int> player_id => buts
     */
    public function distributeGoals(int $total, array $weights): array
    {
        $result = $weights;                 // les planchers vérifiés
        $assigned = array_sum($weights);
        $remaining = $total - $assigned;
        if ($remaining <= 0) {
            return $result;                 // déjà au total, rien à ajouter
        }
        $ids = array_keys($weights);
        $sumWeights = array_sum($weights);
        for ($i = 0; $i < $remaining; $i++) {
            $pick = mt_rand(1, $sumWeights);
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
