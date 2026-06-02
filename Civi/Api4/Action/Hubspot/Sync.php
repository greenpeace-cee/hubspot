<?php

namespace Civi\Api4\Action\Hubspot;

use Civi;
use Civi\Api4;
use CRM_Core_DAO;
use CRM_Hubspot_HubspotBatchProcessor as HubspotBatchProcessor;
use CRM_Hubspot_HubspotClient as HubspotClient;
use Exception;
use GuzzleHttp\Psr7\Response;

/**
 * Sync modified contacts to HubSpot
 */
class Sync extends Api4\Generic\AbstractAction {

  private static array $_countryIDs;

  public function _run(Api4\Generic\Result $result) {
    $contact_creator = new HubspotBatchProcessor(
      HubspotBatchProcessor::CREATE_CONTACTS,
      __CLASS__ . '::handleSuccessfulBatch',
      __CLASS__ . "::handleFailedBatch",
    );

    $contact_updater = new HubspotBatchProcessor(
      HubspotBatchProcessor::UPDATE_CONTACTS,
      __CLASS__ . '::handleSuccessfulBatch',
      __CLASS__ . "::handleFailedBatch",
    );

    $hubspot_account = Api4\HubspotAccount::get(FALSE)
      ->addSelect('config')
      ->execute()
      ->first();

    $contact_sync_query = $hubspot_account['config']['contactSyncQuery'];
    self::validateSyncQuery($contact_sync_query);

    $owner_country = Civi::settings()->get('hubspot_sync_owner_country');

    if (empty($owner_country)) {
      throw new Exception("Missing required setting 'hubspot_sync_owner_country'");
    }

    $scheduled_for_create = 0;
    $scheduled_for_update = 0;

    foreach (self::selectContactsForSync($contact_sync_query) as $contact) {
      $contact['civicrm_id'] = $contact['id'];
      unset($contact['id']);

      $hubspot_id = $contact['hubspot_id'];
      unset($contact['hubspot_id']);

      $contact['owned_by'] = $owner_country;
      $contact['ownership_score'] ??= 0;

      if (empty($hubspot_id)) {
        $contact_creator->add(NULL, $contact);
        $scheduled_for_create++;
      } else {
        $contact_updater->add($hubspot_id, $contact);
        $scheduled_for_update++;
      }
    }

    $contact_creator->flush();
    $contact_updater->flush();

    $result['scheduledForCreate'] = $scheduled_for_create;
    $result['scheduledForUpdate'] = $scheduled_for_update;
  }

  private static function countryID(string $iso_code): ?int {
    if (!isset(self::$_countryIDs)) {
      $result = Api4\Country::get(FALSE)
        ->addSelect('id', 'iso_code')
        ->addWhere('iso_code', 'IN', ['AT', 'BG', 'HR', 'HU', 'PL', 'RO', 'SI', 'SK', 'UA'])
        ->execute()
        ->indexBy('iso_code');

      self::$_countryIDs = array_map(fn ($country) => $country['id'], (array) $result);
    }

    return self::$_countryIDs[$iso_code] ?? NULL;
  }

  public static function handleFailedBatch(array $batch, Response $response): void {
    $sync_data = [];

    foreach ($batch as $batch_item) {
      $local_contact_id = $batch_item['id'] ?? NULL;
      $local_contact = $batch_item['properties'];
      $owned_by_country = $local_contact['owned_by'];

      if (isset($local_contact['email'])) {
        $primary_email_owner_result = HubspotClient::getContactByEmail($local_contact['email']);
        $primary_email_owner_id = $primary_email_owner_result['id'];
        $primary_email_owner = $primary_email_owner_result['properties'];

        if (isset($primary_email_owner) && $primary_email_owner_id !== $local_contact_id) {
          if ($local_contact['ownership_score'] > $primary_email_owner['ownership_score']) {
            HubspotClient::updateContact($primary_email_owner_id, [ 'email' => '' ]);
          } else {
            $local_contact['email'] = '';
            $owned_by_country = $primary_email_owner['owned_by'];
          }
        }
      }

      if (empty($local_contact_id)) {
        $created_contact = HubspotClient::createContact($local_contact);
        $local_contact_id = $created_contact['id'];
      } else {
        HubspotClient::updateContact($local_contact_id, $local_contact);
      }

      $sync_data[] = [
        'civicrm_id'      => $local_contact['civicrm_id'],
        'hubspot_id'      => $local_contact_id,
        'owned_by'        => $owned_by_country,
        'ownership_score' => $local_contact['ownership_score'],
        'sync_payload'    => $local_contact,
      ];
    }

    self::updateSyncTable($sync_data);
  }

