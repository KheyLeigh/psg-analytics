<?php
declare(strict_types=1);
// Représente une compétition (championnat, coupe, etc.).
final class Competition
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $scope,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (string) $r['name'], (string) $r['type'], (string) $r['scope'],
        );
    }
}
