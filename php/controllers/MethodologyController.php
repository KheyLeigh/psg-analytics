<?php

declare(strict_types=1);

// Page Méthodologie : d'où viennent les chiffres, pour la saison sélectionnée
// (?saison=, saison courante par défaut). Expose les sources de données et le
// taux de vérification par table, coeur de la promesse de traçabilité du site.
final class MethodologyController extends Controller
{
    public function __construct(
        private ?SourceRepository $sources = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->sources ??= new SourceRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('methodology', $this->buildViewData($season));
    }

    // Assemble sources et couverture pour une saison donnée, isolé du rendu pour
    // rester testable.
    public function buildViewData(Season $season): array
    {
        return [
            'title'    => 'Méthodologie · PSG Analytics',
            'page'     => 'methodology',
            'sources'  => $this->sources->sources(),
            'coverage' => $this->sources->coverageByTable($season->id),
        ] + $this->seasons->navData($season);
    }
}
