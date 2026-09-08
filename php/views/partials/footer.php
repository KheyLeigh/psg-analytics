<?php declare(strict_types=1); ?>
<?php
// Ce partial est inclus sur toutes les pages, y compris les fiches (player_detail/
// match_detail) où $selectedSeason n'existe pas : le lien reste alors nu.
$footerMethodoHref = '/methodologie' . (isset($selectedSeason) ? '?saison=' . urlencode($selectedSeason) : '');
?>
<footer class="site-footer">
  <span>Chaque chiffre remonte à sa source.</span>
  <div class="site-footer__legend">
    <span class="trace trace--ok"><span class="trace__dot" aria-hidden="true"></span>Donnée vérifiée</span>
    <span class="trace trace--est"><span class="trace__dot" aria-hidden="true"></span>Donnée estimée</span>
  </div>
  <span>Sources et méthode : voir <a href="<?= View::e($footerMethodoHref) ?>">méthodologie</a>.</span>
</footer>
