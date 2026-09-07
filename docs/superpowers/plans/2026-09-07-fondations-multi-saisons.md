# Fondations multi-saisons Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire cohabiter proprement la saison 2025-26 (terminée, en production) et la saison 2026-27 (à venir) dans PSG Analytics, sans automatisation de collecte à ce stade.

**Architecture:** Schéma additif (nouvelle table `people`, colonnes `seasons.is_current` et `players.person_id`), seeds réorganisés en un sous-dossier par saison, `Migrator` généralisé en boucle multi-saisons avec un garde-fou générique toujours actif et des totaux figés optionnels par saison, puis propagation explicite d'un `season_id` résolu une fois par page (jamais d'état global) à travers les repositories, services et contrôleurs.

**Tech Stack:** PHP 8.1+ sans framework, SQLite (tests et local) / MySQL (prod), suite de tests maison (`tests/run.php`), zéro dépendance.

## Global Constraints

- Ne jamais utiliser le tiret cadratin (em dash), dans aucun fichier ni aucun message de commit.
- Vigilance stricte sur les accents français : jamais de caractères ASCII à la place d'accents, y compris dans les messages de commit.
- Toute donnée automatiquement récupérée doit être marquée « vérifiée » avec sa source et sa date de relevé ; ce plan ne récupère aucune donnée automatiquement (hors périmètre, spec séparé).
- `php tests/run.php` doit rester intégralement vert après chaque tâche.
- Ne jamais exécuter `./deploy.sh` ni saisir de mot de passe FTP.
- La saison 2025-26 déjà en base ne doit jamais être modifiée ni supprimée par ce travail.
- Chaque changement de schéma ou d'architecture déjà validé dans `docs/superpowers/specs/2026-09-07-fondations-multi-saisons-design.md` ; ne pas dévier sans repasser par l'utilisateur.
- Commits fréquents, un par tâche, message en français avec accents corrects.

---

### Task 1: Schéma, `is_current` sur `seasons` + modèle `Season`

**Files:**
- Modify: `database/schema.sqlite.sql`
- Modify: `database/schema.mysql.sql`
- Modify: `php/models/Season.php`
- Modify: `database/seeds/verified/seasons.php`
- Modify: `database/seeds/Migrator.php:25-30` (insertion de la saison)
- Test: `tests/unit/SeasonModelTest.php` (nouveau)

**Interfaces:**
- Produces: `Season::$isCurrent` (bool), `Season::fromRow(array $r): self` accepte désormais une clé `is_current`.

- [ ] **Step 1: Écrire le test du modèle (échoue : colonne absente)**

