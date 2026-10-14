<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_BatchContactCreator as BatchContactCreator;
use CRM_Hubspot_BatchContactUpdater as BatchContactUpdater;
use CRM_Hubspot_SyncGuard;
use Exception;
use Generator;

/**
 * Sync modified contacts to HubSpot
 */
class SyncContacts extends Api4\Generic\DAOGetAction {

  use \CRM_Hubspot_LoadHubspotAccountTrait;

  const CREATE_QUEUE = 'hubspot-sync-create-contacts';
  const UPDATE_QUEUE = 'hubspot-sync-update-contacts';

  public function _run(Api4\Generic\Result $result) {
    $result['scheduledForCreate'] = 0;
    $result['scheduledForUpdate'] = 0;

    CRM_Hubspot_SyncGuard::run('sync-contacts', [self::CREATE_QUEUE, self::UPDATE_QUEUE], fn () => $this->sync($result));
  }

  private function sync(Api4\Generic\Result $result): void {
    $contact_creator = new BatchContactCreator([ 'queue_name' => self::CREATE_QUEUE ]);
    $contact_updater = new BatchContactUpdater([ 'queue_name' => self::UPDATE_QUEUE ]);

    foreach ($this->selectContactsForSync() as $contact) {
      $contact['civicrm_id'] = $contact['id'];
      unset($contact['id']);

      $hubspot_id = $contact['hubspot_id'];
      unset($contact['hubspot_id']);

      $contact['owned_by'] = self::hubspotAccount()['owner_identifier'];
      $contact['unique_civicrm_id'] = $contact['owned_by'] . '-' . $contact['civicrm_id'];

      if (empty($hubspot_id)) {
        $contact_creator->add([ 'properties' => $contact ]);
        $result['scheduledForCreate']++;
      } else {
        $contact_updater->add([
          'id'         => $hubspot_id,
          'properties' => $contact,
        ]);

        $result['scheduledForUpdate']++;
      }
    }

    $contact_creator->flush();
    $contact_updater->flush();
  }

  private function selectContactsForSync(): Generator {
    $contact_query = [
      'select'           => $this->select,
      'where'            => $this->where,
      'join'             => $this->join,
      'orderBy'          => [ 'id' => 'ASC' ],
      'limit'            => 100,
      'checkPermissions' => FALSE,
    ];

    $select_hubspot_id = null;

    foreach ($contact_query['select'] as $value) {
      if (preg_match('/(^| AS )hubspot_id$/', $value)) {
        $select_hubspot_id = $value;
        break;
      }
    }

    if (is_null($select_hubspot_id)) {
      throw new Exception("Invalid sync query: Missing property 'hubspot_id' in select clause");
    }

    $contact_id_offset = 0;

    while (true) {
      $contact_query['where'][] = ['id', '>', $contact_id_offset];
      $result = civicrm_api4('Contact', 'get', $contact_query);
      array_pop($contact_query['where']);

      if ($result->countFetched() < 1) return;

      $contact_id_offset = $result->last()['id'];

      foreach ($result as $contact) yield $contact;
    }
  }

}
