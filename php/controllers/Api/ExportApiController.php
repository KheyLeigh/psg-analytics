<?php
declare(strict_types=1);
// Frontière HTTP des exports : assemble via CsvExporter/PdfExporter pour la saison
// sélectionnée (?saison=, saison courante par défaut), pose les en-têtes de téléchargement.
final class ExportApiController extends Controller
{
    // Nombre de buteurs inclus dans les exports (toutes compétitions confondues).
    private const TOP_LIMIT = 50;

    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->seasons ??= new SeasonRepository();
    }

    private function resolveSeason(Request $r): Season
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        return $this->seasons->resolve($slug !== '' ? $slug : null);
    }

    public function playersCsv(Request $r, array $params): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="joueurs.csv"');
        echo $this->buildCsv($this->resolveSeason($r));
    }

    public function buildCsv(Season $season): string
    {
        $rows = array_map(static function (array $row): array {
            return [$row['player']->fullName(), $row['goals'], $row['assists'], $row['minutes']];
        }, $this->stats->topScorers($season->id, self::TOP_LIMIT, null));

        return CsvExporter::fromRows(['Joueur', 'Buts', 'Passes', 'Minutes'], $rows);
    }

    public function reportPdf(Request $r, array $params): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="rapport.pdf"');
        echo $this->buildPdf($this->resolveSeason($r));
    }

    public function buildPdf(Season $season): string
    {
        $lines = array_map(
            static fn(array $row): string => sprintf(
                '%s - %d buts, %d passes décisives',
                $row['player']->fullName(),
                $row['goals'],
                $row['assists'],
            ),
            $this->stats->topScorers($season->id, self::TOP_LIMIT, null),
        );

        return PdfExporter::simpleReport('Rapport PSG Analytics', [
            ['heading' => 'Meilleurs buteurs', 'lines' => $lines],
        ]);
    }
}
