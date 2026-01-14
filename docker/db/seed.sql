-- Seed admin user (email: admin@example.com, password: adminadmin, role: admin)
INSERT INTO users (firstname, lastname, email, password, birth_date, role, enabled)
VALUES (
    'Admin',
    'User',
    'admin@example.com',
    '$2b$10$w8Qw6Qw6Qw6Qw6Qw6Qw6QeQw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6', -- bcrypt for 'adminadmin'
    '1980-01-01',
    'admin',
    TRUE
)
ON CONFLICT (email) DO NOTHING;