Créer `tests/unit/SeasonModelTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie que Season expose bien is_current depuis une ligne de base.
final class SeasonModelTest extends TestCase
{
    public function testFromRowExposeIsCurrent(): void
    {
        $courante = Season::fromRow(['id' => 1, 'label' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => 1]);
        $ancienne = Season::fromRow(['id' => 2, 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => 0]);

        $this->assertTrue($courante->isCurrent, 'is_current=1 -> true');
        $this->assertTrue($ancienne->isCurrent === false, 'is_current=0 -> false');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL sur `SeasonModelTest::testFromRowExposeIsCurrent` (`Undefined array key "is_current"` ou propriété inexistante).

- [ ] **Step 3: Ajouter la colonne aux deux schémas**

Dans `database/schema.sqlite.sql`, table `seasons` :

```sql
CREATE TABLE seasons (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    label       TEXT NOT NULL,
    start_date  TEXT NOT NULL,
    end_date    TEXT NOT NULL,
    is_current  INTEGER NOT NULL DEFAULT 0
);
```

Dans `database/schema.mysql.sql`, table `seasons` :

```sql
CREATE TABLE seasons (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    is_current  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Mettre à jour le modèle `Season`**

Remplacer le contenu de `php/models/Season.php` :

```php
<?php
declare(strict_types=1);
// Représente une saison sportive.
final class Season
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly bool $isCurrent,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (string) $r['label'], (string) $r['start_date'], (string) $r['end_date'],
            (bool) $r['is_current'],
        );
    }
}
```

- [ ] **Step 5: Mettre à jour le seed `seasons.php` et son insertion**

Remplacer `database/seeds/verified/seasons.php` :

```php
<?php
declare(strict_types=1);
// Saisons couvertes par le projet. Une seule porte is_current à la fois (vérifié
// par le Migrator). 2025-26 : saison terminée, cinq trophées. 2026-27 : saison en
// cours, structure vide pour l'instant (voir verified/2026-27/).
return [
    ['key' => '2025-26', 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => false],
];
```

Note : une seule saison pour l'instant (2025-26) ; 2026-27 est ajoutée à la Task 7 une fois la boucle multi-saisons posée (Task 5). Modifier `database/seeds/Migrator.php`, fonction `migrator_seed_reference` (lignes 25-30), pour insérer `is_current` :

```php
    $season = (require __DIR__ . '/verified/seasons.php')[0];
    $stmt = $pdo->prepare('INSERT INTO seasons (label, start_date, end_date, is_current) VALUES (?, ?, ?, ?)');
    $stmt->execute([$season['label'], $season['start_date'], $season['end_date'], (int) $season['is_current']]);
    $seasonId = (int) $pdo->lastInsertId();
```

- [ ] **Step 6: Lancer la suite pour confirmer que tout passe**

Run: `php tests/run.php`
Expected: `OK` (tous les tests existants + le nouveau `SeasonModelTest`).

- [ ] **Step 7: Commit**

```bash
git add database/schema.sqlite.sql database/schema.mysql.sql php/models/Season.php database/seeds/verified/seasons.php database/seeds/Migrator.php tests/unit/SeasonModelTest.php
git commit -m "$(cat <<'EOF'
feat(schema): ajoute is_current sur seasons

Prépare la notion de saison courante, socle du sélecteur de saison a
venir. Une seule saison existe encore a ce stade (2025-26).
EOF
)"
```

---

### Task 2: Schéma, table `people` + `person_id` sur `players`

**Files:**
- Modify: `database/schema.sqlite.sql`
- Modify: `database/schema.mysql.sql`
- Modify: `php/models/Player.php`
- Modify: `database/seeds/Migrator.php` (fonction `migrator_seed_players`)
- Test: `tests/unit/PlayerModelTest.php` (existant, à étendre)

**Interfaces:**
- Consumes: rien de nouveau.
- Produces: `Player::$personId` (int), colonne `people.id` utilisée par la Task 6 (résolution `person_id`) et la Task 9 (`seasonsForPerson`).

- [ ] **Step 1: Lire le test existant pour respecter son style**

Lire `tests/unit/PlayerModelTest.php` avant modification (déjà lu dans ce plan : vérifie `fullName()`/`initials()` depuis `fromRow`).

- [ ] **Step 2: Étendre le test (échoue : personId absent)**

Ajouter à `tests/unit/PlayerModelTest.php` une méthode :

```php
    public function testFromRowExposePersonId(): void
    {
        $p = Player::fromRow([
            'id' => 1, 'season_id' => 1, 'person_id' => 42, 'shirt_number' => 29,
            'first_name' => 'Bradley', 'last_name' => 'Barcola', 'position' => 'FW',
            'detailed_position' => 'LW', 'foot' => 'right', 'nationality' => 'France',
            'birth_date' => null, 'height_cm' => 182, 'is_captain' => 0,
        ]);
        $this->assertSame(42, $p->personId);
    }
```

- [ ] **Step 3: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL sur `PlayerModelTest::testFromRowExposePersonId`.

- [ ] **Step 4: Ajouter la table `people` et la colonne `person_id` aux deux schémas**

Dans `database/schema.sqlite.sql`, ajouter avant `CREATE TABLE players` :

```sql
CREATE TABLE people (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name  TEXT NOT NULL,
    last_name   TEXT NOT NULL
);
```

Puis dans la table `players`, ajouter la colonne juste après `season_id` :

```sql
CREATE TABLE players (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id    INTEGER NOT NULL REFERENCES seasons(id),
    person_id    INTEGER NOT NULL REFERENCES people(id),
    shirt_number INTEGER,
    first_name   TEXT NOT NULL,
    last_name    TEXT NOT NULL,
    position     TEXT NOT NULL CHECK (position IN ('GK','DF','MF','FW')),
    detailed_position TEXT,
    foot         TEXT CHECK (foot IN ('left','right','both')),
    nationality  TEXT NOT NULL,
    birth_date   TEXT,
    height_cm    INTEGER,
    is_captain   INTEGER NOT NULL DEFAULT 0
);
```

Dans `database/schema.mysql.sql`, ajouter avant `CREATE TABLE players` :

```sql
CREATE TABLE people (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Puis dans la table `players` MySQL, ajouter la colonne et sa contrainte :

```sql
CREATE TABLE players (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    season_id         INT NOT NULL,
    person_id         INT NOT NULL,
    shirt_number      INT,
    first_name        VARCHAR(100) NOT NULL,
    last_name         VARCHAR(100) NOT NULL,
    position          ENUM('GK','DF','MF','FW') NOT NULL,
    detailed_position VARCHAR(50),
    foot              ENUM('left','right','both'),
    nationality       VARCHAR(100) NOT NULL,
    birth_date        DATE,
    height_cm         INT,
    is_captain        TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (season_id) REFERENCES seasons(id),
    FOREIGN KEY (person_id) REFERENCES people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 5: Mettre à jour le modèle `Player`**

Dans `php/models/Player.php`, ajouter `personId` juste après `seasonId` dans le constructeur et dans `fromRow` :

```php
    public function __construct(
        public readonly int $id,
        public readonly int $seasonId,
        public readonly int $personId,
        public readonly ?int $shirtNumber,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $position,
        public readonly ?string $detailedPosition,
        public readonly ?string $foot,
        public readonly string $nationality,
        public readonly ?string $birthDate,
        public readonly ?int $heightCm,
        public readonly bool $isCaptain,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (int) $r['season_id'], (int) $r['person_id'],
            $r['shirt_number'] !== null ? (int) $r['shirt_number'] : null,
            (string) $r['first_name'], (string) $r['last_name'],
            (string) $r['position'], $r['detailed_position'] ?? null, $r['foot'] ?? null,
            (string) $r['nationality'], $r['birth_date'] ?? null,
            $r['height_cm'] !== null ? (int) $r['height_cm'] : null,
            (bool) $r['is_captain'],
        );
    }
```

- [ ] **Step 6: Adapter tous les `Player::fromRow([...])` existants (tests) pour inclure `person_id`**

Cette étape touche des fixtures de tests déjà écrites qui construisent un `Player` à la main. Rechercher tous les appels :

Run: `grep -rln "'season_id' =>" tests/unit/`

Pour chaque fichier trouvé (`HomeControllerTest.php`, `PlayerControllerTest.php`, `PlayerRepositoryTest.php`, `StatisticRepositoryTest.php`, `StatsApiControllerTest.php`, `PlayerApiControllerTest.php`, `ExportApiControllerTest.php`, `KpiServiceTest.php`), ajouter `'person_id' => <id>,` juste après `'season_id' => 1,` dans chaque tableau littéral passé à `Player::fromRow`. Utiliser la même valeur que l'`id` du joueur pour rester simple (ex. `'id' => 29, 'season_id' => 1, 'person_id' => 29, ...`), sauf indication contraire du test lui-même.

Pour les tests utilisant une table SQLite créée à la main (`PlayerRepositoryTest.php`, `StatisticRepositoryTest.php`), ajouter la colonne `person_id INT` à la définition `CREATE TABLE players (...)` et une valeur dans chaque `INSERT INTO players VALUES (...)` (position juste après `season_id`).

Exemple pour `tests/unit/PlayerRepositoryTest.php` :

```php
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,2,5,'','Marquinhos','DF','CB','right','Brésil','1994-05-14',183,1)");
```

Exemple pour `tests/unit/StatisticRepositoryTest.php`, même ajustement sur les deux `CREATE TABLE players` et leurs `INSERT`.

- [ ] **Step 7: Résoudre `person_id` dans le Migrator**

Dans `database/seeds/Migrator.php`, remplacer `migrator_seed_players` :

```php
// Insère les 24 joueurs, résout leur people.id par nom/prénom (crée si absent),
// et renvoie leur identifiant, clé de résolution et poste.
function migrator_seed_players(PDO $pdo, int $seasonId): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO players (season_id, person_id, shirt_number, first_name, last_name, position, detailed_position, nationality, is_captain)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $players = [];
    foreach (require __DIR__ . '/verified/players.php' as [$num, $first, $last, $pos, $detailed, $nat, $captain]) {
        $personId = migrator_resolve_person($pdo, $first, $last);
        $stmt->execute([$seasonId, $personId, $num, $first, $last, $pos, $detailed, $nat, (int) $captain]);
        $id = (int) $pdo->lastInsertId();
        $players[] = ['id' => $id, 'shirt' => $num, 'key' => migrator_player_key($first, $last), 'position' => $pos];
    }
    return $players;
}

// Retrouve la people.id d'un joueur par nom/prénom exact, ou en crée une nouvelle.
// C'est cette résolution qui relie les lignes players de plusieurs saisons entre elles.
function migrator_resolve_person(PDO $pdo, string $firstName, string $lastName): int
{
    $stmt = $pdo->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ?');
    $stmt->execute([$firstName, $lastName]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $insert = $pdo->prepare('INSERT INTO people (first_name, last_name) VALUES (?, ?)');
    $insert->execute([$firstName, $lastName]);
    return (int) $pdo->lastInsertId();
}
```

Le chemin `verified/players.php` change à la Task 3 (déplacement par saison) ; ne pas anticiper ici.

- [ ] **Step 8: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`. Si un test échoue avec `NOT NULL constraint failed: players.person_id`, c'est qu'une fixture de test a été oubliée à l'étape 6 : chercher le fichier concerné et compléter.

- [ ] **Step 9: Commit**

```bash
git add database/schema.sqlite.sql database/schema.mysql.sql php/models/Player.php database/seeds/Migrator.php tests/unit/
git commit -m "$(cat <<'EOF'
feat(schema): ajoute people et person_id sur players

Identite stable d'un joueur a travers les saisons, resolue par nom et
prenom lors du peuplement. Fondation pour la liste des saisons jouees
par un joueur (fiche joueur, tache ulterieure).
EOF
)"
```

---

### Task 3: Réorganiser les seeds 2025-26 dans un sous-dossier

**Files:**
- Move: `database/seeds/verified/players.php` -> `database/seeds/verified/2025-26/players.php`
- Move: `database/seeds/verified/player_season.php` -> `database/seeds/verified/2025-26/player_season.php`
- Move: `database/seeds/verified/matches_l1.php` -> `database/seeds/verified/2025-26/matches_l1.php`
- Move: `database/seeds/verified/matches_other.php` -> `database/seeds/verified/2025-26/matches_other.php`
- Move: `database/seeds/verified/players_l1_fbref.php` -> `database/seeds/verified/2025-26/players_l1_fbref.php`
- Modify: `database/seeds/Migrator.php`
- Modify: `database/seeds/MigratorStats.php`

**Interfaces:**
- Produces: chemins `verified/2025-26/*.php`, consommés directement par cette même tâche (pas de nouvelle fonction publique).

Cette tâche est un déplacement pur : aucune donnée n'est réécrite, aucun comportement ne doit changer. Pas de nouveau test dédié ; la vérification est `php tests/run.php` intégralement vert avant et après.

- [ ] **Step 1: Confirmer l'état vert avant déplacement**

Run: `php tests/run.php`
Expected: `OK` (baseline avant le déplacement).

- [ ] **Step 2: Déplacer les fichiers**

```bash
mkdir -p database/seeds/verified/2025-26
git mv database/seeds/verified/players.php database/seeds/verified/2025-26/players.php
git mv database/seeds/verified/player_season.php database/seeds/verified/2025-26/player_season.php
git mv database/seeds/verified/matches_l1.php database/seeds/verified/2025-26/matches_l1.php
git mv database/seeds/verified/matches_other.php database/seeds/verified/2025-26/matches_other.php
git mv database/seeds/verified/players_l1_fbref.php database/seeds/verified/2025-26/players_l1_fbref.php
```

Note : `teams.php`, `competitions.php`, `sources.php`, `seasons.php` restent à la racine de `verified/` (catalogues partagés entre saisons). Les fichiers non-PHP (`fbref-fixtures-2025-26.csv`, `fbref-players-l1-2025-26.csv`, `fbref-shots-by-competition-2025.json`, `understat-shots-2025.json`) restent aussi à la racine : ce sont des exports bruts déjà nommés par saison dans leur propre nom de fichier, non chargés par le Migrator, référencés directement par `PlayerController` (voir Task 17, aucun changement de chemin nécessaire pour eux).

- [ ] **Step 3: Mettre à jour les chemins dans `Migrator.php`**

Dans `database/seeds/Migrator.php`, remplacer chaque `require __DIR__ . '/verified/xxx.php'` concerné par le chemin `2025-26/` :

- `migrator_seed_players` (fonction déjà modifiée à la Task 2) : `require __DIR__ . '/verified/2025-26/players.php'`
- `migrator_seed_player_season` : `require __DIR__ . '/verified/2025-26/player_season.php'`
- `migrator_seed_matches` : `require __DIR__ . '/verified/2025-26/matches_l1.php'`
- `migrator_seed_other_matches` : `require __DIR__ . '/verified/2025-26/matches_other.php'`

(`teams.php`, `competitions.php`, `sources.php`, `seasons.php` gardent leur chemin racine actuel, inchangé.)

- [ ] **Step 4: Mettre à jour le chemin dans `MigratorStats.php`**

Dans `database/seeds/MigratorStats.php`, fonction `migrator_l1_totals`, remplacer :

```php
    foreach (require __DIR__ . '/verified/players_l1_fbref.php' as $key => $data) {
```

par :

```php
    foreach (require __DIR__ . '/verified/2025-26/players_l1_fbref.php' as $key => $data) {
```

- [ ] **Step 5: Lancer la suite complète pour confirmer l'absence de régression**

Run: `php tests/run.php`
Expected: `OK`, résultat strictement identique à l'étape 1 (même nombre de tests, même nombre d'assertions).

- [ ] **Step 6: Commit**

```bash
git add database/seeds/verified/2025-26/ database/seeds/Migrator.php database/seeds/MigratorStats.php
git commit -m "$(cat <<'EOF'
refactor(seeds): deplace les fichiers 2025-26 dans un sous-dossier

Prepare l'accueil de 2026-27 sans toucher aux donnees existantes.
Deplacement pur : aucune donnee reecrite, comportement inchange.
EOF
)"
```

---

### Task 4: Scinder le garde-fou en générique + totaux figés optionnels

**Files:**
- Modify: `database/seeds/Migrator.php` (remplace `migrator_verify_identities`, `migrator_compute_report`)
- Create: `database/seeds/verified/2025-26/season_totals.php`
- Modify: `database/migrate.php` (appel du garde-fou)
- Test: `tests/unit/MigratorGuardsTest.php` (nouveau)

**Interfaces:**
- Consumes: rien de nouveau côté schéma.
- Produces: `migrator_verify_generic(PDO $pdo, int $seasonId): void`, `migrator_verify_fixed_totals(string $seasonKey, array $report): void`, `migrator_compute_report(PDO $pdo, int $seasonId, int $psgId, int $leagueCompId): array`. Ces trois signatures sont celles que la Task 5 rebranchera dans la boucle multi-saisons.

- [ ] **Step 1: Écrire le test du garde-fou générique (échoue : fonction absente)**

Créer `tests/unit/MigratorGuardsTest.php` :

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/Migrator.php';

// Vérifie les deux garde-fous du Migrator, indépendamment d'une migration réelle :
// le contrôle générique (toujours actif) et les totaux figés (optionnels par saison).
final class MigratorGuardsTest extends TestCase
{
    private function pdoAvecUnMatch(int $teamGoals, int $individualGoals): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT)');
        $pdo->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT)');
        $pdo->exec('CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT)');
        $pdo->exec('INSERT INTO teams VALUES (1,1),(2,0)');
        $pdo->exec("INSERT INTO matches VALUES (1,10,100,1,2,{$teamGoals},0)");
        $pdo->exec('INSERT INTO players VALUES (1,10)');
        $pdo->exec("INSERT INTO player_match_stats VALUES (1,1,1,{$individualGoals})");
        return $pdo;
    }

    public function testGardeFouGeneriqueLaisseFairePasserUnCasCoherent(): void
    {
        $pdo = $this->pdoAvecUnMatch(3, 3);
        migrator_verify_generic($pdo, 10);
        $this->assertTrue(true, 'aucune exception levée');
    }

    public function testGardeFouGeneriqueEchoueSiButsIndividuelsDepassentEquipe(): void
    {
        $pdo = $this->pdoAvecUnMatch(2, 3);
        $this->assertThrows(
            static fn () => migrator_verify_generic($pdo, 10),
            RuntimeException::class,
            'buts individuels (3) > buts d\'équipe (2)'
        );
    }

    public function testGardeFouGeneriqueDetecteUnJoueurDuneAutreSaison(): void
    {
        $pdo = $this->pdoAvecUnMatch(3, 3);
        // Le joueur 1 appartient en réalité à la saison 10 (season_id juste inséré) ;
        // on le fait pointer sur une autre saison pour simuler la fuite.
        $pdo->exec('UPDATE players SET season_id = 99 WHERE id = 1');
        $this->assertThrows(
            static fn () => migrator_verify_generic($pdo, 10),
            RuntimeException::class,
            'joueur d\'une autre saison'
        );
    }

    public function testTotauxFigesAbsentsNeDeclenchentAucuneVerification(): void
    {
        // Aucun fichier season_totals.php pour cette clé : ne doit jamais échouer.
        migrator_verify_fixed_totals('inconnue-2099', ['l1_wins' => 0, 'l1_draws' => 0, 'l1_losses' => 0, 'l1_goals_for' => 0, 'l1_goals_against' => 0, 'l1_individual_goals' => 0]);
        $this->assertTrue(true, 'aucune exception levée en l\'absence de season_totals.php');
    }

    public function testTotauxFigesEchouentSiIncoherentsAvecLeFichier(): void
    {
        // 2025-26/season_totals.php existe déjà (Step 3) : un rapport volontairement faux doit échouer.
        $this->assertThrows(
            static fn () => migrator_verify_fixed_totals('2025-26', [
                'l1_wins' => 0, 'l1_draws' => 0, 'l1_losses' => 0,
                'l1_goals_for' => 0, 'l1_goals_against' => 0, 'l1_individual_goals' => 0,
            ]),
            RuntimeException::class,
            'totaux volontairement faux face à 2025-26/season_totals.php'
        );
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (fonctions `migrator_verify_generic`/`migrator_verify_fixed_totals` inexistantes).

- [ ] **Step 3: Créer `season_totals.php` pour 2025-26**

Créer `database/seeds/verified/2025-26/season_totals.php` avec les valeurs actuellement codées en dur dans `migrator_verify_identities` :

```php
<?php
declare(strict_types=1);
// Totaux Ligue 1 vérifiés FBref pour la saison 2025-26 (terminée) : garde-fou
// d'identité, contrôlé uniquement si ce fichier existe (voir MigratorGuardsTest).
return [
    'l1_wins' => 24,
    'l1_draws' => 4,
    'l1_losses' => 6,
    'l1_goals_for' => 74,
    'l1_goals_against' => 29,
    'l1_individual_goals' => 73,
];
```

- [ ] **Step 4: Remplacer `migrator_verify_identities` et `migrator_compute_report` dans `Migrator.php`**

Dans `database/seeds/Migrator.php`, remplacer la fonction `migrator_compute_report` (lignes actuelles ~161-186) par :

```php
// Calcule le bilan Ligue 1 d'une saison (V/N/D, buts) et le recoupe avec les buts
// individuels affectés, pour vérification d'intégrité. COALESCE(...,0) : une saison
// sans matchs (ex. tout juste amorcée) ne doit jamais produire de NULL arithmétique.
function migrator_compute_report(PDO $pdo, int $seasonId, int $psgId, int $leagueCompId): array
{
    $row = $pdo->query("SELECT
        COALESCE(SUM(CASE WHEN (home_team_id={$psgId} AND home_goals>away_goals) OR (away_team_id={$psgId} AND away_goals>home_goals) THEN 1 ELSE 0 END), 0) w,
        COALESCE(SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END), 0) d,
        COALESCE(SUM(CASE WHEN (home_team_id={$psgId} AND home_goals<away_goals) OR (away_team_id={$psgId} AND away_goals<home_goals) THEN 1 ELSE 0 END), 0) l,
        COALESCE(SUM(CASE WHEN home_team_id={$psgId} THEN home_goals ELSE away_goals END), 0) gf,
        COALESCE(SUM(CASE WHEN home_team_id={$psgId} THEN away_goals ELSE home_goals END), 0) ga
        FROM matches WHERE competition_id={$leagueCompId} AND season_id={$seasonId}")->fetch();
    $individualGoals = (int) $pdo->query(
        "SELECT COALESCE(SUM(goals),0) FROM player_match_stats s JOIN matches m ON m.id = s.match_id
         WHERE m.competition_id={$leagueCompId} AND m.season_id={$seasonId}"
    )->fetchColumn();

    return [
        'matches' => (int) $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id={$seasonId}")->fetchColumn(),
        'players' => (int) $pdo->query("SELECT COUNT(*) FROM players WHERE season_id={$seasonId}")->fetchColumn(),
        'l1_wins' => (int) $row['w'],
        'l1_draws' => (int) $row['d'],
        'l1_losses' => (int) $row['l'],
        'l1_goals_for' => (int) $row['gf'],
        'l1_goals_against' => (int) $row['ga'],
        'l1_individual_goals' => $individualGoals,
    ];
}

// Garde-fou générique, toujours actif : ne suppose aucun total connu à l'avance,
// s'applique donc aussi bien à une saison terminée qu'en cours.
function migrator_verify_generic(PDO $pdo, int $seasonId): void
{
    $rows = $pdo->query(
        "SELECT m.competition_id,
                SUM(CASE WHEN m.home_team_id IN (SELECT id FROM teams WHERE is_psg=1) THEN m.home_goals ELSE m.away_goals END) team_goals,
                COALESCE((
                    SELECT SUM(s.goals) FROM player_match_stats s JOIN matches mm ON mm.id = s.match_id
                    WHERE mm.competition_id = m.competition_id AND mm.season_id = {$seasonId}
                ), 0) individual_goals
         FROM matches m
         WHERE m.season_id = {$seasonId}
           AND (m.home_team_id IN (SELECT id FROM teams WHERE is_psg=1) OR m.away_team_id IN (SELECT id FROM teams WHERE is_psg=1))
         GROUP BY m.competition_id"
    )->fetchAll();

    foreach ($rows as $row) {
        if ((int) $row['individual_goals'] > (int) $row['team_goals']) {
            throw new RuntimeException(sprintf(
                'Garde-fou générique : saison %d, compétition %d : buts individuels (%d) dépassent les buts d\'équipe (%d)',
                $seasonId, $row['competition_id'], $row['individual_goals'], $row['team_goals']
            ));
        }
    }

    $orphans = (int) $pdo->query(
        "SELECT COUNT(*) FROM player_match_stats s
         JOIN players p ON p.id = s.player_id
         JOIN matches m ON m.id = s.match_id
         WHERE m.season_id = {$seasonId} AND p.season_id <> m.season_id"
    )->fetchColumn();
    if ($orphans > 0) {
        throw new RuntimeException("Garde-fou générique : saison {$seasonId}, {$orphans} ligne(s) player_match_stats référencent un joueur d'une autre saison");
    }
}

// Totaux figés, optionnels par saison : vérifiés seulement si verified/{clé}/season_totals.php
// existe (saison terminée). Aucune vérification pour une saison en cours qui n'a pas ce fichier.
function migrator_verify_fixed_totals(string $seasonKey, array $report): void
{
    $file = __DIR__ . "/verified/{$seasonKey}/season_totals.php";
    if (!is_file($file)) {
        return;
    }
    $expected = require $file;
    $ok = $report['l1_wins'] === $expected['l1_wins']
        && $report['l1_draws'] === $expected['l1_draws']
        && $report['l1_losses'] === $expected['l1_losses']
        && $report['l1_goals_for'] === $expected['l1_goals_for']
        && $report['l1_goals_against'] === $expected['l1_goals_against']
        && $report['l1_individual_goals'] === $expected['l1_individual_goals'];

    if (!$ok) {
        throw new RuntimeException(sprintf(
            'Identité invalide (%s) : obtenu %dV %dN %dD (%d-%d) buts indiv. %d, attendu %dV %dN %dD (%d-%d) buts indiv. %d',
            $seasonKey,
            $report['l1_wins'], $report['l1_draws'], $report['l1_losses'],
            $report['l1_goals_for'], $report['l1_goals_against'], $report['l1_individual_goals'],
            $expected['l1_wins'], $expected['l1_draws'], $expected['l1_losses'],
            $expected['l1_goals_for'], $expected['l1_goals_against'], $expected['l1_individual_goals']
        ));
    }
}
```

Supprimer entièrement l'ancienne fonction `migrator_verify_identities` (elle est remplacée par les deux fonctions ci-dessus).

- [ ] **Step 5: Adapter l'appel dans `database/migrate.php`**

Dans `database/migrate.php`, fonction `run_migration`, remplacer :

```php
    $report = migrator_compute_report($pdo, $ref);
    migrator_verify_identities($report);
    return $report;
```

par (le câblage complet multi-saisons arrive à la Task 5 ; pour l'instant on garde `run_migration` mono-saison mais avec les nouvelles signatures) :

```php
    $psgId = $ref['psg_id'];
    $leagueCompId = $ref['competition_ids']['ligue1'];
    $report = migrator_compute_report($pdo, $ref['season_id'], $psgId, $leagueCompId);
    migrator_verify_generic($pdo, $ref['season_id']);
    migrator_verify_fixed_totals('2025-26', $report);
    return $report;
```

- [ ] **Step 6: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`. `SeedIntegrityTest` doit rester vert à l'identique (le garde-fou générique + les totaux figés 2025-26 couvrent exactement les mêmes identités que l'ancien `migrator_verify_identities`).

- [ ] **Step 7: Commit**

```bash
git add database/seeds/Migrator.php database/seeds/verified/2025-26/season_totals.php database/migrate.php tests/unit/MigratorGuardsTest.php
git commit -m "$(cat <<'EOF'
refactor(migrator): scinde le garde-fou en generique + totaux figes

Le controle generique (buts individuels <= buts d'equipe, pas de fuite
inter-saisons) reste actif pour toute saison. Les totaux figes exacts
deviennent un fichier season_totals.php optionnel par saison, absent
pour une saison en cours.
EOF
)"
```

---

### Task 5: Généraliser `run_migration()` en boucle multi-saisons (une seule saison pour l'instant)

**Files:**
- Modify: `database/seeds/Migrator.php` (`migrator_seed_reference` éclate en `migrator_seed_catalog` + `migrator_seed_seasons`)
- Modify: `database/migrate.php` (`run_migration` boucle, bloc CLI adapté)
- Test: `tests/unit/SeedIntegrityTest.php` (aucune modif de contenu, sert de non-régression)
- Test: `tests/unit/MigratorLoopTest.php` (nouveau)

**Interfaces:**
- Produces: `migrator_seed_catalog(PDO $pdo): array` (teams/competitions/sources, plus `psg_id`), `migrator_seed_seasons(PDO $pdo): array` (liste `['id' => int, 'key' => string, 'is_current' => bool]`), `migrator_seed_season(PDO $pdo, array $season, array $catalog): array` (report de cette saison), `run_migration(PDO $pdo): array` retourne désormais `[seasonKey => report]` au lieu d'un report unique.
- Consumes: `migrator_seed_players`, `migrator_seed_matches`, `migrator_seed_other_matches`, `migrator_generate_player_stats`, `migrator_seed_player_season`, `migrator_compute_report`, `migrator_verify_generic`, `migrator_verify_fixed_totals` (Tasks 2-4).

- [ ] **Step 1: Écrire le test de la boucle (échoue : fonctions absentes)**

Créer `tests/unit/MigratorLoopTest.php` :

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

// Vérifie que run_migration() traite chaque saison déclarée et renvoie un
// rapport indexé par clé de saison, sans mélanger les identifiants entre saisons.
final class MigratorLoopTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testRunMigrationRenvoieUnRapportParCleDeSaison(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $reports = run_migration($pdo);

        $this->assertTrue(array_key_exists('2025-26', $reports), 'la clé 2025-26 est présente');
        $this->assertSame(55, $reports['2025-26']['matches']);
        $this->assertSame(24, $reports['2025-26']['players']);
    }

    public function testChaqueJoueurAUnSeasonIdCoherentAvecSaSaison(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query(
            'SELECT COUNT(*) FROM players p JOIN seasons s ON s.id = p.season_id WHERE s.label = "2025-26"'
        )->fetchColumn();
        $this->assertSame(24, $n, 'les 24 joueurs sont bien rattachés à la saison 2025-26');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL sur `MigratorLoopTest::testRunMigrationRenvoieUnRapportParCleDeSaison` (clé `2025-26` absente : `run_migration` renvoie encore un report unique, pas un tableau indexé).

- [ ] **Step 3: Éclater `migrator_seed_reference` en catalogue partagé + saisons**

Dans `database/seeds/Migrator.php`, remplacer `migrator_seed_reference` par :

```php
// Insère teams, competitions et sources (catalogues partagés entre saisons) ;
// renvoie les identifiants nécessaires au peuplement de chaque saison.
function migrator_seed_catalog(PDO $pdo): array
{
    $teamIds = [];
    $psgId = null;
    $stmt = $pdo->prepare('INSERT INTO teams (name, short_name, country, is_psg) VALUES (?, ?, ?, ?)');
    foreach (require __DIR__ . '/verified/teams.php' as $team) {
        $stmt->execute([$team['name'], $team['short_name'], $team['country'], (int) $team['is_psg']]);
        $id = (int) $pdo->lastInsertId();
        $teamIds[$team['name']] = $id;
        if ($team['is_psg']) {
            $psgId = $id;
        }
    }

    $competitionIds = [];
    $stmt = $pdo->prepare('INSERT INTO competitions (name, type, scope) VALUES (?, ?, ?)');
    foreach (require __DIR__ . '/verified/competitions.php' as $comp) {
        $stmt->execute([$comp['name'], $comp['type'], $comp['scope']]);
        $competitionIds[$comp['key']] = (int) $pdo->lastInsertId();
    }

    $sourceIds = [];
    $stmt = $pdo->prepare('INSERT INTO data_sources (label, url, collected_at, confidence, note) VALUES (?, ?, ?, ?, ?)');
    foreach (require __DIR__ . '/verified/sources.php' as $src) {
        $stmt->execute([$src['label'], $src['url'], $src['collected_at'], $src['confidence'], $src['note']]);
        $sourceIds[$src['key']] = (int) $pdo->lastInsertId();
    }

    return [
        'team_ids' => $teamIds,
        'psg_id' => $psgId,
        'competition_ids' => $competitionIds,
        'source_ids' => $sourceIds,
    ];
}

// Insère chaque saison déclarée dans verified/seasons.php ; renvoie la liste dans
// l'ordre du fichier, avec l'id inséré et la clé (nom du sous-dossier verified/{clé}/).
function migrator_seed_seasons(PDO $pdo): array
{
    $stmt = $pdo->prepare('INSERT INTO seasons (label, start_date, end_date, is_current) VALUES (?, ?, ?, ?)');
    $seasons = [];
    foreach (require __DIR__ . '/verified/seasons.php' as $season) {
        $stmt->execute([$season['label'], $season['start_date'], $season['end_date'], (int) $season['is_current']]);
        $seasons[] = ['id' => (int) $pdo->lastInsertId(), 'key' => $season['key'], 'is_current' => $season['is_current']];
    }
    return $seasons;
}

// Peuple une saison (joueurs, matchs, statistiques) à partir de verified/{clé}/ et
// calcule son rapport, garde-fous compris.
function migrator_seed_season(PDO $pdo, array $season, array $catalog): array
{
    $seasonId = $season['id'];
    $seasonKey = $season['key'];
    $ref = $catalog + ['season_id' => $seasonId];

    $players = migrator_seed_players($pdo, $seasonId, $seasonKey);
    $matches = migrator_seed_matches($pdo, $ref, $seasonKey);
    migrator_seed_other_matches($pdo, $ref, $seasonKey);
    migrator_generate_player_stats($pdo, $matches, $players, $ref, $seasonKey);
    migrator_seed_player_season($pdo, $players, $ref, $seasonKey);

    $report = migrator_compute_report($pdo, $seasonId, $catalog['psg_id'], $catalog['competition_ids']['ligue1']);
    migrator_verify_generic($pdo, $seasonId);
    migrator_verify_fixed_totals($seasonKey, $report);
    return $report;
}
```

- [ ] **Step 4: Paramétrer les fonctions de peuplement par `$seasonKey`**

Dans `database/seeds/Migrator.php`, changer les signatures et les chemins de fichiers :

```php
function migrator_seed_players(PDO $pdo, int $seasonId, string $seasonKey): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO players (season_id, person_id, shirt_number, first_name, last_name, position, detailed_position, nationality, is_captain)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $players = [];
    foreach (require __DIR__ . "/verified/{$seasonKey}/players.php" as [$num, $first, $last, $pos, $detailed, $nat, $captain]) {
        $personId = migrator_resolve_person($pdo, $first, $last);
        $stmt->execute([$seasonId, $personId, $num, $first, $last, $pos, $detailed, $nat, (int) $captain]);
        $id = (int) $pdo->lastInsertId();
        $players[] = ['id' => $id, 'shirt' => $num, 'key' => migrator_player_key($first, $last), 'position' => $pos];
    }
    return $players;
}
```

```php
function migrator_seed_player_season(PDO $pdo, array $players, array $ref, string $seasonKey): void
{
    $file = __DIR__ . "/verified/{$seasonKey}/player_season.php";
    if (!is_file($file)) {
        return;
    }
    $idByShirt = [];
    foreach ($players as $p) {
        $idByShirt[$p['shirt']] = $p['id'];
    }
    $sourceId = $ref['source_ids']['squad_screenshots'];
    $seasonId = $ref['season_id'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_season_stats (player_id, season_id, appearances, starts, goals, assists, yellow_cards, red_cards, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (require $file as [$shirt, $apps, $starts, $goals, $assists, $yellow, $red]) {
        if (!isset($idByShirt[$shirt])) {
            throw new RuntimeException("bilan saison : joueur au numéro {$shirt} introuvable");
        }
        $stmt->execute([$idByShirt[$shirt], $seasonId, $apps, $starts, $goals, $assists, $yellow, $red, $sourceId]);
    }
}
```

```php
function migrator_seed_matches(PDO $pdo, array $ref, string $seasonKey): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO matches (season_id, competition_id, round_label, played_at, home_team_id, away_team_id, home_goals, away_goals, venue, attendance, psg_possession, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $psgId = $ref['psg_id'];
    $compId = $ref['competition_ids']['ligue1'];
    $sourceId = $ref['source_ids']['fbref'];
    $matches = [];
    foreach (require __DIR__ . "/verified/{$seasonKey}/matches_l1.php" as [$round, $date, $opponent, $isHome, $psgGoals, $advGoals, $attendance, $possession]) {
        $oppId = $ref['team_ids'][$opponent];
        [$homeId, $awayId, $homeGoals, $awayGoals] = $isHome
            ? [$psgId, $oppId, $psgGoals, $advGoals]
            : [$oppId, $psgId, $advGoals, $psgGoals];
        $venue = $isHome ? 'home' : 'away';
        $stmt->execute([$ref['season_id'], $compId, $round, $date, $homeId, $awayId, $homeGoals, $awayGoals, $venue, $attendance, $possession, $sourceId]);
        $matches[] = ['id' => (int) $pdo->lastInsertId(), 'psg_goals' => $psgGoals];
    }
    return $matches;
}

function migrator_seed_other_matches(PDO $pdo, array $ref, string $seasonKey): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO matches (season_id, competition_id, round_label, played_at, home_team_id, away_team_id, home_goals, away_goals, went_to_extra, penalty_shootout, penalty_score, venue, attendance, psg_possession, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $psgId = $ref['psg_id'];
    $sourceId = $ref['source_ids']['fbref'];
    foreach (require __DIR__ . "/verified/{$seasonKey}/matches_other.php" as [
        $compKey, $round, $date, $venue, $opponent, $psgGoals, $advGoals,
        $possession, $attendance, $wentToExtra, $penaltyShootout, $penaltyScore,
    ]) {
        $compId = $ref['competition_ids'][$compKey];
        $oppId = $ref['team_ids'][$opponent];
        [$homeId, $awayId, $homeGoals, $awayGoals] = $venue === 'away'
            ? [$oppId, $psgId, $advGoals, $psgGoals]
            : [$psgId, $oppId, $psgGoals, $advGoals];
        $stmt->execute([
            $ref['season_id'], $compId, $round, $date, $homeId, $awayId, $homeGoals, $awayGoals,
            (int) $wentToExtra, (int) $penaltyShootout, $penaltyScore, $venue, $attendance, $possession, $sourceId,
        ]);
    }
}
```

Dans `database/seeds/MigratorStats.php`, changer les signatures pour recevoir `$seasonKey` et rendre `migrator_generate_player_stats` tolérante à l'absence de `players_l1_fbref.php` (saison en cours sans totaux vérifiés) :

```php
function migrator_l1_totals(array $players, string $file): array
{
    $idByKey = [];
    foreach ($players as $p) {
        $idByKey[$p['key']] = $p['id'];
    }
    $totals = [];
    foreach (require $file as $key => $data) {
        if (!isset($idByKey[$key])) {
            throw new RuntimeException("statistiques L1 fbref : joueur introuvable pour la clé {$key}");
        }
        $totals[$idByKey[$key]] = $data;
    }
    return $totals;
}
```

```php
// Orchestre la génération des player_match_stats : totaux exacts, affectation
// des buts/passes/cartons aux matchs, complément d'apparitions, puis
// insertion d'une ligne par (joueur, match). Ne génère rien si la saison n'a
// pas encore de totaux vérifiés (players_l1_fbref.php absent) ou pas de matchs :
// aucune statistique individuelle n'est jamais fabriquée sans total vérifié.
function migrator_generate_player_stats(PDO $pdo, array $matches, array $players, array $ref, string $seasonKey): void
{
    $totalsFile = __DIR__ . "/verified/{$seasonKey}/players_l1_fbref.php";
    if (!is_file($totalsFile) || $matches === [] || $players === []) {
        return;
    }

    $gen = new StatGenerator(2026);
    $sourceId = $ref['source_ids']['stat_generator'];
    $stmt = $pdo->prepare(
        'INSERT INTO player_match_stats (player_id, match_id, is_starter, minutes, goals, assists, shots, shots_on_target, duels_won, interceptions, yellow_cards, red_card, rating, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $totals = migrator_l1_totals($players, $totalsFile);
    $goalTotals = array_map(static fn (array $t): int => $t['goals'], $totals);
    $assistTotals = array_map(static fn (array $t): int => $t['assists'], $totals);

    $agg = [];
    migrator_spread_goals($agg, $matches, $goalTotals);
    migrator_spread_assists($agg, $matches, $assistTotals);
    migrator_spread_cards($agg, $matches, $totals);
    migrator_top_up_appearances($agg, $matches, $totals);
    migrator_insert_stats($stmt, $gen, $agg, $totals, $sourceId);
}
```

- [ ] **Step 5: Recâbler `run_migration()` et le bloc CLI**

Dans `database/migrate.php`, remplacer entièrement `run_migration` :

```php
// Point d'entrée réutilisable par les tests et par le mode CLI. Renvoie un
// rapport indexé par clé de saison (ex. '2025-26' => [...]).
function run_migration(PDO $pdo): array
{
    migrator_apply_schema($pdo);
    $catalog = migrator_seed_catalog($pdo);
    $seasons = migrator_seed_seasons($pdo);

    $reports = [];
    foreach ($seasons as $season) {
        $reports[$season['key']] = migrator_seed_season($pdo, $season, $catalog);
    }
    return $reports;
}
```

Remplacer le bloc CLI (fin du fichier) :

```php
    try {
        $reports = run_migration($pdo);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "ÉCHEC migration : {$e->getMessage()}\n");
        exit(1);
    }

    foreach ($reports as $seasonKey => $report) {
        printf(
            "%s : matches %d, players %d, L1 %dV %dN %dD (%d-%d)\n",
            $seasonKey,
            $report['matches'],
            $report['players'],
            $report['l1_wins'],
            $report['l1_draws'],
            $report['l1_losses'],
            $report['l1_goals_for'],
            $report['l1_goals_against']
        );
    }
```

- [ ] **Step 6: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`. `SeedIntegrityTest` et `PlayerSeasonStatsTest` restent verts sans modification (ils n'utilisent que le PDO peuplé, pas la valeur de retour de `run_migration`). `MigratorLoopTest` passe désormais.

- [ ] **Step 7: Vérifier le mode CLI manuellement**

Run: `php database/migrate.php`
Expected: une ligne `2025-26 : matches 55, players 24, L1 24V 4N 6D (74-29)`.

- [ ] **Step 8: Commit**

```bash
git add database/seeds/Migrator.php database/seeds/MigratorStats.php database/migrate.php tests/unit/MigratorLoopTest.php
git commit -m "$(cat <<'EOF'
refactor(migrator): boucle multi-saisons dans run_migration

run_migration() traite desormais chaque saison declaree dans
seasons.php et renvoie un rapport indexe par cle de saison. Une seule
saison existe encore a ce stade (2025-26) : comportement inchange.
EOF
)"
```

---

### Task 6: (fusionnée dans la Task 2, résolution `person_id` déjà en place)

Cette tâche a été anticipée et livrée avec la Task 2 (`migrator_resolve_person`, appelée depuis `migrator_seed_players`). Aucune action supplémentaire ici ; elle reste listée pour mémoire de la numérotation du spec, mais n'introduit aucun step.

---

### Task 7: Ajouter la saison 2026-27 (seeds vides)

**Files:**
- Create: `database/seeds/verified/2026-27/players.php`
- Create: `database/seeds/verified/2026-27/matches_l1.php`
- Create: `database/seeds/verified/2026-27/matches_other.php`
- Modify: `database/seeds/verified/seasons.php`
- Test: `tests/unit/MigratorLoopTest.php` (étendre)

**Interfaces:**
- Consumes: la boucle multi-saisons de la Task 5, le garde-fou générique de la Task 4 (doit tolérer une saison vide), `migrator_generate_player_stats`/`migrator_seed_player_season` déjà tolérants à l'absence de fichiers optionnels (Task 5, Step 4).

- [ ] **Step 1: Étendre le test de boucle (échoue : la saison 2026-27 n'existe pas encore)**

Ajouter à `tests/unit/MigratorLoopTest.php` :

```php
    public function testDeuxiemeSaisonCoexisteSansAlererLaPremiere(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $reports = run_migration($pdo);

        $this->assertTrue(array_key_exists('2026-27', $reports), 'la clé 2026-27 est présente');
        $this->assertSame(0, $reports['2026-27']['matches'], 'aucun match encore pour 2026-27');
        $this->assertSame(0, $reports['2026-27']['players'], 'aucun joueur encore pour 2026-27');

        // 2025-26 reste strictement identique, quelle que soit la présence de 2026-27.
        $this->assertSame(55, $reports['2025-26']['matches']);
        $this->assertSame(24, $reports['2025-26']['players']);
    }

    public function testUneSeuleSaisonEstCourante(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);

        $n = (int) $pdo->query('SELECT COUNT(*) FROM seasons WHERE is_current = 1')->fetchColumn();
        $this->assertSame(1, $n, 'exactement une saison courante');

        $label = $pdo->query('SELECT label FROM seasons WHERE is_current = 1')->fetchColumn();
        $this->assertSame('2026-27', $label, '2026-27 est la saison courante');
    }
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (clé `2026-27` absente du rapport, aucune saison marquée courante).

- [ ] **Step 3: Créer les seeds vides de 2026-27**

Créer `database/seeds/verified/2026-27/players.php` :

```php
<?php
declare(strict_types=1);
// Effectif 2026-27 : à cadrer et remplir séparément (transferts, calendrier, mercato).
// Format identique à verified/2025-26/players.php : [numéro, prénom, nom, poste,
// poste détaillé, nationalité, capitaine].
return [];
```

Créer `database/seeds/verified/2026-27/matches_l1.php` :

```php
<?php
declare(strict_types=1);
// Matchs de Ligue 1 2026-27 : remplis au fil de la saison. Format identique à
// verified/2025-26/matches_l1.php : [journée, date, adversaire, domicile, buts PSG,
// buts adverse, affluence, possession].
return [];
```

Créer `database/seeds/verified/2026-27/matches_other.php` :

```php
<?php
declare(strict_types=1);
// Matchs hors Ligue 1 2026-27 (Ligue des Champions, Trophée des Champions le cas
// échéant, Coupe de France...) : remplis au fil de la saison. Format identique à
// verified/2025-26/matches_other.php.
return [];
```

Ne pas créer `players_l1_fbref.php`, `player_season.php` ni `season_totals.php` pour 2026-27 : ce sont les trois fichiers optionnels (Tasks 4 et 5) qui n'existent que lorsque des totaux vérifiés existent.

- [ ] **Step 4: Basculer `is_current` et déclarer 2026-27**

Remplacer `database/seeds/verified/seasons.php` :

```php
<?php
declare(strict_types=1);
// Saisons couvertes par le projet. Une seule porte is_current à la fois (vérifié
// par le Migrator). 2025-26 : saison terminée, cinq trophées. 2026-27 : saison en
// cours, structure vide pour l'instant (effectif et calendrier à cadrer séparément).
return [
    ['key' => '2025-26', 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => false],
    ['key' => '2026-27', 'label' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true],
];
```

- [ ] **Step 5: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`. Vérifier en particulier que `SeedIntegrityTest` (2025-26) reste vert à l'identique : la présence de 2026-27 ne doit rien changer à ses résultats.

- [ ] **Step 6: Vérifier le mode CLI manuellement**

Run: `php database/migrate.php`
Expected :
```
2025-26 : matches 55, players 24, L1 24V 4N 6D (74-29)
2026-27 : matches 0, players 0, L1 0V 0N 0D (0-0)
```

- [ ] **Step 7: Commit**

```bash
git add database/seeds/verified/2026-27/ database/seeds/verified/seasons.php tests/unit/MigratorLoopTest.php
git commit -m "$(cat <<'EOF'
feat(seeds): ajoute la structure vide de la saison 2026-27

2026-27 devient la saison courante (is_current). Effectif et
calendrier restent a zero, a remplir separement (hors perimetre de ce
plan). 2025-26 reste strictement inchangee.
EOF
)"
```

---

### Task 8: `SeasonRepository` (current/bySlug/resolve/all/navData)

**Files:**
- Create: `php/repositories/SeasonRepository.php`
- Test: `tests/unit/SeasonRepositoryTest.php` (nouveau)

**Interfaces:**
- Consumes: `Season::fromRow()` (Task 1), table `seasons` avec `is_current` (Task 1).
- Produces: `SeasonRepository::current(): Season`, `::bySlug(string $slug): ?Season`, `::resolve(?string $slug): Season`, `::all(): array` (liste de `Season`), `::navData(Season $selected): array` (forme `['seasons' => [['label' => string, 'isCurrent' => bool], ...], 'selectedSeason' => string]`). Consommé par toutes les Tasks 15-19 et 21.

- [ ] **Step 1: Écrire le test (échoue : classe absente)**

Créer `tests/unit/SeasonRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie la résolution de la saison affichée : courante par défaut, par slug si
// valide, retombée sur la courante si le slug est invalide ou absent.
final class SeasonRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $pdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        return $pdo;
    }

    public function testCurrentRenvoieLaSaisonCourante(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->current()->label);
    }

    public function testBySlugRenvoieLaSaisonCorrespondante(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $s = $repo->bySlug('2025-26');
        $this->assertTrue($s instanceof Season);
        $this->assertSame(1, $s->id);
    }

    public function testBySlugRenvoieNullSiInconnue(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame(null, $repo->bySlug('1999-00'));
    }

    public function testResolveRetombeSurLaCouranteSiSlugAbsent(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->resolve(null)->label);
        $this->assertSame('2026-27', $repo->resolve('')->label);
    }

    public function testResolveRetombeSurLaCouranteSiSlugInvalide(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2026-27', $repo->resolve('nimportequoi')->label);
    }

    public function testResolveRenvoieLaSaisonDemandeeSiValide(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame('2025-26', $repo->resolve('2025-26')->label);
    }

    public function testAllRenvoieLesDeuxSaisonsTrieesParDate(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $all = $repo->all();
        $this->assertCount(2, $all);
        $this->assertSame('2025-26', $all[0]->label);
        $this->assertSame('2026-27', $all[1]->label);
    }

    public function testNavDataExposeLesSaisonsEtLaSelection(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $nav = $repo->navData($repo->bySlug('2025-26'));
        $this->assertSame('2025-26', $nav['selectedSeason']);
        $this->assertSame(['2025-26', '2026-27'], array_column($nav['seasons'], 'label'));
        $this->assertSame(false, $nav['seasons'][0]['isCurrent']);
        $this->assertSame(true, $nav['seasons'][1]['isCurrent']);
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (classe `SeasonRepository` introuvable).

- [ ] **Step 3: Créer `SeasonRepository`**

Créer `php/repositories/SeasonRepository.php` :

```php
<?php
declare(strict_types=1);
// Accès aux saisons : résolution de la saison affichée (courante par défaut, ou
// par slug via ?saison=), liste complète pour le sélecteur du header.
final class SeasonRepository extends Repository
{
    public function current(): Season
    {
        $row = $this->fetchOne('SELECT * FROM seasons WHERE is_current = 1');
        if ($row === null) {
            throw new RuntimeException('Aucune saison courante définie (is_current)');
        }
        return Season::fromRow($row);
    }

    public function bySlug(string $slug): ?Season
    {
        $row = $this->fetchOne('SELECT * FROM seasons WHERE label = ?', [$slug]);
        return $row ? Season::fromRow($row) : null;
    }

    // Résout la saison à afficher : le slug demandé s'il correspond à une saison
    // connue, sinon la saison courante. Ne lève jamais pour un slug absent/invalide.
    public function resolve(?string $slug): Season
    {
        if ($slug !== null && $slug !== '') {
            $season = $this->bySlug($slug);
            if ($season !== null) {
                return $season;
            }
        }
        return $this->current();
    }

    public function all(): array
    {
        return array_map(Season::fromRow(...), $this->fetchAll('SELECT * FROM seasons ORDER BY start_date'));
    }

    // Données du sélecteur de saison (header) : toutes les saisons connues et le
    // label actuellement sélectionné, pour surligner l'option active côté vue.
    public function navData(Season $selected): array
    {
        return [
            'seasons' => array_map(static fn (Season $s): array => [
                'label' => $s->label,
                'isCurrent' => $s->isCurrent,
            ], $this->all()),
            'selectedSeason' => $selected->label,
        ];
    }
}
```

- [ ] **Step 4: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add php/repositories/SeasonRepository.php tests/unit/SeasonRepositoryTest.php
git commit -m "$(cat <<'EOF'
feat(saisons): ajoute SeasonRepository (current/bySlug/resolve/navData)

Point d'entree unique de resolution de la saison affichee, reutilise
par toutes les pages et l'API a partir des taches suivantes.
EOF
)"
```

---

### Task 9: `PlayerRepository`, filtrage `season_id` + `seasonsForPerson`

**Files:**
- Modify: `php/repositories/PlayerRepository.php`
- Modify: `tests/unit/PlayerRepositoryTest.php`

**Interfaces:**
- Consumes: `Player::$personId` (Task 2), table `people`/`players.person_id`.
- Produces: `PlayerRepository::all(int $seasonId): array`, `::paginate(int $seasonId, int $page, int $perPage, string $sort, string $order, ?string $position): array`, `::seasonsForPerson(int $personId): array` (forme `[['label' => string, 'isCurrent' => bool, 'playerId' => int], ...]` triée par date de saison croissante). Consommé par Task 17 (`PlayerController`) et Task 21 (`PlayerApiController`).

- [ ] **Step 1: Étendre le test (échoue : signatures actuelles, méthode absente)**

Remplacer entièrement `tests/unit/PlayerRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie la lecture, la pagination filtrée par saison, et la résolution des
// saisons jouées par un même joueur (person_id) du PlayerRepository.
final class PlayerRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)");
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $pdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        // Barcola (person 1) joue les deux saisons ; Marquinhos (person 2) ne joue que 2025-26.
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,2,5,'','Marquinhos','DF','CB','right','Brésil','1994-05-14',183,1)");
        $pdo->exec("INSERT INTO players VALUES (3,2,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        return $pdo;
    }

    public function testFindRetourneJoueur(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $p = $repo->find(1);
        $this->assertTrue($p instanceof Player, 'trouvé');
        $this->assertSame('Bradley Barcola', $p->fullName());
    }

    public function testAllFiltreParSaison(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $this->assertCount(2, $repo->all(1), '2025-26 : Barcola + Marquinhos');
        $this->assertCount(1, $repo->all(2), '2026-27 : Barcola seul');
    }

    public function testPaginateFiltrePositionEtSaison(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $res = $repo->paginate(1, 1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(1, $res['total']);
        $this->assertSame('Marquinhos', $res['items'][0]->lastName);

        // Même filtre position, mais saison 2026-27 : Marquinhos n'y joue pas.
        $res2026 = $repo->paginate(2, 1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(0, $res2026['total'], 'Marquinhos absent de 2026-27');
    }

    public function testSeasonsForPersonRenvoieLesSaisonsJoueesTrieesParDate(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $seasons = $repo->seasonsForPerson(1);

        $this->assertCount(2, $seasons, 'Barcola a joué deux saisons');
        $this->assertSame(['2025-26', '2026-27'], array_column($seasons, 'label'));
        $this->assertSame(1, $seasons[0]['playerId']);
        $this->assertSame(3, $seasons[1]['playerId']);
        $this->assertSame(false, $seasons[0]['isCurrent']);
        $this->assertSame(true, $seasons[1]['isCurrent']);
    }

    public function testSeasonsForPersonRenvoieUneSeuleSaisonSiJoueeUneFois(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $seasons = $repo->seasonsForPerson(2);
        $this->assertCount(1, $seasons);
        $this->assertSame('2025-26', $seasons[0]['label']);
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `paginate()` incompatible, méthode `seasonsForPerson` absente, `all()` sans argument).

- [ ] **Step 3: Modifier `PlayerRepository`**

Remplacer `php/repositories/PlayerRepository.php` :

```php
<?php
declare(strict_types=1);
// Accès aux joueurs : lecture unitaire, liste complète, pagination filtrée, et
// résolution des saisons jouées par un même joueur (person_id).
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class PlayerRepository extends Repository
{
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];

    public function find(int $id): ?Player
    {
        $row = $this->fetchOne('SELECT * FROM players WHERE id = ?', [$id]);
        return $row ? Player::fromRow($row) : null;
    }

    public function all(int $seasonId): array
    {
        return array_map(
            Player::fromRow(...),
            $this->fetchAll('SELECT * FROM players WHERE season_id = ? ORDER BY last_name', [$seasonId])
        );
    }

    public function paginate(int $seasonId, int $page, int $perPage, string $sortColumn, string $order, ?string $position): array
    {
        $column = in_array($sortColumn, self::SORTABLE, true) ? $sortColumn : 'last_name';
        $dir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $where = 'WHERE season_id = :season' . ($position !== null ? ' AND position = :position' : '');
        $params = $position !== null ? ['season' => $seasonId, 'position' => $position] : ['season' => $seasonId];

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM players {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM players {$where} ORDER BY {$column} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(Player::fromRow(...), $rows), 'total' => $total];
    }

    // Saisons PSG jouées par un même joueur (person_id), triées de la plus ancienne
    // à la plus récente. playerId permet à la vue de lier vers la fiche de chaque
    // saison (une ligne players distincte par saison).
    public function seasonsForPerson(int $personId): array
    {
        $rows = $this->fetchAll(
            'SELECT p.id player_id, s.label, s.is_current
             FROM players p
             JOIN seasons s ON s.id = p.season_id
             WHERE p.person_id = ?
             ORDER BY s.start_date ASC',
            [$personId]
        );
        return array_map(static fn (array $r): array => [
            'label'     => (string) $r['label'],
            'isCurrent' => (bool) $r['is_current'],
            'playerId'  => (int) $r['player_id'],
        ], $rows);
    }
}
```

- [ ] **Step 4: Adapter les appelants existants**

Aucun appelant n'est encore modifié à cette tâche : `PlayerController` et `PlayerApiController` seront mis à jour aux Tasks 17 et 21. Entre les deux, `php tests/run.php` échouera sur les tests de ces contrôleurs (arité incompatible sur leurs doublures `paginate`) : c'est attendu, ces tests seront corrigés dans leurs tâches respectives. Vérifier ici uniquement `PlayerRepositoryTest` :

Run: `php tests/run.php`
Expected: `PlayerRepositoryTest` passe (`.` pour chacun de ses tests). D'autres fichiers (`PlayerControllerTest`, `PlayerApiControllerTest`, `HomeControllerTest`) échouent temporairement : noter les noms de méthode en échec, ils sont résolus aux Tasks 16, 17 et 21.

- [ ] **Step 5: Commit**

```bash
git add php/repositories/PlayerRepository.php tests/unit/PlayerRepositoryTest.php
git commit -m "$(cat <<'EOF'
feat(joueurs): PlayerRepository filtre par season_id, ajoute seasonsForPerson

all() et paginate() prennent desormais la saison en premier parametre,
explicite comme le reste du projet. seasonsForPerson() sert la future
liste des saisons jouees sur la fiche joueur (Task 17).

Les appelants (PlayerController, PlayerApiController) seront mis a
jour dans leurs propres taches ; certains tests restent rouges entre
les deux, c'est attendu.
EOF
)"
```

---

### Task 10: `MatchRepository`, filtrage `season_id`

**Files:**
- Modify: `php/repositories/MatchRepository.php`
- Modify: `tests/unit/MatchRepositoryTest.php`

**Interfaces:**
- Produces: `MatchRepository::paginate(int $seasonId, int $page, int $perPage, ?int $competitionId, ?string $result, int $psgTeamId): array`, `::seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array`, `::cumulativePoints(int $seasonId, int $psgTeamId, int $competitionId): array`, `::recentDetailed(int $seasonId, int $psgTeamId, int $limit): array`. `find()` et `recent()` restent inchangées (id déjà scopé par saison ; `recent()` n'a aucun appelant dans l'app, laissée telle quelle : hors périmètre).

- [ ] **Step 1: Étendre le test (échoue : signatures actuelles)**

Remplacer entièrement `tests/unit/MatchRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie le bilan Ligue 1 calculé en une requête (V/N/D, buts, clean sheets,
// possession), filtré par saison.
final class MatchRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE competitions (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT, psg_possession REAL)');
        $pdo->exec("INSERT INTO competitions VALUES (1,'Ligue 1'),(2,'Coupe de France')");
        // PSG (id 1) : mêmes six issues que CompetitionRepositoryTest, un seul clean sheet
        // (match 2, où PSG encaisse 0), possession partiellement renseignée (NULL sur 3 matchs).
        // Tous saison_id=10, sauf le match 8 (saison_id=20) qui doit être exclu par le filtre.
        $pdo->exec(
            'INSERT INTO matches (id,season_id,competition_id,home_team_id,away_team_id,home_goals,away_goals,psg_possession) VALUES
            (1,10,1,1,2,3,1,60),   -- victoire PSG à domicile, encaisse 1
            (2,10,1,3,1,0,2,NULL), -- victoire PSG à l\'extérieur, encaisse 0 (clean sheet)
            (3,10,1,1,4,0,2,55),   -- défaite PSG à domicile, encaisse 2
            (4,10,1,2,1,3,0,NULL), -- défaite PSG à l\'extérieur, encaisse 3
            (5,10,1,1,3,1,1,50),   -- nul PSG à domicile, encaisse 1
            (6,10,1,4,1,2,2,NULL), -- nul PSG à l\'extérieur, encaisse 2
            (7,10,2,1,5,4,0,99),   -- Coupe de France : exclue du bilan Ligue 1
            (8,20,1,1,2,9,0,10)'   -- autre saison : doit être totalement exclue
        );
        return $pdo;
    }

    public function testSeasonRecordCalculeLeBilanLigue1DeLaSaisonDemandee(): void
    {
        $repo = new MatchRepository($this->pdo());
        $record = $repo->seasonRecord(10, 1, 1);

        $this->assertSame(6, $record['played'], 'seule Ligue 1, saison 10, est comptée');
        $this->assertSame(2, $record['wins']);
        $this->assertSame(2, $record['draws']);
        $this->assertSame(2, $record['losses']);
        $this->assertSame(8, $record['goals_for']);
        $this->assertSame(9, $record['goals_against']);
        $this->assertSame(1, $record['clean_sheets'], 'un seul match où PSG encaisse 0');
        $this->assertSame(55.0, $record['avg_possession'], 'moyenne sur les seules valeurs renseignées (60+55+50)/3');
    }

    public function testSeasonRecordIgnoreLesAutresSaisons(): void
    {
        $repo = new MatchRepository($this->pdo());
        $record = $repo->seasonRecord(20, 1, 1);
        $this->assertSame(1, $record['played'], 'seul le match de la saison 20 est compté');
    }

    public function testSeasonRecordGereUnePossessionEntierementNulle(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('UPDATE matches SET psg_possession = NULL WHERE season_id = 10');
        $repo = new MatchRepository($pdo);
        $record = $repo->seasonRecord(10, 1, 1);

        $this->assertSame(0.0, $record['avg_possession'], 'COALESCE ramène à 0 quand tout est NULL');
    }

    // Cumul pur : deux victoires, un nul, une défaite depuis le point de vue PSG (id 1).
    // 3, 6, 7, 7 points ; le résultat suit le score et les journées sont numérotées.
    // accumulatePoints() est une fonction pure sur des lignes déjà filtrées : pas de
    // season_id à tester ici, le filtre vit dans la requête SQL de cumulativePoints().
    public function testAccumulatePointsCumuleLesPointsParJournee(): void
    {
        $rows = [
            ['round_label' => 'J1', 'home_team_id' => 1, 'away_team_id' => 2, 'home_goals' => 2, 'away_goals' => 0],
            ['round_label' => 'J2', 'home_team_id' => 3, 'away_team_id' => 1, 'home_goals' => 0, 'away_goals' => 1],
            ['round_label' => 'J3', 'home_team_id' => 1, 'away_team_id' => 4, 'home_goals' => 1, 'away_goals' => 1],
            ['round_label' => 'J4', 'home_team_id' => 5, 'away_team_id' => 1, 'home_goals' => 3, 'away_goals' => 0],
        ];
        $series = MatchRepository::accumulatePoints($rows, 1);

        $this->assertCount(4, $series);
        $this->assertSame([1, 3, 'W', 'J1'], [$series[0]['x'], $series[0]['y'], $series[0]['result'], $series[0]['label']]);
        $this->assertSame(6, $series[1]['y'], 'victoire à l\'extérieur : +3');
        $this->assertSame(7, $series[2]['y'], 'match nul : +1');
        $this->assertSame('L', $series[3]['result'], 'défaite : résultat L');
        $this->assertSame(7, $series[3]['y'], 'la défaite n\'ajoute aucun point');
    }

    // Fixture d'une saison Ligue 1 complète (34 journées) pour cumulativePoints() : la
    // méthode réellement appelée par DashboardController (requête SQL + tri + filtre),
    // pas seulement accumulatePoints() sur des données synthétiques.
    private function pdoSaison(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(
            'CREATE TABLE matches (
                id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT,
                home_goals INT, away_goals INT, round_label TEXT, played_at TEXT
            )'
        );

        // 34 journées, PSG (id 1) à domicile face à l'id 2 : 24 victoires, 4 nuls, 6 défaites,
        // soit 24*3 + 4*1 = 76 points (référence du brief : "total attendu 76"). Saison 10.
        $results = [
            'W', 'W', 'D', 'W', 'W', 'L', 'W', 'W', 'D', 'W', 'W', 'L',
            'W', 'W', 'D', 'W', 'W', 'L', 'W', 'W', 'D', 'W', 'W', 'L',
            'W', 'W', 'W', 'L', 'W', 'W', 'W', 'L', 'W', 'W',
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO matches (id, season_id, competition_id, home_team_id, away_team_id, home_goals, away_goals, round_label, played_at)
             VALUES (:id, 10, 1, 1, 2, :hg, :ag, :label, :date)'
        );
        foreach ($results as $i => $result) {
            $matchday = $i + 1;
            [$hg, $ag] = match ($result) {
                'W' => [2, 0],
                'D' => [1, 1],
                'L' => [0, 2],
            };
            $stmt->execute([
                'id'    => $matchday,
                'hg'    => $hg,
                'ag'    => $ag,
                'label' => "J{$matchday}",
                'date'  => date('Y-m-d', strtotime('2023-08-13') + $i * 7 * 86400),
            ]);
        }

        // Bruit : match Ligue 1 sans PSG (doit être exclu par le filtre équipe).
        $pdo->exec(
            "INSERT INTO matches (id, season_id, competition_id, home_team_id, away_team_id, home_goals, away_goals, round_label, played_at)
             VALUES (100, 10, 1, 3, 4, 1, 1, 'Bruit', '2023-09-10')"
        );
        // Bruit : match PSG en Coupe de France (doit être exclu par le filtre compétition).
        $pdo->exec(
            "INSERT INTO matches (id, season_id, competition_id, home_team_id, away_team_id, home_goals, away_goals, round_label, played_at)
             VALUES (101, 10, 2, 1, 5, 4, 0, 'Bruit', '2023-09-11')"
        );
        // Bruit : même adversaire, même score, mais une autre saison (doit être exclu).
        $pdo->exec(
            "INSERT INTO matches (id, season_id, competition_id, home_team_id, away_team_id, home_goals, away_goals, round_label, played_at)
             VALUES (102, 20, 1, 1, 2, 2, 0, 'J1', '2024-08-13')"
        );
        return $pdo;
    }

    public function testCumulativePointsTotaliseLaSaisonLigue1Demandee(): void
    {
        $repo = new MatchRepository($this->pdoSaison());
        $series = $repo->cumulativePoints(10, 1, 1);

        $this->assertCount(34, $series, 'les 34 journées de la saison 10, le bruit hors équipe/compétition/saison est exclu');
        $dernier = $series[count($series) - 1];
        $this->assertSame(34, $dernier['x'], 'la dernière journée est bien la 34e');
        $this->assertSame(76, $dernier['y'], 'total cumulé de la saison : 24V*3 + 4N*1 + 6D*0 = 76 pts');
    }

    public function testPaginateFiltreParSaison(): void
    {
        $repo = new MatchRepository($this->pdo());
        $res = $repo->paginate(10, 1, 20, null, null, 1);
        $this->assertSame(7, $res['total'], 'les 7 matchs de la saison 10 (Ligue 1 + Coupe de France)');

        $res20 = $repo->paginate(20, 1, 20, null, null, 1);
        $this->assertSame(1, $res20['total'], 'un seul match dans la saison 20');
    }

    public function testRecentDetailedFiltreParSaison(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO teams VALUES (1,'PSG'),(2,'Marseille'),(3,'Lyon'),(4,'Lens'),(5,'Nice')");
        $repo = new MatchRepository($pdo);
        $recent = $repo->recentDetailed(10, 1, 20);
        $this->assertSame(7, count($recent), 'les 7 matchs PSG de la saison 10 uniquement');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `seasonRecord`/`cumulativePoints`/`paginate`/`recentDetailed` incompatible).

- [ ] **Step 3: Modifier `MatchRepository`**

Dans `php/repositories/MatchRepository.php`, appliquer ces remplacements (le fichier garde sa structure, seules les méthodes listées changent de signature et de requête) :

```php
    public function paginate(int $seasonId, int $page, int $perPage, ?int $competitionId, ?string $result, int $psgTeamId): array
    {
        [$conditions, $params] = $this->buildFilters($seasonId, $competitionId, $result, $psgTeamId);
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM matches {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM matches {$where} ORDER BY played_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(MatchGame::fromRow(...), $rows), 'total' => $total];
    }

    // Bilan de PSG pour une saison et une compétition données, en une requête
    // portable : V/N/D, buts, clean sheets, possession moyenne.
    public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array
    {
        $row = $this->fetchOne(
            "SELECT
                SUM(CASE
                    WHEN (m.home_team_id = :psg1 AND m.home_goals > m.away_goals)
                      OR (m.away_team_id = :psg2 AND m.away_goals > m.home_goals) THEN 1 ELSE 0
                END) wins,
                SUM(CASE WHEN m.home_goals = m.away_goals THEN 1 ELSE 0 END) draws,
                SUM(CASE
                    WHEN (m.home_team_id = :psg3 AND m.home_goals < m.away_goals)
                      OR (m.away_team_id = :psg4 AND m.away_goals < m.home_goals) THEN 1 ELSE 0
                END) losses,
                SUM(CASE WHEN m.home_team_id = :psg5 THEN m.home_goals ELSE m.away_goals END) goals_for,
                SUM(CASE WHEN m.home_team_id = :psg6 THEN m.away_goals ELSE m.home_goals END) goals_against,
                SUM(CASE
                    WHEN (m.home_team_id = :psg7 AND m.away_goals = 0)
                      OR (m.away_team_id = :psg8 AND m.home_goals = 0) THEN 1 ELSE 0
                END) clean_sheets,
                COALESCE(AVG(m.psg_possession), 0) avg_possession,
                COUNT(*) played
             FROM matches m
             WHERE m.season_id = :season AND m.competition_id = :comp AND (m.home_team_id = :psg9 OR m.away_team_id = :psg10)",
            [
                'psg1' => $psgTeamId, 'psg2' => $psgTeamId, 'psg3' => $psgTeamId, 'psg4' => $psgTeamId,
                'psg5' => $psgTeamId, 'psg6' => $psgTeamId, 'psg7' => $psgTeamId, 'psg8' => $psgTeamId,
                'psg9' => $psgTeamId, 'psg10' => $psgTeamId, 'comp' => $competitionId, 'season' => $seasonId,
            ]
        ) ?? [];

        return [
            'wins'           => (int) ($row['wins'] ?? 0),
            'draws'          => (int) ($row['draws'] ?? 0),
            'losses'         => (int) ($row['losses'] ?? 0),
            'goals_for'      => (int) ($row['goals_for'] ?? 0),
            'goals_against'  => (int) ($row['goals_against'] ?? 0),
            'clean_sheets'   => (int) ($row['clean_sheets'] ?? 0),
            'avg_possession' => (float) ($row['avg_possession'] ?? 0),
            'played'         => (int) ($row['played'] ?? 0),
        ];
    }

    public function cumulativePoints(int $seasonId, int $psgTeamId, int $competitionId): array
    {
        $rows = $this->fetchAll(
            'SELECT round_label, home_team_id, away_team_id, home_goals, away_goals
             FROM matches
             WHERE season_id = :season AND competition_id = :comp AND (home_team_id = :psg1 OR away_team_id = :psg2)
             ORDER BY played_at ASC, id ASC',
            ['season' => $seasonId, 'comp' => $competitionId, 'psg1' => $psgTeamId, 'psg2' => $psgTeamId]
        );
        return self::accumulatePoints($rows, $psgTeamId);
    }
```

`accumulatePoints()` ne change pas (fonction pure sur des lignes déjà filtrées par la requête ci-dessus).

```php
    public function recentDetailed(int $seasonId, int $psgTeamId, int $limit): array
    {
        $rows = $this->fetchAll(
            "SELECT m.home_team_id, m.away_team_id, m.home_goals, m.away_goals,
                    c.name comp_name, ht.name home_name, at.name away_name
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             JOIN competitions c ON c.id = m.competition_id
             WHERE m.season_id = :season AND (m.home_team_id = :psg1 OR m.away_team_id = :psg2)
             ORDER BY m.played_at DESC, m.id DESC
             LIMIT " . (int) $limit,
            ['season' => $seasonId, 'psg1' => $psgTeamId, 'psg2' => $psgTeamId]
        );
        return array_map(static function (array $r) use ($psgTeamId): array {
            $home = (int) $r['home_team_id'] === $psgTeamId;
            $goalsFor = $home ? (int) $r['home_goals'] : (int) $r['away_goals'];
            $goalsAgainst = $home ? (int) $r['away_goals'] : (int) $r['home_goals'];
            $result = $goalsFor > $goalsAgainst ? 'W' : ($goalsFor === $goalsAgainst ? 'D' : 'L');
            return [
                'competition'  => (string) $r['comp_name'],
                'opponent'     => $home ? (string) $r['away_name'] : (string) $r['home_name'],
                'home'         => $home,
                'goalsFor'     => $goalsFor,
                'goalsAgainst' => $goalsAgainst,
                'result'       => $result,
            ];
        }, $rows);
    }

    private function buildFilters(int $seasonId, ?int $competitionId, ?string $result, int $psgTeamId): array
    {
        $conditions = ['season_id = :season'];
        $params = ['season' => $seasonId];
        if ($competitionId !== null) {
            $conditions[] = 'competition_id = :competition';
            $params['competition'] = $competitionId;
        }
        if ($result !== null) {
            $conditions[] = self::resultCase() . ' = :result';
            $params['psg_home'] = $psgTeamId;
            $params['psg_away'] = $psgTeamId;
            $params['result'] = $result;
        }
        return [$conditions, $params];
    }
```

`resultCase()`, `find()` et `recent()` restent inchangées.

- [ ] **Step 4: Lancer la suite pour vérifier `MatchRepositoryTest`**

Run: `php tests/run.php`
Expected: `MatchRepositoryTest` passe entièrement. `MatchControllerTest`, `MatchApiControllerTest`, `DashboardControllerTest` (implicite via `HomeControllerTest`/`KpiServiceTest`) échouent temporairement sur l'arité de leurs doublures : attendu, résolu aux Tasks 13, 15, 16, 18, 21.

- [ ] **Step 5: Commit**

```bash
git add php/repositories/MatchRepository.php tests/unit/MatchRepositoryTest.php
git commit -m "$(cat <<'EOF'
feat(matchs): MatchRepository filtre paginate/seasonRecord/cumulativePoints/recentDetailed par season_id

season_id devient le premier parametre explicite de chaque methode
d'agregation, dans la continuite de psgId/leagueId deja explicites.
find() et recent() restent inchangees (recent() n'a aucun appelant,
hors perimetre).
EOF
)"
```

---

### Task 11: `StatisticRepository`, filtrage `season_id` sur les agrégats

**Files:**
- Modify: `php/repositories/StatisticRepository.php`
- Modify: `tests/unit/StatisticRepositoryTest.php`

**Interfaces:**
- Produces: `StatisticRepository::topScorers(int $seasonId, int $limit, ?int $competitionId): array`, `::squadAxisMax(int $seasonId): array`, `::goalsByPlayerAndMonth(int $seasonId): array`. `seasonTotalsByPlayer()`, `timeline()`, `byMatch()` restent inchangées (déjà scopées par `player_id`/`match_id`, qui impliquent une seule saison).

- [ ] **Step 1: Étendre le test (échoue : signatures actuelles)**

Remplacer `tests/unit/StatisticRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie l'agrégat des buteurs (SUM groupé, tri, filtre compétition et saison).
final class StatisticRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT, assists INT, minutes INT, shots INT, duels_won INT, rating REAL)");
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0),(2,1,2,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (1,1,1),(2,1,1),(3,2,1)");
        // Match 3 appartient à la saison 2 : ses stats ne doivent jamais compter dans les agrégats saison 1.
        $pdo->exec("INSERT INTO player_match_stats (player_id,match_id,goals,assists,minutes,shots,duels_won,rating) VALUES (1,1,2,1,90,5,3,7.5),(1,2,1,0,90,3,2,6.8),(2,1,1,2,80,4,4,7.0),(1,3,9,9,90,9,9,9.9)");
        return $pdo;
    }

    public function testTopScorersOrdonneEtFiltreParSaison(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(1, 5, null);
        $this->assertSame('Barcola', $top[0]['player']->lastName);
        $this->assertSame(3, $top[0]['goals'], 'saison 1 uniquement (2+1), le but de la saison 2 est exclu');
        $this->assertSame('Dembélé', $top[1]['player']->lastName);
    }

    public function testTopScorersAutreSaisonNeVoitQueSonMatch(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(2, 5, null);
        $this->assertCount(1, $top);
        $this->assertSame(9, $top[0]['goals']);
    }

    public function testSquadAxisMaxFiltreParSaison(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $max = $repo->squadAxisMax(1);
        $this->assertSame(3.0, $max['goals'], 'meilleur total de but sur la saison 1 (Barcola : 2+1)');
    }

    private function pdoComplet(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, played_at TEXT)');
        $pdo->exec(
            'CREATE TABLE player_match_stats (
                id INTEGER PRIMARY KEY, player_id INT, match_id INT, is_starter INT, minutes INT,
                goals INT, assists INT, shots INT, shots_on_target INT, passes INT, pass_accuracy REAL,
                duels_won INT, interceptions INT, yellow_cards INT, red_card INT, saves INT, goals_conceded INT,
                rating REAL, xg REAL, xag REAL, source_id INT
            )'
        );
        return $pdo;
    }

    public function testByMatchAssocieIdentiteJoueurSansEcraserIdDuStat(): void
    {
        $pdo = $this->pdoComplet();
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,2,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (10,1,'2025-01-01')");
        $pdo->exec(
            'INSERT INTO player_match_stats VALUES
            (100,1,10,1,90,2,1,4,2,38,80.5,5,4,0,0,0,0,7.5,0.6,0.3,1),
            (101,2,10,1,70,0,2,1,0,20,70.0,3,2,1,0,0,0,6.8,0.1,0.4,1)'
        );

        $repo = new StatisticRepository($pdo);
        $rows = $repo->byMatch(10);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['player']->id, 'identité du joueur');
        $this->assertSame('Barcola', $rows[0]['player']->lastName);
        $this->assertSame(100, $rows[0]['stat']->id, "l'id du stat n'est pas écrasé par l'id du joueur");
        $this->assertSame(1, $rows[0]['stat']->playerId);
        $this->assertSame(2, $rows[0]['stat']->goals);
        $this->assertSame(1, $rows[0]['stat']->assists);

        $this->assertSame(2, $rows[1]['player']->id, 'identité du joueur');
        $this->assertSame('Dembélé', $rows[1]['player']->lastName);
        $this->assertSame(101, $rows[1]['stat']->id, "l'id du stat n'est pas écrasé par l'id du joueur");
        $this->assertSame(2, $rows[1]['stat']->playerId);
        $this->assertSame(0, $rows[1]['stat']->goals);
        $this->assertSame(2, $rows[1]['stat']->assists);
    }

    public function testTimelineExposeLesPassesDecisivesPasLesPassesTotales(): void
    {
        $pdo = $this->pdoComplet();
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO matches VALUES (20,1,'2025-02-01')");
        $pdo->exec(
            "INSERT INTO player_match_stats VALUES
            (200,1,20,1,90,1,3,4,2,45,80.0,5,3,0,0,0,0,7.2,0.5,0.4,1)"
        );

        $repo = new StatisticRepository($pdo);
        $rows = $repo->timeline(1);

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['assists'], 'expose les passes décisives (assists), pas les passes totales');
        $this->assertTrue(!array_key_exists('passes', $rows[0]), "ne conserve pas une clé 'passes' qui contiendrait en réalité les assists");
    }

    public function testGoalsByPlayerAndMonthFiltreParSaison(): void
    {
        $pdo = $this->pdo();
        $rows = (new StatisticRepository($pdo))->goalsByPlayerAndMonth(1);
        $total = array_sum(array_column($rows, 'goals'));
        $this->assertSame(4, $total, 'saison 1 uniquement : 2+1 (Barcola) + 1 (Dembélé), le 9 de la saison 2 est exclu');
    }
}
```

Note : `goalsByPlayerAndMonth()` groupe par mois via `SUBSTR(played_at,1,7)` (voir code source) ; comme la fixture ci-dessus ne renseigne pas `played_at` sur la table `matches` créée dans `pdo()`, ajouter la colonne et une valeur non nulle est nécessaire pour ce test. Ajuster `pdo()` dans le Step 1 : ajouter `played_at TEXT` à la définition de `matches` et une date à chaque `INSERT INTO matches` (ex. `(1,1,1,'2025-08-01'),(2,1,1,'2025-08-02'),(3,2,1,'2025-09-01')`).

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `topScorers`/`squadAxisMax`/`goalsByPlayerAndMonth` incompatible).

- [ ] **Step 3: Modifier `StatisticRepository`**

Dans `php/repositories/StatisticRepository.php`, remplacer les trois méthodes concernées :

```php
    public function topScorers(int $seasonId, int $limit, ?int $competitionId): array
    {
        $compFilter = $competitionId !== null ? ' AND m.competition_id = :comp' : '';
        $params = ['season' => $seasonId] + ($competitionId !== null ? ['comp' => $competitionId] : []);
        $rows = $this->fetchAll(
            "SELECT p.*, SUM(s.goals) goals, SUM(s.assists) assists, SUM(s.minutes) minutes
             FROM player_match_stats s
             JOIN players p ON p.id = s.player_id
             JOIN matches m ON m.id = s.match_id
             WHERE m.season_id = :season{$compFilter}
             GROUP BY p.id
             HAVING SUM(s.goals) > 0
             ORDER BY goals DESC, assists DESC
             LIMIT {$limit}",
            $params
        );
        return array_map(static function (array $r): array {
            return [
                'player'  => Player::fromRow($r),
                'goals'   => (int) $r['goals'],
                'assists' => (int) $r['assists'],
                'minutes' => (int) $r['minutes'],
            ];
        }, $rows);
    }
```

```php
    // Maxima de l'effectif par axe du radar de profil, pour une saison donnée : chaque
    // axe est le meilleur total d'un joueur de cette saison, pour normaliser un profil
    // (valeur du joueur / meilleur total de l'axe) sur une lecture de 0 à 1.
    public function squadAxisMax(int $seasonId): array
    {
        $row = $this->fetchOne(
            'SELECT MAX(g) goals, MAX(a) assists, MAX(mn) minutes, MAX(sh) shots,
                    MAX(dw) duels_won, MAX(rt) rating
             FROM (
                 SELECT SUM(s.goals) g, SUM(s.assists) a, SUM(s.minutes) mn, SUM(s.shots) sh,
                        SUM(s.duels_won) dw, AVG(s.rating) rt
                 FROM player_match_stats s
                 JOIN matches m ON m.id = s.match_id
                 WHERE m.season_id = :season
                 GROUP BY s.player_id
             ) t',
            ['season' => $seasonId]
        ) ?? [];

        return [
            'goals'    => (float) ($row['goals'] ?? 0),
            'assists'  => (float) ($row['assists'] ?? 0),
            'minutes'  => (float) ($row['minutes'] ?? 0),
            'shots'    => (float) ($row['shots'] ?? 0),
            'duelsWon' => (float) ($row['duels_won'] ?? 0),
            'rating'   => (float) ($row['rating'] ?? 0),
        ];
    }
```

```php
    // Buts marqués par joueur et par mois, pour une saison donnée ; regroupement
    // portable via SUBSTR (les 7 premiers caractères de played_at, format AAAA-MM-JJ).
    public function goalsByPlayerAndMonth(int $seasonId): array
    {
        $rows = $this->fetchAll(
            "SELECT s.player_id, SUBSTR(m.played_at, 1, 7) month, SUM(s.goals) goals
             FROM player_match_stats s
             JOIN matches m ON m.id = s.match_id
             WHERE m.season_id = :season
             GROUP BY s.player_id, SUBSTR(m.played_at, 1, 7)
             HAVING SUM(s.goals) > 0
             ORDER BY month, s.player_id",
            ['season' => $seasonId]
        );
        return array_map(static function (array $r): array {
            return [
                'playerId' => (int) $r['player_id'],
                'month'    => (string) $r['month'],
                'goals'    => (int) $r['goals'],
            ];
        }, $rows);
    }
```

`seasonTotalsByPlayer()`, `timeline()` et `byMatch()` restent inchangées.

- [ ] **Step 4: Lancer la suite pour vérifier `StatisticRepositoryTest`**

Run: `php tests/run.php`
Expected: `StatisticRepositoryTest` passe entièrement. `KpiServiceTest`, `HomeControllerTest`, `PlayerControllerTest`, `StatsApiControllerTest`, `ExportApiControllerTest` échouent temporairement sur l'arité de leurs doublures : attendu, résolu aux Tasks 13, 15-17, 21.

- [ ] **Step 5: Commit**

```bash
git add php/repositories/StatisticRepository.php tests/unit/StatisticRepositoryTest.php
git commit -m "$(cat <<'EOF'
feat(stats): StatisticRepository filtre topScorers/squadAxisMax/goalsByPlayerAndMonth par season_id

Ces trois methodes agregent sur l'ensemble des joueurs ou des matchs :
elles ont besoin de season_id (via jointure matches). seasonTotalsByPlayer,
timeline et byMatch restent inchangees, deja scopees par un id qui
implique une seule saison.
EOF
)"
```

---

### Task 12: `PlayerSeasonStatsRepository`, `CompetitionRepository`, `SourceRepository`, filtrage `season_id`

**Files:**
- Modify: `php/repositories/PlayerSeasonStatsRepository.php`
- Modify: `php/repositories/CompetitionRepository.php`
- Modify: `php/repositories/SourceRepository.php`
- Modify: `tests/unit/CompetitionRepositoryTest.php`
- Test: `tests/unit/PlayerSeasonStatsRepositoryTest.php` (nouveau, `all()` n'avait pas de test dédié jusqu'ici, seulement via `PlayerSeasonStatsTest` qui teste `forPlayer()`)
- Test: `tests/unit/SourceRepositoryTest.php` (nouveau, `coverageByTable()` n'avait pas de test dédié jusqu'ici)

**Interfaces:**
- Produces: `PlayerSeasonStatsRepository::all(int $seasonId): array`, `CompetitionRepository::standings(int $seasonId, int $psgTeamId): array`, `SourceRepository::coverageByTable(int $seasonId): array`. `forPlayer()`, `all()`/`leagueId()` de `CompetitionRepository`, et `sources()` restent inchangées (catalogues partagés ou déjà scopées par id).

- [ ] **Step 1: Écrire les tests (échouent : signatures actuelles / méthodes non testées)**

Créer `tests/unit/PlayerSeasonStatsRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie que all() filtre par saison (forPlayer() est déjà couvert par
// PlayerSeasonStatsTest via une migration réelle).
final class PlayerSeasonStatsRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE player_season_stats (id INTEGER PRIMARY KEY, player_id INT, season_id INT, appearances INT, starts INT, goals INT, assists INT, yellow_cards INT, red_cards INT, source_id INT)');
        $pdo->exec('INSERT INTO player_season_stats (player_id,season_id,appearances,starts,goals,assists,yellow_cards,red_cards,source_id) VALUES
            (1,1,49,35,13,6,3,0,1),
            (2,1,30,20,2,1,1,0,1),
            (3,2,10,10,4,0,0,0,1)');
        return $pdo;
    }

    public function testAllFiltreParSaisonEtTrieParButs(): void
    {
        $repo = new PlayerSeasonStatsRepository($this->pdo());
        $bilans = $repo->all(1);
        $this->assertCount(2, $bilans, 'saison 1 uniquement');
        $this->assertSame(13, $bilans[1]->goals, 'indexé par player_id, Barcola (id 1) en tête');
    }

    public function testAllAutreSaisonNeVoitQueSonJoueur(): void
    {
        $repo = new PlayerSeasonStatsRepository($this->pdo());
        $bilans = $repo->all(2);
        $this->assertCount(1, $bilans);
        $this->assertSame(4, $bilans[3]->goals);
    }
}
```

Remplacer `tests/unit/CompetitionRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie le bilan V/N/D et les buts, en particulier la logique CASE côté
// domicile/extérieur, filtré par saison.
final class CompetitionRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE competitions (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT)');
        $pdo->exec("INSERT INTO competitions VALUES (1,'Ligue 1')");
        // PSG (id 1) alterne domicile/extérieur sur les trois issues possibles, saison 10.
        // Le match 7 (saison 20) doit être exclu du bilan de la saison 10.
        $pdo->exec(
            'INSERT INTO matches (id,season_id,competition_id,home_team_id,away_team_id,home_goals,away_goals) VALUES
            (1,10,1,1,2,3,1),  -- victoire PSG à domicile
            (2,10,1,3,1,0,2),  -- victoire PSG à l\'extérieur
            (3,10,1,1,4,0,2),  -- défaite PSG à domicile
            (4,10,1,2,1,3,0),  -- défaite PSG à l\'extérieur
            (5,10,1,1,3,1,1),  -- nul PSG à domicile
            (6,10,1,4,1,2,2),  -- nul PSG à l\'extérieur
            (7,20,1,1,2,5,0)   -- autre saison : exclu'
        );
        return $pdo;
    }

    public function testStandingsCalculeVNDEtButsSelonLeCoteEtLaSaison(): void
    {
        $repo = new CompetitionRepository($this->pdo());
        $rows = $repo->standings(10, 1);

        $this->assertCount(1, $rows, 'une seule compétition');
        $row = $rows[0];

        $this->assertSame(1, $row['competitionId']);
        $this->assertSame('Ligue 1', $row['competitionName']);
        $this->assertSame(2, $row['wins'], 'victoires domicile + extérieur, saison 10 uniquement');
        $this->assertSame(2, $row['draws'], 'nuls domicile + extérieur');
        $this->assertSame(2, $row['losses'], 'défaites domicile + extérieur');
        $this->assertSame(8, $row['goalsFor'], 'buts marqués selon le côté de PSG, sans le match de la saison 20');
        $this->assertSame(9, $row['goalsAgainst'], 'buts encaissés selon le côté de PSG');
    }
}
```

Créer `tests/unit/SourceRepositoryTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie coverageByTable() : taux de vérification filtré par saison (sources()
// reste un catalogue global, déjà couvert par MethodologyControllerTest via une
// doublure).
final class SourceRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE data_sources (id INTEGER PRIMARY KEY, confidence TEXT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, source_id INT)");
        $pdo->exec("CREATE TABLE player_season_stats (id INTEGER PRIMARY KEY, season_id INT, source_id INT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, match_id INT, source_id INT)");
        $pdo->exec("INSERT INTO data_sources VALUES (1,'verified'),(2,'estimated')");
        // Saison 10 : 2 matchs vérifiés. Saison 20 : 1 match, non vérifié (ne doit pas compter dans la saison 10).
        $pdo->exec("INSERT INTO matches VALUES (1,10,1),(2,10,1),(3,20,2)");
        $pdo->exec("INSERT INTO player_season_stats VALUES (1,10,1),(2,20,2)");
        $pdo->exec("INSERT INTO player_match_stats VALUES (1,1,2),(2,2,2)");
        return $pdo;
    }

    public function testCoverageByTableFiltreParSaison(): void
    {
        $repo = new SourceRepository($this->pdo());
        $rows = $repo->coverageByTable(10);
        $byLabel = [];
        foreach ($rows as $r) {
            $byLabel[$r['label']] = $r;
        }
        $this->assertSame(2, $byLabel['Matchs (score, possession, affluence)']['total'], 'seule la saison 10 est comptée');
        $this->assertSame(100, $byLabel['Matchs (score, possession, affluence)']['pct']);
        $this->assertSame(1, $byLabel['Bilans joueurs (toutes compétitions)']['total']);
        $this->assertSame(2, $byLabel['Statistiques par match (attribution)']['total'], 'via jointure matches, filtré saison 10');
        $this->assertSame(0, $byLabel['Statistiques par match (attribution)']['pct'], 'source estimée');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `standings()` incompatible, `all(int $seasonId)`/`coverageByTable(int $seasonId)` inexistantes).

- [ ] **Step 3: Modifier `PlayerSeasonStatsRepository`**

Remplacer `php/repositories/PlayerSeasonStatsRepository.php` :

```php
<?php
declare(strict_types=1);
// Accès aux bilans de saison par joueur (données vérifiées, toutes compétitions).
final class PlayerSeasonStatsRepository extends Repository
{
    public function forPlayer(int $playerId): ?PlayerSeasonStats
    {
        $row = $this->fetchOne('SELECT * FROM player_season_stats WHERE player_id = ?', [$playerId]);
        return $row ? PlayerSeasonStats::fromRow($row) : null;
    }

    /** @return array<int,PlayerSeasonStats> indexé par player_id, du plus prolifique au moins, pour une saison donnée */
    public function all(int $seasonId): array
    {
        $bilans = [];
        foreach ($this->fetchAll('SELECT * FROM player_season_stats WHERE season_id = ? ORDER BY goals DESC, assists DESC', [$seasonId]) as $row) {
            $bilans[(int) $row['player_id']] = PlayerSeasonStats::fromRow($row);
        }
        return $bilans;
    }
}
```

- [ ] **Step 4: Modifier `CompetitionRepository::standings`**

Dans `php/repositories/CompetitionRepository.php`, remplacer `standings` :

```php
    public function standings(int $seasonId, int $psgTeamId): array
    {
        $rows = $this->fetchAll(
            "SELECT c.id competition_id, c.name competition_name,
                    SUM(CASE
                        WHEN (m.home_team_id = :psg1 AND m.home_goals > m.away_goals)
                          OR (m.away_team_id = :psg2 AND m.away_goals > m.home_goals) THEN 1 ELSE 0
                    END) wins,
                    SUM(CASE WHEN m.home_goals = m.away_goals THEN 1 ELSE 0 END) draws,
                    SUM(CASE
                        WHEN (m.home_team_id = :psg3 AND m.home_goals < m.away_goals)
                          OR (m.away_team_id = :psg4 AND m.away_goals < m.home_goals) THEN 1 ELSE 0
                    END) losses,
                    SUM(CASE WHEN m.home_team_id = :psg5 THEN m.home_goals ELSE m.away_goals END) goals_for,
                    SUM(CASE WHEN m.home_team_id = :psg6 THEN m.away_goals ELSE m.home_goals END) goals_against
             FROM matches m
             JOIN competitions c ON c.id = m.competition_id
             WHERE m.season_id = :season
             GROUP BY c.id, c.name
             ORDER BY c.name",
            [
                'psg1' => $psgTeamId, 'psg2' => $psgTeamId, 'psg3' => $psgTeamId,
                'psg4' => $psgTeamId, 'psg5' => $psgTeamId, 'psg6' => $psgTeamId, 'season' => $seasonId,
            ]
        );

        return array_map(static function (array $r): array {
            return [
                'competitionId'   => (int) $r['competition_id'],
                'competitionName' => (string) $r['competition_name'],
                'wins'            => (int) $r['wins'],
                'draws'           => (int) $r['draws'],
                'losses'          => (int) $r['losses'],
                'goalsFor'        => (int) $r['goals_for'],
                'goalsAgainst'    => (int) $r['goals_against'],
            ];
        }, $rows);
    }
