// Client HTTP minimal de l'API interne. Même origine uniquement : on n'accepte
// qu'un chemin absolu commençant par "/", jamais une URL externe, et aucune donnée
// sensible ne transite par la query string. Renvoie l'enveloppe { data, meta, source }.

export async function get(path) {
  if (typeof path !== 'string' || !path.startsWith('/')) {
    throw new Error(`Chemin d'API invalide : un chemin absolu de même origine est attendu (reçu ${String(path)}).`);
  }

  let response;
  try {
    response = await fetch(path, { headers: { Accept: 'application/json' } });
  } catch (cause) {
    // Coupure réseau ou requête bloquée : on remonte une erreur explicite et typée.
    throw new Error(`Requête réseau échouée pour ${path}.`, { cause });
  }

  if (!response.ok) {
    throw new Error(`Réponse ${response.status} (${response.statusText}) pour ${path}.`);
  }

  let body;
  try {
    body = await response.json();
  } catch (cause) {
    throw new Error(`JSON invalide reçu de ${path}.`, { cause });
  }

  return body;
}
