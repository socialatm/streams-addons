CREATE TABLE IF NOT EXISTS `faces_encoding` (
	`encoding_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `encoding_hash` char(191) NOT NULL DEFAULT '',
	`finder` int(10) unsigned NOT NULL DEFAULT 0,				-- type of face detection and prediction
	`channel_id` int(10) unsigned NOT NULL DEFAULT 0,			-- channel id of user
	`id` int(10) unsigned NOT NULL DEFAULT 0,				-- file id from table attach
        `updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `encoding` TEXT NOT NULL DEFAULT '',					-- file id from table attach
        `confidence` float(8,7) NOT NULL DEFAULT 0.0,
        `location` varchar(191) NOT NULL DEFAULT '',	
        `location_css` varchar(191) NOT NULL DEFAULT '',				-- coodinates of the face in image
	`encoding_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `encoding_time` BIGINT NOT NULL DEFAULT 0,
        `person_verified` int(10) unsigned NOT NULL DEFAULT 0 ,		-- coodinates of the face in image
	`verified_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',		-- identified by the user manually
        `distance` float(17,16) NOT NULL DEFAULT 0.0,
        `person_recognized` int(10) unsigned NOT NULL DEFAULT 0 ,		-- identified by the face recognition by comparing face encodings
	`recognized_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `recognized_time` BIGINT NOT NULL DEFAULT 0,
        `person_marked_unknown` tinyint(4) NOT NULL DEFAULT 0 ,			-- identified as (still) unknown by the user manually
        `marked_ignore` tinyint(4) NOT NULL DEFAULT 0 ,				-- marked as ignored by the user
        `no_faces` tinyint(4) NOT NULL DEFAULT 0 ,				-- image with not face detected by the script
        `error` tinyint(4) NOT NULL DEFAULT 0 , 
	PRIMARY KEY `encoding_id` (`encoding_id`),
	KEY `encoding_hash` (`encoding_hash`),
	KEY `updated` (`updated`),
	KEY `finder` (`finder`),
	KEY `channel_id` (`channel_id`),
	KEY `id` (`id`),
	KEY `confidence` (`confidence`),
	KEY `encoding_time` (`encoding_time`),
	KEY `person_verified` (`person_verified`),
	KEY `verified_updated` (`verified_updated`),
	KEY `person_recognized` (`person_recognized`),
	KEY `distance` (`distance`),
	KEY `recognized_time` (`recognized_time`),
	KEY `person_marked_unknown` (`person_marked_unknown`),
	KEY `marked_ignore` (`marked_ignore`),
	KEY `no_faces` (`no_faces`),
	KEY `error` (`error`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faces_person` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `hash` char(191) NOT NULL DEFAULT '',
        `name` char(191) NOT NULL DEFAULT '',
        `xchan_hash` char(191) NOT NULL DEFAULT '',
	`channel_id` int(10) unsigned NOT NULL DEFAULT 0,	-- identified by the face recognition by comparing face encodings
	`updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `allow_cid` mediumtext NOT NULL DEFAULT '',
        `allow_gid` mediumtext NOT NULL DEFAULT '',
        `deny_cid` mediumtext NOT NULL DEFAULT '',
        `deny_gid` mediumtext NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `hash` (`hash`),
	KEY `name` (`name`),
	KEY `xchan_hash` (`xchan_hash`),
	KEY `channel_id` (`channel_id`),
	KEY `updated` (`updated`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faces_proc` (					-- use to communicate between PHP and Python (server and script)
	`proc_id` int(10) unsigned NOT NULL DEFAULT 0,
	`created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	`updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	`finished` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `running` tinyint(4) NOT NULL DEFAULT 0,
        `summary` TEXT NOT NULL DEFAULT '',
	PRIMARY KEY `proc_id` (`proc_id`),
	KEY `created` (`created`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;

