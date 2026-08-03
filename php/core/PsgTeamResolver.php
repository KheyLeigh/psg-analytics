<?php
declare(strict_types=1);
// Résout l'identifiant de l'équipe PSG en base (aucun TeamRepository dédié à ce jour).
final class PsgTeamResolver
{
    public static function id(): int
    {
        return (int) Database::connection()->query('SELECT id FROM teams WHERE is_psg = 1')->fetchColumn();
    }
}
