<?php
declare(strict_types=1);
// Accès à l'équipe : résolution de l'identifiant du PSG et des noms d'équipes.
// Non final : les tests sous-classent en doublure (idiome du projet).
class TeamRepository extends Repository
{
    public function psgId(): int
    {
        $row = $this->fetchOne('SELECT id FROM teams WHERE is_psg = 1');
        return $row !== null ? (int) $row['id'] : 0;
    }

    // Table de correspondance identifiant vers nom d'équipe : les matchs ne stockent
    // que des identifiants, la page Matchs résout les noms via cette carte.
    public function namesById(): array
    {
        $map = [];
        foreach ($this->fetchAll('SELECT id, name FROM teams') as $row) {
            $map[(int) $row['id']] = (string) $row['name'];
        }
        return $map;
    }
}
