<?php
declare(strict_types=1);
// Représente une saison sportive.
final class Season
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly bool $isCurrent,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (string) $r['label'], (string) $r['start_date'], (string) $r['end_date'],
            (bool) $r['is_current'],
        );
    }
}
