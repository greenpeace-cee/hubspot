<?php

namespace Civi\HubSpot\Sync;

class Phone extends AbstractSync {

  public function syncInbound() {
    if (empty($this->contactUpdate->civiPayload['phone'])  && empty($this->contactUpdate->hubspotContactId)) {
      // ignore empty email during initial sync
      return;
    }
    // get primary phone in Civi
    $phone = \Civi\Api4\Phone::get(FALSE)
      ->addSelect('id', 'phone_numeric')
      ->addWhere('contact_id', '=', $this->contactUpdate->contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();
    if (!empty($phone['phone_numeric']) && $phone['phone_numeric'] == preg_replace('/[^\d]/', '', $this->contactUpdate->civiPayload['phone'])) {
      // phone in Civi matches numeric value in HubSpot
      return;
    }
    if (empty($this->contactUpdate->civiPayload['phone']) && !empty($phone['id'])) {
      // phone was deleted in HubSpot
      $this->executeAndLogApi(
        'Phone',
        'delete',
        [
          'where' => [
            ['id', '=', $phone['id']],
          ],
        ]
      );
    }
    if (!empty($this->contactUpdate->civiPayload['phone'])) {
      // phone number in Civi differs
      $record = [
        'location_type_id:name' => 'Main',
        'contact_id' => $this->contactUpdate->contactId,
        'phone' => $this->contactUpdate->civiPayload['phone'],
        'is_primary' => TRUE,
      ];
      if (!empty($phone['id'])) {
        // update existing phone number
        $record['id'] = $phone['id'];
      }
      $this->executeAndLogApi('Phone', 'save', ['records' => [$record]]);
    }
  }

  public function syncOutbound() {
    $properties = [];
    $propertyMap = ['phone_primary.phone' => 'phone'];
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
