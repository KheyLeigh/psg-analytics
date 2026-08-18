<?php

declare(strict_types=1);

// Page Méthodologie : d'où viennent les chiffres. Expose les sources de données et le
// taux de vérification par table, coeur de la promesse de traçabilité du site.
final class MethodologyController extends Controller
{
    public function __construct(
        private ?SourceRepository $sources = null,
    ) {
        $this->sources ??= new SourceRepository();
    }

    public function index(Request $r, array $params): void
    {
        $this->render('methodology', $this->buildViewData());
    }

    // Assemble sources et couverture, isolé du rendu pour rester testable.
    public function buildViewData(): array
    {
        return [
            'title'    => 'Méthodologie · PSG Analytics',
            'page'     => 'methodology',
            'sources'  => $this->sources->sources(),
            'coverage' => $this->sources->coverageByTable(),
        ];
    }
}
