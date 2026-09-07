<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="container site-header__bar">
    <a href="/" class="brand"><span class="crest" aria-hidden="true"></span>PSG&nbsp;<span class="brand__accent">Analytics</span></a>
    <?php require BASE_PATH . '/php/views/partials/nav.php'; ?>
    <div class="header-spacer"></div>
    <?php if (isset($seasons, $selectedSeason)): ?>
      <form method="get" class="season-switch">
        <label for="saison-select" class="sr-only">Saison</label>
        <select name="saison" id="saison-select" class="season-switch__select" onchange="this.form.submit()">
          <?php foreach ($seasons as $s): ?>
            <option value="<?= View::e($s['label']) ?>"<?= $s['label'] === $selectedSeason ? ' selected' : '' ?>><?= View::e($s['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="season-switch__go">OK</button>
      </form>
    <?php endif; ?>
    <button type="button" id="transp" class="transp-btn" aria-label="Activer le mode transparence des données estimées">
      <span class="transp-btn__sw" aria-hidden="true"></span>Transparence
    </button>
    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Basculer le thème clair ou sombre">
      <span aria-hidden="true">&#9790;</span>
    </button>
  </div>
  <div class="hechter site-header__stripe" aria-hidden="true"></div>
</header>
