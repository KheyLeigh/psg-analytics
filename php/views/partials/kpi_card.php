<?php
declare(strict_types=1);

/**
 * Carte KPI "Matchday" : libellé en capitales, valeur en chiffres condensés, badge
 * de traçabilité. Les cartes estimées portent data-est (surlignage en mode
 * transparence) et un texte de provenance optionnel affiché au survol.
 * @var string $label
 * @var string|int|float $value
 * @var string|null $confidence 'verified'|'estimated'
 * @var string|null $tip Texte de provenance affiché au survol
 * @var int|null $countup Cible entière pour l'effet compteur (facultatif, opt-in)
 */
$isEstimated = ($confidence ?? '') === 'estimated';
$tipText = $tip ?? '';
$countupTarget = isset($countup) && is_numeric($countup) ? (int) $countup : null;
?>
<div class="card"
  <?= $isEstimated ? 'data-est' : '' ?>
  <?php if ($tipText !== ''): ?>data-src="<?= $isEstimated ? 'e' : 'v' ?>" data-tip="<?= View::e($tipText) ?>"<?php endif; ?>>
  <span class="card__label"><?= View::e($label) ?></span>
  <span class="card__value"<?= $countupTarget !== null ? ' data-countup="' . $countupTarget . '"' : '' ?>><?= View::e($value) ?></span>
  <?php if (!empty($confidence)): ?>
    <?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
  <?php endif; ?>
</div>
