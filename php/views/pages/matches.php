<?php
declare(strict_types=1);

// Page Matchs : la saison rencontre par rencontre. Liste filtrable (compétition,
// résultat) et paginée, rendue côté serveur. $matches, $competitions, $results,
// $filters, $meta sont fournis par MatchController::index. Toutes les données sont
// vérifiées (feuilles de match).

$resultMeta = [
    'W' => ['V', 'pill--w', 'Victoire'],
    'D' => ['N', 'pill--n', 'Match nul'],
    'L' => ['D', 'pill--l', 'Défaite'],
];

// Construit une URL de filtre en conservant l'autre critère et en repartant page 1.
$filterHref = static function (?int $competitionId, ?string $result): string {
    $params = [];
    if ($competitionId !== null) {
        $params['competition_id'] = $competitionId;
    }
    if ($result !== null) {
        $params['result'] = $result;
    }
    return '/matchs' . ($params ? '?' . http_build_query($params) : '');
};

$currentComp = $filters['competition_id'];
$currentResult = $filters['result'];
?>
<div class="stack section">
  <div>
    <div class="hero__eyebrow">Paris Saint-Germain · Saison 2025-26 · Calendrier</div>
    <h1 class="mt-title">La saison, match par match</h1>
    <p class="mt-lede">Les <?= View::e($meta['total']) ?> rencontres de la saison, filtrables par compétition et par résultat. Chaque score vient des feuilles de match.</p>
  </div>

  <div class="hechter dash-sep" aria-hidden="true"></div>

  <div class="mt-filters">
    <div class="mt-filter-group" role="group" aria-label="Filtrer par compétition">
      <a href="<?= View::e($filterHref(null, $currentResult)) ?>" class="chip<?= $currentComp === null ? ' chip--on' : '' ?>"<?= $currentComp === null ? ' aria-current="true"' : '' ?>>Toutes compétitions</a>
      <?php foreach ($competitions as $c): ?>
        <a href="<?= View::e($filterHref($c['id'], $currentResult)) ?>" class="chip<?= $currentComp === $c['id'] ? ' chip--on' : '' ?>"<?= $currentComp === $c['id'] ? ' aria-current="true"' : '' ?>><?= View::e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="mt-filter-group" role="group" aria-label="Filtrer par résultat">
      <a href="<?= View::e($filterHref($currentComp, null)) ?>" class="chip<?= $currentResult === null ? ' chip--on' : '' ?>"<?= $currentResult === null ? ' aria-current="true"' : '' ?>>Tous résultats</a>
      <?php foreach ($results as $code): [$letter] = $resultMeta[$code]; ?>
        <a href="<?= View::e($filterHref($currentComp, $code)) ?>" class="chip<?= $currentResult === $code ? ' chip--on' : '' ?>"<?= $currentResult === $code ? ' aria-current="true"' : '' ?>><?= View::e(['W' => 'Victoires', 'D' => 'Nuls', 'L' => 'Défaites'][$code]) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($matches === []): ?>
    <p class="roster-empty">Aucune rencontre ne correspond à ces filtres.</p>
  <?php else: ?>
    <section class="panel">
      <table class="table mt-table">
        <caption class="visually-hidden">Les rencontres de la saison, avec date, compétition, adversaire, score et résultat.</caption>
        <thead>
          <tr>
            <th scope="col">Date</th>
            <th scope="col">Compétition</th>
            <th scope="col">Rencontre</th>
            <th scope="col">Score</th>
            <th scope="col">Résultat</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matches as $m): [$letter, $pill, $resultLabel] = $resultMeta[$m['result']] ?? ['N', 'pill--n', 'Match nul']; ?>
            <tr class="mt-row" onclick="location='/matchs/<?= View::e($m['id']) ?>'">
              <td class="mt-date"><?= View::e($m['playedAt']) ?></td>
              <td><?= View::e($m['competition']) ?><?php if (!empty($m['round'])): ?> <span class="mt-round"><?= View::e($m['round']) ?></span><?php endif; ?></td>
              <td class="mt-teams">
                <a href="/matchs/<?= View::e($m['id']) ?>"><?= View::e($m['home']) ?> <span class="mt-vs">contre</span> <?= View::e($m['away']) ?></a>
              </td>
              <td>
                <span class="mt-score" aria-label="<?= View::e($m['homeGoals']) ?> à <?= View::e($m['awayGoals']) ?>"><?= View::e($m['homeGoals']) ?><span class="mt-score__sep" aria-hidden="true">·</span><?= View::e($m['awayGoals']) ?></span>
                <?php if (!empty($m['penaltyShootout'])): ?><span class="mt-tab" title="Vainqueur aux tirs au but">t.a.b. <?= View::e($m['penaltyScore']) ?></span><?php endif; ?>
              </td>
              <td><span class="pill <?= View::e($pill) ?>" title="<?= View::e($resultLabel) ?>" aria-label="<?= View::e($resultLabel) ?>"><?= View::e($letter) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <?php if ($meta['total_pages'] > 1): ?>
      <nav class="mt-pager" aria-label="Pagination des matchs">
        <?php $prev = max(1, $meta['page'] - 1); $next = min($meta['total_pages'], $meta['page'] + 1); ?>
        <?php
          $pageHref = static function (int $p) use ($currentComp, $currentResult): string {
              $params = ['page' => $p];
              if ($currentComp !== null) { $params['competition_id'] = $currentComp; }
              if ($currentResult !== null) { $params['result'] = $currentResult; }
              return '/matchs?' . http_build_query($params);
          };
        ?>
        <a class="btn btn--ghost<?= $meta['page'] <= 1 ? ' is-disabled' : '' ?>"<?= $meta['page'] <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?> href="<?= View::e($pageHref($prev)) ?>">Précédent</a>
        <span class="mt-pager__state">Page <?= View::e($meta['page']) ?> sur <?= View::e($meta['total_pages']) ?></span>
        <a class="btn<?= $meta['page'] >= $meta['total_pages'] ? ' is-disabled' : '' ?>"<?= $meta['page'] >= $meta['total_pages'] ? ' aria-disabled="true" tabindex="-1"' : '' ?> href="<?= View::e($pageHref($next)) ?>">Suivant</a>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
