<?php

namespace Civi\Api4\Action\Hubspot;

use Civi;
use Civi\Api4;
use CRM_Core_DAO;
use CRM_Hubspot_HubspotBatchProcessor as HubspotBatchProcessor;
use CRM_Hubspot_HubspotClient as HubspotClient;
use CRM_Hubspot_HubspotContact as HubspotContact;
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

    $scheduled_for_create = 0;
    $scheduled_for_update = 0;

    foreach (self::selectContactsForSync($contact_sync_query) as $contact_data) {
      $hubspot_contact = HubspotContact::fromCiviProperties($contact_data);
      $hubspot_contact->ownedBy = Civi::settings()->get('hubspot_sync_owner_country');
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
    $synced_contacts = [];

    foreach ($request['body']['inputs'] as $contact_data) {
      $local_contact = HubspotContact::fromHubspotProperties($contact_data['properties']);
      $local_contact->id = $contact_data['id'] ?? NULL;

      if (isset($local_contact->email)) {
        $primary_email_owner = HubspotClient::getContactByEmail($local_contact->email);

        if (isset($primary_email_owner) && $primary_email_owner->id !== $local_contact->id) {
          if ($local_contact->ownershipScore > $primary_email_owner->ownershipScore) {
            $primary_email_owner->email = '';
            HubspotClient::updateContact($primary_email_owner);
          } else {
            $local_contact->email = '';
          }
        }
      }

      if (empty($local_contact->id)) {
        $synced_contacts[] = HubspotClient::createContact($local_contact);
      } else {
        $synced_contacts[] = HubspotClient::updateContact($local_contact);
      }
    }

    self::updateSyncTable($synced_contacts);
  }

  public static function handleSuccessfulBatch(array $request, Response $response): void {
    self::updateSyncTable(array_map(
      fn ($result) => HubspotContact::fromHubspotProperties($result['properties']),
      json_decode((string) $response->getBody(), TRUE)['results']
    ));
  }

  private static function updateSyncTable(array $contacts): void {
    if (empty($contacts)) return;

    $rows = [];
    $params = [];

    foreach ($contacts as $contact) {
      $offset = count($params) + 1;
      list($i, $j, $k, $l, $m) = range($offset, $offset + 5);

      $rows[] = "(%$i, %$j, 0, %$k, %$l, CURRENT_TIMESTAMP, %$m)";

      $params[$i] = [$contact->civicrmID,                          'Integer'];
      $params[$j] = [$contact->id,                                 'String' ];
      $params[$k] = [self::countryID($contact->ownedBy),           'Integer'];
      $params[$l] = [$contact->ownershipScore,                     'Integer'];
      $params[$m] = [json_encode($contact->toHubspotProperties()), 'String' ];
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
      ) VALUES " . implode(', ', $rows) . " AS new
      ON DUPLICATE KEY UPDATE
        hubspot_id        = new.hubspot_id,
        has_changes       = 0,
        owned_by          = new.owned_by,
        ownership_score   = new.ownership_score,
        last_sync_date    = CURRENT_TIMESTAMP,
        last_sync_payload = new.last_sync_payload
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

}
