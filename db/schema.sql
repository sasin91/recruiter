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

CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `cvr_number` varchar(8) DEFAULT NULL,
  `company_type_term_id` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cvr_number` (`cvr_number`),
  KEY `company_type_term_id` (`company_type_term_id`),
  CONSTRAINT `companies_company_type_fk` FOREIGN KEY (`company_type_term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The company's roster. Login target for user level 2 (see config/login.php).
-- Emails are unique across all rosters: staff are encouraged to use company emails.
CREATE TABLE IF NOT EXISTS `company_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `trongate_user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(60) DEFAULT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'recruiter',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `num_logins` int(11) NOT NULL DEFAULT 0,
  `last_login` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trongate_user_id` (`trongate_user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_members_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_members_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trongate_user_id` (`trongate_user_id`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `candidates_user_fk` FOREIGN KEY (`trongate_user_id`) REFERENCES `trongate_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Job posts
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS `job_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `level` varchar(24) DEFAULT NULL,
  `min_experience_years` tinyint(3) unsigned DEFAULT NULL,
  `workplace_flexibility` varchar(16) DEFAULT NULL,
  `work_hours` varchar(16) DEFAULT NULL,
  `education_level` varchar(24) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'DK',
  `language` char(2) NOT NULL DEFAULT 'da',
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `raw_text` mediumtext NOT NULL,
  `extractor` varchar(32) NOT NULL DEFAULT 'manual',
  `external_id` varchar(64) DEFAULT NULL,
  `published_at` int(11) DEFAULT NULL,
  `closes_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `company_status` (`company_id`, `status`),
  KEY `status_published` (`status`, `published_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `job_posts_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `job_posts_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `company_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Everything the ranker matches on. term_id stays NULL until the phrase is mapped.
-- match_method: exact, synonym, fuzzy (typo-tolerant), embedding, manual, none.
CREATE TABLE IF NOT EXISTS `job_post_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_post_id` int(11) NOT NULL,
  `kind` varchar(24) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `raw_text` varchar(255) NOT NULL,
  `normalised` varchar(191) COLLATE utf8mb4_bin NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_highlighted` tinyint(1) NOT NULL DEFAULT 0,
  `match_method` varchar(16) NOT NULL DEFAULT 'none',
  `similarity` decimal(4,3) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `job_post_kind` (`job_post_id`, `kind`),
  KEY `term_id` (`term_id`),
  KEY `kind_normalised` (`kind`, `normalised`),
  CONSTRAINT `job_post_terms_job_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_post_terms_term_fk` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------
-- Job applications (mirror the job side)
-- ---------------------------------------------

CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_post_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `current_title` varchar(255) DEFAULT NULL,
  `level` varchar(24) DEFAULT NULL,
  `experience_years` tinyint(3) unsigned DEFAULT NULL,
  `workplace_flexibility` varchar(16) DEFAULT NULL,
  `work_hours` varchar(16) DEFAULT NULL,
  `education_level` varchar(24) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `language` char(2) NOT NULL DEFAULT 'da',
  `source` varchar(16) NOT NULL DEFAULT 'form',
  `raw_text` mediumtext DEFAULT NULL,
  `extractor` varchar(32) NOT NULL DEFAULT 'manual',
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `submitted_at` int(11) DEFAULT NULL,
  `shortlisted_at` int(11) DEFAULT NULL,
  `rejected_at` int(11) DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
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
