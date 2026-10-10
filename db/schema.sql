-- Recruiter database schema.
--
-- The whole schema in one file, applied once to an empty database. It does not
-- name a database, so it loads into whichever one the client selects:
--   bin/import-db.sh / bin/import-db.ps1   (local dev; drops and recreates `recruiter` first)
--   Trongate.cloud (recruiter.trongate.dev): the app's own tc_p{app id} database, via bin/import-db.php
-- Change it in place while there are no customers to migrate; once there are,
-- add numbered migrations next to it.
--
-- Model: a company has a roster of staff (company_members, login level 2) and
-- posts job_posts. A candidate (1:1 with trongate_users, login level 3) sends
-- job_applications. Both sides map what they ask for / have onto the shared
-- taxonomy (terms) through *_terms rows, and match_scores rank applications
-- against a post.
--
-- Conventions: InnoDB, utf8mb4, int(11) ids, unix int timestamps, real foreign
-- keys. Enum-like columns are short varchar codes validated in PHP. No json
-- columns; display extras go in the one key/value table, `attributes`.

-- ---------------------------------------------------------------------------
-- Trongate framework: users, levels, admin login, tokens, login module
-- (same tables as modules/setup/sql/setup.sql; the setup wizard creates the admin)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `trongate_user_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_title` varchar(125) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `trongate_user_levels` (`id`, `level_title`) VALUES
(1, 'admin'),
(2, 'company_member'),
(3, 'candidate');

CREATE TABLE IF NOT EXISTS `trongate_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) DEFAULT NULL,
  `user_level_id` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `trongate_administrators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(65) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(60) DEFAULT NULL,
  `trongate_user_id` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `last_failed_attempt` int(11) NOT NULL DEFAULT 0,
  `login_blocked_until` int(11) NOT NULL DEFAULT 0,
  `failed_login_ip` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `trongate_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(125) DEFAULT NULL,
  `user_id` int(11) DEFAULT 0,
  `expiry_date` int(11) DEFAULT NULL,
  `code` varchar(3) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_table` varchar(255) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `attempted_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `target_table` (`target_table`(191)),
  KEY `identifier` (`identifier`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_table` varchar(255) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_date` int(11) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Taxonomy
-- ---------------------------------------------

-- One language-independent concept per row; names live in term_labels.
-- kind: title, role, skill_category, skill, requirement, education,
-- responsibility_area, company_type, culture. Skills form a tree through
-- parent_id: skill_category -> skill group -> skill.
CREATE TABLE IF NOT EXISTS `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kind` varchar(24) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `slug` varchar(191) NOT NULL,
  `definition` text DEFAULT NULL,
  `source` varchar(24) NOT NULL DEFAULT 'manual',
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `merged_into_id` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kind_slug` (`kind`, `slug`),
  KEY `parent_id` (`parent_id`),
  KEY `merged_into_id` (`merged_into_id`),
  CONSTRAINT `terms_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `terms_merged_into_fk` FOREIGN KEY (`merged_into_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Danish/English names and synonyms; the exact/synonym lookup reads (normalised, lang).
-- Every `normalised` column is utf8mb4_bin: the value is already NFC-lowercased,
-- and general_ci would treat å as a ("maling" = "måling").
CREATE TABLE IF NOT EXISTS `term_labels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `lang` char(2) NOT NULL,
  `label` varchar(191) NOT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `term_lang_normalised` (`term_id`, `lang`, `normalised`),
  KEY `normalised_lang` (`normalised`, `lang`),
  CONSTRAINT `term_labels_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Role <-> skill many-to-many and other links between terms.
