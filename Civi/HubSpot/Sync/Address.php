<?php

namespace Civi\HubSpot\Sync;

class Address extends AbstractSync {

  public function syncInbound() {
    $fieldMap = [
      'street_address',
      'city',
      'postal_code',
      'country_id:name',
    ];
    $addressEmpty = TRUE;
    foreach ($fieldMap as $field) {
      if ($field != 'country_id:name' && !empty($this->contactUpdate->civiPayload[$field])) {
        $addressEmpty = FALSE;
      }
    }
    if ($addressEmpty) {
      return;
    }
    // get primary address in Civi
    $address = \Civi\Api4\Address::get(FALSE)
      ->addSelect('id', 'street_address', 'city', 'postal_code', 'country_id:name')
      ->addWhere('contact_id', '=', $this->contactUpdate->contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();
    $record = [];
    foreach ($fieldMap as $field) {
      if (empty($this->contactUpdate->civiPayload[$field]) || empty($address[$field]) || $address[$field] != $this->contactUpdate->civiPayload[$field]) {
        $record[$field] = $this->contactUpdate->civiPayload[$field];
      }
    }
    if (count($record) == 0) {
      return;
    }
    $record['location_type_id:name'] = 'Main';
    $record['is_primary'] = TRUE;
    $record['contact_id'] = $this->contactUpdate->contactId;
    if (!empty($address['id'])) {
      // update existing address number
      $record['id'] = $address['id'];
    }
    $this->executeAndLogApi('Address', 'save', ['records' => [$record]]);
  }

  public function syncOutbound() {
    $properties = [];
    $propertyMap = [
      'address_primary.street_address' => 'address',
      'address_primary.city' => 'city',
      'address_primary.postal_code' => 'zip',
      'address_primary.country_id:name' => 'country',
    ];
    foreach ($propertyMap as $fieldName => $hubspotPropertyName) {
      $valueDiffers = !empty($this->contactUpdate->contactPrefetch[$fieldName]) && $this->contactUpdate->contactPrefetch[$fieldName] != $this->contactUpdate->payload[$hubspotPropertyName];
      $isUnsetUpdate = !$this->contactUpdate->isInitialSync && empty($this->contactUpdate->contactPrefetch[$fieldName]) && !empty($this->contactUpdate->payload[$hubspotPropertyName]);
      if ($valueDiffers || $isUnsetUpdate) {
        $properties[$hubspotPropertyName] = $this->contactUpdate->contactPrefetch[$fieldName];
      }
    }
    if (count($properties) > 0) {
      $this->sendAndLog('update', $properties);
    }
  }

}
