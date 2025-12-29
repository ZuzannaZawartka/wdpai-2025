CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    enabled BOOLEAN DEFAULT TRUE
);

INSERT INTO users (firstname, lastname, email, password, bio, enabled)
VALUES (
    'Jan',
    'Kowalski',
    'jan.kowalski@example.com',
    '$2b$10$ZbzQrqD1vDhLJpYe/vzSbeDJHTUnVPCpwlXclkiFa8dO5gOAfg8tq',
    'Lubi programować w JS i PL/SQL.',
    TRUE
);
 
-- Global login attempts tracking (per email + IP)
CREATE TABLE IF NOT EXISTS login_attempts (
    email VARCHAR(150) NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    count INTEGER NOT NULL DEFAULT 0,
    lock_until BIGINT NOT NULL DEFAULT 0,
    last_attempt BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (email, ip_hash)
);

-- Catalog tables: sports and levels
CREATE TABLE IF NOT EXISTS sports (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS levels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO sports (name) VALUES
    ('Soccer'), ('Basketball'), ('Tennis'), ('Running'), ('Cycling')
ON CONFLICT DO NOTHING;

INSERT INTO levels (name) VALUES
    ('Beginner'), ('Intermediate'), ('Advanced')
ON CONFLICT DO NOTHING;

-- Events and participation
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    sport_id INTEGER NOT NULL REFERENCES sports(id),
    location_text VARCHAR(255),
    latitude DECIMAL(9,6),
    longitude DECIMAL(9,6),
    start_time TIMESTAMPTZ NOT NULL,
    level_id INTEGER REFERENCES levels(id),
    image_url TEXT,
    max_players INTEGER NOT NULL DEFAULT 12,
    min_needed INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS event_participants (
    event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed', -- confirmed|pending|declined
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (event_id, user_id)
);

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_events_owner ON events(owner_id);
CREATE INDEX IF NOT EXISTS idx_events_start_time ON events(start_time);
CREATE INDEX IF NOT EXISTS idx_events_sport ON events(sport_id);
CREATE INDEX IF NOT EXISTS idx_events_level ON events(level_id);
CREATE INDEX IF NOT EXISTS idx_event_participants_user ON event_participants(user_id);
