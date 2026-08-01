<?php
declare(strict_types=1);
// Accès aux événements d'un match, dans l'ordre chronologique.
final class EventRepository extends Repository
{
    public function byMatch(int $matchId): array
    {
        return array_map(
            Event::fromRow(...),
            $this->fetchAll(
                'SELECT * FROM events WHERE match_id = ? ORDER BY minute ASC, stoppage ASC',
                [$matchId]
            )
        );
    }
}
