<?php
declare(strict_types=1);

// Accès aux sources de données et au taux de vérification par table. Sert la page
// Méthodologie : chaque ligne de données porte un source_id vers data_sources, dont
// la confiance (verified / estimated) est la clé de voûte de la traçabilité du site.
// Non final : les tests sous-classent en doublure (idiome du projet).
class SourceRepository extends Repository
{
    // Tables porteuses d'un source_id, avec leur libellé lisible. Clés fixes (jamais
    // une entrée utilisateur) : leur interpolation dans la requête est sans risque.
    private const TABLES = [
        'matches'             => 'Matchs (score, possession, affluence)',
        'player_season_stats' => 'Bilans joueurs (toutes compétitions)',
        'player_match_stats'  => 'Statistiques par match (attribution)',
    ];

    public function sources(): array
    {
        return array_map(static fn (array $r): array => [
            'label'       => (string) $r['label'],
            'confidence'  => (string) $r['confidence'],
            'url'         => $r['url'] !== null ? (string) $r['url'] : null,
            'note'        => (string) ($r['note'] ?? ''),
            'collectedAt' => (string) ($r['collected_at'] ?? ''),
        ], $this->fetchAll('SELECT label, confidence, url, note, collected_at FROM data_sources ORDER BY id'));
    }

    // Taux de vérification par table : part des lignes reliées à une source vérifiée.
    public function coverageByTable(): array
    {
        $out = [];
        foreach (self::TABLES as $table => $label) {
            $row = $this->fetchOne(
                "SELECT COUNT(*) total,
                        SUM(CASE WHEN d.confidence = 'verified' THEN 1 ELSE 0 END) verified
                 FROM {$table} t
                 JOIN data_sources d ON d.id = t.source_id"
            ) ?? [];
            $total = (int) ($row['total'] ?? 0);
            $verified = (int) ($row['verified'] ?? 0);
            $out[] = [
                'label'    => $label,
                'total'    => $total,
                'verified' => $verified,
                'pct'      => $total > 0 ? (int) round($verified / $total * 100) : 0,
            ];
        }
        return $out;
    }
}
