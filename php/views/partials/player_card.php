<?php
declare(strict_types=1);

/**
 * Fiche joueur "Matchday" : numéro de maillot condensé, nom, tag de poste teinté,
 * nationalité, badge de traçabilité optionnel.
 * @var int $number
 * @var string $name
 * @var string $position 'GK'|'DF'|'MF'|'FW'
 * @var string $nationality
 * @var string|null $confidence 'verified'|'estimated'
 */
$tagByPosition = ['GK' => 'tag--gk', 'DF' => 'tag--def', 'MF' => 'tag--mid', 'FW' => 'tag--fwd'];
$tagClass = 'tag ' . ($tagByPosition[$position] ?? 'tag--mid');
?>
<div class="player-card">
  <span class="jersey"><?= View::e($number) ?></span>
  <div class="player-card__body">
    <div class="player-card__name"><?= View::e($name) ?></div>
    <div class="player-card__meta">
      <span class="<?= View::e($tagClass) ?>"><?= View::e($position) ?></span>
      <span><?= View::e($nationality) ?></span>
      <?php if (!empty($confidence)): ?>
        <?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
