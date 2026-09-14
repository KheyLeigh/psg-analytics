<?php
declare(strict_types=1);
// Frontière HTTP des exports : assemble via CsvExporter/PdfExporter pour la saison
// sélectionnée (?saison=, saison courante par défaut), pose les en-têtes de téléchargement.
final class ExportApiController extends Controller
{
    // Nombre de buteurs inclus dans les exports.
    private const TOP_LIMIT = 50;

    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?SeasonRepository $seasons = null,
        private ?CompetitionRepository $comps = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->seasons ??= new SeasonRepository();
        $this->comps ??= new CompetitionRepository();
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

    // Scopé explicitement à la Ligue 1 : sans ce filtre, un joueur ayant aussi
    // marqué en Ligue des Champions verrait ses totaux des deux compétitions
    // additionnés dans cet export, sans qu'aucune colonne ne le précise.
    public function buildCsv(Season $season): string
    {
        $leagueId = $this->comps->leagueId() ?? 0;
        $rows = array_map(static function (array $row): array {
            return [$row['player']->fullName(), $row['goals'], $row['assists'], $row['minutes']];
        }, $this->stats->topScorers($season->id, self::TOP_LIMIT, $leagueId));

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
        $leagueId = $this->comps->leagueId() ?? 0;
        $lines = array_map(
            static fn(array $row): string => sprintf(
                '%s - %d buts, %d passes décisives',
                $row['player']->fullName(),
                $row['goals'],
                $row['assists'],
            ),
            $this->stats->topScorers($season->id, self::TOP_LIMIT, $leagueId),
        );

        return PdfExporter::simpleReport('Rapport PSG Analytics', [
            ['heading' => 'Meilleurs buteurs Ligue 1', 'lines' => $lines],
        ]);
    }
}
