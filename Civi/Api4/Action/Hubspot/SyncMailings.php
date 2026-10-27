<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_ApiClient as ApiClient;
use CRM_Hubspot_BatchMailingImporter as BatchMailingImporter;
use CRM_Hubspot_SyncGuard as SyncGuard;
use Generator;

/**
 * Sync mailings from HubSpot
 */
class SyncMailings extends Api4\Generic\DAOGetAction {

  const PAGE_SIZE = 100;
  const QUEUE = 'hubspot-sync-mailings';

  public function _run(Api4\Generic\Result $result) {
    SyncGuard::run('sync-mailings', [self::QUEUE], fn () => $this->sync($result));
  }

  private function sync(Api4\Generic\Result $result): void {
    $batch_processor = new BatchMailingImporter([
      'queue_name' => self::QUEUE,
      'batch_size' => 100,
    ]);

    $scheduled = 0;

    foreach (self::getMailings() as $mailing) {
      echo "{$mailing['publishDate']} ({$mailing['id']}): {$mailing['subject']}\n";

      $batch_processor->add($mailing);
      $scheduled ++;
    }

    $batch_processor->flush();
    $result['scheduledForImport'] = $scheduled;
  }

  private static function getMailings(): Generator {
    $has_more = TRUE;
    $paging_cursor_token = NULL;

    while ($has_more) {
      $response = ApiClient::getMarketingEmails([
        'after' => $paging_cursor_token,
        'limit' => self::PAGE_SIZE,
      ]);

      $response_payload = json_decode((string) $response->getBody(), TRUE);

      foreach ($response_payload['results'] as $mailing) {
        unset($mailing['content']);
        yield $mailing;
      }

      $paging_cursor_token = $response_payload['paging']['next']['after'] ?? NULL;
      $has_more = isset($paging_cursor_token);
    }
  }

}