  public static function handleSuccessfulBatch(array $batch, Response $response): void {
    $sync_data = [];

    $results = json_decode((string) $response->getBody(), TRUE)['results'];
    $hubspot_ids = [];

    foreach ($results as $result) {
      $civicrm_id = $result['properties']['civicrm_id'];
      $hubspot_ids[$civicrm_id] = $result['id'];
    }

    foreach ($batch as $batch_item) {
      $civicrm_id = $batch_item['properties']['civicrm_id'];

      $sync_data[] = [
        'civicrm_id'      => $civicrm_id,
        'hubspot_id'      => $hubspot_ids[$civicrm_id],
        'owned_by'        => $batch_item['properties']['owned_by'],
        'ownership_score' => $batch_item['properties']['ownership_score'],
        'sync_payload'    => $batch_item['properties'],
      ];
    }

    self::updateSyncTable($sync_data);
  }

  private static function updateSyncTable(array $sync_data): void {
    if (empty($sync_data)) return;

    $rows = [];
    $params = [];

    foreach ($sync_data as $record) {
      $offset = count($params) + 1;
      list($i, $j, $k, $l, $m) = range($offset, $offset + 5);

      $rows[] = "(%$i, %$j, 0, %$k, %$l, CURRENT_TIMESTAMP, %$m)";

      $params[$i] = [$record['civicrm_id'],                'Integer'];
      $params[$j] = [$record['hubspot_id'],                'String' ];
      $params[$k] = [self::countryID($record['owned_by']), 'Integer'];
      $params[$l] = [$record['ownership_score'],           'Integer'];
      $params[$m] = [json_encode($record['sync_payload']), 'String' ];
    }

    CRM_Core_DAO::executeQuery("
      INSERT INTO civicrm_value_hubspot_sync (
        entity_id,
        hubspot_id,
        has_changes,
        owned_by,
        ownership_score,
        last_sync_date,
        last_sync_payload
      ) VALUES " . implode(', ', $rows) . "
      ON DUPLICATE KEY UPDATE
        hubspot_id        = VALUES(hubspot_id),
        has_changes       = 0,
        owned_by          = VALUES(owned_by),
        ownership_score   = VALUES(ownership_score),
        last_sync_date    = CURRENT_TIMESTAMP,
        last_sync_payload = VALUES(last_sync_payload)
    ", $params);
  }

  private static function selectContactsForSync(array $contact_query) {
    $contact_query['orderBy'] = [ 'id' => 'ASC' ];
    $contact_id_offset = 0;

    while (true) {
      $contact_query['where'][] = ['id', '>', $contact_id_offset];
      $result = civicrm_api4('Contact', 'get', $contact_query);
      array_pop($contact_query['where']);

      if ($result->countFetched() < 1) return;

      $contact_id_offset = $result->last()['id'];

      foreach ($result as $contact) yield $contact;
    }
  }

  private static function validateSyncQuery(array $sync_query): void {
    $required_props = [
      'firstname',
      'lastname',
      'email',
      'hubspot_id',
      'owned_by',
      'ownership_score',
    ];

    foreach ($required_props as $req_prop) {
      $matching_prop = array_find(
        $sync_query['select'],
        fn ($value) => (bool) preg_match("/(^| AS ){$req_prop}$/", $value)
      );

      if (is_null($matching_prop)) {
        throw new Exception("Invalid sync query: Missing property '$req_prop' in select clause");
      }
    }
  }

}
