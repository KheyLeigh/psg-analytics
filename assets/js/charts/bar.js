// Diagramme en barres verticales. Série identité en rouge par défaut.
// Signature : render(el, [{label, value}], {color, ariaLabel}).

import { linearScale, bandScale, niceMax } from './scale.js';
import { svgEl, renderYAxis, renderXLabels } from './axis.js';

const W = 640;
const H = 360;
const M = { top: 20, right: 16, bottom: 40, left: 40 };

export function render(container, data, options = {}) {
  const { color = 'var(--red)', ariaLabel = 'Diagramme en barres' } = options;
  container.textContent = '';
  container.classList.add('chart');

  const values = data.map((d) => d.value);
  const max = niceMax(Math.max(...values, 0));

  const plotW = W - M.left - M.right;
  const plotH = H - M.top - M.bottom;
  const x0 = M.left;
  const x1 = W - M.right;
  const yBase = M.top + plotH;

  // Y projette une valeur vers une ordonnée, l'origine étant en bas.
  const y = linearScale(0, max, yBase, M.top);
  const band = bandScale(data, x0, x1, 0.32);

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet',
  });

  svg.appendChild(renderYAxis(y, max, { x0, x1 }));

  // Ligne de base de l'axe X.
  svg.appendChild(svgEl('line', { class: 'axis-baseline', x1: x0, y1: yBase, x2: x1, y2: yBase }));

  data.forEach((d, i) => {
    const bx = band.pos(i);
    const by = y(d.value);
    const bh = yBase - by;
    const g = svgEl('g');
    const rect = svgEl('rect', {
      class: 'bar', x: bx, y: by, width: band.bandwidth, height: Math.max(bh, 0),
      rx: 3, fill: color,
    });
    // Infobulle native : libellé et valeur exacte au survol.
    rect.appendChild(svgEl('title', {}, `${d.label} : ${d.value}`));
    g.appendChild(rect);
    // Valeur affichée au-dessus de la barre, chiffres italiques.
    g.appendChild(svgEl('text', {
      class: 'bar-value', x: bx + band.bandwidth / 2, y: by - 6, 'text-anchor': 'middle',
    }, String(d.value)));
    svg.appendChild(g);
  });

  svg.appendChild(renderXLabels(data.map((d) => d.label), (i) => band.pos(i) + band.bandwidth / 2, H - 14));

  container.appendChild(svg);
  return svg;
}
