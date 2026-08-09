<?php
declare(strict_types=1);
/**
 * Carte KPI : libellé, valeur, badge de source optionnel.
 * @var string $label
 * @var string|int|float $value
 * @var string|null $confidence
 */
?>
<div class="kpi">
  <span class="kpi__label"><?= View::e($label) ?></span>
  <span class="kpi__value"><?= View::e($value) ?></span>
  <?php if (!empty($confidence)): ?>
    <div class="kpi__footer">
      <?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
    </div>
  <?php endif; ?>
</div>
