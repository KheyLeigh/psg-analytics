<?php
declare(strict_types=1);
/**
 * Badge de source : point + libellé, discret. Attend $confidence ('verified'|'estimated').
 * @var string $confidence
 */
$isEstimated = ($confidence ?? 'verified') === 'estimated';
$label = $isEstimated ? 'estimé' : 'vérifié';
$class = 'badge' . ($isEstimated ? ' badge--estimated' : '');
?>
<span class="<?= View::e($class) ?>" title="Fiabilité de la donnée">
  <span class="badge__dot" aria-hidden="true"></span><?= View::e($label) ?>
</span>
