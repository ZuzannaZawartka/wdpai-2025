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
