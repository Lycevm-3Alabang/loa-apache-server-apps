-- ============================================================
-- Drop All Tables — LOA Auth Platform + Cert Platform
-- ============================================================
-- Explicitly drops FK constraints before each table.
-- Run each section against the correct database.
-- ============================================================

-- ─── Auth Platform: lyceumalabang_auth_db ───────────────────

USE `lyceumalabang_auth_db`;

-- child tables first (depend on users, tenants, user_groups, permissions)

ALTER TABLE `activations`
  DROP FOREIGN KEY `activations_user_id_foreign`;

ALTER TABLE `login_attempts`
  DROP FOREIGN KEY `login_attempts_user_id_foreign`;

ALTER TABLE `password_reset_tokens`
  DROP FOREIGN KEY `password_reset_tokens_user_id_foreign`;

ALTER TABLE `password_set_tokens`
  DROP FOREIGN KEY `password_set_tokens_user_id_foreign`;

ALTER TABLE `refresh_tokens`
  DROP FOREIGN KEY `refresh_tokens_replaced_by_foreign`,
  DROP FOREIGN KEY `refresh_tokens_user_id_foreign`;

ALTER TABLE `user_claim_overrides`
  DROP FOREIGN KEY `user_claim_overrides_user_id_foreign`;

ALTER TABLE `user_permission`
  DROP FOREIGN KEY `user_permission_permission_id_foreign`,
  DROP FOREIGN KEY `user_permission_tenant_id_foreign`,
  DROP FOREIGN KEY `user_permission_user_id_foreign`;

ALTER TABLE `user_tenants`
  DROP FOREIGN KEY `user_tenants_tenant_id_foreign`,
  DROP FOREIGN KEY `user_tenants_user_id_foreign`;

ALTER TABLE `user_user_group`
  DROP FOREIGN KEY `user_user_group_user_group_id_foreign`,
  DROP FOREIGN KEY `user_user_group_user_id_foreign`;

ALTER TABLE `user_group_permission`
  DROP FOREIGN KEY `user_group_permission_permission_id_foreign`,
  DROP FOREIGN KEY `user_group_permission_tenant_id_foreign`,
  DROP FOREIGN KEY `user_group_permission_user_group_id_foreign`;

ALTER TABLE `group_claims`
  DROP FOREIGN KEY `group_claims_group_id_foreign`;

ALTER TABLE `tenant_api_keys`
  DROP FOREIGN KEY `tenant_api_keys_created_by_foreign`,
  DROP FOREIGN KEY `tenant_api_keys_tenant_id_foreign`;

ALTER TABLE `tenant_app_endpoints`
  DROP FOREIGN KEY `tenant_app_endpoints_tenant_id_foreign`;

ALTER TABLE `tenant_endpoint_grants`
  DROP FOREIGN KEY `tenant_endpoint_grants_group_id_foreign`,
  DROP FOREIGN KEY `tenant_endpoint_grants_tenant_id_foreign`;

ALTER TABLE `tenant_endpoint_overrides`
  DROP FOREIGN KEY `tenant_endpoint_overrides_tenant_id_foreign`,
  DROP FOREIGN KEY `tenant_endpoint_overrides_user_id_foreign`;

-- now drop all tables in any order

DROP TABLE IF EXISTS `activations`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `group_claims`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `migrations`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `password_set_tokens`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `refresh_tokens`;
DROP TABLE IF EXISTS `route_policies`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `tenant_api_keys`;
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

-- ─── Cert Platform: lyceumalabang_e_cert_db ─────────────────

USE `lyceumalabang_e_cert_db`;

-- child tables first (depend on organizations, events, certificates, certificate_templates)

ALTER TABLE `audit_logs`
  DROP FOREIGN KEY `audit_logs_organization_id_foreign`;

ALTER TABLE `certificate_emails`
  DROP FOREIGN KEY `certificate_emails_certificate_id_foreign`;

ALTER TABLE `certificate_sequences`
  DROP FOREIGN KEY `certificate_sequences_organization_id_foreign`;

ALTER TABLE `certificate_templates`
  DROP FOREIGN KEY `certificate_templates_organization_id_foreign`;

ALTER TABLE `certificates`
  DROP FOREIGN KEY `certificates_event_id_foreign`,
  DROP FOREIGN KEY `certificates_organization_id_foreign`,
  DROP FOREIGN KEY `certificates_template_id_foreign`;

ALTER TABLE `event_attendees`
  DROP FOREIGN KEY `event_attendees_certificate_id_foreign`,
  DROP FOREIGN KEY `event_attendees_event_id_foreign`,
  DROP FOREIGN KEY `event_attendees_organization_id_foreign`;

ALTER TABLE `events`
  DROP FOREIGN KEY `events_email_template_id_foreign`,
  DROP FOREIGN KEY `events_organization_id_foreign`,
  DROP FOREIGN KEY `events_template_id_foreign`;

-- now drop all tables in any order

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `certificate_emails`;
DROP TABLE IF EXISTS `certificate_sequences`;
DROP TABLE IF EXISTS `certificate_templates`;
DROP TABLE IF EXISTS `certificates`;
DROP TABLE IF EXISTS `event_attendees`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `migrations`;
DROP TABLE IF EXISTS `organizations`;
