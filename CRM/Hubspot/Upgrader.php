<?php
use CRM_Hubspot_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Hubspot_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001');
    $column_exists = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_hubspot_contact` LIKE 'is_merge';");
    if (!$column_exists) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_hubspot_contact` ADD COLUMN `is_merge` tinyint NOT NULL DEFAULT 0 COMMENT 'Is this record a non-canonical merge?'");
      $logging = new CRM_Logging_Schema();
      $logging->fixSchemaDifferences();
    }
    return TRUE;
  }

  public function upgrade_1002() {
    $this->ctx->log->info('Applying update 1002');
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_hubspot_contact` MODIFY COLUMN `hubspot_portal_id` int unsigned NULL COMMENT 'FK to HubSpotPortal'");
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_hubspot_contact` MODIFY COLUMN `hubspot_vid` decimal(20,0) NULL COMMENT 'Unique identifier of contact in HubSpot. DECIMAL because core does not support BIGINT.'");
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_hubspot_contact_update` MODIFY COLUMN `hubspot_portal_id` int unsigned NULL COMMENT 'FK to HubSpotPortal'");
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_hubspot_contact_update` MODIFY COLUMN `hubspot_vid` decimal(20,0) NULL COMMENT 'Unique identifier of contact in HubSpot. DECIMAL because core does not support BIGINT.'");
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

}
