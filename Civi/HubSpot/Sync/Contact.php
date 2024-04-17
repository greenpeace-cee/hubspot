<?php

namespace Civi\HubSpot\Sync;

class Contact extends AbstractSync {

  public function syncInbound() {
    $contact = [];
    if (!empty($this->contactUpdate->contactId)) {
      // get existing contact fields
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id', 'first_name', 'last_name', 'formal_title')
        ->addWhere('id', '=', $this->contactUpdate->contactId)
        ->execute()
        ->first();
    }
    $record = [];
    foreach (['first_name', 'last_name', 'formal_title'] as $fieldName) {
      if ((empty($contact[$fieldName]) && !empty($this->contactUpdate->civiPayload[$fieldName])) || (!empty($contact[$fieldName]) && (string) $contact[$fieldName] != (string) $this->contactUpdate->civiPayload[$fieldName])) {
        // value in Civi differs from value in HubSpot payload
        $record[$fieldName] = $this->contactUpdate->civiPayload[$fieldName];
      }
    }
    if (!empty($record) && !empty($contact)) {
      // update existing contact
      $record['id'] = $contact['id'];
    }
    if (empty($contact)) {
      // create new contact
      $record['contact_type:name'] = 'Individual';
    }
    if (!empty($record)) {
      $result = $this->executeAndLogApi('Contact', 'save', ['records' => [$record]]);
      if (!empty($result)) {
        $contact = $result->first();
        $this->contactUpdate->contactId = $contact['id'];
        $this->contactUpdate->hubspotVid = $this->contactUpdate->item['hubspot_vid'];
      }
    }
  }

  public function syncOutbound() {
    $properties = [];
    $propertyMap = ['first_name' => 'firstname', 'last_name' => 'lastname', 'formal_title' => 'title'];
    $action = $this->contactUpdate->isInitialSync ? 'create' : 'update';
    foreach ($propertyMap as $fieldName => $hubspotPropertyName) {
      $isCreate = $action == 'create';
      $valueDiffers = !empty($this->contactUpdate->contactPrefetch[$fieldName]) && $this->contactUpdate->contactPrefetch[$fieldName] != $this->contactUpdate->civiPayload[$fieldName];
      $isUnsetUpdate = $action == 'update' && empty($this->contactUpdate->contactPrefetch[$fieldName]) && !empty($this->contactUpdate->civiPayload[$fieldName]);
      if ($isCreate || $valueDiffers || $isUnsetUpdate) {
        $properties[$hubspotPropertyName] = $this->contactUpdate->contactPrefetch[$fieldName];
      }
    }
    if (count($properties) > 0) {
      $response = $this->sendAndLog($action, $properties);
      if ($action == 'create' && $response instanceof \SevenShores\Hubspot\Http\Response) {
        $contact = $response->getData();
        $this->contactUpdate->hubspotVid = $contact->vid;
        $this->contactUpdate->item['hubspot_vid'] = $contact->vid;
      }
    }
  }

}
