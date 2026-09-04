<?php

use Civi\Api4;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_ApiClient {

  use CRM_Hubspot_LoadHubspotAccountTrait;

  const LOG_MESSAGE_TEMPLATE = "\n\nRequest: {method} {uri}\n{req_body}\n\nResponse: {code} {phrase}\n{res_body}\n\nError: {error}";

  public static HandlerStack $handlerStack;

  private static Client $_client;

  public static function batchCreateContacts(array $contacts_batch): Response {
    return self::request('POST', '/crm/v3/objects/contacts/batch/create', [
      'json' => [ 'inputs' => $contacts_batch ],
    ]);
  }

  public static function batchCreateEvents(array $events_batch): Response {
    return self::request('POST', '/events/2026-03/send/batch', [
      'json' => [ 'inputs' => $events_batch ],
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

  private static function client(): Client {
    if (!isset(self::$_client)) {
      $config = self::hubspotAccount();

      if (!isset(self::$handlerStack)) {
        self::$handlerStack = HandlerStack::create();

        $logger = Middleware::log(
          Civi::log('hubspot-sync'),
          new MessageFormatter(self::LOG_MESSAGE_TEMPLATE)
        );

        self::$handlerStack->push($logger);
      }

      self::$_client = new Client([
        'handler'  => self::$handlerStack,
        'base_uri' => $config['base_uri'],
      ]);
    }

    return self::$_client;
  }

  private static function request(string $method, string $endpoint, array $options = []): Response {
    $api_key = self::hubspotAccount()['api_key'];

    $options = [
      ...$options,
      'headers' => [
        ...($options['headers'] ?? []),
        'Authorization' => "Bearer $api_key",
      ],
    ];

    return self::client()->request($method, $endpoint, $options);
  }

}
