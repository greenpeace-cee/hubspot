<?php

use Civi\Api4;

class CRM_Hubspot_HubspotClient {

  private static array $_config;

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
    $client = new GuzzleHttp\Client([ 'base_uri' => $config['base_uri'] ]);

    $options = [
      ...$options,
      'headers' => [
        ...($options['headers'] ?? []),
        'Authorization' => 'Bearer ' . $config['api_key'],
      ],
    ];

    $response = $client->request($method, $endpoint, $options);
    $response_body = json_decode((string) $response->getBody(), TRUE);

    $request_data = [
      'method'   => $method,
      'endpoint' => $endpoint,
      'headers'  => $options['headers'],
    ];

    $request_data['headers']['Authorization'] = 'Bearer *****';

    if (!empty($options['query'])) {
      $request_data['query_params'] = $options['query'];
    }

    if (isset($options['json'])) {
      $request_data['body'] = $options['json'];
    }

    $response_data = [
      'status'  => $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
      'headers' => $response->getHeaders(),
      'body'    => $response_body,
    ];

    Civi::log('hubspot-sync')->info("$method {$config['base_uri']}$endpoint", [
      'request'  => $request_data,
      'response' => $response_data,
    ]);

    return $response;
  }

  public static function updateContact(string $contact_id, array $contact_data): array {
    $response = self::request('PATCH', "/crm/v3/objects/contacts/$contact_id", [
      'json' => [ 'properties' => $contact_data ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }

}
