# Automatisation de la collecte 2026-27 : spécification de conception

**Date :** 8 septembre 2026
**Auteur :** Mathis Periard
**Statut :** validé, prêt pour planification

---

## 1. Contexte et objectif

### Pourquoi ce spec existe

Les fondations multi-saisons (spec et plan du 7 septembre 2026, mergés sur `main`) permettent à la saison 2025-26 (terminée) et à la saison 2026-27 (en cours, structure vide) de coexister en base. La saison 2025-26 a été saisie entièrement à la main : Mathis allait chercher les tableaux sur FBref et Understat via un navigateur, les recopiait dans des fichiers seed PHP (`database/seeds/verified/2025-26/*.php`), puis relançait `database/migrate.php`.

Ce spec automatise ce travail pour la saison 2026-27, au fil des matchs joués, sans qu'une session manuelle soit nécessaire chaque semaine.

### Critères de réussite

1. Une tâche planifiée hebdomadaire ajoute automatiquement les matchs joués depuis le dernier passage, avec leurs statistiques individuelles, dans `verified/2026-27/`.
2. Chaque donnée insérée reste marquée vérifiée avec sa source et sa date de relevé : rien n'est jamais inventé.
3. Un changement de structure (effectif, compétitions) est détecté et signalé, jamais écrit automatiquement.
4. `php database/migrate.php` et `php tests/run.php` passent après chaque exécution ; en cas d'échec, aucun commit n'est créé.
5. Mathis reçoit une notification résumant ce qui s'est passé à chaque passage.
6. Aucune automatisation ne touche à `./deploy.sh` ni ne saisit de mot de passe FTP.

### Hors périmètre

- Déploiement automatique du site (`./deploy.sh` reste une action manuelle de Mathis).
- Correction automatique de l'effectif ou du catalogue de compétitions (signalement seul, voir section 6).
- Toute troisième source de données au-delà de FBref et Understat.
- Toute forme de saisie ou de stockage du mot de passe FTP par Claude.

---

## 2. Déclenchement

Une tâche planifiée Claude Code (skill `schedule`), en cron hebdomadaire (ex. `"0 8 * * 1"`, lundi 8h heure locale), avec un prompt entièrement autonome : chaque exécution démarre sans mémoire d'une conversation précédente, donc le prompt de la tâche doit contenir tout le contexte nécessaire (formats de fichiers, règles de non-écriture, contenu du résumé attendu).

Les tâches planifiées Claude Code tournent pendant que l'application est ouverte ; si elle est fermée au moment prévu, l'exécution a lieu au prochain lancement. Ce délai éventuel est accepté : Mathis ouvre son ordinateur chaque lundi matin (cours), ce qui rend le décalage sans conséquence pratique pour une mise à jour hebdomadaire.

---

## 3. Sources et contournement de Cloudflare

FBref est protégé par Cloudflare : un accès HTTP direct (`curl`, requête programmatique simple) échoue. La tâche pilote donc un vrai navigateur (mêmes outils de navigation que dans une session Claude Code interactive) pour consulter :

- FBref : calendrier et résultats de la saison, tableau de statistiques standard Ligue 1 par joueur, tirs par compétition.
- Understat : tirs individuels avec xG/xAG, comme pour 2025-26.

Si un site est inaccessible, bloqué, ou si la structure d'une page ne correspond plus à ce qui est attendu, la tâche n'écrit aucune donnée cette semaine-là et le signale explicitement dans sa notification, plutôt que de risquer une écriture incorrecte. Une semaine sans mise à jour n'est jamais grave : le prochain passage rattrapera le retard.

---

## 4. Détection des nouveautés : comparaison aux seeds existants, sans état séparé

La tâche ne maintient aucun fichier d'état type « dernier match vu ». À chaque passage, elle relit l'intégralité du calendrier FBref de la saison 2026-27 et le compare aux entrées déjà présentes dans `verified/2026-27/matches_l1.php` et `matches_other.php` (rapprochement par journée, adversaire et date). Seuls les matchs réellement joués et absents des seeds sont ajoutés. Cette approche est idempotente : rejouer la tâche sur une semaine déjà traitée n'insère rien en double.

