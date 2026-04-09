CREATE TRIGGER hubspot_email_update
    AFTER UPDATE ON civicrm_email
    FOR EACH ROW
        INSERT INTO civicrm_value_hubspot_sync (entity_id, sync_date)
            VALUES (NEW.contact_id, NULL)
        ON DUPLICATE KEY UPDATE
            sync_date = NULL
;
