-- Creates the cert and consult databases and grants the loa user access.
-- The auth database (loa_auth) is created by MYSQL_DATABASE env var.

CREATE DATABASE IF NOT EXISTS loa_cert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_cert.* TO 'loa'@'%';
FLUSH PRIVILEGES;

CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%';
FLUSH PRIVILEGES;

-- Consult test database (test-suite.md CON-1: dedicated loa_consult_test).
-- run-tests.ps1 also ensures it at test time; this covers fresh volumes for
-- direct `php artisan test` runs.
CREATE DATABASE IF NOT EXISTS loa_consult_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult_test.* TO 'loa'@'%';
FLUSH PRIVILEGES;
