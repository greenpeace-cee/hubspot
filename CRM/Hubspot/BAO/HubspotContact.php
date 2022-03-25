<?php
use CRM_Hubspot_ExtensionUtil as E;

class CRM_Hubspot_BAO_HubspotContact extends CRM_Hubspot_DAO_HubspotContact {

  /**
   * Create a new HubspotContact based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Hubspot_DAO_HubspotContact|NULL
   *
  public static function create($params) {
    $className = 'CRM_Hubspot_DAO_HubspotContact';
    $entityName = 'HubspotContact';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
