<?php

use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchContactAuditor extends CRM_Hubspot_BatchProcessor {

  use CRM_Hubspot_AuditSyncStateTrait;

  /**
   * HubSpot returns every property as a string (or null if unset), and emails in lower case.
   */
  private static function normalize(string $property, $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    $value = (string) ($value ?? '');

    return $property === 'email' ? mb_strtolower($value) : $value;
  }

  public static function processBatch(CRM_Queue_TaskContext $_ctx, array $batch): bool {
    $batch = self::skipChangedSinceQueued($batch);

    if (empty($batch)) {
      return TRUE;
    }

    $properties = array_keys(array_merge([], ...array_column($batch, 'properties')));
    $hubspot_ids = array_values(array_unique(array_column($batch, 'id')));

    $response = ApiClient::batchGetContacts($hubspot_ids, $properties);

    // 207: some contacts were not found
    if (!in_array($response->getStatusCode(), [200, 207])) {
      return FALSE;
    }

    $response_body = json_decode((string) $response->getBody(), TRUE);
    $hubspot_contacts = array_column($response_body['results'] ?? [], 'properties', 'id');

    foreach ($batch as $item) {
      $context = [
        'civicrm_id' => $item['civicrm_id'],
        'hubspot_id' => $item['id'],
      ];

      if (!isset($hubspot_contacts[$item['id']])) {
        Civi::log('hubspot-audit')->warning('Contact not found in HubSpot', $context);
        continue;
      }

      $diff = [];

      foreach ($item['properties'] as $property => $civicrm_value) {
        $hubspot_value = $hubspot_contacts[$item['id']][$property] ?? NULL;

        if (self::normalize($property, $civicrm_value) !== self::normalize($property, $hubspot_value)) {
          $diff[$property] = ['civicrm' => $civicrm_value, 'hubspot' => $hubspot_value];
        }
      }

      if (empty($diff)) {
        continue;
      }

      Civi::log('hubspot-audit')->warning('Contact differs from HubSpot', $context + ['diff' => $diff]);
    }

    return TRUE;
  }

}
