<?php
declare(strict_types=1);

/**
 * Badge de traçabilité "Matchday" : point PLEIN vert pour le vérifié, anneau CREUX
 * ambre (en italique) pour l'estimé. La forme porte le sens autant que la couleur.
 * @var string $confidence 'verified'|'estimated'
 */
$isEstimated = ($confidence ?? 'verified') === 'estimated';
$label = $isEstimated ? 'estimé' : 'vérifié';
$class = 'trace ' . ($isEstimated ? 'trace--est' : 'trace--ok');
?>
<span class="<?= View::e($class) ?>" title="Fiabilité de la donnée">
  <span class="trace__dot" aria-hidden="true"></span><?= View::e($label) ?>
</span>
