-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE llx_lmdbreferral_link (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128),
	referrer_type varchar(8) NOT NULL,
	fk_soc_parrain integer,
	fk_user_parrain integer,
	fk_soc_filleul integer NOT NULL,
	status integer DEFAULT 1 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	date_modification datetime,
	date_annulation datetime,
	fk_user_author integer,
	fk_user_modif integer,
	fk_user_cancel integer,
	note_private text,
	model_pdf varchar(255),
	last_main_doc varchar(255),
	import_key varchar(14)
) ENGINE=innodb;
