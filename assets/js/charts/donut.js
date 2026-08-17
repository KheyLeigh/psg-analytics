// Donut : répartition en secteurs. La couleur de chaque secteur est fournie
// par l'appelant (palette catégorielle --chart-1..5 en démo).
// Signature : render(el, [{label, value, color}], {ariaLabel, centerLabel, centerSub}).

import { svgEl } from './axis.js';

const W = 640;
const H = 360;
const CX = 200;
const CY = H / 2;
const R_OUT = 130;
const R_IN = 78;

// Point d'un cercle pour un angle donné, l'origine étant en haut (12 h).
function polar(cx, cy, r, angle) {
  const a = angle - Math.PI / 2;
  return [cx + r * Math.cos(a), cy + r * Math.sin(a)];
}

// Chemin d'un anneau entre deux angles (secteur de donut).
function arcPath(startA, endA) {
  const [x0o, y0o] = polar(CX, CY, R_OUT, startA);
  const [x1o, y1o] = polar(CX, CY, R_OUT, endA);
  const [x1i, y1i] = polar(CX, CY, R_IN, endA);
  const [x0i, y0i] = polar(CX, CY, R_IN, startA);
  const large = endA - startA > Math.PI ? 1 : 0;
  return [
    `M ${x0o} ${y0o}`,
    `A ${R_OUT} ${R_OUT} 0 ${large} 1 ${x1o} ${y1o}`,
    `L ${x1i} ${y1i}`,
    `A ${R_IN} ${R_IN} 0 ${large} 0 ${x0i} ${y0i}`,
    'Z',
  ].join(' ');
}

export function render(container, data, options = {}) {
  const { ariaLabel = 'Répartition en donut', centerLabel = null, centerSub = null } = options;
  container.textContent = '';
  container.classList.add('chart');

  const total = data.reduce((s, d) => s + d.value, 0) || 1;

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet',
  });

  let angle = 0;
  data.forEach((d) => {
    const sweep = (d.value / total) * Math.PI * 2;
    const path = svgEl('path', { class: 'donut-slice', d: arcPath(angle, angle + sweep), fill: d.color });
    const pct = Math.round((d.value / total) * 100);
    path.appendChild(svgEl('title', {}, `${d.label} : ${d.value} (${pct}%)`));
    svg.appendChild(path);
    angle += sweep;
  });

  // Coeur du donut : total ou libellé de synthèse.
  if (centerLabel) {
    svg.appendChild(svgEl('text', {
      class: 'donut-center', x: CX, y: CY - 2, 'text-anchor': 'middle',
      'dominant-baseline': 'middle', 'font-size': 34,
    }, centerLabel));
  }
  if (centerSub) {
    svg.appendChild(svgEl('text', {
      class: 'donut-center-sub', x: CX, y: CY + 24, 'text-anchor': 'middle', 'dominant-baseline': 'middle',
    }, centerSub));
  }

  // Légende à droite : pastille de couleur, libellé et valeur.
  const lx = 400;
  let ly = CY - (data.length - 1) * 14;
  data.forEach((d) => {
    svg.appendChild(svgEl('rect', { x: lx, y: ly - 9, width: 12, height: 12, rx: 3, fill: d.color }));
    svg.appendChild(svgEl('text', { class: 'legend-item', x: lx + 20, y: ly, 'dominant-baseline': 'middle' },
      `${d.label} · ${d.value}`));
    ly += 28;
  });

  container.appendChild(svg);
  return svg;
}
