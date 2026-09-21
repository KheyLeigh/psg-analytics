<?php
declare(strict_types=1);

// Fiche joueur : identité, saison en chiffres (totaux vérifiés FBref), radar de profil
// (normalisé contre le meilleur de l'effectif par axe) et courbe de contribution
// (attribution par match estimée, signalée comme telle). $player, $totals, $profile,
// $timeline sont fournis par PlayerController::show.

$posTag = ['GK' => 'tag--gk', 'DF' => 'tag--def', 'MF' => 'tag--mid', 'FW' => 'tag--fwd'][$player['position']] ?? 'tag--mid';

// Chiffres-clés de la saison (vérifiés). La note moyenne n'est ajoutée que si connue.
$kpis = [
    ['label' => 'Buts', 'value' => $totals['goals']],
    ['label' => 'Passes déc.', 'value' => $totals['assists']],
    ['label' => 'Matchs', 'value' => $totals['matches']],
    ['label' => 'Minutes', 'value' => $totals['minutes']],
    ['label' => 'Tacles gagnés', 'value' => $totals['duelsWon']],
    ['label' => 'Interceptions', 'value' => $totals['interceptions']],
];
if (($totals['rating'] ?? null) !== null) {
    $kpis[] = ['label' => 'Note moy.', 'value' => number_format((float) $totals['rating'], 2, ',', ' ')];
}

