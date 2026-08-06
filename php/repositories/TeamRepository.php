<?php
declare(strict_types=1);
// Accès à l'équipe : résolution de l'identifiant du PSG en base.
final class TeamRepository extends Repository
{
    public function psgId(): int
    {
        $row = $this->fetchOne('SELECT id FROM teams WHERE is_psg = 1');
        return $row !== null ? (int) $row['id'] : 0;
    }
}
