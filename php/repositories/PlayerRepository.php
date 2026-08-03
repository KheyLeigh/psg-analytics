<?php
declare(strict_types=1);
// Accès aux joueurs : lecture unitaire, liste complète, pagination filtrée.
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class PlayerRepository extends Repository
{
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];

    public function find(int $id): ?Player
    {
        $row = $this->fetchOne('SELECT * FROM players WHERE id = ?', [$id]);
        return $row ? Player::fromRow($row) : null;
    }

    public function all(): array
    {
        return array_map(
            Player::fromRow(...),
            $this->fetchAll('SELECT * FROM players ORDER BY last_name')
        );
    }

    public function paginate(int $page, int $perPage, string $sortColumn, string $order, ?string $position): array
    {
        $column = in_array($sortColumn, self::SORTABLE, true) ? $sortColumn : 'last_name';
        $dir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $where = $position !== null ? 'WHERE position = :position' : '';
        $params = $position !== null ? ['position' => $position] : [];

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM players {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM players {$where} ORDER BY {$column} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(Player::fromRow(...), $rows), 'total' => $total];
    }
}
