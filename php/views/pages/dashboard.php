<?php
declare(strict_types=1);

// Tableau de bord "Matchday" : coquille SSR branchée sur les données réelles
// (KpiService, repositories). Les KPI et le contenu (matchs, forme, badges) sont
// rendus côté serveur ; les charts sont hydratés côté client par pages/dashboard.js.
// Traçabilité honnête : le bilan, la possession et les points remontent aux données
// vérifiées FBref ; la répartition mensuelle des buts et la carte de chaleur sont
// reconstituées par le générateur déterministe (estimé), ce que le mode Transparence
// fait ressortir.

// Formatage FR : virgule décimale, séparateur de milliers en espace fine.
$fr = static fn (float $v, int $dec): string => number_format($v, $dec, ',', ' ');

$goalsFor = (int) $record['goals_for'];
$goalsAgainst = (int) $record['goals_against'];
$diff = $goalsFor - $goalsAgainst;

$kpiCards = [
    [
        'label' => 'Points de Ligue 1', 'value' => $totalPoints, 'countup' => $totalPoints,
        'confidence' => 'verified',
        'tip' => 'Cumul V=3 / N=1 / D=0 sur les 34 journées de Ligue 1, résultats vérifiés FBref.',
    ],
    [
        'label' => 'Bilan (V · N · D)',
        'value' => $record['wins'] . ' · ' . $record['draws'] . ' · ' . $record['losses'],
        'countup' => null, 'confidence' => 'verified',
        'tip' => 'Victoires, nuls et défaites en Ligue 1, du point de vue du PSG (source FBref).',
    ],
    [
        'label' => 'Buts marqués', 'value' => $goalsFor, 'countup' => $goalsFor,
        'confidence' => 'verified',
        'tip' => 'Total des buts inscrits par le PSG sur la saison de Ligue 1 (source FBref).',
    ],
    [
        'label' => 'Différence de buts', 'value' => sprintf('%+d', $diff), 'countup' => null,
        'confidence' => 'verified',
        'tip' => 'Buts marqués (' . $goalsFor . ') moins buts encaissés (' . $goalsAgainst . '), données vérifiées.',
    ],
    [
        'label' => 'Possession moyenne', 'value' => $fr((float) $kpi['avg_possession'], 1) . ' %',
        'countup' => null, 'confidence' => 'verified',
        'tip' => 'Moyenne de possession sur les matchs de Ligue 1 renseignés (source FBref).',
    ],
    [
        'label' => 'Buts par match', 'value' => $fr((float) $kpi['goals_per_match'], 2), 'countup' => null,
        'confidence' => 'verified',
        'tip' => 'Buts marqués rapportés au nombre de matchs de Ligue 1 joués (données vérifiées).',
    ],
    [
        'label' => 'Clean sheets', 'value' => (int) $kpi['clean_sheets'], 'countup' => (int) $kpi['clean_sheets'],
        'confidence' => 'verified',
        'tip' => 'Matchs de Ligue 1 sans encaisser de but, comptés sur les scores vérifiés.',
    ],
    [
        'label' => 'Meilleur buteur · ' . ($kpi['top_scorer']['name'] ?? 'à venir'),
        'value' => (int) ($kpi['top_scorer']['goals'] ?? 0),
        'countup' => (int) ($kpi['top_scorer']['goals'] ?? 0),
        'confidence' => 'verified',
        'tip' => 'Meilleur total de buts de Ligue 1, calibré sur les totaux vérifiés FBref.',
    ],
];

