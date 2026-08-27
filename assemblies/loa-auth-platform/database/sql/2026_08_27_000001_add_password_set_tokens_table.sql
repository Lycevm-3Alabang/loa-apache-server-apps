-- ============================================================================
-- MIGRATION: Add password_set_tokens table
-- Target:     loa_auth (or lyceumalabang_auth_db on production)
-- Date:       2026-08-27
-- Reversible: Yes (run the DOWN section to undo)
-- ============================================================================

-- UP
CREATE TABLE IF NOT EXISTS `password_set_tokens` (
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

-- DOWN (uncomment to rollback)
-- DROP TABLE IF EXISTS `password_set_tokens`;