```

`all()` et `leagueId()` restent inchangées (catalogue partagé).

- [ ] **Step 5: Modifier `SourceRepository::coverageByTable`**

Dans `php/repositories/SourceRepository.php`, remplacer `coverageByTable` :

```php
    // Taux de vérification par table, pour une saison donnée : part des lignes
    // reliées à une source vérifiée. matches et player_season_stats portent
    // season_id directement ; player_match_stats est filtrée via une jointure matches.
    public function coverageByTable(int $seasonId): array
    {
        $queries = [
            'matches'             => "SELECT COUNT(*) total, SUM(CASE WHEN d.confidence = 'verified' THEN 1 ELSE 0 END) verified
                                       FROM matches t JOIN data_sources d ON d.id = t.source_id WHERE t.season_id = :season",
            'player_season_stats' => "SELECT COUNT(*) total, SUM(CASE WHEN d.confidence = 'verified' THEN 1 ELSE 0 END) verified
                                       FROM player_season_stats t JOIN data_sources d ON d.id = t.source_id WHERE t.season_id = :season",
            'player_match_stats'  => "SELECT COUNT(*) total, SUM(CASE WHEN d.confidence = 'verified' THEN 1 ELSE 0 END) verified
                                       FROM player_match_stats t
                                       JOIN data_sources d ON d.id = t.source_id
                                       JOIN matches m ON m.id = t.match_id
                                       WHERE m.season_id = :season",
        ];

        $out = [];
        foreach (self::TABLES as $table => $label) {
            $row = $this->fetchOne($queries[$table], ['season' => $seasonId]) ?? [];
            $total = (int) ($row['total'] ?? 0);
            $verified = (int) ($row['verified'] ?? 0);
            $out[] = [
                'label'    => $label,
                'total'    => $total,
                'verified' => $verified,
                'pct'      => $total > 0 ? (int) round($verified / $total * 100) : 0,
            ];
        }
        return $out;
    }
