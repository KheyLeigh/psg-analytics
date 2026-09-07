# Fondations multi-saisons : spécification de conception

**Date :** 7 septembre 2026
**Auteur :** Mathis Periard
**Statut :** validé, prêt pour planification

---

## 1. Contexte et objectif

### Pourquoi ce spec existe

PSG Analytics modélise aujourd'hui une seule saison, la 2025-26, déjà terminée. Le PSG entame la saison 2026-27, et l'objectif à terme est d'automatiser la remontée de ses statistiques au fil des matchs joués (spec séparé, à venir). Cette automatisation a besoin d'un endroit où écrire des données 2026-27 sans jamais toucher aux données 2025-26 déjà en production.

Or, malgré une colonne `season_id` présente sur plusieurs tables (`players`, `matches`, `player_season_stats`), **aucun repository ne filtre dessus aujourd'hui** : chaque requête suppose implicitement qu'une seule saison existe en base. `database/seeds/verified/seasons.php` porte même le commentaire « hors périmètre : multi-saisons ». Ce spec pose donc la fondation manquante : faire cohabiter proprement plusieurs saisons, sans automatisation de collecte à ce stade.

### Critères de réussite

1. La saison 2025-26 continue de s'afficher à l'identique sur toutes les pages, sans aucune régression.
2. Une deuxième saison (2026-27, vide ou partielle au départ) peut exister en base sans jamais modifier ni supprimer une ligne de 2025-26.
3. Un sélecteur de saison, visible sur toutes les pages, permet de basculer l'affichage entre les saisons disponibles.
4. `php database/migrate.php` reconstruit correctement les deux saisons à partir de leurs seeds respectifs, dans la continuité du fonctionnement actuel (rebuild complet à chaque exécution).
5. `php tests/run.php` reste intégralement vert.

### Hors périmètre

- Toute automatisation de collecte de données (scraping FBref/Understat, tâche planifiée). Ce sera un spec séparé, qui s'appuiera sur les fondations posées ici.
- Statistiques cumulées multi-saisons par joueur (carrière PSG agrégée). Seule la liste des saisons jouées par un joueur est affichée.
- Remplissage réel du contenu sportif de la saison 2026-27 (effectif, calendrier, résultats). Ce spec crée la structure d'accueil ; le contenu est cadré et rempli séparément.

---

## 2. Schéma : évolutions additives

Le principe directeur est qu'aucune colonne ni table existante n'est supprimée ou modifiée de façon destructive. Les évolutions sont des ajouts.

### `seasons`

Ajout d'une colonne `is_current INTEGER NOT NULL DEFAULT 0`. Une seule saison porte `is_current = 1` à un instant donné ; c'est elle que le site affiche par défaut. La contrainte d'unicité (une seule saison courante) est vérifiée par le Migrator au moment du peuplement, pas en SQL (cohérent avec le reste du projet, où les garde-fous d'intégrité vivent dans `Migrator.php`).

### `people` (nouvelle table)

Identité stable d'un joueur à travers les saisons, indépendante de son effectif saison par saison :

```sql
CREATE TABLE people (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name  TEXT NOT NULL,
    last_name   TEXT NOT NULL
);
```

Volontairement minimale : une clé de résolution, pas un doublon des attributs saisonniers (poste, nationalité, numéro de maillot, etc.), qui restent portés par `players` exactement comme aujourd'hui. Un joueur transféré, retraité ou dont le poste change d'une saison à l'autre garde ainsi ses lignes `players` indépendantes, reliées par la même `people.id`.

### `players`

Ajout d'une colonne `person_id INTEGER NOT NULL REFERENCES people(id)`. Toutes les colonnes existantes (`season_id`, `shirt_number`, `position`, etc.) restent inchangées.

### Tables inchangées

`teams`, `competitions`, `data_sources` restent des catalogues partagés entre saisons, comme c'est déjà implicitement le cas aujourd'hui (aucune de ces tables ne porte de `season_id`). Ajouter une saison ne nécessite d'insérer une nouvelle ligne dans ces tables que si un club, une compétition ou une source est réellement inédit (ex. un adversaire de Ligue des Champions jamais affronté), exactement comme la migration actuelle ajoute déjà des équipes au fil des seeds.

`schema.sqlite.sql` et `schema.mysql.sql` évoluent en parallèle, avec les mêmes équivalences de types déjà en usage entre les deux fichiers (`INTEGER`/`INT`, `TEXT`/`VARCHAR`, etc.).

---

## 3. Réorganisation des seeds

`database/seeds/verified/` éclate en un sous-dossier par saison :

```
database/seeds/verified/
  teams.php               (partagé, inchangé)
  competitions.php        (partagé, inchangé)
  sources.php             (partagé, inchangé)
  seasons.php             (liste à plusieurs entrées)
  2025-26/
    players.php
    player_season.php
    matches_l1.php
    matches_other.php
    players_l1_fbref.php
    season_totals.php      (nouveau, voir section 4)
  2026-27/
    players.php             (contenu réel cadré séparément)
    matches_l1.php
    matches_other.php
    ...
```

Les fichiers `2025-26/*.php` sont les fichiers actuels, déplacés tels quels (aucune donnée réécrite). `seasons.php` devient :

```php
return [
    ['key' => '2025-26', 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => false],
    ['key' => '2026-27', 'label' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true],
];
```

---

## 4. Migrator : boucle multi-saisons et garde-fou générique

### Boucle par saison

