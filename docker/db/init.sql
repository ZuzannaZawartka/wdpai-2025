CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    birth_date DATE,
    avatar_url TEXT,
    latitude DECIMAL(9,6),
    longitude DECIMAL(9,6),
    bio TEXT,
    role VARCHAR(20) DEFAULT 'basic',
    enabled BOOLEAN DEFAULT TRUE
);

INSERT INTO users (firstname, lastname, email, password, birth_date, latitude, longitude, bio, enabled)
VALUES (
    'Jan',
    'Kowalski',
    'jan.kowalski@example.com',
    '$2b$10$ZbzQrqD1vDhLJpYe/vzSbeDJHTUnVPCpwlXclkiFa8dO5gOAfg8tq',
    '1990-05-15',
    40.7580,
    -73.9855,
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

-- Catalog tables: sports and levels (must be created BEFORE referencing them)
CREATE TABLE IF NOT EXISTS sports (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    icon VARCHAR(10) DEFAULT '🏅'
);

CREATE TABLE IF NOT EXISTS levels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO sports (name, icon) VALUES
    ('Soccer', '⚽'), 
    ('Basketball', '🏀'), 
    ('Tennis', '🎾'), 
    ('Running', '🏃'), 
    ('Cycling', '🚴')
ON CONFLICT (name) DO NOTHING;

INSERT INTO levels (name) VALUES
    ('Beginner'), ('Intermediate'), ('Advanced')
ON CONFLICT DO NOTHING;

-- User favourite sports (NOW sports and levels exist)
CREATE TABLE IF NOT EXISTS user_favourite_sports (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    sport_id INTEGER NOT NULL REFERENCES sports(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, sport_id)
);

-- (1:1 relacja - każdy użytkownik ma swoje statystyki)
CREATE TABLE IF NOT EXISTS user_statistics (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    total_events_joined INTEGER DEFAULT 0,
    total_events_created INTEGER DEFAULT 0
);

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
    status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (event_id, user_id)
);

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_events_owner ON events(owner_id);
CREATE INDEX IF NOT EXISTS idx_events_start_time ON events(start_time);
CREATE INDEX IF NOT EXISTS idx_events_sport ON events(sport_id);
CREATE INDEX IF NOT EXISTS idx_events_level ON events(level_id);
CREATE INDEX IF NOT EXISTS idx_event_participants_user ON event_participants(user_id);

--Views
-- v1:Wszystkie wydarzenia z informacją o właścicielu i sporcie
CREATE OR REPLACE VIEW vw_events_full AS
SELECT 
    e.id,
    e.title,
    e.description,
    e.start_time,
    e.location_text,
    e.latitude,
    e.longitude,
    e.max_players,
    e.min_needed,
    u.id as owner_id,
    u.firstname || ' ' || u.lastname as owner_name,
    u.avatar_url as owner_avatar,
    s.name as sport_name,
    s.icon as sport_icon,
    l.name as level_name,
    (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id) as current_players,
    e.created_at,
    e.updated_at
FROM events e
JOIN users u ON e.owner_id = u.id
LEFT JOIN sports s ON e.sport_id = s.id
LEFT JOIN levels l ON e.level_id = l.id
ORDER BY e.start_time DESC;

-- v2: Statystyka użytkowników, ich ulubione sporty i wydarzenia
CREATE OR REPLACE VIEW vw_user_stats AS
SELECT 
    u.id,
    u.firstname || ' ' || u.lastname as full_name,
    u.email,
    COUNT(DISTINCT ufs.sport_id) as favorite_sports_count,
    COUNT(DISTINCT ep.event_id) as events_joined_count,
    COUNT(DISTINCT e.id) as events_created_count,
    COALESCE(MAX(e.start_time), u.created_at) as last_activity
FROM users u
LEFT JOIN user_favourite_sports ufs ON u.id = ufs.user_id
LEFT JOIN event_participants ep ON u.id = ep.user_id
LEFT JOIN events e ON u.id = e.owner_id
GROUP BY u.id, u.firstname, u.lastname, u.email;


--f1: Oblicza wiek użytkownika na podstawie birth_date
CREATE OR REPLACE FUNCTION calculate_user_age(birth_date DATE)
RETURNS INTEGER AS $$
BEGIN
    IF birth_date IS NULL THEN
        RETURN NULL;
    END IF;
    RETURN EXTRACT(YEAR FROM AGE(CURRENT_DATE, birth_date))::INTEGER;
END;
$$ LANGUAGE plpgsql;


-- trigger: Automatyczne aktualizowanie pola updated_at w tabeli events
CREATE OR REPLACE FUNCTION update_events_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tr_update_events_timestamp ON events;
CREATE TRIGGER tr_update_events_timestamp
BEFORE UPDATE ON events
FOR EACH ROW
EXECUTE FUNCTION update_events_timestamp();


-- Tabela dla auditowania zmian (transakcja)
CREATE TABLE IF NOT EXISTS audit_log (
    id SERIAL PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    operation VARCHAR(10) NOT NULL,
    record_id INTEGER,
    old_data JSONB,
    new_data JSONB,
    changed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    changed_at TIMESTAMPTZ DEFAULT NOW()
);

--trigger: Logowanie zmian w event_participants (demonstracja wyzwalacza z transakcją)
CREATE OR REPLACE FUNCTION audit_event_participant_change()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO audit_log (table_name, operation, record_id, new_data, changed_at)
        VALUES ('event_participants', 'INSERT', NEW.event_id, row_to_json(NEW), NOW());
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO audit_log (table_name, operation, record_id, old_data, changed_at)
        VALUES ('event_participants', 'DELETE', OLD.event_id, row_to_json(OLD), NOW());
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tr_audit_event_participant ON event_participants;
CREATE TRIGGER tr_audit_event_participant
AFTER INSERT OR DELETE ON event_participants
FOR EACH ROW
EXECUTE FUNCTION audit_event_participant_change();
