<?php
declare(strict_types=1);

// Fiche match : identité de la rencontre, score (et résultat aux tirs au but si la
// rencontre s'est décidée ainsi), statistiques vérifiées du PSG (possession, tirs,
// affluence). Les événements détaillés minute par minute ne sont pas disponibles en
// base : la vue le signale honnêtement plutôt que d'inventer. $match est fourni par
// MatchController::show.

$resultMeta = [
    'W' => ['V', 'pill--w', 'Victoire'],
    'D' => ['N', 'pill--n', 'Match nul'],
    'L' => ['D', 'pill--l', 'Défaite'],
];
[$letter, $pill, $resultLabel] = $resultMeta[$match['result']] ?? ['N', 'pill--n', 'Match nul'];

$venueLabel = ['home' => 'à domicile', 'away' => 'à l\'extérieur', 'neutral' => 'terrain neutre'][$match['venue']] ?? '';

// Statistiques vérifiées à afficher (uniquement celles connues pour la rencontre).
$stats = [];
if ($match['possession'] !== null) {
    $stats[] = ['Possession', number_format((float) $match['possession'], 1, ',', ' ') . ' %'];
}
if (($match['shots'] ?? null) !== null) {
    $stats[] = ['Tirs', $match['shots']];
}
if (($match['shotsOnTarget'] ?? null) !== null) {
    $stats[] = ['Tirs cadrés', $match['shotsOnTarget']];
}
if (($match['attendance'] ?? null) !== null) {
    $stats[] = ['Affluence', number_format((int) $match['attendance'], 0, ',', ' ')];
}
?>
<div class="stack section md">
  <a href="/matchs" class="pd__back">Retour au calendrier</a>

  <header class="md__head">
    <div class="md__comp">
      <?= View::e($match['competition']) ?><?php if (!empty($match['round'])): ?> · <?= View::e($match['round']) ?><?php endif; ?>
      · <?= View::e($match['playedAt']) ?><?php if ($venueLabel !== ''): ?> · <?= View::e($venueLabel) ?><?php endif; ?>
    </div>
    <h1 class="md__score">
      <span class="md__team"><?= View::e($match['home']) ?></span>
      <span class="md__nums"><?= View::e($match['homeGoals']) ?><span class="md__sep">·</span><?= View::e($match['awayGoals']) ?></span>
      <span class="md__team md__team--away"><?= View::e($match['away']) ?></span>
    </h1>
    <div class="md__result">
      <span class="pill <?= View::e($pill) ?>"><?= View::e($letter) ?></span>
      <span><?= View::e($resultLabel) ?> côté PSG</span>
      <?php if (!empty($match['penaltyShootout'])): ?>
        <span class="md__tab">Vainqueur aux tirs au but (<?= View::e($match['penaltyScore']) ?>)</span>
      <?php elseif (!empty($match['wentToExtra'])): ?>
        <span class="md__tab">Après prolongation</span>
      <?php endif; ?>
      <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
    </div>
  </header>

  <?php if ($stats !== []): ?>
    <section class="panel">
      <div class="panel__header"><h2 class="panel__title">Statistiques du PSG</h2></div>
      <div class="md__stats">
        <?php foreach ($stats as [$label, $value]): ?>
          <div class="md__stat">
            <span class="md__stat-num"><?= View::e($value) ?></span>
            <span class="md__stat-label"><?= View::e($label) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <p class="md__note">Le détail des événements minute par minute (buts, cartons, remplacements) n'est pas disponible pour cette rencontre. Seules les données de synthèse, vérifiées, sont présentées.</p>
</div>
