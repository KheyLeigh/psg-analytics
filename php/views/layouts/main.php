<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'PSG Analytics') ?></title>
  <link rel="stylesheet" href="/assets/fonts/inter.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/charts.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
</head>
<body>
  <?php require BASE_PATH . '/php/views/partials/header.php'; ?>
  <main class="container"><?= $content ?></main>
  <?php require BASE_PATH . '/php/views/partials/footer.php'; ?>
  <div id="tip"></div>
  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
