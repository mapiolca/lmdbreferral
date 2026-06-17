-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_entity (entity);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_ref (ref);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_status (status);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_fk_soc_parrain (fk_soc_parrain);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_fk_user_parrain (fk_user_parrain);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_fk_soc_filleul (fk_soc_filleul);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_date_creation (date_creation);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_pair_soc (entity, referrer_type, fk_soc_parrain, fk_soc_filleul, status);
ALTER TABLE llx_lmdbreferral_link ADD INDEX idx_lmdbreferral_link_pair_user (entity, referrer_type, fk_user_parrain, fk_soc_filleul, status);
