<?php

use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchSubscriptionUpdater extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    $batch = self::removeUnchangedStatuses($batch);

    if (empty($batch)) return TRUE;

    return ApiClient::batchUpdateSubscriptionStatus($batch)->getStatusCode() === 200;
  }

  /**
   * Drop updates for subscriptions that already have the requested status in HubSpot.
   * This saves the write, and the next sync from reading back an event for it.
   */
  private static function removeUnchangedStatuses(array $batch): array {
    try {
      $statuses_response = ApiClient::batchGetSubscriptionStatuses(
        array_values(array_unique(array_column($batch, 'subscriberIdString')))
      );
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
      // Sending everything is better than sending nothing
      Civi::log('hubspot-sync')->warning('Could not read subscription statuses: ' . $e->getMessage());

      return $batch;
    }

    $current_statuses = [];

    foreach (json_decode((string) $statuses_response->getBody(), TRUE)['results'] ?? [] as $subscriber) {
      foreach ($subscriber['statuses'] ?? [] as $status) {
        $current_statuses[strtolower($subscriber['subscriberIdString'])][$status['subscriptionId']] = $status['status'];
      }
    }

    return array_values(array_filter(
      $batch,
      fn ($update) => ($current_statuses[strtolower($update['subscriberIdString'])][$update['subscriptionId']] ?? NULL) !== $update['statusState']
    ));
  }

}
