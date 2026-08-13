<?php
declare(strict_types=1);

// Labo vivant du design system "Matchday" : sert à vérifier visuellement tous les
// composants en thème sombre et clair, et à démontrer le système de traçabilité
// (vérifié / estimé, mode transparence, provenance au survol). Données d'exemple
// cohérentes avec la saison 2025-26, uniquement à titre d'illustration des composants.

// Trophées de la saison : un rectangle or par compétition gagnée. Le trophée est un
// placeholder SVG doré qui pivote, en attendant le vrai modèle 3D (étape de finition).
$trophies = [
    ['name' => 'Ligue des Champions', 'stat' => '17 matchs européens'],
    ['name' => 'Ligue 1', 'stat' => '76 points · 24-4-6'],
    ['name' => 'Coupe de France', 'stat' => 'doublé national'],
    ['name' => 'Trophée des Champions', 'stat' => 'supercoupe de France'],
];

// Dégradé or défini une seule fois, réutilisé par chaque trophée.
$trophyDefs = '<svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">'
    . '<defs><linearGradient id="trophyGold" x1="0" y1="0" x2="1" y2="1">'
    . '<stop offset="0" stop-color="#fbe7a3"/><stop offset="0.5" stop-color="#f4c94b"/><stop offset="1" stop-color="#b8860b"/>'
    . '</linearGradient></defs></svg>';

$trophySvg = '<svg viewBox="0 0 64 96" width="76" height="96" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
    . '<path d="M12 16 C2 16 2 34 20 38" fill="none" stroke="url(#trophyGold)" stroke-width="4"/>'
    . '<path d="M52 16 C62 16 62 34 44 38" fill="none" stroke="url(#trophyGold)" stroke-width="4"/>'
    . '<path d="M14 12 H50 V28 C50 44 42 52 32 52 C22 52 14 44 14 28 Z" fill="url(#trophyGold)"/>'
    . '<rect x="28" y="52" width="8" height="14" fill="url(#trophyGold)"/>'
    . '<rect x="18" y="66" width="28" height="7" rx="2" fill="url(#trophyGold)"/>'
    . '<rect x="13" y="73" width="38" height="8" rx="2" fill="url(#trophyGold)"/>'
    . '</svg>';

$kpis = [
    ['label' => 'Buts marqués', 'value' => 74, 'confidence' => 'verified', 'tip' => "Somme des buts du PSG sur les 34 matchs de Ligue 1, relevés un à un."],
    ['label' => 'Différence de buts', 'value' => '+45', 'confidence' => 'verified', 'tip' => "Buts marqués moins buts encaissés, sur données de match vérifiées."],
    ['label' => 'Possession moy.', 'value' => '68,8%', 'confidence' => 'verified', 'tip' => "Moyenne de possession sur les 34 matchs de Ligue 1 (source FBref)."],
    ['label' => 'Indice de forme', 'value' => '7,4', 'confidence' => 'estimated', 'tip' => "Exemple de valeur reconstituée : marquée estimée, elle ressort en mode Transparence."],
];

// Meilleurs buteurs de Ligue 1 (vérifiés FBref). La jauge est relative au meilleur total.
$topGoals = 11;
$scorers = [
    ['number' => 29, 'name' => 'Barcola', 'role' => 'Ailier gauche', 'position' => 'FW', 'goals' => 11],
    ['number' => 10, 'name' => 'Dembélé', 'role' => 'Attaquant', 'position' => 'FW', 'goals' => 10],
    ['number' => 7,  'name' => 'Kvaratskhelia', 'role' => 'Ailier', 'position' => 'FW', 'goals' => 8],
    ['number' => 14, 'name' => 'Doué', 'role' => 'Milieu offensif', 'position' => 'MF', 'goals' => 7],
    ['number' => 9,  'name' => 'Ramos', 'role' => 'Avant-centre', 'position' => 'FW', 'goals' => 6],
];

$positionTags = ['GK' => 'tag--gk', 'DF' => 'tag--def', 'MF' => 'tag--mid', 'FW' => 'tag--fwd'];

$players = [
    ['number' => 30, 'name' => 'Lucas Chevalier', 'position' => 'GK', 'nationality' => 'France', 'confidence' => 'verified'],
    ['number' => 5, 'name' => 'Marquinhos', 'position' => 'DF', 'nationality' => 'Brésil', 'confidence' => 'verified'],
    ['number' => 17, 'name' => 'Vitinha', 'position' => 'MF', 'nationality' => 'Portugal', 'confidence' => 'verified'],
    ['number' => 29, 'name' => 'Bradley Barcola', 'position' => 'FW', 'nationality' => 'France', 'confidence' => 'estimated'],
];

