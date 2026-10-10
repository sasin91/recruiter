-- The queue's tables (modules/queue). Re-runnable; names no database.

-- One row per queued job ("run Class::_method later") waiting, running or failed. Deleted once it ran.
-- `parameters` is the job's named parameters as a JSON object, e.g. {"application_id":42}.
CREATE TABLE IF NOT EXISTS `queue_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(32) NOT NULL,
  `method` varchar(150) NOT NULL,
  `parameters` json NOT NULL,
  `unique_key` varchar(191) DEFAULT NULL,
  `available_at` int(11) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reserved_at` int(11) DEFAULT NULL,
  `reserved_by` char(16) DEFAULT NULL,
  `failed_at` int(11) DEFAULT NULL,
  `error_class` varchar(150) DEFAULT NULL,
  `error_message` varchar(1000) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`unique_key`),
  KEY `due` (`queue`, `failed_at`, `reserved_at`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per worker process: is any worker running, and how is it doing.
CREATE TABLE IF NOT EXISTS `queue_workers` (
  `id` char(16) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `process_id` int(11) NOT NULL,
  `queues` varchar(255) NOT NULL,
  `handled` int(10) unsigned NOT NULL DEFAULT 0,
  `failed` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` int(11) NOT NULL,
  `last_seen_at` int(11) NOT NULL,
  `stopped_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