```

`sources()` reste inchangée (catalogue global : `data_sources` n'a pas de `season_id`).

- [ ] **Step 6: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `PlayerSeasonStatsRepositoryTest`, `CompetitionRepositoryTest`, `SourceRepositoryTest` passent. `CompetitionApiControllerTest` et `MethodologyControllerTest` échouent temporairement (arité) : attendu, résolu aux Tasks 19 et 21. `PlayerSeasonStatsTest`/`RankingServiceTest` (qui utilisent `run_migration` + `PlayerSeasonStatsRepository::all()` sans argument via `RankingService`) échouent aussi temporairement : résolu à la Task 14.

- [ ] **Step 7: Commit**

```bash
git add php/repositories/PlayerSeasonStatsRepository.php php/repositories/CompetitionRepository.php php/repositories/SourceRepository.php tests/unit/CompetitionRepositoryTest.php tests/unit/PlayerSeasonStatsRepositoryTest.php tests/unit/SourceRepositoryTest.php
git commit -m "$(cat <<'EOF'
feat(saisons): PlayerSeasonStatsRepository, CompetitionRepository, SourceRepository filtrent par season_id

all(), standings() et coverageByTable() agregent sur l'ensemble des
joueurs/matchs d'une saison : ils recoivent desormais season_id en
premier parametre. forPlayer(), all()/leagueId() de CompetitionRepository
et sources() restent inchanges (deja scopes par id ou catalogue global).
EOF
)"
```

---

### Task 13: `KpiService`, seasonId au constructeur

**Files:**
- Modify: `php/services/KpiService.php`
- Modify: `tests/unit/KpiServiceTest.php`
- Modify: `tests/unit/StatsApiControllerTest.php` (helper `kpiService()`)

**Interfaces:**
- Consumes: `StatisticRepository::topScorers(int $seasonId, ...)` (Task 11), `MatchRepository::seasonRecord(int $seasonId, ...)` (Task 10).
- Produces: `KpiService::__construct(StatisticRepository $stats, MatchRepository $matches, CompetitionRepository $comps, int $psgTeamId, int $seasonId)`. Consommé par Task 15 (`DashboardController`), Task 16 (`HomeController`), Task 21 (`StatsApiController`).

- [ ] **Step 1: Modifier le test (échoue : arité incompatible)**

Remplacer `tests/unit/KpiServiceTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie l'agrégation des KPI du tableau de bord depuis des repositories bouchonnés.
final class KpiServiceTest extends TestCase
{
    public function testDashboardAgregeLesKpi(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $competitionId): array {
                return [['player' => Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]), 'goals'=>11, 'assists'=>4, 'minutes'=>2400]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array { return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34]; }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        $kpi = new KpiService($stats, $matches, $comps, 1, 1);
        $d = $kpi->dashboard();
        $this->assertSame('Bradley Barcola', $d['top_scorer']['name']);
        $this->assertSame(24, $d['wins']);
        $this->assertSame(2.18, round($d['goals_per_match'], 2)); // 74/34
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`KpiService::__construct` : arité incompatible ; doublures `topScorers`/`seasonRecord` incompatibles avec la classe mère déjà modifiée aux Tasks 10-11).

