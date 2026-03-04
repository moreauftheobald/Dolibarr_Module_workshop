ALTER TABLE llx_workshop_planning ADD UNIQUE INDEX uk_workshop_planning (fk_object, object_type, entity);
