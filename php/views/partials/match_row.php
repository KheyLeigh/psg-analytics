<?php
declare(strict_types=1);
/**
 * Ligne de match : compétition, adversaire, score, résultat.
 * @var string $competition
 * @var string $opponent
 * @var bool $home
 * @var int $goalsFor
 * @var int $goalsAgainst
 * @var string $result 'W'|'D'|'L'
 * @var string|null $confidence
 */
$resultClass = ['W' => 'match-row__result--w', 'D' => 'match-row__result--d', 'L' => 'match-row__result--l'][$result] ?? 'match-row__result--d';
$teams = $home ? "PSG – {$opponent}" : "{$opponent} – PSG";
?>
<div class="match-row">
  <span class="match-row__competition"><?= View::e($competition) ?></span>
  <span class="match-row__teams"><?= View::e($teams) ?></span>
  <span class="match-row__score"><?= View::e($goalsFor) ?> – <?= View::e($goalsAgainst) ?></span>
  <span class="match-row__result <?= View::e($resultClass) ?>"><?= View::e($result) ?></span>
  <?php if (!empty($confidence)): ?>
    <?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
  <?php endif; ?>
</div>
