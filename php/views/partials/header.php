<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="container row row--between">
    <a href="/" class="brand">PSG <span class="brand__accent">Analytics</span></a>
    <?php require BASE_PATH . '/php/views/partials/nav.php'; ?>
    <button type="button" id="theme-toggle" class="btn btn--ghost" aria-label="Basculer le thème clair/sombre">
      <span aria-hidden="true">&#9789;</span>
    </button>
  </div>
</header>
