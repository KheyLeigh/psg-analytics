# PSG Analytics : spécification de conception

**Date :** 20 juillet 2026
**Auteur :** Mathis Periard
**Statut :** validé, prêt pour planification

---

## 1. Contexte et objectif

### Pourquoi ce projet existe

PSG Analytics est une plateforme web d'analyse des performances du Paris Saint-Germain sur la saison 2025-26. Elle permet d'explorer le bilan collectif, les statistiques individuelles et le détail des matchs à travers une interface d'analyse moderne.

Le projet sert un double objectif : démontrer une maîtrise du développement web full stack (PHP orienté objet, architecture MVC, API REST, base relationnelle) et une capacité d'analyse de données (modélisation, agrégation, visualisation, traçabilité).

### Public visé

Recruteurs et maîtres d'apprentissage évaluant une candidature en alternance BTS SIO option SLAM. Le projet doit se lire comme un produit, pas comme un exercice.

### Critères de réussite

1. Un visiteur comprend en moins de dix secondes ce que fait la plateforme et pourquoi elle existe.
2. Le projet se lance en local en une commande, sans installer MySQL.
3. Le code résiste à une lecture attentive : séparation des couches nette, aucune fonction fourre-tout, nommage cohérent.
4. Chaque chiffre affiché peut être remonté à sa source.
5. Le README seul suffit à convaincre avant même d'ouvrir le code.

### Hors périmètre

Authentification, back-office d'administration, données multi-saisons, données multi-clubs, temps réel, framework front. Ces éléments n'ajouteraient pas de valeur démonstrative proportionnée à leur coût.

---

## 2. Le sujet : la saison 2025-26

Saison exceptionnelle et donc narrativement forte. Le PSG remporte cinq trophées sur six possibles : Ligue 1, Ligue des Champions, Trophée des Champions, Supercoupe de l'UEFA et Coupe Intercontinentale. Le sextuplé échoue sur une élimination en 32es de finale de Coupe de France.

