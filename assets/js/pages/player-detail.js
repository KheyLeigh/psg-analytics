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

  // Radar de profil : une seule série (le joueur), en rouge identité.
  const radarEl = document.querySelector('#pd-radar');
  const profile = payload.profile;
  if (radarEl && profile && Array.isArray(profile.axes) && profile.axes.length > 0) {
    radarEl.textContent = '';
    renderRadar(
      radarEl,
      { axes: profile.axes, series: [{ name, values: profile.values }] },
      { ariaLabel: `Radar de profil de ${name}, chaque axe rapporté au meilleur de l'effectif.` }
    );
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
    });
  }
}
