<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_HubspotBatchProcessor as HubspotBatchProcessor;

/**
 * Sync modified contacts to HubSpot
 */
class Sync extends Api4\Generic\AbstractAction {

  public function _run(Api4\Generic\Result $result) {
    $hubspot_account = self::loadHubspotAccount();
    $contact_sync_query = $hubspot_account['config']['contactSyncQuery'];
    $processor = new HubspotBatchProcessor($hubspot_account);

    foreach (self::selectContactsForSync($contact_sync_query) as $contact) {
      // if (current score is lower than score of last update) {
      //   Check whether ownership should pass to another country
      // }

      $processor->upsertContact($contact);
    }

    $processor->flushRemainingBatch();
  }

  private static function loadHubspotAccount() {
    return Api4\HubspotAccount::get(FALSE)
      ->addSelect('api_key', 'config')
      ->execute()
      ->first();
  }

  private static function selectContactsForSync(array $contact_query, $batch_callback = NULL) {
    $contact_query['orderBy'] = [ 'id' => 'ASC' ];
    $contact_query['where'][] = ['id', '<=', 10];
    $contact_id_offset = 0;

    while (true) {
      $contact_query['where'][] = ['id', '>', $contact_id_offset];
      $result = civicrm_api4('Contact', 'get', $contact_query);
      array_pop($contact_query['where']);

      if ($result->countFetched() < 1) return;

      $contact_id_offset = $result->last()['id'];

      if (isset($batch_callback)) $batch_callback((array) $result);

      foreach ($result as $contact) yield $contact;
    }
  }

}
