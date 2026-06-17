-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

CREATE TABLE llx_lmdbreferral_event (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_lmdbreferral_link integer NOT NULL,
	event_type varchar(32) NOT NULL,
	fk_propal integer,
	amount_ht double(24,8) DEFAULT 0,
	amount_ttc double(24,8) DEFAULT 0,
	date_event datetime NOT NULL,
	fk_user_author integer,
	note_private text,
	import_key varchar(14)
) ENGINE=innodb;
