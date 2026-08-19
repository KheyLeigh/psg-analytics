// Fiche joueur : hydrate le radar de profil et la courbe de contribution à partir
// des données embarquées en SSR (#pd-data). La page reste lisible sans JavaScript :
// ces deux graphiques sont une amélioration progressive.
import { render as renderRadar } from '../charts/radar.js';
import { render as renderLine } from '../charts/line.js';

export function initPlayerDetail() {
  const dataEl = document.querySelector('#pd-data');
  if (!dataEl) {
    return;
  }

  let payload;
  try {
    payload = JSON.parse(dataEl.textContent);
  } catch (err) {
    console.warn('Données de la fiche joueur illisibles.', err);
    return;
  }

  const name = (document.querySelector('.pd__name')?.textContent || 'Joueur').trim();

  // Remplace le contenu d'un conteneur de graphique par un message d'état vide
  // honnête (le joueur n'a pas de statistique par match), plutôt que de laisser le
  // repère "s'affiche ici (JavaScript activé)" qui deviendrait mensonger.
  const showEmpty = (el, message) => {
    if (el) {
      el.textContent = '';
      const p = document.createElement('p');
      p.className = 'chart-fallback';
      p.textContent = message;
      el.appendChild(p);
    }
  };

  // Radar de profil : une seule série (le joueur), en rouge identité.
  const radarEl = document.querySelector('#pd-radar');
  const profile = payload.profile;
  const hasProfile = profile && Array.isArray(profile.axes) && profile.axes.length > 0
    && Array.isArray(profile.values) && profile.values.some((v) => v > 0);
  if (radarEl && hasProfile) {
    radarEl.textContent = '';
    renderRadar(
      radarEl,
      { axes: profile.axes, series: [{ name, values: profile.values }] },
      { ariaLabel: `Radar de profil de ${name}, chaque axe rapporté au meilleur de l'effectif.` }
    );
  } else if (radarEl) {
    showEmpty(radarEl, 'Profil indisponible : aucune statistique par match pour ce joueur.');
  }

  // Courbe : contribution cumulée (buts + passes décisives) au fil des matchs.
  const lineEl = document.querySelector('#pd-line');
  const timeline = payload.timeline;
  if (lineEl && Array.isArray(timeline) && timeline.length > 0) {
    let cumulative = 0;
    const series = timeline.map((match, index) => {
      cumulative += (match.goals || 0) + (match.assists || 0);
      return { x: index + 1, y: cumulative };
    });
    lineEl.textContent = '';
    renderLine(lineEl, series, {
      ariaLabel: `Contribution cumulée de ${name} (buts et passes décisives) au fil de la saison.`,
      pointLabel: (d) => `Match ${d.x} : ${d.y} contribution${d.y >= 2 ? 's' : ''} cumulée${d.y >= 2 ? 's' : ''}`,
    });
  } else if (lineEl) {
    showEmpty(lineEl, 'Courbe indisponible : aucune statistique par match pour ce joueur.');
  }
}
