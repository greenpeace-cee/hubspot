<?php

use Civi\Api4;
use GuzzleHttp\HandlerStack;

class CRM_Hubspot_HubspotClient {

  private static array $_config;
  public static HandlerStack $handlerStack;

  public static function createContact(array $contact_data): array {
    $response = self::request('POST', '/crm/v3/objects/contacts', [
      'json' => [ 'properties' => $contact_data ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }

  private static function getConfig(): array {
    if (!isset(self::$_config)) {
      self::$_config = Api4\HubspotAccount::get(FALSE)
        ->addSelect('api_key', 'base_uri')
        ->execute()
        ->first();
    }

    return self::$_config;
  }

  public static function getContactByEmail(string $email, array $props = []): ?array {
    try {
      $response = self::request('GET', "/crm/v3/objects/contacts/$email", [
        'query' => [
          'idProperty' => 'email',
          'properties' => implode(',', $props),
        ],
      ]);

      return json_decode((string) $response->getBody(), TRUE);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      if ($exception->getResponse()->getStatusCode() === 404) return NULL;

      throw $exception;
    }
  }

  public static function request(string $method, string $endpoint, array $options = []): GuzzleHttp\Psr7\Response {
    $config = self::getConfig();

    $client = isset(self::$handlerStack)
      ? new GuzzleHttp\Client([ 'handler' => self::$handlerStack ])
      : new GuzzleHttp\Client([ 'base_uri' => $config['base_uri'] ]);

    $options = [
      ...$options,
      'headers' => [
        ...($options['headers'] ?? []),
        'Authorization' => 'Bearer ' . $config['api_key'],
      ],
    ];

    return $client->request($method, $endpoint, $options);
  }

  public static function updateContact(string $contact_id, array $contact_data): array {
    $response = self::request('PATCH', "/crm/v3/objects/contacts/$contact_id", [
      'json' => [ 'properties' => $contact_data ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }

}
