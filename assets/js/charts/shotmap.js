// Carte des tirs (shot map) : demi-terrain vu du dessus, but en haut, chaque tir posé
// a ses coordonnees reelles (x/y Understat), taille du marqueur proportionnelle au xG,
// buts mis en evidence. Signature : render(el, { shots }, { ariaLabel }).
//
// Coordonnees Understat : x = 0 (ligne de but propre) a 1 (ligne de but adverse),
// y = 0 a 1 en largeur. On affiche le tiers/demi offensif (x de 0.5 a 1.0).

import { svgEl } from './axis.js';

// Terrain a l'echelle metrique (105 x 68 m), on n'en montre que la moitie offensive.
const W = 680;             // largeur = 68 m
const H = 525;             // hauteur = 52.5 m (demi-longueur)
const X0 = 0.5;            // borne basse de x affichee

// Projette une coordonnee Understat (x,y) vers le repere SVG (but en haut).
function project(x, y) {
  const cy = ((1 - x) / (1 - X0)) * H;   // x=1 -> haut (0), x=0.5 -> bas (H)
  const cx = y * W;
  return [cx, cy];
}

// Trace les lignes du terrain (contour, surface, 6 metres, point et arc de penalty).
function drawPitch(svg) {
  // Fond de la pelouse (teinte discrete du design system, pas de vert criard).
  svg.appendChild(svgEl('rect', { class: 'sm-pitch', x: 0, y: -8, width: W, height: H + 8 }));
  const line = (attrs) => svg.appendChild(svgEl('rect', { class: 'sm-line', fill: 'none', ...attrs }));
  // Contour de la demi-surface de jeu.
  line({ x: 1, y: 1, width: W - 2, height: H - 2, rx: 2 });
  // Surface de reparation : 40.3 m de large, 16.5 m de profondeur depuis la ligne de but.
  const [pbx] = project(1, (1 - 40.3 / 68) / 2);
  const [pbx2] = project(1, 1 - (1 - 40.3 / 68) / 2);
  const [, pby] = project(1 - 16.5 / 105, 0);
  line({ x: pbx, y: 0, width: pbx2 - pbx, height: pby });
  // Surface de but : 18.3 m de large, 5.5 m de profondeur.
  const [gbx] = project(1, (1 - 18.32 / 68) / 2);
  const [gbx2] = project(1, 1 - (1 - 18.32 / 68) / 2);
  const [, gby] = project(1 - 5.5 / 105, 0);
  line({ x: gbx, y: 0, width: gbx2 - gbx, height: gby });
  // But (cage) en haut au centre : 7.32 m de large.
  const [gx] = project(1, (1 - 7.32 / 68) / 2);
  const [gx2] = project(1, 1 - (1 - 7.32 / 68) / 2);
  svg.appendChild(svgEl('rect', { class: 'sm-goal', x: gx, y: -6, width: gx2 - gx, height: 6 }));
  // Point de penalty (11 m) et arc.
  const [px, py] = project(1 - 11 / 105, 0.5);
  svg.appendChild(svgEl('circle', { class: 'sm-line', cx: px, cy: py, r: 2, fill: 'currentColor' }));
  const arcR = (9.15 / 68) * W;
  const path = svgEl('path', {
    class: 'sm-line', fill: 'none',
    d: `M ${px - arcR * 0.72} ${py + arcR * 0.69} A ${arcR} ${arcR} 0 0 0 ${px + arcR * 0.72} ${py + arcR * 0.69}`,
  });
  svg.appendChild(path);
}

const RES_LABEL = { goal: 'But', saved: 'Arrêt', miss: 'Manqué', block: 'Contré', post: 'Poteau' };

export function render(container, data, options = {}) {
  const { ariaLabel = 'Carte des tirs' } = options;
  const shots = (data && data.shots) || [];
  container.textContent = '';
  container.classList.add('chart');

  const svg = svgEl('svg', {
    viewBox: `0 -8 ${W} ${H + 10}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet', class: 'shotmap',
  });
  drawPitch(svg);

  // Tirs sans but d'abord, buts par-dessus pour rester lisibles.
  const order = [...shots].sort((a, b) => (a.res === 'goal' ? 1 : 0) - (b.res === 'goal' ? 1 : 0));
  for (const s of order) {
    const [cx, cy] = project(s.x, s.y);
    const r = 4 + Math.sqrt(Math.max(0, s.xg)) * 26;   // aire ~ proportionnelle au xG
    const isGoal = s.res === 'goal';
    const dot = svgEl('circle', {
      class: 'sm-shot' + (isGoal ? ' sm-shot--goal' : ''),
      cx, cy, r: Math.max(3, r),
    });
    const opp = s.opp ? ` ${s.ha === 'h' ? 'contre' : 'à'} ${s.opp}` : '';
    dot.appendChild(svgEl('title', {}, `${RES_LABEL[s.res] || s.res} · ${s.min}'${opp} · xG ${s.xg.toFixed(2)}`));
    svg.appendChild(dot);
  }

  container.appendChild(svg);
  return svg;
}
