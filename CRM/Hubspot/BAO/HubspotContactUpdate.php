<?php
use CRM_Hubspot_ExtensionUtil as E;

class CRM_Hubspot_BAO_HubspotContactUpdate extends CRM_Hubspot_DAO_HubspotContactUpdate {

  /**
   * Create a new HubspotContactUpdate based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Hubspot_DAO_HubspotContactUpdate|NULL
   *
  public static function create($params) {
    $className = 'CRM_Hubspot_DAO_HubspotContactUpdate';
    $entityName = 'HubspotContactUpdate';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
