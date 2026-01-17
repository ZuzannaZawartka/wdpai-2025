INSERT INTO users (firstname, lastname, email, password, birth_date, latitude, longitude, role, enabled)
VALUES
    ('Anna', 'Nowak', 'anna.nowak@example.com', '$2y$10$CAOW351Dd91Pc9a1MwCqz.DcV8NUiKOjHuf0eXPubgcCel4jXcWaW', '1995-01-01', 52.2297, 21.0122, 'user', TRUE), -- test1234
    ('Piotr', 'Kowalski', 'piotr.kowalski@example.com', '$2y$10$CAOW351Dd91Pc9a1MwCqz.DcV8NUiKOjHuf0eXPubgcCel4jXcWaW', '1992-03-15', 50.0614, 19.9383, 'user', TRUE), -- test1234
    ('Kasia', 'Wiśniewska', 'kasia.wisniewska@example.com', '$2y$10$J0MAr2rTKS813dH9hfLaN.Qu2l8IOLwD/Uy0Af8n5JvUCQGyWTqxa', '1998-07-22', 51.1079, 17.0385, 'user', TRUE), -- test5678
    ('Marek', 'Lewandowski', 'marek.lewandowski@example.com', '$2y$10$J0MAr2rTKS813dH9hfLaN.Qu2l8IOLwD/Uy0Af8n5JvUCQGyWTqxa', '1990-11-30', 54.3520, 18.6466, 'user', TRUE), -- test5678
    ('Ola', 'Zielińska', 'ola.zielinska@example.com', '$2y$10$EbTL2aI5Rn.8ok9H7FhcluJ4gg2HLyq/7P1psDUbj0UW8Y710C/AC', '1993-04-10', 53.1325, 23.1688, 'user', TRUE), -- testabcd
    ('Tomasz', 'Wójcik', 'tomasz.wojcik@example.com', '$2y$10$EbTL2aI5Rn.8ok9H7FhcluJ4gg2HLyq/7P1psDUbj0UW8Y710C/AC', '1988-09-18', 50.0413, 21.9990, 'user', TRUE), -- testabcd
    ('Ewa', 'Kamińska', 'ewa.kaminska@example.com', '$2y$10$3nZtTlRL.w69GBKeNnAmyuTYVkI/hMwDMLytCBBkjLaLWfTnuzAOS', '1996-12-05', 51.7592, 19.4559, 'user', TRUE), -- testqwer
    ('Bartek', 'Dąbrowski', 'bartek.dabrowski@example.com', '$2y$10$3nZtTlRL.w69GBKeNnAmyuTYVkI/hMwDMLytCBBkjLaLWfTnuzAOS', '1991-06-14', 52.4064, 16.9252, 'user', TRUE), -- testqwer
    ('Magda', 'Szymańska', 'magda.szymanska@example.com', '$2y$10$/hfNZuu7STPf7/Lmy5vXpuMxaEQC0P0EZ51RpE0/HfXHNf9FqLEZe', '1994-02-28', 50.2649, 19.0238, 'user', TRUE), -- testzxcv
    ('Kamil', 'Kaczmarek', 'kamil.kaczmarek@example.com', '$2y$10$/hfNZuu7STPf7/Lmy5vXpuMxaEQC0P0EZ51RpE0/HfXHNf9FqLEZe', '1997-08-19', 51.2465, 22.5684, 'user', TRUE), -- testzxcv
    ('Admin', 'User', 'admin@gmail.com', '$2y$10$.cxeuGcH1DL/afgQI68wPesFehMV5eppB775vripuhtZfjULqiiF.', '1980-01-01', NULL, NULL, 'admin', TRUE) -- adminadmin
ON CONFLICT (email) DO NOTHING;


UPDATE sports SET icon = 'sports_soccer' WHERE name = 'Soccer';
UPDATE sports SET icon = 'sports_basketball' WHERE name = 'Basketball';
UPDATE sports SET icon = 'sports_tennis' WHERE name = 'Tennis';
UPDATE sports SET icon = 'directions_run' WHERE name = 'Running';
UPDATE sports SET icon = 'directions_bike' WHERE name = 'Cycling';
UPDATE sports SET icon = 'sports_volleyball' WHERE name = 'Volleyball';


