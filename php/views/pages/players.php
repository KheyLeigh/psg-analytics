<?php
declare(strict_types=1);

// Vue Joueurs "Matchday" : navigateur d'effectif rendu côté serveur. Le tableau,
// le filtre par poste et la pagination sont des liens ?sort/order/position/page
// (navigables sans JavaScript) ; assets/js/pages/players.js les enrichit ensuite
// en recherche live, tri au clic et pagination fluide via /api/players, et branche
// le comparateur radar sur /api/compare. Un seul <h1> sur la page.

$posLabels = ['GK' => 'Gardien', 'DF' => 'Défenseur', 'MF' => 'Milieu', 'FW' => 'Attaquant'];
$posPlural = ['GK' => 'Gardiens', 'DF' => 'Défenseurs', 'MF' => 'Milieux', 'FW' => 'Attaquants'];
$tagByPosition = ['GK' => 'tag--gk', 'DF' => 'tag--def', 'MF' => 'tag--mid', 'FW' => 'tag--fwd'];

// Construit une URL /joueurs en ne conservant que les paramètres connus et non
// défaut : jamais de sort/order/position hors liste blanche (déjà validés en amont).
$buildQuery = static function (array $over) use ($sort, $order, $position): string {
    $q = array_merge(['sort' => $sort, 'order' => $order, 'position' => $position, 'page' => 1], $over);
    $parts = [];
    if (!empty($q['position'])) {
        $parts['position'] = $q['position'];
    }
    if ($q['sort'] !== 'last_name' || $q['order'] !== 'ASC') {
        $parts['sort'] = $q['sort'];
        $parts['order'] = $q['order'];
    }
    if ((int) $q['page'] > 1) {
        $parts['page'] = (int) $q['page'];
    }
    return '/joueurs' . ($parts !== [] ? '?' . http_build_query($parts) : '');
};

// Colonnes triables, dans l'ordre d'affichage. La clé est la valeur API (liste blanche).
$columns = [
    'shirt_number' => 'Numéro',
    'last_name'    => 'Nom',
    'position'     => 'Poste',
    'nationality'  => 'Nationalité',
];

