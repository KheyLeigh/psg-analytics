<?php
declare(strict_types=1);
// Accès aux saisons : résolution de la saison affichée (courante par défaut, ou
// par slug via ?saison=), liste complète pour le sélecteur du header.
final class SeasonRepository extends Repository
{
    public function current(): Season
    {
        $row = $this->fetchOne('SELECT * FROM seasons WHERE is_current = 1');
        if ($row === null) {
            throw new RuntimeException('Aucune saison courante définie (is_current)');
        }
        return Season::fromRow($row);
    }

    public function bySlug(string $slug): ?Season
    {
        $row = $this->fetchOne('SELECT * FROM seasons WHERE label = ?', [$slug]);
        return $row ? Season::fromRow($row) : null;
    }

    // Résout la saison à afficher : le slug demandé s'il correspond à une saison
    // connue, sinon la saison courante. Ne lève jamais pour un slug absent/invalide.
    public function resolve(?string $slug): Season
    {
        if ($slug !== null && $slug !== '') {
            $season = $this->bySlug($slug);
            if ($season !== null) {
                return $season;
            }
        }
        return $this->current();
    }

    public function all(): array
    {
        return array_map(Season::fromRow(...), $this->fetchAll('SELECT * FROM seasons ORDER BY start_date'));
    }

    // Données du sélecteur de saison (header) : toutes les saisons connues et le
    // label actuellement sélectionné, pour surligner l'option active côté vue.
    public function navData(Season $selected): array
    {
        return [
            'seasons' => array_map(static fn (Season $s): array => [
                'label' => $s->label,
                'isCurrent' => $s->isCurrent,
            ], $this->all()),
            'selectedSeason' => $selected->label,
        ];
    }
}
