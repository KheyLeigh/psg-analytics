<?php
declare(strict_types=1);

/**
 * Ligne de match "Matchday" : compétition, affiche (PSG placé selon domicile), score
 * en chiffres condensés, pastille de résultat V/N/D et badge de traçabilité optionnel.
 * @var string $competition
 * @var string $opponent
 * @var bool $home
 * @var int $goalsFor Buts marqués par le PSG
 * @var int $goalsAgainst Buts encaissés par le PSG
 * @var string $result 'W'|'D'|'L'
 * @var string|null $confidence 'verified'|'estimated'
 */
$pillByResult = ['W' => 'pill--w', 'D' => 'pill--n', 'L' => 'pill--l'];
$letterByResult = ['W' => 'V', 'D' => 'N', 'L' => 'D'];
$pillClass = 'pill ' . ($pillByResult[$result] ?? 'pill--n');
$resultLetter = $letterByResult[$result] ?? 'N';

// Le PSG est à gauche à domicile, à droite à l'extérieur ; le score suit ce placement.
$homeTeam = $home ? 'PSG' : $opponent;
$awayTeam = $home ? $opponent : 'PSG';
$homeGoals = $home ? $goalsFor : $goalsAgainst;
$awayGoals = $home ? $goalsAgainst : $goalsFor;
?>
<div class="match-row">
  <div class="match-row__competition"><?= View::e($competition) ?></div>
  <div class="match-row__score">
    <span class="match-row__team"><?= View::e($homeTeam) ?></span>
    <span class="match-row__figures">
      <span class="match-row__sc"><?= View::e($homeGoals) ?><span class="match-row__sep">·</span><?= View::e($awayGoals) ?></span>
      <span class="<?= View::e($pillClass) ?>" title="Résultat"><?= View::e($resultLetter) ?></span>
    </span>
    <span class="match-row__team match-row__team--away"><?= View::e($awayTeam) ?></span>
  </div>
  <?php if (!empty($confidence)): ?>
    <div style="margin-top:12px"><?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?></div>
  <?php endif; ?>
</div>
