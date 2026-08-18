<?php

declare(strict_types=1);

// Page Joueurs : le navigateur d'effectif. La coquille et la PREMIERE PAGE sont
// rendues côté serveur (SSR) pour rester navigables sans JavaScript : le tri, le
// filtre par poste et la pagination passent par des liens ?sort/order/position/page
// que le contrôleur relit et valide par liste blanche. Recherche live, tri au clic,
// pagination fluide et comparateur radar sont ensuite branchés en amélioration
// progressive par assets/js/pages/players.js sur les endpoints /api/players et
// /api/compare (mêmes listes blanches, côté client comme côté serveur).
final class PlayerController extends Controller
{
    // Listes blanches partagées avec l'API : aucune URL n'est forgée hors de ces valeurs.
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];
    private const POSITIONS = ['GK', 'DF', 'MF', 'FW'];

    // Taille de page de l'effectif : 12 pour montrer une pagination réelle (24 joueurs
    // sur deux pages) tout en gardant des lignes de tableau confortables.
    private const PER_PAGE = 12;

    public function __construct(
        private ?PlayerRepository $players = null,
    ) {
        $this->players ??= new PlayerRepository();
    }

    public function index(Request $r, array $params): void
    {
        $this->render('players', $this->buildViewData($_GET));
    }

    // Assemble les données de la page à partir des paramètres de requête, validés par
    // liste blanche. Isolé de index() (aucun rendu ni effet de bord) pour rester
    // testable, à l'image des contrôleurs d'API du projet.
    public function buildViewData(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? self::PER_PAGE, 1, 50, self::PER_PAGE);
        $sort = Validator::inList($query['sort'] ?? 'last_name', self::SORTABLE, 'last_name');
        // is_string() avant strtoupper() : un paramètre forgé en tableau (?order[]=x) ne
        // doit jamais atteindre une fonction de chaîne (sinon warning "Array to string").
        $orderRaw = $query['order'] ?? 'ASC';
        $order = Validator::inList(is_string($orderRaw) ? strtoupper($orderRaw) : '', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? (Validator::inList($query['position'], self::POSITIONS, '') ?: null)
            : null;

        $res = $this->players->paginate($page, $perPage, $sort, $order, $position);
        // Mêmes champs d'identité que /api/players : id, numéro, nom, poste, nationalité.
        $items = array_map(static fn (Player $p): array => [
            'id' => $p->id,
            'number' => $p->shirtNumber,
            'name' => $p->fullName(),
            'position' => $p->position,
            'nationality' => $p->nationality,
        ], $res['items']);

        $total = (int) $res['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        // Page demandée au-delà du dernier index (ex : filtre qui réduit le total) :
        // on la ramène dans les bornes pour un affichage cohérent des contrôles.
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'title'     => 'Joueurs · PSG Analytics',
            'page'      => 'players',
            'players'   => $items,
            'meta'      => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
            'sort'      => $sort,
            'order'     => $order,
            'position'  => $position,
            'positions' => self::POSITIONS,
        ];
    }
}