// Données embarquées pour l'hydratation : la courbe (sans endpoint) et le classement
// des buteurs (totaux vérifiés). La répartition et la carte de chaleur passent par l'API.
$embedded = [
    'points' => $points,
    'milestone' => ['value' => $totalPoints, 'label' => $totalPoints . ' · Champion'],
    'topScorers' => $topScorers,
];
$formLetters = ['W' => 'V', 'D' => 'N', 'L' => 'D'];
$formPills = ['W' => 'pill--w', 'D' => 'pill--n', 'L' => 'pill--l'];
?>
<div class="stack section">
  <?php
  $heroEyebrow = 'Paris Saint-Germain · Saison 2025-26 · Dashboard';
  $heroTitle = 'La saison, chiffres à l\'appui';
  $heroYear = '25·26';
  $trophies = [
      ['name' => 'Ligue des Champions', 'stat' => 'sacre européen'],
      ['name' => 'Ligue 1', 'stat' => $totalPoints . ' pts · ' . $record['wins'] . '-' . $record['draws'] . '-' . $record['losses']],
      ['name' => 'Coupe de France', 'stat' => 'doublé national'],
      ['name' => 'Trophée des Champions', 'stat' => 'supercoupe de France'],
  ];
  require BASE_PATH . '/php/views/partials/hero.php';
  ?>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <section aria-labelledby="dash-kpi-h">
    <div class="sec-h" id="dash-kpi-h" style="margin-top:0">Indicateurs clés · Ligue 1</div>
    <div class="grid grid--4">
      <?php foreach ($kpiCards as $kpi_card): extract($kpi_card, EXTR_OVERWRITE); ?>
        <?php require BASE_PATH . '/php/views/partials/kpi_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <section aria-labelledby="dash-charts-h">
    <div class="sec-h" id="dash-charts-h">Graphiques de la saison</div>
    <div class="dash-charts">
      <figure class="panel chart-card chart-card--wide">
        <figcaption class="chart-card__head">
          <div>
            <h2 class="chart-card__title">Course au titre</h2>
            <p class="chart-card__sub">Points de Ligue 1 cumulés, journée par journée. Jalon <span class="gold">76 · champion</span> en or.</p>
          </div>
          <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
        </figcaption>
        <div class="chart-card__canvas" id="chart-points" data-chart="points">
          <p class="chart-fallback"><?= View::e($totalPoints) ?> points au terme des <?= View::e(count($points)) ?> journées de Ligue 1.</p>
        </div>
      </figure>

      <figure class="panel chart-card"
        data-est data-src="e"
        data-tip="Répartition reconstituée mois par mois par le générateur déterministe (StatGenerator), calibrée sur les totaux de buts vérifiés.">
        <figcaption class="chart-card__head">
          <div>
            <h2 class="chart-card__title">Répartition des buts par mois</h2>
            <p class="chart-card__sub">Buts du PSG ventilés par période de la saison.</p>
          </div>
          <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
        </figcaption>
        <div class="chart-card__canvas" id="chart-distribution" data-chart="distribution">
          <p class="chart-fallback">Graphique de répartition mensuelle des buts (hydraté avec JavaScript activé).</p>
        </div>
      </figure>

      <figure class="panel chart-card">
        <figcaption class="chart-card__head">
          <div>
            <h2 class="chart-card__title">Top buteurs</h2>
            <p class="chart-card__sub">Meilleurs artificiers du PSG en Ligue 1 (totaux vérifiés).</p>
          </div>
          <?php $confidence = 'verified'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
        </figcaption>
        <div class="chart-card__canvas" id="chart-scorers" data-chart="scorers">
          <p class="chart-fallback">
            <?php foreach ($topScorers as $i => $s): ?>
              <?= View::e($s['label']) ?> <?= View::e($s['value']) ?><?= $i < count($topScorers) - 1 ? ' · ' : '' ?>
            <?php endforeach; ?>
          </p>
        </div>
      </figure>

      <figure class="panel chart-card chart-card--wide"
        data-est data-src="e"
        data-tip="Buts par joueur et par mois reconstitués par le générateur déterministe (StatGenerator), calibrés sur les totaux vérifiés.">
        <figcaption class="chart-card__head">
          <div>
            <h2 class="chart-card__title">Buts par joueur et par mois</h2>
            <p class="chart-card__sub">Carte de chaleur des buteurs au fil de la saison.</p>
          </div>
          <?php $confidence = 'estimated'; require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
        </figcaption>
        <div class="chart-card__canvas" id="chart-heatmap" data-chart="heatmap">
          <p class="chart-fallback">Carte de chaleur des buts par joueur et par mois (hydratée avec JavaScript activé).</p>
        </div>
      </figure>
    </div>
  </section>

  <div class="dash-sep hechter" aria-hidden="true"></div>

  <div class="grid grid--2 dash-tail">
    <section aria-labelledby="dash-recent-h">
      <div class="sec-h" id="dash-recent-h" style="margin-top:0">Cinq derniers matchs</div>
      <div class="stack">
        <?php foreach ($recent as $m): extract($m, EXTR_OVERWRITE); $confidence = 'verified'; ?>
          <?php require BASE_PATH . '/php/views/partials/match_row.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>

    <section aria-labelledby="dash-form-h">
      <div class="sec-h" id="dash-form-h" style="margin-top:0">Guide de forme</div>
      <div class="panel stack">
        <p>Cinq dernières rencontres, de la plus ancienne à la plus récente.</p>
        <div class="form dash-form">
          <?php foreach ($form as $i => $r): ?>
            <span class="pill <?= View::e($formPills[$r] ?? 'pill--n') ?>" style="--i:<?= View::e($i) ?>" title="<?= View::e($r === 'W' ? 'Victoire' : ($r === 'D' ? 'Nul' : 'Défaite')) ?>"><?= View::e($formLetters[$r] ?? 'N') ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>
</div>

<script type="application/json" id="dashboard-data"><?= json_encode($embedded, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
