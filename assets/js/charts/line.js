// Courbe cumulée (points de championnat au fil des journées). Trait bleu de
// progression, aire diluée, et jalon champion en or sur le dernier point.
// Signature : render(el, [{x, y}], {ariaLabel, milestone}).
// milestone (optionnel) : {value, label} met en avant un jalon (ex: 76 · Champion).

import { linearScale, niceMax } from './scale.js';
import { svgEl, renderYAxis } from './axis.js';

const W = 640;
const H = 360;
const M = { top: 24, right: 20, bottom: 36, left: 40 };

export function render(container, data, options = {}) {
  const { ariaLabel = 'Courbe cumulée', milestone = null } = options;
  container.textContent = '';
  container.classList.add('chart');

  const xs = data.map((d) => d.x);
  const ys = data.map((d) => d.y);
  const xMin = Math.min(...xs);
  const xMax = Math.max(...xs);
  const yMax = niceMax(Math.max(...ys, 0));

  const plotH = H - M.top - M.bottom;
  const x0 = M.left;
  const x1 = W - M.right;
  const yBase = M.top + plotH;

  const x = linearScale(xMin, xMax, x0, x1);
  const y = linearScale(0, yMax, yBase, M.top);

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet',
  });

  svg.appendChild(renderYAxis(y, yMax, { x0, x1 }));
  svg.appendChild(svgEl('line', { class: 'axis-baseline', x1: x0, y1: yBase, x2: x1, y2: yBase }));

  const points = data.map((d) => `${x(d.x)},${y(d.y)}`).join(' ');

  // Aire sous la courbe, refermée sur la ligne de base.
  const areaPts = `${x0},${yBase} ${points} ${x1},${yBase}`;
  svg.appendChild(svgEl('polygon', { class: 'line-area', points: areaPts }));

  // Trait principal de la courbe.
  svg.appendChild(svgEl('polyline', { class: 'line-path', points }));

  // Points de données discrets avec infobulle native.
  data.forEach((d) => {
    const dot = svgEl('circle', { class: 'line-point', cx: x(d.x), cy: y(d.y), r: 2.5 });
    dot.appendChild(svgEl('title', {}, `J${d.x} : ${d.y} pts`));
    svg.appendChild(dot);
  });

  // Jalon champion : marqueur et libellé en or sur le point concerné.
  if (milestone) {
    const last = data[data.length - 1];
    const mx = x(last.x);
    const my = y(milestone.value ?? last.y);
    const dot = svgEl('circle', { class: 'milestone-dot', cx: mx, cy: my, r: 5.5 });
    dot.appendChild(svgEl('title', {}, milestone.label));
    svg.appendChild(dot);
    // Libellé calé à gauche du marqueur pour rester dans le cadre.
    svg.appendChild(svgEl('text', {
      class: 'milestone-label', x: mx - 10, y: my - 10, 'text-anchor': 'end',
    }, milestone.label));
  }

  container.appendChild(svg);
  return svg;
}