`migrate.php` continue de faire un rebuild complet à chaque exécution, comportement actuel conservé (la base n'est pas versionnée, les seeds restent la seule source de vérité). `run_migration()` boucle désormais sur les saisons déclarées dans `seasons.php` et charge le sous-dossier correspondant pour chacune. Ajouter 2026-27 ne modifie aucune ligne de code qui traite 2025-26 ; les deux saisons passent par le même chemin de code, avec des données différentes.

### Résolution `person_id`

Lors du peuplement d'une saison, chaque joueur du fichier `players.php` de cette saison est résolu vers une `people.id` par clé nom/prénom (même fonction `migrator_player_key` déjà utilisée pour résoudre les joueurs au sein d'une saison) : si la clé correspond à une `people` déjà créée par une saison précédente, elle est réutilisée ; sinon une nouvelle ligne `people` est créée.

### Garde-fou d'intégrité : générique + totaux figés optionnels

Le garde-fou actuel (`migrator_verify_identities`) vérifie des totaux exacts et figés (24V/4N/6D, 74-29, 73 buts individuels) qui n'existent que pour une saison terminée. Il se scinde en deux niveaux :

1. **Contrôle générique, toujours actif, pour chaque saison** : la somme des buts individuels d'une compétition ne dépasse jamais le total de buts d'équipe de cette compétition ; toute ligne `player_match_stats`/`player_season_stats` référence un joueur et un match existants dans la même saison. Ce contrôle ne suppose aucun total connu à l'avance et s'applique donc aussi bien à une saison terminée qu'en cours.
2. **Totaux figés, optionnels par saison** : un fichier `season_totals.php` (comme celui listé en section 3) contenant les totaux exacts vérifiés (V/N/D, buts pour/contre, buts individuels) n'est vérifié que s'il existe pour la saison en cours de traitement. `2025-26/season_totals.php` existe et porte les valeurs actuelles (24V/4N/6D, 74-29, 73). `2026-27/season_totals.php` n'existe pas tant que la saison n'est pas terminée : aucune vérification de totaux figés ne s'applique à elle avant cela.

---

## 5. Sélecteur de saison, repositories et contrôleurs

### Résolution de la saison affichée

Un paramètre de requête `?saison=2025-26` sur les routes de pages sélectionne la saison consultée, dans la continuité des filtres déjà exposés en query string ailleurs sur le site (`/matchs?competition=...&resultat=...`). Absent ou invalide, il vaut la saison dont `is_current = 1`. Une nouvelle méthode `SeasonRepository::current()` et `SeasonRepository::bySlug(string $slug)` portent cette résolution.

### Propagation explicite, pas d'état global

Chaque contrôleur résout la saison une fois en début d'action, puis passe `season_id` explicitement aux méthodes de repository, exactement comme `psgId` et `leagueId` sont déjà résolus une fois et passés en paramètre aujourd'hui (voir `DashboardController::index`). Aucun nouveau mécanisme de conteneur ou d'état global n'est introduit ; le style de câblage manuel déjà en place dans les contrôleurs est conservé.

### Repositories concernés

`PlayerRepository`, `MatchRepository`, `StatisticRepository`, `PlayerSeasonStatsRepository` gagnent un paramètre `season_id` sur leurs méthodes de lecture, avec une clause `WHERE season_id = ?` directe (`players`, `player_season_stats`) ou via jointure sur `matches.season_id` (`player_match_stats`, `events`). `CompetitionRepository`, `TeamRepository`, `SourceRepository` restent inchangés (catalogues partagés).

### Vues

Un sélecteur de saison dans le header, sur le même principe que la bascule de thème déjà en place (composant persistant, cohérent visuellement). Toutes les pages listant des données saisonnières (accueil, dashboard, joueurs, matchs, méthodologie) respectent la saison sélectionnée.

---

## 6. Fiche joueur : ajout minimal

`/joueurs/{id}` affiche, en plus du contenu actuel, la liste des saisons PSG associées au `person_id` de ce joueur (labels de saison uniquement, pas de statistiques cumulées), en retrouvant les autres lignes `players` reliées à la même `people.id`.

---

## 7. Tests

Les 87 tests existants s'adaptent partout où `season_id` devient un paramètre requis. S'ajoutent :

- Un test vérifiant que la boucle multi-saisons du Migrator peuple correctement deux saisons distinctes sans collision d'identifiants.
- Un test de résolution `person_id` (un joueur présent dans deux saisons obtient la même `people.id`, un joueur nouveau en obtient une nouvelle).
- Un test du garde-fou générique (buts individuels > buts d'équipe doit échouer, y compris sans `season_totals.php`).
- Un test du sélecteur de saison (paramètre absent → saison courante ; paramètre invalide → saison courante ; paramètre valide → saison demandée).

`php tests/run.php` doit rester intégralement vert après ces changements.

---

## 8. Risques et points de vigilance

- **Volume de changement transversal** : le sélecteur de saison touche tous les contrôleurs de pages et leurs repositories. Le plan d'implémentation devra séquencer ce travail page par page pour rester vérifiable à chaque étape, plutôt que de tout modifier d'un bloc.
- **Déplacement des fichiers seeds 2025-26** : un simple déplacement de fichier, mais toute référence à un chemin `verified/xxx.php` ailleurs dans le code (tests, documentation) doit être mise à jour en cohérence.
- **`deploy.sh`** : aucun changement requis à ce spec (il relance déjà `migrate.php` avant l'envoi FTP), mais le comportement de rebuild complet doit être revérifié une fois la boucle multi-saisons en place, avant tout déploiement réel.
