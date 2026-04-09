CREATE TRIGGER hubspot_contact_update
    AFTER UPDATE ON civicrm_contact
    FOR EACH ROW
        INSERT INTO civicrm_value_hubspot_sync (entity_id, sync_date)
            VALUES (NEW.id, NULL)
        ON DUPLICATE KEY UPDATE
            sync_date = NULL
;
