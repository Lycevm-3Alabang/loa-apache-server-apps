-- Creates the cert database and grants the loa user access.
-- The auth database (loa_auth) is created by MYSQL_DATABASE env var.

CREATE DATABASE IF NOT EXISTS loa_cert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_cert.* TO 'loa'@'%';
FLUSH PRIVILEGES;
