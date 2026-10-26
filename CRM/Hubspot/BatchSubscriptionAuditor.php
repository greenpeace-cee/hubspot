<?php

use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchSubscriptionAuditor extends CRM_Hubspot_BatchProcessor {

  use CRM_Hubspot_AuditSyncStateTrait;

  public static function processBatch(CRM_Queue_TaskContext $_ctx, array $batch): bool {
    $batch = self::skipChangedSinceQueued($batch);

    // Local subscription changes after the last subscription sync (as of queueing) are either pending or newer
    // than the queued statuses. All items of a batch come from the same audit run, so they share that timestamp.
    if (!empty($batch)) {
      $changed_contact_ids = self::contactsWithSubscriptionChangesSince(
        array_column($batch, 'civicrm_id'),
        reset($batch)['last_subscription_sync']
      );

      $batch = array_filter($batch, fn ($item) => !in_array($item['civicrm_id'], $changed_contact_ids));
    }

    if (empty($batch)) {
      return TRUE;
    }

    $emails = array_values(array_unique(array_column($batch, 'email')));

    $response = ApiClient::batchGetSubscriptionStatuses($emails);

    if (!in_array($response->getStatusCode(), [200, 207])) {
      return FALSE;
    }

    $response_body = json_decode((string) $response->getBody(), TRUE);
    $hubspot_statuses = [];

    foreach ($response_body['results'] ?? [] as $result) {
      $email = strtolower($result['subscriberIdString']);

      foreach ($result['statuses'] as $status) {
        $hubspot_statuses[$email][$status['subscriptionId']] = $status['status'];
      }
    }

    foreach ($batch as $item) {
      $diff = [];

      foreach ($item['statuses'] as $subscription_id => $civicrm_status) {
        $hubspot_status = $hubspot_statuses[strtolower($item['email'])][$subscription_id] ?? 'NOT_SPECIFIED';

        if ($civicrm_status !== $hubspot_status) {
          $diff[$subscription_id] = ['civicrm' => $civicrm_status, 'hubspot' => $hubspot_status];
        }
      }

      if (empty($diff)) {
        continue;
      }

      Civi::log('hubspot-audit')->warning('Subscriptions differ from HubSpot', [
        'civicrm_id' => $item['civicrm_id'],
        'email'      => $item['email'],
        'diff'       => $diff,
      ]);
    }

    return TRUE;
  }

}