---

## 5. Écriture des données vérifiées

Les formats de fichiers restent strictement identiques à ceux déjà utilisés pour 2025-26 (voir `database/seeds/verified/2025-26/*.php` pour référence exacte) :

- **`matches_l1.php`** : ajoute une ligne par nouveau match de Ligue 1, format tuple `[round_label, date, adversaire, est_domicile, buts_psg, buts_adv, affluence, possession]`.
- **`matches_other.php`** : idem pour les matchs hors Ligue 1 (Ligue des Champions, coupes), format tuple existant à 12 champs.
- **`players_l1_fbref.php`** : mise à jour des totaux exacts par joueur (matchs, minutes, buts, passes, cartons, tirs, tirs cadrés, tacles, interceptions), au fur et à mesure que la saison avance. Ce fichier est réécrit dans son intégralité à chaque passage (les totaux FBref sont cumulatifs par nature), pas complété ligne par ligne.
- **`player_season.php`** : bilan toutes compétitions par joueur, même traitement.
- **`verified/2026-27/understat-shots-2026.json`** et **`verified/2026-27/fbref-shots-by-competition-2026.json`** : nouveaux fichiers (n'existent pas encore), produits par la tâche au même format que leurs équivalents 2025-26, alimentant la carte de tirs de la fiche joueur.
- **`sources.php`** (catalogue global, partagé entre saisons) : le champ `collected_at` des sources `fbref`, `fbref_players_l1` et `understat` est mis à jour à la date du relevé de la semaine en cours. Ce catalogue ne porte qu'une date de dernier relevé par source, pas un historique par semaine.

Chaque écriture respecte le principe déjà en vigueur pour 2025-26 : les totaux de saison sont vérifiés, leur répartition match par match reste déterministe/estimée via le `StatGenerator` existant (aucun changement à cette mécanique).

---

## 6. Détection de changements de structure : signalement seul

À chaque passage, la tâche compare :

- L'effectif FBref actuel à `verified/2026-27/players.php`.
- Les compétitions rencontrées dans le calendrier au catalogue partagé `competitions.php`.

Tout écart (joueur absent de FBref mais présent dans nos seeds, joueur FBref inconnu de nos seeds, compétition non répertoriée) est listé dans le résumé de notification, avec le contexte disponible (nom, numéro, ou nom de compétition). La tâche n'écrit jamais elle-même dans `players.php` ou `competitions.php` : ces fichiers restent édités à la main par Mathis, comme pour 2025-26. Ce choix évite qu'une absence temporaire (blessure, non-convocation) soit prise à tort pour un départ du club.

---

## 7. Correctif compagnon : chemins de shotmap paramétrés par saison

`PlayerController::shotmap()` et `PlayerController::shotsByCompetition()` (méthodes existantes, non touchées par les fondations multi-saisons) construisent aujourd'hui un chemin de fichier codé en dur : `verified/understat-shots-2025.json` et `verified/fbref-shots-by-competition-2025.json`, sans référence à la saison consultée. Tant qu'aucun joueur 2026-27 n'a de statistiques de match, ce n'est pas visible : ces méthodes renvoient toujours les données 2025-26, quelle que soit la saison affichée.

Une fois ce spec implémenté, un joueur 2026-27 aura un `player_id` distinct qui, par coïncidence numérique, pourrait correspondre à une entrée du fichier JSON 2025-26 : sa fiche afficherait alors, sans aucun avertissement, la carte de tirs d'un joueur totalement différent. C'est exactement le type de donnée fausse affichée silencieusement que la traçabilité du projet est censée exclure.

Correctif inclus dans ce spec : les deux méthodes construisent le chemin à partir de la clé de saison du joueur consulté (`verified/{seasonKey}/understat-shots-{année}.json`, `verified/{seasonKey}/fbref-shots-by-competition-{année}.json`), avec repli à `null` (section masquée, comportement déjà existant) si le fichier n'existe pas encore pour cette saison. Les fichiers 2025-26 sont déplacés dans `verified/2025-26/` par cohérence avec le reste des seeds déjà réorganisés par saison.

---

## 8. Garde-fous avant tout commit

Après avoir écrit les fichiers seeds concernés, la tâche exécute dans l'ordre :

1. `php database/migrate.php` : reconstruit la base depuis les seeds. Le garde-fou générique déjà en place (buts individuels ne dépassant jamais les buts d'équipe, pas de fuite inter-saisons) s'applique sans modification.
2. `php tests/run.php` : doit rester intégralement vert.

Si l'une de ces deux étapes échoue, la tâche **n'effectue aucun commit** : les fichiers modifiés restent en l'état dans l'arbre de travail, et l'échec est signalé en tête de la notification (avec le message d'erreur précis), pour que Mathis puisse diagnostiquer au calme plutôt que de découvrir un historique git pollué par une tentative ratée.

---

## 9. Commit et notification

**Commit :** un commit git **local uniquement** (jamais poussé vers `origin`) par passage réussi, avec un message explicite listant les matchs ajoutés, les fichiers de statistiques mis à jour, la source et la date de relevé.

**Notification :** une notification push Claude Code à la fin de chaque exécution, résumant :
- Les matchs ajoutés depuis le dernier passage (score, compétition, date).
- Les statistiques individuelles mises à jour.
- Les écarts d'effectif ou de compétitions détectés (section 6), le cas échéant.
- Le résultat des garde-fous (section 8) : succès et commit créé, ou échec et rien committé.
- Si aucune source n'était accessible cette semaine : le signaler explicitement plutôt que de laisser croire à un passage silencieux et réussi.

Aucune étape de ce processus ne déclenche `./deploy.sh` ni ne demande, stocke ou saisit un mot de passe FTP. Le déploiement reste une décision et une action manuelles de Mathis, après qu'il ait consulté la notification et, s'il le souhaite, relu le commit local.

---

## 10. Tests

- Le correctif de la section 7 (chemins de shotmap par saison) est couvert par un test unitaire vérifiant que `buildDetail()` construit le bon chemin pour une saison donnée et renvoie `null` sans erreur si le fichier n'existe pas.
- La logique de détection des nouveautés (section 4) et de détection d'écarts de structure (section 6) est testée unitairement sur des jeux de données synthétiques (seeds existants vs calendrier/effectif simulé), indépendamment de tout accès réseau réel à FBref/Understat.
- `php tests/run.php` doit rester intégralement vert après implémentation.

Le contenu précis de la tâche planifiée elle-même (le prompt autonome exécuté chaque semaine) n'est pas un test automatisé au sens de la suite PHP : sa fiabilité repose sur les garde-fous de la section 8, qui eux sont testés.

---

## 11. Risques et points de vigilance

- **Fragilité du scraping** : FBref ou Understat peuvent changer la structure de leurs pages à tout moment, ce qui casserait silencieusement la lecture. Le garde-fou générique (section 8) attrape les incohérences arithmétiques, mais pas une page mal interprétée qui produirait des valeurs plausibles mais fausses. Un contrôle humain périodique (Mathis relit les commits locaux de temps en temps) reste recommandé, au moins pendant les premières semaines.
- **Cloudflare** : même via un navigateur piloté, un blocage ponctuel reste possible. La tâche doit échouer proprement (section 3) plutôt que de forcer un contournement agressif.
- **`sources.php` est un catalogue global** : mettre à jour `collected_at` chaque semaine écrase la date précédente. Si l'historique exact des dates de relevé importe un jour, une évolution de schéma serait nécessaire (hors périmètre ici).
- **Fichier `players_l1_fbref.php` réécrit en entier** : contrairement aux fichiers de matchs (complétés), ce fichier est régénéré à chaque passage à partir des totaux cumulatifs FBref. Une erreur de lecture un mois donné écraserait un total correct par un total faux, sans conserver de trace de la version précédente au-delà de l'historique git (déjà atténué par le commit local systématique de la section 9, qui permet de revenir en arrière).
