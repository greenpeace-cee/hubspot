<?php

use Civi\Api4;

trait CRM_Hubspot_UpdateContactSyncTableTrait {

  /**
   * The HubSpot ID is kept after deletion and merging, so check the sync status as well.
   */
  private static function hasActiveHubspotContact(?array $contact): bool {
    return !empty($contact['hubspot_sync.hubspot_id'])
      && !in_array($contact['hubspot_sync.sync_status'], ['deleted', 'merged'], TRUE);
  }

  /**
   * Contacts can change after a batch was queued. Keep only items whose contact
   * still exists, isn't deleted and matches $where.
   */
  private static function filterBatchContacts(array $batch, array $where = []): array {
    $contact_ids = Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->setWhere($where)
      ->addWhere('id', 'IN', array_map(fn ($item) => $item['properties']['civicrm_id'], $batch))
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->column('id');

    return array_values(array_filter(
      $batch,
      fn ($item) => in_array((int) $item['properties']['civicrm_id'], $contact_ids, TRUE)
    ));
  }

  private static function updateSyncRecord(array $record): void {
    $assignments = ['last_sync_date = CURRENT_TIMESTAMP'];
    $params = [];
    $i = 0;

    foreach ($record as $column => $value) {
      $i++;

      switch ($column) {
        case 'hubspot_id': {
          $assignments[] = "hubspot_id = NULLIF(%$i, '')";
          $params[$i] = [$value ?? '', 'String'];
          break;
        }

        case 'owned_by': {
          $assignments[] = "owned_by = NULLIF(%$i, '')";
          $params[$i] = [$value ?? '', 'String'];
          break;
        }

        case 'sync_status': {
          $assignments[] = "sync_status = NULLIF(%$i, '')";
          $params[$i] = [$value ?? '', 'String'];
          break;
        }

        case 'last_sync_payload': {
          $assignments[] = "last_sync_payload = NULLIF(%$i, '')";
          $params[$i] = [empty($value) ? '' : json_encode($value), 'String'];
          break;
        }
      }
    }

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_value_hubspot_sync" .
      " SET " . implode(', ', $assignments) .
      " WHERE entity_id = " . (int) $record['entity_id'],
      $params
    );
  }

}
