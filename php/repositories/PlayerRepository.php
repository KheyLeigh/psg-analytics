<?php
declare(strict_types=1);
// Accès aux joueurs : lecture unitaire, liste complète, pagination filtrée, et
// résolution des saisons jouées par un même joueur (person_id).
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class PlayerRepository extends Repository
{
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];

    public function find(int $id): ?Player
    {
        $row = $this->fetchOne('SELECT * FROM players WHERE id = ?', [$id]);
        return $row ? Player::fromRow($row) : null;
    }

    public function all(int $seasonId): array
    {
        return array_map(
            Player::fromRow(...),
            $this->fetchAll('SELECT * FROM players WHERE season_id = ? ORDER BY last_name', [$seasonId])
        );
    }

    public function paginate(int $seasonId, int $page, int $perPage, string $sortColumn, string $order, ?string $position): array
    {
        $column = in_array($sortColumn, self::SORTABLE, true) ? $sortColumn : 'last_name';
        $dir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $where = 'WHERE season_id = :season' . ($position !== null ? ' AND position = :position' : '');
        $params = $position !== null ? ['season' => $seasonId, 'position' => $position] : ['season' => $seasonId];

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM players {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM players {$where} ORDER BY {$column} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(Player::fromRow(...), $rows), 'total' => $total];
    }

    // Saisons PSG jouées par un même joueur (person_id), triées de la plus ancienne
    // à la plus récente. playerId permet à la vue de lier vers la fiche de chaque
    // saison (une ligne players distincte par saison).
    public function seasonsForPerson(int $personId): array
    {
        $rows = $this->fetchAll(
            'SELECT p.id player_id, s.label, s.is_current
             FROM players p
             JOIN seasons s ON s.id = p.season_id
             WHERE p.person_id = ?
             ORDER BY s.start_date ASC',
            [$personId]
        );
        return array_map(static fn (array $r): array => [
            'label'     => (string) $r['label'],
            'isCurrent' => (bool) $r['is_current'],
            'playerId'  => (int) $r['player_id'],
        ], $rows);
    }
}
