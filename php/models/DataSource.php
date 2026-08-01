<?php
declare(strict_types=1);
// Représente une source de données (traçabilité des statistiques).
final class DataSource
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $url,
        public readonly ?string $collectedAt,
        public readonly string $confidence,
        public readonly ?string $note,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (string) $r['label'], $r['url'] ?? null,
            $r['collected_at'] ?? null, (string) $r['confidence'], $r['note'] ?? null,
        );
    }
}
