# Automatisation de la collecte 2026-27 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatiser l'arrivée des statistiques de la saison PSG 2026-27 (en cours) via une tâche planifiée hebdomadaire qui scrape FBref/Understat, écrit les seeds vérifiés, et notifie Mathis, sans jamais toucher au déploiement.

**Architecture:** Deux couches séparées : des fonctions PHP pures et testables (détection des nouveautés, écriture des seeds) dans `database/seeds/`, et un prompt agentique autonome (la tâche planifiée elle-même) qui pilote un navigateur pour le scraping puis appelle ces fonctions. Un correctif compagnon rend les chemins de fichiers de tirs (`shotmap`) paramétrés par saison.

**Tech Stack:** PHP 8.1+, SQLite/MySQL, tâche planifiée Claude Code (skill `schedule`, MCP `scheduled-tasks`), navigateur piloté (outils `Claude_Browser`), zéro nouvelle dépendance.

## Global Constraints

- Ne jamais utiliser le tiret cadratin (em dash), dans aucun fichier ni message de commit.
- Vigilance stricte sur les accents français (jamais de caractères ASCII à la place d'accents), y compris dans les messages de commit.
- `php tests/run.php` doit rester intégralement vert après chaque tâche.
- Ne jamais exécuter `./deploy.sh` ni saisir de mot de passe FTP, à aucun moment, ni dans le code ni dans le prompt de la tâche planifiée.
- Toute donnée insérée en base doit rester marquée vérifiée avec sa source et sa date de relevé : rien n'est jamais inventé.
- Un changement d'effectif ou de compétition est signalé, jamais écrit automatiquement.
- En cas d'échec de `migrate.php` ou de `tests/run.php` après une écriture, aucun commit n'est créé.
- Le commit de chaque passage de la tâche planifiée reste local, jamais poussé vers `origin`.

---

### Task 1: Correctif compagnon, chemins de tirs paramétrés par saison

**Files:**
- Modify: `php/repositories/SeasonRepository.php`
- Modify: `php/controllers/PlayerController.php`
- Modify: `database/seeds/verified/sources.php`
- Move: `database/seeds/verified/understat-shots-2025.json` -> `database/seeds/verified/2025-26/understat-shots-2025.json`
- Move: `database/seeds/verified/fbref-shots-by-competition-2025.json` -> `database/seeds/verified/2025-26/fbref-shots-by-competition-2025.json`
- Test: `tests/unit/SeasonRepositoryTest.php`
- Test: `tests/unit/PlayerControllerTest.php`

**Interfaces:**
- Produces: `SeasonRepository::find(int $id): ?Season`, `PlayerController::shotmapPath(string $seasonLabel): string`, `PlayerController::shotsByCompetitionPath(string $seasonLabel): string` (méthodes statiques publiques, pures, testables sans accès disque). Consommé par les méthodes d'instance `shotmap()`/`shotsByCompetition()`, elles-mêmes appelées par `buildDetail()`.

- [ ] **Step 1: Écrire le test de `SeasonRepository::find()` (échoue : méthode absente)**

Ajouter à `tests/unit/SeasonRepositoryTest.php` :

```php
    public function testFindRenvoieLaSaisonParId(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $s = $repo->find(2);
        $this->assertTrue($s instanceof Season);
        $this->assertSame('2026-27', $s->label);
    }

    public function testFindRenvoieNullSiIdInconnu(): void
    {
        $repo = new SeasonRepository($this->pdo());
        $this->assertSame(null, $repo->find(999));
    }
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`SeasonRepository::find` introuvable).

- [ ] **Step 3: Ajouter `SeasonRepository::find()`**

Dans `php/repositories/SeasonRepository.php`, ajouter, après `bySlug()` :

```php
    public function find(int $id): ?Season
    {
        $row = $this->fetchOne('SELECT * FROM seasons WHERE id = ?', [$id]);
        return $row ? Season::fromRow($row) : null;
    }
```

- [ ] **Step 4: Écrire les tests des chemins paramétrés par saison (échouent : méthodes absentes)**

Ajouter à `tests/unit/PlayerControllerTest.php` :

```php
    public function testShotmapPathDependDeLaSaison(): void
    {
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2025-26/understat-shots-2025.json',
            PlayerController::shotmapPath('2025-26')
        );
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2026-27/understat-shots-2026.json',
            PlayerController::shotmapPath('2026-27')
        );
    }

    public function testShotsByCompetitionPathDependDeLaSaison(): void
    {
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2025-26/fbref-shots-by-competition-2025.json',
            PlayerController::shotsByCompetitionPath('2025-26')
        );
    }

    public function testFicheJoueurSansFichierDeTirsPourSaSaisonRenvoieShotmapNull(): void
    {
        // 2026-27 n'a pas encore de fichier de tirs : la fiche doit rester
        // silencieuse (section masquée), jamais planter.
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 1, 'season_id' => 2, 'person_id' => 1, 'shirt_number' => 9, 'first_name' => 'Joueur',
                    'last_name' => 'DeuxMilleVingtSix', 'position' => 'FW', 'detailed_position' => 'ST',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 180, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array { return []; }
        };
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        $seasons = new SeasonRepository($seasonsPdo);

        $ctrl = new PlayerController($players, $this->statsDouble(), $seasons);
        $data = $ctrl->buildDetail(1);

        $this->assertSame(null, $data['shotmap']);
        $this->assertSame(null, $data['shotsByComp']);
    }

    public function testFicheJoueurAvecFichierDeTirsReelPourSaSaisonRenvoieUnShotmap(): void
    {
        // Achraf Hakimi, id réel 4 dans verified/2025-26/understat-shots-2025.json
        // (27 tirs) : preuve que le fichier deplace au Step 6 est bien retrouve.
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 4, 'season_id' => 1, 'person_id' => 4, 'shirt_number' => 2, 'first_name' => 'Achraf',
                    'last_name' => 'Hakimi', 'position' => 'DF', 'detailed_position' => 'RB',
                    'foot' => 'right', 'nationality' => 'Maroc', 'birth_date' => null,
                    'height_cm' => 181, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array { return []; }
        };
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $seasons = new SeasonRepository($seasonsPdo);

        $ctrl = new PlayerController($players, $this->statsDouble(), $seasons);
        $data = $ctrl->buildDetail(4);

        $this->assertTrue($data['shotmap'] !== null, 'le fichier deplace en 2025-26/ est retrouve');
        $this->assertSame(27, count($data['shotmap']['shots']));
    }