- [ ] **Step 3: Modifier `KpiService`**

Remplacer `php/services/KpiService.php` :

```php
<?php
declare(strict_types=1);
// Agrège les indicateurs clés du tableau de bord depuis les repositories, pour une
// saison donnée, sans constante en dur.
final class KpiService
{
    public function __construct(
        private StatisticRepository $stats,
        private MatchRepository $matches,
        private CompetitionRepository $comps,
        private int $psgTeamId,
        private int $seasonId,
    ) {}

    public function dashboard(): array
    {
        $scorers = $this->stats->topScorers($this->seasonId, 1, null);
        $leagueId = $this->comps->leagueId() ?? 0;
        $record = $this->matches->seasonRecord($this->seasonId, $this->psgTeamId, $leagueId);
        $played = max(1, (int) $record['played']);
        $topScorer = $scorers[0] ?? null;

        return [
            'top_scorer'      => $topScorer ? ['name' => $topScorer['player']->fullName(), 'goals' => $topScorer['goals']] : null,
            'top_assister'    => $this->topAssister(),
            'wins'            => (int) $record['wins'],
            'draws'           => (int) $record['draws'],
            'losses'          => (int) $record['losses'],
            'clean_sheets'    => (int) $record['clean_sheets'],
            'avg_possession'  => round((float) $record['avg_possession'], 1),
            'goals_per_match' => round((int) $record['goals_for'] / $played, 2),
        ];
    }

    private function topAssister(): ?array
    {
        $rows = $this->stats->topScorers($this->seasonId, 50, null);
        usort($rows, static fn($a, $b) => $b['assists'] <=> $a['assists']);
        $best = $rows[0] ?? null;
        return $best ? ['name' => $best['player']->fullName(), 'assists' => $best['assists']] : null;
    }
}
```

- [ ] **Step 4: Mettre à jour la doublure `kpiService()` de `StatsApiControllerTest`**

Dans `tests/unit/StatsApiControllerTest.php`, méthode `kpiService()`, mettre à jour les signatures des doublures et l'appel au constructeur :

```php
    private function kpiService(): KpiService
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $competitionId): array {
                return [['player' => Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]), 'goals'=>11, 'assists'=>4, 'minutes'=>2400]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array {
                return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34];
            }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        return new KpiService($stats, $matches, $comps, 1, 1);
    }
```

Ne pas modifier `heatmapService()` ici (traité à la Task 14).

- [ ] **Step 5: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `KpiServiceTest` passe. `StatsApiControllerTest::testBuildKpisEnveloppeLeDashboard` passe aussi (il utilise `kpiService()`) ; `testBuildDistributionAgregeParMois`/`testBuildHeatmapRetourneLesMoisEtLignes` restent rouges (dépendent de `heatmapService()`, Task 14). `HomeControllerTest`, `DashboardController` (pas encore de test direct), `StatsApiController::buildKpiService()` (usage réel) restent rouges : attendu, résolu Tasks 15-16 et 21.

- [ ] **Step 6: Commit**

```bash
git add php/services/KpiService.php tests/unit/KpiServiceTest.php tests/unit/StatsApiControllerTest.php
git commit -m "$(cat <<'EOF'
feat(kpi): KpiService recoit seasonId au constructeur

dashboard() et topAssister() transmettent desormais la saison a
topScorers()/seasonRecord(), qui l'exigent depuis les taches
precedentes.
EOF
)"
```

---

### Task 14: `HeatmapService` et `RankingService`, seasonId sur la méthode d'agrégation

**Files:**
- Modify: `php/services/HeatmapService.php`
- Modify: `php/services/RankingService.php`
- Modify: `tests/unit/HeatmapServiceTest.php`
- Modify: `tests/unit/RankingServiceTest.php`
- Modify: `tests/unit/StatsApiControllerTest.php` (helper `heatmapService()`)

**Interfaces:**
- Consumes: `StatisticRepository::goalsByPlayerAndMonth(int $seasonId)` (Task 11), `PlayerSeasonStatsRepository::all(int $seasonId)` (Task 12).
- Produces: `HeatmapService::goalsByPlayerAndMonth(int $seasonId): array`, `RankingService::byMetric(int $seasonId, string $metric, int $limit): array`. Consommé par Task 21 (`StatsApiController`) pour Heatmap ; `RankingService` n'a aucun appelant applicatif actuel (seulement testé), aucune tâche ultérieure n'en dépend.

- [ ] **Step 1: Modifier les tests (échouent : arité incompatible)**

Remplacer `tests/unit/HeatmapServiceTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie la matrice buts par joueur et par mois (regroupement portable via SUBSTR),
// pour la saison 2025-26 issue d'une migration réelle.
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

final class HeatmapServiceTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testMatriceMoisTrieeEtSommeEgaleAuTotalDesButsIndividuels(): void
    {
        $pdo = $this->migratedPdo();
        $seasonId = (int) $pdo->query("SELECT id FROM seasons WHERE label = '2025-26'")->fetchColumn();
        $svc = new HeatmapService(new StatisticRepository($pdo), new PlayerRepository($pdo));

        $matrix = $svc->goalsByPlayerAndMonth($seasonId);

        $this->assertTrue(count($matrix['months']) > 1, 'la saison s\'étale sur plusieurs mois');
        $sorted = $matrix['months'];
        sort($sorted);
        $this->assertSame($sorted, $matrix['months'], 'mois triés par ordre chronologique');

        $total = 0;
        foreach ($matrix['rows'] as $row) {
            $total += array_sum($row['cells']);
            foreach ($matrix['months'] as $month) {
                $this->assertTrue(array_key_exists($month, $row['cells']), 'chaque ligne couvre tous les mois (0 par défaut)');
            }
        }
        $totalReel = (int) $pdo->query(
            "SELECT SUM(s.goals) FROM player_match_stats s JOIN matches m ON m.id = s.match_id WHERE m.season_id = {$seasonId}"
        )->fetchColumn();
        $this->assertSame($totalReel, $total, 'la somme de la matrice égale le total des buts individuels de la saison 2025-26');
    }
}
```

Remplacer `tests/unit/RankingServiceTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie le classement par métrique (liste blanche) sur les bilans saison
// vérifiés de 2025-26.
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

final class RankingServiceTest extends TestCase
{
    private function migratedPdoEtSaison(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        $seasonId = (int) $pdo->query("SELECT id FROM seasons WHERE label = '2025-26'")->fetchColumn();
        return [$pdo, $seasonId];
    }

    public function testClasseParButsVerifiesToutesCompetitions(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $top = $svc->byMetric($seasonId, 'goals', 3);

        $this->assertCount(3, $top);
        $this->assertSame('Dembélé', $top[0]['player']->lastName, 'meilleur buteur vérifié toutes comps');
        $this->assertSame(20, $top[0]['value']);
        $this->assertTrue($top[0]['value'] >= $top[1]['value'], 'tri décroissant');
        $this->assertTrue($top[1]['value'] >= $top[2]['value'], 'tri décroissant');
    }

    public function testRejetteUneMetriqueHorsListeBlanche(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $this->assertThrows(
            static fn () => $svc->byMetric($seasonId, 'DROP TABLE players; --', 5),
            InvalidArgumentException::class,
            'métrique non whitelistée rejetée'
        );
    }

    public function testClasseParContributionsButsPlusPasses(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $top = $svc->byMetric($seasonId, 'goal_contributions', 1);

        $this->assertSame('Dembélé', $top[0]['player']->lastName, '20 buts + 11 passes = 31');
        $this->assertSame(31, $top[0]['value']);
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `goalsByPlayerAndMonth`/`byMetric` incompatible).

- [ ] **Step 3: Modifier `HeatmapService`**

Remplacer `php/services/HeatmapService.php` :

```php
<?php
declare(strict_types=1);
// Construit une matrice buts marqués x (joueur, mois) pour affichage en heatmap,
// pour une saison donnée.
final class HeatmapService
{
    public function __construct(
        private StatisticRepository $stats,
        private PlayerRepository $players,
    ) {}

    public function goalsByPlayerAndMonth(int $seasonId): array
    {
        $months = [];
        $cellsByPlayer = [];
        foreach ($this->stats->goalsByPlayerAndMonth($seasonId) as $row) {
            if (!in_array($row['month'], $months, true)) {
                $months[] = $row['month'];
            }
            $cellsByPlayer[$row['playerId']][$row['month']] = $row['goals'];
        }
        sort($months);

        $rows = [];
        foreach ($cellsByPlayer as $playerId => $cells) {
            $player = $this->players->find($playerId);
            if ($player === null) {
                continue;
            }
            $filled = [];
            foreach ($months as $month) {
                $filled[$month] = $cells[$month] ?? 0;
            }
            $rows[] = ['player' => $player, 'cells' => $filled];
        }

        return ['months' => $months, 'rows' => $rows];
    }
}
```

- [ ] **Step 4: Modifier `RankingService`**

Remplacer `php/services/RankingService.php` :

```php
<?php
declare(strict_types=1);
// Classements de joueurs sur des métriques vérifiées, saison complète toutes
// compétitions, pour une saison donnée.
final class RankingService
{
    // Liste blanche : seules ces métriques peuvent être triées (jamais de tri sur clé brute).
    private const METRICS = ['goals', 'assists', 'goal_contributions', 'appearances', 'yellow_cards', 'red_cards'];

