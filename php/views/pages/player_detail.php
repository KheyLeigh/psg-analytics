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
];
if (($totals['rating'] ?? null) !== null) {
    $kpis[] = ['label' => 'Note moy.', 'value' => number_format((float) $totals['rating'], 2, ',', ' ')];
}

// Données transmises au JS pour hydrater les deux graphiques (profil et contribution).
$payload = json_encode(
    ['profile' => $profile, 'timeline' => $timeline],
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

  <div>
    <div class="sec-h">Saison en chiffres</div>
    <div class="grid grid--4">
      <?php foreach ($kpis as $k): ?>
        <?php $label = $k['label']; $value = $k['value']; $confidence = 'verified'; $tip = null; ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>

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

  <script type="application/json" id="pd-data"><?= $payload ?></script>
</div>
