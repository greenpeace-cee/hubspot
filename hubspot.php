<?php

require_once 'hubspot.civix.php';
require_once __DIR__ . '/vendor/autoload.php';
// phpcs:disable
use Civi\HubSpot\Listener;
use CRM_Hubspot_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function hubspot_civicrm_config(&$config) {
  _hubspot_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function hubspot_civicrm_xmlMenu(&$files) {
  _hubspot_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function hubspot_civicrm_install() {
  _hubspot_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function hubspot_civicrm_postInstall() {
  _hubspot_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function hubspot_civicrm_uninstall() {
  _hubspot_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function hubspot_civicrm_enable() {
  _hubspot_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function hubspot_civicrm_disable() {
  _hubspot_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function hubspot_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _hubspot_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function hubspot_civicrm_managed(&$entities) {
  _hubspot_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function hubspot_civicrm_caseTypes(&$caseTypes) {
  _hubspot_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function hubspot_civicrm_angularModules(&$angularModules) {
  _hubspot_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function hubspot_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _hubspot_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function hubspot_civicrm_entityTypes(&$entityTypes) {
  _hubspot_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function hubspot_civicrm_themes(&$themes) {
  _hubspot_civix_civicrm_themes($themes);
}

function hubspot_civicrm_postCommit($op, $objectName, $objectId, &$objectRef) {
  if (Listener::$enabled && $op != 'view' && in_array($objectName, Listener::OBJECTS)) {
    Listener::queuePush($op, $objectName, $objectId, $objectRef);
  }
}

function _hubspot_is_mergeable_duplicate($contactId) {
  $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
    ->selectRowCount()
    ->addWhere('contact_id', '=', $contactId)
    ->addWhere('is_merge', '=', FALSE)
    ->execute();
  return !($hubspotContact->rowCount > 0);
}

function hubspot_civicrm_merge($type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL) {
  if ($type == 'sqls') {
    if (!_hubspot_is_mergeable_duplicate($otherId)) {
      throw new Exception('Unable to merge contacts: Contact ID ' . $otherId . ' needs to be merged in HubSpot first. Please perform the merge and/or wait for the merge to synchronize before continuing.');
    }
    // remove UPDATE against civicrm_hubspot_contact as it would fail due to
    // the unique index on contact_id
    $data = array_filter($data, function($sql) {
      return strpos($sql, 'UPDATE civicrm_hubspot_contact ') === FALSE;
    });
  }
}

function hubspot_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Contact_Form_Merge') {
    if (!_hubspot_is_mergeable_duplicate($form->_oid)) {
      $errors['_qf_default'] = 'Contact ID ' . $form->_oid . ' needs to be merged in HubSpot first. Please perform the merge and/or wait for the merge to synchronize before continuing.';
    }
  }

}