La finale de Ligue des Champions du 30 mai 2026 se solde par un 1-1 contre Arsenal (ouverture de Kai Havertz à la 6e, égalisation d'Ousmane Dembélé sur penalty en seconde période), puis une victoire 4-3 aux tirs au but. Luis Enrique conserve le titre européen et remporte sa troisième Ligue des Champions comme entraîneur.

Ce récit structure la page d'accueil et donne au projet un angle éditorial que n'aurait pas un jeu de données neutre.

---

## 3. Stratégie de données : le modèle hybride tracé

### Le problème

Les données réelles de la saison ne sont que partiellement accessibles. Les principales sources de statistiques détaillées (FBref, WorldFootball) renvoient un HTTP 403 sur tout accès automatisé. Une couverture complète et vérifiée des 56 matchs et des 24 joueurs n'est pas atteignable.

### Le principe retenu

Plutôt que de masquer cette limite, la plateforme en fait une fonctionnalité. Chaque enregistrement statistique porte sa provenance :

- `verified` : donnée collectée sur une source identifiée, avec URL et date de collecte.
- `estimated` : donnée reconstituée par un générateur déterministe, calibré pour respecter exactement les totaux réels connus.

L'interface signale les valeurs estimées par un indicateur discret. Le README documente intégralement la méthodologie de reconstitution. Une page « Méthodologie » expose le taux de couverture vérifiée par table.

### Pourquoi ce choix

Il transforme une contrainte en argument. Le projet ne présente plus seulement des chiffres, il présente une chaîne de données avec un niveau de confiance explicite. C'est le vocabulaire et la rigueur attendus d'un profil data, et cela donne une réponse solide à la question « d'où viennent vos données ? ».

### Règles de calibration du générateur

Le générateur n'invente pas librement. Il est contraint par les totaux vérifiés :

1. La somme des buts individuels par compétition égale le total collectif vérifié.
2. La somme des buts marqués sur les matchs égale le total collectif vérifié.
3. Les minutes jouées par joueur sur un match sont bornées par la durée du match et le total de l'effectif aligné.
4. Les cartons individuels vérifiés en Ligue 1 sont respectés à l'unité près.
5. Le générateur utilise une graine fixe. Deux exécutions du seed produisent une base identique, ce qui rend les captures d'écran et le README reproductibles.

### Données vérifiées disponibles

**Bilan collectif toutes compétitions :** 56 matchs joués, 35 victoires, 12 nuls, 9 défaites, 128 buts marqués, 58 encaissés.

**Bilan Ligue 1 :** 34 matchs, 24 victoires, 4 nuls, 6 défaites, 74 buts marqués, 29 encaissés, champion.

**Palmarès :** Ligue 1 (vainqueur), Ligue des Champions (vainqueur), Trophée des Champions (vainqueur), Supercoupe UEFA (vainqueur), Coupe Intercontinentale (vainqueur), Coupe de France (éliminé en 32es).

**Effectif :** 24 joueurs avec numéro, poste et nationalité. trois gardiens (Chevalier 30, Safonov 39, Marin 89), sept défenseurs dont deux latéraux (Hakimi 2, Beraldo 4, Marquinhos 5 capitaine, Zabarnyi 6, Hernandez 21, Mendes 25, Pacho 51), sept milieux (Ruiz 8, Vitinha 17, Lee 19, Mayulu 24, Fernández 27, Zaïre-Emery 33, Neves 87), sept attaquants (Kvaratskhelia 7, Ramos 9, Dembélé 10, Doué 14, Barcola 29, Ndjantou 47, Mbaye 49). Entraîneur : Luis Enrique.

**Buteurs Ligue 1 :** Barcola 11 buts en 29 matchs, Dembélé 10 en 22, Kvaratskhelia 8 en 28, Doué 7 en 23, Ramos 6 en 30. Dembélé totalise 20 buts toutes compétitions.

**Passeurs Ligue 1 :** Vitinha 7, Dembélé 7, Nuno Mendes 5.

**Discipline Ligue 1 :** Zabarnyi 6 jaunes en 28 matchs, Hakimi 3 jaunes et 1 rouge en 18, Hernandez 4 jaunes en 25, Zaïre-Emery 3 en 32, Lee 3 en 27, Mayulu 2 en 26, Vitinha 2 en 29, Ruiz 2 en 20, Beraldo 2 en 20, Nuno Mendes 1 en 20. Les autres joueurs n'ont pris aucun carton.

**Matchs de Ligue 1 :** 23 rencontres documentées avec score et affluence, d'août 2025 à février 2026.

### Données à reconstituer

Les onze derniers matchs de Ligue 1, le détail des dix-sept matchs de Ligue des Champions, les minutes jouées par joueur, les statistiques de gardiens (arrêts, clean sheets par joueur), les buteurs hors Ligue 1, les valeurs d'expected goals, la possession moyenne, et les événements minute par minute.

### Contrainte de vérification préalable

Le formatage des scores collectés est ambigu sur certaines lignes (l'ordre domicile/extérieur n'est pas systématiquement explicite dans la source). Avant de générer les seeds, chaque score de Ligue 1 doit être recoupé une seconde fois, et le bilan résultant doit vérifier l'identité 24 victoires / 4 nuls / 6 défaites et 74 buts pour / 29 contre. Le seed échoue explicitement si l'identité n'est pas satisfaite.

---

## 4. Architecture technique

### Arborescence

```
psg-analytics/
├── index.php                      Front controller unique
├── .htaccess                      Réécriture d'URL vers index.php
├── php/
│   ├── config/
│   │   ├── config.php             Constantes, environnement
│   │   ├── database.php           Paramètres de connexion
│   │   └── routes.php             Table de routage déclarative
│   ├── core/
│   │   ├── Router.php             Résolution URL vers contrôleur
│   │   ├── Database.php           Singleton PDO, MySQL ou SQLite
│   │   ├── Controller.php         Contrôleur de base
│   │   ├── Model.php              Modèle de base
│   │   ├── Repository.php         Repository de base
│   │   ├── View.php               Moteur de rendu, échappement
│   │   ├── Request.php            Encapsulation de la requête
│   │   ├── Response.php           Réponses HTML et JSON
│   │   └── Validator.php          Validation des entrées
│   ├── models/
│   │   ├── Player.php  Match.php  Team.php
│   │   ├── Competition.php  Season.php
│   │   └── Statistic.php  Event.php  DataSource.php
│   ├── repositories/
│   │   ├── PlayerRepository.php   MatchRepository.php
│   │   ├── StatisticRepository.php  EventRepository.php
│   │   └── CompetitionRepository.php
│   ├── services/
│   │   ├── KpiService.php         Calcul des indicateurs
│   │   ├── ComparisonService.php  Comparaison de joueurs
│   │   ├── RankingService.php     Classements et tris
│   │   ├── HeatmapService.php     Agrégation spatiale
│   │   ├── CsvExporter.php        Export CSV
│   │   └── PdfExporter.php        Export PDF sans dépendance
│   ├── controllers/
│   │   ├── HomeController.php     DashboardController.php
│   │   ├── PlayerController.php   MatchController.php
│   │   ├── MethodologyController.php
│   │   └── Api/                   Contrôleurs REST
│   └── views/
│       ├── layouts/  partials/  pages/  errors/
├── assets/
│   ├── css/  (tokens, base, composants, pages)
│   ├── js/   (charts/, modules/, app.js)
│   └── images/
├── database/
│   ├── schema.mysql.sql           Schéma de référence
│   ├── schema.sqlite.sql          Miroir d'exécution locale
│   ├── seeds/                     Jeux de données verified et estimated
│   ├── migrate.php                Création et peuplement
│   └── docs/                      MCD, MLD, dictionnaire de données
├── tests/                         Tests unitaires, runner maison
├── README.md  LICENSE  .gitignore
```

### Principes structurants

**Front controller unique.** Toutes les requêtes passent par `index.php`, qui délègue au routeur. Aucun fichier PHP accessible directement en dehors de lui.

**Séparation stricte des couches.** Un contrôleur ne contient pas de SQL. Un repository ne contient pas de logique métier. Un service ne connaît pas la requête HTTP. Une vue ne fait aucun appel base. Cette règle est vérifiable à la lecture et constitue un argument d'entretien.

**Portabilité MySQL et SQLite.** `Database` expose une connexion PDO unique. Les repositories écrivent du SQL portable : pas de fonction propriétaire, requêtes préparées systématiques. MySQL reste le schéma de référence présenté, SQLite permet le lancement immédiat.

**Zéro dépendance.** Ni Composer, ni npm, ni CDN. Tout le code est lisible dans le dépôt. C'est un choix assumé et argumenté dans le README : le projet doit démontrer ce que sait faire son auteur, pas ce que savent faire ses bibliothèques.

**Fichiers courts.** Aucun fichier ne dépasse raisonnablement 250 lignes. Un fichier qui grossit signale une responsabilité mal découpée.

---

## 5. Modèle de données

### Tables

`teams` : identité des clubs (id, nom, nom court, pays, logo, est_psg).

`competitions` : compétitions disputées (id, nom, type, pays ou zone, format).

`seasons` : saisons (id, libellé, date de début, date de fin).

`players` : effectif (id, saison, numéro, prénom, nom, poste, pied fort, nationalité, date de naissance, taille, date d'arrivée, capitaine).

`matches` : rencontres (id, saison, compétition, journée ou tour, date, équipe domicile, équipe extérieur, buts domicile, buts extérieur, prolongation, tirs au but, score tab, affluence, possession psg, tirs, tirs cadrés, id source).

`player_match_stats` : ligne par joueur et par match (id, joueur, match, titulaire, minutes, buts, passes décisives, tirs, tirs cadrés, passes, précision de passe, duels gagnés, cartons jaunes, carton rouge, arrêts, buts encaissés, note, xg, xag, id source).

`events` : événements de match (id, match, joueur, joueur associé, type, minute, minute additionnelle, description).

`data_sources` : traçabilité (id, libellé, url, date de collecte, niveau de confiance, note méthodologique).

### Relations

Clés étrangères explicites avec contraintes d'intégrité référentielle. `players` vers `seasons`. `matches` vers `seasons`, `competitions` et deux fois `teams`. `player_match_stats` vers `players` et `matches`, avec contrainte d'unicité sur le couple. `events` vers `matches` et `players`. Les tables `matches` et `player_match_stats` référencent `data_sources`.

### Normalisation et performance

Modèle en troisième forme normale. Aucune statistique agrégée n'est stockée : les KPI sont calculés en SQL à la demande. Index sur toutes les clés étrangères, plus des index composites sur les colonnes de tri fréquent (buts, passes décisives, minutes) et sur la date de match.

### Documentation

Un MCD et un MLD sont produits sous forme de diagrammes Mermaid dans `database/docs/`, accompagnés d'un dictionnaire de données décrivant chaque colonne, son type, son domaine de valeurs et sa provenance.

---

## 6. API REST

### Points d'entrée

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/players` | Liste paginée, filtrable, triable |
| GET | `/api/players/{id}` | Fiche détaillée avec agrégats |
| GET | `/api/players/{id}/timeline` | Évolution match par match |
| GET | `/api/players/compare?ids=a,b` | Comparaison de deux joueurs |
| GET | `/api/matches` | Liste paginée et filtrable |
| GET | `/api/matches/{id}` | Détail avec événements et compositions |
| GET | `/api/stats/kpis` | Indicateurs globaux |
| GET | `/api/stats/distribution` | Répartition des buts par compétition et par période |
| GET | `/api/stats/heatmap` | Matrice buts par joueur et par mois |
| GET | `/api/competitions` | Compétitions et bilan par compétition |
| GET | `/api/export/players.csv` | Export CSV |
| GET | `/api/export/report.pdf` | Rapport PDF |

### Contrat de réponse

Toutes les réponses partagent une enveloppe :

```json
{
  "data": [],
  "meta": { "page": 1, "per_page": 20, "total": 24, "total_pages": 2 },
  "source": { "verified_ratio": 0.62, "generated_at": "2026-07-20T10:00:00+02:00" }
}
```

Les erreurs renvoient `{"error": {"code": "...", "message": "..."}}` avec le code HTTP approprié : 400 pour une entrée invalide, 404 pour une ressource absente, 405 pour une méthode non autorisée, 500 pour une erreur serveur. Aucune trace d'exception n'est exposée en production.

Les paramètres de requête (`page`, `per_page`, `sort`, `order`, `position`, `search`, `competition`) sont validés côté serveur avec une liste blanche. Les colonnes de tri sont contrôlées par liste blanche pour prévenir toute injection.

---

## 7. Interface et design

### Direction artistique

Minimalisme dense, dans la lignée de Stripe et Linear. Beaucoup d'espace négatif, une seule couleur d'accent, hiérarchie typographique portée par la graisse et la taille plutôt que par la couleur.

Palette : fond `#FAFBFC`, primaire `#0F172A`, accent `#2563EB`, bordures `#E2E8F0`. Police Inter, servie localement. Échelle d'espacement basée sur 4 pixels. Rayons de bordure et ombres discrets, définis comme jetons CSS.

Le mode sombre dérive des mêmes jetons via des variables CSS, avec bascule manuelle persistée en `localStorage` et respect de `prefers-color-scheme` au premier chargement.

### Pages

**Accueil.** Le récit de la saison. Titre fort, pitch en une phrase, bandeau des cinq trophées, KPI clés, cinq derniers matchs, aperçu du top buteurs, présentation de la stack et lien vers la méthodologie.

**Dashboard.** Vue analytique complète. Cartes KPI (top buteur, top passeur, victoires, nuls, défaites, clean sheets, possession moyenne, buts par match). Courbe de forme sur la saison, répartition des buts par compétition, buts par période de quinze minutes, heatmap buteurs par mois, bilan par compétition.

**Joueurs.** Grille ou tableau commutable. Recherche instantanée côté client sur le jeu chargé, filtres par poste et nationalité, tri dynamique sur toutes les colonnes, pagination. Sélection de deux joueurs pour ouvrir le comparateur.

**Fiche joueur.** Identité, saison en chiffres, radar de profil sur six axes normalisés, courbe de contribution match par match, historique détaillé, part dans le collectif.

**Matchs.** Liste filtrable par compétition et par résultat, avec fiche de match détaillée : score, événements chronologiques, statistiques d'équipe, joueurs alignés.

**Méthodologie.** Sources, taux de couverture vérifiée par table, règles de calibration du générateur, limites assumées.

### Moteur de visualisation

Bibliothèque SVG maison, sans dépendance, découpée en modules : `bar.js`, `line.js`, `radar.js`, `donut.js`, `heatmap.js`, plus un socle commun `scale.js` et `axis.js` gérant échelles, axes et mise en page. Chaque graphique est responsive, accessible (rôle et description textuelle), animé à l'apparition par transition CSS, et propose une infobulle au survol.

### Responsive et accessibilité

Approche mobile d'abord, points de rupture à 640, 1024 et 1280 pixels. Navigation au clavier complète, contrastes conformes WCAG AA, attributs ARIA sur les composants interactifs, respect de `prefers-reduced-motion`.

---

## 8. Qualité et vérification

### Conventions

PSR-12, typage strict activé, commentaires en français expliquant l'intention et non la syntaxe. Docblocks sur toutes les méthodes publiques. Fonctions courtes et à responsabilité unique.

### Sécurité

Requêtes préparées systématiques, échappement de toute sortie via `htmlspecialchars`, listes blanches sur les paramètres de tri et de filtre, en-têtes de sécurité, aucune donnée sensible en dépôt, `.gitignore` couvrant la configuration locale et la base SQLite générée.

### Tests

Runner de tests maison en PHP, sans dépendance. Couverture ciblée sur ce qui porte du risque : services de calcul de KPI, comparateur, validateur, exporteurs, et cohérence des seeds (les identités de totaux décrites en section 3 sont testées).

### Vérification avant livraison

Le projet est lancé via le serveur PHP intégré, chaque page est ouverte et inspectée dans le navigateur, les erreurs console et réseau sont contrôlées, le rendu est vérifié en clair, en sombre et en mobile. Des captures réelles sont produites pour le README. Aucune fonctionnalité n'est déclarée terminée sans avoir été observée en fonctionnement.

---

## 9. README

Structure : titre et pitch, captures, pourquoi ce projet, fonctionnalités, architecture, modèle de données avec diagramme, stack et justification des choix, installation en une commande, méthodologie des données, structure du projet, choix techniques argumentés, tests, améliorations futures, licence.

Le README doit tenir debout seul. Un recruteur qui ne lit que lui doit comprendre le périmètre, l'architecture et le niveau de rigueur.

---

## 10. Décisions actées

| Sujet | Décision | Raison |
|---|---|---|
| Base locale | MySQL de référence, SQLite miroir | Lancement immédiat sans installation |
| Données | Hybride tracé verified / estimated | Honnêteté et argument data en entretien |
| Graphiques | Moteur SVG maison | Cohérence avec la stack annoncée, valeur démonstrative |
| Dépendances | Aucune | Le projet démontre ce que sait faire son auteur |
| Périmètre | Profondeur analytique, qualité backend, finition design, documentation | Les quatre axes retenus par l'auteur |
| Saison | 2025-26 uniquement | Un récit fort vaut mieux qu'un volume vide |
