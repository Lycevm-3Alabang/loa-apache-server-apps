-- ============================================================================
-- LOA AUTH PLATFORM - cPanel DATABASE INSTALLER
-- Target database : lyceumalabang_auth_db
--                   (create it first in cPanel, collation utf8mb4_unicode_ci,
--                    then select it in phpMyAdmin before importing this file)
-- Generated       : 2026-08-24 from a freshly migrated schema plus provisioned
--                   reference data (tenant, catalog, groups, grants).
-- Re-runnable     : yes - every table is DROP TABLE IF EXISTS'd first.
--
-- INCLUDED
--   * Full schema (identity, sessions, cache, jobs, audit, activations...)
--   * Tenant 'loa-e-cert' with redirect origins (e-cert.vercel.app)
--   * Endpoint catalog (57 endpoints incl. attendees/lookup) + level-based grant matrix:
--       cert-admin : 57 grants @ admin   (full control)
--       cert-staff : 43 grants            (read/write levels; admin-only paths excluded)
--       cert-user  :  5 grants @ read    (/me/* participant scope)
--   * Permissions registry (7 keys) + platform-admin claim grants (9)
--   * Default admin: admin@lyceumalabang.edu.ph / Admin123!   <<< CHANGE AFTER FIRST LOGIN
--
-- NOT included by design: end-user accounts. Provision them afterwards via
-- activation emails, bulk-import CSV, or /sso/register (LOA domains only).
--
-- HOW TO RUN
--   cPanel -> phpMyAdmin -> select lyceumalabang_auth_db -> Import tab -> upload this file.
--   this file. Or over SSH: mysql -u <user> -p lyceumalabang_auth_db < this-file.sql
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- FIX v2: purge ALL tables up-front so no stale/incompatible definitions
-- from previous import attempts can collide with foreign keys below.
DROP TABLE IF EXISTS `activations`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `group_claims`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `migrations`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `refresh_tokens`;
DROP TABLE IF EXISTS `route_policies`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `tenant_app_endpoints`;
DROP TABLE IF EXISTS `tenant_endpoint_grants`;
DROP TABLE IF EXISTS `tenant_endpoint_overrides`;
DROP TABLE IF EXISTS `tenants`;
DROP TABLE IF EXISTS `user_claim_overrides`;
DROP TABLE IF EXISTS `user_group_permission`;
DROP TABLE IF EXISTS `user_groups`;
DROP TABLE IF EXISTS `user_permission`;
DROP TABLE IF EXISTS `user_tenants`;
DROP TABLE IF EXISTS `user_user_group`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activations_user_id_foreign` (`user_id`),
  KEY `activations_token_index` (`token`),
  CONSTRAINT `activations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `claims_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `claim_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `scope_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_claims_group_id_foreign` (`group_id`),
  CONSTRAINT `group_claims_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_attempted` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_user_id_attempted_at_index` (`user_id`,`attempted_at`),
  KEY `login_attempts_ip_address_attempted_at_index` (`ip_address`,`attempted_at`),
  CONSTRAINT `login_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_reset_tokens_token_index` (`token`),
  KEY `password_reset_tokens_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `password_reset_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_set_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_set_tokens` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_set_tokens_token_index` (`token`),
  KEY `password_set_tokens_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `password_set_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jti` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `replaced_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `route_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `route_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filter` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_app_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_app_endpoints` (
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `required_level` enum('read','write','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'read',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tenant_id`,`method`,`path`),
  KEY `tenant_app_endpoints_method_path_index` (`method`,`path`),
  CONSTRAINT `tenant_app_endpoints_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_endpoint_grants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_endpoint_grants` (
  `group_id` bigint unsigned NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('read','write','admin','deny') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'read',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`group_id`,`tenant_id`,`method`,`path`),
  KEY `tenant_endpoint_grants_tenant_id_foreign` (`tenant_id`),
  KEY `tenant_endpoint_grants_method_path_index` (`method`,`path`),
  CONSTRAINT `tenant_endpoint_grants_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_endpoint_grants_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_endpoint_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_endpoint_overrides` (
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('read','write','admin','deny') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'read',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`tenant_id`,`method`,`path`),
  KEY `tenant_endpoint_overrides_tenant_id_foreign` (`tenant_id`),
  KEY `tenant_endpoint_overrides_method_path_index` (`method`,`path`),
  CONSTRAINT `tenant_endpoint_overrides_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_endpoint_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `app_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dev_app_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect_origins` json DEFAULT NULL,
  `dev_redirect_origins` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_claim_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_claim_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_claim_overrides_user_id_foreign` (`user_id`),
  CONSTRAINT `user_claim_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_group_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_group_permission` (
  `user_group_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  UNIQUE KEY `ugp_scope_unique` (`user_group_id`,`permission_id`,`tenant_id`),
  KEY `user_group_permission_user_group_id_index` (`user_group_id`),
  KEY `user_group_permission_permission_id_index` (`permission_id`),
  KEY `user_group_permission_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_group_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_group_permission_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_group_permission_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '10',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_groups_tenant_id_name_index` (`tenant_id`,`name`),
  CONSTRAINT `user_groups_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permission` (
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  UNIQUE KEY `up_scope_unique` (`user_id`,`permission_id`,`tenant_id`),
  KEY `user_permission_user_id_index` (`user_id`),
  KEY `user_permission_permission_id_index` (`permission_id`),
  KEY `user_permission_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permission_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permission_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_tenants` (
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`tenant_id`),
  KEY `user_tenants_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `user_tenants_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_tenants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_user_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_user_group` (
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_group_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`user_group_id`),
  KEY `user_user_group_user_group_id_foreign` (`user_group_id`),
  CONSTRAINT `user_user_group_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_user_group_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','active','disabled','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `failed_attempts` int NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
LOCK TABLES `tenants` WRITE;
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','loa-e-cert','Local Cert App','active','http://localhost:9001','http://localhost:9001','[\"http://localhost:3000\"]','[\"http://localhost:3000\"]','2026-08-21 10:42:49','2026-08-21 15:36:35');
UNLOCK TABLES;
LOCK TABLES `user_groups` WRITE;
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (1,'loa-auth-admin','Platform administrator',10,NULL,'2026-08-21 10:42:48','2026-08-21 10:42:48');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (2,'cert-admin','Local certificate administrator',2,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (3,'cert-staff','Local certificate staff',3,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (4,'cert-user','Local certificate user',4,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-21 10:42:49','2026-08-21 10:42:49');
UNLOCK TABLES;
LOCK TABLES `permissions` WRITE;
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (1,'users.view','View user list and details',NULL,'2026-08-21 10:42:48','2026-08-21 10:42:48');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (2,'users.manage','Enable/disable users, manage status',NULL,'2026-08-21 10:42:48','2026-08-21 10:42:48');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (3,'groups.view','View groups',NULL,'2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (4,'groups.manage','Create, edit, delete groups',NULL,'2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (5,'permissions.view','View permissions',NULL,'2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (6,'permissions.manage','Assign permissions to groups',NULL,'2026-08-21 10:42:49','2026-08-21 10:42:49');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (7,'auth.verify','Validate tokens (internal)',NULL,'2026-08-21 10:42:49','2026-08-21 10:42:49');
UNLOCK TABLES;
LOCK TABLES `user_group_permission` WRITE;
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,1,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,2,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,3,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,4,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,5,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,6,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,7,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (2,2,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (2,1,NULL,1);
UNLOCK TABLES;
-- JWT permission-key claims (source of truth for jwt.permission:* middleware)
LOCK TABLES `group_claims` WRITE;
INSERT INTO `group_claims` (`group_id`, `claim_key`, `scope_type`, `scope_id`, `created_at`, `updated_at`) VALUES
(1,'users.view','none',NULL,NOW(),NOW()),
(1,'users.manage','none',NULL,NOW(),NOW()),
(1,'groups.view','none',NULL,NOW(),NOW()),
(1,'groups.manage','none',NULL,NOW(),NOW()),
(1,'permissions.view','none',NULL,NOW(),NOW()),
(1,'permissions.manage','none',NULL,NOW(),NOW()),
(1,'auth.verify','none',NULL,NOW(),NOW()),
(2,'users.view','none',NULL,NOW(),NOW()),
(2,'users.manage','none',NULL,NOW(),NOW());
UNLOCK TABLES;
LOCK TABLES `tenant_app_endpoints` WRITE;
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','DELETE','/api/v1/attendees/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','DELETE','/api/v1/attendees/{id}/with-cert',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','DELETE','/api/v1/certificates/{id}',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','DELETE','/api/v1/events/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','DELETE','/api/v1/templates/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/access',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/admin/audit-logs',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/admin/audit-logs/export',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/attendees/{id}/delete-preview',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/attendees/{id}/file-data',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/audit',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates/{id}',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates/{id}/download',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates/{id}/email-logs',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates/{id}/pdf',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/certificates/qr',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/dashboard/activity',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/dashboard/stats',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/events',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/events/{id}',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/events/{id}/attendees',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/events/{id}/revoke-expired',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/events/{id}/stats',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/attendees',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/certificates',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/certificates/{id}',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/events',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/organizations',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/me/templates',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/templates',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/templates/{id}',NULL,NULL,'read','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','PATCH','/api/v1/attendees/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','PATCH','/api/v1/events/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','PATCH','/api/v1/templates/{id}',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/auth/callback',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/auth/logout',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/auth/refresh',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/{id}/email',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/{id}/reissue',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/{id}/revoke',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/bulk',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/expire',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/certificates/upload',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/attendees',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/attendees/import',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/bulk-issue',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/clone-email-template',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/clone-template',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/issue-completed',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/reissue',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/events/{id}/revoke-expired',NULL,NULL,'admin','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','POST','/api/v1/templates',NULL,NULL,'write','2026-08-21 22:37:08','2026-08-21 22:37:08');
INSERT INTO `tenant_app_endpoints` (`tenant_id`, `method`, `path`, `label`, `description`, `required_level`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','GET','/api/v1/attendees/lookup','Attendee lookup by email','Cross-event attendee and certificate summary for a single email address','read','2026-08-24 00:00:00','2026-08-24 00:00:00');
UNLOCK TABLES;
LOCK TABLES `tenant_endpoint_grants` WRITE;
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'DELETE','/api/v1/attendees/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'DELETE','/api/v1/attendees/{id}/with-cert','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'DELETE','/api/v1/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'DELETE','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'DELETE','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/access','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/admin/audit-logs','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/admin/audit-logs/export','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/attendees/{id}/delete-preview','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/attendees/{id}/file-data','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/audit','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/attendees/lookup','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-24 00:00:00','2026-08-24 00:00:00');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates/{id}/download','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates/{id}/email-logs','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates/{id}/pdf','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/certificates/qr','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/dashboard/activity','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/dashboard/stats','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/events','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/events/{id}/attendees','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/events/{id}/revoke-expired','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/events/{id}/stats','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/attendees','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/events','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/organizations','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/me/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'GET','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'PATCH','/api/v1/attendees/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'PATCH','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'PATCH','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/auth/callback','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/auth/logout','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/auth/refresh','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/{id}/email','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/{id}/reissue','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/{id}/revoke','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/bulk','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/expire','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/certificates/upload','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/attendees','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/attendees/import','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/bulk-issue','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/clone-email-template','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/clone-template','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/issue-completed','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/reissue','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/events/{id}/revoke-expired','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
INSERT INTO `tenant_endpoint_grants` (`group_id`, `method`, `path`, `tenant_id`, `level`, `created_at`, `updated_at`) VALUES (2,'POST','/api/v1/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','admin','2026-08-21 22:38:45','2026-08-21 22:38:45');
UNLOCK TABLES;
-- --- Production tenant values (replaces local dev URLs) -----------------------
UPDATE `tenants` SET
  `name` = 'LOA e-cert',
  `app_url` = 'https://e-cert.vercel.app',
  `redirect_origins` = '["https://e-cert.vercel.app"]',
  `dev_app_url` = NULL,
  `dev_redirect_origins` = NULL
WHERE `slug` = 'loa';
UPDATE `user_groups` SET `description` = 'Certificate administrator' WHERE `name` = 'cert-admin';
UPDATE `user_groups` SET `description` = 'Certificate staff'         WHERE `name` = 'cert-staff';
UPDATE `user_groups` SET `description` = 'Certificate participant'   WHERE `name` = 'cert-user';
-- --- Default platform admin ----------------------------------------------------
-- Credentials: admin@lyceumalabang.edu.ph / Admin123!  - CHANGE AFTER FIRST LOGIN.
INSERT INTO `users` (`id`, `email`, `password`, `name`, `status`, `created_at`, `updated_at`) VALUES
('4f09e69e-4b82-4c9f-bfe0-4ccae5256b1e', 'admin@lyceumalabang.edu.ph',
 '$2y$10$yPmbXZYnpGNF3rnXEkPS2OH3aTunz5GWtg6YEv/D4i1B0l8ws0LBC',
 'Super Admin', 'active', NOW(), NOW());
INSERT INTO `user_user_group` (`user_id`, `user_group_id`, `created_at`, `updated_at`) VALUES
('4f09e69e-4b82-4c9f-bfe0-4ccae5256b1e', 1, NOW(), NOW());
-- --- Auth tenant (LOA Auth Platform) -----------------------------------------
-- slug = 'auth', read-only (redirect_origins = empty), app_url = NULL
-- Members are assigned to platform-level groups (tenant_id = NULL) so the
-- session redirects to platform groups, not back to /auth/login.
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES
('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'auth', 'LOA Auth Platform', 'active', NULL, NULL, '[]', '[]', NOW(), NOW());
-- --- Generated staff/user endpoint grants --------------------------------------
-- cert-staff: every read/write catalog endpoint at its own level (admin-only excluded)
-- cert-user : GET read-level /me/* endpoints only
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/events','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'PATCH','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'DELETE','/api/v1/events/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/events/{id}/stats','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/clone-template','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/clone-email-template','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/bulk-issue','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/events/{id}/revoke-expired','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/issue-completed','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/events/{id}/attendees','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/attendees','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/events/{id}/attendees/import','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'PATCH','/api/v1/attendees/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'DELETE','/api/v1/attendees/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/attendees/{id}/delete-preview','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/attendees/{id}/file-data','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'PATCH','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'DELETE','/api/v1/templates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/certificates/bulk','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/certificates/upload','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates/{id}/pdf','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates/{id}/download','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'POST','/api/v1/certificates/{id}/email','91128f0a-df85-47a9-ae1d-5298904dacd5','write');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates/{id}/email-logs','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/certificates/qr','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/me/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (4,'GET','/api/v1/me/certificates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/me/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (4,'GET','/api/v1/me/certificates/{id}','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/me/events','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (4,'GET','/api/v1/me/events','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/me/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (4,'GET','/api/v1/me/templates','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/dashboard/stats','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
INSERT INTO `tenant_endpoint_grants` (`group_id`,`method`,`path`,`tenant_id`,`level`) VALUES (3,'GET','/api/v1/dashboard/activity','91128f0a-df85-47a9-ae1d-5298904dacd5','read');
SET FOREIGN_KEY_CHECKS = 1;
-- --- Sanity checks (uncomment to run after import) ----------------------------------------
-- SELECT COUNT(*) AS tenants      FROM tenants;                  -- 1
-- SELECT COUNT(*) AS endpoints    FROM tenant_app_endpoints;     -- 56
-- SELECT COUNT(*) AS groups       FROM user_groups;              -- 4
-- SELECT COUNT(*) AS grants_total FROM tenant_endpoint_grants;   -- 99
-- SELECT COUNT(*) AS users        FROM users;                    -- 1
-- SELECT redirect_origins FROM tenants WHERE slug = 'loa';     -- ["https://e-cert.vercel.app"]
