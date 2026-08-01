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