    public function __construct(
        private PlayerSeasonStatsRepository $seasonStats,
        private PlayerRepository $players,
    ) {}

    public function byMetric(int $seasonId, string $metric, int $limit): array
    {
        if (!in_array($metric, self::METRICS, true)) {
            throw new InvalidArgumentException("métrique de classement inconnue : {$metric}");
        }

        $rows = [];
        foreach ($this->seasonStats->all($seasonId) as $playerId => $bilan) {
            $player = $this->players->find($playerId);
            if ($player === null) {
                continue;
            }
            $rows[] = ['player' => $player, 'value' => $this->valueFor($bilan, $metric)];
        }

        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return array_slice($rows, 0, max(0, $limit));
    }

    private function valueFor(PlayerSeasonStats $bilan, string $metric): int
    {
        return match ($metric) {
            'goals'               => $bilan->goals,
            'assists'             => $bilan->assists,
            'goal_contributions'  => $bilan->goalContributions(),
            'appearances'         => $bilan->appearances,
            'yellow_cards'        => $bilan->yellowCards,
            'red_cards'           => $bilan->redCards,
        };
    }
}
```

- [ ] **Step 5: Mettre à jour la doublure `heatmapService()` de `StatsApiControllerTest`**

Dans `tests/unit/StatsApiControllerTest.php`, méthode `heatmapService()` :

```php
    private function heatmapService(): HeatmapService
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function goalsByPlayerAndMonth(int $seasonId): array {
                return [
                    ['playerId' => 29, 'month' => '2025-08', 'goals' => 2],
                    ['playerId' => 29, 'month' => '2025-09', 'goals' => 1],
                    ['playerId' => 7, 'month' => '2025-08', 'goals' => 3],
                ];
            }
        };
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player {
                return Player::fromRow([
                    'id' => $id, 'season_id' => 1, 'person_id' => $id, 'shirt_number' => $id, 'first_name' => 'Prenom',
                    'last_name' => "Joueur{$id}", 'position' => 'FW', 'detailed_position' => 'ST',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 180, 'is_captain' => 0,
                ]);
            }
        };
        return new HeatmapService($stats, $players);
    }
```

Les tests `testBuildDistributionAgregeParMois`/`testBuildHeatmapRetourneLesMoisEtLignes` de `StatsApiControllerTest` appellent `$controller->buildDistribution()`/`buildHeatmap()`, qui appellent en interne `$this->heatmap->goalsByPlayerAndMonth()` sans argument (voir `StatsApiController`, inchangé jusqu'à la Task 21) : ils resteront rouges jusqu'à cette tâche, c'est attendu.

- [ ] **Step 6: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `HeatmapServiceTest` et `RankingServiceTest` passent. `StatsApiControllerTest::testBuildDistributionAgregeParMois`/`testBuildHeatmapRetourneLesMoisEtLignes` restent rouges : résolu Task 21.

- [ ] **Step 7: Commit**

```bash
git add php/services/HeatmapService.php php/services/RankingService.php tests/unit/HeatmapServiceTest.php tests/unit/RankingServiceTest.php tests/unit/StatsApiControllerTest.php
git commit -m "$(cat <<'EOF'
feat(stats): HeatmapService et RankingService recoivent seasonId

goalsByPlayerAndMonth() et byMetric() exigent desormais la saison,
propagee depuis leurs repositories (Tasks 11-12).
EOF
)"
```

---

### Task 15: `DashboardController`, résolution de saison + navData

**Files:**
- Modify: `php/controllers/DashboardController.php`
- Create: `tests/unit/DashboardControllerTest.php` (n'existait pas encore)

**Interfaces:**
- Consumes: `SeasonRepository::resolve/navData` (Task 8), `KpiService::__construct(..., int $seasonId)` (Task 13), `MatchRepository::seasonRecord/cumulativePoints/recentDetailed(int $seasonId, ...)` (Task 10), `StatisticRepository::topScorers(int $seasonId, ...)` (Task 11).
- Produces: `DashboardController::buildViewData(Season $season): array`, incluant désormais `'seasons'` et `'selectedSeason'` (Task 20 les consomme dans la vue).

- [ ] **Step 1: Écrire le test (échoue : classe/méthode non conformes)**

Créer `tests/unit/DashboardControllerTest.php` (aucun test dédié n'existait avant cette tâche) :

```php
<?php
declare(strict_types=1);

// Vérifie l'assemblage des données du Dashboard (buildViewData), isolé du rendu :
// la saison demandée est bien celle transmise aux repositories, et navData est
// fournie pour le sélecteur de saison du header.
final class DashboardControllerTest extends TestCase
{
    private function makePlayer(int $id, string $first, string $last): Player
    {
        return Player::fromRow([
            'id' => $id, 'season_id' => 1, 'person_id' => $id, 'shirt_number' => $id, 'first_name' => $first,
            'last_name' => $last, 'position' => 'FW', 'detailed_position' => 'ST',
            'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
            'height_cm' => 180, 'is_captain' => 0,
        ]);
    }

    private function controller(): array
    {
        $seen = [];
        $player = fn (int $id, string $f, string $l) => $this->makePlayer($id, $f, $l);
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public array $seen = [];
            public function topScorers(int $seasonId, int $limit, ?int $competitionId): array {
                $this->seen[] = ['topScorers', $seasonId];
                return [['player' => Player::fromRow(['id'=>9,'season_id'=>1,'person_id'=>9,'shirt_number'=>9,'first_name'=>'Ousmane','last_name'=>'Dembélé','position'=>'FW','detailed_position'=>'CF','foot'=>'both','nationality'=>'France','birth_date'=>null,'height_cm'=>178,'is_captain'=>0]), 'goals'=>21, 'assists'=>6, 'minutes'=>2600]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public array $seen = [];
            public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array {
                $this->seen[] = ['seasonRecord', $seasonId];
                return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34];
            }
            public function cumulativePoints(int $seasonId, int $psgTeamId, int $competitionId): array {
                $this->seen[] = ['cumulativePoints', $seasonId];
                return [['x'=>1,'y'=>3,'result'=>'W','label'=>'J1'], ['x'=>34,'y'=>76,'result'=>'W','label'=>'J34']];
            }
            public function recentDetailed(int $seasonId, int $psgTeamId, int $limit): array {
                $this->seen[] = ['recentDetailed', $seasonId];
                return [['competition'=>'Ligue 1','opponent'=>'Nice','home'=>true,'goalsFor'=>3,'goalsAgainst'=>0,'result'=>'W']];
            }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INTEGER)');
        $pdo->exec('INSERT INTO teams (id, is_psg) VALUES (1, 1)');
        $teams = new TeamRepository($pdo);
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (7,'2026-27','2026-07-01','2027-06-30',1)");
        $seasons = new SeasonRepository($seasonsPdo);

        return [new DashboardController($stats, $matches, $comps, $teams, $seasons), $stats, $matches];
    }

    public function testBuildViewDataTransmetLaSaisonDemandeeAuxRepositories(): void
    {
        [$ctrl, $stats, $matches] = $this->controller();
        $season = new Season(7, '2026-27', '2026-07-01', '2027-06-30', true);

        $data = $ctrl->buildViewData($season);

        $this->assertSame([['topScorers', 7]], $stats->seen);
        $this->assertSame(
            [['seasonRecord', 7], ['cumulativePoints', 7], ['recentDetailed', 7]],
            $matches->seen
        );
        $this->assertSame('dashboard', $data['page']);
        $this->assertSame(76, $data['totalPoints']);
    }

    public function testBuildViewDataExposeLaNavigationDeSaison(): void
    {
        [$ctrl] = $this->controller();
        $season = new Season(7, '2026-27', '2026-07-01', '2027-06-30', true);

        $data = $ctrl->buildViewData($season);

        $this->assertSame('2026-27', $data['selectedSeason']);
        $this->assertSame(['2026-27'], array_column($data['seasons'], 'label'));
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`DashboardController` n'a pas encore de 5e paramètre `SeasonRepository`, `buildViewData` ne prend pas de `Season`).

- [ ] **Step 3: Modifier `DashboardController`**

Remplacer `php/controllers/DashboardController.php` :

```php
<?php
declare(strict_types=1);

// Page phare du site : agrège les données RÉELLES (KpiService, repositories) et les
// prépare pour un rendu SSR "Matchday" pour la saison sélectionnée (?saison=, saison
// courante par défaut). Les charts sont hydratés côté client à partir des données
// embarquées et de l'API ; la page reste lisible sans JavaScript.
final class DashboardController extends Controller
{
    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?MatchRepository $matches = null,
        private ?CompetitionRepository $comps = null,
        private ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->matches ??= new MatchRepository();
        $this->comps ??= new CompetitionRepository();
        $this->teams ??= new TeamRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('dashboard', $this->buildViewData($season));
    }

    // Assemble les données réelles du dashboard pour une saison donnée, isolé de
    // index() (aucun rendu ni effet de bord) pour rester testable.
    public function buildViewData(Season $season): array
    {
        $seasonId = $season->id;
        $psgId = $this->teams->psgId();
        $leagueId = $this->comps->leagueId() ?? 0;

        $kpi = (new KpiService($this->stats, $this->matches, $this->comps, $psgId, $seasonId))->dashboard();
        $record = $this->matches->seasonRecord($seasonId, $psgId, $leagueId);

        // Points cumulés : reconstruits côté serveur (aucun endpoint).
        $points = $this->matches->cumulativePoints($seasonId, $psgId, $leagueId);
        $totalPoints = $points !== [] ? (int) end($points)['y'] : ($record['wins'] * 3 + $record['draws']);

        // Cinq derniers matchs (toutes compétitions) et forme lue du plus ancien au récent.
        $recent = $this->matches->recentDetailed($seasonId, $psgId, 5);
        $form = array_reverse(array_map(static fn (array $m): string => $m['result'], $recent));

        // Meilleurs buteurs de Ligue 1 de la saison sélectionnée.
        $scorers = $this->stats->topScorers($seasonId, 5, $leagueId);
        $topScorers = array_map(static fn (array $s): array => [
            'label' => $s['player']->lastName,
            'value' => $s['goals'],
        ], $scorers);

        return [
            'title'       => 'Dashboard · PSG Analytics',
            'page'        => 'dashboard',
            'kpi'         => $kpi,
            'record'      => $record,
            'totalPoints' => $totalPoints,
            'points'      => $points,
            'recent'      => $recent,
            'form'        => $form,
            'topScorers'  => $topScorers,
        ] + $this->seasons->navData($season);
    }
}
```

- [ ] **Step 4: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `DashboardControllerTest` passe.

- [ ] **Step 5: Commit**

```bash
git add php/controllers/DashboardController.php tests/unit/DashboardControllerTest.php
git commit -m "$(cat <<'EOF'
feat(dashboard): resout la saison affichee et expose navData

?saison= resout la saison via SeasonRepository (courante par defaut),
propagee explicitement aux repositories comme psgId/leagueId. La vue
recoit desormais seasons/selectedSeason pour le selecteur du header
(Task 20).
EOF
)"
```

---

### Task 16: `HomeController`, résolution de saison + navData

**Files:**
- Modify: `php/controllers/HomeController.php`
- Modify: `tests/unit/HomeControllerTest.php`

**Interfaces:**
- Consumes: identiques à la Task 15 (mêmes repositories, même `SeasonRepository`).
- Produces: `HomeController::buildViewData(Season $season): array`.

- [ ] **Step 1: Modifier le test (échoue : arité incompatible)**

Remplacer `tests/unit/HomeControllerTest.php` :

```php
<?php

declare(strict_types=1);

// Vérifie l'assemblage des données de l'Accueil (buildViewData), isolé du rendu :
// total de points lu du cumul, forme remise dans l'ordre chronologique, top buteurs
// mis au format compact, et navigation de saison exposée pour le header.
final class HomeControllerTest extends TestCase
{
    private function makePlayer(int $id, string $first, string $last): Player
    {
        return Player::fromRow([
            'id' => $id, 'season_id' => 1, 'person_id' => $id, 'shirt_number' => $id, 'first_name' => $first,
            'last_name' => $last, 'position' => 'FW', 'detailed_position' => 'ST',
            'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
            'height_cm' => 180, 'is_captain' => 0,
        ]);
    }

    private function controller(): HomeController
    {
        $player = fn (int $id, string $f, string $l) => $this->makePlayer($id, $f, $l);
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public array $rows = [];
            public function topScorers(int $seasonId, int $limit, ?int $competitionId): array
            {
                return array_slice($this->rows, 0, $limit);
            }
        };
        $stats->rows = [
            ['player' => $player(9, 'Ousmane', 'Dembélé'), 'goals' => 21, 'assists' => 6, 'minutes' => 2600],
            ['player' => $player(29, 'Bradley', 'Barcola'), 'goals' => 13, 'assists' => 8, 'minutes' => 2500],
        ];

        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array
            {
                return ['wins' => 24, 'draws' => 4, 'losses' => 6, 'goals_for' => 74,
                    'goals_against' => 29, 'clean_sheets' => 15, 'avg_possession' => 63.2, 'played' => 34];
            }
            public function cumulativePoints(int $seasonId, int $psgTeamId, int $competitionId): array
            {
                return [['x' => 1, 'y' => 3, 'result' => 'W', 'label' => 'J1'],
                    ['x' => 2, 'y' => 6, 'result' => 'W', 'label' => 'J2'],
                    ['x' => 3, 'y' => 76, 'result' => 'W', 'label' => 'J34']];
            }
            public function recentDetailed(int $seasonId, int $psgTeamId, int $limit): array
            {
                return [
                    ['competition' => 'Ligue 1', 'opponent' => 'Nice', 'home' => true, 'goalsFor' => 3, 'goalsAgainst' => 0, 'result' => 'W'],
                    ['competition' => 'Ligue 1', 'opponent' => 'Lens', 'home' => false, 'goalsFor' => 1, 'goalsAgainst' => 1, 'result' => 'D'],
                    ['competition' => 'Ligue 1', 'opponent' => 'Lyon', 'home' => true, 'goalsFor' => 2, 'goalsAgainst' => 1, 'result' => 'W'],
                ];
            }
        };

        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INTEGER)');
        $pdo->exec('INSERT INTO teams (id, is_psg) VALUES (1, 1)');
        $teams = new TeamRepository($pdo);

        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        $seasons = new SeasonRepository($seasonsPdo);

        return new HomeController($stats, $matches, $comps, $teams, $seasons);
    }

    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testTotalPointsVientDuCumul(): void
    {
        $data = $this->controller()->buildViewData($this->saison());
        $this->assertSame(76, $data['totalPoints']);
        $this->assertSame('home', $data['page']);
    }

    public function testFormeRemiseDansLordreChronologique(): void
    {
        $data = $this->controller()->buildViewData($this->saison());
        $this->assertSame(['W', 'D', 'W'], $data['form']);
    }

    public function testTopButeursCompactsAvecMeilleurTotal(): void
    {
        $data = $this->controller()->buildViewData($this->saison());
        $this->assertSame(21, $data['topGoals']);
        $this->assertSame('Dembélé', $data['topScorers'][0]['name']);
        $this->assertSame('Bradley', $data['topScorers'][1]['first']);
        $this->assertSame(13, $data['topScorers'][1]['goals']);
    }

    public function testExposeLaNavigationDeSaison(): void
    {
        $data = $this->controller()->buildViewData($this->saison());
        $this->assertSame('2025-26', $data['selectedSeason']);
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité incompatible).

- [ ] **Step 3: Modifier `HomeController`**

Remplacer `php/controllers/HomeController.php` :

```php
<?php

declare(strict_types=1);

// Accueil : la couverture éditoriale du site (récit de saison, promesse de
// traçabilité, portes d'entrée vers les pages profondes), pour la saison
// sélectionnée (?saison=, saison courante par défaut). Elle réutilise les mêmes
// accès de données réelles que le Dashboard (KpiService, repositories), mais reste
// chart-light : ici le récit et la navigation priment sur l'atelier analytique.
final class HomeController extends Controller
{
    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?MatchRepository $matches = null,
        private ?CompetitionRepository $comps = null,
        private ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->matches ??= new MatchRepository();
        $this->comps ??= new CompetitionRepository();
        $this->teams ??= new TeamRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('home', $this->buildViewData($season));
    }

    // Assemble les données réelles de l'accueil pour une saison donnée. Isolé de
    // index() (aucun rendu ni effet de bord) pour rester testable, comme les
    // contrôleurs d'API du projet.
    public function buildViewData(Season $season): array
    {
        $seasonId = $season->id;
        $psgId = $this->teams->psgId();
        $leagueId = $this->comps->leagueId() ?? 0;

        $kpi = (new KpiService($this->stats, $this->matches, $this->comps, $psgId, $seasonId))->dashboard();
        $record = $this->matches->seasonRecord($seasonId, $psgId, $leagueId);

        $points = $this->matches->cumulativePoints($seasonId, $psgId, $leagueId);
        $totalPoints = $points !== [] ? (int) end($points)['y'] : ($record['wins'] * 3 + $record['draws']);

        $recent = $this->matches->recentDetailed($seasonId, $psgId, 5);
        $form = array_reverse(array_map(static fn (array $m): string => $m['result'], $recent));

        $scorers = $this->stats->topScorers($seasonId, 5, $leagueId);
        $topGoals = $scorers !== [] ? (int) $scorers[0]['goals'] : 0;
        $topScorers = array_map(static fn (array $s): array => [
            'name'  => $s['player']->lastName,
            'first' => $s['player']->firstName,
            'goals' => (int) $s['goals'],
        ], $scorers);

        return [
            'title'       => 'PSG Analytics · La saison remonte à sa source',
            'page'        => 'home',
            'record'      => $record,
            'totalPoints' => $totalPoints,
            'kpi'         => $kpi,
            'recent'      => $recent,
            'form'        => $form,
            'topScorers'  => $topScorers,
            'topGoals'    => $topGoals,
        ] + $this->seasons->navData($season);
    }
}
```

- [ ] **Step 4: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `HomeControllerTest` passe.

- [ ] **Step 5: Commit**

```bash
git add php/controllers/HomeController.php tests/unit/HomeControllerTest.php
git commit -m "$(cat <<'EOF'
feat(accueil): resout la saison affichee et expose navData

Meme cablage que le Dashboard (Task 15) : ?saison= resolu via
SeasonRepository, propage explicitement, navData exposee a la vue.
EOF
)"
```

---

### Task 17: `PlayerController`, résolution de saison + liste des saisons du joueur

**Files:**
- Modify: `php/controllers/PlayerController.php`
- Modify: `tests/unit/PlayerControllerTest.php`

**Interfaces:**
- Consumes: `PlayerRepository::paginate(int $seasonId, ...)`, `::seasonsForPerson(int $personId)` (Task 9), `StatisticRepository::squadAxisMax(int $seasonId)` (Task 11), `SeasonRepository` (Task 8).
- Produces: `PlayerController::buildViewData(array $query, Season $season): array`, `PlayerController::buildDetail(int $id): ?array` (gagne la clé `'seasons'`).

- [ ] **Step 1: Modifier le test (échoue : arité incompatible, clé `seasons` absente)**

Remplacer `tests/unit/PlayerControllerTest.php` :

```php
<?php
declare(strict_types=1);

// Vérifie l'assemblage de la page Joueurs (buildViewData), isolé du rendu : liste
// blanche de tri/ordre/poste respectée côté serveur, pagination bornée, méta calculée,
// items réduits aux champs d'identité, et navigation de saison exposée. Vérifie aussi
// la fiche joueur (buildDetail) : profil normalisé et liste des saisons jouées.
final class PlayerControllerTest extends TestCase
{
    private function repo(): PlayerRepository
    {
        return new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public array $seen = [];
            public function paginate(int $seasonId, int $page, int $perPage, string $sort, string $order, ?string $position): array
            {
                $this->seen = compact('seasonId', 'page', 'perPage', 'sort', 'order', 'position');
                $player = Player::fromRow([
                    'id' => 22, 'season_id' => 1, 'person_id' => 22, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
                return ['items' => [$player], 'total' => 24];
            }
        };
    }

    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    private function seasonsDouble(): SeasonRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        return new SeasonRepository($pdo);
    }

    public function testValeursParDefaut(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $this->assertSame('last_name', $data['sort']);
        $this->assertSame('ASC', $data['order']);
        $this->assertSame(null, $data['position']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(12, $data['meta']['per_page']);
        $this->assertSame(1, $repo->seen['seasonId']);
    }

    public function testTriHorsListeBlancheRejete(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['sort' => 'goals', 'order' => 'sideways'], $this->saison());
        $this->assertSame('last_name', $repo->seen['sort']);
        $this->assertSame('ASC', $repo->seen['order']);
        $this->assertSame('last_name', $data['sort']);
    }

