ALTER TABLE llx_workshop_tag ADD UNIQUE INDEX uk_workshop_tag_code (code);
ALTER TABLE llx_workshop_tag ADD INDEX idx_workshop_tag_active (active);
