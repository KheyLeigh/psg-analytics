// Vue Dashboard : hydrate les graphiques à partir des données embarquées en SSR
// (courbe des points, top buteurs) et de l'API interne (répartition, carte de
// chaleur), puis ajoute quelques touches "Matchday" (compteur des KPI, tracé de la
// courbe, pulsation du jalon or, apparition décalée du guide de forme via la CSS).
// Tout est en amélioration progressive : sans JS, la page reste lisible.

import { get } from '../modules/api.js';
import * as line from '../charts/line.js';
import * as bar from '../charts/bar.js';
import * as heatmap from '../charts/heatmap.js';

const MONTHS_FR = ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'];
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// "2025-08" devient "août" ; robuste à un format inattendu.
function monthLabel(ym) {
  const month = Number(String(ym).slice(5, 7));
  return MONTHS_FR[month - 1] || String(ym);
}

function readEmbedded() {
  const el = document.querySelector('#dashboard-data');
  if (!el) {
    return {};
  }
  try {
    return JSON.parse(el.textContent);
  } catch (err) {
    console.warn('Données embarquées du dashboard illisibles.', err);
    return {};
  }
}

export function initDashboard() {
  const data = readEmbedded();
  hydratePoints(data);
  hydrateScorers(data);
  hydrateScorersLdc(data);
  hydrateAssists(data);
  hydrateAssistsLdc(data);
  hydrateHeatmap();
  countUpKpis();
}

// Courbe des points cumulés (données SSR, aucun endpoint) avec jalon champion en or.
function hydratePoints(data) {
  const el = document.querySelector('#chart-points');
  const points = Array.isArray(data.points) ? data.points : [];
  if (!el || points.length === 0) {
    return;
  }
  const svg = line.render(el, points.map((p) => ({ x: p.x, y: p.y })), {
    ariaLabel: 'Points de Ligue 1 cumulés journée par journée, jusqu\'au titre.',
    milestone: data.milestone || null,
  });
  el.classList.add('chart--points');
  animateLine(svg);
}

// Tracé progressif de la courbe puis pulsation unique du jalon or, sans jamais
// masquer l'information (le SVG final est déjà présent et lisible).
function animateLine(svg) {
  const dot = svg.querySelector('.milestone-dot');
  if (reduceMotion()) {
    return;
  }
  const path = svg.querySelector('.line-path');
  if (path && typeof path.getTotalLength === 'function') {
    const length = path.getTotalLength();
    path.style.strokeDasharray = String(length);
    path.style.strokeDashoffset = String(length);
    path.getBoundingClientRect();
    path.style.transition = 'stroke-dashoffset 1.1s ease';
    path.style.strokeDashoffset = '0';
  }
  if (dot) {
    window.setTimeout(() => dot.classList.add('is-lit'), 1000);
  }
}

// Top buteurs (données SSR, totaux vérifiés) en barres identité rouge.
function hydrateScorers(data) {
  const el = document.querySelector('#chart-scorers');
  const scorers = Array.isArray(data.topScorers) ? data.topScorers : [];
  if (!el || scorers.length === 0) {
    return;
  }
  bar.render(el, scorers, { ariaLabel: 'Meilleurs buteurs du PSG en Ligue 1.' });
}

// Top buteurs en Ligue des Champions (données SSR, totaux vérifiés). La carte
// n'existe dans le DOM que si le contrôleur a des buts à montrer (jamais de
// donnée fabriquée en cas de saison sans but encore inscrit en C1).
function hydrateScorersLdc(data) {
  const el = document.querySelector('#chart-scorers-ldc');
  const scorers = Array.isArray(data.topScorersLdc) ? data.topScorersLdc : [];
  if (!el || scorers.length === 0) {
    return;
  }
  bar.render(el, scorers, { ariaLabel: 'Meilleurs buteurs du PSG en Ligue des Champions.' });
}

// Top passeurs (données SSR, totaux vérifiés) en barres identité rouge.
function hydrateAssists(data) {
  const el = document.querySelector('#chart-assists');
  const assists = Array.isArray(data.topAssists) ? data.topAssists : [];
  if (!el || assists.length === 0) {
    return;
  }
  bar.render(el, assists, { ariaLabel: 'Meilleurs passeurs du PSG en Ligue 1.' });
}

// Top passeurs en Ligue des Champions (données SSR, totaux vérifiés). La carte
// n'existe dans le DOM que si le contrôleur a des passes à montrer (jamais de
// donnée fabriquée en cas de saison sans passe décisive encore inscrite en C1).
function hydrateAssistsLdc(data) {
  const el = document.querySelector('#chart-assists-ldc');
  const assists = Array.isArray(data.topAssistsLdc) ? data.topAssistsLdc : [];
  if (!el || assists.length === 0) {
    return;
  }
  bar.render(el, assists, { ariaLabel: 'Meilleurs passeurs du PSG en Ligue des Champions.' });
}

// Carte de chaleur buteurs x mois (API /api/heatmap), série estimée. On garde les
// huit meilleurs totaux pour rester lisible.
async function hydrateHeatmap() {
  const el = document.querySelector('#chart-heatmap');
  if (!el) {
    return;
  }
  try {
    const body = await get('/api/heatmap');
    const payload = (body && body.data) || {};
    const months = Array.isArray(payload.months) ? payload.months : [];
    const rawRows = Array.isArray(payload.rows) ? payload.rows : [];
    if (months.length === 0 || rawRows.length === 0) {
      return;
    }
    const rows = rawRows
      .map((row) => {
        const cells = months.map((m) => Number(row.cells[m]) || 0);
        // Libellé court (nom de famille) pour tenir dans la gouttière de la heatmap :
        // les noms complets (ex: Khvicha Kvaratskhelia) débordaient du cadre.
        const fullName = row.player.name || '';
        const label = fullName.split(' ').filter(Boolean).pop() || fullName;
        return { label, cells, total: cells.reduce((s, v) => s + v, 0) };
      })
      .sort((a, b) => b.total - a.total)
      .slice(0, 8);
    heatmap.render(el, { cols: months.length, rows }, {
      ariaLabel: 'Buts par joueur et par mois du PSG sur la saison.',
      colLabels: months.map(monthLabel),
    });
  } catch (err) {
    console.warn('Carte de chaleur indisponible.', err);
  }
}

// Compteur des KPI : les chiffres montent de 0 à leur valeur (esprit tableau
// d'affichage). La valeur finale SSR est restaurée à la fin et si le mouvement
// est limité, on n'y touche pas.
function countUpKpis() {
  if (reduceMotion()) {
    return;
  }
  document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = Number(el.dataset.countup);
    if (!Number.isFinite(target)) {
      return;
    }
    const finalText = el.textContent;
    const duration = 850;
    const start = performance.now();
    el.textContent = '0';
    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = String(Math.round(target * eased));
      if (t < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = finalText;
      }
    };
    requestAnimationFrame(step);
  });
}