```

- [ ] **Step 5: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`PlayerController::shotmapPath`/`shotsByCompetitionPath` introuvables, fichiers pas encore déplacés).

- [ ] **Step 6: Déplacer les deux fichiers JSON**

```bash
mkdir -p database/seeds/verified/2025-26
git mv database/seeds/verified/understat-shots-2025.json database/seeds/verified/2025-26/understat-shots-2025.json
git mv database/seeds/verified/fbref-shots-by-competition-2025.json database/seeds/verified/2025-26/fbref-shots-by-competition-2025.json
```

- [ ] **Step 7: Modifier `PlayerController` : chemins paramétrés par saison**

Dans `php/controllers/PlayerController.php`, remplacer `shotmap()` et `shotsByCompetition()`, et modifier `buildDetail()` pour résoudre la saison du joueur :

```php
    public function buildDetail(int $id): ?array
    {
        $player = $this->players->find($id);
        if ($player === null) {
            return null;
        }

        $season = $this->seasons->find($player->seasonId);
        $seasonLabel = $season?->label ?? '';

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
            'shotmap'  => $this->shotmap($id, $player->fullName(), $seasonLabel),
            'shotsByComp' => $this->shotsByCompetition($id, $seasonLabel),
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

    // Chemin du fichier de tirs Understat pour une saison donnée (ex. '2025-26' ->
    // .../2025-26/understat-shots-2025.json). Statique et pure : testable sans
    // accès disque, et réutilisée par shotmap().
    public static function shotmapPath(string $seasonLabel): string
    {
        $year = substr($seasonLabel, 0, 4);
        return BASE_PATH . "/database/seeds/verified/{$seasonLabel}/understat-shots-{$year}.json";
    }

    // Chemin du fichier de tirs par compétition FBref pour une saison donnée.
    public static function shotsByCompetitionPath(string $seasonLabel): string
    {
        $year = substr($seasonLabel, 0, 4);
        return BASE_PATH . "/database/seeds/verified/{$seasonLabel}/fbref-shots-by-competition-{$year}.json";
    }

    // Carte des tirs du joueur : tirs vérifiés avec coordonnées réelles (Understat),
    // indexés par id joueur, pour la saison du joueur consulté. Renvoie null si le
    // joueur n'a pas de tirs référencés, ou si aucun fichier n'existe encore pour
    // cette saison (saison en cours, pas encore de données Understat).
    private function shotmap(int $id, string $fullName, string $seasonLabel): ?array
    {
        if ($seasonLabel === '') {
            return null;
        }
        $file = self::shotmapPath($seasonLabel);
        if (!is_file($file)) {
            return null;
        }
        $all = json_decode((string) file_get_contents($file), true);
        $entry = $all['players'][(string) $id] ?? null;
        if (!$entry || empty($entry['shots'])) {
            return null;
        }
        return [
            'player'      => $entry['name'] ?? $fullName,
            'season'      => $all['season'] ?? '',
            'competition' => $all['competition'] ?? '',
            'source'      => $all['source'] ?? '',
            'shots_total' => (int) ($entry['shots_total'] ?? count($entry['shots'])),
            'goals'       => (int) ($entry['goals'] ?? 0),
            'xg_total'    => (float) ($entry['xg_total'] ?? 0),
            'shots'       => $entry['shots'],
        ];
    }

    // Tirs par compétition (FBref), pour la saison du joueur consulté. Renvoie
    // null si aucun fichier n'existe encore pour cette saison.
    private function shotsByCompetition(int $id, string $seasonLabel): ?array
    {
        if ($seasonLabel === '') {
            return null;
        }
        $file = self::shotsByCompetitionPath($seasonLabel);
        if (!is_file($file)) {
            return null;
        }
        $all = json_decode((string) file_get_contents($file), true);
        $entry = $all['players'][(string) $id] ?? null;
        if (!$entry || empty($entry['byCompetition'])) {
            return null;
        }
        return [
            'season' => $all['season'] ?? '',
            'source' => $all['source'] ?? '',
            'rows'   => $entry['byCompetition'],
            'total'  => $entry['total'] ?? ['shots' => 0, 'goals' => 0],
        ];
    }
```

- [ ] **Step 8: Ajouter la source `understat` manquante**

Dans `database/seeds/verified/sources.php`, ajouter une entrée après celle de `fbref_players_l1` (le tableau retourné gagne un élément, aucune entrée existante n'est modifiée) :

```php
    [
        'key'          => 'understat',
        'label'        => 'Understat : tirs individuels avec xG, saison PSG',
        'url'          => 'https://understat.com/team/Paris_Saint_Germain/2025',
        'collected_at' => '2026-08-11',
        'confidence'   => 'verified',
        'note'         => 'Export JSON Understat (database/seeds/verified/{saison}/understat-shots-{année}.json) : '
            . 'position, minute et xG de chaque tir, par joueur.',
    ],
```

- [ ] **Step 9: Lancer la suite complète et vérifier la migration**

Run: `php tests/run.php`
Expected: `OK`, tous les tests passent, y compris les nouveaux.

Run: `php database/migrate.php`
Expected: les deux lignes habituelles (2025-26 avec ses vraies données, 2026-27 à zéro), sans erreur liée à la nouvelle source.

- [ ] **Step 10: Commit**

```bash
git add php/repositories/SeasonRepository.php php/controllers/PlayerController.php database/seeds/verified/sources.php database/seeds/verified/2025-26/understat-shots-2025.json database/seeds/verified/2025-26/fbref-shots-by-competition-2025.json tests/unit/SeasonRepositoryTest.php tests/unit/PlayerControllerTest.php
git commit -m "$(cat <<'EOF'
fix(joueurs): paramètre les chemins de tirs par saison

shotmap()/shotsByCompetition() construisaient un chemin codé en dur
sur 2025-26 : un joueur 2026-27 aurait pu afficher, par collision
d'id, la carte de tirs d'un joueur totalement différent. Les chemins
dépendent désormais de la saison du joueur consulté, via
SeasonRepository::find() (nouveau) et deux méthodes statiques pures
et testables.

Déplace aussi les fichiers de tirs 2025-26 dans leur sous-dossier de
saison, et ajoute la source Understat manquante au catalogue (jamais
listée jusqu'ici sur la page Méthodologie malgré son usage réel).
EOF
)"
```

---

### Task 2: Fonctions de détection des nouveautés (déterministes, testables)

**Files:**
- Create: `database/seeds/SeasonUpdateHelpers.php`
- Test: `tests/unit/SeasonUpdateHelpersTest.php`

**Interfaces:**
- Consumes: rien (fonctions pures sur des tableaux).
- Produces: `season_update_new_l1_matches(array $scraped, array $existing): array`, `season_update_new_other_matches(array $scraped, array $existing): array`, `season_update_roster_diff(array $scrapedRoster, array $existingPlayers): array`, `season_update_unknown_competitions(array $scrapedCompetitionNames, array $knownCompetitions): array`. Consommées par le prompt de la tâche planifiée (Task 4), jamais par le code applicatif.

- [ ] **Step 1: Écrire les tests (échouent : fichier/fonctions absents)**

Créer `tests/unit/SeasonUpdateHelpersTest.php` :

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateHelpers.php';

// Vérifie les fonctions déterministes de détection utilisées par la tâche
// planifiée d'automatisation (spec 2026-09-08), sur des données synthétiques :
// aucun accès réseau ni fichier, tout est en mémoire.
final class SeasonUpdateHelpersTest extends TestCase
{
    public function testNewL1MatchesDetecteUniquementLesMatchsAbsents(): void
    {
        $existing = [
            ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],
            ['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55],
        ];
        $scraped = [
            ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],
            ['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55],
            ['J3', '2026-08-30', 'Marseille', true, 3, 1, 47000, 65],
        ];
        $new = season_update_new_l1_matches($scraped, $existing);

        $this->assertCount(1, $new);
        $this->assertSame('J3', $new[0][0]);
        $this->assertSame('Marseille', $new[0][2]);
    }

    public function testNewL1MatchesRenvoieVideSiRienDeNouveau(): void
    {
        $rows = [['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60]];
        $this->assertSame([], season_update_new_l1_matches($rows, $rows));
    }

    public function testNewOtherMatchesDetecteUniquementLesMatchsAbsents(): void
    {
        $existing = [
            ['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null],
        ];
        $scraped = [
            ['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null],
            ['ldc', 'Phase de ligue J2', '2026-10-01', 'away', 'Barcelone', 1, 1, 50, 90000, 0, 0, null],
        ];
        $new = season_update_new_other_matches($scraped, $existing);

        $this->assertCount(1, $new);
        $this->assertSame('Barcelone', $new[0][4]);
    }

    public function testRosterDiffDetecteNouveauxEtAbsents(): void
    {
        $scraped = [
            ['first' => 'Bradley', 'last' => 'Barcola'],
            ['first' => 'Nouveau', 'last' => 'Recrue'],
        ];
        $existing = [
            [29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false],
            [17, 'Ancien', 'Parti', 'MF', 'CM', 'France', false],
        ];
        $diff = season_update_roster_diff($scraped, $existing);

        $this->assertSame(['Recrue'], $diff['nouveaux']);
        $this->assertSame(['Parti'], $diff['absents']);
    }

    public function testRosterDiffVideSiEffectifIdentique(): void
    {
        $scraped = [['first' => 'Bradley', 'last' => 'Barcola']];
        $existing = [[29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false]];
        $diff = season_update_roster_diff($scraped, $existing);

        $this->assertSame([], $diff['nouveaux']);
        $this->assertSame([], $diff['absents']);
    }

    public function testUnknownCompetitionsDetecteUneCompetitionInconnue(): void
    {
        $known = [
            ['key' => 'ligue1', 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic'],
            ['key' => 'ldc', 'name' => 'Ligue des Champions', 'type' => 'cup', 'scope' => 'european'],
        ];
        $rencontrees = ['Ligue 1', 'Ligue des Champions', 'Trophée des Champions'];

        $this->assertSame(['Trophée des Champions'], season_update_unknown_competitions($rencontrees, $known));
    }

    public function testUnknownCompetitionsVideSiTouteConnues(): void
    {
        $known = [['key' => 'ligue1', 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic']];
        $this->assertSame([], season_update_unknown_competitions(['Ligue 1'], $known));
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`database/seeds/SeasonUpdateHelpers.php` introuvable).

- [ ] **Step 3: Créer `SeasonUpdateHelpers.php`**

Créer `database/seeds/SeasonUpdateHelpers.php` :

```php
<?php
declare(strict_types=1);
// Fonctions déterministes utilisées par la tâche planifiée d'automatisation de
// la collecte (spec 2026-09-08) : comparent des données fraîchement scrappées
// aux seeds déjà présents. Purement fonctionnelles, aucun accès réseau ni
// fichier ici (l'écriture vit dans SeasonUpdateWriters.php) : testables sur
// des tableaux synthétiques, sans dépendre de FBref/Understat.

// Renvoie les lignes de $scraped absentes de $existing (comparaison par
// round_label + adversaire + date), même format tuple que matches_l1.php :
// [round_label, date, adversaire, est_domicile, buts_psg, buts_adv, affluence, possession].
function season_update_new_l1_matches(array $scraped, array $existing): array
{
    $seen = [];
    foreach ($existing as $row) {
        $seen[$row[0] . '|' . $row[2] . '|' . $row[1]] = true;
    }
    $new = [];
    foreach ($scraped as $row) {
        $key = $row[0] . '|' . $row[2] . '|' . $row[1];
        if (!isset($seen[$key])) {
            $new[] = $row;
        }
    }
    return $new;
}

// Idem pour matches_other.php, format tuple [competition_key, round_label,
// date, venue, adversaire, buts_psg, buts_adv, possession, affluence,
// prolongation, tirs_au_but, score_tab]. Comparaison par competition_key +
// round_label + date.
function season_update_new_other_matches(array $scraped, array $existing): array
{
    $seen = [];
    foreach ($existing as $row) {
        $seen[$row[0] . '|' . $row[1] . '|' . $row[2]] = true;
    }
    $new = [];
    foreach ($scraped as $row) {
        $key = $row[0] . '|' . $row[1] . '|' . $row[2];
        if (!isset($seen[$key])) {
            $new[] = $row;
        }
    }
    return $new;
}

// Compare l'effectif scrappé (liste de ['first' => prénom, 'last' => nom]) à
// players.php existant (tuples [num, prénom, nom, poste, poste_détaillé,
// nationalité, capitaine]). Renvoie ['nouveaux' => [...noms...], 'absents' =>
// [...noms...]]. Résolution par nom/prénom exact, comme migrator_resolve_person :
// aucune normalisation, un écart est toujours signalé à Mathis, jamais résolu
// automatiquement.
function season_update_roster_diff(array $scrapedRoster, array $existingPlayers): array
{
    $scrapedKeys = [];
    foreach ($scrapedRoster as $p) {
        $displayName = $p['last'] !== '' ? $p['last'] : $p['first'];
        $scrapedKeys[$p['first'] . '|' . $p['last']] = $displayName;
    }
    $existingKeys = [];
    foreach ($existingPlayers as $p) {
        [, $first, $last] = $p;
        $displayName = $last !== '' ? $last : $first;
        $existingKeys[$first . '|' . $last] = $displayName;
    }
    return [
        'nouveaux' => array_values(array_diff_key($scrapedKeys, $existingKeys)),
        'absents'  => array_values(array_diff_key($existingKeys, $scrapedKeys)),
    ];
}

// Compare les noms de compétitions rencontrées au calendrier scrappé au
// catalogue compétitions connu (valeurs 'name' de competitions.php). Renvoie
// la liste des noms de compétitions inconnues (à créer manuellement par Mathis).
function season_update_unknown_competitions(array $scrapedCompetitionNames, array $knownCompetitions): array
{
    $known = array_map(static fn (array $c): string => $c['name'], $knownCompetitions);
    return array_values(array_diff($scrapedCompetitionNames, $known));
}
```

- [ ] **Step 4: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add database/seeds/SeasonUpdateHelpers.php tests/unit/SeasonUpdateHelpersTest.php
git commit -m "$(cat <<'EOF'
feat(automatisation): fonctions de détection des nouveautés

Comparent des données scrappées (matchs, effectif, compétitions) aux
seeds déjà présents pour la saison en cours. Purement fonctionnelles,
testées sur des données synthétiques, sans aucun accès réseau : la
tâche planifiée (spec 2026-09-08) les appellera sur ce qu'elle aura
lu sur FBref/Understat.
EOF
)"
```

---

### Task 3: Fonctions d'écriture des seeds (déterministes, testables)

**Files:**
- Create: `database/seeds/SeasonUpdateWriters.php`
- Test: `tests/unit/SeasonUpdateWritersTest.php`

**Interfaces:**
- Consumes: rien de nouveau (opèrent sur des chemins de fichiers et des tableaux).
- Produces: `season_update_append_matches(string $path, array $newRows): void`, `season_update_append_other_matches(string $path, array $newRows): void`, `season_update_replace_totals(string $path, array $totals, string $comment): void`, `season_update_touch_sources(string $sourcesPath, array $collectedAtByKey): void`. Consommées par le prompt de la tâche planifiée (Task 4).

- [ ] **Step 1: Écrire les tests (échouent : fichier/fonctions absents)**

Créer `tests/unit/SeasonUpdateWritersTest.php` :

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/SeasonUpdateWriters.php';

// Vérifie les fonctions d'écriture de seeds utilisées par la tâche planifiée
// d'automatisation (spec 2026-09-08), sur des fichiers temporaires du
// scratchpad de test : aucune modification des vrais seeds du projet.
final class SeasonUpdateWritersTest extends TestCase
{
    private string $tmpDir;

    private function tmp(string $name): string
    {
        if (!isset($this->tmpDir)) {
            $this->tmpDir = sys_get_temp_dir() . '/psg_season_update_test_' . uniqid();
            mkdir($this->tmpDir);
        }
        return $this->tmpDir . '/' . $name;
    }

    public function testAppendMatchesAjouteALaFinDunFichierExistant(): void
    {
        $path = $this->tmp('matches_l1.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n    ['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60],\n];\n");

        season_update_append_matches($path, [['J2', '2026-08-23', 'Lens', false, 1, 1, 45000, 55]]);

        $rows = require $path;
        $this->assertCount(2, $rows);
        $this->assertSame('J1', $rows[0][0]);
        $this->assertSame('J2', $rows[1][0]);
    }

    public function testAppendMatchesCreeLeFichierSiAbsent(): void
    {
        $path = $this->tmp('matches_l1_nouveau.php');
        season_update_append_matches($path, [['J1', '2026-08-16', 'Nantes', true, 2, 0, 40000, 60]]);

        $this->assertTrue(is_file($path));
        $rows = require $path;
        $this->assertCount(1, $rows);
    }

    public function testAppendOtherMatchesAjouteALaFin(): void
    {
        $path = $this->tmp('matches_other.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [];\n");

        season_update_append_other_matches($path, [['ldc', 'Phase de ligue J1', '2026-09-17', 'home', 'Bayern', 2, 1, 58, 45000, 0, 0, null]]);

        $rows = require $path;
        $this->assertCount(1, $rows);
        $this->assertSame('Bayern', $rows[0][4]);
    }

    public function testReplaceTotalsRemplaceIntegralementLeContenu(): void
    {
        $path = $this->tmp('players_l1_fbref.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn ['Ancien' => ['mp' => 1]];\n");

        season_update_replace_totals($path, ['Barcola' => ['mp' => 10, 'goals' => 5]], 'Totaux mis à jour.');

        $totals = require $path;
        $this->assertSame(['Barcola' => ['mp' => 10, 'goals' => 5]], $totals);
    }

    public function testTouchSourcesMetAJourUniquementLesClesDemandees(): void
    {
        $path = $this->tmp('sources.php');
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn [\n"
            . "    ['key' => 'fbref', 'label' => 'FBref', 'url' => null, 'collected_at' => '2026-01-01', 'confidence' => 'verified', 'note' => ''],\n"
            . "    ['key' => 'understat', 'label' => 'Understat', 'url' => null, 'collected_at' => '2026-01-01', 'confidence' => 'verified', 'note' => ''],\n"
            . "];\n");

        season_update_touch_sources($path, ['fbref' => '2026-09-15']);

        $sources = require $path;
        $byKey = [];
        foreach ($sources as $s) {
            $byKey[$s['key']] = $s;
        }
        $this->assertSame('2026-09-15', $byKey['fbref']['collected_at']);
        $this->assertSame('2026-01-01', $byKey['understat']['collected_at'], 'clé non demandée : inchangée');
        $this->assertSame('FBref', $byKey['fbref']['label'], 'les autres champs restent intacts');
    }
}
```

- [ ] **Step 2: Lancer la suite pour confirmer l'échec**

Run: `php tests/run.php`
Expected: FAIL (`database/seeds/SeasonUpdateWriters.php` introuvable).

- [ ] **Step 3: Créer `SeasonUpdateWriters.php`**

Créer `database/seeds/SeasonUpdateWriters.php` :

```php
<?php
declare(strict_types=1);
// Écriture des fichiers seeds verified/{saison}/ pour la tâche planifiée
// d'automatisation (spec 2026-09-08). Rendu volontairement simple (var_export) :
// ces fichiers sont désormais générés à chaque passage, pas mis en forme à la
// main comme les seeds 2025-26.

// Ajoute $newRows à la fin du tableau retourné par le fichier matches_l1.php
// existant (ou en repart d'un tableau vide si le fichier est encore vide),
// et réécrit le fichier en entier. $newRows est déjà filtré par
// season_update_new_l1_matches() : cette fonction ne fait aucune déduplication.
function season_update_append_matches(string $path, array $newRows): void
{
    $existing = is_file($path) ? require $path : [];
    $all = array_merge($existing, $newRows);
    season_update_write_php_array($path, $all, 'Matchs de Ligue 1, mis à jour automatiquement (voir sources.php).');
}

// Idem pour matches_other.php.
function season_update_append_other_matches(string $path, array $newRows): void
{
    $existing = is_file($path) ? require $path : [];
    $all = array_merge($existing, $newRows);
    season_update_write_php_array($path, $all, 'Matchs hors Ligue 1, mis à jour automatiquement (voir sources.php).');
}

// players_l1_fbref.php et player_season.php portent des totaux cumulatifs
// FBref : remplacés en entier à chaque passage, jamais complétés ligne par
// ligne (une correction FBref d'un mois donné doit se répercuter, pas
// s'additionner à l'ancien total).
function season_update_replace_totals(string $path, array $totals, string $comment): void
{
    season_update_write_php_array($path, $totals, $comment);
}

// Rendu commun : un fichier PHP valide, une seule instruction return, formaté
// par var_export (lisible mais pas mis en forme à la main, à la différence des
// seeds 2025-26 écrits manuellement).
function season_update_write_php_array(string $path, array $data, string $comment): void
{
    $php = "<?php\ndeclare(strict_types=1);\n// {$comment}\n"
        . '// Fichier généré automatiquement, dernière mise à jour : ' . date('Y-m-d') . ".\n"
        . 'return ' . var_export($data, true) . ";\n";
    file_put_contents($path, $php);
}

// Met à jour collected_at pour les clés de sources données (ex.
// ['fbref' => '2026-09-15']), en réécrivant sources.php en entier tout en
// conservant les autres champs de chaque source inchangés. Les clés absentes
// de $collectedAtByKey ne sont pas touchées.
function season_update_touch_sources(string $sourcesPath, array $collectedAtByKey): void
{
    $sources = require $sourcesPath;
    foreach ($sources as &$source) {
        if (isset($collectedAtByKey[$source['key']])) {
            $source['collected_at'] = $collectedAtByKey[$source['key']];
        }
    }
    unset($source);
    $php = "<?php\ndeclare(strict_types=1);\n"
        . "// Sources de données, traçabilité obligatoire (spec section 3).\n"
        . "// `key` permet une résolution stable indépendante de l'ordre d'insertion.\n"
        . "// collected_at mis à jour automatiquement par la tâche planifiée (spec 2026-09-08).\n"
        . 'return ' . var_export($sources, true) . ";\n";
    file_put_contents($sourcesPath, $php);
}
```

- [ ] **Step 4: Lancer la suite complète**

Run: `php tests/run.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add database/seeds/SeasonUpdateWriters.php tests/unit/SeasonUpdateWritersTest.php
git commit -m "$(cat <<'EOF'
feat(automatisation): fonctions d'écriture des seeds 2026-27

Complètent matches_l1.php/matches_other.php, remplacent en entier
players_l1_fbref.php/player_season.php (totaux cumulatifs FBref), et
mettent à jour collected_at dans le catalogue sources.php partagé.
Testées sur des fichiers temporaires, jamais sur les vrais seeds du
projet.
EOF
)"
```

---

### Task 4: Tâche planifiée hebdomadaire

**Files:**
- Create: `docs/superpowers/plans/2026-09-08-automatisation-collecte-2026-27-prompt.md` (texte de référence du prompt, conservé dans le dépôt pour traçabilité et relecture future)
- Registers: une tâche planifiée via l'outil `create_scheduled_task` (stockée par le mécanisme de tâches planifiées, hors du dépôt git)

**Interfaces:**
- Consumes: `season_update_new_l1_matches`, `season_update_new_other_matches`, `season_update_roster_diff`, `season_update_unknown_competitions` (Task 2), `season_update_append_matches`, `season_update_append_other_matches`, `season_update_replace_totals`, `season_update_touch_sources` (Task 3), `PlayerController::shotmapPath`/`shotsByCompetitionPath` (Task 1, pour produire les fichiers de tirs 2026-27 au bon endroit).
- Produces: une tâche planifiée active, identifiant `psg-analytics-collecte-2026-27`, cron `"0 8 * * 1"` (lundi 8h heure locale).

Cette tâche n'est pas du code applicatif classique : c'est la rédaction d'un prompt agentique autonome, enregistré comme tâche planifiée. Il n'y a pas de cycle RED/GREEN au sens TDD ici (aucun outil ne peut « faire échouer » un prompt avant qu'il existe) ; la vérification se fait par relecture du prompt contre la checklist de contraintes, puis par un déclenchement manuel de contrôle.

- [ ] **Step 1: Rédiger le texte du prompt**

Créer `docs/superpowers/plans/2026-09-08-automatisation-collecte-2026-27-prompt.md` avec exactement ce contenu (c'est ce texte qui sera passé tel quel comme `prompt` à l'outil `create_scheduled_task` au Step 3) :

```markdown
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
```

- [ ] **Step 2: Relire le prompt contre la checklist de contraintes**

Vérifier ligne par ligne que le texte ci-dessus :
- Ne mentionne `./deploy.sh` que pour l'interdire explicitement.
- Ne mentionne jamais de mot de passe ni d'identifiant FTP.
- N'écrit jamais dans `players.php` ni `competitions.php`.
- Ne pousse jamais de commit.
- Prévoit un rapport y compris quand rien ne se passe (pour que l'absence de notification ne soit jamais interprétée comme "tout va bien" par défaut).

- [ ] **Step 3: Enregistrer la tâche planifiée**

Appeler l'outil `create_scheduled_task` avec :
- `taskId`: `"psg-analytics-collecte-2026-27"`
- `description`: `"Collecte hebdomadaire des stats PSG 2026-27 (FBref/Understat), lundi 8h"`
- `cronExpression`: `"0 8 * * 1"`
- `prompt`: le contenu exact du fichier créé au Step 1 (copié tel quel, pas résumé)
- `notifyOnCompletion`: `true`

- [ ] **Step 4: Vérifier l'enregistrement**

Appeler `list_scheduled_tasks` et confirmer que `psg-analytics-collecte-2026-27` apparaît, avec le bon `cronExpression` et un `nextRunAt` cohérent (prochain lundi 8h).

- [ ] **Step 5: Déclenchement manuel de contrôle**

Avant de faire confiance au cron hebdomadaire, demander à Mathis s'il souhaite un premier passage de contrôle immédiat (en exécutant le contenu du prompt du Step 1 directement dans une session, hors du mécanisme planifié), pour vérifier en conditions réelles que FBref/Understat répondent bien, que les fichiers générés sont corrects, et que le rapport produit est lisible. Ne jamais déclencher ce test sans une confirmation explicite de Mathis au moment de l'implémentation (il peut préférer attendre le premier lundi réel).

- [ ] **Step 6: Commit du texte de référence du prompt**

```bash
git add docs/superpowers/plans/2026-09-08-automatisation-collecte-2026-27-prompt.md
git commit -m "$(cat <<'EOF'
docs: texte de référence de la tâche planifiée de collecte 2026-27

Conserve dans le dépôt le prompt exact enregistré comme tâche
planifiée (psg-analytics-collecte-2026-27, hebdomadaire), pour
traçabilité et relecture future. Le contenu réellement actif vit dans
le mécanisme de tâches planifiées, hors du dépôt git.
EOF
)"
```

---

## Self-Review

**1. Couverture du spec** : vérification section par section :
- §2 Déclenchement (cron hebdomadaire, prompt autonome) -> Task 4.
- §3 Sources et contournement Cloudflare (navigateur piloté, échec propre) -> Task 4, étapes 1 et 6.
- §4 Détection sans état séparé -> Task 2.
- §5 Écriture (formats identiques 2025-26, sources.php global) -> Task 3, Task 4 étape 8.
- §6 Signalement seul (effectif, compétitions) -> Task 2 (`season_update_roster_diff`/`season_update_unknown_competitions`), Task 4 étape 7 (jamais écrit).
- §7 Correctif shotmap -> Task 1.
- §8 Garde-fous avant commit -> Task 4 étape 8.
- §9 Commit local et notification -> Task 4 étape 9.
- §10 Tests -> Task 1 (shotmap), Task 2 et Task 3 (détection et écriture), toutes en TDD sur données synthétiques.
- §11 Risques -> couverts par les choix de conception (échec propre plutôt que forcé, commit local systématique permettant un retour en arrière).

**2. Points de vigilance** (intégrés aux tâches ci-dessus, listés ici pour mémoire) :
- Le prompt de la Task 4 ne peut pas être testé par `php tests/run.php` : sa fiabilité repose entièrement sur les fonctions testées des Tasks 2 et 3, qu'il doit appeler plutôt que réimplémenter sa propre logique de comparaison à la volée.
- `players_l1_fbref.php` est réécrit en entier à chaque passage (pas complété) : une erreur de lecture un mois donné écraserait un total correct. Le commit local systématique (Task 4, étape 9) permet de revenir en arrière via `git log`/`git revert` si Mathis repère une anomalie.
- Le déclenchement manuel de contrôle (Task 4, Step 5) nécessite l'accord explicite de Mathis avant d'être exécuté : ne pas le lancer de façon autonome pendant l'implémentation de ce plan.

**3. Cohérence des types** : `Season::$label` (déjà existant) est utilisé identiquement dans `PlayerController::shotmapPath()`/`shotsByCompetitionPath()` (Task 1) et dans le format de nommage de fichier attendu par la Task 4 (`verified/{seasonKey}/...-{année}.json`, où `{année}` = les 4 premiers caractères de `{seasonKey}`). Les formats de tuples `matches_l1.php`/`matches_other.php`/`players_l1_fbref.php`/`player_season.php` utilisés dans les Tasks 2, 3 et 4 sont identiques entre eux et identiques aux fichiers 2025-26 existants, vérifiés par lecture directe de ces fichiers avant rédaction du plan.

---

## Choix d'exécution

Une fois ce plan approuvé, deux options pour l'exécuter :

1. **Subagent-Driven (recommandé)** : un sous-agent frais par tâche, revue à deux étages entre chaque tâche.
2. **Exécution en ligne** : exécution par lots avec points de contrôle, dans cette même session.
