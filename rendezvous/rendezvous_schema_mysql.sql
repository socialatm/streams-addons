
--
-- Table structure for table `rendezvous_groups`
--

CREATE TABLE IF NOT EXISTS `rendezvous_groups` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`aid` int(10) unsigned NOT NULL DEFAULT '0',
	`uid` int(10) unsigned NOT NULL DEFAULT '0',
	`size` int(10) unsigned NOT NULL DEFAULT '0',
	`stale` int(10) unsigned NOT NULL DEFAULT '0',
	`deleted` int(1) unsigned NOT NULL DEFAULT '0',
	`guid` char(255) NOT NULL DEFAULT '',
	`name` char(255) NOT NULL DEFAULT '',
	`created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	`edited` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	`expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	`allow_cid` mediumtext NOT NULL,
	`allow_gid` mediumtext NOT NULL,
	`deny_cid` mediumtext NOT NULL,
	`deny_gid` mediumtext NOT NULL,
	PRIMARY KEY (`id`),
	KEY `aid` (`aid`),
	KEY `uid` (`uid`),
	KEY `size` (`size`),
	KEY `stale` (`stale`),
	KEY `deleted` (`deleted`),
	KEY `guid` (`guid`),
	KEY `name` (`name`),
	KEY `created` (`created`),
	KEY `edited` (`edited`),
	KEY `expires` (`expires`),
	FULLTEXT KEY `allow_cid` (`allow_cid`),
	FULLTEXT KEY `allow_gid` (`allow_gid`),
	FULLTEXT KEY `deny_cid` (`deny_cid`),
	FULLTEXT KEY `deny_gid` (`deny_gid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------


--
-- Table structure for table `rendezvous_members`
--

CREATE TABLE IF NOT EXISTS `rendezvous_members` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`rid` int(10) unsigned NOT NULL DEFAULT '0',
	`mid` char(255) NOT NULL DEFAULT '',
	`name` char(255) NOT NULL DEFAULT '',
	`secret` char(255) NOT NULL DEFAULT '',
	`deleted` int(1) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `rid` (`rid`),
	KEY `mid` (`mid`),
	KEY `name` (`name`),
	KEY `secret` (`secret`),
	KEY `deleted` (`deleted`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
