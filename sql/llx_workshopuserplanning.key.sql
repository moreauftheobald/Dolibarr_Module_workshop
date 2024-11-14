ALTER TABLE llx_workshopuserplanning ADD UNIQUE INDEX uk_llx_workshopuserplanning_o_o_ent_act (fk_object,object_type,entity,active);
