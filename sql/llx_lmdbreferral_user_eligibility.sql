-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE llx_lmdbreferral_user_eligibility (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_user integer NOT NULL,
	active tinyint DEFAULT 1 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	date_modification datetime,
	fk_user_author integer,
	fk_user_modif integer
) ENGINE=innodb;
