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
class Sync extends Api4\Generic\DAOGetAction {

  const SYNC_TABLE = 'civicrm_value_hubspot_sync';

  private static array $_countryIDs;
  private static string $_ownerCountry;

  public function _run(Api4\Generic\Result $result) {
    $contact_creator = new HubspotBatchProcessor(
      HubspotBatchProcessor::CREATE_CONTACTS,
      __CLASS__ . '::onBatchSuccess',
      __CLASS__ . '::onBatchConflict',
    );

    $contact_updater = new HubspotBatchProcessor(
      HubspotBatchProcessor::UPDATE_CONTACTS,
      __CLASS__ . '::onBatchSuccess',
      __CLASS__ . '::onBatchConflict',
    );

    $result['scheduledForCreate'] = 0;
    $result['scheduledForUpdate'] = 0;

    foreach ($this->selectContactsForSync() as $contact) {
      $contact['civicrm_id'] = $contact['id'];
      unset($contact['id']);

      $hubspot_id = $contact['hubspot_id'];
      unset($contact['hubspot_id']);

      $contact['owned_by'] = self::ownerCountry();
      $contact['unique_civicrm_id'] = $contact['owned_by'] . '-' . $contact['civicrm_id'];

      if (empty($hubspot_id)) {
        $contact_creator->add(NULL, $contact);
        $result['scheduledForCreate']++;
      } else {
        $contact_updater->add($hubspot_id, $contact);
        $result['scheduledForUpdate']++;
      }
    }

    $contact_creator->flush();
    $contact_updater->flush();
  }

  private static function countryID(string $iso_code): ?int {
    if (empty(self::$_countryIDs)) {
      $result = Api4\Country::get(FALSE)
        ->addSelect('id', 'iso_code')
        ->addWhere('iso_code', 'IN', ['AT', 'BG', 'HR', 'HU', 'PL', 'RO', 'SI', 'SK', 'UA'])
        ->execute()
        ->indexBy('iso_code');

      self::$_countryIDs = array_map(fn ($country) => $country['id'], (array) $result);
    }

    return self::$_countryIDs[$iso_code] ?? NULL;
  }

  public static function onBatchConflict(array $batch, Response $_response): void {
    foreach ($batch as $batch_item) {
      $hubspot_id = $batch_item['id'] ?? NULL;
      $civicrm_id = (int) $batch_item['properties']['civicrm_id'];
      $email = $batch_item['properties']['email'] ?? NULL;
      $owned_by = self::ownerCountry();
      $sync_payload = $batch_item['properties'];

      try {
        $primary_email_owner = empty($email) ? NULL : HubspotClient::getContactByEmail($email);

        if (isset($primary_email_owner) && $primary_email_owner['id'] != $hubspot_id) {
          $local_contact_score = (int) $batch_item['properties']['ownership_score'];
          $primary_owner_score = (int) $primary_email_owner['properties']['ownership_score'];

          if ($local_contact_score > $primary_owner_score) {
            HubspotClient::updateContact($primary_email_owner['id'], [ 'email' => '' ]);
          } else {
            unset($sync_payload['email']);
            $owned_by = $primary_email_owner['properties']['owned_by'];
          }
        }

        if (empty($hubspot_id)) {
          $hubspot_id = HubspotClient::createContact($sync_payload)['id'];
        } else {
          HubspotClient::updateContact($hubspot_id, $sync_payload);
        }

        self::updateSyncRecord([
          'entity_id'         => $civicrm_id,
          'hubspot_id'        => $hubspot_id,
          'owned_by'          => $owned_by,
          'last_sync_failed'  => FALSE,
          'last_sync_payload' => $sync_payload,
        ]);
      } catch (Exception $exception) {
        Civi::log()->error('Contact could not be synced', [
          'contact'   => $batch_item,
          'exception' => $exception,
        ]);

        self::updateSyncRecord([
          'entity_id'         => $civicrm_id,
          'last_sync_failed'  => TRUE,
          'last_sync_payload' => $sync_payload,
        ]);
      }
    }
  }