-- relation: title_role, role_skill, related. confidence is the embedding cosine
-- for derived links (title_role), NULL where it is unknown (role_skill from the legacy data);
-- step 3 weights a weak link down.
CREATE TABLE IF NOT EXISTS `term_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `related_term_id` int(11) NOT NULL,
  `relation` varchar(24) NOT NULL,
  `confidence` decimal(4,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `term_related_relation` (`term_id`, `related_term_id`, `relation`),
  KEY `related_term_id` (`related_term_id`),
  CONSTRAINT `term_relations_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `term_relations_related_fk` FOREIGN KEY (`related_term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Companies and their staff
-- ---------------------------------------------

-- Companies sign up themselves. contact_email is the address candidates see;
-- active = 0 shuts the company's staff out.
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `cvr_number` varchar(8) DEFAULT NULL,
  `company_type_term_id` int(11) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cvr_number` (`cvr_number`),
  KEY `company_type_term_id` (`company_type_term_id`),
  CONSTRAINT `companies_company_type_fk` FOREIGN KEY (`company_type_term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before company sign-up get its columns.
ALTER TABLE `companies`
  ADD COLUMN IF NOT EXISTS `contact_email` varchar(255) DEFAULT NULL AFTER `company_type_term_id`,
  ADD COLUMN IF NOT EXISTS `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `contact_email`;

-- The company's roster. Login target for user level 2 (see config/login.php).
-- Emails are unique across all rosters: staff are encouraged to use company emails.
-- role: owner (also manages members, the AI key and settings) or recruiter.
-- An invited member has no password yet, only invite_token: the link they
-- open to choose one (company/join/{token}), cleared once they have.
CREATE TABLE IF NOT EXISTS `company_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `trongate_user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(60) DEFAULT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'recruiter',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `invite_token` char(32) DEFAULT NULL,
  `invited_at` int(11) DEFAULT NULL,
  `num_logins` int(11) NOT NULL DEFAULT 0,
  `last_login` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trongate_user_id` (`trongate_user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `invite_token` (`invite_token`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_members_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_members_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before member invites get their columns.
ALTER TABLE `company_members`
  ADD COLUMN IF NOT EXISTS `invite_token` char(32) DEFAULT NULL AFTER `active`,
  ADD COLUMN IF NOT EXISTS `invited_at` int(11) DEFAULT NULL AFTER `invite_token`,
  ADD UNIQUE KEY IF NOT EXISTS `invite_token` (`invite_token`);

-- ---------------------------------------------
-- Candidates (1:1 with trongate_users). Login target for user level 3.
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trongate_user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(60) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `num_logins` int(11) NOT NULL DEFAULT 0,
  `last_login` int(11) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `open_to_work` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trongate_user_id` (`trongate_user_id`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `candidates_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before open_to_work get it: whether the candidate is
-- looking for work (companies may invite them, and they get match suggestions).
ALTER TABLE `candidates`
  ADD COLUMN IF NOT EXISTS `open_to_work` tinyint(1) NOT NULL DEFAULT 0 AFTER `postal_code`;

-- ---------------------------------------------
-- Job posts
-- ---------------------------------------------

-- status: draft, active, paused (the link says it isn't taking applications),
-- closed, archived. public_token is the post's link (/jobs/{token}), set on
-- first publish. version goes up when a live post's requirements change.
-- Experience and education asked for are job_post_terms rows (kind
-- experience / education), not columns: "kok or 5 years" is one OR-group.
CREATE TABLE IF NOT EXISTS `job_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `level` varchar(24) DEFAULT NULL,
  `workplace_flexibility` varchar(16) DEFAULT NULL,
  `work_hours` varchar(16) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'DK',
  `language` char(2) NOT NULL DEFAULT 'da',
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `raw_text` mediumtext NOT NULL,
  `pitch` mediumtext DEFAULT NULL,
  `extractor` varchar(32) NOT NULL DEFAULT 'manual',
  `external_id` varchar(64) DEFAULT NULL,
  `public_token` char(12) DEFAULT NULL,
  `accepts_suggested` tinyint(1) NOT NULL DEFAULT 1,
  `max_suggested_applications` smallint(6) NOT NULL DEFAULT 20,
  `published_at` int(11) DEFAULT NULL,
  `paused_at` int(11) DEFAULT NULL,
  `closed_at` int(11) DEFAULT NULL,
  `archived_at` int(11) DEFAULT NULL,
  `closes_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `company_status` (`company_id`, `status`),
  KEY `status_published` (`status`, `published_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `job_posts_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `job_posts_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `company_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before job posts get their columns (and lose the two the
-- requirements list replaced).
ALTER TABLE `job_posts`
  ADD COLUMN IF NOT EXISTS `pitch` mediumtext DEFAULT NULL AFTER `raw_text`,
  ADD COLUMN IF NOT EXISTS `public_token` char(12) DEFAULT NULL AFTER `external_id`,
  ADD COLUMN IF NOT EXISTS `accepts_suggested` tinyint(1) NOT NULL DEFAULT 1 AFTER `public_token`,
  ADD COLUMN IF NOT EXISTS `max_suggested_applications` smallint(6) NOT NULL DEFAULT 20 AFTER `accepts_suggested`,
  ADD COLUMN IF NOT EXISTS `paused_at` int(11) DEFAULT NULL AFTER `published_at`,
  ADD COLUMN IF NOT EXISTS `closed_at` int(11) DEFAULT NULL AFTER `paused_at`,
  ADD COLUMN IF NOT EXISTS `archived_at` int(11) DEFAULT NULL AFTER `closed_at`,
  ADD UNIQUE KEY IF NOT EXISTS `public_token` (`public_token`),
  DROP COLUMN IF EXISTS `min_experience_years`,
  DROP COLUMN IF EXISTS `education_level`;

-- Everything the ranker matches on. term_id stays NULL until the phrase is mapped.
-- match_method: exact, synonym, fuzzy (typo-tolerant), embedding, manual, none.
-- The post's requirements list is its rows of kind skill, soft_skill (shown,
-- never scored), education, certificate, experience and language. Rows sharing
-- an alt_group are alternatives (OR) and share is_required; min_years is for
-- experience rows, min_level the level asked for ("fluent", "master's
-- degree"). Also kind title (one row) and responsibility_area. english is the
-- row's text in English: the Laya summary is English.
CREATE TABLE IF NOT EXISTS `job_post_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_post_id` int(11) NOT NULL,
  `kind` varchar(24) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `raw_text` varchar(255) NOT NULL,
  `english` varchar(255) DEFAULT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_highlighted` tinyint(1) NOT NULL DEFAULT 0,
  `alt_group` smallint(6) DEFAULT NULL,
  `min_years` tinyint(3) unsigned DEFAULT NULL,
  `min_level` varchar(24) DEFAULT NULL,
  `match_method` varchar(16) NOT NULL DEFAULT 'none',
  `similarity` decimal(4,3) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `job_post_kind` (`job_post_id`, `kind`),
  KEY `job_post_alt_group` (`job_post_id`, `alt_group`),
  KEY `term_id` (`term_id`),
  KEY `kind_normalised` (`kind`, `normalised`),
  CONSTRAINT `job_post_terms_job_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_post_terms_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before the requirements list get its columns.
ALTER TABLE `job_post_terms`
  ADD COLUMN IF NOT EXISTS `english` varchar(255) DEFAULT NULL AFTER `raw_text`,
  ADD COLUMN IF NOT EXISTS `alt_group` smallint(6) DEFAULT NULL AFTER `is_highlighted`,
  ADD COLUMN IF NOT EXISTS `min_years` tinyint(3) unsigned DEFAULT NULL AFTER `alt_group`,
  ADD COLUMN IF NOT EXISTS `min_level` varchar(24) DEFAULT NULL AFTER `min_years`,
  ADD KEY IF NOT EXISTS `job_post_alt_group` (`job_post_id`, `alt_group`);

-- When each member last opened a post's applicants: "new" is per member.
CREATE TABLE IF NOT EXISTS `job_post_member_views` (
  `job_post_id` int(11) NOT NULL,
  `company_member_id` int(11) NOT NULL,
  `last_viewed_at` int(11) NOT NULL,
  PRIMARY KEY (`job_post_id`, `company_member_id`),
  KEY `company_member_id` (`company_member_id`),
  CONSTRAINT `job_post_member_views_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_post_member_views_member_fk` FOREIGN KEY (`company_member_id`) REFERENCES `company_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Job applications (mirror the job side)
-- ---------------------------------------------

-- One per (post, candidate). raw_text is the CV as sent and the
-- job_application_terms rows what was read from it, both snapshotted from
-- candidate_resumes at that version, so a later CV doesn't change
-- what the company saw. Experience and education are term rows (kind
-- experience: years; education: level), as on the job side.
-- source: apply (from the post's link), invite, suggested.
-- status: in_review, rejected, withdrawn, hired (and later held, skipped,
-- expired, invited for match cards and invitations).
-- shortlisted_at / bookmarked_at are per-application toggles, so two
-- recruiters never overwrite each other's lists.
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_post_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `current_title` varchar(255) DEFAULT NULL,
  `level` varchar(24) DEFAULT NULL,
  `workplace_flexibility` varchar(16) DEFAULT NULL,
  `work_hours` varchar(16) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `language` char(2) NOT NULL DEFAULT 'da',
  `source` varchar(16) NOT NULL DEFAULT 'form',
  `raw_text` mediumtext DEFAULT NULL,
  `cover_letter` mediumtext DEFAULT NULL,
  `extractor` varchar(32) NOT NULL DEFAULT 'manual',
  `candidate_resume_version` int(11) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `submitted_at` int(11) DEFAULT NULL,
  `shortlisted_at` int(11) DEFAULT NULL,
  `bookmarked_at` int(11) DEFAULT NULL,
  `rejected_at` int(11) DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `withdrawn_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_post_candidate` (`job_post_id`, `candidate_id`),
  KEY `job_post_status` (`job_post_id`, `status`),
  KEY `candidate_id` (`candidate_id`),
  CONSTRAINT `job_applications_job_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`),
  CONSTRAINT `job_applications_candidate_fk` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `job_application_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_application_id` int(11) NOT NULL,
  `kind` varchar(24) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `raw_text` varchar(255) NOT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `years` tinyint(3) unsigned DEFAULT NULL,
  `level` varchar(24) DEFAULT NULL,
  `is_highlighted` tinyint(1) NOT NULL DEFAULT 0,
  `match_method` varchar(16) NOT NULL DEFAULT 'none',
  `similarity` decimal(4,3) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `job_application_kind` (`job_application_id`, `kind`),
  KEY `term_id` (`term_id`),
  KEY `kind_normalised` (`kind`, `normalised`),
  CONSTRAINT `job_application_terms_application_fk` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_application_terms_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before applying get the application columns (and lose the
-- two the requirements list replaced).
ALTER TABLE `job_applications`
  ADD COLUMN IF NOT EXISTS `cover_letter` mediumtext DEFAULT NULL AFTER `raw_text`,
  ADD COLUMN IF NOT EXISTS `candidate_resume_version` int(11) DEFAULT NULL AFTER `extractor`,
  ADD COLUMN IF NOT EXISTS `bookmarked_at` int(11) DEFAULT NULL AFTER `shortlisted_at`,
  ADD COLUMN IF NOT EXISTS `withdrawn_at` int(11) DEFAULT NULL AFTER `reject_reason`,
  DROP COLUMN IF EXISTS `experience_years`,
  DROP COLUMN IF EXISTS `education_level`;
ALTER TABLE `job_application_terms`
  ADD COLUMN IF NOT EXISTS `level` varchar(24) DEFAULT NULL AFTER `years`;

-- The candidate's résumé (D6, "candidate_profiles" in the plan): the CV they
-- gave, read once, then snapshotted into each application. version goes up
-- each time the CV is replaced.
CREATE TABLE IF NOT EXISTS `candidate_resumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `cv_name` varchar(255) NOT NULL DEFAULT '',
  `cv_text` mediumtext NOT NULL,
  `current_title` varchar(255) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `experience_years` tinyint(3) unsigned DEFAULT NULL,
  `language` char(2) NOT NULL DEFAULT 'da',
  `extractor` varchar(32) NOT NULL DEFAULT 'manual',
  `confirmed_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidate_id` (`candidate_id`),
  CONSTRAINT `candidate_resumes_candidate_fk` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The résumé's rows, the same shape as job_application_terms. Kinds:
-- title, skill, language, certificate, education, responsibility_area, and
-- one experience row with the total years (term_id NULL).
CREATE TABLE IF NOT EXISTS `candidate_resume_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_resume_id` int(11) NOT NULL,
  `kind` varchar(24) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `raw_text` varchar(255) NOT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `years` tinyint(3) unsigned DEFAULT NULL,
  `level` varchar(24) DEFAULT NULL,
  `match_method` varchar(16) NOT NULL DEFAULT 'none',
  `similarity` decimal(4,3) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `candidate_resume_kind` (`candidate_resume_id`, `kind`),
  KEY `term_id` (`term_id`),
  KEY `kind_normalised` (`kind`, `normalised`),
  CONSTRAINT `candidate_resume_terms_resume_fk` FOREIGN KEY (`candidate_resume_id`) REFERENCES `candidate_resumes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_resume_terms_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- The one key/value table: display extras and the long tail only.
-- Anything the ranker scores is a column or a *_terms row instead.
-- owner_type: job_post, job_application, company, candidate. No FK on
-- owner_id, so the owning module deletes its own attributes.
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS `attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(16) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `attr_key` varchar(64) NOT NULL,
  `value` text NOT NULL,
  `lang` char(2) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner_type`, `owner_id`),
  KEY `attr_key` (`attr_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Review queue: one row per distinct unknown phrase.
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS `unmatched_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kind` varchar(24) NOT NULL,
  `lang` char(2) NOT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `example_raw_text` varchar(255) NOT NULL,
  `occurrences` int(11) NOT NULL DEFAULT 1,
  `suggested_term_id` int(11) DEFAULT NULL,
  `suggested_similarity` decimal(4,3) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `resolved_term_id` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kind_lang_normalised` (`kind`, `lang`, `normalised`),
  KEY `status` (`status`),
  KEY `suggested_term_id` (`suggested_term_id`),
  KEY `resolved_term_id` (`resolved_term_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `unmatched_terms_suggested_fk` FOREIGN KEY (`suggested_term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unmatched_terms_resolved_fk` FOREIGN KEY (`resolved_term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unmatched_terms_resolved_by_fk` FOREIGN KEY (`resolved_by`) REFERENCES `trongate_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Scoring (replaces the legacy rankingDetails blob)
-- ---------------------------------------------

-- One row per (application, job post version, ranking version); doubles as the LLM verdict cache.
CREATE TABLE IF NOT EXISTS `match_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_post_id` int(11) NOT NULL,
  `job_application_id` int(11) NOT NULL,
  `job_post_version` int(11) NOT NULL,
  `ranking_version` varchar(16) NOT NULL,
  `deterministic_score` decimal(5,4) NOT NULL,
  `llm_score` decimal(5,4) DEFAULT NULL,
  `combined_score` decimal(5,4) NOT NULL,
  `tag` varchar(8) NOT NULL,
  `laya_requirements_probability` decimal(5,4) DEFAULT NULL,
  `laya_fit_expected` decimal(4,3) DEFAULT NULL,
  `laya_choice` varchar(16) DEFAULT NULL,
  `needs_human` tinyint(1) NOT NULL DEFAULT 0,
  `final_rank` smallint(6) DEFAULT NULL,
  `computed_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_versions` (`job_application_id`, `job_post_version`, `ranking_version`),
  KEY `job_post_combined` (`job_post_id`, `combined_score`),
  KEY `job_post_rank` (`job_post_id`, `final_rank`),
  CONSTRAINT `match_scores_job_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_scores_application_fk` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per criterion or question: the "why" behind a score.
CREATE TABLE IF NOT EXISTS `match_score_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_score_id` int(11) NOT NULL,
  `stage` varchar(16) NOT NULL,
  `criterion` varchar(32) NOT NULL,
  `job_post_term_id` int(11) DEFAULT NULL,
  `job_application_term_id` int(11) DEFAULT NULL,
  `rule` varchar(16) NOT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `rank` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `probability` decimal(5,4) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_score_stage` (`match_score_id`, `stage`),
  KEY `job_post_term_id` (`job_post_term_id`),
  KEY `job_application_term_id` (`job_application_term_id`),
  CONSTRAINT `match_score_details_score_fk` FOREIGN KEY (`match_score_id`) REFERENCES `match_scores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_score_details_job_post_term_fk` FOREIGN KEY (`job_post_term_id`) REFERENCES `job_post_terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `match_score_details_application_term_fk` FOREIGN KEY (`job_application_term_id`) REFERENCES `job_application_terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- CV checker (/cv_match): a person's own CV against job posts they found
-- ---------------------------------------------

-- What was done to an application, by whom and when: shortlist, unshortlist,
-- bookmark, unbookmark, reject, unreject, apply, withdraw, rescore.
-- company_member_id for staff, candidate_id for the candidate. The rest is
-- the context at that moment: the status before and after, the post version,
-- the score on screen (match_score_id) and its rank then (final_rank, which
-- changes later), where it was done (source: matchmaker, apply_page,
-- my_applications, swipe_card, system) and why (reason, a short code such
-- as not_qualified or not_this_company, plus a free-text note).
CREATE TABLE IF NOT EXISTS `job_application_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_application_id` int(11) NOT NULL,
  `company_member_id` int(11) DEFAULT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `action` varchar(24) NOT NULL,
  `from_status` varchar(16) DEFAULT NULL,
  `to_status` varchar(16) NOT NULL,
  `job_post_version` int(11) NOT NULL,
  `match_score_id` int(11) DEFAULT NULL,
  `final_rank` smallint(6) DEFAULT NULL,
  `source` varchar(24) NOT NULL,
  `reason` varchar(32) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `application_created` (`job_application_id`, `created_at`),
  KEY `company_member_id` (`company_member_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `match_score_id` (`match_score_id`),
  KEY `action_reason` (`action`, `reason`),
  CONSTRAINT `job_application_actions_application_fk` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_application_actions_member_fk` FOREIGN KEY (`company_member_id`) REFERENCES `company_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_application_actions_candidate_fk` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_application_actions_score_fk` FOREIGN KEY (`match_score_id`) REFERENCES `match_scores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases that got job_application_actions before it had context.
ALTER TABLE `job_application_actions`
  ADD COLUMN IF NOT EXISTS `from_status` varchar(16) DEFAULT NULL AFTER `action`,
  ADD COLUMN IF NOT EXISTS `to_status` varchar(16) NOT NULL DEFAULT '' AFTER `from_status`,
  ADD COLUMN IF NOT EXISTS `job_post_version` int(11) NOT NULL DEFAULT 0 AFTER `to_status`,
  ADD COLUMN IF NOT EXISTS `match_score_id` int(11) DEFAULT NULL AFTER `job_post_version`,
  ADD COLUMN IF NOT EXISTS `final_rank` smallint(6) DEFAULT NULL AFTER `match_score_id`,
  ADD COLUMN IF NOT EXISTS `source` varchar(24) NOT NULL DEFAULT '' AFTER `final_rank`,
  ADD COLUMN IF NOT EXISTS `reason` varchar(32) DEFAULT NULL AFTER `source`,
  ADD KEY IF NOT EXISTS `match_score_id` (`match_score_id`),
  ADD KEY IF NOT EXISTS `action_reason` (`action`, `reason`),
  ADD CONSTRAINT `job_application_actions_score_fk` FOREIGN KEY IF NOT EXISTS (`match_score_id`) REFERENCES `match_scores` (`id`) ON DELETE SET NULL;

-- One row per match the CV checker scores. Standalone on purpose: the post and
-- CV are the person's own pasted text, not job_posts/candidates rows.
-- trongate_user_id is whoever was logged in (NULL in dev, where the page is open).
-- score is the legacy match index (points / max_points); tag: top, good, medium, poor.
-- application_text is the generated job application, once asked for;
-- resume_text the CV tailored to this post, once asked for.
CREATE TABLE IF NOT EXISTS `cv_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trongate_user_id` int(11) DEFAULT NULL,
  `job_title` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL DEFAULT '',
  `job_url` varchar(2048) DEFAULT NULL,
  `job_text` mediumtext NOT NULL,
  `cv_name` varchar(255) NOT NULL DEFAULT '',
  `cv_text` mediumtext NOT NULL,
  `score` decimal(5,4) NOT NULL,
  `points` smallint(6) NOT NULL,
  `max_points` smallint(6) NOT NULL,
  `tag` varchar(8) NOT NULL,
  `application_text` mediumtext DEFAULT NULL,
  `application_written_at` int(11) DEFAULT NULL,
  `resume_text` mediumtext DEFAULT NULL,
  `resume_written_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_created` (`trongate_user_id`, `created_at`),
  CONSTRAINT `cv_matches_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Databases made before the tailored résumé get its columns.
ALTER TABLE `cv_matches`
  ADD COLUMN IF NOT EXISTS `resume_text` mediumtext DEFAULT NULL AFTER `application_written_at`,
  ADD COLUMN IF NOT EXISTS `resume_written_at` int(11) DEFAULT NULL AFTER `resume_text`;

-- One row per scored criterion item: the "why" behind a cv_matches score.
-- criterion: requirements, skills, title, responsibilities (the ranker's groups).
-- verdict: met, partial, missing. decided_by: exact, synonym, fuzzy, related,
-- years (taxonomy or rule) or llm (the model judged it).
CREATE TABLE IF NOT EXISTS `cv_match_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_match_id` int(11) NOT NULL,
  `criterion` varchar(24) NOT NULL,
  `text` varchar(500) NOT NULL,
  `verdict` varchar(8) NOT NULL,
  `decided_by` varchar(16) NOT NULL,
  `reason` varchar(500) NOT NULL DEFAULT '',
  `evidence` varchar(500) NOT NULL DEFAULT '',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cv_match_id` (`cv_match_id`, `sort_order`),
  CONSTRAINT `cv_match_items_match_fk` FOREIGN KEY (`cv_match_id`) REFERENCES `cv_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The tailored résumé as fields (Tailored_resume), shaped like sasin91.xyz's
-- content/cv.toml, so its PDF is laid out from them. cv_matches.resume_text
-- keeps the plain-text version made from these fields. Matches tailored
-- before these tables have resume_text only.

-- The header, one row per match with a structured résumé. location is free
-- text as the CV gives it ("Slagelse, 4200"); links are " · "-joined; intro
-- holds the profile paragraphs separated by a blank line; education_note is
-- the optional line under Education. The *_heading columns are the section
-- headings in the post's language ("Erfaring", "Kompetencer").
CREATE TABLE IF NOT EXISTS `cv_match_resumes` (
  `cv_match_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(64) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `links` varchar(1000) NOT NULL DEFAULT '',
  `intro` text NOT NULL,
  `education_note` varchar(500) NOT NULL DEFAULT '',
  `experience_heading` varchar(64) NOT NULL DEFAULT 'Experience',
  `skills_heading` varchar(64) NOT NULL DEFAULT 'Skills',
  `education_heading` varchar(64) NOT NULL DEFAULT 'Education',
  `languages_heading` varchar(64) NOT NULL DEFAULT 'Languages',
  PRIMARY KEY (`cv_match_id`),
  CONSTRAINT `cv_match_resumes_match_fk` FOREIGN KEY (`cv_match_id`) REFERENCES `cv_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A role (section experience) or a school (section education), in order.
-- organisation: the employer or school. starts/ends as the résumé writes them
-- ("Januar 2017", "nu"). summary: the line under the dates ("Job & candidate
-- matchmaking platform"); note closes the entry, set smaller.
CREATE TABLE IF NOT EXISTS `cv_match_resume_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_match_id` int(11) NOT NULL,
  `section` varchar(12) NOT NULL,
  `title` varchar(255) NOT NULL,
  `organisation` varchar(255) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  `starts` varchar(32) NOT NULL DEFAULT '',
  `ends` varchar(32) NOT NULL DEFAULT '',
  `summary` varchar(500) NOT NULL DEFAULT '',
  `note` varchar(1000) NOT NULL DEFAULT '',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cv_match_id` (`cv_match_id`, `section`, `sort_order`),
  CONSTRAINT `cv_match_resume_entries_match_fk` FOREIGN KEY (`cv_match_id`) REFERENCES `cv_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One line of a list: an entry's bullet (kind bullet, entry_id set) or one of
-- the résumé's skills or languages (kind skill / language, entry_id NULL).
CREATE TABLE IF NOT EXISTS `cv_match_resume_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_match_id` int(11) NOT NULL,
  `entry_id` int(11) DEFAULT NULL,
  `kind` varchar(12) NOT NULL,
  `text` varchar(1000) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cv_match_id` (`cv_match_id`, `kind`, `sort_order`),
  KEY `entry_id` (`entry_id`, `sort_order`),
  CONSTRAINT `cv_match_resume_lines_match_fk` FOREIGN KEY (`cv_match_id`) REFERENCES `cv_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cv_match_resume_lines_entry_fk` FOREIGN KEY (`entry_id`) REFERENCES `cv_match_resume_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Users' own language model API keys
-- ---------------------------------------------

-- One key per user: the AI features (CV match extraction, judging, written
-- applications) run on it. provider: openai or anthropic (the llm module's
-- adapters); model is empty for the provider's default. api_key_encrypted is
-- base64 of nonce + XChaCha20-Poly1305 ciphertext under a key derived from the
-- server's LLM_KEY_SECRET, bound to trongate_user_id; the plain key is never
-- stored. key_hint is the key's last 4 characters, to show which key is saved.
CREATE TABLE IF NOT EXISTS `user_llm_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trongate_user_id` int(11) NOT NULL,
  `provider` varchar(16) NOT NULL,
  `model` varchar(64) NOT NULL DEFAULT '',
  `api_key_encrypted` varchar(1024) NOT NULL,
  `key_hint` varchar(4) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trongate_user_id` (`trongate_user_id`),
  CONSTRAINT `user_llm_keys_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One key per company: the company side's AI features (reading job posts,
-- judging applications) run on it, as user_llm_keys does for a candidate.
-- Encrypted the same way, bound to company_id instead of a user.
CREATE TABLE IF NOT EXISTS `company_llm_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `provider` varchar(16) NOT NULL,
  `model` varchar(64) NOT NULL DEFAULT '',
  `api_key_encrypted` varchar(1024) NOT NULL,
  `key_hint` varchar(4) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`),
  CONSTRAINT `company_llm_keys_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Messenger (modules/messenger): the queue. Same as modules/messenger/sql/messenger.sql.
-- ---------------------------------------------------------------------------

-- One row per queued call ("run module/_method later") waiting, running or failed. Deleted once it ran.
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transport` varchar(32) NOT NULL,
  `target` varchar(150) NOT NULL,
  `dedupe_key` varchar(191) DEFAULT NULL,
  `available_at` int(11) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `delivered_at` int(11) DEFAULT NULL,
  `delivered_to` char(16) DEFAULT NULL,
  `failed_at` int(11) DEFAULT NULL,
  `error_class` varchar(150) DEFAULT NULL,
  `error_message` varchar(1000) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dedupe_key` (`dedupe_key`),
  KEY `due` (`transport`, `failed_at`, `delivered_at`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The call's arguments in order, one row each (no json column).
CREATE TABLE IF NOT EXISTS `messenger_message_arguments` (
  `messenger_message_id` bigint(20) unsigned NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `value_type` varchar(6) NOT NULL,
  `value` mediumtext DEFAULT NULL,
  PRIMARY KEY (`messenger_message_id`, `position`),
  CONSTRAINT `messenger_message_arguments_message_fk` FOREIGN KEY (`messenger_message_id`) REFERENCES `messenger_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per worker process: is anything consuming, and how is it doing.
CREATE TABLE IF NOT EXISTS `messenger_workers` (
  `id` char(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `process_id` int(11) NOT NULL,
  `transports` varchar(255) NOT NULL,
  `handled` int(10) unsigned NOT NULL DEFAULT 0,
  `failed` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` int(11) NOT NULL,
  `last_seen_at` int(11) NOT NULL,
  `stopped_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
