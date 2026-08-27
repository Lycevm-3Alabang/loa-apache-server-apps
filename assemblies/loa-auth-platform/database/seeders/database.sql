USE `loa_auth`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_07_30_000001_create_users_table', 1),
(2, '2026_07_30_000002_create_user_groups_table', 1),
(3, '2026_07_30_000003_create_permissions_table', 1),
(4, '2026_07_30_000004_create_user_user_group_table', 1),
(5, '2026_07_30_000005_create_user_group_permission_table', 1),
(6, '2026_07_30_000006_create_user_permission_table', 1),
(7, '2026_07_30_000007_create_login_attempts_table', 1),
(8, '2026_07_30_000008_create_password_reset_tokens_table', 1),
(9, '2026_07_30_000009_create_refresh_tokens_table', 1),
(10, '2026_08_01_000010_create_sessions_table', 1),
(11, '2026_08_01_000011_create_tenants_table', 1),
(12, '2026_08_01_000012_create_user_tenants_table', 1),
(13, '2026_08_01_000013_add_tenant_id_to_user_groups_table', 1),
(14, '2026_08_01_000014_add_tenant_id_to_user_group_permission_table', 1),
(15, '2026_08_01_000015_add_tenant_id_to_user_permission_table', 1),
(16, '2026_08_14_000001_add_dev_columns_to_tenants_table', 1);

