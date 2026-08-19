# PSG Analytics

Tableau de bord de la saison 2025-2026 du Paris Saint-Germain : dashboard, effectif, matchs et fiches joueurs, adossés à des données réelles et tracées.

La signature du projet est la **traçabilité**. Chaque chiffre affiché porte sa provenance : une donnée **vérifiée** (pastille pleine verte) vient d'une source de référence recoupée ; une donnée **estimée** (anneau creux ambre) est une reconstitution déterministe assumée comme telle. Un mode Transparence met en évidence toutes les données estimées d'un coup d'œil. L'information est portée par la forme autant que par la couleur, pour rester lisible sans distinction chromatique.

## Stack

Zéro dépendance, zéro étape de build. Tout est écrit à la main pour rester maîtrisable et durable.

- **PHP 8.1 ou plus récent**, sans framework : une couche MVC maison (`php/core`).
- **SQLite** en local (fichier `database/psg.sqlite`), **MySQL** en production. Les deux dialectes sont gérés par le schéma de migration.
- **JavaScript vanilla** pour l'amélioration progressive (recherche, tri, comparateur, hydratation des graphiques). Les pages restent utilisables sans JavaScript.
- **Graphiques SVG** rendus par un moteur maison (`assets/js/charts`) : barres, courbe, donut, radar, heatmap. Aucune librairie de dataviz.
- **Polices** locales : Archivo Black pour les titres, Saira italique pour les chiffres. Aucun appel réseau externe.

## Données

La base est peuplée à partir de sources vérifiées (`database/seeds/verified/`), principalement des exports FBref de la saison 2025-2026 :

- **55 matchs** toutes compétitions (Ligue 1, Ligue des Champions, Coupe de France, Trophée des Champions, Supercoupe), avec score, possession, affluence et lieu réels.
- **Statistiques par joueur en Ligue 1** exactes (buts, passes décisives, minutes, titularisations, cartons).
- **Bilans joueurs toutes compétitions** pour l'effectif de champ.

L'attribution match par match des statistiques individuelles est **estimée** de façon déterministe (les totaux de saison, eux, sont vérifiés). La migration échoue si les identités vérifiées de la saison de Ligue 1 ne sont pas respectées par les données réellement insérées (24 victoires, 4 nuls, 6 défaites, 74 buts pour, 29 contre), ce qui garantit qu'aucune dérive silencieuse ne s'installe.

## Lancer le projet

Prérequis : PHP 8.1 ou plus récent (extension PDO SQLite incluse dans la distribution standard).

```bash
# 1. Construire et peupler la base (recrée le schéma à chaque fois)
php database/migrate.php

# 2. Servir l'application
php -S 127.0.0.1:8077
```

Puis ouvrir http://127.0.0.1:8077.

La migration affiche un récapitulatif de contrôle, par exemple :

```
matches: 55, players: 24, L1: 24V 4N 6D (74-29) OK
```

## Pages

| Route | Description |
| --- | --- |
| `/` | Accueil éditorial : la promesse de traçabilité, les chiffres clés, les portes d'entrée. |
| `/dashboard` | Indicateurs de la saison et graphiques (points cumulés, répartition des buts, buteurs, heatmap). |
| `/joueurs` | Navigateur d'effectif : tri, filtre par poste, pagination et comparateur radar. |
| `/joueurs/{id}` | Fiche joueur : identité, totaux de saison, radar de profil, courbe de contribution. |
| `/matchs` | Liste filtrable par compétition et par résultat. |
| `/matchs/{id}` | Fiche match : score, statistiques vérifiées, résultat aux tirs au but le cas échéant. |
| `/methodologie` | Taux de vérification par table, liste des sources et exports. |
| `/styleguide` | Laboratoire du design system (composants, thèmes, traçabilité). |

## API REST

L'API sert des réponses JSON avec une enveloppe `data` / `meta` / `source`. Endpoints principaux :

- `GET /api/players`, `GET /api/players/{id}`, `GET /api/players/{id}/timeline`, `GET /api/compare`
- `GET /api/kpis`, `GET /api/distribution`, `GET /api/heatmap`
- `GET /api/matches`, `GET /api/matches/{id}`
- `GET /api/competitions`
- `GET /api/export/players.csv`, `GET /api/export/report.pdf`

Les paramètres de tri, de filtre et de pagination passent par des listes blanches strictes côté serveur.

## Architecture

```
index.php                 Front controller (une seule entrée, résolution de route)
php/
  core/                   Couche MVC maison (Router, Request, Response, Controller, View, Validator, Database)
  config/                 Configuration, base de données, table de routage
  models/                 Modèles de domaine (readonly, fromRow)
  repositories/           Accès aux données (requêtes préparées, agrégats SQL)
  services/               Logique métier (KPI, comparaison, classement, heatmap, exports)
  controllers/            Contrôleurs de pages et sous-dossier api/ pour l'API REST
  views/                  Gabarits (layouts), pages et partials
assets/
  css/                    Design system Matchday (tokens, base, layout, composants, charts, pages)
  js/                     Amélioration progressive et moteur de graphiques SVG
  fonts/                  Archivo Black et Saira (woff2 locaux)
database/
  migrate.php             Recrée le schéma et peuple la base
  seeds/                  Générateurs et données, dont seeds/verified/ (sources de vérité)
tests/                    Suite de tests maison (unitaires et navigateur)
```

## Tests

```bash
php tests/run.php
```

La suite couvre les contrôleurs, les repositories et les services (assemblage des données, listes blanches respectées, agrégats corrects, cas limites), sans dépendance externe.

## Design system

Direction artistique **Matchday**, sombre par défaut, avec une variante claire cohérente (bascule persistée). Bleu nuit profond comme toile, rouge PSG réservé à l'identité, or réservé au jalon de champion. Titres en Archivo Black, chiffres en Saira italique façon numéro de maillot. Le système de traçabilité vérifié / estimé est un composant de première classe, décliné sur toutes les pages.
