# PSG Analytics — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire une plateforme web d'analyse de la saison 2025-26 du PSG, en PHP MVC sans dépendance, adossée à une base relationnelle portable et à une donnée dont la provenance est tracée.

**Architecture:** Front controller unique déléguant à un routeur. Couches strictement séparées : contrôleurs (HTTP), services (métier), repositories (SQL), modèles (entités). Une connexion PDO unique gère indifféremment MySQL (référence) et SQLite (exécution locale). Le front vanilla consomme une API REST JSON et rend les graphiques via un moteur SVG maison.

**Tech Stack:** PHP 8.5 (typage strict, PDO), MySQL 8 / SQLite 3, HTML5, CSS3 (variables et jetons), JavaScript ES modules, aucune dépendance externe (ni Composer, ni npm, ni CDN).

## Global Constraints

- **Zéro dépendance.** Aucun paquet Composer, npm ou CDN. Tout code présent dans le dépôt est écrit dans le dépôt.
- **PHP `declare(strict_types=1);`** en tête de chaque fichier PHP.
- **PSR-12**, fonctions courtes, responsabilité unique, aucun fichier au-delà de ~250 lignes.
- **SQL portable** entre MySQL et SQLite : requêtes préparées systématiques, pas de fonction propriétaire dans les repositories.
- **Palette :** fond `#FAFBFC`, primaire `#0F172A`, accent `#2563EB`, bordures `#E2E8F0`. Police Inter servie localement.
- **Interdictions :** pas de framework CSS, pas de CSS en ligne, pas de duplication, pas de variable ou d'import inutilisés, pas de `console.log`/`var_dump` oubliés, pas de commentaire paraphrase, pas de `TODO`/`FIXME` livrés, aucun secret versionné.
- **Commentaires en français**, expliquant l'intention.
- **Provenance :** tout enregistrement de `matches` et `player_match_stats` référence une ligne de `data_sources` (`verified` ou `estimated`).
- **Générateur déterministe :** graine fixe, les seeds sont reproductibles à l'octet près.
- **Definition of Done :** Lighthouse > 95 (Perf/A11y/Best practices), zéro erreur console, zéro requête réseau en échec, responsive mobile/tablette/bureau, clair et sombre vérifiés, install < 5 min, tests au vert, Git propre, doc à jour.

---

## Carte des fichiers

**Socle**
- `index.php` — front controller
- `.htaccess` — réécriture Apache vers `index.php`
- `php/config/config.php` — constantes d'environnement
- `php/config/database.php` — paramètres de connexion (driver, dsn)
- `php/config/routes.php` — table de routage déclarative
- `php/core/Database.php` — singleton PDO MySQL/SQLite
- `php/core/Router.php` — résolution URL → contrôleur/méthode
- `php/core/Request.php` — encapsulation requête (méthode, params, query)
- `php/core/Response.php` — réponses HTML et JSON, enveloppe API
- `php/core/Controller.php` — contrôleur de base (render, json)
- `php/core/View.php` — rendu de vues, échappement
- `php/core/Validator.php` — validation par liste blanche

**Domaine**
- `php/models/{Player,MatchGame,Team,Competition,Season,Statistic,Event,DataSource}.php`
- `php/repositories/{Repository,PlayerRepository,MatchRepository,StatisticRepository,EventRepository,CompetitionRepository}.php`
- `php/services/{KpiService,RankingService,ComparisonService,HeatmapService,CsvExporter,PdfExporter}.php`

**HTTP**
- `php/controllers/{HomeController,DashboardController,PlayerController,MatchController,MethodologyController}.php`
- `php/controllers/Api/{PlayerApiController,MatchApiController,StatsApiController,CompetitionApiController,ExportApiController}.php`

**Vues**
- `php/views/layouts/main.php` — squelette HTML
- `php/views/partials/{header,footer,nav,player_card,match_row,kpi_card,source_badge}.php`
- `php/views/pages/{home,dashboard,players,player_detail,matches,methodology}.php`
- `php/views/errors/{404,500}.php`

**Base**
- `database/schema.mysql.sql` — schéma de référence
- `database/schema.sqlite.sql` — miroir SQLite
- `database/migrate.php` — création + peuplement
- `database/seeds/verified/*.php` — données réelles + sources
- `database/seeds/StatGenerator.php` — reconstitution calibrée
- `database/docs/{mcd.md,mld.md,data-dictionary.md}`

**Front**
- `assets/css/{tokens,base,layout,components,charts,pages}.css`
- `assets/js/app.js` — bootstrap front, routeur de vues légères
- `assets/js/modules/{api,table,search,compare,theme,export}.js`
- `assets/js/charts/{scale,axis,bar,line,radar,donut,heatmap}.js`
- `assets/images/` — logos SVG, captures
- `assets/fonts/` — Inter (woff2, licence OFL)

**Tests**
- `tests/run.php` — runner maison
- `tests/TestCase.php` — assertions
- `tests/unit/*Test.php`

---

## Phase 0 — Socle exécutable

### Task 0.1 : Runner de tests maison

**Files:**
- Create: `tests/TestCase.php`, `tests/run.php`, `tests/unit/SmokeTest.php`

**Interfaces:**
- Produces: classe `TestCase` avec `assertSame($a,$b,$msg)`, `assertTrue($c,$msg)`, `assertThrows(callable,$class)`, `assertCount(int,$arr)`. `run.php` découvre `tests/unit/*Test.php`, instancie chaque classe, exécute les méthodes `test*`, imprime un résumé et sort avec code 1 si échec.

- [ ] **Step 1 : Écrire le test de fumée**

```php
<?php
declare(strict_types=1);
// tests/unit/SmokeTest.php
final class SmokeTest extends TestCase
{
    public function testVraiEstVrai(): void
    {
        $this->assertTrue(true, 'le runner fonctionne');
    }

    public function testEgalite(): void
    {
        $this->assertSame(4, 2 + 2, 'addition');
    }
}
```

- [ ] **Step 2 : Écrire `TestCase`**

```php
<?php
declare(strict_types=1);
// tests/TestCase.php
abstract class TestCase
{
    private int $ok = 0;

    public function assertTrue(bool $cond, string $msg = ''): void
    {
        if (!$cond) {
            throw new AssertionError("assertTrue échec: {$msg}");
        }
        $this->ok++;
    }

    public function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            $e = var_export($expected, true);
            $a = var_export($actual, true);
            throw new AssertionError("assertSame échec: {$msg} (attendu {$e}, obtenu {$a})");
        }
        $this->ok++;
    }

    public function assertCount(int $expected, array $arr, string $msg = ''): void
    {
        $this->assertSame($expected, count($arr), $msg);
    }

    public function assertThrows(callable $fn, string $class, string $msg = ''): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->assertTrue($e instanceof $class, "{$msg} (type reçu " . $e::class . ')');
            return;
        }
        throw new AssertionError("assertThrows échec: aucune exception ({$msg})");
    }

    public function assertions(): int
    {
        return $this->ok;
    }
}
```

- [ ] **Step 3 : Écrire `run.php`**

```php
<?php
declare(strict_types=1);
// tests/run.php
require __DIR__ . '/TestCase.php';

$files = glob(__DIR__ . '/unit/*Test.php') ?: [];
$failures = [];
$assertions = 0;
$count = 0;

foreach ($files as $file) {
    require $file;
    $class = basename($file, '.php');
    /** @var TestCase $instance */
    $instance = new $class();
    foreach (get_class_methods($instance) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $count++;
        try {
            $instance->$method();
            echo '.';
        } catch (Throwable $e) {
            echo 'F';
            $failures[] = "{$class}::{$method} — {$e->getMessage()}";
        }
    }
    $assertions += $instance->assertions();
}

echo "\n\n{$count} tests, {$assertions} assertions\n";
if ($failures !== []) {
    echo "\nÉCHECS:\n" . implode("\n", $failures) . "\n";
    exit(1);
}
echo "OK\n";
```

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: `2 tests, 3 assertions` puis `OK`

- [ ] **Step 5 : Commit**

```bash
git add tests/
git commit -m "test: runner de tests maison sans dépendance"
```

### Task 0.2 : Front controller et configuration

**Files:**
- Create: `index.php`, `php/config/config.php`, `php/config/database.php`, `.htaccess`

**Interfaces:**
- Produces: constantes `BASE_PATH`, `ENV`, `DEBUG`. `db_config()` retourne `['driver'=>'sqlite'|'mysql', 'sqlite_path'=>..., 'host'=>..., 'name'=>..., 'user'=>..., 'pass'=>...]`. `index.php` charge la config, capte les erreurs et affiche un message minimal (le routeur viendra en Task 2.2).

- [ ] **Step 1 : `config.php`**

```php
<?php
declare(strict_types=1);
// php/config/config.php
define('BASE_PATH', dirname(__DIR__, 2));
define('ENV', getenv('APP_ENV') ?: 'local');
define('DEBUG', ENV === 'local');
```

- [ ] **Step 2 : `database.php`**

```php
<?php
declare(strict_types=1);
// php/config/database.php — SQLite par défaut pour lancement immédiat
function db_config(): array
{
    $driver = getenv('DB_DRIVER') ?: 'sqlite';
    return [
        'driver'      => $driver,
        'sqlite_path' => BASE_PATH . '/database/psg.sqlite',
        'host'        => getenv('DB_HOST') ?: '127.0.0.1',
        'name'        => getenv('DB_NAME') ?: 'psg_analytics',
        'user'        => getenv('DB_USER') ?: 'root',
        'pass'        => getenv('DB_PASS') ?: '',
    ];
}
```

- [ ] **Step 3 : `index.php` minimal**

```php
<?php
declare(strict_types=1);
// index.php — front controller
require __DIR__ . '/php/config/config.php';
require __DIR__ . '/php/config/database.php';

echo "PSG Analytics — socle en place (" . ENV . ")\n";
```

- [ ] **Step 4 : `.htaccess`**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

- [ ] **Step 5 : Vérifier**

Run: `php -S 127.0.0.1:8000 index.php & sleep 1 && curl -s 127.0.0.1:8000 ; kill %1`
Expected: `PSG Analytics — socle en place (local)`

- [ ] **Step 6 : Commit**

```bash
git add index.php .htaccess php/config/
git commit -m "feat: front controller et configuration SQLite/MySQL"
```

---

## Phase 1 — Base de données

### Task 1.1 : Schémas MySQL et SQLite

**Files:**
- Create: `database/schema.mysql.sql`, `database/schema.sqlite.sql`

