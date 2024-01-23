<?php

namespace Civi\HubSpot\Sync;

class GroupContact extends AbstractSync {

  public function syncInbound() {
    if (empty($this->contactUpdate->hubspotPortal['config']['subscription_map'])) {
      // no subscription mapping has been set
      return NULL;
    }
    // fetch current GroupContact values for synced subscriptions
    $groupNames = array_values($this->contactUpdate->hubspotPortal['config']['subscription_map']);
    $groupContacts = \Civi\Api4\GroupContact::get(FALSE)
      ->addSelect('id', 'group_id:name', 'status')
      ->addWhere('group_id:name', 'IN', $groupNames)
      ->addWhere('contact_id', '=', $this->contactUpdate->contactId)
      ->execute()
      ->indexBy('group_id:name');
    $records = [];
    // iterate over mapped subscriptions
    foreach ($this->contactUpdate->hubspotPortal['config']['subscription_map'] as $fieldName => $groupName) {
      if ($this->contactUpdate->payload[$fieldName] == 'true' && (empty($groupContacts[$groupName]) || $groupContacts[$groupName]['status'] != 'Added')) {
        // subscription is set in HubSpot, but GroupContact either doesn't exist
        // or is status=Removed => add GroupContact
        $record = [
          'group_id:name' => $groupName,
          'contact_id' => $this->contactUpdate->contactId,
          'status' => 'Added',
        ];
        if (!empty($groupContacts[$groupName]['id'])) {
          $record['id'] = $groupContacts[$groupName]['id'];
        }
        $records[] = $record;
      }
      if ((empty($this->contactUpdate->payload[$fieldName]) || $this->contactUpdate->payload[$fieldName] == 'false') && !empty($groupContacts[$groupName]) && $groupContacts[$groupName]['status'] == 'Added') {
        // subscription is not set in HubSpot, but GroupContact is status=Added
        // => set status=Removed
        $records[] = [
          'id' => $groupContacts[$groupName]['id'],
          'group_id:name' => $groupName,
          'contact_id' => $this->contactUpdate->contactId,
          'status' => 'Removed',
        ];
      }
    }
    if (count($records) > 0) {
      $this->executeAndLogApi('GroupContact', 'save', ['records' => $records]);
    }
  }

  public function syncOutbound() {
    if (empty($this->contactUpdate->hubspotPortal['config']['subscription_map'])) {
      // no subscription mapping has been set
      return;
    }
    $properties = [];
    foreach ($this->contactUpdate->hubspotPortal['config']['subscription_map'] as $fieldName => $groupName) {
      $valueDiffers = !empty($this->contactUpdate->contactPrefetch['groups'][$groupName]['status']) && ($this->contactUpdate->contactPrefetch['groups'][$groupName]['status'] == 'Added') != ($this->contactUpdate->payload[$fieldName] == 'true');
      $groupRemovedAfterInitialSync = !$this->contactUpdate->isInitialSync && (empty($this->contactUpdate->contactPrefetch['groups'][$groupName]['status']) || $this->contactUpdate->contactPrefetch['groups'][$groupName]['status'] == 'Removed') && ($this->contactUpdate->payload[$fieldName] == 'true');
      if ($valueDiffers || $groupRemovedAfterInitialSync) {
        $properties[$fieldName] = ($this->contactUpdate->contactPrefetch['groups'][$groupName]['status'] ?? 'Removed') == 'Added';
      }
    }
    if (count($properties) > 0) {
      $this->sendAndLog('update', $properties);
    }
  }

}