$matches = [
    ['competition' => 'Ligue 1', 'opponent' => 'Marseille', 'home' => true, 'goalsFor' => 3, 'goalsAgainst' => 1, 'result' => 'W', 'confidence' => 'verified'],
    ['competition' => 'Ligue 1', 'opponent' => 'Lyon', 'home' => false, 'goalsFor' => 2, 'goalsAgainst' => 2, 'result' => 'D', 'confidence' => 'verified'],
    ['competition' => 'Ligue des champions', 'opponent' => 'Bayern Munich', 'home' => true, 'goalsFor' => 1, 'goalsAgainst' => 2, 'result' => 'L', 'confidence' => 'estimated'],
];

// Cinq derniers résultats, du plus ancien au plus récent (V victoire, N nul, D défaite).
$form = ['W', 'W', 'W', 'D', 'W'];
$formLetters = ['W' => 'V', 'D' => 'N', 'L' => 'D'];
$formPills = ['W' => 'pill--w', 'D' => 'pill--n', 'L' => 'pill--l'];
?>
<div class="stack section">
  <div>
    <div class="sec-h" style="margin-top:0">Design system Matchday</div>
    <p>Référence vivante des composants : hero champion, cartes KPI, badges de traçabilité, boutons, tags de poste, tableau de buteurs, guide de forme, fiches joueur et lignes de match. Le bouton Transparence du header fait ressortir les données estimées ; survolez les cartes marquées pour voir leur provenance. Utilisez le bouton lune pour basculer sombre et clair.</p>
  </div>

  <section class="hero">
    <div class="hero__band" aria-hidden="true">
      <span class="hero__band-star">&#9733;</span>
      <span class="hero__band-year">25&middot;26</span>
    </div>
    <div class="hero__body">
      <div class="hero__eyebrow">Paris Saint-Germain &middot; Saison à quatre trophées</div>
      <h1 class="hero__title">Les titres, chiffres à l'appui</h1>
      <?= $trophyDefs ?>
      <div class="hero__cabinet">
        <?php foreach ($trophies as $t): ?>
          <article class="trophy">
            <div class="trophy__stage"><span class="trophy__spin"><?= $trophySvg ?></span></div>
            <div class="trophy__name"><?= View::e($t['name']) ?></div>
            <div class="trophy__stat"><?= View::e($t['stat']) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="hero__note">Trophées en placeholder qui pivote, à remplacer par un vrai modèle 3D par carte en étape de finition.</p>
    </div>
  </section>

  <div>
    <div class="sec-h">Indicateurs clés</div>
    <div class="grid grid--4">
      <?php foreach ($kpis as $kpi): extract($kpi, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="panel stack">
    <h2 class="panel__title">Badges de traçabilité</h2>
    <p>La forme porte le sens autant que la couleur : point plein vert pour le vérifié, anneau creux ambre pour l'estimé.</p>
    <div class="row">
      <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
      <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
    </div>
  </section>

  <div class="grid grid--2">
    <section class="panel stack">
      <h2 class="panel__title">Boutons</h2>
      <div class="row">
        <button type="button" class="btn">Défaut</button>
        <button type="button" class="btn btn--primary">Action</button>
        <button type="button" class="btn btn--gold">Sacre</button>
        <button type="button" class="btn btn--ghost">Discret</button>
      </div>
    </section>

    <section class="panel stack">
      <h2 class="panel__title">Tags de poste</h2>
      <div class="row">
        <span class="tag tag--gk">GK</span>
        <span class="tag tag--def">DF</span>
        <span class="tag tag--mid">MF</span>
        <span class="tag tag--fwd">FW</span>
      </div>
      <h2 class="panel__title">Guide de forme</h2>
      <div class="form">
        <?php foreach ($form as $r): ?>
          <span class="pill <?= View::e($formPills[$r] ?? 'pill--n') ?>"><?= View::e($formLetters[$r] ?? 'N') ?></span>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <section class="panel">
    <div class="panel__header">
      <h2 class="panel__title">Classement des buteurs</h2>
      <span class="panel__more">Ligue 1</span>
    </div>
    <table class="table">
      <thead>
        <tr>
          <th aria-sort="none">Joueur</th>
          <th>Poste</th>
          <th aria-sort="descending">Buts</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($scorers as $s): ?>
          <tr>
            <td>
              <div class="player-cell">
                <span class="jersey jersey--sm"><?= View::e($s['number']) ?></span>
                <div>
                  <?= View::e($s['name']) ?>
                  <div class="player-cell__role"><?= View::e($s['role']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="tag <?= View::e($positionTags[$s['position']] ?? 'tag--mid') ?>"><?= View::e($s['position']) ?></span></td>
            <td>
              <div class="meter-row">
                <span class="meter"><span class="meter__fill" style="--v:<?= View::e(round($s['goals'] / $topGoals, 3)) ?>"></span></span>
                <span class="meter-row__num"><?= View::e($s['goals']) ?></span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <div>
    <div class="sec-h">Fiches joueur</div>
    <div class="grid grid--2">
      <?php foreach ($players as $p): extract($p, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/player_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="sec-h">Derniers matchs</div>
    <div class="grid grid--3">
      <?php foreach ($matches as $m): extract($m, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/match_row.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
