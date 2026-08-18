// Tri au clic sur en-tête et pagination de l'effectif, via /api/players. La liste
// blanche de tri est respectée côté client comme côté serveur : une colonne non
// triable ne déclenche aucun appel et aucune URL n'est forgée hors liste. Dégradation
// propre : en cas d'échec réseau, l'affichage courant est conservé (aucune casse).

import { get } from '../modules/api.js';

const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];
const POS_LABELS = { GK: 'Gardien', DF: 'Défenseur', MF: 'Milieu', FW: 'Attaquant' };
const TAG_CLASS = { GK: 'tag--gk', DF: 'tag--def', MF: 'tag--mid', FW: 'tag--fwd' };

function toInt(value, fallback) {
  const n = parseInt(value, 10);
  return Number.isFinite(n) ? n : fallback;
}

export function initTable(onUpdate) {
  const roster = document.querySelector('.roster');
  const tbody = document.querySelector('#roster-body');
  const pager = document.querySelector('#roster-pager');
  const more = roster ? roster.querySelector('.panel__more') : null;
  if (!roster || !tbody) {
    return null;
  }

  const state = {
    page: toInt(roster.dataset.curPage, 1),
    perPage: toInt(roster.dataset.perPage, 12),
    totalPages: toInt(roster.dataset.totalPages, 1),
    total: toInt(roster.dataset.total, 0),
    sort: SORTABLE.includes(roster.dataset.sort) ? roster.dataset.sort : 'last_name',
    order: roster.dataset.order === 'DESC' ? 'DESC' : 'ASC',
    position: roster.dataset.position || '',
    busy: false,
  };

  // Reconstruit une URL /joueurs cohérente avec le SSR (mêmes règles que le contrôleur),
  // pour que les liens restent justes même si l'hydratation venait à échouer plus tard.
  function buildHref(over) {
    const s = { sort: state.sort, order: state.order, position: state.position, page: 1, ...over };
    const parts = [];
    if (s.position) {
      parts.push(['position', s.position]);
    }
    if (s.sort !== 'last_name' || s.order !== 'ASC') {
      parts.push(['sort', s.sort], ['order', s.order]);
    }
    if (Number(s.page) > 1) {
      parts.push(['page', s.page]);
    }
    const qs = parts.map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
    return '/joueurs' + (qs ? '?' + qs : '');
  }

  // Tri : interception du lien d'en-tête pour passer par l'API (liste blanche stricte).
  roster.querySelectorAll('th[data-sort] .th-sort').forEach((link) => {
    const key = link.closest('th').dataset.sort;
    link.addEventListener('click', (event) => {
      if (!SORTABLE.includes(key)) {
        return; // hors liste blanche : on laisse le lien SSR agir, aucun appel forgé
      }
      event.preventDefault();
      const order = state.sort === key && state.order === 'ASC' ? 'DESC' : 'ASC';
      load({ sort: key, order, page: 1 });
    });
  });

  // Pagination : délégation sur le conteneur, dont les boutons sont reconstruits.
  if (pager) {
    pager.addEventListener('click', (event) => {
      const btn = event.target.closest('a[data-page]');
      if (!btn) {
        return;
      }
      event.preventDefault();
      load({ page: toInt(btn.dataset.page, state.page) });
    });
  }

  async function load(next) {
    if (state.busy) {
      return;
    }
    const params = {
      page: next.page ?? state.page,
      perPage: next.perPage ?? state.perPage,
      sort: next.sort ?? state.sort,
      order: next.order ?? state.order,
      position: next.position ?? state.position,
    };
    // Garde-fous : jamais de valeur hors liste blanche dans l'URL appelée.
    if (!SORTABLE.includes(params.sort)) {
      params.sort = 'last_name';
    }
    if (params.order !== 'DESC') {
      params.order = 'ASC';
    }

    let path = `/api/players?page=${params.page}&per_page=${params.perPage}&sort=${params.sort}&order=${params.order}`;
    if (params.position) {
      path += `&position=${encodeURIComponent(params.position)}`;
    }

    state.busy = true;
    roster.classList.add('is-loading');
    try {
      const body = await get(path);
      const items = (body && body.data) || [];
      const meta = (body && body.meta) || {};
      renderRows(items);
      Object.assign(state, {
        page: meta.page ?? params.page,
        perPage: meta.per_page ?? params.perPage,
        totalPages: meta.total_pages ?? state.totalPages,
        total: meta.total ?? state.total,
        sort: params.sort,
        order: params.order,
        position: params.position,
      });
      syncDataset();
      syncHeaders();
      renderPager();
      if (more) {
        more.textContent = `${state.total} joueurs · page ${state.page}/${state.totalPages}`;
      }
      if (typeof onUpdate === 'function') {
        onUpdate();
      }
    } catch (err) {
      // Dégradation : l'affichage courant reste en place, rien ne casse.
      console.warn('Tri ou pagination indisponible, affichage courant conservé.', err);
    } finally {
      state.busy = false;
      roster.classList.remove('is-loading');
    }
  }

  // Rendu des lignes par le DOM (jamais d'innerHTML avec des données) : aucune injection.
  function renderRows(items) {
    tbody.textContent = '';
    items.forEach((p) => {
      const tr = document.createElement('tr');
      tr.className = 'roster-row';
      tr.dataset.id = p.id;
      tr.dataset.name = p.name;

      const tdNum = document.createElement('td');
      const jersey = document.createElement('span');
      jersey.className = 'jersey jersey--sm';
      jersey.textContent = p.number == null ? '·' : p.number;
      tdNum.appendChild(jersey);

      const tdName = document.createElement('td');
      const name = document.createElement('span');
      name.className = 'roster-row__name';
      name.textContent = p.name;
      tdName.appendChild(name);

      const tdPos = document.createElement('td');
      const tag = document.createElement('span');
      tag.className = 'tag ' + (TAG_CLASS[p.position] || 'tag--mid');
      tag.title = POS_LABELS[p.position] || p.position;
      tag.textContent = p.position;
      tdPos.appendChild(tag);

      const tdNat = document.createElement('td');
      tdNat.textContent = p.nationality;

      const tdCmp = document.createElement('td');
      tdCmp.className = 'roster-row__cmp';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cmp-pick';
      btn.dataset.id = p.id;
      btn.dataset.name = p.name;
      btn.setAttribute('aria-pressed', 'false');
      const lbl = document.createElement('span');
      lbl.className = 'cmp-pick__label';
      lbl.textContent = 'Comparer';
      btn.appendChild(lbl);
      tdCmp.appendChild(btn);

      tr.append(tdNum, tdName, tdPos, tdNat, tdCmp);
      tbody.appendChild(tr);
    });
  }

  function syncDataset() {
    roster.dataset.curPage = String(state.page);
    roster.dataset.sort = state.sort;
    roster.dataset.order = state.order;
    roster.dataset.position = state.position;
    roster.dataset.totalPages = String(state.totalPages);
  }

  // Met à jour aria-sort et les liens d'en-tête pour refléter le tri courant.
  function syncHeaders() {
    roster.querySelectorAll('th[data-sort]').forEach((th) => {
      const key = th.dataset.sort;
      const active = state.sort === key;
      th.setAttribute('aria-sort', active ? (state.order === 'ASC' ? 'ascending' : 'descending') : 'none');
      const link = th.querySelector('.th-sort');
      if (link) {
        const nextOrder = active && state.order === 'ASC' ? 'DESC' : 'ASC';
        link.dataset.order = nextOrder;
        link.setAttribute('href', buildHref({ sort: key, order: nextOrder, page: 1 }));
      }
    });
  }

  function renderPager() {
    if (!pager) {
      return;
    }
    pager.textContent = '';
    pager.appendChild(pagerBtn('Précédent', state.page > 1 ? state.page - 1 : null, 'prev'));
    const status = document.createElement('span');
    status.className = 'pager__status';
    status.setAttribute('aria-live', 'polite');
    status.textContent = `Page ${state.page} sur ${state.totalPages}`;
    pager.appendChild(status);
    pager.appendChild(pagerBtn('Suivant', state.page < state.totalPages ? state.page + 1 : null, 'next'));
  }

  function pagerBtn(label, targetPage, rel) {
    if (targetPage === null) {
      const span = document.createElement('span');
      span.className = 'pager__btn is-disabled';
      span.setAttribute('aria-disabled', 'true');
      span.textContent = label;
      return span;
    }
    const a = document.createElement('a');
    a.className = 'pager__btn';
    a.rel = rel;
    a.dataset.page = String(targetPage);
    a.href = buildHref({ page: targetPage });
    a.textContent = label;
    return a;
  }

  return { load };
}
