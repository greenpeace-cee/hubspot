<?php

use Civi\Api4;
use CRM_Hubspot_HubspotContact as HubspotContact;

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

  public static function createContact(HubspotContact $contact): HubspotContact {
    $response = self::apiClient()->apiRequest([
      'method' => 'POST',
      'path'   => "/crm/v3/objects/contacts",
      'body'   => [
        'properties' => $contact->toHubspotProperties(),
      ],
    ]);

    $response_body = json_decode((string) $response->getBody(), TRUE);

    return HubspotContact::fromHubspotProperties($response_body['properties']);
  }

  public static function getContactByEmail(string $email): ?HubspotContact {
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

      $response_body = json_decode((string) $response->getBody(), TRUE);

      return HubspotContact::fromHubspotProperties($response_body['properties']);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      if ($exception->getResponse()->getStatusCode() === 404) return NULL;

      throw $exception;
    }
  }

  public static function updateContact(HubspotContact $contact): HubspotContact {
    $response = self::apiClient()->apiRequest([
      'method' => 'PATCH',
      'path'   => "/crm/v3/objects/contacts/{$contact->id}",
      'body'   => [
        'properties' => $contact->toHubspotProperties(),
      ],
    ]);

    $response_body = json_decode((string) $response->getBody(), TRUE);

    return HubspotContact::fromHubspotProperties($response_body['properties']);
  }

}