// Note : on n'utilise PAS $page ici, réservé au layout (data-page="players") et
// injecté par extract(). La page de pagination courante est $curPage.
$shown = count($players);
$total = (int) $meta['total'];
$curPage = (int) $meta['page'];
$totalPages = (int) $meta['total_pages'];
?>
<div class="stack section players">

  <header class="players-head">
    <p class="players-head__eyebrow">Paris Saint-Germain · Saison 2025-26 · Effectif</p>
    <h1 class="players-head__title">L'effectif, joueur par joueur</h1>
    <p class="players-head__lede">
      Vingt-quatre joueurs, une seule source d'identité. Cherchez, triez, filtrez par poste,
      puis mettez deux profils face à face sur le radar de comparaison.
    </p>
  </header>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <!-- Barre d'outils : recherche live (sur la page chargée), compteur annoncé, filtre poste. -->
  <section class="players-tools" aria-label="Recherche et filtres">
    <div class="search">
      <label class="search__label" for="player-search">Rechercher un joueur</label>
      <input
        type="search"
        id="player-search"
        class="search__input"
        placeholder="Nom, nationalité ou numéro"
        autocomplete="off"
        spellcheck="false">
      <p class="search__count" id="player-count" aria-live="polite">
        <?= View::e($shown) ?> <?= $shown > 1 ? 'joueurs' : 'joueur' ?> sur cette page
      </p>
    </div>

    <nav class="pos-filter" aria-label="Filtrer par poste">
      <a class="pos-filter__item<?= $position === null ? ' is-active' : '' ?>"
         href="<?= View::e($buildQuery(['position' => null, 'page' => 1])) ?>"
         data-position=""<?= $position === null ? ' aria-current="page"' : '' ?>>Tous</a>
      <?php foreach ($positions as $pos): ?>
        <a class="pos-filter__item<?= $position === $pos ? ' is-active' : '' ?>"
           href="<?= View::e($buildQuery(['position' => $pos, 'page' => 1])) ?>"
           data-position="<?= View::e($pos) ?>"<?= $position === $pos ? ' aria-current="page"' : '' ?>><?= View::e($posPlural[$pos] ?? $pos) ?></a>
      <?php endforeach; ?>
    </nav>
  </section>

  <!-- Tableau de l'effectif. L'état de tri/pagination est porté par des data-* pour
       l'hydratation ; sans JS, les liens d'en-tête et de pagination restent actifs. -->
  <section class="panel roster"
    aria-labelledby="roster-h"
    data-cur-page="<?= View::e($curPage) ?>"
    data-per-page="<?= View::e($meta['per_page']) ?>"
    data-total-pages="<?= View::e($totalPages) ?>"
    data-total="<?= View::e($total) ?>"
    data-sort="<?= View::e($sort) ?>"
    data-order="<?= View::e($order) ?>"
    data-position="<?= View::e($position ?? '') ?>">

    <div class="panel__header">
      <h2 class="panel__title" id="roster-h">Effectif complet</h2>
      <span class="panel__more"><?= View::e($total) ?> joueurs · page <?= View::e($curPage) ?>/<?= View::e($totalPages) ?></span>
    </div>

    <div class="table-scroll">
      <table class="table roster-table">
        <thead>
          <tr>
            <?php foreach ($columns as $key => $label):
                $isActive = $sort === $key;
                $ariaSort = $isActive ? ($order === 'ASC' ? 'ascending' : 'descending') : 'none';
                $nextOrder = $isActive && $order === 'ASC' ? 'DESC' : 'ASC';
                $href = $buildQuery(['sort' => $key, 'order' => $nextOrder, 'page' => 1]);
            ?>
              <th scope="col" aria-sort="<?= View::e($ariaSort) ?>" data-sort="<?= View::e($key) ?>">
                <a class="th-sort" href="<?= View::e($href) ?>" data-order="<?= View::e($nextOrder) ?>"><?= View::e($label) ?></a>
              </th>
            <?php endforeach; ?>
            <th scope="col" class="roster-table__cmp-h">Comparer</th>
          </tr>
        </thead>
        <tbody id="roster-body">
          <?php foreach ($players as $p): ?>
            <tr class="roster-row" data-id="<?= View::e($p['id']) ?>" data-name="<?= View::e($p['name']) ?>">
              <td>
                <span class="jersey jersey--sm"><?= View::e($p['number'] ?? '·') ?></span>
              </td>
              <td>
                <span class="roster-row__name"><?= View::e($p['name']) ?></span>
              </td>
              <td>
                <span class="tag <?= View::e($tagByPosition[$p['position']] ?? 'tag--mid') ?>"
                      title="<?= View::e($posLabels[$p['position']] ?? $p['position']) ?>"><?= View::e($p['position']) ?></span>
              </td>
              <td><?= View::e($p['nationality']) ?></td>
              <td class="roster-row__cmp">
                <button type="button" class="cmp-pick" data-id="<?= View::e($p['id']) ?>" data-name="<?= View::e($p['name']) ?>" aria-pressed="false">
                  <span class="cmp-pick__label">Comparer</span>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination SSR : liens précédent/suivant. Le module la reconstruit après un tri. -->
    <nav class="pager" aria-label="Pagination de l'effectif" id="roster-pager">
      <?php if ($curPage > 1): ?>
        <a class="pager__btn" rel="prev" href="<?= View::e($buildQuery(['page' => $curPage - 1])) ?>" data-page="<?= View::e($curPage - 1) ?>">Précédent</a>
      <?php else: ?>
        <span class="pager__btn is-disabled" aria-disabled="true">Précédent</span>
      <?php endif; ?>

      <span class="pager__status" aria-live="polite">Page <?= View::e($curPage) ?> sur <?= View::e($totalPages) ?></span>

      <?php if ($curPage < $totalPages): ?>
        <a class="pager__btn" rel="next" href="<?= View::e($buildQuery(['page' => $curPage + 1])) ?>" data-page="<?= View::e($curPage + 1) ?>">Suivant</a>
      <?php else: ?>
        <span class="pager__btn is-disabled" aria-disabled="true">Suivant</span>
      <?php endif; ?>
    </nav>
  </section>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <!-- Comparateur : deux joueurs face à face sur un radar normalisé. Les totaux par
       joueur sont reconstitués par le générateur déterministe (estimé), ce que le mode
       Transparence fait ressortir ; l'identité (nom, numéro, poste) reste vérifiée. -->
  <section class="panel compare"
    aria-labelledby="compare-h"
    data-est data-src="e"
    data-tip="Radar reconstitué : les totaux par joueur (buts, passes, minutes, tirs, duels, note) sont générés par le générateur déterministe, calibrés sur les totaux vérifiés. L'identité des joueurs reste vérifiée.">
    <div class="panel__header">
      <h2 class="panel__title" id="compare-h">Face à face</h2>
      <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
    </div>

    <p class="compare__hint" id="compare-hint">
      Sélectionnez deux joueurs avec le bouton <span class="cmp-inline">Comparer</span> de chaque ligne.
      La sélection A ressort en <span class="ok">rouge</span>, la sélection B en <span class="blue">bleu</span>.
    </p>

    <div class="compare__slots">
      <div class="cmp-slot cmp-slot--a" id="cmp-slot-a">
        <span class="cmp-slot__tag" aria-hidden="true">A</span>
        <span class="cmp-slot__name">Choisir un joueur</span>
      </div>
      <span class="cmp-slot__vs" aria-hidden="true">contre</span>
      <div class="cmp-slot cmp-slot--b" id="cmp-slot-b">
        <span class="cmp-slot__tag" aria-hidden="true">B</span>
        <span class="cmp-slot__name">Choisir un joueur</span>
      </div>
      <button type="button" class="btn btn--ghost cmp-clear" id="cmp-clear" hidden>Effacer</button>
    </div>

    <div class="compare__canvas" id="compare-radar">
      <p class="chart-fallback">Le radar de comparaison s'affiche ici une fois deux joueurs sélectionnés (JavaScript activé).</p>
    </div>
  </section>

</div>
