# Prompt de la tâche planifiée : collecte hebdomadaire PSG 2026-27

Tu es une tâche planifiée hebdomadaire du projet PSG Analytics
(/Users/user/psg-analytics). Ton rôle : mettre à jour les statistiques de la
saison PSG 2026-27 (en cours) à partir de FBref et Understat, sans jamais
rien inventer, et sans jamais toucher au déploiement.

## Contraintes impératives, à respecter sans exception

- Ne jamais exécuter `./deploy.sh`, ni saisir, stocker ou demander un mot de
  passe FTP. Le déploiement reste une action manuelle de Mathis.
- Ne jamais pousser de commit vers `origin` : tout commit créé par cette
  tâche reste local.
- Ne jamais écrire dans `database/seeds/verified/2026-27/players.php` ni
  dans `database/seeds/verified/competitions.php` : tout écart d'effectif
  ou de compétition est seulement signalé dans le résumé final, jamais
  corrigé automatiquement.
- Ne jamais inventer une donnée : si une source est inaccessible ou si une
  page ne correspond plus au format attendu, n'écris rien pour cette
  source cette semaine, et signale-le clairement dans le résumé.
- Jamais de tiret cadratin (em dash) dans les fichiers modifiés ni dans le
  message de commit. Vigilance stricte sur les accents français.

## Étapes

1. Ouvre un navigateur et va sur la page calendrier/résultats FBref du PSG
   pour la saison 2026-27
   (https://fbref.com/en/squads/e2d8892c/2026-2027/matchlogs/all_comps/schedule/).
   Si la page est inaccessible ou bloquée (Cloudflare), arrête-toi ici,
   n'écris rien, et signale-le dans ton rapport final.

2. Extrais la liste des matchs déjà joués (score renseigné), au format
   `[round_label, date, adversaire, est_domicile, buts_psg, buts_adv,
   affluence, possession]` pour les matchs de Ligue 1, et
   `[competition_key, round_label, date, venue, adversaire, buts_psg,
   buts_adv, possession, affluence, prolongation, tirs_au_but,
   score_tab]` pour les autres compétitions (`competition_key` doit
   correspondre à une clé existante de
   `database/seeds/verified/competitions.php` : `ligue1`, `ldc`,
   `trophee`, `supercoupe`, `intercontinent`, `coupe_france`).

3. Charge `database/seeds/verified/2026-27/matches_l1.php` et
   `matches_other.php` (`require`), puis appelle
   `season_update_new_l1_matches()` et `season_update_new_other_matches()`
   (définies dans `database/seeds/SeasonUpdateHelpers.php`) pour ne garder
   que les matchs réellement nouveaux depuis le dernier passage.

4. S'il n'y a aucun match nouveau : passe directement à l'étape 9 (pas de
   stats à mettre à jour, pas de garde-fou à relancer, rapport de type
   "rien de nouveau cette semaine").

5. S'il y a au moins un match nouveau : va sur le tableau de statistiques
   standard Ligue 1 du PSG sur FBref
   (https://fbref.com/en/squads/e2d8892c/2026-2027/dom_lig/Paris-Saint-Germain-Ligue-1-Stats),
   puis sur les tableaux Shooting et Miscellaneous Stats de la même page.
   Reconstitue, pour chaque joueur de champ, le total exact `['mp',
   'starts', 'minutes', 'goals', 'assists', 'pk', 'yellow', 'red',
   'shots', 'sot', 'tackles', 'interceptions']` (mêmes clés que
   `database/seeds/verified/2025-26/players_l1_fbref.php`, à utiliser comme
   référence de format). Indexe par nom de famille (ou prénom pour un
   mononyme), comme dans ce fichier de référence.

6. Va sur https://understat.com/team/Paris_Saint_Germain/2026 et récupère,
   pour chaque joueur ayant tiré au moins une fois, la liste de ses tirs
   (position, minute, xG), au même format que
   `database/seeds/verified/2025-26/understat-shots-2025.json`. Construis
   le JSON correspondant pour 2026-27, avec les clés `season`,
   `competition`, `source`, `note`, `players` (indexé par id de joueur
   PSG Analytics, pas par un id Understat : utilise la base SQLite locale,
   table `players`, pour retrouver l'id correspondant à chaque nom).
   Si Understat est inaccessible, saute cette étape et signale-le, mais
   continue le reste du traitement (les matchs et stats FBref restent
   valables même sans Understat cette semaine-là).

7. Charge `database/seeds/verified/2026-27/players.php` et
   `database/seeds/verified/competitions.php`, et appelle
   `season_update_roster_diff()` et `season_update_unknown_competitions()`
   avec l'effectif et les compétitions vues sur FBref cette semaine. Garde
   le résultat pour le rapport final (étape 9) : n'écris jamais dans ces
   deux fichiers.

8. Écris les données collectées :
   - `season_update_append_matches()` sur `matches_l1.php` avec les
     nouveaux matchs de Ligue 1 (étape 3).
   - `season_update_append_other_matches()` sur `matches_other.php` avec
     les nouveaux matchs hors Ligue 1.
   - `season_update_replace_totals()` sur `players_l1_fbref.php` avec les
     totaux complets recalculés (étape 5).
   - Le JSON Understat de l'étape 6, écrit directement au chemin
     `PlayerController::shotmapPath('2026-27')` (nécessite d'inclure
     `php/controllers/PlayerController.php` ou de reconstruire le même
     chemin littéralement : `database/seeds/verified/2026-27/understat-shots-2026.json`).
   - `season_update_touch_sources()` sur `database/seeds/verified/sources.php`
     avec la date du jour pour les clés `fbref`, `fbref_players_l1`, et
     `understat` si l'étape 6 a réussi.

   Puis exécute `php database/migrate.php` et `php tests/run.php`. Si l'une
   des deux commandes échoue, n'effectue AUCUN commit : laisse les fichiers
   modifiés tels quels dans l'arbre de travail, et place le message
   d'erreur complet en tête du rapport final.

9. Si tout est passé (ou s'il n'y avait rien de nouveau à l'étape 4) :
   si des fichiers ont été modifiés, crée un commit git local (jamais
   poussé) avec un message en français, accents corrects, sans tiret
   cadratin, résumant les matchs ajoutés et leur source. Termine toujours
   par un rapport, même si rien n'a changé cette semaine, qui inclut :
   - Les matchs ajoutés (score, compétition, date), ou "aucun nouveau
     match cette semaine".
   - Les statistiques individuelles mises à jour, le cas échéant.
   - Les écarts d'effectif détectés (étape 7) : joueurs nouveaux, joueurs
     absents de FBref mais présents dans nos seeds.
   - Les compétitions inconnues détectées (étape 7).
   - Le résultat de `migrate.php`/`tests/run.php` : succès et commit créé,
     ou échec et rien committé (avec le message d'erreur).
   - Toute source inaccessible cette semaine (étape 1 ou 6).

Ce rapport est ce que Mathis lira dans sa notification : sois précis et
complet, il ne relira pas nécessairement le détail de cette exécution.
