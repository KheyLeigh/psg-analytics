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
  duels_won: 'Tacles gagnés',
  rating: 'Note',
};

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Message de repli identique à celui rendu côté serveur (cohérence SSR/JS).
const FALLBACK_MSG = 'Le radar de comparaison s\'affiche ici une fois deux joueurs sélectionnés (JavaScript activé).';

function num(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

// Formate une valeur brute selon l'axe : note à deux décimales (virgule FR),
// minutes avec séparateur de milliers FR, le reste en entier.
function fmtValue(key, value) {
  if (key === 'rating') {
    return value.toFixed(2).replace('.', ',');
  }
  if (key === 'minutes') {
    return Math.round(value).toLocaleString('fr-FR');
  }
  return String(Math.round(value));
}

export function initCompare() {
  const canvas = document.querySelector('#compare-radar');
  const stats = document.querySelector('#compare-stats');
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
      resetCanvas(FALLBACK_MSG);
      return;
    }
    if (sel.a.id === sel.b.id) {
      setHint('Choisissez deux joueurs différents.');
      resetCanvas(FALLBACK_MSG);
      return;
    }
    setHint(`Comparaison : ${sel.a.name} (rouge) contre ${sel.b.name} (bleu).`);
    try {
      const env = await get(`/api/compare?a=${encodeURIComponent(sel.a.id)}&b=${encodeURIComponent(sel.b.id)}`);
      draw((env && env.data) || {});
    } catch (err) {
      console.warn('Comparaison indisponible.', err);
      // Échec réseau : jamais garder l'ancien radar affiché pour une paire qui ne
      // correspond plus au message affiché.
      setHint('La comparaison a échoué, réessayez.');
      resetCanvas('Comparaison indisponible pour le moment. Réessayez dans un instant.');
    }
  }

  // Vide le conteneur du radar et affiche un message de repli à la place : appelé à
  // chaque fois que la sélection ne correspond plus à un radar affichable (sélection
  // incomplète, joueurs identiques, effacement, ou échec réseau).
  function resetCanvas(message) {
    canvas.classList.remove('is-in');
    canvas.textContent = '';
    const p = document.createElement('p');
    p.className = 'chart-fallback';
    p.textContent = message;
    canvas.appendChild(p);
    if (stats) {
      stats.textContent = '';
    }
  }

  // Face à face chiffré : une ligne par axe, valeur brute de A (rouge) et de B (bleu),
  // barre de proportion A/B au centre, meneur mis en avant. Complète le radar (forme)
  // par l'écart exact, plus parlant. Construit en DOM (pas d'innerHTML).
  function renderStats(data, axes, labels) {
    if (!stats) {
      return;
    }
    stats.textContent = '';
    axes.forEach((key, i) => {
      const va = num(data.a.totals ? data.a.totals[key] : 0);
      const vb = num(data.b.totals ? data.b.totals[key] : 0);
      const total = va + vb;
      const pctA = total > 0 ? (va / total) * 100 : 0;
      const pctB = total > 0 ? (vb / total) * 100 : 0;

      const valA = document.createElement('span');
      valA.className = 'cmp-stats__val cmp-stats__val--a' + (va > vb ? ' is-lead' : '');
      valA.textContent = fmtValue(key, va);

      const label = document.createElement('span');
      label.className = 'cmp-stats__label';
      label.textContent = labels[i];

      const barA = document.createElement('span');
      barA.className = 'cmp-stats__bar-a';
      barA.style.width = pctA.toFixed(1) + '%';
      const barB = document.createElement('span');
      barB.className = 'cmp-stats__bar-b';
      barB.style.width = pctB.toFixed(1) + '%';
      const bar = document.createElement('div');
      bar.className = 'cmp-stats__bar';
      bar.append(barA, barB);

      const mid = document.createElement('div');
      mid.className = 'cmp-stats__mid';
      mid.append(label, bar);

      const valB = document.createElement('span');
      valB.className = 'cmp-stats__val cmp-stats__val--b' + (vb > va ? ' is-lead' : '');
      valB.textContent = fmtValue(key, vb);

      const row = document.createElement('li');
      row.className = 'cmp-stats__row';
      row.append(valA, mid, valB);
      stats.appendChild(row);
    });
  }

  function draw(data) {
    const axes = Array.isArray(data.axes) ? data.axes : [];
    if (axes.length === 0 || !data.a || !data.b) {
      resetCanvas('Comparaison indisponible pour le moment. Réessayez dans un instant.');
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
    renderStats(data, axes, labels);
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
