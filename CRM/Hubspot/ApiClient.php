<?php

use Civi\Api4;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_ApiClient {

  use CRM_Hubspot_LoadHubspotAccountTrait;

  public static HandlerStack $handlerStack;

  public static function batchCreateContacts(array $contacts_batch): Response {
    return self::request('POST', '/crm/v3/objects/contacts/batch/create', [
      'json' => [ 'inputs' => $contacts_batch ],
    ]);
  }

  public static function batchGetContacts(array $contact_ids, array $props = []): Response {
    return self::request('POST', '/crm/v3/objects/contacts/batch/read', [
      'json' => [
        'inputs'                => array_map(fn ($id) => [ 'id' => $id ], $contact_ids),
        'properties'            => $props,
        'propertiesWithHistory' => []
      ],
    ]);
  }

  public static function batchUpdateContacts(array $contacts_batch): Response {
    return self::request('POST', '/crm/v3/objects/contacts/batch/update', [
      'json' => [ 'inputs' => $contacts_batch ],
    ]);
  }

  public static function batchUpdateSubscriptionStatus(array $changes): Response {
    return self::request('POST', '/communication-preferences/v4/statuses/batch/write', [
      'json' => [ 'inputs' => $changes ],
    ]);
  }

  public static function createContact(array $contact_data): Response {
    return self::request('POST', '/crm/v3/objects/contacts', [
      'json' => [ 'properties' => $contact_data ],
    ]);
  }

  public static function getContactByEmail(string $email, array $props = []): Response {
    return self::request('GET', "/crm/v3/objects/contacts/$email", [
      'query' => [
        'idProperty' => 'email',
        'properties' => implode(',', $props),
      ],
    ]);
  }

  public static function getEvents(array $query = []): Response {
    return self::request('GET', '/events/event-occurrences/2026-03', [
      'query' => array_filter($query),
    ]);
  }

  public static function getSubscriptionDefinitions(): Response {
    return self::request('GET', '/communication-preferences/v3/definitions');
  }

  public static function getSubscriptionsTimeline(int $start, int $end): Response {
    return self::request('GET', '/email/public/v1/subscriptions/timeline', [
      'query' => [
        'startTimestamp' => $start,
        'endTimestamp'   => $end,
      ],
    ]);
  }

  public static function updateContact(string $contact_id, array $contact_data): Response {
    return self::request('PATCH', "/crm/v3/objects/contacts/$contact_id", [
      'json' => [ 'properties' => $contact_data ],
    ]);
  }

  private static function request(string $method, string $endpoint, array $options = []): Response {
    $config = self::hubspotAccount();

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

}
