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

}
