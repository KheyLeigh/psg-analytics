// Radar de comparaison : axes normalisés 0..1, deux séries superposées.
// Série 1 en rouge (identité), série 2 en bleu (comparaison).
// Signature : render(el, {axes:[...], series:[{name, values:[0..1]}]}, {ariaLabel}).

import { svgEl } from './axis.js';

const W = 640;
const H = 380;
const CX = W / 2;
const CY = H / 2 + 6;
const R = 130;
const RINGS = 4;

// Point du radar pour l'axe i (réparti sur le cercle) et un rayon normalisé t.
function point(i, count, t) {
  const angle = (i / count) * Math.PI * 2 - Math.PI / 2;
  return [CX + R * t * Math.cos(angle), CY + R * t * Math.sin(angle)];
}

export function render(container, data, options = {}) {
  const { ariaLabel = 'Radar comparatif' } = options;
  const { axes, series } = data;
  container.textContent = '';
  container.classList.add('chart');

  const n = axes.length;

  const svg = svgEl('svg', {
    viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': ariaLabel,
    preserveAspectRatio: 'xMidYMid meet',
  });

  // Toile : anneaux concentriques discrets.
  for (let ring = 1; ring <= RINGS; ring++) {
    const t = ring / RINGS;
    const pts = axes.map((_, i) => point(i, n, t).join(',')).join(' ');
    svg.appendChild(svgEl('polygon', { class: 'radar-web', points: pts }));
  }

  // Rayons vers chaque axe et libellés en périphérie.
  axes.forEach((label, i) => {
    const [ex, ey] = point(i, n, 1);
    svg.appendChild(svgEl('line', { class: 'radar-spoke', x1: CX, y1: CY, x2: ex, y2: ey }));
    const [lx, ly] = point(i, n, 1.16);
    const anchor = Math.abs(lx - CX) < 4 ? 'middle' : (lx > CX ? 'start' : 'end');
    svg.appendChild(svgEl('text', {
      class: 'radar-axis-label', x: lx, y: ly, 'text-anchor': anchor, 'dominant-baseline': 'middle',
    }, label));
  });

  // Séries : la première prend le rôle identité, la seconde la comparaison.
  series.forEach((serie, s) => {
    const pts = serie.values.map((v, i) => point(i, n, Math.max(0, Math.min(1, v))).join(',')).join(' ');
    const poly = svgEl('polygon', { class: `radar-serie-${s + 1}`, points: pts });
    poly.appendChild(svgEl('title', {}, serie.name));
    svg.appendChild(poly);
    // Sommets marqués pour la lecture point à point.
    serie.values.forEach((v, i) => {
      const [px, py] = point(i, n, Math.max(0, Math.min(1, v)));
      const dot = svgEl('circle', {
        cx: px, cy: py, r: 2.5, fill: s === 0 ? 'var(--red)' : 'var(--blue)',
      });
      dot.appendChild(svgEl('title', {}, `${serie.name} · ${axes[i]} : ${Math.round(v * 100)}%`));
      svg.appendChild(dot);
    });
  });

  // Légende des séries en bas.
  let lx = 40;
  series.forEach((serie, s) => {
    svg.appendChild(svgEl('rect', {
      x: lx, y: H - 18, width: 12, height: 12, rx: 3, fill: s === 0 ? 'var(--red)' : 'var(--blue)',
    }));
    svg.appendChild(svgEl('text', { class: 'legend-item', x: lx + 20, y: H - 8 }, serie.name));
    lx += 150;
  });

  container.appendChild(svg);
  return svg;
}
