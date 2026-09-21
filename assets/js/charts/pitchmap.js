// Heatmap de présence sur le terrain (pitch map) : terrain complet vu du dessus,
// attaque vers la droite, densité de touches de balle en camaïeu rouge identité
// (color-mix vers le rouge, même logique que la heatmap buteurs x mois). Chaque
// cellule (grille Sofascore, pas de 12 sur 0-100) devient un disque flou dont la
// teinte suit l'intensité (racine carrée du nombre de touches relatif au maximum
// du joueur, pour rester lisible malgré la loi de puissance des données réelles) :
// le flou (filtre SVG) fait fusionner les disques voisins en zones chaudes, sans
// jamais dessiner de valeur individuelle non vérifiée. Signature :
// render(el, { points }, { ariaLabel }).

import { svgEl } from './axis.js';

// Terrain a l'echelle metrique (105 x 68 m), vue complete (pas de demi-terrain
// contrairement a la shot map : la presence couvre tout le jeu).
const W = 700;              // largeur = 105 m (longueur du terrain, affichee horizontalement)
const H = 453;              // hauteur = 68 m (largeur du terrain)

// Projette une coordonnee normalisee Sofascore (0-100 sur x et y) vers le repere SVG.
function project(x, y) {
  return [(x / 100) * W, (y / 100) * H];
}

// Trace les lignes du terrain complet : contour, ligne mediane, rond central,
// surfaces de reparation et de but, points et arcs de penalty, aux deux bouts.
function drawPitch(svg) {
  svg.appendChild(svgEl('rect', { class: 'pm-pitch', x: 0, y: 0, width: W, height: H }));
  const line = (attrs) => svg.appendChild(svgEl('rect', { class: 'pm-line', fill: 'none', ...attrs }));

  line({ x: 1, y: 1, width: W - 2, height: H - 2, rx: 2 });
  svg.appendChild(svgEl('line', { class: 'pm-line', x1: W / 2, y1: 0, x2: W / 2, y2: H }));
  svg.appendChild(svgEl('circle', {
    class: 'pm-line', fill: 'none', cx: W / 2, cy: H / 2, r: (9.15 / 68) * H,
  }));
  svg.appendChild(svgEl('circle', { class: 'pm-line', cx: W / 2, cy: H / 2, r: 2, fill: 'currentColor' }));

  // Cote gauche (x=0, but propre par convention Sofascore) puis cote droit (x=100).
  for (const side of [0, 1]) {
    const dir = side === 0 ? 1 : -1;
    const baseX = side === 0 ? 0 : W;

    const [, pbY1] = project(0, (1 - 40.3 / 68) * 50);
    const [, pbY2] = project(0, 100 - (1 - 40.3 / 68) * 50);
    const pbW = (16.5 / 105) * W;
    line({ x: side === 0 ? 0 : W - pbW, y: pbY1, width: pbW, height: pbY2 - pbY1 });

    const [, gbY1] = project(0, (1 - 18.32 / 68) * 50);
    const [, gbY2] = project(0, 100 - (1 - 18.32 / 68) * 50);
    const gbW = (5.5 / 105) * W;
    line({ x: side === 0 ? 0 : W - gbW, y: gbY1, width: gbW, height: gbY2 - gbY1 });

    const [, gY1] = project(0, (1 - 7.32 / 68) * 50);
    const [, gY2] = project(0, 100 - (1 - 7.32 / 68) * 50);
    svg.appendChild(svgEl('rect', {
      class: 'pm-goal', x: baseX - (side === 0 ? 6 : 0), y: gY1, width: 6, height: gY2 - gY1,
    }));

    const px = baseX + dir * (11 / 105) * W;
    const [, py] = project(0, 50);
    svg.appendChild(svgEl('circle', { class: 'pm-line', cx: px, cy: py, r: 2, fill: 'currentColor' }));
  }

  // Fleche de sens d'attaque, au-dessus du terrain.
  svg.appendChild(svgEl('path', {
    class: 'pm-arrow', d: `M ${W / 2 - 22} -14 H ${W / 2 + 14} M ${W / 2 + 6} -20 L ${W / 2 + 14} -14 L ${W / 2 + 6} -8`,
    fill: 'none',
  }));
}

// Points de touches très inégaux (quelques cellules concentrent l'essentiel du
// volume, en loi de puissance) : une intensité linéaire (valeur / max) écrase
// tout sauf la cellule la plus chaude en à peine visible. La racine carrée
// remonte les zones moyennement visitées à une intensité lisible, sans jamais
// changer le classement relatif des cellules entre elles.
function intensity(count, max) {
  return Math.sqrt(Math.min(1, count / max));
}

export function render(container, data, options = {}) {
  const { ariaLabel = 'Carte de présence sur le terrain' } = options;
  const points = (data && data.points) || [];
  container.textContent = '';
  container.classList.add('chart');

  const svg = svgEl('svg', {
    viewBox: `0 -26 ${W} ${H + 26}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet', class: 'pitchmap',
  });

  const defs = svgEl('defs', {}, [
    svgEl('filter', { id: 'pm-blur', x: '-40%', y: '-40%', width: '180%', height: '180%' },
      svgEl('feGaussianBlur', { stdDeviation: 9 })),
  ]);
  svg.appendChild(defs);

  drawPitch(svg);

  if (points.length === 0) {
    container.appendChild(svg);
    return svg;
  }

  const max = Math.max(...points.map((p) => p[2]));
  const blobs = svgEl('g', { filter: 'url(#pm-blur)' });
  for (const [x, y, c] of points) {
    const [cx, cy] = project(x, y);
    const t = intensity(c, max);
    // `style` (pas l'attribut `fill`) : une règle CSS de classe (.pm-cell) gagne
    // toujours sur un attribut de présentation dans la cascade, ce qui écraserait
    // silencieusement le dégradé calculé ici par une même teinte pour toutes les
    // cellules.
    blobs.appendChild(svgEl('circle', {
      class: 'pm-cell', cx, cy, r: 22 + t * 26,
      style: `fill: color-mix(in srgb, var(--red) ${Math.round(20 + t * 80)}%, transparent)`,
    }));
  }
  svg.appendChild(blobs);

  container.appendChild(svg);
  return svg;
}
