DROP TABLE IF EXISTS {SQL_PREFIX}mod_session;
CREATE TABLE {SQL_PREFIX}mod_session (
  ip varchar(38) NOT NULL DEFAULT '',
  attempts tinyint(3) NOT NULL,
  blocked datetime NOT NULL,
  UNIQUE KEY ip (ip)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;