  public static function onBatchSuccess(array $batch, Response $response): void {
    $response_body = json_decode((string) $response->getBody(), TRUE);

    foreach ($response_body['results'] as $result_item) {
      $hubspot_id = $result_item['id'];

      $sync_payload = array_reduce($batch,
        fn ($result, $item) =>
          (int) $item['properties']['civicrm_id'] === (int) $result_item['properties']['civicrm_id']
          ? $item['properties']
          : $result
      );

      self::updateSyncRecord([
        'entity_id'         => $result_item['properties']['civicrm_id'],
        'hubspot_id'        => $hubspot_id,
        'owned_by'          => self::ownerCountry(),
        'last_sync_failed'  => FALSE,
        'last_sync_payload' => $sync_payload,
      ]);
    }

    if (!array_key_exists('errors', $response_body)) return;

    Civi::log()->error('Some contacts could not be synced', $response_body['errors']);

    foreach ($response_body['errors'] as $error) {
      switch ($error['category']) {
        case 'OBJECT_NOT_FOUND': {
          foreach ($error['context']['ids'] as $hubspot_id) {
            $sync_payload = array_reduce($batch,
              fn ($result, $item) => $item['id'] === $hubspot_id ? $item['properties'] : $result
            );

            self::updateSyncRecord([
              'entity_id'         => $sync_payload['civicrm_id'],
              'last_sync_failed'  => TRUE,
              'last_sync_payload' => $sync_payload,
            ]);
          }

          break;
        }
      }
    }
  }

  private static function ownerCountry(): string {
    self::$_ownerCountry ??= Civi::settings()->get('hubspot_sync_owner_country');

    if (empty(self::$_ownerCountry)) {
      throw new Exception("Missing required setting 'hubspot_sync_owner_country'");
    }

    return self::$_ownerCountry;
  }

  private function selectContactsForSync() {
    $contact_query = [
      'select'           => $this->select,
      'where'            => $this->where,
      'join'             => $this->join,
      'orderBy'          => [ 'id' => 'ASC' ],
      'limit'            => 100,
      'checkPermissions' => FALSE,
    ];

    $select_hubspot_id = array_find(
      $contact_query['select'],
      fn ($value) => (bool) preg_match("/(^| AS )hubspot_id$/", $value)
    );

    if (is_null($select_hubspot_id)) {
      throw new Exception("Invalid sync query: Missing property 'hubspot_id' in select clause");
    }

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

  private static function updateSyncRecord(array $record): void {
    $assignments = [
      'has_changes = 0',
      'last_sync_date = CURRENT_TIMESTAMP',
    ];

    $params = [];
    $i = 0;

    foreach ($record as $column => $value) {
      $i++;

      switch ($column) {
        case 'hubspot_id': {
          $assignments[] = "hubspot_id = NULLIF(%$i, '')";
          $params[$i] = [$value ?? '', 'String'];
          break;
        }

        case 'owned_by': {
          $assignments[] = "owned_by = NULLIF(%$i, 0)";
          $params[$i] = [self::countryID($value) ?? 0, 'Integer'];
          break;
        }

        case 'last_sync_failed': {
          $assignments[] = "last_sync_failed = %$i";
          $params[$i] = [(int) $value, 'Integer'];
          break;
        }

        case 'last_sync_payload': {
          $assignments[] = "last_sync_payload = NULLIF(%$i, '')";
          $params[$i] = [empty($value) ? '' : json_encode($value), 'String'];
          break;
        }
      }
    }

    CRM_Core_DAO::executeQuery(
      "UPDATE " . self::SYNC_TABLE .
      " SET " . implode(', ', $assignments) .
      " WHERE entity_id = " . (int) $record['entity_id'],
      $params
    );
  }

}
