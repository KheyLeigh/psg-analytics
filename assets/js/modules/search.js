// Recherche live côté client : filtre les lignes DÉJÀ chargées de l'effectif
// (amélioration progressive, aucun appel réseau). Masque les lignes non
// correspondantes, annonce le nombre de résultats via aria-live et anime
// discrètement le compteur quand il change (respecte prefers-reduced-motion).

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Normalise pour une comparaison insensible à la casse et aux accents (Dembélé ~ dembele).
function norm(value) {
  return String(value).toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

// Colonnes ciblées par la recherche, dans l'ordre des <td> du tableau : numéro,
// nom, poste (exclu), nationalité. Le poste et le bouton Comparer sont exclus pour
// que "comparer" ne remonte pas toutes les lignes (voir roster-table__cmp-h).
const SEARCH_CELL_INDEXES = [0, 1, 3];

// Construit la chaîne cherchable d'une ligne à partir des seules cellules
// pertinentes (numéro, nom, nationalité), jamais du texte entier de la ligne.
function searchableText(row) {
  const cells = row.querySelectorAll('td');
  const parts = SEARCH_CELL_INDEXES.map((i) => cells[i] && cells[i].textContent).filter(Boolean);
  return norm(parts.join(' '));
}

export function initSearch() {
  const input = document.querySelector('#player-search');
  const body = document.querySelector('#roster-body');
  const count = document.querySelector('#player-count');
  const empty = document.querySelector('#roster-empty');
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
      const match = query === '' || searchableText(row).includes(query);
      row.hidden = !match;
      if (match) {
        visible += 1;
      }
    });
    setCount(visible);
    setEmptyState(visible, query !== '');
  }

  // Message "aucun résultat" dédié : distinct selon qu'il s'agit d'une recherche
  // sans correspondance ou d'un effectif vide (filtre poste sans résultat côté API).
  function setEmptyState(visible, hasQuery) {
    if (!empty) {
      return;
    }
    empty.hidden = visible !== 0;
    if (visible === 0) {
      empty.textContent = hasQuery
        ? 'Aucun joueur ne correspond à la recherche.'
        : 'Aucun joueur ne correspond aux filtres actuels.';
    }
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
