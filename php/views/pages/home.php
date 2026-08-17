<?php

declare(strict_types=1);

// Accueil "Matchday" : la couverture du site. Récit de saison, promesse de
// traçabilité et portes d'entrée vers les pages profondes. Tout est rendu côté
// serveur à partir de données réelles ; les rares touches animées sont opt-in et se
// coupent sous prefers-reduced-motion. Un seul <h1> sur la page : le titre du hero.

$goalsFor = (int) $record['goals_for'];
$cleanSheets = (int) $kpi['clean_sheets'];

// Vitrine partagée avec le hero : le nombre de trophées nourrit aussi le récit.
$trophies = [
    ['name' => 'Ligue des Champions', 'stat' => 'sacre européen'],
    ['name' => 'Ligue 1', 'stat' => $totalPoints . ' pts · ' . $record['wins'] . '-' . $record['draws'] . '-' . $record['losses']],
    ['name' => 'Coupe de France', 'stat' => 'doublé national'],
    ['name' => 'Trophée des Champions', 'stat' => 'supercoupe de France'],
];

// Chiffres phares de la saison : quelques totaux vérifiés en très gros, badges à
// l'appui. Volontairement peu nombreux (l'Accueil raconte, le Dashboard détaille).
$headline = [
    [
        'label' => 'Points de Ligue 1', 'value' => $totalPoints, 'countup' => $totalPoints,
        'confidence' => 'verified',
        'tip' => 'Cumul V=3 / N=1 / D=0 sur les 34 journées de Ligue 1, résultats vérifiés FBref.',
    ],
    [
        'label' => 'Trophées 2025-26', 'value' => count($trophies), 'countup' => count($trophies),
        'confidence' => 'verified',
        'tip' => 'Ligue des Champions, Ligue 1, Coupe de France et Trophée des Champions.',
    ],
    [
        'label' => 'Buts marqués', 'value' => $goalsFor, 'countup' => $goalsFor,
        'confidence' => 'verified',
        'tip' => 'Total des buts inscrits par le PSG sur la saison de Ligue 1 (source FBref).',
    ],
    [
        'label' => 'Clean sheets', 'value' => $cleanSheets, 'countup' => $cleanSheets,
        'confidence' => 'verified',
        'tip' => 'Matchs de Ligue 1 sans encaisser de but, comptés sur les scores vérifiés.',
    ],
];

// Portes d'entrée : chaque carte dit en un mot ce qu'on trouve derrière.
$entries = [
    [
        'href' => '/dashboard', 'kicker' => 'Analyse',
        'title' => 'Dashboard', 'desc' => 'La course au titre, la possession et les buts, graphiques à l\'appui.',
    ],
    [
        'href' => '/joueurs', 'kicker' => 'Effectif',
        'title' => 'Joueurs', 'desc' => 'Fiches, totaux et comparaisons, poste par poste.',
    ],
    [
        'href' => '/matchs', 'kicker' => 'Calendrier',
        'title' => 'Matchs', 'desc' => 'Chaque rencontre de la saison, résultat et feuille de match.',
    ],
    [
        'href' => '/methodologie', 'kicker' => 'Sources',
        'title' => 'Méthodologie', 'desc' => 'D\'où viennent les chiffres, vérifié contre estimé.',
    ],
];

