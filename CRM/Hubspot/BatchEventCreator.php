<?php

use Civi\Api4;
use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchEventCreator extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    $batch_response = ApiClient::batchCreateEvents($batch);

    $event_update = Api4\HubspotEvent::save(FALSE);

    if ($batch_response->getStatusCode() !== 204) {
      // @TODO: Handle error, mark events with sync_failed = TRUE
      return FALSE;
    }

    foreach ($batch as $event) {
      $event_update->addRecord([
        'id'          => $event['properties']['civicrm_id'],
        'hubspot_id'  => $event['uuid'],
        'sync_date'   => date('Y-m-d H:i:s'),
        'sync_failed' => FALSE,
      ]);
    }

    $event_update->setMatch(['id'])->execute();

    return TRUE;
  }

}
