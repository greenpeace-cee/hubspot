<?php

use Civi\Api4;
use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchEventCreator extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    $sync_failed = FALSE;

    try {
      ApiClient::batchCreateEvents($batch);
    } catch (Exception $exception) {
      $sync_failed = TRUE;
    }

    $event_update = Api4\HubspotEvent::save(FALSE);

    foreach ($batch as $event) {
      $event_update->addRecord([
        'id'          => $event['properties']['civicrm_id'],
        'hubspot_id'  => $sync_failed ? NULL : $event['uuid'],
        'sync_date'   => date('Y-m-d H:i:s'),
        'sync_failed' => $sync_failed,
      ]);
    }

    $event_update->setMatch(['id'])->execute();

    return !$sync_failed;
  }

}
