<?php
declare(strict_types=1);
// Page de démonstration du design system : sert à vérifier visuellement les composants
// (clair/sombre) et reste disponible comme référence vivante pour les phases suivantes.

$kpis = [
    ['label' => 'Victoires L1', 'value' => 24, 'confidence' => 'verified'],
    ['label' => 'Buts marqués', 'value' => 128, 'confidence' => 'verified'],
    ['label' => 'Possession moyenne', 'value' => '58%', 'confidence' => 'estimated'],
    ['label' => 'xG cumulé', 'value' => '61.4', 'confidence' => 'estimated'],
];

$players = [
    ['number' => 30, 'name' => 'Lucas Chevalier', 'position' => 'GK', 'nationality' => 'France', 'confidence' => 'verified'],
    ['number' => 5, 'name' => 'Marquinhos', 'position' => 'DF', 'nationality' => 'Brésil', 'confidence' => 'verified'],
    ['number' => 8, 'name' => 'Fabián Ruiz', 'position' => 'MF', 'nationality' => 'Espagne', 'confidence' => 'verified'],
    ['number' => 29, 'name' => 'Bradley Barcola', 'position' => 'FW', 'nationality' => 'France', 'confidence' => 'estimated'],
];

$matches = [
    ['competition' => 'Ligue 1', 'opponent' => 'Marseille', 'home' => true, 'goalsFor' => 3, 'goalsAgainst' => 1, 'result' => 'W', 'confidence' => 'verified'],
    ['competition' => 'Ligue 1', 'opponent' => 'Lyon', 'home' => false, 'goalsFor' => 2, 'goalsAgainst' => 2, 'result' => 'D', 'confidence' => 'verified'],
    ['competition' => 'Ligue des champions', 'opponent' => 'Bayern Munich', 'home' => true, 'goalsFor' => 1, 'goalsAgainst' => 2, 'result' => 'L', 'confidence' => 'estimated'],
];

$scorers = [
    ['name' => 'Bradley Barcola', 'position' => 'FW', 'goals' => 11, 'matches' => 29],
    ['name' => 'Ousmane Dembélé', 'position' => 'FW', 'goals' => 10, 'matches' => 22],
    ['name' => 'Khvicha Kvaratskhelia', 'position' => 'FW', 'goals' => 8, 'matches' => 28],
];
?>
<div class="stack section">
  <div>
    <h1>Styleguide</h1>
    <p>Référence vivante des composants du design system : jetons, cartes, KPI, badges de source, boutons, tableaux, tags de poste, fiches joueur et lignes de match. Utiliser le bouton de la barre de navigation pour basculer entre thème clair et sombre.</p>
  </div>

  <section>
    <h2>Cartes KPI</h2>
    <div class="grid grid--4">
      <?php foreach ($kpis as $kpi): extract($kpi, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card stack">
    <h2>Badges de source</h2>
    <div class="row">
      <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
    </div>
  </section>

  <section class="card stack">
    <h2>Boutons</h2>
    <div class="row">
      <button type="button" class="btn">Défaut</button>
      <button type="button" class="btn btn--primary">Primaire</button>
      <button type="button" class="btn btn--ghost">Discret</button>
    </div>
  </section>

  <section class="card stack">
    <h2>Tags de poste</h2>
    <div class="row">
      <span class="tag tag--gk">GK</span>
      <span class="tag tag--def">DF</span>
      <span class="tag tag--mid">MF</span>
      <span class="tag tag--fwd">FW</span>
    </div>
  </section>

  <section class="card">
    <div class="card__header">
      <h2 class="card__title">Tableau triable — top buteurs</h2>
    </div>
    <table class="table">
      <thead>
        <tr>
          <th aria-sort="descending">Joueur</th>
          <th>Poste</th>
          <th aria-sort="none">Buts</th>
          <th aria-sort="none">Matchs</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($scorers as $s): ?>
          <tr>
            <td><?= View::e($s['name']) ?></td>
            <td><span class="tag tag--fwd"><?= View::e($s['position']) ?></span></td>
            <td><?= View::e($s['goals']) ?></td>
            <td><?= View::e($s['matches']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>Fiches joueur</h2>
    <div class="grid grid--2">
      <?php foreach ($players as $p): extract($p, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/player_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card__header">
      <h2 class="card__title">Derniers matchs</h2>
    </div>
    <div>
      <?php foreach ($matches as $m): extract($m, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/match_row.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
</div>
