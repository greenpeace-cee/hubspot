<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_LoadHubspotAccountTrait as LoadHubspotAccountTrait;
use CRM_Hubspot_ApiClient as ApiClient;
use CRM_Hubspot_BatchMailingImporter as BatchMailingImporter;
use CRM_Hubspot_SyncGuard as SyncGuard;
use Generator;

/**
 * Sync mailings from HubSpot
 */
class SyncMailings extends Api4\Generic\DAOGetAction {

  use LoadHubspotAccountTrait;

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

    $total = 0;

    $owner = self::hubspotAccount()['owner_identifier'];

    foreach (self::getMarketingCampaigns($owner) as $marketing_campaign) {
      foreach (self::getCampaignEmails($marketing_campaign['id']) as $marketing_email) {
        $marketing_email['civicrm_campaign'] = $marketing_campaign['properties']['civicrm_campaign'];
        $batch_processor->add($marketing_email);
        $total++;
      }

    }

    $batch_processor->flush();

    $result['scheduledForImport'] = $total;
  }

  private static function getCampaignEmails(string $campaign_id): Generator {
    $has_more = TRUE;
    $paging_cursor_token = NULL;

    while ($has_more) {
      $query_params = [
        'campaign=' . urlencode($campaign_id),
        'includedProperties=allEmailCampaignIds',
        'includedProperties=primaryEmailCampaignId',
        'includedProperties=from',
        'includedProperties=id',
        'includedProperties=publishDate',
        'includedProperties=subject',
        'limit=' . self::PAGE_SIZE,
      ];

      if (isset($paging_cursor_token)) {
        $query_params[] = 'after=' . urlencode($paging_cursor_token);
      }

      $response = ApiClient::getMarketingEmails(implode('&', $query_params));
      $response_payload = json_decode((string) $response->getBody(), TRUE);

      foreach ($response_payload['results'] as $mailing) yield $mailing;

      $paging_cursor_token = $response_payload['paging']['next']['after'] ?? NULL;
      $has_more = isset($paging_cursor_token);
    }
  }

  private static function getMarketingCampaigns(string $owned_by): Generator {
    $has_more = TRUE;
    $paging_cursor_token = NULL;

    while ($has_more) {
      $query_params = [
        'properties=civicrm_campaign',
        'properties=civicrm_owned_by',
        'limit=' . self::PAGE_SIZE,
      ];

      if (isset($paging_cursor_token)) {
        $query_params[] = 'after=' . urlencode($paging_cursor_token);
      }

      $response = ApiClient::getMarketingCampaigns(implode('&', $query_params));
      $response_payload = json_decode((string) $response->getBody(), TRUE);

      foreach ($response_payload['results'] as $campaign) {
        $props = $campaign['properties'];

        if (empty($props['civicrm_campaign'])) continue;
        if (empty($props['civicrm_owned_by']) || $props['civicrm_owned_by'] !== $owned_by) continue;

        yield $campaign;
      }

      $paging_cursor_token = $response_payload['paging']['next']['after'] ?? NULL;
      $has_more = isset($paging_cursor_token);
    }
  }

}
