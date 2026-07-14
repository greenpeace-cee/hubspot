<?php

use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchSubscriptionUpdater extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    return ApiClient::batchUpdateSubscriptionStatus($batch)->getStatusCode() === 200;
  }

}
