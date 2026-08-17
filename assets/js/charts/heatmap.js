// Carte de chaleur : grille de cellules, intensité = valeur / max.
// Du navy discret vers une teinte chaude (rouge), lisible en dark et en clair.
// Signature : render(el, {cols, rows:[{label, cells:[...]}]}, {ariaLabel, colLabels}).

import { svgEl } from './axis.js';

const W = 640;
const H = 300;
const M = { top: 24, right: 16, bottom: 28, left: 92 };
const GAP = 4;

export function render(container, data, options = {}) {
  const { ariaLabel = 'Carte de chaleur', colLabels = null } = options;
  const { cols, rows } = data;
  container.textContent = '';
  container.classList.add('chart');

  const allValues = rows.flatMap((r) => r.cells);
  const max = Math.max(...allValues, 0) || 1;

  const plotW = W - M.left - M.right;
  const plotH = H - M.top - M.bottom;
  const cw = plotW / cols;
  const ch = plotH / rows.length;

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet',
  });

  rows.forEach((row, r) => {
    // Libellé de rangée à gauche.
    svg.appendChild(svgEl('text', {
      class: 'heat-label', x: M.left - 10, y: M.top + r * ch + ch / 2,
      'text-anchor': 'end', 'dominant-baseline': 'middle',
    }, row.label));

    row.cells.forEach((value, c) => {
      const t = value / max;
      const x = M.left + c * cw + GAP / 2;
      const y = M.top + r * ch + GAP / 2;
      // Intensité portée par color-mix : fond navy vers rouge chaud selon t.
      const fill = `color-mix(in srgb, var(--red) ${Math.round(t * 100)}%, var(--surface-2))`;
      const cell = svgEl('rect', {
        class: 'heat-cell', x, y, width: cw - GAP, height: ch - GAP, rx: 4, fill,
      });
      cell.appendChild(svgEl('title', {}, `${row.label}, ${(colLabels && colLabels[c]) || 'zone ' + (c + 1)} : ${value}`));
      svg.appendChild(cell);
    });
  });

  // Libellés de colonnes sous la grille (optionnels).
  if (colLabels) {
    colLabels.forEach((label, c) => {
      svg.appendChild(svgEl('text', {
        class: 'heat-label', x: M.left + c * cw + cw / 2, y: H - 10, 'text-anchor': 'middle',
      }, label));
    });
  }

  container.appendChild(svg);
  return svg;
}
