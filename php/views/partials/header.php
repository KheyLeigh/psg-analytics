<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="container site-header__bar">
    <a href="/" class="brand"><span class="crest" aria-hidden="true"></span>PSG&nbsp;<span class="brand__accent">Analytics</span></a>
    <?php require BASE_PATH . '/php/views/partials/nav.php'; ?>
    <div class="header-spacer"></div>
    <button type="button" id="transp" class="transp-btn" aria-label="Activer le mode transparence des données estimées">
      <span class="transp-btn__sw" aria-hidden="true"></span>Transparence
    </button>
    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Basculer le thème clair ou sombre">
      <span aria-hidden="true">&#9790;</span>
    </button>
  </div>
  <div class="hechter site-header__stripe" aria-hidden="true"></div>
</header>