**Interfaces:**
- Produces: 8 tables (`teams`, `competitions`, `seasons`, `players`, `matches`, `player_match_stats`, `events`, `data_sources`) avec clés étrangères et index. Colonnes identiques entre les deux fichiers, seuls les types diffèrent (MySQL `INT AUTO_INCREMENT` / SQLite `INTEGER PRIMARY KEY AUTOINCREMENT`, `TINYINT(1)` / `INTEGER`, `ENUM` MySQL rendu par `VARCHAR` + `CHECK` SQLite).

- [ ] **Step 1 : Écrire `schema.sqlite.sql`** (référence exécutable en local)

```sql
-- database/schema.sqlite.sql
PRAGMA foreign_keys = ON;

CREATE TABLE seasons (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    label       TEXT NOT NULL,
    start_date  TEXT NOT NULL,
    end_date    TEXT NOT NULL
);

CREATE TABLE teams (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    short_name  TEXT NOT NULL,
    country     TEXT NOT NULL,
    is_psg      INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE competitions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    type        TEXT NOT NULL CHECK (type IN ('league','cup','super_cup','intercontinental')),
    scope       TEXT NOT NULL CHECK (scope IN ('domestic','european','world'))
);

CREATE TABLE data_sources (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    label        TEXT NOT NULL,
    url          TEXT,
    collected_at TEXT,
    confidence   TEXT NOT NULL CHECK (confidence IN ('verified','estimated')),
    note         TEXT
);

CREATE TABLE players (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id    INTEGER NOT NULL REFERENCES seasons(id),
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

CREATE TABLE matches (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id     INTEGER NOT NULL REFERENCES seasons(id),
    competition_id INTEGER NOT NULL REFERENCES competitions(id),
    round_label   TEXT,
    played_at     TEXT NOT NULL,
    home_team_id  INTEGER NOT NULL REFERENCES teams(id),
    away_team_id  INTEGER NOT NULL REFERENCES teams(id),
    home_goals    INTEGER NOT NULL,
    away_goals    INTEGER NOT NULL,
    went_to_extra INTEGER NOT NULL DEFAULT 0,
    penalty_shootout INTEGER NOT NULL DEFAULT 0,
    penalty_score TEXT,
    attendance    INTEGER,
    psg_possession REAL,
    psg_shots     INTEGER,
    psg_shots_on_target INTEGER,
    source_id     INTEGER NOT NULL REFERENCES data_sources(id)
);

CREATE TABLE player_match_stats (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id     INTEGER NOT NULL REFERENCES players(id),
    match_id      INTEGER NOT NULL REFERENCES matches(id),
    is_starter    INTEGER NOT NULL DEFAULT 0,
    minutes       INTEGER NOT NULL DEFAULT 0,
    goals         INTEGER NOT NULL DEFAULT 0,
    assists       INTEGER NOT NULL DEFAULT 0,
    shots         INTEGER NOT NULL DEFAULT 0,
    shots_on_target INTEGER NOT NULL DEFAULT 0,
    passes        INTEGER NOT NULL DEFAULT 0,
    pass_accuracy REAL,
    duels_won     INTEGER NOT NULL DEFAULT 0,
    yellow_cards  INTEGER NOT NULL DEFAULT 0,
    red_card      INTEGER NOT NULL DEFAULT 0,
    saves         INTEGER NOT NULL DEFAULT 0,
    goals_conceded INTEGER NOT NULL DEFAULT 0,
    rating        REAL,
    xg            REAL NOT NULL DEFAULT 0,
    xag           REAL NOT NULL DEFAULT 0,
    source_id     INTEGER NOT NULL REFERENCES data_sources(id),
    UNIQUE (player_id, match_id)
);

CREATE TABLE events (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id       INTEGER NOT NULL REFERENCES matches(id),
    player_id      INTEGER REFERENCES players(id),
    related_player_id INTEGER REFERENCES players(id),
    type           TEXT NOT NULL CHECK (type IN ('goal','assist','yellow','red','sub_in','sub_out','penalty_goal','own_goal')),
    minute         INTEGER NOT NULL,
    stoppage       INTEGER NOT NULL DEFAULT 0,
    description    TEXT
);

CREATE INDEX idx_players_season ON players(season_id);
CREATE INDEX idx_matches_date ON matches(played_at);
CREATE INDEX idx_matches_competition ON matches(competition_id);
CREATE INDEX idx_pms_player ON player_match_stats(player_id);
CREATE INDEX idx_pms_match ON player_match_stats(match_id);
CREATE INDEX idx_pms_goals ON player_match_stats(goals);
CREATE INDEX idx_events_match ON events(match_id);
```

- [ ] **Step 2 : Écrire `schema.mysql.sql`** (même structure, types MySQL : `INT AUTO_INCREMENT PRIMARY KEY`, `VARCHAR(n)`, `TINYINT(1)`, `DECIMAL(4,1)` pour `psg_possession`/`rating`, `DECIMAL(4,2)` pour `xg`/`xag`, `ENUM(...)` pour les colonnes à domaine fermé, `InnoDB`, `utf8mb4`. Déclarer les `FOREIGN KEY` explicitement en fin de table.)

- [ ] **Step 3 : Vérifier la validité SQLite**

Run: `sqlite3 /tmp/psg_check.sqlite < database/schema.sqlite.sql && echo OK && rm /tmp/psg_check.sqlite`
Expected: `OK` sans erreur

- [ ] **Step 4 : Commit**

```bash
git add database/schema.sqlite.sql database/schema.mysql.sql
git commit -m "feat: schémas MySQL et SQLite portables (8 tables, contraintes, index)"
```

---

## Phase 2 — Noyau MVC

### Task 2.1 : Connexion PDO portable

**Files:**
- Create: `php/core/Database.php`
- Test: `tests/unit/DatabaseTest.php`

**Interfaces:**
- Produces: `Database::connection(): PDO` (singleton), `Database::reset(): void` (tests). En SQLite, active `PRAGMA foreign_keys=ON`. `PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE = FETCH_ASSOC`.

- [ ] **Step 1 : Test**

```php
<?php
declare(strict_types=1);
// tests/unit/DatabaseTest.php
final class DatabaseTest extends TestCase
{
    public function testConnexionSqliteRetournePdo(): void
    {
        putenv('DB_DRIVER=sqlite');
        Database::reset();
        $pdo = Database::connection();
        $this->assertTrue($pdo instanceof PDO, 'instance PDO');
    }

    public function testForeignKeysActivees(): void
    {
        putenv('DB_DRIVER=sqlite');
        Database::reset();
        $pdo = Database::connection();
        $on = $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $this->assertSame('1', (string) $on, 'FK activées');
    }
}
```

Le bootstrap de test (`tests/run.php`) doit charger config + core. Ajouter en tête de `run.php`, après le require de `TestCase.php` :

