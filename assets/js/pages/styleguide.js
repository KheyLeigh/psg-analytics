// Labo styleguide : hydrate les démonstrations qui ont besoin de JavaScript. Pour
// l'instant, la carte des tirs (prototype), alimentée par les données embarquées en SSR.
import { render as renderShotmap } from '../charts/shotmap.js';

export function initStyleguide() {
  const el = document.querySelector('#shotmap');
  const dataEl = document.querySelector('#shotmap-data');
  if (!el || !dataEl) {
    return;
  }
  let data;
  try {
    data = JSON.parse(dataEl.textContent);
  } catch (err) {
    return;
  }
  el.textContent = '';
  const who = [data.player, data.competition, data.season].filter(Boolean).join(', ');
  renderShotmap(el, data, { ariaLabel: `Carte des tirs de ${who}.` });
}
