<?php

use CRM_Hubspot_ApiClient as ApiClient;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_BatchContactUpdater extends CRM_Hubspot_BatchProcessor {

  use CRM_Hubspot_CountryIsoResolverTrait;
  use CRM_Hubspot_LoadHubspotAccountTrait;
  use CRM_Hubspot_UpdateContactSyncTableTrait;

  const API_ENDPOINT = '/crm/v3/objects/contacts/batch/update';

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
      $owned_by = self::hubspotAccount()['owner_country'];
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
            $owned_by = self::getCountryId($primary_email_owner['properties']['owned_by']);
          }
        }

        ApiClient::updateContact($hubspot_id, $sync_payload);

        self::updateSyncRecord([
          'entity_id'         => $civicrm_id,
          'hubspot_id'        => $hubspot_id,
          'owned_by'          => $owned_by,
          'last_sync_failed'  => FALSE,
          'last_sync_payload' => $sync_payload,
        ]);
      } catch (Exception $exception) {
        Civi::log('hubspot-sync')->error('Contact could not be synced', [
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

  private static function onSuccess(array $batch, Response $response): void {
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
        'owned_by'          => self::hubspotAccount()['owner_country'],
        'last_sync_failed'  => FALSE,
        'last_sync_payload' => $sync_payload,
      ]);
    }

    if (!array_key_exists('errors', $response_body)) return;

    Civi::log('hubspot-sync')->error('Some contacts could not be synced', $response_body['errors']);

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

  public static function processBatch(CRM_Queue_TaskContext $_ctx, array $batch): bool {
    try {
      $response = ApiClient::batchUpdateContacts($batch);

      if (in_array($response->getStatusCode(), [200, 201, 207])) {
        self::onSuccess($batch, $response);
      } else {
        Civi::log('hubspot-sync')->error('Received unexpected response status code: ' . $response->getStatusCode(), [
          'request'  => $request,
          'response' => $response,
        ]);

        return FALSE;
      }
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