    public function testOrderDescConserve(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['sort' => 'shirt_number', 'order' => 'desc'], $this->saison());
        $this->assertSame('shirt_number', $repo->seen['sort']);
        $this->assertSame('DESC', $repo->seen['order']);
    }

    public function testPositionListeBlanche(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['position' => 'ZZ'], $this->saison());
        $this->assertSame(null, $repo->seen['position']);

        $repo2 = $this->repo();
        (new PlayerController($repo2, null, $this->seasonsDouble()))->buildViewData(['position' => 'FW'], $this->saison());
        $this->assertSame('FW', $repo2->seen['position']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['per_page' => '9999'], $this->saison());
        $this->assertSame(50, $data['meta']['per_page']);
        $this->assertSame(50, $repo->seen['perPage']);
    }

    public function testTotalPagesCalcule(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData(['per_page' => '12'], $this->saison());
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(24, $data['meta']['total']);
    }

    public function testItemsReduitsAIdentite(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $item = $data['players'][0];
        $this->assertSame(['id', 'number', 'name', 'position', 'nationality'], array_keys($item));
        $this->assertSame('Bradley Barcola', $item['name']);
    }

    public function testPageAuDelaDuTotalRameneeALaDernierePage(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['page' => '99', 'per_page' => '12'], $this->saison());
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(2, $data['meta']['page']);
        $this->assertSame(99, $repo->seen['page']);
    }

    public function testOrderForgeEnTableauNeDeclencheAucunWarning(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['order' => ['x']], $this->saison());
        $this->assertSame('ASC', $data['order']);
        $this->assertSame('ASC', $repo->seen['order']);
    }

    public function testExposeLaNavigationDeSaison(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $this->assertSame('2025-26', $data['selectedSeason']);
    }

    public function testFicheJoueurIntrouvableRenvoieNull(): void
    {
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player { return null; }
        };
        $ctrl = new PlayerController($players, $this->statsDouble());
        $this->assertSame(null, $ctrl->buildDetail(999));
    }

    public function testFicheJoueurNormaliseLeProfilContreLEffectif(): void
    {
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 29, 'season_id' => 1, 'person_id' => 42, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array
            {
                return [['label' => '2025-26', 'isCurrent' => false, 'playerId' => 29]];
            }
        };
        $data = (new PlayerController($players, $this->statsDouble()))->buildDetail(29);

        $this->assertSame('Bradley Barcola', $data['player']['name']);
        $this->assertSame(['Buts', 'Passes déc.', 'Minutes', 'Tirs', 'Tacles gagnés', 'Note'], $data['profile']['axes']);
        $this->assertSame([0.5, 0.5, 1.0, 0.5, 0.5, 1.0], $data['profile']['values']);
        $this->assertSame(1, count($data['timeline']));
        $this->assertSame([['label' => '2025-26', 'isCurrent' => false, 'playerId' => 29]], $data['seasons']);
    }

    // Doublure de StatisticRepository : totaux, maxima d'effectif et timeline cannés.
    private function statsDouble(): StatisticRepository
    {
        return new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $playerId): array
            {
                return ['goals' => 11, 'assists' => 5, 'minutes' => 2000, 'shots' => 40, 'duelsWon' => 30, 'rating' => 7.2];
            }
            public function squadAxisMax(int $seasonId): array
            {
                return ['goals' => 22.0, 'assists' => 10.0, 'minutes' => 2000.0, 'shots' => 80.0, 'duelsWon' => 60.0, 'rating' => 7.2];
            }
            public function timeline(int $playerId): array
            {
                return [['matchId' => 1, 'playedAt' => '2025-08-01', 'goals' => 1, 'assists' => 0, 'minutes' => 90, 'rating' => 7.0]];
            }
        };
    }
}
```

Note : `buildDetail` appelle désormais `squadAxisMax($player->seasonId)` (la saison du joueur consulté, pas une saison "sélectionnée" puisque `show()` ne passe pas par le sélecteur, voir Step 3) : la doublure `statsDouble()` accepte bien `int $seasonId` même si elle ignore sa valeur dans ce test.

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `paginate`/`buildViewData` incompatible, `seasonsForPerson` absente de la doublure, clé `seasons` absente de `buildDetail`).

- [ ] **Step 3: Modifier `PlayerController`**

Dans `php/controllers/PlayerController.php`, appliquer ces changements :

```php
    public function __construct(
        private ?PlayerRepository $players = null,
        private ?StatisticRepository $stats = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->players ??= new PlayerRepository();
        $this->stats ??= new StatisticRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('players', $this->buildViewData($_GET, $season));
    }
```

`show()` reste inchangée (aucune résolution de saison, l'id détermine déjà tout). Modifier `buildDetail` pour ajouter la liste des saisons du joueur, et passer `$player->seasonId` à `squadAxisMax` (le profil se compare toujours à l'effectif de la même saison que le joueur consulté) :

```php
    public function buildDetail(int $id): ?array
    {
        $player = $this->players->find($id);
        if ($player === null) {
            return null;
        }

        $totals = $this->stats->seasonTotalsByPlayer($id);
        $max = $this->stats->squadAxisMax($player->seasonId);

        $axes = [];
        $values = [];
        foreach (self::PROFILE_AXES as $key => $label) {
            $axes[] = $label;
            $ceiling = (float) ($max[$key] ?? 0);
            $value = (float) ($totals[$key] ?? 0);
            $values[] = $ceiling > 0 ? round($value / $ceiling, 4) : 0.0;
        }

        return [
            'title'    => $player->fullName() . ' · PSG Analytics',
            'page'     => 'player_detail',
            'shotmap'  => $this->shotmap($id, $player->fullName()),
            'shotsByComp' => $this->shotsByCompetition($id),
            'seasons'  => $this->players->seasonsForPerson($player->personId),
            'player'   => [
                'id'               => $player->id,
                'number'           => $player->shirtNumber,
                'name'             => $player->fullName(),
                'position'         => $player->position,
                'detailedPosition' => $player->detailedPosition,
                'foot'             => $player->foot,
                'nationality'      => $player->nationality,
                'birthDate'        => $player->birthDate,
                'heightCm'         => $player->heightCm,
                'isCaptain'        => $player->isCaptain,
            ],
            'totals'   => $totals,
            'profile'  => ['axes' => $axes, 'values' => $values],
            'timeline' => $this->stats->timeline($id),
        ];
    }
```

Modifier `buildViewData` pour accepter la saison et filtrer/exposer la navigation :

```php
    public function buildViewData(array $query, Season $season): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? self::PER_PAGE, 1, 50, self::PER_PAGE);
        $sort = Validator::inList($query['sort'] ?? 'last_name', self::SORTABLE, 'last_name');
        $orderRaw = $query['order'] ?? 'ASC';
        $order = Validator::inList(is_string($orderRaw) ? strtoupper($orderRaw) : '', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? (Validator::inList($query['position'], self::POSITIONS, '') ?: null)
            : null;

        $res = $this->players->paginate($season->id, $page, $perPage, $sort, $order, $position);
        $items = array_map(static fn (Player $p): array => [
            'id' => $p->id,
            'number' => $p->shirtNumber,
            'name' => $p->fullName(),
            'position' => $p->position,
            'nationality' => $p->nationality,
        ], $res['items']);

        $total = (int) $res['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'title'     => 'Joueurs · PSG Analytics',
            'page'      => 'players',
            'players'   => $items,
            'meta'      => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
            'sort'      => $sort,
            'order'     => $order,
            'position'  => $position,
            'positions' => self::POSITIONS,
        ] + $this->seasons->navData($season);
    }
```

- [ ] **Step 4: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `PlayerControllerTest` passe.

- [ ] **Step 5: Commit**

```bash
git add php/controllers/PlayerController.php tests/unit/PlayerControllerTest.php
git commit -m "$(cat <<'EOF'
feat(joueurs): resout la saison affichee, ajoute la liste des saisons a la fiche joueur

buildViewData() filtre desormais par season_id et expose navData.
buildDetail() ajoute 'seasons' (saisons PSG du meme person_id) et
compare le profil au meilleur de l'effectif de la saison du joueur
consulte, pas de la saison actuellement selectionnee dans le header.
EOF
)"
```

---

### Task 18: `MatchController`, résolution de saison

**Files:**
- Modify: `php/controllers/MatchController.php`
- Modify: `tests/unit/MatchControllerTest.php`

**Interfaces:**
- Consumes: `MatchRepository::paginate(int $seasonId, ...)` (Task 10), `SeasonRepository` (Task 8).
- Produces: `MatchController::buildIndex(array $query, Season $season): array`.

- [ ] **Step 1: Modifier le test (échoue : arité incompatible)**

Dans `tests/unit/MatchControllerTest.php`, modifier la doublure `matchesDouble()` et `testFiltreResultatHorsListeBlancheRejete` :

```php
    private function matchesDouble(): MatchRepository
    {
        return new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public array $seen = [];
            public array $rows = [];
            public ?array $findRow = null;
            public function paginate(int $seasonId, int $page, int $perPage, ?int $competitionId, ?string $result, int $psgTeamId): array
            {
                $this->seen = compact('seasonId', 'page', 'perPage', 'competitionId', 'result', 'psgTeamId');
                return ['items' => array_map(static fn (array $r): MatchGame => MatchGame::fromRow($r), $this->rows), 'total' => count($this->rows)];
            }
            public function find(int $id): ?MatchGame
            {
                return $this->findRow !== null ? MatchGame::fromRow($this->findRow) : null;
            }
        };
    }

    private function seasonsDouble(): SeasonRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        return new SeasonRepository($pdo);
    }

    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testFiltreResultatHorsListeBlancheRejete(): void
    {
        $matches = $this->matchesDouble();
        $matches->rows = [$this->matchRow()];
        $ctrl = new MatchController($matches, $this->teamsDouble(), $this->competitionsDouble(), 1, $this->seasonsDouble());

        $data = $ctrl->buildIndex(['result' => 'X', 'competition_id' => '2'], $this->saison());

        $this->assertSame(null, $matches->seen['result']);
        $this->assertSame(2, $matches->seen['competitionId']);
        $this->assertSame(1, $matches->seen['seasonId']);

        $item = $data['matches'][0];
        $this->assertSame('PSG', $item['home']);
        $this->assertSame('Arsenal', $item['away']);
        $this->assertSame('Ligue des Champions', $item['competition']);
        $this->assertSame(true, $item['penaltyShootout']);
        $this->assertSame('4-3', $item['penaltyScore']);
        $this->assertSame('2025-26', $data['selectedSeason']);
    }
```

Les deux autres tests (`testFicheMatchIntrouvableRenvoieNull`, `testFicheMatchResoutNomsEtStats`) appellent `buildDetail`, inchangée : les laisser tels quels, mais mettre à jour l'instanciation de `MatchController` pour inclure le 5e paramètre `$this->seasonsDouble()` uniquement si le constructeur l'exige positionnellement avant `$psgId`, voir Step 3 (l'ordre choisi place `SeasonRepository` après `$psgId`, donc ces deux tests n'ont besoin d'aucune modification, `$seasons` reste `null` et se résout via `new SeasonRepository()` par défaut, jamais appelée par `buildDetail`).

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `paginate`/`buildIndex` incompatible).

- [ ] **Step 3: Modifier `MatchController`**

Dans `php/controllers/MatchController.php` :

```php
    public function __construct(
        private ?MatchRepository $matches = null,
        private ?TeamRepository $teams = null,
        private ?CompetitionRepository $competitions = null,
        ?int $psgId = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->matches ??= new MatchRepository();
        $this->teams ??= new TeamRepository();
        $this->competitions ??= new CompetitionRepository();
        $this->psgId = $psgId ?? $this->teams->psgId();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('matches', $this->buildIndex($_GET, $season));
    }

    // Assemble la liste filtrée et paginée pour une saison donnée, isolée du rendu
    // pour rester testable.
    public function buildIndex(array $query, Season $season): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $competitionId = isset($query['competition_id'])
            ? (Validator::int($query['competition_id'], 1, PHP_INT_MAX, 0) ?: null)
            : null;
        $result = isset($query['result'])
            ? (Validator::inList($query['result'], self::RESULTS, '') ?: null)
            : null;

        $res = $this->matches->paginate($season->id, $page, self::PER_PAGE, $competitionId, $result, $this->psgId);

        $names = $this->teams->namesById();
        $competitions = $this->competitions->all();
        $competitionNames = [];
        foreach ($competitions as $c) {
            $competitionNames[$c->id] = $c->name;
        }

        $items = array_map(fn (MatchGame $m): array => $this->summarize($m, $names, $competitionNames), $res['items']);

        $total = (int) $res['total'];
        $totalPages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'title'        => 'Matchs · PSG Analytics',
            'page'         => 'matches',
            'matches'      => $items,
            'competitions' => array_map(static fn ($c): array => ['id' => $c->id, 'name' => $c->name], $competitions),
            'results'      => self::RESULTS,
            'filters'      => ['competition_id' => $competitionId, 'result' => $result],
            'meta'         => [
                'page'        => $page,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ] + $this->seasons->navData($season);
    }
```

Le reste du fichier (`show()`, `buildDetail()`, `summarize()`, propriété `$psgId`) reste inchangé.

- [ ] **Step 4: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `MatchControllerTest` passe.

- [ ] **Step 5: Commit**

```bash
git add php/controllers/MatchController.php tests/unit/MatchControllerTest.php
git commit -m "$(cat <<'EOF'
feat(matchs): resout la saison affichee sur la liste filtree

buildIndex() filtre desormais par season_id et expose navData. show()
et buildDetail() restent inchanges (l'id du match determine deja tout).
EOF
)"
```

---

### Task 19: `MethodologyController`, résolution de saison

**Files:**
- Modify: `php/controllers/MethodologyController.php`
- Modify: `tests/unit/MethodologyControllerTest.php`

**Interfaces:**
- Consumes: `SourceRepository::coverageByTable(int $seasonId)` (Task 12), `SeasonRepository` (Task 8).
- Produces: `MethodologyController::buildViewData(Season $season): array`.

- [ ] **Step 1: Modifier le test (échoue : arité incompatible)**

Remplacer `tests/unit/MethodologyControllerTest.php` :

```php
<?php
declare(strict_types=1);

// Vérifie que la page Méthodologie assemble bien les sources et la couverture par
// table fournies par le repository pour la saison sélectionnée, sans les recalculer.
final class MethodologyControllerTest extends TestCase
{
    private function sourcesDouble(): SourceRepository
    {
        return new class(new PDO('sqlite::memory:')) extends SourceRepository {
            public array $seen = [];
            public function sources(): array
            {
                return [
                    ['label' => 'FBref', 'confidence' => 'verified', 'url' => 'https://fbref.com', 'note' => 'Scores', 'collectedAt' => '2026-08-11'],
                    ['label' => 'StatGenerator', 'confidence' => 'estimated', 'url' => null, 'note' => 'Attribution', 'collectedAt' => '2026-07-20'],
                ];
            }
            public function coverageByTable(int $seasonId): array
            {
                $this->seen[] = $seasonId;
                return [
                    ['label' => 'Matchs', 'total' => 55, 'verified' => 55, 'pct' => 100],
                    ['label' => 'Statistiques par match', 'total' => 515, 'verified' => 0, 'pct' => 0],
                ];
            }
        };
    }

    private function seasonsDouble(): SeasonRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        return new SeasonRepository($pdo);
    }

    public function testAssembleSourcesEtCouverturePourLaSaisonDemandee(): void
    {
        $sources = $this->sourcesDouble();
        $season = new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
        $data = (new MethodologyController($sources, $this->seasonsDouble()))->buildViewData($season);

        $this->assertSame('methodology', $data['page']);
        $this->assertSame(2, count($data['sources']));
        $this->assertSame('verified', $data['sources'][0]['confidence']);
        $this->assertSame('estimated', $data['sources'][1]['confidence']);

        $this->assertSame(100, $data['coverage'][0]['pct']);
        $this->assertSame(0, $data['coverage'][1]['pct']);
        $this->assertSame(515, $data['coverage'][1]['total']);

        $this->assertSame([1], $sources->seen, 'coverageByTable reçoit bien la saison demandée');
        $this->assertSame('2025-26', $data['selectedSeason']);
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité de `buildViewData`/`coverageByTable` incompatible).

- [ ] **Step 3: Modifier `MethodologyController`**

Remplacer `php/controllers/MethodologyController.php` :

```php
<?php

declare(strict_types=1);

// Page Méthodologie : d'où viennent les chiffres, pour la saison sélectionnée
// (?saison=, saison courante par défaut). Expose les sources de données et le
// taux de vérification par table, coeur de la promesse de traçabilité du site.
final class MethodologyController extends Controller
{
    public function __construct(
        private ?SourceRepository $sources = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->sources ??= new SourceRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('methodology', $this->buildViewData($season));
    }

    // Assemble sources et couverture pour une saison donnée, isolé du rendu pour
    // rester testable.
    public function buildViewData(Season $season): array
    {
        return [
            'title'    => 'Méthodologie · PSG Analytics',
            'page'     => 'methodology',
            'sources'  => $this->sources->sources(),
            'coverage' => $this->sources->coverageByTable($season->id),
        ] + $this->seasons->navData($season);
    }
}
```

- [ ] **Step 4: Lancer la suite pour vérifier**

Run: `php tests/run.php`
Expected: `MethodologyControllerTest` passe.

- [ ] **Step 5: Commit**

```bash
git add php/controllers/MethodologyController.php tests/unit/MethodologyControllerTest.php
git commit -m "$(cat <<'EOF'
feat(methodologie): resout la saison affichee pour la couverture par table

coverageByTable() est desormais filtree par saison ; sources() reste
un catalogue global. navData exposee comme les autres pages.
EOF
)"
```

---

### Task 20: Vues, sélecteur de saison dans le header, liste des saisons sur la fiche joueur

**Files:**
- Modify: `php/views/partials/header.php`
- Modify: `assets/css/components.css`
- Modify: `php/views/pages/player_detail.php`

**Interfaces:**
- Consumes: `$seasons`/`$selectedSeason` injectées par `View::render()` via `extract()` depuis les tableaux retournés par `buildViewData()`/`buildIndex()` des Tasks 15, 16, 17 (index), 18, 19. `$seasons` (forme différente, saisons du joueur) injectée par `PlayerController::buildDetail()` (Task 17) pour `player_detail.php` uniquement.

Pas de nouveau test unitaire (rendu HTML non couvert par la suite maison, cohérent avec le reste du projet : les vues ne sont pas testées unitairement, seulement leurs données via les contrôleurs). Vérification par lecture visuelle en local (Step 5).

- [ ] **Step 1: Ajouter le sélecteur au header**

