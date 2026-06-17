-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>

ALTER TABLE llx_lmdbreferral_user_eligibility ADD INDEX idx_lmdbreferral_user_eligibility_entity (entity);
ALTER TABLE llx_lmdbreferral_user_eligibility ADD INDEX idx_lmdbreferral_user_eligibility_fk_user (fk_user);
ALTER TABLE llx_lmdbreferral_user_eligibility ADD INDEX idx_lmdbreferral_user_eligibility_active (active);
ALTER TABLE llx_lmdbreferral_user_eligibility ADD UNIQUE INDEX uk_lmdbreferral_user_eligibility (entity, fk_user);
