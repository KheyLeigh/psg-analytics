// Comparateur : sélection de deux joueurs (A puis B), appel /api/compare?a&b, rendu
// d'un radar normalisé (série A en rouge, série B en bleu, selon les rôles couleur du
// design system). Gère la sélection incomplète et le cas a == b. Fondu au changement,
// désactivé sous prefers-reduced-motion. Amélioration progressive : sans JS, la page
// reste navigable ; en cas d'échec réseau, aucune casse.

import { get } from '../modules/api.js';
import * as radar from '../charts/radar.js';

// Libellés FR des axes renvoyés par l'API (clés stables du ComparisonService).
const AXIS_LABELS = {
  goals: 'Buts',
  assists: 'Passes déc.',
  minutes: 'Minutes',
  shots: 'Tirs',
  duels_won: 'Duels gagnés',
  rating: 'Note',
};

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function num(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

export function initCompare() {
  const canvas = document.querySelector('#compare-radar');
  const slotA = document.querySelector('#cmp-slot-a');
  const slotB = document.querySelector('#cmp-slot-b');
  const hint = document.querySelector('#compare-hint');
  const clearBtn = document.querySelector('#cmp-clear');
  const tbody = document.querySelector('#roster-body');
  if (!canvas || !slotA || !slotB || !tbody) {
    return null;
  }

  // Sélection courante : { id, name } ou null pour chaque emplacement.
  const sel = { a: null, b: null };

  // Choisit ou déselectionne un joueur. A se remplit d'abord, puis B ; les deux pleins,
  // un nouveau clic remplace B (le plus récent).
  function pick(id, name) {
    id = String(id);
    if (sel.a && sel.a.id === id) {
      sel.a = null;
    } else if (sel.b && sel.b.id === id) {
      sel.b = null;
    } else if (!sel.a) {
      sel.a = { id, name };
    } else if (!sel.b) {
      sel.b = { id, name };
    } else {
      sel.b = { id, name };
    }
    reflect();
  }

  // Reflète l'état de sélection sur les emplacements A/B et sur chaque ligne du tableau.
  function reflect() {
    setSlot(slotA, sel.a);
    setSlot(slotB, sel.b);
    tbody.querySelectorAll('.cmp-pick').forEach((btn) => {
      const id = String(btn.dataset.id);
      const isA = sel.a && sel.a.id === id;
      const isB = sel.b && sel.b.id === id;
      btn.setAttribute('aria-pressed', isA || isB ? 'true' : 'false');
      btn.classList.toggle('is-a', Boolean(isA));
      btn.classList.toggle('is-b', Boolean(isB));
      const label = btn.querySelector('.cmp-pick__label');
      if (label) {
        label.textContent = isA ? 'Joueur A' : (isB ? 'Joueur B' : 'Comparer');
      }
      const row = btn.closest('.roster-row');
      if (row) {
        row.classList.toggle('is-a', Boolean(isA));
        row.classList.toggle('is-b', Boolean(isB));
      }
    });
    if (clearBtn) {
      clearBtn.hidden = !(sel.a || sel.b);
    }
    update();
  }

  function setSlot(slot, player) {
    const nameEl = slot.querySelector('.cmp-slot__name');
    slot.classList.toggle('is-filled', Boolean(player));
    if (nameEl) {
      nameEl.textContent = player ? player.name : 'Choisir un joueur';
    }
  }

  async function update() {
    if (!sel.a || !sel.b) {
      setHint('Sélectionnez deux joueurs pour afficher le radar.');
      return;
    }
    if (sel.a.id === sel.b.id) {
      setHint('Choisissez deux joueurs différents.');
      return;
    }
    setHint(`Comparaison : ${sel.a.name} (rouge) contre ${sel.b.name} (bleu).`);
    try {
      const env = await get(`/api/compare?a=${encodeURIComponent(sel.a.id)}&b=${encodeURIComponent(sel.b.id)}`);
      draw((env && env.data) || {});
    } catch (err) {
      console.warn('Comparaison indisponible.', err);
    }
  }

  function draw(data) {
    const axes = Array.isArray(data.axes) ? data.axes : [];
    if (axes.length === 0 || !data.a || !data.b) {
      return;
    }
    const labels = axes.map((key) => AXIS_LABELS[key] || key);
    const series = [
      { name: data.a.player ? data.a.player.name : 'A', values: axes.map((key) => num(data.a.normalized[key])) },
      { name: data.b.player ? data.b.player.name : 'B', values: axes.map((key) => num(data.b.normalized[key])) },
    ];
    const ariaLabel = `Radar comparatif : ${series[0].name} en rouge contre ${series[1].name} en bleu, sur ${labels.join(', ')}.`;
    if (!reduceMotion()) {
      canvas.classList.remove('is-in');
      void canvas.offsetWidth;
      canvas.classList.add('is-in');
    }
    radar.render(canvas, { axes: labels, series }, { ariaLabel });
  }

  function setHint(text) {
    if (hint) {
      hint.textContent = text;
    }
  }

  function clearAll() {
    sel.a = null;
    sel.b = null;
    reflect();
  }

  // Bouton Comparer = chemin accessible (clavier) ; clic sur la ligne = confort souris.
  tbody.addEventListener('click', (event) => {
    const btn = event.target.closest('.cmp-pick');
    if (btn) {
      pick(btn.dataset.id, btn.dataset.name);
      return;
    }
    const row = event.target.closest('.roster-row');
    if (row && !event.target.closest('a')) {
      pick(row.dataset.id, row.dataset.name);
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', clearAll);
  }

  // Après un rechargement du tableau (tri/pagination), on ré-applique l'état visuel.
  return { rebind: reflect, clear: clearAll };
}
