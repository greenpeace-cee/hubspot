<?php

use Civi\Api4;

class CRM_Hubspot_HubspotClient {

  protected static HubSpot\Discovery\Discovery $_apiClient;

  protected static function apiClient(): HubSpot\Discovery\Discovery {
    if (!isset(self::$_apiClient)) {
      $hubspot_account = Api4\HubspotAccount::get(FALSE)
        ->addSelect('api_key')
        ->execute()
        ->first();

      self::$_apiClient = HubSpot\Factory::createWithAccessToken($hubspot_account['api_key']);
    }

    return self::$_apiClient;
  }

  public static function createContact(array $contact_data): array {
    $response = self::apiClient()->apiRequest([
      'method' => 'POST',
      'path'   => "/crm/v3/objects/contacts",
      'body'   => [ 'properties' => $contact_data ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }

  public static function getContactByEmail(string $email): ?array {
    try {
      $response = self::apiClient()->apiRequest([
        'method' => 'GET',
        'path'   => "/crm/v3/objects/contacts/$email?idProperty=email&properties=" . implode(',', [
          'firstname',
          'lastname',
          'email',
          'civicrm_id',
          'owned_by',
          'ownership_score',
        ]),
      ]);

      return json_decode((string) $response->getBody(), TRUE);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      if ($exception->getResponse()->getStatusCode() === 404) return NULL;

      throw $exception;
    }
  }

  public static function updateContact(string $contact_id, array $contact_data): array {
    $response = self::apiClient()->apiRequest([
      'method' => 'PATCH',
      'path'   => "/crm/v3/objects/contacts/$contact_id",
      'body'   => [ 'properties' => $contact_data ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }

}
