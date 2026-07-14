<?php

use Civi\Api4;

trait CRM_Hubspot_UpdateContactSyncTableTrait {

  private static function updateSyncRecord(array $record): void {
    $assignments = [
      'has_changes = 0',
      'last_sync_date = CURRENT_TIMESTAMP',
    ];

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
          $assignments[] = "owned_by = NULLIF(%$i, 0)";
          $params[$i] = [$value ?? 0, 'Integer'];
          break;
        }

        case 'last_sync_failed': {
          $assignments[] = "last_sync_failed = %$i";
          $params[$i] = [(int) $value, 'Integer'];
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
