-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbreferral_event ADD INDEX idx_lmdbreferral_event_entity (entity);
ALTER TABLE llx_lmdbreferral_event ADD INDEX idx_lmdbreferral_event_fk_link (fk_lmdbreferral_link);
ALTER TABLE llx_lmdbreferral_event ADD INDEX idx_lmdbreferral_event_type (event_type);
ALTER TABLE llx_lmdbreferral_event ADD INDEX idx_lmdbreferral_event_fk_propal (fk_propal);
ALTER TABLE llx_lmdbreferral_event ADD INDEX idx_lmdbreferral_event_date (date_event);
ALTER TABLE llx_lmdbreferral_event ADD UNIQUE INDEX uk_lmdbreferral_event_propal (entity, fk_lmdbreferral_link, event_type, fk_propal);
