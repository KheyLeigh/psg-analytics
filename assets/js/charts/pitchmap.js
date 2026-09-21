// Heatmap de présence sur le terrain (pitch map) : terrain complet vu du dessus,
// attaque vers la droite, champ de densité continu façon thermographie sportive
// (bleu froid -> vert -> jaune -> orange -> rouge chaud), couvrant tout le
// terrain plutôt que des îlots isolés. Un canvas calcule la densité (noyau
// gaussien autour de chaque point vérifié, poids en racine carrée du nombre de
// touches pour rester lisible malgré la loi de puissance des données réelles)
// puis la colorise ; les lignes du terrain sont dessinées par-dessus en SVG,
// nettes quel que soit le zoom. Jamais de valeur individuelle non vérifiée
// dessinée, jamais de champ hors des limites exactes du terrain (canvas =
// dimensions du terrain, rien ne peut déborder). Signature :
// render(el, { points }, { ariaLabel }).

import { svgEl } from './axis.js';

// Terrain a l'echelle metrique (105 x 68 m), vue complete (pas de demi-terrain
// contrairement a la shot map : la presence couvre tout le jeu).
const W = 700;              // largeur = 105 m (longueur du terrain, affichee horizontalement)
const H = 453;              // hauteur = 68 m (largeur du terrain)

// Grille de calcul de densité : résolution réduite (perf, ~1/5) puis agrandie
// avec lissage bilinéaire du canvas, qui produit déjà un dégradé continu sans
// recalculer chaque pixel final.
const GRID_W = 140;
const GRID_H = 91;

// Étalement du noyau gaussien autour de chaque point (en unités du terrain,
// W=700) : assez large pour fusionner les points voisins en zones continues,
// assez serré pour distinguer plusieurs foyers de présence.
const SIGMA = 52;

// Palette thermographique classique (froid -> chaud), interpolée par paliers.
const JET_STOPS = [
  [0.00, [18, 24, 110]],
  [0.20, [22, 120, 200]],
  [0.42, [40, 175, 120]],
  [0.60, [225, 215, 60]],
  [0.80, [240, 140, 30]],
  [1.00, [205, 20, 20]],
];

function lerp(a, b, t) {
  return a + (b - a) * t;
}

function jetColor(t) {
  const v = Math.max(0, Math.min(1, t));
  let i = 0;
  while (i < JET_STOPS.length - 2 && v > JET_STOPS[i + 1][0]) i++;
  const [t0, c0] = JET_STOPS[i];
  const [t1, c1] = JET_STOPS[i + 1];
  const localT = t1 > t0 ? (v - t0) / (t1 - t0) : 0;
  return [
    Math.round(lerp(c0[0], c1[0], localT)),
    Math.round(lerp(c0[1], c1[1], localT)),
    Math.round(lerp(c0[2], c1[2], localT)),
  ];
}

// Projette une coordonnee normalisee Sofascore (0-100 sur x et y) vers le repere SVG.
function project(x, y) {
  return [(x / 100) * W, (y / 100) * H];
}

