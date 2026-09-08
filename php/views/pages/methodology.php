<?php
declare(strict_types=1);

// Page Méthodologie : la promesse de traçabilité, détaillée. Sources de données,
// taux de vérification par table, règle de calibration des estimations, limites, et
// exports. $sources et $coverage sont fournis par MethodologyController::buildViewData.
?>
<div class="stack section mth">
  <div>
    <div class="hero__eyebrow">Paris Saint-Germain · Saison <?= View::e($selectedSeason) ?> · Sources</div>
    <h1 class="mth-title">D'où viennent les chiffres</h1>
    <p class="mth-lede">Chaque chiffre de ce site remonte à sa source. Une donnée <span class="mth-ok">vérifiée</span> vient d'une feuille de match officielle ou d'un relevé public (FBref, captures d'application). Une donnée <span class="mth-est">estimée</span> est reconstituée par un générateur déterministe, calibré sur des totaux vérifiés, et signalée partout comme telle. Le bouton Transparence du header fait ressortir les estimations sur tout le site.</p>
  </div>

  <div class="hechter dash-sep" aria-hidden="true"></div>

  <section class="panel">
    <div class="panel__header"><h2 class="panel__title">Taux de vérification par table</h2></div>
    <div class="table-scroll">
    <table class="table mth-cov">
      <thead>
        <tr>
          <th scope="col">Donnée</th>
          <th scope="col">Lignes</th>
          <th scope="col">Vérifié</th>
          <th scope="col">Fiabilité</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coverage as $row): $confidence = $row['pct'] === 100 ? 'verified' : 'estimated'; ?>
          <tr>
            <td><?= View::e($row['label']) ?></td>
            <td class="num"><?= View::e($row['total']) ?></td>
            <td>
              <div class="meter-row">
                <span class="meter"><span class="meter__fill" style="--v:<?= View::e($row['total'] > 0 ? round($row['verified'] / $row['total'], 3) : 0) ?>"></span></span>
                <span class="meter-row__num"><?= View::e($row['pct']) ?>%</span>
              </div>
            </td>
            <td><?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="mth-note">L'attribution des buts, minutes et notes à chaque match (statistiques par match) est estimée : le générateur répartit les totaux de saison vérifiés, sans inventer de total. Les bilans de saison et les scores, eux, sont vérifiés.</p>
  </section>

  <section class="panel">
    <div class="panel__header"><h2 class="panel__title">Sources</h2></div>
    <ul class="mth-sources">
      <?php foreach ($sources as $s): $confidence = $s['confidence'] === 'estimated' ? 'estimated' : 'verified'; ?>
        <li class="mth-source">
          <div class="mth-source__head">
            <span class="mth-source__label"><?= View::e($s['label']) ?></span>
            <?php require BASE_PATH . '/php/views/partials/source_badge.php'; ?>
          </div>
          <?php if ($s['note'] !== ''): ?><p class="mth-source__note"><?= View::e($s['note']) ?></p><?php endif; ?>
          <div class="mth-source__meta">
            <?php if ($s['collectedAt'] !== ''): ?><span>relevé le <?= View::e($s['collectedAt']) ?></span><?php endif; ?>
            <?php if ($s['url'] !== null): ?><a href="<?= View::e($s['url']) ?>" rel="noopener noreferrer" target="_blank">source en ligne</a><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="panel">
    <div class="panel__header"><h2 class="panel__title">Modèles 3D des trophées</h2></div>
    <p class="mth-note">Les coupes du hero ne sont pas des assets officiels : les cinq sont reconstruites par IA (Meshy) à partir d'une photo ou d'une illustration du trophée réel, sans garantie de fidélité totale au design officiel. Aucun modèle 3D existant, libre de droits, n'a été trouvé pour ces cinq compétitions.</p>
    <ul class="mth-sources">
      <li class="mth-source">
        <div class="mth-source__head"><span class="mth-source__label">Ligue des Champions</span></div>
        <p class="mth-source__note">Reconstruit par IA (Meshy) à partir d'une image de référence.</p>
      </li>
      <li class="mth-source">
        <div class="mth-source__head"><span class="mth-source__label">Ligue 1</span></div>
        <p class="mth-source__note">Reconstruit par IA (Meshy) à partir d'une image de référence.</p>
      </li>
      <li class="mth-source">
        <div class="mth-source__head"><span class="mth-source__label">Coupe de France</span></div>
        <p class="mth-source__note">Reconstruit par IA (Meshy) à partir d'une image de référence.</p>
      </li>
      <li class="mth-source">
        <div class="mth-source__head"><span class="mth-source__label">Trophée des Champions</span></div>
        <p class="mth-source__note">Reconstruit par IA (Meshy) à partir d'une photo du trophée, licence CC-BY 2.0.</p>
        <div class="mth-source__meta"><a href="https://commons.wikimedia.org/wiki/File:Troph%C3%A9e_des_champions.jpeg" rel="noopener noreferrer" target="_blank">source en ligne</a></div>
      </li>
      <li class="mth-source">
        <div class="mth-source__head"><span class="mth-source__label">Coupe Intercontinentale</span></div>
        <p class="mth-source__note">Aucun modèle 3D libre trouvé (compétition créée en 2024). Reconstruit par IA (Meshy) à partir d'une illustration du domaine public.</p>
        <div class="mth-source__meta"><a href="https://commons.wikimedia.org/wiki/File:Challenger_Cup_FIFA.png" rel="noopener noreferrer" target="_blank">source en ligne</a></div>
      </li>
    </ul>
  </section>

  <section class="panel stack">
    <div class="panel__header"><h2 class="panel__title">Exports</h2></div>
    <p>Les données de l'effectif et une synthèse de la saison sont téléchargeables.</p>
    <div class="row">
      <a class="btn btn--primary" href="/api/export/players.csv" download>Joueurs (CSV)</a>
      <a class="btn" href="/api/export/report.pdf" download>Rapport de saison (PDF)</a>
    </div>
  </section>
</div>
