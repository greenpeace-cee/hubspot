<?php

namespace Civi\Api4\Action\Hubspot;

use Civi;
use Civi\Api4;
use CRM_Core_DAO;
use CRM_Hubspot_HubspotBatchProcessor as HubspotBatchProcessor;
use CRM_Hubspot_HubspotClient as HubspotClient;
use CRM_Hubspot_HubspotContact as HubspotContact;
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

    foreach (self::selectContactsForSync($contact_sync_query) as $contact_data) {
      $hubspot_contact = HubspotContact::fromCiviProperties($contact_data);
      $hubspot_contact->ownedBy = $owner_country;
      $hubspot_contact->ownershipScore ??= 0;

      if (empty($hubspot_contact->id)) {
        $contact_creator->add($hubspot_contact);
        $scheduled_for_create++;
      } else {
        $contact_updater->add($hubspot_contact);
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

  public static function handleFailedBatch(array $request, Response $response): void {
    $sync_data = [];

    foreach ($request['body']['inputs'] as $contact_data) {
      $local_contact = HubspotContact::fromHubspotProperties($contact_data['properties']);
      $local_contact->id = $contact_data['id'] ?? NULL;
      $owned_by_country = $local_contact->ownedBy;

      if (isset($local_contact->email)) {
        $primary_email_owner = HubspotClient::getContactByEmail($local_contact->email);

        if (isset($primary_email_owner) && $primary_email_owner->id !== $local_contact->id) {
          if ($local_contact->ownershipScore > $primary_email_owner->ownershipScore) {
            $primary_email_owner->email = '';
            HubspotClient::updateContact($primary_email_owner);
          } else {
            $local_contact->email = '';
            $owned_by_country = $primary_email_owner->ownedBy;
          }
        }
      }

      if (empty($local_contact->id)) {
        $local_contact = HubspotClient::createContact($local_contact);
      } else {
        $local_contact = HubspotClient::updateContact($local_contact);
      }

      // Set the owner country *after* the API request since the contact in HubSpot should always be
      // owned by this Civi instance but the current primary ownership of the email address should
      // be recorded in the local sync table
      $sync_data[] = [
        'civicrm_id'      => $local_contact->civicrmID,
        'hubspot_id'      => $local_contact->id,
        'owned_by'        => $owned_by_country,
        'ownership_score' => $local_contact->ownershipScore,
        'request_payload' => $contact_data['properties'],
      ];
    }

    self::updateSyncTable($sync_data);
  }

  public static function handleSuccessfulBatch(array $request, Response $response): void {
    $results = json_decode((string) $response->getBody(), TRUE)['results'];
    $contacts = [];
    $sync_data = [];

    foreach ($results as $result) {
      $civicrm_id = $result['properties']['civicrm_id'];
      $contacts[$civicrm_id] = HubspotContact::fromHubspotProperties($result['properties']);
    }

    foreach ($request['body']['inputs'] as $input) {
      $request_payload = $input['properties'];
      $contact = $contacts[$request_payload['civicrm_id']];

      $sync_data[] = [
        'civicrm_id'      => $contact->civicrmID,
        'hubspot_id'      => $contact->id,
        'owned_by'        => $contact->ownedBy,
        'ownership_score' => $contact->ownershipScore,
        'request_payload' => $request_payload,
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

      $params[$i] = [$record['civicrm_id'],                   'Integer'];
      $params[$j] = [$record['hubspot_id'],                   'String' ];
      $params[$k] = [self::countryID($record['owned_by']),    'Integer'];
      $params[$l] = [$record['ownership_score'],              'Integer'];
      $params[$m] = [json_encode($record['request_payload']), 'String' ];
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
      'first_name',
      'last_name',
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
