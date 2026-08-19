<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'PSG Analytics') ?></title>
  <meta name="description" content="<?= View::e($description ?? 'PSG Analytics : la saison 2025-2026 du Paris Saint-Germain en données vérifiées et tracées. Dashboard, effectif, matchs et méthodologie.') ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230c1830'/%3E%3Crect x='12' width='8' height='32' fill='%23ffffff'/%3E%3Crect x='13.5' width='5' height='32' fill='%23e2001a'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="/assets/fonts/fonts.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/charts.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
</head>
<body<?= isset($page) && $page !== '' ? ' data-page="' . View::e($page) . '"' : '' ?>>
  <?php require BASE_PATH . '/php/views/partials/header.php'; ?>
  <main class="container"><?= $content ?></main>
  <?php require BASE_PATH . '/php/views/partials/footer.php'; ?>
  <div id="tip"></div>
  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
