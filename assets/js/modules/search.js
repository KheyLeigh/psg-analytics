// Recherche live côté client : filtre les lignes DÉJÀ chargées de l'effectif
// (amélioration progressive, aucun appel réseau). Masque les lignes non
// correspondantes, annonce le nombre de résultats via aria-live et anime
// discrètement le compteur quand il change (respecte prefers-reduced-motion).

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Normalise pour une comparaison insensible à la casse et aux accents (Dembélé ~ dembele).
function norm(value) {
  return String(value).toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

export function initSearch() {
  const input = document.querySelector('#player-search');
  const body = document.querySelector('#roster-body');
  const count = document.querySelector('#player-count');
  if (!input || !body || !count) {
    return null;
  }

  let lastCount = -1;

  // Filtre les lignes visibles selon la saisie. Réutilisable après un rechargement
  // du tableau (les lignes sont relues à chaque appel).
  function apply() {
    const query = norm(input.value.trim());
    let visible = 0;
    body.querySelectorAll('.roster-row').forEach((row) => {
      const match = query === '' || norm(row.textContent).includes(query);
      row.hidden = !match;
      if (match) {
        visible += 1;
      }
    });
    setCount(visible);
  }

  function setCount(n) {
    count.textContent = `${n} ${n > 1 ? 'joueurs' : 'joueur'} sur cette page`;
    // Petite pulsation quand le total change, jamais porteuse d'information à elle seule.
    if (n !== lastCount && lastCount !== -1 && !reduceMotion()) {
      count.classList.remove('is-bump');
      void count.offsetWidth;
      count.classList.add('is-bump');
    }
    lastCount = n;
  }

  input.addEventListener('input', apply);
  return { apply };
}
