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

    // Axes du radar de profil : même famille que le comparateur (ComparisonService),
    // chacun mappé vers sa clé de totaux et son libellé lisible.
    private const PROFILE_AXES = [
        'goals'    => 'Buts',
        'assists'  => 'Passes déc.',
        'minutes'  => 'Minutes',
        'shots'    => 'Tirs',
        'duelsWon' => 'Tacles gagnés',
        'rating'   => 'Note',
    ];

    public function __construct(
        private ?PlayerRepository $players = null,
        private ?StatisticRepository $stats = null,
        private ?SeasonRepository $seasons = null,
        private ?CompetitionRepository $comps = null,
    ) {
        $this->players ??= new PlayerRepository();
        $this->stats ??= new StatisticRepository();
        $this->seasons ??= new SeasonRepository();
        $this->comps ??= new CompetitionRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('players', $this->buildViewData($_GET, $season));
    }

    public function show(Request $r, array $params): void
    {
        $id = Validator::int($params['id'] ?? 0, 1, PHP_INT_MAX, 0);
        $comp = Validator::inList($r->query('comp', 'ligue1'), ['ligue1', 'all'], 'ligue1');
        $data = $this->buildDetail($id, $comp === 'all');
        if ($data === null) {
            // Joueur introuvable : même rendu 404 que le front controller (index.php).
            Response::html(View::render('errors/404', [], 'main'), 404);
            return;
        }
        $this->render('player_detail', $data);
    }

    // Assemble la fiche joueur (identité, totaux saison, profil normalisé contre le
    // meilleur de l'effectif par axe, timeline de contribution), isolé du rendu pour
    // rester testable. Renvoie null si le joueur est introuvable (le contrôleur répond
    // alors 404). Traçabilité : les totaux saison sont vérifiés (FBref/Sofascore),
    // l'attribution par match de la timeline est estimée (la vue la signale comme telle).
    //
    // $allCompetitions : false (par défaut) scope à la Ligue 1 ; sans ce filtre, un
    // joueur ayant aussi marqué en Ligue des Champions verrait ses totaux des deux
    // compétitions additionnés sur sa fiche sans qu'aucun libellé ne le précise. true
    // additionne volontairement toutes les compétitions couvertes par des stats
    // vérifiées (choix explicite de l'utilisateur via le bouton de la fiche, jamais le
    // défaut silencieux).
    public function buildDetail(int $id, bool $allCompetitions = false): ?array
    {
        $player = $this->players->find($id);
        if ($player === null) {
            return null;
        }

        $season = $this->seasons->find($player->seasonId);
        $seasonLabel = $season?->label ?? '';

        $competitionId = $allCompetitions ? null : ($this->comps->leagueId() ?? 0);
        $totals = $this->stats->seasonTotalsByPlayer($id, $competitionId);
        $max = $this->stats->squadAxisMax($player->seasonId, $competitionId);

        $axes = [];
        $values = [];
        foreach (self::PROFILE_AXES as $key => $label) {
            $axes[] = $label;
            $ceiling = (float) ($max[$key] ?? 0);
            $value = (float) ($totals[$key] ?? 0);
            $values[] = $ceiling > 0 ? round($value / $ceiling, 4) : 0.0;
        }

        return [
            'title'    => $player->fullName() . ' · PSG Analytics',
            // data-page vaut le nom de la vue (player_detail) : View::render conserve le
            // nom de template via extract(EXTR_SKIP). Le routeur app.js s'aligne dessus.
            'page'     => 'player_detail',
            'shotmap'  => $this->shotmap($id, $player->fullName(), $seasonLabel),
            'shotsByComp' => $this->shotsByCompetition($id, $seasonLabel),
            'heatmap'  => $this->heatmap($id, $seasonLabel),
            'seasons'  => $this->players->seasonsForPerson($player->personId),
            'player'   => [
                'id'               => $player->id,
                'number'           => $player->shirtNumber,
                'name'             => $player->fullName(),
                'position'         => $player->position,
                'detailedPosition' => $player->detailedPosition,
                'foot'             => $player->foot,
                'nationality'      => $player->nationality,
                'birthDate'        => $player->birthDate,
                'heightCm'         => $player->heightCm,
                'isCaptain'        => $player->isCaptain,
            ],
            'totals'   => $totals,
            'profile'  => ['axes' => $axes, 'values' => $values],
            'timeline' => $this->stats->timeline($id),
            'comp'     => $allCompetitions ? 'all' : 'ligue1',
        ];
    }

    // Chemin du fichier de tirs pour une saison donnée (ex. '2025-26' ->
    // .../2025-26/shots-2025.json). Le nom ne porte pas la source : elle a changé
    // en cours de route (Understat pour 2025-26, FotMob à partir de 2026-27) et
    // c'est le champ `source` du fichier qui en fait foi, pas son nom. Statique
    // et pure : testable sans accès disque, et réutilisée par shotmap().
    public static function shotmapPath(string $seasonLabel): string
    {
        $year = substr($seasonLabel, 0, 4);
        return BASE_PATH . "/database/seeds/verified/{$seasonLabel}/shots-{$year}.json";
    }

    // Compétitions réellement couvertes par les tirs d'un joueur, dans l'ordre
    // déclaré par le fichier. Le fichier couvre la saison entière, un joueur non :
    // annoncer "Ligue 1 et Ligue des Champions" pour quelqu'un qui n'a tiré qu'en
    // C1 serait faux. Un fichier antérieur à FotMob ne porte pas de `comp` par
    // tir : on retombe alors sur l'ordre fourni, valable pour toute la saison.
    // Statique et pure : testable sans accès disque.
    public static function shotmapCompetitions(array $shots, array $ordre): array
    {
        $presentes = [];
        foreach ($shots as $shot) {
            if (isset($shot['comp']) && $shot['comp'] !== '') {
                $presentes[$shot['comp']] = true;
            }
        }
        if ($presentes === []) {
            return array_values($ordre);
        }
        $retenues = [];
        foreach ($ordre as $competition) {
            if (isset($presentes[$competition])) {
                $retenues[] = $competition;
                unset($presentes[$competition]);
            }
        }
        // Compétition vue dans les tirs mais absente de l'entête : on la garde en
        // queue plutôt que de la perdre sans bruit, c'est le signe d'un fichier
        // incohérent qui doit rester visible.
        return array_merge($retenues, array_keys($presentes));
    }

    // Énumération lisible en français : "A", "A et B", "A, B et C".
    public static function shotmapCompetitionLabel(array $competitions): string
    {
        if (count($competitions) < 2) {
            return (string) ($competitions[0] ?? '');
        }
        return implode(', ', array_slice($competitions, 0, -1)) . ' et ' . end($competitions);
    }

    // Chemin du fichier de tirs par compétition FBref pour une saison donnée.
    public static function shotsByCompetitionPath(string $seasonLabel): string
    {
        $year = substr($seasonLabel, 0, 4);
        return BASE_PATH . "/database/seeds/verified/{$seasonLabel}/fbref-shots-by-competition-{$year}.json";
    }

    // Carte des tirs du joueur : tirs vérifiés avec coordonnées réelles, indexés
    // par id joueur, pour la saison du joueur consulté. Renvoie null si le joueur
    // n'a pas de tirs référencés, ou si aucun fichier n'existe encore pour cette
    // saison. `competitions` liste celles que la carte couvre réellement : elle
    // varie selon la source (Understat ne donnait que la Ligue 1, FotMob couvre
    // aussi la Ligue des Champions), et la vue s'en sert pour ne pas annoncer une
    // couverture fausse.
    private function shotmap(int $id, string $fullName, string $seasonLabel): ?array
    {
        if ($seasonLabel === '') {
            return null;
        }
        $file = self::shotmapPath($seasonLabel);
        if (!is_file($file)) {
            return null;
        }
        $all = json_decode((string) file_get_contents($file), true);
        $entry = $all['players'][(string) $id] ?? null;
        if (!$entry || empty($entry['shots'])) {
            return null;
        }
        // Fichiers antérieurs à FotMob : une seule compétition, en clair dans
        // `competition`. On la reprend telle quelle plutôt que de laisser la vue
        // deviner.
        $ordre = $all['competitions'] ?? array_filter([$all['competition'] ?? '']);
        $competitions = self::shotmapCompetitions($entry['shots'], $ordre);
        return [
            'player'       => $entry['name'] ?? $fullName,
            'season'       => $all['season'] ?? '',
            'competition'  => self::shotmapCompetitionLabel($competitions),
            'competitions' => $competitions,
            // Ordre complet du fichier : c'est lui qui fixe l'apparence d'une
            // compétition sur la carte, et pas le sous-ensemble du joueur. Sans
            // ça, la Ligue des Champions serait en pointillés chez un joueur qui
            // a aussi tiré en L1, et en trait plein chez un joueur qui n'a tiré
            // qu'en C1 : la même compétition changerait de tête d'une fiche à
            // l'autre.
            'competitions_all' => array_values($ordre),
            'source'       => $all['source'] ?? '',
            'shots_total'  => (int) ($entry['shots_total'] ?? count($entry['shots'])),
            'goals'        => (int) ($entry['goals'] ?? 0),
            'xg_total'     => (float) ($entry['xg_total'] ?? 0),
            'shots'        => $entry['shots'],
        ];
    }

    // Tirs par compétition (FBref), pour la saison du joueur consulté. Renvoie
    // null si aucun fichier n'existe encore pour cette saison.
    private function shotsByCompetition(int $id, string $seasonLabel): ?array
    {
        if ($seasonLabel === '') {
            return null;
        }
        $file = self::shotsByCompetitionPath($seasonLabel);
        if (!is_file($file)) {
            return null;
        }
        $all = json_decode((string) file_get_contents($file), true);
        $entry = $all['players'][(string) $id] ?? null;
        if (!$entry || empty($entry['byCompetition'])) {
            return null;
        }
        return [
            'season' => $all['season'] ?? '',
            'source' => $all['source'] ?? '',
            'rows'   => $entry['byCompetition'],
            'total'  => $entry['total'] ?? ['shots' => 0, 'goals' => 0],
        ];
    }

    // Chemin du fichier de heatmap de présence Sofascore pour une saison donnée.
    public static function heatmapPath(string $seasonLabel): string
    {
        $year = substr($seasonLabel, 0, 4);
        return BASE_PATH . "/database/seeds/verified/{$seasonLabel}/sofascore-heatmap-{$year}.json";
    }

    // Heatmap de présence sur le terrain (densité de touches, toutes compétitions
    // confondues), pour la saison du joueur consulté. Renvoie null si le joueur n'a
    // pas de heatmap référencée, ou si aucun fichier n'existe encore pour cette
    // saison (saison en cours ou pas encore de relevé Sofascore).
    private function heatmap(int $id, string $seasonLabel): ?array
    {
        if ($seasonLabel === '') {
            return null;
        }
        $file = self::heatmapPath($seasonLabel);
        if (!is_file($file)) {
            return null;
        }
        $all = json_decode((string) file_get_contents($file), true);
        $entry = $all['players'][(string) $id] ?? null;
        if (!$entry || empty($entry['points'])) {
            return null;
        }
        return [
            'season'       => $all['season'] ?? '',
            'competitions' => $all['competitions'] ?? '',
            'source'       => $all['source'] ?? '',
            'matches'      => (int) ($entry['matches'] ?? 0),
            'points'       => $entry['points'],
        ];
    }

    // Assemble les données de la page à partir des paramètres de requête, validés par
    // liste blanche. Isolé de index() (aucun rendu ni effet de bord) pour rester
    // testable, à l'image des contrôleurs d'API du projet.
    public function buildViewData(array $query, Season $season): array
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

        $res = $this->players->paginate($season->id, $page, $perPage, $sort, $order, $position);
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
        ] + $this->seasons->navData($season);
    }
}