$formLetters = ['W' => 'V', 'D' => 'N', 'L' => 'D'];
$formPills = ['W' => 'pill--w', 'D' => 'pill--n', 'L' => 'pill--l'];
$formTitles = ['W' => 'Victoire', 'D' => 'Nul', 'L' => 'Défaite'];
?>
<div class="stack section">
  <?php
  $heroEyebrow = 'Paris Saint-Germain · Saison 2025-26';
  $heroTitle = 'Une saison, quatre trophées';
  $heroYear = '25·26';
  require BASE_PATH . '/php/views/partials/hero.php';
  ?>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <section class="home-promise" aria-labelledby="home-promise-h">
    <div class="home-promise__text">
      <div class="sec-h" id="home-promise-h" style="margin-top:0">La promesse</div>
      <p class="home-promise__lede">Chaque chiffre de ce site remonte à sa source. Une donnée <strong class="ok">vérifiée</strong> vient des feuilles de match officielles. Une donnée <em class="est">estimée</em> est reconstituée par notre générateur, et signalée comme telle.</p>
      <div class="home-promise__legend">
        <span class="trace trace--ok"><span class="trace__dot" aria-hidden="true"></span>Donnée vérifiée</span>
        <span class="trace trace--est"><span class="trace__dot" aria-hidden="true"></span>Donnée estimée</span>
      </div>
      <p class="home-promise__more">
        Le <button type="button" class="linklike" id="home-transp-hint">mode Transparence</button> fait ressortir les estimations partout sur le site.
        Méthode complète : <a href="/methodologie">voir la méthodologie</a>.
      </p>
    </div>
  </section>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <section aria-labelledby="home-key-h">
    <div class="sec-h" id="home-key-h" style="margin-top:0">Chiffres clés de la saison</div>
    <div class="grid grid--4">
      <?php foreach ($headline as $kpi_card): extract($kpi_card, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <figure class="home-quote">
    <blockquote>Quatre trophées, une méthode : la saison qui restera, chiffre après chiffre.</blockquote>
    <figcaption>Récit de saison · PSG Analytics</figcaption>
  </figure>

  <div class="grid grid--2 home-tail">
    <section aria-labelledby="home-recent-h">
      <div class="sec-h" id="home-recent-h" style="margin-top:0">Cinq derniers matchs</div>
      <div class="stack">
        <?php foreach ($recent as $m): extract($m, EXTR_OVERWRITE); $confidence = 'verified'; ?>
          <?php require BASE_PATH . '/php/views/partials/match_row.php'; ?>
        <?php endforeach; ?>
        <div class="panel home-form">
          <p>Forme récente, de la plus ancienne à la plus récente rencontre.</p>
          <div class="form">
            <?php foreach ($form as $i => $r): ?>
              <span class="pill <?= View::e($formPills[$r] ?? 'pill--n') ?>" style="--i:<?= View::e($i) ?>" title="<?= View::e($formTitles[$r] ?? 'Nul') ?>"><?= View::e($formLetters[$r] ?? 'N') ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <section aria-labelledby="home-scorers-h">
      <div class="sec-h" id="home-scorers-h" style="margin-top:0">Meilleurs buteurs · Ligue 1</div>
      <div class="panel">
        <ol class="home-scorers">
          <?php foreach ($topScorers as $i => $s): ?>
            <?php $ratio = $topGoals > 0 ? round($s['goals'] / $topGoals, 3) : 0; ?>
            <li class="home-scorer">
              <span class="home-scorer__rank"><?= View::e($i + 1) ?></span>
              <span class="home-scorer__name">
                <?= View::e($s['name']) ?>
                <span class="home-scorer__first"><?= View::e($s['first']) ?></span>
              </span>
              <span class="meter" aria-hidden="true"><span class="meter__fill" style="--v:<?= View::e($ratio) ?>"></span></span>
              <span class="home-scorer__goals"><?= View::e($s['goals']) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <p class="home-scorers__foot"><span class="trace trace--ok"><span class="trace__dot" aria-hidden="true"></span>Totaux vérifiés</span></p>
      </div>
    </section>
  </div>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <section aria-labelledby="home-entries-h">
    <div class="sec-h" id="home-entries-h" style="margin-top:0">Entrer dans la saison</div>
    <div class="grid grid--4 home-entries">
      <?php foreach ($entries as $e): ?>
        <a class="entry-card" href="<?= View::e($e['href']) ?>">
          <span class="entry-card__kicker"><?= View::e($e['kicker']) ?></span>
          <span class="entry-card__title"><?= View::e($e['title']) ?></span>
          <span class="entry-card__desc"><?= View::e($e['desc']) ?></span>
          <span class="entry-card__go" aria-hidden="true">Ouvrir <span class="entry-card__arrow">&rsaquo;</span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</div>
