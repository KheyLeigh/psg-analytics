-- database/schema.mysql.sql
-- Equivalent MySQL du schema.sqlite.sql : memes tables, memes colonnes, meme ordre.
-- Seuls les types SQL et les contraintes de domaine (ENUM au lieu de CHECK) different.

SET NAMES utf8mb4;

CREATE TABLE seasons (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    short_name  VARCHAR(20) NOT NULL,
    country     VARCHAR(100) NOT NULL,
    is_psg      TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE competitions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('league','cup','super_cup','intercontinental') NOT NULL,
    scope       ENUM('domestic','european','world') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE data_sources (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    label        VARCHAR(100) NOT NULL,
    url          TEXT,
    collected_at DATETIME,
    confidence   ENUM('verified','estimated') NOT NULL,
    note         TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE players (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    season_id         INT NOT NULL,
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
    FOREIGN KEY (season_id) REFERENCES seasons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE matches (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    season_id            INT NOT NULL,
    competition_id       INT NOT NULL,
    round_label          VARCHAR(100),
    played_at            DATETIME NOT NULL,
    home_team_id         INT NOT NULL,
    away_team_id         INT NOT NULL,
    home_goals           INT NOT NULL,
    away_goals           INT NOT NULL,
    went_to_extra        TINYINT(1) NOT NULL DEFAULT 0,
    penalty_shootout     TINYINT(1) NOT NULL DEFAULT 0,
    penalty_score        VARCHAR(20),
    venue                ENUM('home','away','neutral') NOT NULL DEFAULT 'home',
    attendance            INT,
    psg_possession       DECIMAL(4,1),
    psg_shots            INT,
    psg_shots_on_target  INT,
    source_id            INT NOT NULL,
    FOREIGN KEY (season_id) REFERENCES seasons(id),
    FOREIGN KEY (competition_id) REFERENCES competitions(id),
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id),
    FOREIGN KEY (source_id) REFERENCES data_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE player_match_stats (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    player_id        INT NOT NULL,
    match_id         INT NOT NULL,
    is_starter       TINYINT(1) NOT NULL DEFAULT 0,
    minutes          INT NOT NULL DEFAULT 0,
    goals            INT NOT NULL DEFAULT 0,
    assists          INT NOT NULL DEFAULT 0,
    shots            INT NOT NULL DEFAULT 0,
    shots_on_target  INT NOT NULL DEFAULT 0,
    passes           INT NOT NULL DEFAULT 0,
    pass_accuracy    DECIMAL(5,2),
    duels_won        INT NOT NULL DEFAULT 0,
    yellow_cards     INT NOT NULL DEFAULT 0,
    red_card         TINYINT(1) NOT NULL DEFAULT 0,
    saves            INT NOT NULL DEFAULT 0,
    goals_conceded   INT NOT NULL DEFAULT 0,
    rating           DECIMAL(4,1),
    xg               DECIMAL(4,2) NOT NULL DEFAULT 0,
    xag              DECIMAL(4,2) NOT NULL DEFAULT 0,
    source_id        INT NOT NULL,
    UNIQUE (player_id, match_id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (source_id) REFERENCES data_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    match_id           INT NOT NULL,
    player_id          INT,
    related_player_id  INT,
    type               ENUM('goal','assist','yellow','red','sub_in','sub_out','penalty_goal','own_goal') NOT NULL,
    minute             INT NOT NULL,
    stoppage           TINYINT(1) NOT NULL DEFAULT 0,
    description        TEXT,
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (related_player_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE player_season_stats (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    player_id    INT NOT NULL,
    season_id    INT NOT NULL,
    appearances  INT NOT NULL DEFAULT 0,
    starts       INT NOT NULL DEFAULT 0,
    goals        INT NOT NULL DEFAULT 0,
    assists      INT NOT NULL DEFAULT 0,
    yellow_cards INT NOT NULL DEFAULT 0,
    red_cards    INT NOT NULL DEFAULT 0,
    source_id    INT NOT NULL,
    UNIQUE (player_id, season_id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (season_id) REFERENCES seasons(id),
    FOREIGN KEY (source_id) REFERENCES data_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_players_season ON players(season_id);
CREATE INDEX idx_matches_date ON matches(played_at);
CREATE INDEX idx_matches_competition ON matches(competition_id);
CREATE INDEX idx_pms_player ON player_match_stats(player_id);
CREATE INDEX idx_pms_match ON player_match_stats(match_id);
CREATE INDEX idx_pms_goals ON player_match_stats(goals);
CREATE INDEX idx_pss_player ON player_season_stats(player_id);
CREATE INDEX idx_events_match ON events(match_id);
