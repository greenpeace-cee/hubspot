<?php

namespace Civi\HubSpot\Sync;

class Email extends AbstractSync {

  public function syncInbound() {
    if (empty($this->contactUpdate->civiPayload['email']) && empty($this->contactUpdate->hubspotContactId)) {
      // ignore empty email during initial sync
      return;
    }
    $suppressed = $this->contactUpdate->civiPayload['suppressed'] ?? FALSE;
    // get primary email in Civi
    $email = \Civi\Api4\Email::get(FALSE)
      ->addSelect('id', 'email', 'on_hold')
      ->addWhere('contact_id', '=', $this->contactUpdate->contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();
    if (!empty($email['email']) && $email['email'] == $this->contactUpdate->civiPayload['email'] && $email['on_hold'] == $suppressed) {
      // email in Civi matches value in HubSpot
      return;
    }
    if (empty($this->contactUpdate->civiPayload['email']) && !empty($email['id'])) {
      // email was deleted in HubSpot
      $this->executeAndLogApi(
        'Email',
        'delete',
        [
          'where' => [
            ['id', '=', $email['id']],
          ],
        ]
      );
    }
    // email in Civi differs
    $record = [
      'location_type_id:name' => 'Main',
      'contact_id' => $this->contactUpdate->contactId,
      'email' => $this->contactUpdate->civiPayload['email'],
      'is_primary' => TRUE,
      'on_hold' => $suppressed,
    ];
    if (!empty($email['id'])) {
      // update existing email
      $record['id'] = $email['id'];
    }
    $this->executeAndLogApi('Email', 'save', ['records' => [$record]]);
  }

  public function syncOutbound() {
    $properties = [];
    $propertyMap = ['email_primary.email' => 'email', 'email_primary.on_hold' => 'suppressed'];
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