Dans `php/views/partials/header.php`, ajouter le formulaire de sélection de saison juste avant les boutons Transparence/Thème (uniquement si la page courante a exposé `$seasons`/`$selectedSeason` : les pages de fiche, comme `player_detail`/`match_detail`, ne les exposent pas et n'affichent donc pas ce sélecteur, cf. Tasks 17-18) :

```php
<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="container site-header__bar">
    <a href="/" class="brand"><span class="crest" aria-hidden="true"></span>PSG&nbsp;<span class="brand__accent">Analytics</span></a>
    <?php require BASE_PATH . '/php/views/partials/nav.php'; ?>
    <div class="header-spacer"></div>
    <?php if (isset($seasons, $selectedSeason)): ?>
      <form method="get" class="season-switch">
        <label for="saison-select" class="sr-only">Saison</label>
        <select name="saison" id="saison-select" class="season-switch__select" onchange="this.form.submit()">
          <?php foreach ($seasons as $s): ?>
            <option value="<?= View::e($s['label']) ?>"<?= $s['label'] === $selectedSeason ? ' selected' : '' ?>><?= View::e($s['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="season-switch__go">OK</button>
      </form>
    <?php endif; ?>
    <button type="button" id="transp" class="transp-btn" aria-label="Activer le mode transparence des données estimées">
      <span class="transp-btn__sw" aria-hidden="true"></span>Transparence
    </button>
    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Basculer le thème clair ou sombre">
      <span aria-hidden="true">&#9790;</span>
    </button>
  </div>
  <div class="hechter site-header__stripe" aria-hidden="true"></div>
</header>
```

- [ ] **Step 2: Ajouter le style du sélecteur**

Dans `assets/css/components.css`, ajouter à la suite du bloc `.theme-toggle` (déjà présent, réutilisé comme référence de style) :

```css
.season-switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.season-switch__select {
  background: transparent;
  border: 1px solid var(--line-2);
  border-radius: 9px;
  color: var(--text);
  font: inherit;
  padding: 6px 10px;
  cursor: pointer;
}

.season-switch__go {
  border: 1px solid var(--line-2);
  border-radius: 9px;
  background: transparent;
  color: var(--dim);
  cursor: pointer;
  padding: 6px 10px;
  font: inherit;
}

.season-switch__go:hover {
  color: var(--text);
}
```

Vérifier que `.sr-only` existe déjà dans le design system (utilisée ailleurs pour les libellés accessibles masqués) :

Run: `grep -rn "\.sr-only" assets/css/`
Expected : au moins une définition existante. Si absente, l'ajouter dans `assets/css/base.css` :

```css
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
```

- [ ] **Step 3: Ajouter la liste des saisons à la fiche joueur**

Dans `php/views/pages/player_detail.php`, ajouter une section juste après le `<header class="pd__head">` (avant la section « Saison en chiffres »), affichée seulement si le joueur a joué plus d'une saison (sinon aucune valeur ajoutée à afficher une liste à un seul élément) :

```php
  <?php if (!empty($seasons) && count($seasons) > 1): ?>
    <nav aria-label="Saisons PSG de ce joueur" class="pd__seasons">
      <?php foreach ($seasons as $s): ?>
        <a href="/joueurs/<?= View::e($s['playerId']) ?>" class="tag<?= $s['playerId'] === $player['id'] ? ' tag--active' : '' ?>">
          <?= View::e($s['label']) ?><?= $s['isCurrent'] ? ' (en cours)' : '' ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
```

Vérifier l'existence d'une classe `.tag--active` ou équivalente pour marquer la saison consultée :

Run: `grep -n "tag--active\|\.tag\b" assets/css/components.css`

Si `.tag--active` n'existe pas, l'ajouter à `assets/css/components.css` à la suite des variantes `.tag--gk`/`.tag--def`/etc. déjà présentes :

```css
.tag--active {
  border-color: var(--red);
  color: var(--text);
}
```

- [ ] **Step 4: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK` (les vues ne sont pas testées unitairement, cette étape confirme l'absence de régression ailleurs).

- [ ] **Step 5: Vérification visuelle locale**

```bash
php database/migrate.php
php -S 127.0.0.1:8077
```

Ouvrir `http://127.0.0.1:8077/dashboard`, `http://127.0.0.1:8077/joueurs`, `http://127.0.0.1:8077/matchs`, `http://127.0.0.1:8077/methodologie` : vérifier que le sélecteur de saison apparaît dans le header, propose « 2025-26 » et « 2026-27 », et que choisir « 2025-26 » recharge la page avec les données de cette saison (34+21 matchs, 24 joueurs) tandis que « 2026-27 » affiche une page vide (0 match, 0 joueur, sans erreur). Ouvrir `http://127.0.0.1:8077/joueurs/{id}` d'un joueur de 2025-26 : vérifier l'absence du sélecteur de saison dans le header (fiche, pas de liste) et l'absence de bloc « Saisons PSG » (un seul player_id pour ce person_id tant que 2026-27 n'a pas d'effectif).

- [ ] **Step 6: Commit**

```bash
git add php/views/partials/header.php php/views/pages/player_detail.php assets/css/components.css assets/css/base.css
git commit -m "$(cat <<'EOF'
feat(vues): selecteur de saison dans le header, saisons jouees sur la fiche joueur

Le selecteur n'apparait que sur les pages qui exposent seasons/selectedSeason
(listage), jamais sur les fiches (player_detail/match_detail), dont
les donnees sont deja figees a une seule saison par l'id consulte.
EOF
)"
```

---

### Task 21: Contrôleurs API, résolution de saison

**Files:**
- Modify: `php/controllers/Api/PlayerApiController.php`
- Modify: `php/controllers/Api/MatchApiController.php`
- Modify: `php/controllers/Api/StatsApiController.php`
- Modify: `php/controllers/Api/CompetitionApiController.php`
- Modify: `php/controllers/Api/ExportApiController.php`
- Modify: `tests/unit/PlayerApiControllerTest.php`
- Modify: `tests/unit/MatchApiControllerTest.php`
- Modify: `tests/unit/StatsApiControllerTest.php`
- Modify: `tests/unit/CompetitionApiControllerTest.php`
- Modify: `tests/unit/ExportApiControllerTest.php`

**Interfaces:**
- Consumes : toutes les signatures `season_id`-aware des Tasks 9-14. Même pattern de résolution que les contrôleurs de pages (Tasks 15-19) : `?saison=` en query string, `SeasonRepository::resolve()`, saison courante par défaut. Aucune de ces routes API n'affiche de sélecteur (pas de vue HTML), mais accepte le même paramètre pour rester cohérente avec les pages qui l'appellent en amélioration progressive.
- Produces: `PlayerApiController::buildIndex(array $query, Season $season): array`, `MatchApiController::buildIndex(array $query, Season $season): array`, `StatsApiController::buildKpis(Season $season): array` / `::buildDistribution(Season $season): array` / `::buildHeatmap(Season $season): array`, `CompetitionApiController::buildIndex(Season $season): array`, `ExportApiController::buildCsv(Season $season): string` / `::buildPdf(Season $season): string`.

- [ ] **Step 1: Modifier les tests (échouent : arité incompatible)**

Dans `tests/unit/PlayerApiControllerTest.php`, mettre à jour `controller()` et les tests qui appellent `buildIndex` :

```php
    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    private function controller(): PlayerApiController
    {
        $repo = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function paginate(int $seasonId, int $page, int $perPage, string $sort, string $order, ?string $pos): array {
                return ['items' => [], 'total' => 24];
            }
        };
        return new PlayerApiController($repo);
    }

    public function testEnveloppeContientMeta(): void
    {
        $env = $this->controller()->buildIndex(['page' => '1', 'per_page' => '20'], $this->saison());
        $this->assertSame(24, $env['meta']['total']);
        $this->assertSame(1, $env['meta']['page']);
        $this->assertSame(2, $env['meta']['total_pages']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $env = $this->controller()->buildIndex(['per_page' => '9999'], $this->saison());
        $this->assertSame(50, $env['meta']['per_page']);
    }
```

Les autres tests de ce fichier (`testShowRetourneNullSiAbsent`, `testShowRetourneLeJoueur`, `testTimelineEnveloppeLesMatches`, `testCompareRenvoieLesDeuxJoueurs`) ciblent `buildShow`/`buildTimeline`/`buildCompare`, inchangées : ne pas les modifier, seulement mettre à jour leurs fixtures `Player::fromRow` pour inclure `'person_id'` si ce n'est pas déjà fait à la Task 2 (vérifier : ces fixtures ont été listées explicitement à la Task 2, Step 6).

Dans `tests/unit/MatchApiControllerTest.php` :

```php
    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testBuildIndexEnveloppeLaPagination(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function paginate(int $seasonId, int $page, int $perPage, ?int $c, ?string $r, int $psg): array {
                return ['items' => [MatchGame::fromRow(['id'=>1,'season_id'=>1,'competition_id'=>1,'round_label'=>'J1','played_at'=>'2025-08-16','home_team_id'=>1,'away_team_id'=>2,'home_goals'=>2,'away_goals'=>1,'went_to_extra'=>0,'penalty_shootout'=>0,'penalty_score'=>null,'attendance'=>45000,'psg_possession'=>61.5,'psg_shots'=>14,'psg_shots_on_target'=>6,'source_id'=>1])], 'total' => 34];
            }
        };
        $controller = new MatchApiController($repo, 1);
        $env = $controller->buildIndex(['per_page' => '10'], $this->saison());
        $this->assertSame(34, $env['meta']['total']);
        $this->assertSame('W', $env['data'][0]['result']);
    }
```

Les tests `testBuildShowRetourneNullSiAbsent`/`testBuildShowRetourneLeMatch` ciblent `buildShow`, inchangée : ne pas les modifier.

Dans `tests/unit/StatsApiControllerTest.php`, ajouter une méthode `saison()` et mettre à jour les trois tests qui appellent `buildKpis`/`buildDistribution`/`buildHeatmap` :

```php
    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testBuildKpisEnveloppeLeDashboard(): void
    {
        $controller = new StatsApiController($this->kpiService());
        $env = $controller->buildKpis($this->saison());
        $this->assertSame('Bradley Barcola', $env['data']['top_scorer']['name']);
        $this->assertSame(24, $env['data']['wins']);
    }

    public function testBuildDistributionAgregeParMois(): void
    {
        $controller = new StatsApiController($this->kpiService(), $this->heatmapService());
        $env = $controller->buildDistribution($this->saison());
        $this->assertSame(5, $env['data']['by_month']['2025-08']);
        $this->assertSame(1, $env['data']['by_month']['2025-09']);
    }

    public function testBuildHeatmapRetourneLesMoisEtLignes(): void
    {
        $controller = new StatsApiController($this->kpiService(), $this->heatmapService());
        $env = $controller->buildHeatmap($this->saison());
        $this->assertSame(['2025-08', '2025-09'], $env['data']['months']);
        $this->assertSame(2, count($env['data']['rows']));
    }
```

(`kpiService()`/`heatmapService()` ont déjà été mis à jour aux Tasks 13-14 ; ne pas les retoucher ici.)

Dans `tests/unit/CompetitionApiControllerTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie l'API Compétitions : fusion des compétitions et du bilan, pour la saison demandée.
final class CompetitionApiControllerTest extends TestCase
{
    public function testBuildIndexFusionneCompetitionsEtBilan(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function all(): array {
                return [
                    Competition::fromRow(['id' => 1, 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic']),
                    Competition::fromRow(['id' => 2, 'name' => 'Ligue des champions', 'type' => 'cup', 'scope' => 'europe']),
                ];
            }
            public function standings(int $seasonId, int $psgTeamId): array {
                return [
                    ['competitionId' => 1, 'competitionName' => 'Ligue 1', 'wins' => 24, 'draws' => 4, 'losses' => 6, 'goalsFor' => 74, 'goalsAgainst' => 29],
                ];
            }
        };
        $controller = new CompetitionApiController($repo, 1);
        $season = new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
        $env = $controller->buildIndex($season);
        $this->assertSame(24, $env['data'][0]['wins']);
        $this->assertSame(0, $env['data'][1]['wins']);
    }
}
```

Dans `tests/unit/ExportApiControllerTest.php` :

```php
<?php
declare(strict_types=1);
// Vérifie les exports : en-tête et échappement CSV, structure du PDF, pour la saison demandée.
final class ExportApiControllerTest extends TestCase
{
    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testCsvContientEnTete(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $c): array {
                return [['player'=>Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]),'goals'=>11,'assists'=>4,'minutes'=>2400]];
            }
        };
        $csv = (new ExportApiController($stats))->buildCsv($this->saison());
        $this->assertTrue(str_contains($csv, 'Joueur;Buts;Passes;Minutes'), 'en-tête CSV');
        $this->assertTrue(str_contains($csv, 'Bradley Barcola;11;4;2400'), 'ligne joueur');
    }

    public function testPdfCommenceParEntetePdf(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $c): array {
                return [['player'=>Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]),'goals'=>11,'assists'=>4,'minutes'=>2400]];
            }
        };
        $pdf = (new ExportApiController($stats))->buildPdf($this->saison());
        $this->assertTrue(str_starts_with($pdf, '%PDF-1.4'), 'entête PDF');
        $this->assertTrue(str_contains($pdf, 'Bradley Barcola'), 'contenu buteur');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (arité incompatible sur les cinq contrôleurs API).

- [ ] **Step 3: Modifier `PlayerApiController`**

Dans `php/controllers/Api/PlayerApiController.php` :

```php
    public function __construct(
        private ?PlayerRepository $players = null,
        private ?StatisticRepository $stats = null,
        private ?ComparisonService $comparison = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->players ??= new PlayerRepository();
        $this->stats ??= new StatisticRepository();
        $this->comparison ??= new ComparisonService($this->stats, $this->players);
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->json($this->buildIndex($_GET, $season));
    }

    public function buildIndex(array $query, Season $season): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? 20, 1, 50, 20);
        $sort = Validator::inList($query['sort'] ?? 'last_name', ['last_name', 'shirt_number', 'position', 'nationality'], 'last_name');
        $order = Validator::inList($query['order'] ?? 'ASC', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? Validator::inList($query['position'], ['GK', 'DF', 'MF', 'FW'], '') ?: null
            : null;

        $res = $this->players->paginate($season->id, $page, $perPage, $sort, $order, $position);
        $items = array_map(static fn(Player $p) => [
            'id' => $p->id, 'number' => $p->shirtNumber, 'name' => $p->fullName(),
            'position' => $p->position, 'nationality' => $p->nationality,
        ], $res['items']);

        return Response::apiEnvelope($items, [
            'page' => $page, 'per_page' => $perPage, 'total' => $res['total'],
            'total_pages' => (int) ceil($res['total'] / $perPage),
        ]);
    }
```

`show()`/`buildShow()`, `timeline()`/`buildTimeline()`, `compare()`/`buildCompare()` restent inchangées.

- [ ] **Step 4: Modifier `MatchApiController`**

Dans `php/controllers/Api/MatchApiController.php` :

```php
    public function __construct(
        private ?MatchRepository $matches = null,
        ?int $psgTeamId = null,
        ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->matches ??= new MatchRepository();
        $teams ??= new TeamRepository();
        $this->psgTeamId = $psgTeamId ?? $teams->psgId();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->json($this->buildIndex($_GET, $season));
    }

    public function buildIndex(array $query, Season $season): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? 20, 1, 50, 20);
        $competitionId = isset($query['competition_id'])
            ? (Validator::int($query['competition_id'], 1, PHP_INT_MAX, 0) ?: null)
            : null;
        $result = isset($query['result'])
            ? Validator::inList($query['result'], ['W', 'D', 'L'], '') ?: null
            : null;

        $res = $this->matches->paginate($season->id, $page, $perPage, $competitionId, $result, $this->psgTeamId);
        $items = array_map(fn(MatchGame $m) => $this->summarize($m), $res['items']);

        return Response::apiEnvelope($items, [
            'page' => $page, 'per_page' => $perPage, 'total' => $res['total'],
            'total_pages' => (int) ceil($res['total'] / $perPage),
        ]);
    }
```

`show()`/`buildShow()`/`summarize()` restent inchangées.

- [ ] **Step 5: Modifier `StatsApiController`**

Remplacer `php/controllers/Api/StatsApiController.php` :

```php
<?php
declare(strict_types=1);
// Frontière HTTP de l'API statistiques : délègue à KpiService/HeatmapService pour la
// saison sélectionnée (?saison=, saison courante par défaut), enveloppe la réponse.
final class StatsApiController extends Controller
{
    public function __construct(
        private ?KpiService $kpi = null,
        private ?HeatmapService $heatmap = null,
        private ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->heatmap ??= new HeatmapService(new StatisticRepository(), new PlayerRepository());
        $this->seasons ??= new SeasonRepository();
    }

    private function resolveSeason(Request $r): Season
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        return $this->seasons->resolve($slug !== '' ? $slug : null);
    }

    public function kpis(Request $r, array $params): void
    {
        $this->json($this->buildKpis($this->resolveSeason($r)));
    }

    public function buildKpis(Season $season): array
    {
        $kpi = $this->kpi ?? $this->buildKpiService($season);
        return Response::apiEnvelope($kpi->dashboard());
    }

    // Construction paresseuse : ne résout l'identifiant PSG que si aucune KpiService n'est injectée.
    private function buildKpiService(Season $season): KpiService
    {
        $this->teams ??= new TeamRepository();
        return new KpiService(
            new StatisticRepository(),
            new MatchRepository(),
            new CompetitionRepository(),
            $this->teams->psgId(),
            $season->id,
        );
    }

    public function distribution(Request $r, array $params): void
    {
        $this->json($this->buildDistribution($this->resolveSeason($r)));
    }

    // Répartition des buts par période (mois) : agrégat de la matrice joueur x mois.
    public function buildDistribution(Season $season): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth($season->id);
        $byMonth = array_fill_keys($matrix['months'], 0);
        foreach ($matrix['rows'] as $row) {
            foreach ($row['cells'] as $month => $goals) {
                $byMonth[$month] += $goals;
            }
        }
        return Response::apiEnvelope(['by_month' => $byMonth]);
    }

    public function heatmap(Request $r, array $params): void
    {
        $this->json($this->buildHeatmap($this->resolveSeason($r)));
    }

    public function buildHeatmap(Season $season): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth($season->id);
        $rows = array_map(static fn(array $row): array => [
            'player' => ['id' => $row['player']->id, 'name' => $row['player']->fullName()],
            'cells' => $row['cells'],
        ], $matrix['rows']);

        return Response::apiEnvelope(['months' => $matrix['months'], 'rows' => $rows]);
    }
}
```

Note : `buildKpis()` teste désormais `$this->kpi ?? $this->buildKpiService($season)` sans réaffecter `$this->kpi` (contrairement à l'ancien `$this->kpi ??= ...`), pour ne jamais mémoriser une `KpiService` construite pour une saison qui ne serait plus la bonne lors d'un appel suivant sur la même instance. C'est un changement de comportement volontaire par rapport au code actuel : documenté ici plutôt que laissé implicite.

- [ ] **Step 6: Modifier `CompetitionApiController`**

Dans `php/controllers/Api/CompetitionApiController.php` :

```php
    public function __construct(
        private ?CompetitionRepository $competitions = null,
        ?int $psgTeamId = null,
        ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->competitions ??= new CompetitionRepository();
        $teams ??= new TeamRepository();
        $this->psgTeamId = $psgTeamId ?? $teams->psgId();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->json($this->buildIndex($season));
    }

    public function buildIndex(Season $season): array
    {
        $standingsByCompetition = [];
        foreach ($this->competitions->standings($season->id, $this->psgTeamId) as $standing) {
            $standingsByCompetition[$standing['competitionId']] = $standing;
        }

        $items = array_map(static function (Competition $c) use ($standingsByCompetition): array {
            $s = $standingsByCompetition[$c->id] ?? null;
            return [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'scope' => $c->scope,
                'wins' => $s['wins'] ?? 0,
                'draws' => $s['draws'] ?? 0,
                'losses' => $s['losses'] ?? 0,
                'goalsFor' => $s['goalsFor'] ?? 0,
                'goalsAgainst' => $s['goalsAgainst'] ?? 0,
            ];
        }, $this->competitions->all());

        return Response::apiEnvelope($items);
    }
```

- [ ] **Step 7: Modifier `ExportApiController`**

Remplacer `php/controllers/Api/ExportApiController.php` :

```php
<?php
declare(strict_types=1);
// Frontière HTTP des exports : assemble via CsvExporter/PdfExporter pour la saison
// sélectionnée (?saison=, saison courante par défaut), pose les en-têtes de téléchargement.
final class ExportApiController extends Controller
{
    // Nombre de buteurs inclus dans les exports (toutes compétitions confondues).
    private const TOP_LIMIT = 50;

    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->seasons ??= new SeasonRepository();
    }

    private function resolveSeason(Request $r): Season
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        return $this->seasons->resolve($slug !== '' ? $slug : null);
    }

    public function playersCsv(Request $r, array $params): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="joueurs.csv"');
        echo $this->buildCsv($this->resolveSeason($r));
    }

    public function buildCsv(Season $season): string
    {
        $rows = array_map(static function (array $row): array {
            return [$row['player']->fullName(), $row['goals'], $row['assists'], $row['minutes']];
        }, $this->stats->topScorers($season->id, self::TOP_LIMIT, null));

        return CsvExporter::fromRows(['Joueur', 'Buts', 'Passes', 'Minutes'], $rows);
    }

    public function reportPdf(Request $r, array $params): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="rapport.pdf"');
        echo $this->buildPdf($this->resolveSeason($r));
    }

    public function buildPdf(Season $season): string
    {
        $lines = array_map(
            static fn(array $row): string => sprintf(
                '%s - %d buts, %d passes décisives',
                $row['player']->fullName(),
                $row['goals'],
                $row['assists'],
            ),
            $this->stats->topScorers($season->id, self::TOP_LIMIT, null),
        );

        return PdfExporter::simpleReport('Rapport PSG Analytics', [
            ['heading' => 'Meilleurs buteurs', 'lines' => $lines],
        ]);
    }
}
```

- [ ] **Step 8: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`. C'est la première exécution complète depuis la Task 9 où plus aucun test ne devrait être rouge : toutes les tâches intermédiaires « attendu, résolu à la Task X » convergent ici.

- [ ] **Step 9: Commit**

```bash
git add php/controllers/Api/ tests/unit/PlayerApiControllerTest.php tests/unit/MatchApiControllerTest.php tests/unit/StatsApiControllerTest.php tests/unit/CompetitionApiControllerTest.php tests/unit/ExportApiControllerTest.php
git commit -m "$(cat <<'EOF'
feat(api): resout la saison sur tous les endpoints season-aware

Meme pattern que les pages : ?saison= resolu via SeasonRepository,
saison courante par defaut. StatsApiController ne memorise plus une
KpiService construite pour une saison potentiellement perimee.
EOF
)"
```

---

### Task 22: Vérification finale, README, commit de clôture

**Files:**
- Modify: `README.md`

**Interfaces:** aucune (tâche de clôture, pas de nouveau code).

- [ ] **Step 1: Lancer la suite complète une dernière fois**

Run: `php tests/run.php`
Expected: `OK`, avec un nombre de tests strictement supérieur à celui d'avant la Task 1 (nouveaux fichiers : `SeasonModelTest`, `MigratorGuardsTest`, `MigratorLoopTest`, `SeasonRepositoryTest`, `PlayerSeasonStatsRepositoryTest`, `SourceRepositoryTest`, `DashboardControllerTest`, plus les extensions de fichiers existants).

- [ ] **Step 2: Vérifier le mode CLI et le rapport multi-saisons**

Run: `php database/migrate.php`
Expected :
```
2025-26 : matches 55, players 24, L1 24V 4N 6D (74-29)
2026-27 : matches 0, players 0, L1 0V 0N 0D (0-0)
```

- [ ] **Step 3: Mettre à jour le README**

Dans `README.md`, remplacer la ligne d'introduction (ligne 3) :

```markdown
Tableau de bord des saisons du Paris Saint-Germain : dashboard, effectif, matchs et fiches joueurs, adossés à des données réelles et tracées. La saison 2025-26 (terminée, cinq trophées) et la saison 2026-27 (en cours) coexistent, sélectionnables depuis le header.
```

Dans la section « Données », remplacer le premier paragraphe (autour de la ligne 19) :

```markdown
La base est peuplée à partir de sources vérifiées, organisées par saison (`database/seeds/verified/{saison}/`), principalement des exports FBref :

- **Saison 2025-26** (terminée) : 55 matchs toutes compétitions, statistiques par joueur en Ligue 1 exactes, bilans joueurs toutes compétitions.
- **Saison 2026-27** (en cours) : structure prête, contenu sportif à cadrer et remplir séparément à mesure que la saison avance.
```

Dans la section « Architecture » (autour de la ligne 88), remplacer la ligne `database/` :

```markdown
database/
  migrate.php             Recrée le schéma et peuple chaque saison déclarée
  seeds/                  Générateurs et données, dont seeds/verified/{saison}/ (sources de vérité par saison)
```

Dans la section « Pages » (tableau des routes, autour de la ligne 47-58), ajouter une ligne après le tableau existant :

```markdown
Toutes les pages ci-dessus acceptent un paramètre `?saison=2025-26` (ou toute autre saison connue) pour changer la saison affichée ; absent, la saison courante s'applique. Un sélecteur dans le header permet de basculer sans connaître l'URL.
```

- [ ] **Step 4: Lancer la suite une dernière fois après la modification README**

Run: `php tests/run.php`
Expected: `OK` (README n'affecte aucun test, vérification de forme).

- [ ] **Step 5: Commit**

```bash
git add README.md
git commit -m "$(cat <<'EOF'
docs: documente le multi-saisons dans le README

Mentionne la coexistence 2025-26/2026-27, la structure des seeds par
saison, et le parametre ?saison= disponible sur toutes les pages.
EOF
)"
```

---

## Self-Review (à faire par l'implémenteur avant de considérer le plan terminé)

**1. Couverture du spec**, vérifier que chaque section de `docs/superpowers/specs/2026-09-07-fondations-multi-saisons-design.md` a une tâche correspondante :
- §2 Schéma (`is_current`, `people`, `person_id`) → Tasks 1-2.
- §3 Réorganisation des seeds → Task 3, 7.
- §4 Migrator (boucle, `person_id`, garde-fous) → Tasks 4-7.
- §5 Sélecteur de saison, repositories, contrôleurs → Tasks 8-19, 21.
- §6 Fiche joueur → Task 17, 20.
- §7 Tests → chaque tâche porte ses propres tests, aucune tâche « tests » séparée nécessaire.
- §8 Risques (volume transversal, déplacement de fichiers, deploy.sh) → séquencement page par page (Tasks 15-19 distinctes), chemins mis à jour explicitement (Task 3), `deploy.sh` non touché mais signalé (aucune action requise, rappelé au Step 3 de la Task 22 implicitement par le mode CLI vérifié).

**2. Points de vigilance identifiés pendant la rédaction** (déjà intégrés aux tâches ci-dessus, listés ici pour mémoire) :
- `MatchRepository::recent()` n'a aucun appelant dans l'application ni dans les tests : laissée inchangée (Task 10), pour ne pas modifier du code mort hors périmètre.
- `RankingService` n'a aucun appelant applicatif (seulement testé) : mis à jour par cohérence (Task 14) mais aucune tâche de contrôleur n'en dépend.
- `StatsApiController::buildKpis()` change légèrement de comportement (ne mémorise plus une `KpiService` construite pour une saison potentiellement obsolète) : signalé explicitement à la Task 21, Step 5.
- Le sélecteur de saison n'apparaît jamais sur les fiches (`player_detail`, `match_detail`) : décision explicite (Task 20), les fiches restent figées à la saison de leur enregistrement.

**3. Cohérence des types**, `Season` (Task 1) est utilisé identiquement dans toutes les tâches suivantes : `int $id`, `string $label`, `bool $isCurrent`. `SeasonRepository::navData(Season $selected): array` (Task 8) a la même forme partout où elle est fusionnée (`+ $this->seasons->navData($season)`) dans les Tasks 15, 16, 17, 18, 19. Aucune divergence de nommage détectée entre les tâches.

---

## Choix d'exécution

Une fois ce plan approuvé, deux options pour l'exécuter :

1. **Subagent-Driven (recommandé)**, un sous-agent frais par tâche, revue à deux étages entre chaque tâche.
2. **Exécution en ligne**, exécution par lots avec points de contrôle, dans cette même session.