// Données transmises au JS pour hydrater les graphiques (profil, contribution, tirs,
// présence sur le terrain).
$payload = json_encode(
    ['profile' => $profile, 'timeline' => $timeline, 'shotmap' => $shotmap ?? null, 'heatmap' => $heatmap ?? null],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
?>
<div class="stack section pd">
  <a href="/joueurs" class="pd__back">Retour à l'effectif</a>

  <header class="pd__head">
    <span class="jersey pd__jersey"><?= View::e($player['number']) ?></span>
    <div class="pd__ident">
      <h1 class="pd__name"><?= View::e($player['name']) ?></h1>
      <div class="pd__meta">
        <span class="tag <?= View::e($posTag) ?>"><?= View::e($player['position']) ?></span>
        <?php if (!empty($player['detailedPosition'])): ?><span><?= View::e($player['detailedPosition']) ?></span><?php endif; ?>
        <span><?= View::e($player['nationality']) ?></span>
        <?php if (!empty($player['foot'])): ?><span>pied <?= View::e($player['foot']) ?></span><?php endif; ?>
        <?php if (!empty($player['heightCm'])): ?><span><?= View::e($player['heightCm']) ?> cm</span><?php endif; ?>
        <?php if (!empty($player['isCaptain'])): ?><span class="pd__captain">capitaine</span><?php endif; ?>
        <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      </div>
    </div>
  </header>

  <?php if (!empty($seasons) && count($seasons) > 1): ?>
    <nav aria-label="Saisons PSG de ce joueur" class="pd__seasons">
      <?php foreach ($seasons as $s): ?>
        <a href="/joueurs/<?= View::e($s['playerId']) ?>" class="tag<?= $s['playerId'] === $player['id'] ? ' tag--active' : '' ?>">
          <?= View::e($s['label']) ?><?= $s['isCurrent'] ? ' (en cours)' : '' ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <section aria-labelledby="pd-season-h">
    <h2 class="sec-h" id="pd-season-h">Saison en chiffres</h2>
    <div class="grid grid--4">
      <?php foreach ($kpis as $k): ?>
        <?php $label = $k['label']; $value = $k['value']; $confidence = 'verified'; $tip = null; ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="grid grid--2">
    <section class="panel pd__panel">
      <div class="panel__header">
        <h2 class="panel__title">Profil</h2>
        <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      </div>
      <p class="pd__panel-sub">Chaque axe est rapporté au meilleur total de l'effectif : le joueur ressort de 0 à 1.</p>
      <div id="pd-radar" class="pd__chart">
        <p class="chart-fallback">Le radar de profil s'affiche ici (JavaScript activé).</p>
      </div>
    </section>

    <section class="panel pd__panel" data-est data-src="e" data-tip="Contribution par match : l'attribution des buts et passes décisives à chaque rencontre est estimée (répartition déterministe des totaux vérifiés).">
      <div class="panel__header">
        <h2 class="panel__title">Contribution cumulée</h2>
        <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      </div>
      <p class="pd__panel-sub">Buts et passes décisives additionnés au fil des matchs.</p>
      <div id="pd-line" class="pd__chart">
        <p class="chart-fallback">La courbe de contribution s'affiche ici (JavaScript activé).</p>
      </div>
    </section>
  </div>

  <?php if (!empty($shotmap)): ?>
    <section class="panel" aria-labelledby="pd-shots-h">
      <div class="panel__header">
        <h2 class="panel__title" id="pd-shots-h">Carte des tirs · <?= View::e(trim(($shotmap['competition'] ?? '') . ' ' . ($shotmap['season'] ?? ''))) ?></h2>
        <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      </div>
      <p class="pd__panel-sub">
        <?= View::e((string) $shotmap['shots_total']) ?> tirs, <?= View::e((string) $shotmap['goals']) ?> buts,
        <?= View::e(number_format((float) $shotmap['xg_total'], 2, ',', ' ')) ?> xG. Chaque tir a sa position réelle (source <?= View::e($shotmap['source']) ?>), taille selon le xG.
      </p>
      <div id="pd-shotmap" class="shotmap-wrap">
        <p class="chart-fallback">La carte des tirs s'affiche ici (JavaScript activé).</p>
      </div>
      <div class="sm-legend">
        <span class="sm-legend__item"><span class="sm-key sm-key--goal"></span>But</span>
        <?php
          $couvertes = $shotmap['competitions'] ?? [];
          $ordreFichier = $shotmap['competitions_all'] ?? $couvertes;
          // La légende nomme les compétitions dès que la carte en distingue
          // plusieurs, même si ce joueur n'a tiré que dans l'une d'elles : sans
          // ça, son trait discontinu ne serait expliqué nulle part.
        ?>
        <?php if (count($ordreFichier) > 1): ?>
          <?php foreach ($couvertes as $competition): ?>
            <?php $rang = array_search($competition, $ordreFichier, true); ?>
            <span class="sm-legend__item"><span class="sm-key<?= $rang === 1 ? ' sm-key--comp2' : '' ?>"></span><?= View::e($competition) ?></span>
          <?php endforeach; ?>
          <span class="sm-legend__item">Taille = xG</span>
        <?php else: ?>
          <span class="sm-legend__item"><span class="sm-key"></span>Tir (taille = xG)</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($shotsByComp)): ?>
        <div class="sm-comp">
          <h3 class="sm-comp__title">Tirs par compétition</h3>
          <?php // Couverture annoncée d'après les données, pas en dur : elle dépend de
                // la source de la saison ET des compétitions où ce joueur a tiré
                // (libellé assemblé par PlayerController::shotmapCompetitionLabel()). ?>
          <p class="pd__panel-sub">La carte ci-dessus couvre <?= View::e($shotmap['competition']) ?> (positions réelles, source <?= View::e($shotmap['source']) ?>). Les compétitions non couvertes sont ici en volume (tirs et buts, source FBref).</p>
          <div class="table-scroll">
            <table class="table sm-comp__table">
              <thead>
                <tr>
                  <th scope="col">Compétition</th>
                  <th scope="col" class="sm-comp__num">Tirs</th>
                  <th scope="col" class="sm-comp__num">Buts</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($shotsByComp['rows'] as $r): ?>
                  <tr>
                    <td><?= View::e($r['competition']) ?></td>
                    <td class="sm-comp__num"><?= View::e((string) $r['shots']) ?></td>
                    <td class="sm-comp__num"><?= View::e((string) $r['goals']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <?php if (count($shotsByComp['rows']) > 1): ?>
                <tfoot>
                  <tr>
                    <th scope="row">Toutes compétitions</th>
                    <td class="sm-comp__num"><?= View::e((string) $shotsByComp['total']['shots']) ?></td>
                    <td class="sm-comp__num"><?= View::e((string) $shotsByComp['total']['goals']) ?></td>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($heatmap)): ?>
    <section class="panel" aria-labelledby="pd-heatmap-h">
      <div class="panel__header">
        <h2 class="panel__title" id="pd-heatmap-h">Présence sur le terrain · <?= View::e($heatmap['season']) ?></h2>
        <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      </div>
      <p class="pd__panel-sub">
        Densité de touches de balle sur <?= View::e((string) $heatmap['matches']) ?> matchs, <?= View::e($heatmap['competitions']) ?> (source <?= View::e($heatmap['source']) ?>). Les zones les plus sollicitées ressortent en rouge le plus soutenu.
      </p>
      <div id="pd-heatmap" class="pitchmap-wrap">
        <p class="chart-fallback">La carte de présence s'affiche ici (JavaScript activé).</p>
      </div>
    </section>
  <?php endif; ?>

  <script type="application/json" id="pd-data"><?= $payload ?></script>
</div>
