<?php

use CRM_Hubspot_ApiClient as ApiClient;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_BatchContactUpdater extends CRM_Hubspot_BatchProcessor {

  use CRM_Hubspot_LoadHubspotAccountTrait;
  use CRM_Hubspot_UpdateContactSyncTableTrait;

  private static function getContactByEmail(string $email): ?array {
    try {
      $response = ApiClient::getContactByEmail($email, ['owned_by', 'ownership_score']);

      return json_decode((string) $response->getBody(), TRUE);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      if ($exception->getResponse()->getStatusCode() === 404) return NULL;

      throw $exception;
    }
  }

  private static function onConflict(array $batch, Response $_response): void {
    foreach ($batch as $batch_item) {
      $hubspot_id = $batch_item['id'];
      $civicrm_id = (int) $batch_item['properties']['civicrm_id'];
      $email = $batch_item['properties']['email'] ?? NULL;
      $owned_by = self::hubspotAccount()['owner_identifier'];
      $sync_payload = $batch_item['properties'];

      try {
        $primary_email_owner = empty($email) ? NULL : self::getContactByEmail($email);

        if (isset($primary_email_owner) && $primary_email_owner['id'] != $hubspot_id) {
          $local_contact_score = (int) $batch_item['properties']['ownership_score'];
          $primary_owner_score = (int) $primary_email_owner['properties']['ownership_score'];

          if ($local_contact_score > $primary_owner_score) {
            ApiClient::updateContact($primary_email_owner['id'], [ 'email' => '' ]);
          } else {
            unset($sync_payload['email']);
            $owned_by = $primary_email_owner['properties']['owned_by'];
          }
        }

        ApiClient::updateContact($hubspot_id, $sync_payload);

        self::updateSyncRecord([
          'entity_id'         => $civicrm_id,
          'hubspot_id'        => $hubspot_id,
          'owned_by'          => $owned_by,
          'sync_status'       => 'successful',
          'last_sync_payload' => $sync_payload,
        ]);
      } catch (Exception $exception) {
        Civi::log('hubspot-sync')->error('Contact could not be synced', [
          'contact'   => $batch_item,
          'exception' => $exception,
        ]);

        self::updateSyncRecord([
          'entity_id'         => $civicrm_id,
          'sync_status'       => 'failed',
          'last_sync_payload' => $sync_payload,
        ]);
      }
    }
  }

  private static function onSuccess(array $batch, Response $response): void {
    $not_synced = array_reduce($batch, fn ($result, $item) => $result + [ $item['id'] => $item['properties'] ], []);

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
        'owned_by'          => self::hubspotAccount()['owner_identifier'],
        'sync_status'       => 'successful',
        'last_sync_payload' => $sync_payload,
      ]);

      unset($not_synced[$hubspot_id]);
    }

    if (empty($not_synced)) return;

    Civi::log('hubspot-sync')->error('Some contacts could not be synced:', $not_synced);

    foreach ($not_synced as $properties) {
      self::updateSyncRecord([
        'entity_id'         => $properties['civicrm_id'],
        'sync_status'       => 'failed',
        'last_sync_payload' => $properties,
      ]);
    }
  }

  public static function processBatch(CRM_Queue_TaskContext $_ctx, array $batch): bool {
    // Skip contacts deleted since queueing, e.g. merged duplicates, whose
    // HubSpot contact may now belong to the primary
    $batch = self::filterBatchContacts($batch);

    if (empty($batch)) {
      return TRUE;
    }

    try {
      $response = ApiClient::batchUpdateContacts($batch);

      if (!in_array($response->getStatusCode(), [200, 201, 207])) return FALSE;

      self::onSuccess($batch, $response);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      $response = $exception->getResponse();
      $status_code = $response->getStatusCode();

      switch ($status_code) {
        case 400 /* Bad Request */ :
        case 409 /* Conflict */ : {
          self::onConflict($batch, $response);

          return TRUE;
        }

        case 429 /* Too Many Requests */ : {
          Civi::log('hubspot-sync')->error('Rate limit encountered', [ 'exception' => $exception ]);

          return FALSE;
        }

        default: {
          Civi::log('hubspot-sync')->error('Batch request failed', [
            'batch'      => $batch,
            'statusCode' => $status_code,
            'response'   => json_decode((string) $response->getBody(), TRUE),
            'exception'  => $exception,
          ]);

          return FALSE;
        }
      }
    }

    return TRUE;
  }

}