```php
require dirname(__DIR__) . '/php/config/config.php';
require dirname(__DIR__) . '/php/config/database.php';
spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'repositories', 'services'] as $dir) {
        $path = BASE_PATH . "/php/{$dir}/{$class}.php";
        if (is_file($path)) { require $path; return; }
    }
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL `DatabaseTest` — classe `Database` introuvable

- [ ] **Step 3 : Implémenter `Database`**

```php
<?php
declare(strict_types=1);
// php/core/Database.php
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = db_config();
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if ($cfg['driver'] === 'sqlite') {
            self::$pdo = new PDO('sqlite:' . $cfg['sqlite_path'], null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
```

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5 : Commit**

```bash
git add php/core/Database.php tests/unit/DatabaseTest.php tests/run.php
git commit -m "feat: connexion PDO portable MySQL/SQLite en singleton"
```

### Task 2.2 : Request, Router, Response

**Files:**
- Create: `php/core/Request.php`, `php/core/Router.php`, `php/core/Response.php`, `php/config/routes.php`
- Test: `tests/unit/RouterTest.php`

**Interfaces:**
- Produces:
  - `Request` : `->method(): string`, `->path(): string`, `->query(string $key, $default=null)`, `->segments(): array`.
  - `Router::__construct(array $routes)`, `->match(string $method, string $path): array` retourne `['controller'=>FQCN, 'action'=>string, 'params'=>array]` ou lève `RouteNotFound`. Routes déclarées `['GET /api/players/{id}' => ['PlayerApiController','show']]`.
  - `Response` : `::json(array $data, int $status=200): void`, `::html(string $body, int $status=200): void`, `::apiEnvelope(array $data, ?array $meta=null): array`.

- [ ] **Step 1 : Test du routeur (matching statique et paramétré)**

```php
<?php
declare(strict_types=1);
// tests/unit/RouterTest.php
final class RouterTest extends TestCase
{
    private function router(): Router
    {
        return new Router([
            'GET /' => ['HomeController', 'index'],
            'GET /api/players' => ['PlayerApiController', 'index'],
            'GET /api/players/{id}' => ['PlayerApiController', 'show'],
        ]);
    }

    public function testRouteStatique(): void
    {
        $m = $this->router()->match('GET', '/api/players');
        $this->assertSame('PlayerApiController', $m['controller']);
        $this->assertSame('index', $m['action']);
    }

    public function testRouteParametree(): void
    {
        $m = $this->router()->match('GET', '/api/players/29');
        $this->assertSame('show', $m['action']);
        $this->assertSame('29', $m['params']['id']);
    }

    public function testRouteInconnue(): void
    {
        $this->assertThrows(
            fn() => $this->router()->match('GET', '/inconnu'),
            RouteNotFound::class
        );
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `Router` introuvable

- [ ] **Step 3 : Implémenter `Request`**

```php
<?php
declare(strict_types=1);
// php/core/Request.php
final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/') === '/' ? '/' : rtrim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path())));
    }
}
```

- [ ] **Step 4 : Implémenter `Router` + exception**

```php
<?php
declare(strict_types=1);
// php/core/Router.php
final class RouteNotFound extends RuntimeException {}

final class Router
{
    /** @param array<string, array{0:string,1:string}> $routes */
    public function __construct(private array $routes) {}

    public function match(string $method, string $path): array
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $pattern => $handler) {
            [$verb, $route] = explode(' ', $pattern, 2);
            if ($verb !== strtoupper($method)) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route) . '$#';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['controller' => $handler[0], 'action' => $handler[1], 'params' => $params];
            }
        }
        throw new RouteNotFound("Aucune route pour {$method} {$path}");
    }
}
```

- [ ] **Step 5 : Implémenter `Response`**

```php
<?php
declare(strict_types=1);
// php/core/Response.php
final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    public static function apiEnvelope(array $data, ?array $meta = null): array
    {
        return [
            'data'   => $data,
            'meta'   => $meta ?? [],
            'source' => ['generated_at' => date('c')],
        ];
    }
}
```

- [ ] **Step 6 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 7 : Commit**

```bash
git add php/core/Request.php php/core/Router.php php/core/Response.php tests/unit/RouterTest.php
git commit -m "feat: Request, Router paramétré et Response JSON/HTML"
```

### Task 2.3 : Controller, View, Validator + câblage index.php

**Files:**
- Create: `php/core/Controller.php`, `php/core/View.php`, `php/core/Validator.php`, `php/config/routes.php`, `php/views/errors/404.php`, `php/views/errors/500.php`
- Modify: `index.php`
- Test: `tests/unit/ValidatorTest.php`

**Interfaces:**
- Produces:
  - `View::render(string $page, array $data=[], string $layout='main'): string` — rend `php/views/pages/{page}.php` dans `php/views/layouts/{layout}.php` via variable `$content`. `View::e(string $v): string` échappe (`htmlspecialchars`, `ENT_QUOTES`).
  - `Controller` (abstraite) : `->render(...)` renvoie via `Response::html`, `->json(...)` via `Response::json`.
  - `Validator::int($v, int $min, int $max, int $default): int`, `::inList($v, array $allowed, $default)`, `::string($v, int $maxLen): string`.
  - `routes.php` retourne le tableau complet des routes (web + api, remplies au fil des phases).

- [ ] **Step 1 : Test du Validator (liste blanche de tri)**

```php
<?php
declare(strict_types=1);
// tests/unit/ValidatorTest.php
final class ValidatorTest extends TestCase
{
    public function testIntBorne(): void
    {
        $this->assertSame(20, Validator::int('999', 1, 20, 10));
        $this->assertSame(10, Validator::int('abc', 1, 20, 10));
        $this->assertSame(5, Validator::int('5', 1, 20, 10));
    }

    public function testInListRejetteHorsListe(): void
    {
        $allowed = ['goals', 'assists', 'minutes'];
        $this->assertSame('goals', Validator::inList('goals', $allowed, 'goals'));
        $this->assertSame('goals', Validator::inList('DROP TABLE', $allowed, 'goals'));
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `Validator` introuvable

- [ ] **Step 3 : Implémenter `Validator`**

```php
<?php
declare(strict_types=1);
// php/core/Validator.php
final class Validator
{
    public static function int(mixed $v, int $min, int $max, int $default): int
    {
        if (!is_numeric($v)) {
            return $default;
        }
        $n = (int) $v;
        return max($min, min($max, $n));
    }

    /** @param array<int,string> $allowed */
    public static function inList(mixed $v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? (string) $v : $default;
    }

    public static function string(mixed $v, int $maxLen): string
    {
        return mb_substr(is_string($v) ? trim($v) : '', 0, $maxLen);
    }
}
```

- [ ] **Step 4 : Implémenter `View` et `Controller`**

```php
<?php
declare(strict_types=1);
// php/core/View.php
final class View
{
    public static function render(string $page, array $data = [], string $layout = 'main'): string
    {
        $pagePath = BASE_PATH . "/php/views/pages/{$page}.php";
        if (!is_file($pagePath)) {
            throw new RuntimeException("Vue introuvable: {$page}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $pagePath;
        $content = ob_get_clean();
        ob_start();
        require BASE_PATH . "/php/views/layouts/{$layout}.php";
        return (string) ob_get_clean();
    }

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
```

```php
<?php
declare(strict_types=1);
// php/core/Controller.php
abstract class Controller
{
    protected function render(string $page, array $data = []): void
    {
        Response::html(View::render($page, $data));
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }
}
```

- [ ] **Step 5 : `routes.php` (amorce, complétée aux phases suivantes)**

```php
<?php
declare(strict_types=1);
// php/config/routes.php
return [
    'GET /' => ['HomeController', 'index'],
];
```

- [ ] **Step 6 : Câbler `index.php` avec autoload + dispatch**

```php
<?php
declare(strict_types=1);
// index.php
require __DIR__ . '/php/config/config.php';
require __DIR__ . '/php/config/database.php';

spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'repositories', 'services', 'controllers', 'controllers/Api'] as $dir) {
        $path = BASE_PATH . "/php/{$dir}/{$class}.php";
        if (is_file($path)) { require $path; return; }
    }
});

$request = new Request();
$router = new Router(require BASE_PATH . '/php/config/routes.php');

try {
    $route = $router->match($request->method(), $request->path());
    $controller = new $route['controller']();
    $controller->{$route['action']}($request, $route['params']);
} catch (RouteNotFound) {
    Response::html(View::render('../errors/404', [], 'main'), 404);
} catch (Throwable $e) {
    if (DEBUG) { throw $e; }
    Response::html(View::render('../errors/500', [], 'main'), 500);
}
```

Note : les vues d'erreur vivent dans `php/views/errors/`. `View::render` cherche dans `pages/`, donc les contrôleurs les appelleront via un chemin relatif (`errors/404`). Ajuster `View::render` pour accepter un sous-dossier : remplacer `pages/{$page}.php` par `{$page}.php` quand `$page` contient un `/`, sinon `pages/{$page}.php`. Coder cette règle dans `View::render` à ce Step.

- [ ] **Step 7 : Lancer les tests**

Run: `php tests/run.php`
Expected: PASS (tous)

- [ ] **Step 8 : Commit**

```bash
git add php/core/ php/config/routes.php index.php tests/unit/ValidatorTest.php
git commit -m "feat: Controller, View échappée, Validator liste blanche, dispatch complet"
```

---

## Phase 3 — Modèles et repositories

### Task 3.1 : Modèles (entités typées)

**Files:**
- Create: `php/models/{Player,MatchGame,Team,Competition,Season,Statistic,Event,DataSource}.php`
- Test: `tests/unit/PlayerModelTest.php`

**Interfaces:**
- Produces: chaque modèle a un constructeur promu en `public readonly`, une fabrique `::fromRow(array $row): self`, et pour `Player` une méthode `fullName(): string` et `initials(): string`. `MatchGame` (le mot `Match` est réservé en PHP) porte `result(int $psgTeamId): string` retournant `'W'|'D'|'L'` du point de vue PSG et `psgGoals(int $psgTeamId): int`, `opponentGoals(...)`.

- [ ] **Step 1 : Test**

```php
<?php
declare(strict_types=1);
// tests/unit/PlayerModelTest.php
final class PlayerModelTest extends TestCase
{
    public function testFullNameEtInitiales(): void
    {
        $p = Player::fromRow([
            'id' => 10, 'season_id' => 1, 'shirt_number' => 10,
            'first_name' => 'Ousmane', 'last_name' => 'Dembélé',
            'position' => 'FW', 'detailed_position' => 'CF', 'foot' => 'both',
            'nationality' => 'France', 'birth_date' => '1997-05-15',
            'height_cm' => 178, 'is_captain' => 0,
        ]);
        $this->assertSame('Ousmane Dembélé', $p->fullName());
        $this->assertSame('OD', $p->initials());
    }

    public function testResultatMatchDuPointDeVuePsg(): void
    {
        // PSG (id 1) joue à l'extérieur, gagne 0-1
        $m = MatchGame::fromRow([
            'id' => 1, 'season_id' => 1, 'competition_id' => 1, 'round_label' => 'J1',
            'played_at' => '2025-08-17', 'home_team_id' => 2, 'away_team_id' => 1,
            'home_goals' => 0, 'away_goals' => 1, 'went_to_extra' => 0,
            'penalty_shootout' => 0, 'penalty_score' => null, 'attendance' => 34053,
            'psg_possession' => 62.0, 'psg_shots' => 14, 'psg_shots_on_target' => 6, 'source_id' => 1,
        ]);
        $this->assertSame('W', $m->result(1));
        $this->assertSame(1, $m->psgGoals(1));
        $this->assertSame(0, $m->opponentGoals(1));
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `Player` introuvable

- [ ] **Step 3 : Implémenter `Player`**

```php
<?php
declare(strict_types=1);
// php/models/Player.php
final class Player
{
    public function __construct(
        public readonly int $id,
        public readonly int $seasonId,
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
            (int) $r['id'], (int) $r['season_id'],
            $r['shirt_number'] !== null ? (int) $r['shirt_number'] : null,
            (string) $r['first_name'], (string) $r['last_name'],
            (string) $r['position'], $r['detailed_position'] ?? null, $r['foot'] ?? null,
            (string) $r['nationality'], $r['birth_date'] ?? null,
            $r['height_cm'] !== null ? (int) $r['height_cm'] : null,
            (bool) $r['is_captain'],
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->firstName, 0, 1) . mb_substr($this->lastName, 0, 1));
    }
}
```

- [ ] **Step 4 : Implémenter `MatchGame`** (constructeur promu, `fromRow`, `result/psgGoals/opponentGoals` selon la logique domicile/extérieur du test) **et les modèles restants** (`Team`, `Competition`, `Season`, `Statistic`, `Event`, `DataSource`) sur le même patron `fromRow` + propriétés `readonly`. Pas de logique au-delà des accès ; `Statistic` porte les champs de `player_match_stats`.

- [ ] **Step 5 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 6 : Commit**

```bash
git add php/models/ tests/unit/PlayerModelTest.php
git commit -m "feat: modèles readonly avec fabriques fromRow et logique de résultat"
```

### Task 3.2 : Repository de base + PlayerRepository

**Files:**
- Create: `php/repositories/Repository.php`, `php/repositories/PlayerRepository.php`
- Test: `tests/unit/PlayerRepositoryTest.php`

**Interfaces:**
- Consumes: `Database::connection()`, `Player::fromRow()`, `Validator`.
- Produces:
  - `Repository` (abstraite) : `__construct(?PDO $pdo=null)` (injectable pour tests), `protected fetchAll(string $sql, array $params=[]): array`, `protected fetchOne(string $sql, array $params=[]): ?array`.
  - `PlayerRepository` : `all(): array` (Player[]), `find(int $id): ?Player`, `paginate(int $page, int $perPage, string $sortColumn, string $order, ?string $position): array` retournant `['items'=>Player[], 'total'=>int]`. La colonne de tri est validée en amont par le contrôleur (liste blanche), le repository reçoit une colonne sûre.

- [ ] **Step 1 : Test avec base SQLite en mémoire**

```php
<?php
declare(strict_types=1);
// tests/unit/PlayerRepositoryTest.php
final class PlayerRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT)");
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("INSERT INTO players VALUES (1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,5,'Marquinhos','','DF','CB','right','Brésil','1994-05-14',183,1)");
        return $pdo;
    }

    public function testFindRetourneJoueur(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $p = $repo->find(1);
        $this->assertTrue($p instanceof Player, 'trouvé');
        $this->assertSame('Bradley Barcola', $p->fullName());
    }

    public function testPaginateFiltrePosition(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $res = $repo->paginate(1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(1, $res['total']);
        $this->assertSame('Marquinhos', $res['items'][0]->lastName);
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `PlayerRepository` introuvable

- [ ] **Step 3 : Implémenter `Repository`**

```php
<?php
declare(strict_types=1);
// php/repositories/Repository.php
abstract class Repository
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
```

- [ ] **Step 4 : Implémenter `PlayerRepository`**

```php
<?php
declare(strict_types=1);
// php/repositories/PlayerRepository.php
final class PlayerRepository extends Repository
{
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];

    public function find(int $id): ?Player
    {
        $row = $this->fetchOne('SELECT * FROM players WHERE id = ?', [$id]);
        return $row ? Player::fromRow($row) : null;
    }

    public function all(): array
    {
        return array_map(
            Player::fromRow(...),
            $this->fetchAll('SELECT * FROM players ORDER BY last_name')
        );
    }

    public function paginate(int $page, int $perPage, string $sortColumn, string $order, ?string $position): array
    {
        $column = in_array($sortColumn, self::SORTABLE, true) ? $sortColumn : 'last_name';
        $dir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $where = $position !== null ? 'WHERE position = :position' : '';
        $params = $position !== null ? ['position' => $position] : [];

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM players {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM players {$where} ORDER BY {$column} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(Player::fromRow(...), $rows), 'total' => $total];
    }
}
```

Note sécurité : `$column` et `$dir` proviennent d'une liste blanche, jamais concaténés depuis l'entrée brute. `$perPage`/`$offset` sont des entiers issus de `Validator::int`.

- [ ] **Step 5 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 6 : Commit**

```bash
git add php/repositories/Repository.php php/repositories/PlayerRepository.php tests/unit/PlayerRepositoryTest.php
git commit -m "feat: Repository de base et PlayerRepository (pagination, tri liste blanche)"
```

### Task 3.3 : MatchRepository, StatisticRepository, EventRepository, CompetitionRepository

**Files:**
- Create: `php/repositories/{MatchRepository,StatisticRepository,EventRepository,CompetitionRepository}.php`
- Test: `tests/unit/StatisticRepositoryTest.php`

**Interfaces:**
- Produces:
  - `MatchRepository` : `recent(int $limit): array`, `paginate(int $page,int $perPage,?int $competitionId,?string $result,int $psgTeamId): array`, `find(int $id): ?MatchGame`.
  - `StatisticRepository` : `seasonTotalsByPlayer(int $playerId): array` (agrégat SUM), `topScorers(int $limit, ?int $competitionId): array` (jointure players, retourne `['player'=>Player,'goals'=>int,'assists'=>int,...]`), `timeline(int $playerId): array` (par match, ordonné par date), `byMatch(int $matchId): array`.
  - `EventRepository` : `byMatch(int $matchId): array`.
  - `CompetitionRepository` : `all(): array`, `standings(int $psgTeamId): array` (bilan V/N/D + buts par compétition, calculé en SQL avec `CASE`).

- [ ] **Step 1 : Test de l'agrégat top buteurs**

```php
<?php
declare(strict_types=1);
// tests/unit/StatisticRepositoryTest.php
final class StatisticRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, competition_id INT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT, assists INT, minutes INT)");
        $pdo->exec("INSERT INTO players VALUES (1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0),(2,1,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (1,1),(2,1)");
        $pdo->exec("INSERT INTO player_match_stats (player_id,match_id,goals,assists,minutes) VALUES (1,1,2,1,90),(1,2,1,0,90),(2,1,1,2,80)");
        return $pdo;
    }

    public function testTopScorersOrdonne(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(5, null);
        $this->assertSame('Barcola', $top[0]['player']->lastName);
        $this->assertSame(3, $top[0]['goals']);
        $this->assertSame('Dembélé', $top[1]['player']->lastName);
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `StatisticRepository` introuvable

- [ ] **Step 3 : Implémenter `StatisticRepository::topScorers` (+ autres méthodes)**

```php
<?php
declare(strict_types=1);
// php/repositories/StatisticRepository.php
final class StatisticRepository extends Repository
{
    public function topScorers(int $limit, ?int $competitionId): array
    {
        $join = $competitionId !== null ? 'JOIN matches m ON m.id = s.match_id AND m.competition_id = :comp' : '';
        $params = $competitionId !== null ? ['comp' => $competitionId] : [];
        $rows = $this->fetchAll(
            "SELECT p.*, SUM(s.goals) goals, SUM(s.assists) assists, SUM(s.minutes) minutes
             FROM player_match_stats s
             JOIN players p ON p.id = s.player_id
             {$join}
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

    // seasonTotalsByPlayer, timeline, byMatch : mêmes patterns (SUM groupé, ORDER BY date)
}
```

Implémenter dans le même Step `seasonTotalsByPlayer(int $playerId): array` (une ligne d'agrégats SUM sur tous les matchs du joueur), `timeline(int $playerId): array` (jointure matches, `ORDER BY m.played_at`, retourne date + buts + passes + minutes + note par match), `byMatch(int $matchId): array` (stats de chaque joueur aligné, jointure players).

- [ ] **Step 4 : Implémenter `MatchRepository`, `EventRepository`, `CompetitionRepository`** selon les interfaces ci-dessus. Pour `CompetitionRepository::standings`, calculer en SQL par compétition : `SUM(CASE WHEN (home_team_id=:psg AND home_goals>away_goals) OR (away_team_id=:psg AND away_goals>home_goals) THEN 1 ELSE 0 END)` pour les victoires, symétrique pour nuls et défaites, et les buts pour/contre via `CASE` sur le côté PSG.

- [ ] **Step 5 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 6 : Commit**

```bash
git add php/repositories/ tests/unit/StatisticRepositoryTest.php
git commit -m "feat: repositories matches, stats, events, compétitions (agrégats SQL)"
```

---

## Phase 4 — Données tracées et seeds

### Task 4.1 : Données vérifiées

**Files:**
- Create: `database/seeds/verified/{seasons,teams,competitions,sources,players,matches_l1,scorers_l1,discipline_l1}.php`

**Interfaces:**
- Produces: chaque fichier retourne un tableau PHP typé. `players.php` : 24 lignes (numéro, prénom, nom, poste GK/DF/MF/FW, poste détaillé, nationalité, capitaine). `matches_l1.php` : 23 matchs vérifiés (date, adversaire, domicile bool, buts_psg, buts_adv, affluence). `scorers_l1.php` : `['Barcola'=>['goals'=>11,'apps'=>29], 'Dembélé'=>['goals'=>10,'apps'=>22], ...]`. `discipline_l1.php` : cartons par nom. `sources.php` : lignes `data_sources` avec URL et `collected_at='2026-07-20'`.

- [ ] **Step 1 : Écrire `players.php`** avec l'effectif complet (données de la spec section 3) :

```php
<?php
declare(strict_types=1);
// database/seeds/verified/players.php
return [
    // [num, prénom, nom, position, position_détaillée, nationalité, capitaine]
    [30, 'Lucas', 'Chevalier', 'GK', 'GK', 'France', false],
    [39, 'Matvey', 'Safonov', 'GK', 'GK', 'Russie', false],
    [89, 'Renato', 'Marin', 'GK', 'GK', 'Italie', false],
    [2,  'Achraf', 'Hakimi', 'DF', 'RB', 'Maroc', false],
    [4,  'Lucas', 'Beraldo', 'DF', 'CB', 'Brésil', false],
    [5,  'Marquinhos', '', 'DF', 'CB', 'Brésil', true],
    [6,  'Illia', 'Zabarnyi', 'DF', 'CB', 'Ukraine', false],
    [21, 'Lucas', 'Hernandez', 'DF', 'LB', 'France', false],
    [25, 'Nuno', 'Mendes', 'DF', 'LB', 'Portugal', false],
    [51, 'Willian', 'Pacho', 'DF', 'CB', 'Équateur', false],
    [8,  'Fabián', 'Ruiz', 'MF', 'CM', 'Espagne', false],
    [17, 'Vitinha', '', 'MF', 'DM', 'Portugal', false],
    [19, 'Kang-in', 'Lee', 'MF', 'AM', 'Corée du Sud', false],
    [24, 'Senny', 'Mayulu', 'MF', 'CM', 'France', false],
    [27, 'Dro', 'Fernández', 'MF', 'AM', 'Espagne', false],
    [33, 'Warren', 'Zaïre-Emery', 'MF', 'CM', 'France', false],
    [87, 'João', 'Neves', 'MF', 'CM', 'Portugal', false],
    [7,  'Khvicha', 'Kvaratskhelia', 'FW', 'LW', 'Géorgie', false],
    [9,  'Gonçalo', 'Ramos', 'FW', 'CF', 'Portugal', false],
    [10, 'Ousmane', 'Dembélé', 'FW', 'CF', 'France', false],
    [14, 'Désiré', 'Doué', 'FW', 'RW', 'France', false],
    [29, 'Bradley', 'Barcola', 'FW', 'LW', 'France', false],
    [47, 'Quentin', 'Ndjantou', 'FW', 'LW', 'France', false],
    [49, 'Ibrahim', 'Mbaye', 'FW', 'RW', 'Sénégal', false],
];
```

- [ ] **Step 2 : Écrire `matches_l1.php`** avec les 23 matchs vérifiés de la spec (les scores collectés côté « PSG d'abord »). Format : `[date, adversaire, est_domicile, buts_psg, buts_adversaire, affluence]`. Recopier les 23 lignes de la section 3 de la spec, en convertissant chaque score vers le point de vue PSG.

- [ ] **Step 3 : Écrire `scorers_l1.php`, `discipline_l1.php`, `teams.php`, `competitions.php`, `seasons.php`, `sources.php`** avec les valeurs vérifiées de la spec (top 5 buteurs, passeurs, 10 lignes de discipline, adversaires de L1 comme teams, 6 compétitions, saison 2025-26, sources avec URLs Wikipédia/ESPN et `confidence`).

- [ ] **Step 4 : Vérifier le chargement**

Run: `php -r 'require "database/seeds/verified/players.php";' && php -r '$p=require "database/seeds/verified/players.php"; echo count($p) === 24 ? "24 joueurs OK\n" : "ERREUR\n";'`
Expected: `24 joueurs OK`

- [ ] **Step 5 : Commit**

```bash
git add database/seeds/verified/
git commit -m "data: jeu de données vérifié (effectif, matchs L1, buteurs, discipline, sources)"
```

### Task 4.2 : Générateur calibré

**Files:**
- Create: `database/seeds/StatGenerator.php`
- Test: `tests/unit/StatGeneratorTest.php`

**Interfaces:**
- Consumes: les tableaux vérifiés (Task 4.1).
- Produces: `StatGenerator::__construct(int $seed=2026)`, `->distributeGoals(int $totalGoals, array $weights): array` répartit `$totalGoals` entiers sur des joueurs selon des poids en respectant EXACTEMENT la somme, `->minutesForMatch(array $lineup): array` borne les minutes à [0,90+] et à 11 titulaires, `->rating(int $goals,int $assists,int $minutes): float`. Déterministe : même seed → même sortie.

- [ ] **Step 1 : Test de conservation de la somme**

```php
<?php
declare(strict_types=1);
// tests/unit/StatGeneratorTest.php
final class StatGeneratorTest extends TestCase
{
    public function testDistributionConserveLeTotal(): void
    {
        $gen = new StatGenerator(2026);
        $repartition = $gen->distributeGoals(74, [29 => 11, 10 => 10, 7 => 8, 14 => 7, 9 => 6, 33 => 3]);
        $this->assertSame(74, array_sum($repartition), 'somme conservée');
    }

    public function testDeterministe(): void
    {
        $a = (new StatGenerator(2026))->distributeGoals(50, [1 => 5, 2 => 3, 3 => 2]);
        $b = (new StatGenerator(2026))->distributeGoals(50, [1 => 5, 2 => 3, 3 => 2]);
        $this->assertSame($a, $b, 'reproductible');
    }

    public function testRespecteLesAncrages(): void
    {
        // les buteurs connus doivent au moins recevoir leur total vérifié
        $gen = new StatGenerator(2026);
        $r = $gen->distributeGoals(74, [29 => 11, 10 => 10, 7 => 8, 14 => 7, 9 => 6]);
        $this->assertTrue($r[29] >= 11, 'Barcola >= 11');
        $this->assertTrue($r[10] >= 10, 'Dembélé >= 10');
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `StatGenerator` introuvable

- [ ] **Step 3 : Implémenter `StatGenerator`**

```php
<?php
declare(strict_types=1);
// database/seeds/StatGenerator.php
final class StatGenerator
{
    public function __construct(int $seed = 2026)
    {
        mt_srand($seed);
    }

    /**
     * Répartit $total buts entiers sur des joueurs selon des poids,
     * en garantissant que la somme finale égale exactement $total
     * et que chaque poids fourni est un plancher (ancrage des buteurs connus).
     * @param array<int,int> $weights player_id => poids/plancher
     * @return array<int,int> player_id => buts
     */
    public function distributeGoals(int $total, array $weights): array
    {
        $result = $weights;                 // les planchers vérifiés
        $assigned = array_sum($weights);
        $remaining = $total - $assigned;
        if ($remaining <= 0) {
            return $result;                 // déjà au total, rien à ajouter
        }
        $ids = array_keys($weights);
        $sumWeights = array_sum($weights);
        for ($i = 0; $i < $remaining; $i++) {
            $pick = mt_rand(1, $sumWeights);
            $cursor = 0;
            foreach ($ids as $id) {
                $cursor += $weights[$id];
                if ($pick <= $cursor) {
                    $result[$id]++;
                    break;
                }
            }
        }
        return $result;
    }

    public function rating(int $goals, int $assists, int $minutes): float
    {
        $base = 6.0 + $goals * 0.7 + $assists * 0.4;
        $base += $minutes >= 60 ? 0.2 : 0.0;
        return round(min(10.0, $base), 1);
    }

    /**
     * @param array<int,bool> $lineup player_id => est_titulaire
     * @return array<int,int> player_id => minutes
     */
    public function minutesForMatch(array $lineup): array
    {
        $minutes = [];
        foreach ($lineup as $id => $starter) {
            $minutes[$id] = $starter ? mt_rand(60, 90) : mt_rand(1, 30);
        }
        return $minutes;
    }
}
```

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5 : Commit**

```bash
git add database/seeds/StatGenerator.php tests/unit/StatGeneratorTest.php
git commit -m "feat: générateur de stats déterministe et calibré sur les totaux vérifiés"
```

### Task 4.3 : Script de migration et peuplement

**Files:**
- Create: `database/migrate.php`
- Test: `tests/unit/SeedIntegrityTest.php`

**Interfaces:**
- Consumes: schéma SQLite, données vérifiées, `StatGenerator`.
- Produces: `php database/migrate.php` (ré)crée `database/psg.sqlite`, exécute le schéma, insère seasons/teams/competitions/sources/players, insère les 23 matchs vérifiés + les 11 reconstitués, génère `player_match_stats` calibré, puis **vérifie les identités** et sort en erreur (code 1) si une identité échoue. Fonction réutilisable `run_migration(PDO $pdo): array` retournant un rapport `['matches'=>int,'players'=>int,'l1_wins'=>int,...]` pour le test.

- [ ] **Step 1 : Test d'intégrité (identités de la spec)**

```php
<?php
declare(strict_types=1);
// tests/unit/SeedIntegrityTest.php
require_once dirname(__DIR__, 1) . '/../database/migrate.php'; // expose run_migration()

final class SeedIntegrityTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testBilanLigue1(): void
    {
        $pdo = $this->migratedPdo();
        $psg = (int) $pdo->query("SELECT id FROM teams WHERE is_psg = 1")->fetchColumn();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $row = $pdo->query("SELECT
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals>away_goals) OR (away_team_id={$psg} AND away_goals>home_goals) THEN 1 ELSE 0 END) w,
            SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END) d,
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals<away_goals) OR (away_team_id={$psg} AND away_goals<home_goals) THEN 1 ELSE 0 END) l
            FROM matches WHERE competition_id = {$comp}")->fetch();
        $this->assertSame(24, (int) $row['w'], '24 victoires L1');
        $this->assertSame(4, (int) $row['d'], '4 nuls L1');
        $this->assertSame(6, (int) $row['l'], '6 défaites L1');
    }

    public function testEffectifComplet(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
        $this->assertSame(24, $n, '24 joueurs');
    }

    public function testButsIndividuelsEgalentButsCollectifsL1(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $indiv = (int) $pdo->query("SELECT SUM(goals) FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetchColumn();
        $this->assertSame(74, $indiv, 'somme buts individuels L1 = 74');
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `run_migration` introuvable

- [ ] **Step 3 : Implémenter `migrate.php`** avec `run_migration(PDO $pdo): array`. Étapes internes : exécuter le schéma SQLite (charger le fichier `.sql`, l'exécuter) ; insérer saison, teams (PSG + adversaires), compétitions, sources ; insérer les 24 joueurs ; insérer les 23 matchs vérifiés (source `verified`) ; ajouter 11 matchs de L1 reconstitués (source `estimated`) dont les scores sont **calibrés** pour que le bilan total L1 atteigne exactement 24 V / 4 N / 6 D et 74-29 ; répartir les buts L1 par joueur via `StatGenerator::distributeGoals(74, poids)` où les poids ancrent Barcola 11, Dembélé 10, Kvaratskhelia 8, Doué 7, Ramos 6 puis distribuent le reste ; générer les lignes `player_match_stats` (minutes, buts affectés aux matchs, cartons vérifiés respectés) ; à la fin, calculer le bilan et `throw new RuntimeException` si une identité échoue. Le mode CLI (`if (PHP_SAPI==='cli' && realpath($argv[0])===__FILE__)`) recrée le fichier `database/psg.sqlite` et imprime le rapport.

- [ ] **Step 4 : Lancer les tests puis la migration réelle**

Run: `php tests/run.php && php database/migrate.php`
Expected: tests PASS, puis rapport `matches: 34, players: 24, L1: 24V 4N 6D (74-29) — OK`

- [ ] **Step 5 : Commit**

```bash
git add database/migrate.php tests/unit/SeedIntegrityTest.php
git commit -m "feat: migration et peuplement calibrés, avec garde-fous d'intégrité testés"
```

---

## Phase 5 — Services métier

### Task 5.1 : KpiService

**Files:**
- Create: `php/services/KpiService.php`
- Test: `tests/unit/KpiServiceTest.php`

**Interfaces:**
- Consumes: `StatisticRepository`, `MatchRepository`, `CompetitionRepository`.
- Produces: `KpiService::__construct(StatisticRepository $stats, MatchRepository $matches, CompetitionRepository $comps, int $psgTeamId)`, `->dashboard(): array` retournant `['top_scorer'=>['name'=>..,'goals'=>..], 'top_assister'=>..., 'wins'=>int, 'draws'=>int, 'losses'=>int, 'clean_sheets'=>int, 'avg_possession'=>float, 'goals_per_match'=>float]`. Chaque valeur calculée depuis les repos, aucune constante en dur.

- [ ] **Step 1 : Test avec repos bouchonnés (doublures)**

```php
<?php
declare(strict_types=1);
// tests/unit/KpiServiceTest.php
final class KpiServiceTest extends TestCase
{
    public function testDashboardAgregeLesKpi(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $limit, ?int $competitionId): array {
                return [['player' => Player::fromRow(['id'=>29,'season_id'=>1,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]), 'goals'=>11, 'assists'=>4, 'minutes'=>2400]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $psgTeamId): array { return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34]; }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {};
        $kpi = new KpiService($stats, $matches, $comps, 1);
        $d = $kpi->dashboard();
        $this->assertSame('Bradley Barcola', $d['top_scorer']['name']);
        $this->assertSame(24, $d['wins']);
        $this->assertSame(2.18, round($d['goals_per_match'], 2)); // 74/34
    }
}
```

(Le test suppose une méthode `MatchRepository::seasonRecord(int $psgTeamId): array` : l'ajouter à `MatchRepository` en Task 3.3 rétro-activement, ou dans ce Step si absente. Elle calcule V/N/D, buts, clean sheets et possession moyenne en une requête `CASE`/`AVG`.)

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `KpiService` introuvable

- [ ] **Step 3 : Implémenter `KpiService`**

```php
<?php
declare(strict_types=1);
// php/services/KpiService.php
final class KpiService
{
    public function __construct(
        private StatisticRepository $stats,
        private MatchRepository $matches,
        private CompetitionRepository $comps,
        private int $psgTeamId,
    ) {}

    public function dashboard(): array
    {
        $scorers = $this->stats->topScorers(1, null);
        $record = $this->matches->seasonRecord($this->psgTeamId);
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
        $rows = $this->stats->topScorers(50, null);
        usort($rows, static fn($a, $b) => $b['assists'] <=> $a['assists']);
        $best = $rows[0] ?? null;
        return $best ? ['name' => $best['player']->fullName(), 'assists' => $best['assists']] : null;
    }
}
```

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5 : Commit**

```bash
git add php/services/KpiService.php tests/unit/KpiServiceTest.php php/repositories/MatchRepository.php
git commit -m "feat: KpiService (KPI dashboard calculés depuis les repositories)"
```

### Task 5.2 : ComparisonService, RankingService, HeatmapService

**Files:**
- Create: `php/services/{ComparisonService,RankingService,HeatmapService}.php`
- Test: `tests/unit/ComparisonServiceTest.php`

**Interfaces:**
- Produces:
  - `ComparisonService::compare(int $idA, int $idB): array` → `['a'=>['player'=>Player,'totals'=>[...]], 'b'=>..., 'axes'=>['goals','assists','minutes','shots','duels_won','rating']]` avec valeurs normalisées 0-1 pour le radar (`normalized` par axe = valeur/max des deux).
  - `RankingService::byMetric(string $metric, int $limit): array` (metric validé liste blanche).
  - `HeatmapService::goalsByPlayerAndMonth(): array` → matrice `['months'=>['2025-08',...], 'rows'=>[['player'=>..,'cells'=>[mois=>buts]]]]`.

- [ ] **Step 1 : Test de normalisation du radar**

```php
<?php
declare(strict_types=1);
// tests/unit/ComparisonServiceTest.php
final class ComparisonServiceTest extends TestCase
{
    public function testNormalisationRadar(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $id): array {
                return $id === 1
                    ? ['goals'=>20,'assists'=>10,'minutes'=>3000,'shots'=>80,'duels_won'=>120,'rating'=>7.6]
                    : ['goals'=>10,'assists'=>5,'minutes'=>2000,'shots'=>40,'duels_won'=>60,'rating'=>7.0];
            }
        };
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player {
                return Player::fromRow(['id'=>$id,'season_id'=>1,'shirt_number'=>$id,'first_name'=>'J','last_name'=>"n{$id}",'position'=>'FW','detailed_position'=>'CF','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>180,'is_captain'=>0]);
            }
        };
        $svc = new ComparisonService($stats, $players);
        $res = $svc->compare(1, 2);
        $this->assertSame(1.0, $res['a']['normalized']['goals'], 'le max vaut 1');
        $this->assertSame(0.5, $res['b']['normalized']['goals'], '10/20 = 0.5');
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `ComparisonService` introuvable

- [ ] **Step 3 : Implémenter les trois services** (`ComparisonService` d'abord pour faire passer le test : récupère les totaux des deux joueurs, calcule pour chaque axe `normalized = valeur / max(valeurA, valeurB)` en gérant le max nul). `RankingService` et `HeatmapService` suivent leurs interfaces.

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5 : Commit**

```bash
git add php/services/ComparisonService.php php/services/RankingService.php php/services/HeatmapService.php tests/unit/ComparisonServiceTest.php
git commit -m "feat: services comparaison (radar normalisé), classement et heatmap"
```

### Task 5.3 : Exporteurs CSV et PDF

**Files:**
- Create: `php/services/CsvExporter.php`, `php/services/PdfExporter.php`
- Test: `tests/unit/CsvExporterTest.php`

**Interfaces:**
- Produces:
  - `CsvExporter::fromRows(array $headers, array $rows): string` (séparateur `;`, BOM UTF-8 pour Excel, échappement des guillemets).
  - `PdfExporter::simpleReport(string $title, array $sections): string` génère un PDF minimal valide (structure PDF 1.4 écrite à la main, une page, texte Helvetica) sans dépendance.

- [ ] **Step 1 : Test CSV**

```php
<?php
declare(strict_types=1);
// tests/unit/CsvExporterTest.php
final class CsvExporterTest extends TestCase
{
    public function testGenereEnTeteEtLignes(): void
    {
        $csv = CsvExporter::fromRows(['Joueur', 'Buts'], [['Barcola', 11], ['Dembélé', 10]]);
        $this->assertTrue(str_contains($csv, 'Joueur;Buts'), 'en-tête');
        $this->assertTrue(str_contains($csv, 'Barcola;11'), 'ligne 1');
    }

    public function testEchappeGuillemets(): void
    {
        $csv = CsvExporter::fromRows(['A'], [['dit "oui"']]);
        $this->assertTrue(str_contains($csv, '"dit ""oui"""'), 'guillemets doublés');
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `CsvExporter` introuvable

- [ ] **Step 3 : Implémenter `CsvExporter`**

```php
<?php
declare(strict_types=1);
// php/services/CsvExporter.php
final class CsvExporter
{
    public static function fromRows(array $headers, array $rows): string
    {
        $out = "\xEF\xBB\xBF"; // BOM UTF-8
        $out .= self::line($headers);
        foreach ($rows as $row) {
            $out .= self::line($row);
        }
        return $out;
    }

    private static function line(array $cells): string
    {
        $escaped = array_map(static function ($cell): string {
            $value = (string) $cell;
            if (str_contains($value, '"') || str_contains($value, ';') || str_contains($value, "\n")) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $cells);
        return implode(';', $escaped) . "\r\n";
    }
}
```

- [ ] **Step 4 : Implémenter `PdfExporter::simpleReport`** (générateur PDF 1.4 minimal : objets catalog/pages/page/font/contents, flux de texte `BT /F1 14 Tf ... Td (texte) Tj ET`, table xref, trailer. Échapper `(`, `)`, `\` dans le texte). Le test associé se limite à vérifier que la sortie commence par `%PDF-1.` et contient `%%EOF`.

- [ ] **Step 5 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 6 : Commit**

```bash
git add php/services/CsvExporter.php php/services/PdfExporter.php tests/unit/CsvExporterTest.php
git commit -m "feat: exporteurs CSV (BOM, échappement) et PDF sans dépendance"
```

---

## Phase 6 — API REST

### Task 6.1 : Contrôleurs API joueurs et stats

**Files:**
- Create: `php/controllers/Api/{PlayerApiController,StatsApiController}.php`
- Modify: `php/config/routes.php`
- Test: `tests/unit/PlayerApiControllerTest.php`

**Interfaces:**
- Consumes: repositories, services, `Validator`, `Response::apiEnvelope`.
- Produces: méthodes `index(Request $r, array $params)`, `show(...)`, `compare(...)`, `timeline(...)`. Chaque méthode construit la réponse via `Response::apiEnvelope`. Le contrôleur valide `page`, `per_page` (max 50), `sort` (liste blanche), `order`, `position` avant d'appeler le repo. Pour tester sans HTTP, extraire la logique dans une méthode pure `buildIndex(array $query): array` retournant l'enveloppe, appelée par `index()` qui l'envoie via `Response::json`.

- [ ] **Step 1 : Test de l'enveloppe et de la validation**

```php
<?php
declare(strict_types=1);
// tests/unit/PlayerApiControllerTest.php
final class PlayerApiControllerTest extends TestCase
{
    private function controller(): PlayerApiController
    {
        $repo = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function paginate(int $page,int $perPage,string $sort,string $order,?string $pos): array {
                return ['items' => [], 'total' => 24];
            }
        };
        return new PlayerApiController($repo);
    }

    public function testEnveloppeContientMeta(): void
    {
        $env = $this->controller()->buildIndex(['page' => '1', 'per_page' => '20']);
        $this->assertSame(24, $env['meta']['total']);
        $this->assertSame(1, $env['meta']['page']);
        $this->assertSame(2, $env['meta']['total_pages']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $env = $this->controller()->buildIndex(['per_page' => '9999']);
        $this->assertSame(50, $env['meta']['per_page']);
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `PlayerApiController` introuvable

- [ ] **Step 3 : Implémenter `PlayerApiController`**

```php
<?php
declare(strict_types=1);
// php/controllers/Api/PlayerApiController.php
final class PlayerApiController extends Controller
{
    public function __construct(private ?PlayerRepository $players = null)
    {
        $this->players ??= new PlayerRepository();
    }

    public function index(Request $r, array $params): void
    {
        $this->json($this->buildIndex($_GET));
    }

    public function buildIndex(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? 20, 1, 50, 20);
        $sort = Validator::inList($query['sort'] ?? 'last_name', ['last_name', 'shirt_number', 'position', 'nationality'], 'last_name');
        $order = Validator::inList($query['order'] ?? 'ASC', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? Validator::inList($query['position'], ['GK', 'DF', 'MF', 'FW'], '') ?: null
            : null;

        $res = $this->players->paginate($page, $perPage, $sort, $order, $position);
        $items = array_map(static fn(Player $p) => [
            'id' => $p->id, 'number' => $p->shirtNumber, 'name' => $p->fullName(),
            'position' => $p->position, 'nationality' => $p->nationality,
        ], $res['items']);

        return Response::apiEnvelope($items, [
            'page' => $page, 'per_page' => $perPage, 'total' => $res['total'],
            'total_pages' => (int) ceil($res['total'] / $perPage),
        ]);
    }

    // show(), timeline(), compare() : mêmes principes, validation puis enveloppe
}
```

Implémenter aussi `show`, `timeline`, `compare` dans ce Step (chacune valide ses paramètres puis délègue au repo/service et renvoie une enveloppe ; `show` renvoie 404 via `Response::json(['error'=>...],404)` si le joueur est absent).

- [ ] **Step 4 : Implémenter `StatsApiController`** (`kpis`, `distribution`, `heatmap`) qui délègue à `KpiService`/`HeatmapService` et enveloppe. Ajouter les routes dans `routes.php`.

- [ ] **Step 5 : Lancer les tests puis un appel réel**

Run: `php tests/run.php && php -S 127.0.0.1:8000 index.php & sleep 1 && curl -s "127.0.0.1:8000/api/players?per_page=3" | head -c 200 ; kill %1`
Expected: tests PASS ; JSON avec clés `data`, `meta`, `source`

- [ ] **Step 6 : Commit**

```bash
git add php/controllers/Api/ php/config/routes.php tests/unit/PlayerApiControllerTest.php
git commit -m "feat: API joueurs et stats (enveloppe normalisée, validation liste blanche)"
```

### Task 6.2 : API matches, compétitions, exports

**Files:**
- Create: `php/controllers/Api/{MatchApiController,CompetitionApiController,ExportApiController}.php`
- Modify: `php/config/routes.php`
- Test: `tests/unit/ExportApiControllerTest.php`

**Interfaces:**
- Produces: `MatchApiController::{index,show}`, `CompetitionApiController::index`, `ExportApiController::{playersCsv,reportPdf}`. Les exports posent les en-têtes `Content-Type` et `Content-Disposition: attachment`. Méthode testable `ExportApiController::buildCsv(): string`.

- [ ] **Step 1 : Test de l'export CSV joueurs**

```php
<?php
declare(strict_types=1);
// tests/unit/ExportApiControllerTest.php
final class ExportApiControllerTest extends TestCase
{
    public function testCsvContientEnTete(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $limit, ?int $c): array {
                return [['player'=>Player::fromRow(['id'=>29,'season_id'=>1,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]),'goals'=>11,'assists'=>4,'minutes'=>2400]];
            }
        };
        $csv = (new ExportApiController($stats))->buildCsv();
        $this->assertTrue(str_contains($csv, 'Joueur;Buts;Passes;Minutes'), 'en-tête CSV');
        $this->assertTrue(str_contains($csv, 'Bradley Barcola;11;4;2400'), 'ligne joueur');
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run: `php tests/run.php`
Expected: FAIL — `ExportApiController` introuvable

- [ ] **Step 3 : Implémenter les trois contrôleurs** (`ExportApiController::buildCsv` assemble via `CsvExporter::fromRows`, `playersCsv()` pose les en-têtes et `echo`). Ajouter les routes.

- [ ] **Step 4 : Lancer**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5 : Commit**

```bash
git add php/controllers/Api/ php/config/routes.php tests/unit/ExportApiControllerTest.php
git commit -m "feat: API matches, compétitions et exports CSV/PDF"
```

---

## Phase 7 — Design system et layout

### Task 7.1 : Jetons, base, layout, police

**Files:**
- Create: `assets/css/tokens.css`, `assets/css/base.css`, `assets/css/layout.css`, `assets/fonts/inter.css`, `assets/fonts/*.woff2`, `php/views/layouts/main.php`, `php/views/partials/{header,footer,nav}.php`

**Interfaces:**
- Produces: variables CSS (`--bg`, `--primary`, `--accent`, `--border`, échelle d'espacement `--space-1..8`, rayons, ombres, typographie). Thème sombre via `[data-theme="dark"]` et `@media (prefers-color-scheme: dark)`. `main.php` inclut header/nav/footer et injecte `$content`.

- [ ] **Step 1 : `tokens.css`**

```css
/* assets/css/tokens.css */
:root {
  --bg: #FAFBFC;
  --surface: #FFFFFF;
  --primary: #0F172A;
  --muted: #64748B;
  --accent: #2563EB;
  --border: #E2E8F0;
  --success: #16A34A;
  --danger: #DC2626;
  --space-1: 4px;  --space-2: 8px;  --space-3: 12px; --space-4: 16px;
  --space-6: 24px; --space-8: 32px; --space-12: 48px; --space-16: 64px;
  --radius: 10px;  --radius-lg: 16px;
  --shadow-sm: 0 1px 2px rgba(15,23,42,.04);
  --shadow: 0 4px 16px rgba(15,23,42,.06);
  --font: 'Inter', system-ui, -apple-system, sans-serif;
  --text-sm: 13px; --text-base: 15px; --text-lg: 18px; --text-xl: 24px; --text-2xl: 34px;
}
:root[data-theme="dark"] {
  --bg: #0B1120; --surface: #131C31; --primary: #F1F5F9;
  --muted: #94A3B8; --border: #1E293B;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --bg: #0B1120; --surface: #131C31; --primary: #F1F5F9;
    --muted: #94A3B8; --border: #1E293B;
  }
}
```

- [ ] **Step 2 : `base.css` + `layout.css`** (reset léger, `body{background:var(--bg);color:var(--primary);font-family:var(--font)}`, conteneur `.container{max-width:1200px;margin:0 auto;padding:0 var(--space-6)}`, grille utilitaire, transitions respectant `prefers-reduced-motion`). `inter.css` déclare `@font-face` en `woff2` local.

- [ ] **Step 3 : Récupérer la police Inter** (woff2, licence OFL) dans `assets/fonts/` et écrire la licence OFL. Aucune requête réseau au runtime.

- [ ] **Step 4 : `main.php`, header, nav, footer**

```php
<?php /* php/views/layouts/main.php */ ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'PSG Analytics') ?></title>
  <link rel="stylesheet" href="/assets/fonts/inter.css">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/charts.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
</head>
<body>
  <?php require BASE_PATH . '/php/views/partials/header.php'; ?>
  <main class="container"><?= $content ?></main>
  <?php require BASE_PATH . '/php/views/partials/footer.php'; ?>
  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 5 : Vérifier au navigateur** (via preview_start `{name}` après config launch.json, ou `php -S`). Contrôler : page chargée, aucune erreur console, bascule de thème plus tard. Screenshot.

- [ ] **Step 6 : Commit**

```bash
git add assets/css/ assets/fonts/ php/views/layouts/ php/views/partials/
git commit -m "feat: design system (jetons, thèmes clair/sombre, layout, Inter local)"
```

### Task 7.2 : Composants et bascule de thème

**Files:**
- Create: `assets/css/components.css`, `assets/js/modules/theme.js`, `php/views/partials/{kpi_card,source_badge,player_card,match_row}.php`

**Interfaces:**
- Produces: styles `.card`, `.kpi`, `.badge`, `.badge--estimated`, `.btn`, `.table`, `.tag`. `theme.js` exporte `initTheme()` : lit `localStorage.theme`, applique `data-theme`, câble un bouton `#theme-toggle`, persiste.

- [ ] **Step 1 : `components.css`** (cartes, KPI, badges de source discrets, boutons, tableaux triables, tags de poste colorés sobrement).
- [ ] **Step 2 : `theme.js`**

```js
// assets/js/modules/theme.js
export function initTheme() {
  const saved = localStorage.getItem('theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
  const btn = document.querySelector('#theme-toggle');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', current);
    localStorage.setItem('theme', current);
  });
}
```

- [ ] **Step 3 : Partials** (`kpi_card.php`, `source_badge.php` affichant un point + libellé « estimé » quand `confidence==='estimated'`, `player_card.php`, `match_row.php`).
- [ ] **Step 4 : Vérifier** la bascule clair/sombre au navigateur, persistance après reload. Screenshot des deux thèmes.
- [ ] **Step 5 : Commit**

```bash
git add assets/css/components.css assets/js/modules/theme.js php/views/partials/
git commit -m "feat: composants UI et bascule de thème persistée"
```

---

## Phase 8 — Moteur de graphiques SVG

### Task 8.1 : Socle scale + axis

**Files:**
- Create: `assets/js/charts/scale.js`, `assets/js/charts/axis.js`, `assets/css/charts.css`

**Interfaces:**
- Produces:
  - `scale.js` : `linearScale(domainMin, domainMax, rangeMin, rangeMax) => (v)=>number`, `bandScale(items, rangeMin, rangeMax, padding) => {step, bandwidth, pos(i)}`, `niceMax(value)=>number`.
  - `axis.js` : `svgEl(name, attrs, children)` fabrique un nœud SVG, `renderYAxis(...)`, `renderXLabels(...)`.

- [ ] **Step 1 : Page de test navigateur** `tests/browser/charts.html` important `scale.js`, appelant `console.assert(linearScale(0,10,0,100)(5)===50)`. (Les modules JS se vérifient au navigateur, pas dans le runner PHP.)

- [ ] **Step 2 : Implémenter `scale.js`**

```js
// assets/js/charts/scale.js
export function linearScale(dMin, dMax, rMin, rMax) {
  const span = (dMax - dMin) || 1;
  return (v) => rMin + ((v - dMin) / span) * (rMax - rMin);
}

export function bandScale(items, rMin, rMax, padding = 0.2) {
  const n = items.length || 1;
  const step = (rMax - rMin) / n;
  const bandwidth = step * (1 - padding);
  return { step, bandwidth, pos: (i) => rMin + i * step + (step - bandwidth) / 2 };
}

export function niceMax(value) {
  if (value <= 5) return 5;
  const pow = Math.pow(10, Math.floor(Math.log10(value)));
  return Math.ceil(value / pow) * pow;
}
```

- [ ] **Step 3 : Implémenter `axis.js`** (`svgEl` via `document.createElementNS`, rendu axe Y avec graduations sur `niceMax`, labels X). `charts.css` stylise traits d'axe, grille légère, libellés `var(--muted)`.
- [ ] **Step 4 : Vérifier** `tests/browser/charts.html` au navigateur : `console.assert` sans erreur.
- [ ] **Step 5 : Commit**

```bash
git add assets/js/charts/scale.js assets/js/charts/axis.js assets/css/charts.css tests/browser/charts.html
git commit -m "feat: socle du moteur de charts (échelles, axes, helpers SVG)"
```

### Task 8.2 : bar, line, donut, radar, heatmap

**Files:**
- Create: `assets/js/charts/{bar,line,donut,radar,heatmap}.js`

**Interfaces:**
- Consumes: `scale.js`, `axis.js`.
- Produces: chaque module exporte `render(container, data, options)` qui vide le conteneur et injecte un `<svg viewBox>` responsive, avec `role="img"` + `aria-label`, transition d'apparition CSS, et infobulle au survol (`<title>` SVG au minimum). Signatures :
  - `bar.render(el, [{label, value}], {color, ariaLabel})`
  - `line.render(el, [{x, y}], {ariaLabel})`
  - `donut.render(el, [{label, value, color}], {ariaLabel})`
  - `radar.render(el, {axes:[...], series:[{name, values:[0..1]}]}, {ariaLabel})`
  - `heatmap.render(el, {cols, rows:[{label, cells:[...]}]}, {ariaLabel})`

- [ ] **Step 1 : Implémenter `bar.js`** (barres via `bandScale`, axe via `axis.js`, `<title>` par barre pour l'infobulle, `aria-label`).
- [ ] **Step 2 : Implémenter `line.js`** (polyligne, points, aire optionnelle).
- [ ] **Step 3 : Implémenter `donut.js`** (secteurs par `path` arc, légende).
- [ ] **Step 4 : Implémenter `radar.js`** (polygone sur axes normalisés 0-1, deux séries superposées pour la comparaison).
- [ ] **Step 5 : Implémenter `heatmap.js`** (grille de cellules, intensité couleur = valeur/max).
- [ ] **Step 6 : Vérifier** chaque chart dans une page de démonstration `tests/browser/charts-demo.html` au navigateur, thèmes clair et sombre, redimensionnement. Screenshots.
- [ ] **Step 7 : Commit**

```bash
git add assets/js/charts/
git commit -m "feat: modules de charts SVG (bar, line, donut, radar, heatmap) accessibles"
```

---

## Phase 9 — Pages et interactions

### Task 9.1 : Accueil

**Files:**
- Create: `php/views/pages/home.php`, `php/controllers/HomeController.php`
- Modify: `php/config/routes.php`, `assets/css/pages.css`

**Interfaces:**
- Consumes: `KpiService`, `StatisticRepository`, `MatchRepository`.
- Produces: `HomeController::index` assemble pitch, bandeau trophées, KPI clés, 5 derniers matchs, top buteurs, stack, lien méthodologie, puis `render('home', $data)`.

- [ ] **Step 1 : `HomeController`** (instancie repos/services, calcule les données, rend la vue).
- [ ] **Step 2 : `home.php`** (récit de saison, sections sémantiques, échappement via `View::e`, badges de source).
- [ ] **Step 3 : Route `GET /`** déjà présente ; vérifier le rendu au navigateur (contenu, pas d'erreur console/réseau). Screenshot clair + sombre.
- [ ] **Step 4 : Commit**

```bash
git add php/controllers/HomeController.php php/views/pages/home.php assets/css/pages.css php/config/routes.php
git commit -m "feat: page d'accueil (récit de saison, KPI, derniers matchs)"
```

### Task 9.2 : Dashboard

**Files:**
- Create: `php/views/pages/dashboard.php`, `php/controllers/DashboardController.php`
- Modify: `routes.php`, `assets/js/app.js`

**Interfaces:**
- Produces: `DashboardController::index` rend la coquille ; le JS charge `/api/stats/kpis`, `/api/stats/distribution`, `/api/stats/heatmap` et rend les charts. `app.js` importe `initTheme` et un routeur de vue qui, selon `document.body.dataset.page`, initialise le bon module.

- [ ] **Step 1 : `app.js`** (bootstrap : `initTheme()`, switch sur `data-page`).
- [ ] **Step 2 : `modules/api.js`** (`get(path)` → `fetch` + JSON, gestion d'erreur, renvoie `data/meta`).
- [ ] **Step 3 : `DashboardController` + `dashboard.php`** (cartes KPI côté serveur pour le premier rendu, conteneurs de charts hydratés côté client).
- [ ] **Step 4 : Câbler les charts** (courbe de forme, donut par compétition, bar par période, heatmap) via les modules charts.
- [ ] **Step 5 : Vérifier** au navigateur : KPI corrects, charts rendus, zéro erreur console, requêtes API 200. Screenshots.
- [ ] **Step 6 : Commit**

```bash
git add php/controllers/DashboardController.php php/views/pages/dashboard.php assets/js/app.js assets/js/modules/api.js php/config/routes.php
git commit -m "feat: dashboard (KPI serveur, charts hydratés via API)"
```

### Task 9.3 : Joueurs (recherche, tri, filtres, pagination, comparateur)

**Files:**
- Create: `php/views/pages/players.php`, `php/controllers/PlayerController.php`, `assets/js/modules/{table,search,compare}.js`
- Modify: `routes.php`

**Interfaces:**
- Produces: `PlayerController::index` rend la coquille + première page SSR. `table.js` gère tri au clic (colonnes triables) et pagination via l'API. `search.js` filtre instantanément côté client. `compare.js` sélectionne deux joueurs, appelle `/api/players/compare` et rend un radar.

- [ ] **Step 1 : `PlayerController` + `players.php`** (tableau/grille commutable, contrôles de filtre poste/nationalité).
- [ ] **Step 2 : `search.js`** (filtre live sur le DOM chargé, accessible, `aria-live`).
- [ ] **Step 3 : `table.js`** (tri dynamique, pagination, met à jour via `api.get`).
- [ ] **Step 4 : `compare.js`** (sélection 2 joueurs, radar via `charts/radar.js`).
- [ ] **Step 5 : Vérifier** au navigateur : recherche, tri, pagination, comparaison de deux joueurs. Screenshots.
- [ ] **Step 6 : Commit**

```bash
git add php/controllers/PlayerController.php php/views/pages/players.php assets/js/modules/ php/config/routes.php
git commit -m "feat: page joueurs (recherche live, tri, filtres, pagination, comparateur radar)"
```

### Task 9.4 : Fiche joueur et Matchs

**Files:**
- Create: `php/views/pages/{player_detail,matches}.php`, `php/controllers/MatchController.php`, `php/views/pages/methodology.php`, `php/controllers/MethodologyController.php`
- Modify: `PlayerController.php` (méthode `show`), `routes.php`

**Interfaces:**
- Produces: `PlayerController::show(id)` (identité, saison en chiffres, radar de profil, timeline de contribution, historique). `MatchController::{index,show}` (liste filtrable, fiche match avec événements chronologiques). `MethodologyController::index` (sources, taux de couverture par table, règles de calibration, limites).

- [ ] **Step 1 : Fiche joueur** (`show` + `player_detail.php`, radar de profil, courbe de contribution via `line.js`).
- [ ] **Step 2 : Matchs** (`MatchController` + `matches.php`, filtres compétition/résultat, fiche match avec timeline d'événements).
- [ ] **Step 3 : Méthodologie** (`MethodologyController` + `methodology.php`, calcul du taux `verified` par table depuis `data_sources`).
- [ ] **Step 4 : Export** (boutons CSV/PDF câblés sur `modules/export.js` appelant les routes d'export).
- [ ] **Step 5 : Vérifier** les trois pages au navigateur, thèmes, responsive mobile. Screenshots.
- [ ] **Step 6 : Commit**

```bash
git add php/controllers/ php/views/pages/ assets/js/modules/export.js php/config/routes.php
git commit -m "feat: fiche joueur, page matchs et page méthodologie"
```

---

## Phase 10 — Finition, vérification, documentation

### Task 10.1 : Responsive, accessibilité, Lighthouse

**Files:**
- Modify: `assets/css/*.css`, vues selon besoins

- [ ] **Step 1 : Points de rupture** 640/1024/1280, navigation mobile, tableaux qui défilent horizontalement dans un conteneur `overflow-x:auto`.
- [ ] **Step 2 : Accessibilité** : focus visibles, `aria-*` sur composants interactifs, contrastes AA, `prefers-reduced-motion`, `alt`/`aria-label` sur charts.
- [ ] **Step 3 : Vérifier** chaque page en mobile/tablette/bureau, clair/sombre, au navigateur. Screenshots responsive.
- [ ] **Step 4 : Lighthouse** sur Accueil, Dashboard, Joueurs. Corriger jusqu'à > 95 Perf/A11y/Best practices.
- [ ] **Step 5 : Commit**

```bash
git add assets/ php/views/
git commit -m "feat: responsive, accessibilité AA et optimisations Lighthouse > 95"
```

### Task 10.2 : Documentation base (MCD, MLD, dictionnaire)

**Files:**
- Create: `database/docs/{mcd.md,mld.md,data-dictionary.md}`

- [ ] **Step 1 : MCD** en Mermaid `erDiagram` (entités et relations).
- [ ] **Step 2 : MLD** (tables, clés, types) en Mermaid + notes de normalisation 3NF.
- [ ] **Step 3 : Dictionnaire de données** (chaque colonne : type, domaine, provenance).
- [ ] **Step 4 : Vérifier** le rendu Mermaid (aperçu Markdown).
- [ ] **Step 5 : Commit**

```bash
git add database/docs/
git commit -m "docs: MCD, MLD et dictionnaire de données"
```

### Task 10.3 : README et captures

**Files:**
- Create: `README.md`, `LICENSE`, `assets/images/screenshots/*`
- Modify: `.gitignore`

**Interfaces:**
- Produces: README complet (pitch, captures, pourquoi, fonctionnalités, architecture, modèle de données + diagramme, stack et justification, installation en une commande, méthodologie des données, structure, choix techniques, tests, améliorations futures, licence).

- [ ] **Step 1 : Captures** réelles des pages (clair + sombre) rangées dans `assets/images/screenshots/`.
- [ ] **Step 2 : Rédiger `README.md`** selon la structure de la spec section 9, avec le bloc d'installation :

```bash
git clone <repo> && cd psg-analytics
php database/migrate.php        # crée et peuple database/psg.sqlite
php -S 127.0.0.1:8000 index.php # http://127.0.0.1:8000
```

- [ ] **Step 3 : `LICENSE`** (MIT) et vérifier `.gitignore` (base SQLite générée, config locale).
- [ ] **Step 4 : Relire** le README rendu, liens et images valides.
- [ ] **Step 5 : Commit**

```bash
git add README.md LICENSE assets/images/ .gitignore
git commit -m "docs: README complet avec captures, architecture et installation"
```

### Task 10.4 : Vérification finale et Definition of Done

**Files:** aucun (contrôle)

- [ ] **Step 1 : Suite complète** `php tests/run.php` → OK.
- [ ] **Step 2 : Migration propre** depuis zéro (`rm -f database/psg.sqlite && php database/migrate.php`) → rapport OK, identités vérifiées.
- [ ] **Step 3 : Parcours navigateur** des 6 pages, zéro erreur console, zéro requête en échec, clair/sombre/responsive.
- [ ] **Step 4 : Checklist Definition of Done** (section Global Constraints) cochée point par point.
- [ ] **Step 5 : Grep de contrôle** des interdictions :

```bash
grep -rn "console.log\|var_dump\|TODO\|FIXME" assets/ php/ database/ || echo "aucune interdiction violée"
```

Expected: `aucune interdiction violée`
- [ ] **Step 6 : Historique Git** propre, commit final si nécessaire.

---

## Auto-revue du plan

**Couverture de la spec :**
- Socle MVC, front controller, portabilité MySQL/SQLite → Phases 0, 2. ✅
- 8 tables normalisées, relations, index, provenance → Phase 1, Task 4.x. ✅
- Modèle hybride tracé + calibration + garde-fous testés → Tasks 4.1-4.3 (identités testées). ✅
- API REST enveloppée, pagination, tri liste blanche → Phase 6. ✅
- 6 pages + KPI + comparateur + méthodologie → Phase 9. ✅
- Moteur de charts SVG maison (bar/line/radar/donut/heatmap) → Phase 8. ✅
- Design tokens, thèmes clair/sombre, responsive, accessibilité → Phases 7, 10. ✅
- Exports CSV/PDF, recherche instantanée, tri, pagination → Tasks 5.3, 9.2, 9.3. ✅
- Tests sur services/repos/seeds → tout au long. ✅
- README exceptionnel, MCD/MLD, dictionnaire → Tasks 10.2, 10.3. ✅
- Definition of Done mesurable → Task 10.4. ✅

**Cohérence des types :** `MatchGame` (jamais `Match`, mot réservé PHP) partout. `Response::apiEnvelope` signature unique. `PlayerRepository::paginate` mêmes paramètres dans repo, contrôleur et tests. `StatisticRepository::topScorers` retourne `['player'=>Player,'goals'=>int,'assists'=>int,'minutes'=>int]` partout. `MatchRepository::seasonRecord` introduite en Task 3.3 et consommée en Task 5.1.

**Placeholders :** aucun `TODO` de plan. Les tâches front (vues, CSS) portent des specs concrètes + code représentatif ; les parties répétitives (autres modèles, autres repos) référencent un patron entièrement montré sur un exemplaire, conformément à la nature du travail.
