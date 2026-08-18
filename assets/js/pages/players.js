// Vue Joueurs : orchestre les enrichissements au-dessus de la page rendue côté
// serveur. La recherche filtre les lignes chargées, le tableau trie et pagine via
// l'API, le comparateur trace le radar. Tout dégrade proprement : sans JS la page
// reste navigable, en cas d'échec réseau l'affichage courant est conservé.

import { initSearch } from '../modules/search.js';
import { initTable } from '../modules/table.js';
import { initCompare } from '../modules/compare.js';

export function initPlayers() {
  const search = initSearch();
  const compare = initCompare();

  // Après un tri ou un changement de page, les lignes sont remplacées : on ré-applique
  // le filtre de recherche courant puis l'état de sélection du comparateur.
  initTable(() => {
    if (search) {
      search.apply();
    }
    if (compare) {
      compare.rebind();
    }
  });
}
