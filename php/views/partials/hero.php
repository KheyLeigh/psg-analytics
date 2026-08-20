<?php
declare(strict_types=1);

/**
 * Hero champion "Matchday" réutilisable (Dashboard et Styleguide) : banderole
 * Hechter comme ossature, eyebrow, titre (unique <h1> de la page) et vitrine de
 * trophées (un rectangle or par compétition gagnée). Rendu 3D en cascade par carte :
 * un modèle GLB via model-viewer (PBR + HDRI) si le fichier existe, sinon la coupe
 * stylisée WebGL maison (assets/js/trophy3d.js), sinon le SVG rendu ici. L'asset GLB
 * pilote tout : le déposer dans /assets/trophies/ suffit a activer le rendu réaliste.
 *
 * @var string $heroEyebrow Contexte affiché au-dessus du titre
 * @var string $heroTitle   Titre principal, rendu comme unique <h1> de la page
 * @var string $heroYear    Année de saison portée par la banderole (ex: 25·26)
 * @var array<int,array{name:string,stat:string}> $trophies
 */
$heroYear = $heroYear ?? '25·26';

// Type de coupe 3D par compétition (mappe le nom vers le modèle WebGL de trophy3d.js).
// Robuste a l'ordre des cartes ; repli 'ucl' si un nom inattendu apparait.
$trophyKey = [
    'Ligue des Champions'   => 'ucl',
    'Ligue 1'               => 'l1',
    'Coupe de France'       => 'cdf',
    'Trophée des Champions' => 'tdc',
];

// Rendu 3D par asset : si un GLB existe pour une compétition, la carte le rend en PBR
// via model-viewer (réflexions HDRI, rotation lente, pause au survol) ; sinon elle
// retombe sur la coupe stylisée maison (WebGL puis SVG). Déposer un fichier
// /assets/trophies/{cle}.glb suffit a faire basculer une carte, sans autre changement.
$trophyCfg = require BASE_PATH . '/php/config/trophies.php';
$trophyDir = BASE_PATH . '/assets/trophies/';
$hasGlb = [];
foreach ($trophyKey as $key) {
    $hasGlb[$key] = is_file($trophyDir . $key . '.glb');
}
$anyGlb = in_array(true, $hasGlb, true);

// Dégradé or défini une seule fois, réutilisé par chaque trophée de la vitrine.
$trophyDefs = '<svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">'
    . '<defs><linearGradient id="trophyGold" x1="0" y1="0" x2="1" y2="1">'
    . '<stop offset="0" stop-color="#fbe7a3"/><stop offset="0.5" stop-color="#f4c94b"/><stop offset="1" stop-color="#b8860b"/>'
    . '</linearGradient></defs></svg>';

// Silhouette de coupe dorée, placeholder qui pivote en attendant le vrai modèle 3D.
$trophySvg = '<svg viewBox="0 0 64 96" width="76" height="96" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
    . '<path d="M12 16 C2 16 2 34 20 38" fill="none" stroke="url(#trophyGold)" stroke-width="4"/>'
    . '<path d="M52 16 C62 16 62 34 44 38" fill="none" stroke="url(#trophyGold)" stroke-width="4"/>'
    . '<path d="M14 12 H50 V28 C50 44 42 52 32 52 C22 52 14 44 14 28 Z" fill="url(#trophyGold)"/>'
    . '<rect x="28" y="52" width="8" height="14" fill="url(#trophyGold)"/>'
    . '<rect x="18" y="66" width="28" height="7" rx="2" fill="url(#trophyGold)"/>'
    . '<rect x="13" y="73" width="38" height="8" rx="2" fill="url(#trophyGold)"/>'
    . '</svg>';
?>
<section class="hero">
  <div class="hero__band" aria-hidden="true">
    <span class="hero__band-star">&#9733;</span>
    <span class="hero__band-year"><?= View::e($heroYear) ?></span>
  </div>
  <div class="hero__body">
    <div class="hero__eyebrow"><?= View::e($heroEyebrow) ?></div>
    <h1 class="hero__title"><?= View::e($heroTitle) ?></h1>
    <?= $trophyDefs ?>
    <div class="hero__cabinet">
      <?php foreach ($trophies as $t): $key = $trophyKey[$t['name']] ?? 'ucl'; ?>
        <article class="trophy">
          <?php if ($hasGlb[$key]): $cfg = $trophyCfg[$key] ?? []; ?>
            <div class="trophy__stage is-mv" data-trophy="<?= View::e($key) ?>" data-mv>
              <model-viewer class="trophy__mv"
                src="/assets/trophies/<?= View::e($key) ?>.glb"
                environment-image="/assets/hdri/studio_small_09_1k.hdr"
                exposure="<?= View::e($cfg['exposure'] ?? '1.0') ?>"
                camera-orbit="<?= View::e($cfg['cameraOrbit'] ?? '0deg 80deg 100%') ?>"
                shadow-intensity="0.5" shadow-softness="1"
                auto-rotate auto-rotate-delay="0" rotation-per-second="16deg"
                interaction-prompt="none" camera-controls disable-zoom disable-tap
                touch-action="pan-y" loading="lazy" reveal="auto"
                aria-hidden="true" alt=""></model-viewer>
              <span class="trophy__spin trophy__spin--fallback" hidden><?= $trophySvg ?></span>
            </div>
          <?php else: ?>
            <div class="trophy__stage" data-trophy="<?= View::e($key) ?>"><span class="trophy__spin"><?= $trophySvg ?></span></div>
          <?php endif; ?>
          <div class="trophy__name"><?= View::e($t['name']) ?></div>
          <div class="trophy__stat"><?= View::e($t['stat']) ?></div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($anyGlb): ?>
      <script type="module" src="/assets/js/vendor/model-viewer.min.js"></script>
    <?php endif; ?>
    <p class="hero__note">Coupes stylisées en 3D : une représentation générique, pas les trophées officiels.</p>
  </div>
</section>