CREATE TABLE `tenants` (
  `id` char(36) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `app_url` varchar(255) DEFAULT NULL,
  `dev_app_url` varchar(255) DEFAULT NULL,
  `redirect_origins` json DEFAULT NULL,
  `dev_redirect_origins` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_tenants` (
  `user_id` char(36) NOT NULL,
  `tenant_id` char(36) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`tenant_id`),
  KEY `user_tenants_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_tenants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_tenants_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('active','disabled','locked') NOT NULL DEFAULT 'active',
  `failed_attempts` int NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `priority` int NOT NULL DEFAULT 10,
  `tenant_id` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_groups_tenant_id_name_index` (`tenant_id`,`name`),
  CONSTRAINT `user_groups_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `endpoint_pattern` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_user_group` (
  `user_id` char(36) NOT NULL,
  `user_group_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`user_group_id`),
  KEY `user_user_group_user_group_id_foreign` (`user_group_id`),
  CONSTRAINT `user_user_group_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_user_group_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_group_permission` (
  `user_group_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `tenant_id` char(36) DEFAULT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `ugp_scope_unique` (`user_group_id`,`permission_id`,`tenant_id`),
  KEY `user_group_permission_user_group_id_index` (`user_group_id`),
  KEY `user_group_permission_permission_id_index` (`permission_id`),
  KEY `user_group_permission_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_group_permission_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_group_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_group_permission_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_permission` (
  `user_id` char(36) NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `tenant_id` char(36) DEFAULT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `up_scope_unique` (`user_id`,`permission_id`,`tenant_id`),
  KEY `user_permission_user_id_index` (`user_id`),
  KEY `user_permission_permission_id_index` (`permission_id`),
  KEY `user_permission_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_permission_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permission_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` char(36) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `email_attempted` varchar(255) NOT NULL,
  `ip_address` varchar(255) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_user_id_attempted_at_index` (`user_id`,`attempted_at`),
  KEY `login_attempts_ip_address_attempted_at_index` (`ip_address`,`attempted_at`),
  CONSTRAINT `login_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_reset_tokens_token_index` (`token`),
  KEY `password_reset_tokens_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `password_reset_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_set_tokens` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_set_tokens_token_index` (`token`),
  KEY `password_set_tokens_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `password_set_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refresh_tokens` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `jti` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `replaced_by` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refresh_tokens_jti_unique` (`jti`),
  KEY `refresh_tokens_replaced_by_foreign` (`replaced_by`),
  KEY `refresh_tokens_user_id_revoked_at_index` (`user_id`,`revoked_at`),
  KEY `refresh_tokens_expires_at_index` (`expires_at`),
  CONSTRAINT `refresh_tokens_replaced_by_foreign` FOREIGN KEY (`replaced_by`) REFERENCES `refresh_tokens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refresh_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: loa-auth-admin group (platform-wide, tenant_id NULL)
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES
(1, 'loa-auth-admin', 'Platform administrator', 1, NULL, NOW(), NOW());

INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES
(1, 'users.view', 'View user list and details', NULL, NOW(), NOW()),
(2, 'users.manage', 'Enable/disable users, manage status', NULL, NOW(), NOW()),
(3, 'groups.view', 'View groups', NULL, NOW(), NOW()),
(4, 'groups.manage', 'Create, edit, delete groups', NULL, NOW(), NOW()),
(5, 'permissions.view', 'View permissions', NULL, NOW(), NOW()),
(6, 'permissions.manage', 'Assign permissions to groups', NULL, NOW(), NOW()),
(7, 'auth.verify', 'Validate tokens (internal)', NULL, NOW(), NOW());

INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES
(1, 1, NULL, 1),
(1, 2, NULL, 1),
(1, 3, NULL, 1),
(1, 4, NULL, 1),
(1, 5, NULL, 1),
(1, 6, NULL, 1),
(1, 7, NULL, 1);

-- Admin password: Admin123! (change after first login)
-- Hash generated with: php -r "echo password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 12]);"
INSERT INTO `users` (`id`, `email`, `password`, `name`, `status`, `failed_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(UUID(), 'admin@lyceumalabang.edu.ph', '$2y$12$E6lLznQU.cdjAwfPqxsAUuUlQic6SK0XMtmSAOLkqVXWNIDOaWWwK', 'Super Admin', 'active', 0, NULL, NOW(), NOW());

SET @admin_id = (SELECT `id` FROM `users` WHERE `email` = 'admin@lyceumalabang.edu.ph');
INSERT INTO `user_user_group` (`user_id`, `user_group_id`, `created_at`, `updated_at`) VALUES
(@admin_id, 1, NOW(), NOW());

-- ─── Tenant app: LOA E-Cert ─────────────────────────────────────────
-- Slug is immutable after issuance and must match each tenant app's
-- NEXT_PUBLIC_CERT_TENANT_SLUG / TENANT_SLUG configuration.
SET @ecert_tenant_id = '91128f0a-df85-47a9-ae1d-5298904dacd5';

INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES
(@ecert_tenant_id, 'loa-e-cert', 'Local Cert App', 'active', 'http://localhost:9001', 'http://localhost:9001', JSON_ARRAY('http://localhost:3000'), JSON_ARRAY('http://localhost:3000'), NOW(), NOW());

-- Auth tenant (LOA Auth Platform) — slug = 'auth', read-only, app_url = NULL
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES
('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'auth', 'LOA Auth Platform', 'active', NULL, NULL, JSON_ARRAY(), JSON_ARRAY(), NOW(), NOW());

INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES
(2, 'cert-admin', 'Local certificate administrator', 2, @ecert_tenant_id, NOW(), NOW()),
(3, 'cert-staff', 'Local certificate staff', 3, @ecert_tenant_id, NOW(), NOW()),
(4, 'cert-user', 'Local certificate user', 4, @ecert_tenant_id, NOW(), NOW());

INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES
(2, 1, NULL, 1),
(2, 2, NULL, 1);

-- JWT permission-key claims (source of truth for jwt.permission:* middleware)
INSERT INTO `group_claims` (`group_id`, `claim_key`, `scope_type`, `scope_id`, `created_at`, `updated_at`) VALUES
(1, 'users.view', 'none', NULL, NOW(), NOW()),
(1, 'users.manage', 'none', NULL, NOW(), NOW()),
(1, 'groups.view', 'none', NULL, NOW(), NOW()),
(1, 'groups.manage', 'none', NULL, NOW(), NOW()),
(1, 'permissions.view', 'none', NULL, NOW(), NOW()),
(1, 'permissions.manage', 'none', NULL, NOW(), NOW()),
(1, 'auth.verify', 'none', NULL, NOW(), NOW()),
(2, 'users.view', 'none', NULL, NOW(), NOW()),
(2, 'users.manage', 'none', NULL, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
