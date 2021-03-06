DROP TABLE IF EXISTS {SQL_PREFIX}mod_contact;
CREATE TABLE {SQL_PREFIX}mod_contact (
  id smallint(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  clean_name varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  email varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY id (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2 ;

INSERT INTO {SQL_PREFIX}mod_contact (id, name, email, clean_name) VALUES
(1, 'Contact Us', 'tangocms@example.com', 'contact-us');

DROP TABLE IF EXISTS {SQL_PREFIX}mod_contact_fields;
CREATE TABLE {SQL_PREFIX}mod_contact_fields (
  id smallint(4) NOT NULL AUTO_INCREMENT,
  form_id smallint(4) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `options` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  required int(1) NOT NULL DEFAULT '1',
  `order` smallint(4) NOT NULL DEFAULT '2',
  UNIQUE KEY id (id),
  KEY form_id (form_id),
  KEY `order` (`order`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=3 ;

INSERT INTO {SQL_PREFIX}mod_contact_fields (id, form_id, name, type, options, required, `order`) VALUES
(1, 1, 'Your Name', 'textbox', '', 1, 2),
(2, 1, 'Your Message', 'textarea', '', 1, 2);