// Calcule la grille de densité (noyau gaussien, poids en racine carrée du
// nombre de touches) puis la peint sur un petit canvas hors-écran, agrandi
// ensuite avec lissage sur le canvas visible : couverture continue de tout le
// terrain, jamais de trou entre les zones visitées.
function paintHeat(canvas, points) {
  const weighted = points.map(([x, y, c]) => {
    const [px, py] = project(x, y);
    return [px, py, Math.sqrt(Math.max(0, c))];
  });

  const density = new Float64Array(GRID_W * GRID_H);
  let max = 0;
  const twoSigmaSq = 2 * SIGMA * SIGMA;
  for (let gy = 0; gy < GRID_H; gy++) {
    const py = ((gy + 0.5) / GRID_H) * H;
    for (let gx = 0; gx < GRID_W; gx++) {
      const px = ((gx + 0.5) / GRID_W) * W;
      let sum = 0;
      for (const [wx, wy, weight] of weighted) {
        const dx = px - wx;
        const dy = py - wy;
        sum += weight * Math.exp(-(dx * dx + dy * dy) / twoSigmaSq);
      }
      const idx = gy * GRID_W + gx;
      density[idx] = sum;
      if (sum > max) max = sum;
    }
  }
  if (max <= 0) return;

  const off = document.createElement('canvas');
  off.width = GRID_W;
  off.height = GRID_H;
  const offCtx = off.getContext('2d');
  const img = offCtx.createImageData(GRID_W, GRID_H);
  for (let i = 0; i < density.length; i++) {
    const t = density[i] / max;
    const [r, g, b] = jetColor(t);
    img.data[i * 4] = r;
    img.data[i * 4 + 1] = g;
    img.data[i * 4 + 2] = b;
    img.data[i * 4 + 3] = 255;
  }
  offCtx.putImageData(img, 0, 0);

  const ctx = canvas.getContext('2d');
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
  ctx.clearRect(0, 0, W, H);
  ctx.drawImage(off, 0, 0, GRID_W, GRID_H, 0, 0, W, H);
}

// Trace les lignes du terrain complet, en blanc : contour, ligne mediane, rond
// central, surfaces de reparation et de but, points, arcs de penalty et petits
// arcs de corner, aux deux bouts.
function drawPitchLines(svg) {
  const line = (attrs) => svg.appendChild(svgEl('rect', { class: 'pm-line', fill: 'none', ...attrs }));

  line({ x: 1, y: 1, width: W - 2, height: H - 2, rx: 2 });
  svg.appendChild(svgEl('line', { class: 'pm-line', x1: W / 2, y1: 0, x2: W / 2, y2: H }));
  svg.appendChild(svgEl('circle', {
    class: 'pm-line', fill: 'none', cx: W / 2, cy: H / 2, r: (9.15 / 68) * H,
  }));
  svg.appendChild(svgEl('circle', { class: 'pm-line', cx: W / 2, cy: H / 2, r: 2.5, fill: 'currentColor' }));

  const cornerR = (1 / 68) * H * 4;
  const corners = [[0, 0, 0, 90], [W, 0, 90, 180], [0, H, 270, 360], [W, H, 180, 270]];
  for (const [cx, cy, a0] of corners) {
    const rad0 = (a0 * Math.PI) / 180;
    const rad1 = ((a0 + 90) * Math.PI) / 180;
    const x0 = cx + cornerR * Math.cos(rad0);
    const y0 = cy + cornerR * Math.sin(rad0);
    const x1 = cx + cornerR * Math.cos(rad1);
    const y1 = cy + cornerR * Math.sin(rad1);
    svg.appendChild(svgEl('path', {
      class: 'pm-line', fill: 'none', d: `M ${x0} ${y0} A ${cornerR} ${cornerR} 0 0 1 ${x1} ${y1}`,
    }));
  }

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
    svg.appendChild(svgEl('circle', { class: 'pm-line', cx: px, cy: py, r: 2.5, fill: 'currentColor' }));
  }
}

export function render(container, data, options = {}) {
  const { ariaLabel = 'Carte de présence sur le terrain' } = options;
  const points = (data && data.points) || [];
  container.textContent = '';
  container.classList.add('chart');

  const stage = document.createElement('div');
  stage.className = 'pitchmap-stage';
  stage.style.aspectRatio = `${W} / ${H}`;

  const canvas = document.createElement('canvas');
  canvas.className = 'pitchmap-canvas';
  canvas.width = W;
  canvas.height = H;
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', ariaLabel);
  stage.appendChild(canvas);

  if (points.length > 0) {
    paintHeat(canvas, points);
  }

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, preserveAspectRatio: 'xMidYMid meet', class: 'pitchmap-lines',
    'aria-hidden': 'true',
  });
  drawPitchLines(svg);
  stage.appendChild(svg);

  container.appendChild(stage);
  return stage;
}