INSERT INTO sports (name, icon) VALUES
    ('Soccer', 'sports_soccer'),
    ('Basketball', 'sports_basketball'),
    ('Tennis', 'sports_tennis'),
    ('Running', 'directions_run'),
    ('Cycling', 'directions_bike'),
    ('Volleyball', 'sports_volleyball')
ON CONFLICT (name) DO NOTHING;

INSERT INTO levels (name, hex_color) VALUES
    ('Beginner', '#4CAF50'),
    ('Intermediate', '#FF9800'),
    ('Advanced', '#F44336')
ON CONFLICT (name) DO NOTHING;

INSERT INTO user_favourite_sports (user_id, sport_id) VALUES
    (1, 1), (2, 3), (3, 2), (4, 4), (5, 5),
    (6, 1), (7, 2), (8, 3), (9, 4), (10, 5)
ON CONFLICT DO NOTHING;

INSERT INTO events (owner_id, title, description, sport_id, location_text, latitude, longitude, start_time, level_id, max_players)
VALUES
    (1, 'Mecz piłki nożnej', 'Gramy na Orliku', 1, 'Orlik Warszawa', 52.2297, 21.0122, NOW() + INTERVAL '2 days', 1, 10),
    (2, 'Tenis dla początkujących', 'Trening tenisowy', 3, 'Kort Kraków', 50.0614, 19.9383, NOW() + INTERVAL '3 days', 1, 4),
    (3, 'Koszykówka wieczorem', 'Mecz koszykówki', 2, 'Hala Wrocław', 51.1079, 17.0385, NOW() + INTERVAL '1 day', 2, 8),
    (4, 'Bieg na orientację', 'Bieg w parku', 4, 'Park Gdańsk', 54.3520, 18.6466, NOW() + INTERVAL '5 days', 1, 20),
    (5, 'Rowerowa wycieczka', 'Trasa przez Mazury', 5, 'Mazury', 53.1325, 23.1688, NOW() + INTERVAL '7 days', 3, 12),
    -- Past events created by Kasia (user_id = 3)
    (3, 'Siatkówka na plaży - zakończona', 'Turniej siatkówki plażowej', 6, 'Plaża Wrocław', 51.1079, 17.0385, '2026-01-10 14:00:00', 2, 12),
    (3, 'Poranny jogging - zakończony', 'Wspólny bieg o poranku', 4, 'Park Wrocław', 51.1079, 17.0385, '2026-01-05 08:00:00', 1, 15)
ON CONFLICT DO NOTHING;

-- Past events that Kasia joined
INSERT INTO events (owner_id, title, description, sport_id, location_text, latitude, longitude, start_time, level_id, max_players)
VALUES
    (1, 'Mecz tenisa - zakończony', 'Turniej tenisowy', 3, 'Kort Warszawa', 52.2297, 21.0122, '2026-01-12 16:00:00', 2, 8),
    (2, 'Koszykówka wieczorna - zakończona', 'Mecz koszykówki', 2, 'Hala Kraków', 50.0614, 19.9383, '2026-01-08 18:00:00', 1, 10)
ON CONFLICT DO NOTHING;

INSERT INTO event_participants (event_id, user_id, status)
VALUES
    (1, 2, 'confirmed'), (1, 3, 'confirmed'), (1, 4, 'confirmed'),
    (2, 1, 'confirmed'), (2, 5, 'confirmed'),
    (3, 6, 'confirmed'), (3, 7, 'confirmed'),
    (4, 8, 'confirmed'), (4, 9, 'confirmed'),
    (5, 10, 'confirmed'), (5, 1, 'confirmed'),
    -- Kasia joined past events (event IDs 8 and 9)
    (8, 3, 'confirmed'),
    (9, 3, 'confirmed')
ON CONFLICT DO NOTHING;
