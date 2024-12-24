-- --------------------------------------------------------
-- 
-- This is the MySQL 5.6 database schema 
-- using InnoDB for storage engine and foreign keys connections
-- required minimum is amavisd-new version 2.7.0
-- 
-- --------------------------------------------------------


DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT '7',
  `policy_id` int(10) UNSIGNED NOT NULL DEFAULT '1',
  `email` varbinary(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `aliases` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL COMMENT 'comma separated list',
  `digest` int(1) DEFAULT NULL COMMENT 'null or 1 for daily',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wblist`;
CREATE TABLE IF NOT EXISTS `wblist` (
  `rid` int(10) UNSIGNED NOT NULL,
  `sid` int(10) UNSIGNED NOT NULL,
  `wb` varchar(10) NOT NULL,
  PRIMARY KEY (`rid`,`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mailaddr`;
CREATE TABLE IF NOT EXISTS `mailaddr` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT '7',
  `email` varbinary(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `policy`;
CREATE TABLE IF NOT EXISTS `policy` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(32) DEFAULT NULL,
  `virus_lover` char(1) DEFAULT NULL,
  `spam_lover` char(1) DEFAULT NULL,
  `unchecked_lover` char(1) DEFAULT NULL,
  `banned_files_lover` char(1) DEFAULT NULL,
  `bad_header_lover` char(1) DEFAULT NULL,
  `bypass_virus_checks` char(1) DEFAULT NULL,
  `bypass_spam_checks` char(1) DEFAULT NULL,
  `bypass_banned_checks` char(1) DEFAULT NULL,
  `bypass_header_checks` char(1) DEFAULT NULL,
  `spam_modifies_subj` char(1) DEFAULT NULL,
  `virus_quarantine_to` varchar(64) DEFAULT NULL,
  `spam_quarantine_to` varchar(64) DEFAULT NULL,
  `banned_quarantine_to` varchar(64) DEFAULT NULL,
  `unchecked_quarantine_to` varchar(64) DEFAULT NULL,
  `bad_header_quarantine_to` varchar(64) DEFAULT NULL,
  `clean_quarantine_to` varchar(64) DEFAULT NULL,
  `archive_quarantine_to` varchar(64) DEFAULT NULL,
  `spam_tag_level` float DEFAULT NULL,
  `spam_tag2_level` float DEFAULT NULL,
  `spam_tag3_level` float DEFAULT NULL,
  `spam_kill_level` float DEFAULT NULL,
  `spam_dsn_cutoff_level` float DEFAULT NULL,
  `spam_quarantine_cutoff_level` float DEFAULT NULL,
  `addr_extension_virus` varchar(64) DEFAULT NULL,
  `addr_extension_spam` varchar(64) DEFAULT NULL,
  `addr_extension_banned` varchar(64) DEFAULT NULL,
  `addr_extension_bad_header` varchar(64) DEFAULT NULL,
  `warnvirusrecip` char(1) DEFAULT NULL,
  `warnbannedrecip` char(1) DEFAULT NULL,
  `warnbadhrecip` char(1) DEFAULT NULL,
  `newvirus_admin` varchar(64) DEFAULT NULL,
  `virus_admin` varchar(64) DEFAULT NULL,
  `banned_admin` varchar(64) DEFAULT NULL,
  `bad_header_admin` varchar(64) DEFAULT NULL,
  `spam_admin` varchar(64) DEFAULT NULL,
  `spam_subject_tag` varchar(64) DEFAULT NULL,
  `spam_subject_tag2` varchar(64) DEFAULT NULL,
  `spam_subject_tag3` varchar(64) DEFAULT NULL,
  `message_size_limit` int(11) DEFAULT NULL,
  `banned_rulenames` varchar(64) DEFAULT NULL,
  `disclaimer_options` varchar(64) DEFAULT NULL,
  `forward_method` varchar(64) DEFAULT NULL,
  `sa_userconf` varchar(64) DEFAULT NULL,
  `sa_username` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
--
-- R/W part of the dataset (optional)
--
--   May reside in the same or in a separate database as lookups database;
--   REQUIRES SUPPORT FOR TRANSACTIONS; specified in @storage_sql_dsn
--   The whole MySQL table structure is set to InnoDB to abide the above
--
--  Wayne Smith adds: When using MySQL with InnoDB one might want to
--  increase buffer size for both pool and log, and might also want
--  to change flush settings for a little better performance. Example:
--    innodb_buffer_pool_size = 384M
--    innodb_log_buffer_size = 8M
--    innodb_flush_log_at_trx_commit = 0
--  The big performance increase is the first two, the third just helps with
--  lowering disk activity. Consider also adjusting the key_buffer_size.
--
-- --------------------------------------------------------

DROP TABLE IF EXISTS `maddr`;
CREATE TABLE IF NOT EXISTS `maddr` (
  `partition_tag` int(11) DEFAULT '0',
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varbinary(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `part_email` (`partition_tag`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `msgrcpt`;
CREATE TABLE IF NOT EXISTS `msgrcpt` (
  `partition_tag` int(11) NOT NULL DEFAULT '0',
  `mail_id` varbinary(16) NOT NULL,
  `rseqnum` int(11) NOT NULL DEFAULT '0',
  `rid` bigint(20) UNSIGNED NOT NULL,
  `is_local` char(1) NOT NULL DEFAULT '',
  `content` char(1) NOT NULL DEFAULT '',
  `ds` char(1) NOT NULL,
  `rs` char(1) NOT NULL COMMENT ' release status: Viewed /Released/Pending/Deleted',
  `bl` char(1) DEFAULT '',
  `wl` char(1) DEFAULT '',
  `bspam_level` float DEFAULT NULL,
  `smtp_resp` varchar(255) DEFAULT '',
  PRIMARY KEY (`partition_tag`,`mail_id`,`rseqnum`),
  KEY `msgrcpt_idx_mail_id` (`mail_id`),
  KEY `msgrcpt_idx_rid` (`rid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `msgs`;
CREATE TABLE IF NOT EXISTS `msgs` (
  `partition_tag` int(11) NOT NULL DEFAULT '0',
  `mail_id` varbinary(16) NOT NULL,
  `secret_id` varbinary(16) DEFAULT '',
  `am_id` varchar(20) NOT NULL,
  `time_num` int(10) UNSIGNED NOT NULL,
  `time_iso` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sid` bigint(20) UNSIGNED NOT NULL,
  `policy` varchar(255) DEFAULT '',
  `client_addr` varchar(255) DEFAULT '',
  `size` int(10) UNSIGNED NOT NULL,
  `originating` char(1) NOT NULL DEFAULT '',
  `content` char(1) DEFAULT NULL,
  `quar_type` char(1) DEFAULT NULL,
  `quar_loc` varbinary(255) DEFAULT '',
  `dsn_sent` char(1) DEFAULT NULL,
  `spam_level` float DEFAULT NULL,
  `message_id` varchar(255) DEFAULT '',
  `from_addr` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '',
  `subject` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '',
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`partition_tag`,`mail_id`),
  KEY `msgs_idx_sid` (`sid`),
  KEY `msgs_idx_mess_id` (`message_id`),
  KEY `msgs_idx_time_num` (`time_num`),
  KEY `msgs_idx_mail_id` (`mail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `quarantine`;
CREATE TABLE IF NOT EXISTS `quarantine` (
  `partition_tag` int(11) NOT NULL DEFAULT '0',
  `mail_id` varbinary(16) NOT NULL,
  `chunk_ind` int(10) UNSIGNED NOT NULL,
  `mail_text` blob NOT NULL,
  PRIMARY KEY (`partition_tag`,`mail_id`,`chunk_ind`),
  KEY `message` (`mail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
--
-- Foreign keys
--
-- --------------------------------------------------------

ALTER TABLE `msgrcpt`
  ADD CONSTRAINT `mail_id` FOREIGN KEY (`mail_id`) REFERENCES `msgs` (`mail_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipient_id` FOREIGN KEY (`rid`) REFERENCES `maddr` (`id`) ON DELETE RESTRICT;

ALTER TABLE `msgs`
  ADD CONSTRAINT `sender` FOREIGN KEY (`sid`) REFERENCES `maddr` (`id`) ON DELETE RESTRICT;

ALTER TABLE `quarantine`
  ADD CONSTRAINT `message` FOREIGN KEY (`mail_id`) REFERENCES `msgs` (`mail_id`) ON DELETE CASCADE;

