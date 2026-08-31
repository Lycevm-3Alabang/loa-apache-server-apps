-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: lyceumalabang_auth_db
-- ------------------------------------------------------
-- Server version	8.0.46

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

--
-- Table structure for table `claims`
--

DROP TABLE IF EXISTS `claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `claims`;
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

--
-- Dumping data for table `claims`
--

LOCK TABLES `claims` WRITE;
/*!40000 ALTER TABLE `claims` DISABLE KEYS */;
/*!40000 ALTER TABLE `claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_claims`
--

DROP TABLE IF EXISTS `group_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `group_claims`;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_claims`
--

LOCK TABLES `group_claims` WRITE;
/*!40000 ALTER TABLE `group_claims` DISABLE KEYS */;
INSERT INTO `group_claims` (`id`, `group_id`, `claim_key`, `scope_type`, `scope_id`, `created_at`, `updated_at`) VALUES (1,2,'users.view','none',NULL,'2026-08-30 21:50:25','2026-08-30 21:50:25');
INSERT INTO `group_claims` (`id`, `group_id`, `claim_key`, `scope_type`, `scope_id`, `created_at`, `updated_at`) VALUES (2,2,'users.manage','none',NULL,'2026-08-30 21:50:25','2026-08-30 21:50:25');
/*!40000 ALTER TABLE `group_claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2026_07_30_000001_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2026_07_30_000002_create_user_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2026_07_30_000003_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_30_000004_create_user_user_group_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_07_30_000005_create_user_group_permission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_30_000006_create_user_permission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_30_000007_create_login_attempts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_30_000008_create_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_30_000009_create_refresh_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_08_01_000010_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_08_01_000011_create_tenants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_08_01_000012_create_user_tenants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_08_01_000013_add_tenant_id_to_user_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_08_01_000014_add_tenant_id_to_user_group_permission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_08_01_000015_add_tenant_id_to_user_permission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_08_01_000016_create_claims_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_08_01_000017_create_route_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_08_01_000018_create_group_claims_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_08_01_000019_create_user_claim_overrides_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_08_02_000020_create_tenant_app_endpoints_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_08_02_000021_create_tenant_endpoint_grants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_08_02_000022_create_tenant_endpoint_overrides_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_08_03_000023_add_priority_to_user_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_08_07_000001_add_pending_status_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_08_07_000002_create_activations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_08_14_000001_add_dev_columns_to_tenants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_08_25_000001_ensure_legacy_redirect_origins_have_tenants',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_08_25_000002_create_audit_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_08_27_000001_create_password_set_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_08_27_000002_create_tenant_api_keys_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_08_30_071142_create_jobs_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `permissions`;
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

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (1,'users.view','View user list and details',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (2,'users.manage','Enable/disable users, manage status',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (3,'groups.view','View groups',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (4,'groups.manage','Create, edit, delete groups',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (5,'permissions.view','View permissions',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (6,'permissions.manage','Assign permissions to groups',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `permissions` (`id`, `key`, `description`, `endpoint_pattern`, `created_at`, `updated_at`) VALUES (7,'auth.verify','Validate tokens (internal)',NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `route_policies`
--

DROP TABLE IF EXISTS `route_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `route_policies`;
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

--
-- Dumping data for table `route_policies`
--

LOCK TABLES `route_policies` WRITE;
/*!40000 ALTER TABLE `route_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `route_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_app_endpoints`
--

DROP TABLE IF EXISTS `tenant_app_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `tenant_app_endpoints`;
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

--
-- Dumping data for table `tenant_app_endpoints`
--

LOCK TABLES `tenant_app_endpoints` WRITE;
/*!40000 ALTER TABLE `tenant_app_endpoints` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_app_endpoints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_endpoint_grants`
--

DROP TABLE IF EXISTS `tenant_endpoint_grants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `tenant_endpoint_grants`;
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

--
-- Dumping data for table `tenant_endpoint_grants`
--

LOCK TABLES `tenant_endpoint_grants` WRITE;
/*!40000 ALTER TABLE `tenant_endpoint_grants` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_endpoint_grants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `tenants`;
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

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES ('0eed6f0b-45d8-4071-bb8c-10986b3e81cb','auth','LOA Auth Platform','active',NULL,NULL,'[]','[]','2026-08-30 21:50:25','2026-08-30 21:50:25');
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES ('6caa4779-f03e-4147-8118-2cc9e01dd6de','e-cert','E-Cert Platform','active','https://e-cert.vercel.app',NULL,'[\"https://e-cert.vercel.app\"]',NULL,'2026-08-30 21:50:09','2026-08-30 21:50:09');
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES ('91128f0a-df85-47a9-ae1d-5298904dacd5','loa-e-cert','Local Cert App','active','http://localhost:9001','http://localhost:9001','[\"http://localhost:3000\"]','[\"http://localhost:3000\"]','2026-08-30 21:50:25','2026-08-30 21:50:25');
INSERT INTO `tenants` (`id`, `slug`, `name`, `status`, `app_url`, `dev_app_url`, `redirect_origins`, `dev_redirect_origins`, `created_at`, `updated_at`) VALUES ('fa974feb-c5dc-41e6-907c-69a403381ecf','aces-api','ACES Platform','active','https://aces-api.lyceumalabang.edu.ph',NULL,'[\"https://aces-api.lyceumalabang.edu.ph\"]',NULL,'2026-08-30 21:50:08','2026-08-30 21:50:08');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_group_permission`
--

DROP TABLE IF EXISTS `user_group_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `user_group_permission`;
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

--
-- Dumping data for table `user_group_permission`
--

LOCK TABLES `user_group_permission` WRITE;
/*!40000 ALTER TABLE `user_group_permission` DISABLE KEYS */;
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,1,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,2,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,3,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,4,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,5,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,6,NULL,1);
INSERT INTO `user_group_permission` (`user_group_id`, `permission_id`, `tenant_id`, `granted`) VALUES (1,7,NULL,1);
/*!40000 ALTER TABLE `user_group_permission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_groups`
--

DROP TABLE IF EXISTS `user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `user_groups`;
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

--
-- Dumping data for table `user_groups`
--

LOCK TABLES `user_groups` WRITE;
/*!40000 ALTER TABLE `user_groups` DISABLE KEYS */;
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (1,'loa-auth-admin','Platform administrator',10,NULL,'2026-08-30 21:50:24','2026-08-30 21:50:24');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (2,'cert-admin','Local certificate administrator',2,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-30 21:50:25','2026-08-30 21:50:25');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (3,'cert-staff','Local certificate staff',3,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-30 21:50:25','2026-08-30 21:50:25');
INSERT INTO `user_groups` (`id`, `name`, `description`, `priority`, `tenant_id`, `created_at`, `updated_at`) VALUES (4,'cert-user','Local certificate user',4,'91128f0a-df85-47a9-ae1d-5298904dacd5','2026-08-30 21:50:25','2026-08-30 21:50:25');
/*!40000 ALTER TABLE `user_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'lyceumalabang_auth_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-31  0:51:01