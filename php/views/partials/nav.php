<?php declare(strict_types=1); ?>
<?php
// Les fiches (player_detail/match_detail) incluent ce partial sans $selectedSeason :
// dans ce cas, on laisse les liens nus (comportement actuel).
$navSuffix = isset($selectedSeason) ? ('?saison=' . urlencode($selectedSeason)) : '';
?>
<nav class="nav" aria-label="Navigation principale">
  <ul class="nav__list">
    <li><a href="/dashboard<?= $navSuffix ?>" class="nav__link">Dashboard</a></li>
    <li><a href="/joueurs<?= $navSuffix ?>" class="nav__link">Joueurs</a></li>
    <li><a href="/matchs<?= $navSuffix ?>" class="nav__link">Matchs</a></li>
    <li><a href="/methodologie<?= $navSuffix ?>" class="nav__link">Méthodologie</a></li>
  </ul>
</nav>
