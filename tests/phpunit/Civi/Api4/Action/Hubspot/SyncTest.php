<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi;
use Civi\Api4;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use CRM_Core_DAO;
use CRM_Hubspot_HubspotClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * @group headless
 */
class SyncTest extends TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  const OWNER_COUNTRY = 'AT';

  private array $contactIds = [];
  private static array $countryIds = [];
  private array $historyContainer = [];
  private MockHandler $mockHandler;

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply(TRUE);
  }

  public function setUp(): void {
    $this->mockHandler = new MockHandler();
    $history_mw = Middleware::history($this->historyContainer);

    $handler_stack = HandlerStack::create($this->mockHandler);
    $handler_stack->push($history_mw);
    CRM_Hubspot_HubspotClient::$handlerStack = $handler_stack;

    Api4\HubspotAccount::create(FALSE)
      ->addValue('account_id', 19946500)
      ->addValue('name', 'Test account GPCEE')
      ->addValue('base_uri', 'https://api.hubapi.com')
      ->addValue('api_key', 'pat-abc-00000000-1111-2222-3333-444444444444')
      ->execute();

    Civi::settings()->set('hubspot_sync_owner_country', self::OWNER_COUNTRY);

    self::$countryIds = array_map(
      fn ($country) => $country['id'],
      (array) Api4\Country::get(TRUE)
        ->addSelect('iso_code')
        ->addWhere('iso_code', 'IN', ['AT', 'BG', 'HR', 'HU', 'PL', 'RO', 'SI', 'SK', 'UA'])
        ->execute()
        ->indexBy('iso_code')
    );

    for ($i = 0; $i < 10; $i++) {
      $this->contactIds[] = Api4\Contact::create(FALSE)
        ->addValue('contact_type', 'Individual')
        ->addValue('first_name', 'Contact')
        ->addValue('last_name', "#$i")
        ->addValue('birth_date', date('Y-m-d', random_int(0, pow(10, 9))))
        ->addValue('hubspot_sync.email', "contact-$i@example.org")
        ->addValue('hubspot_sync.owned_by:abbr', self::OWNER_COUNTRY)
        ->execute()
        ->first()['id'];
    }

    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  private static function generateHubspotId(): string {
    return (string) random_int(pow(10, 9), pow(10, 12));
  }

  private static function mapToHubspotProps(array $contact): array {
    return [
      'firstname'         => $contact['first_name'],
      'lastname'          => $contact['last_name'],
      'date_of_birth'     => $contact['birth_date'],
      'email'             => $contact['email'],
      'civicrm_id'        => $contact['id'],
      'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      'owned_by'          => $contact['owned_by'],
      'ownership_score'   => $contact['ownership_score'],
    ];
  }

  private function processQueueItems(string $queue_name): void {
    $queue_result = Api4\Queue::runItems(FALSE)
      ->setQueue($queue_name)
      ->execute()
      ->first();

    $this->assertEquals('ok', $queue_result['outcome'], 'The outcome of the queue runner should be "ok"');

    $queue_items = (array) Api4\QueueItem::get(FALSE)
      ->addWhere('queue_name', '=', $queue_name)
      ->execute();

    $this->assertEmpty($queue_items, 'All queue items should have been processed');
  }

  private function queryContacts(array $contact_ids = NULL): array {
    $contact_ids ??= $this->contactIds;

    return array_map(
      fn ($contact) => [
        'id'                => $contact['id'],
        'first_name'        => $contact['first_name'],
        'last_name'         => $contact['last_name'],
        'birth_date'        => $contact['birth_date'],
        'email'             => $contact['hubspot_sync.email'],
        'hubspot_id'        => $contact['hubspot_sync.hubspot_id'],
        'has_changes'       => $contact['hubspot_sync.has_changes'],
        'owned_by'          => array_search($contact['hubspot_sync.owned_by'], self::$countryIds),
        'ownership_score'   => $contact['hubspot_sync.ownership_score'],
        'last_sync_date'    => $contact['hubspot_sync.last_sync_date'],
        'last_sync_failed'  => $contact['hubspot_sync.last_sync_failed'],
        'last_sync_payload' => $contact['hubspot_sync.last_sync_payload'],
      ],
      (array) Api4\Contact::get(FALSE)
        ->addSelect('*', 'hubspot_sync.*')
        ->addWhere('id', 'IN', $contact_ids)
        ->execute()
    );
  }

  public function testInitialSync(): void {
    $contacts = $this->queryContacts();

    $this->mockHandler->append(new Response(
      200,
      [ 'Content-Type' => 'application/json' ],
      json_encode([
        'results' => array_map(
          fn ($contact) => [
            'id' => self::generateHubspotId(),
            'properties' => self::mapToHubspotProps($contact),
          ],
          $contacts
        ),
      ])
    ));

    $schedule_result = (array) civicrm_api4('Hubspot', 'sync', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['contact_type', '=', 'Individual'],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => count($contacts),
      'scheduledForUpdate' => 0,
    ], $schedule_result);

    $this->processQueueItems('hubspot-sync-create-contacts');

    $request = array_shift($this->historyContainer)['request'];

    $this->assertEquals('POST', $request->getMethod(), 'Should have sent a POST request to the HubSpot API');

    $this->assertEquals(
      '/crm/v3/objects/contacts/batch/create',
      $request->getUri()->getPath(),
      'Should have sent a request to the HubSpot API batch create endpoint'
    );

    $this->assertEquals(
      [ 'inputs' => array_map(fn ($contact) => [ 'properties' => self::mapToHubspotProps($contact) ], $contacts) ],
      json_decode((string) $request->getBody(), TRUE),
      'Should have sent the expected payload to the HubSpot API'
    );

    foreach ($this->queryContacts() as $contact) {
      $this->assertIsNumeric($contact['hubspot_id'], 'The returned HubSpot contact ID should have been saved');
      $this->assertFalse($contact['has_changes'], 'The "has_changes" flag should have been reset');
      $this->assertEquals(self::OWNER_COUNTRY, $contact['owned_by'], 'The contact should be owned by ' . self::OWNER_COUNTRY);
      $this->assertEquals(0, $contact['ownership_score'], 'The initial ownership score should be 0');
      $this->assertEqualsWithDelta(time(), strtotime($contact['last_sync_date']), 24 * 60 * 60, 'The timestamp of the last sync should have been updated');
      $this->assertFalse($contact['last_sync_failed'], 'The "last_sync_failed" flag should be set to FALSE');

      $this->assertEquals(
        [
          'firstname'         => $contact['first_name'],
          'lastname'          => $contact['last_name'],
          'date_of_birth'     => $contact['birth_date'],
          'email'             => $contact['email'],
          'owned_by'          => self::OWNER_COUNTRY,
          'ownership_score'   => $contact['ownership_score'],
          'civicrm_id'        => $contact['id'],
          'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
        ],
        json_decode($contact['last_sync_payload'], TRUE),
        'The payload of the latest sync should contain the expected values'
      );
    }
  }

  public function testSuccessfulUpdate(): void {
    foreach ($this->contactIds as $contact_id) {
      Api4\Contact::update(FALSE)
        ->addValue('hubspot_sync.hubspot_id', self::generateHubspotId())
        ->addValue('hubspot_sync.has_changes', TRUE)
        ->addValue('hubspot_sync.ownership_score', 10)
        ->addValue('hubspot_sync.last_sync_date', date('Y-m-d H:i:s', strtotime('last week')))
        ->addWhere('id', '=', $contact_id)
        ->execute();
    }

    $contacts = $this->queryContacts();

    $this->mockHandler->append(new Response(
      200,
      [ 'Content-Type' => 'application/json' ],
      json_encode([
        'results' => array_map(
          fn ($contact) => [
            'id' => $contact['hubspot_id'],
            'properties' => self::mapToHubspotProps($contact),
          ],
          $this->queryContacts()
        ),
      ])
    ));

    $schedule_result = (array) civicrm_api4('Hubspot', 'sync', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['contact_type', '=', 'Individual'],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 0,
      'scheduledForUpdate' => count($contacts),
    ], $schedule_result);

    $this->processQueueItems('hubspot-sync-update-contacts');

    $request = array_shift($this->historyContainer)['request'];

    $this->assertEquals('POST', $request->getMethod(), 'Should have sent a POST request to the HubSpot API');

    $this->assertEquals(
      '/crm/v3/objects/contacts/batch/update',
      $request->getUri()->getPath(),
      'Should have sent a request to the HubSpot API batch update endpoint'
    );

    $this->assertEquals(
      [
        'inputs' => array_map(fn ($contact) => [
          'id'         => $contact['hubspot_id'],
          'properties' => self::mapToHubspotProps($contact),
        ], $contacts),
      ],
      json_decode((string) $request->getBody(), TRUE),
      'Should have sent the expected payload to the HubSpot API'
    );

    foreach ($this->queryContacts() as $contact) {
      $this->assertFalse($contact['has_changes'], 'The "has_changes" flag should have been reset');
      $this->assertEquals(self::OWNER_COUNTRY, $contact['owned_by'], 'The contact should still be owned by ' . self::OWNER_COUNTRY);
      $this->assertEquals(10, $contact['ownership_score'], 'The ownership score should have been updated');
      $this->assertEqualsWithDelta(time(), strtotime($contact['last_sync_date']), 24 * 60 * 60, 'The timestamp of the last sync should have been updated');
      $this->assertFalse($contact['last_sync_failed'], 'The "last_sync_failed" flag should be set to FALSE');

      $this->assertEquals(
        [
          'firstname'         => $contact['first_name'],
          'lastname'          => $contact['last_name'],
          'date_of_birth'     => $contact['birth_date'],
          'email'             => $contact['email'],
          'owned_by'          => self::OWNER_COUNTRY,
          'ownership_score'   => $contact['ownership_score'],
          'civicrm_id'        => $contact['id'],
          'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
        ],
        json_decode($contact['last_sync_payload'], TRUE),
        'The payload of the latest sync should contain the expected values'
      );
    }
  }

  public function testEmailConflict(): void {
    $contact_id = $this->contactIds[0];
    $contact = $this->queryContacts([$contact_id])[0];
    $hubspot_id = self::generateHubspotId();

    $this->mockHandler->append(new Response(
      400,
      [ 'Content-Type' => 'application/json' ],
      json_encode([
        'status'   => 'error',
        'message'  => '...',
        'category' => 'VALIDATION_ERROR',
      ])
    ));

    $this->mockHandler->append(new Response(
      200,
      [ 'Content-Type' => 'application/json' ],
      json_encode([
        'id' => self::generateHubspotId(),
        'properties' => [
          'email'           => $contact['email'],
          'owned_by'        => 'BG',
          'ownership_score' => 50,
        ],
      ])
    ));

    $this->mockHandler->append(new Response(
      201,
      [ 'Content-Type' => 'application/json' ],
      json_encode([
        'id' => $hubspot_id,
        'properties' => [
          'civicrm_id'      => $contact_id,
          'email'           => $contact['email'],
          'owned_by'        => 'AT',
          'ownership_score' => 60,
          'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact_id,
        ],
      ])
    ));

    $schedule_result = (array) civicrm_api4('Hubspot', 'sync', [
      'select' => [
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['id', '=', $contact_id],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 1,
      'scheduledForUpdate' => 0,
    ], $schedule_result);

    $this->processQueueItems('hubspot-sync-create-contacts');

    $get_primary_email_owner_req = $this->historyContainer[1]['request'];

    $this->assertEquals('GET', $get_primary_email_owner_req->getMethod(), 'Should have sent a GET request to the HubSpot API');

    $this->assertEquals(
      "/crm/v3/objects/contacts/{$contact['email']}",
      $get_primary_email_owner_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API get contact endpoint'
    );

    $create_contact_req = $this->historyContainer[2]['request'];

    $this->assertEquals('POST', $create_contact_req->getMethod(), 'Should have sent a POST request to the HubSpot API');

    $this->assertEquals(
      '/crm/v3/objects/contacts',
      $create_contact_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API update contact endpoint'
    );

    $contact = $this->queryContacts([$contact_id])[0];

    $this->assertIsNumeric($contact['hubspot_id'], 'The returned HubSpot contact ID should have been saved');
    $this->assertFalse($contact['has_changes'], 'The "has_changes" flag should have been reset');
    $this->assertEquals('BG', $contact['owned_by'], 'The contact should be owned by Bulgaria');
    $this->assertEquals(0, $contact['ownership_score'], 'The ownership score should be 0');
    $this->assertEqualsWithDelta(time(), strtotime($contact['last_sync_date']), 24 * 60 * 60, 'The timestamp of the last sync should have been updated');
    $this->assertFalse($contact['last_sync_failed'], 'The "last_sync_failed" flag should be set to FALSE');

    $this->assertEquals(
      [
        'owned_by'          => self::OWNER_COUNTRY,
        'ownership_score'   => $contact['ownership_score'],
        'civicrm_id'        => $contact['id'],
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      ],
      json_decode($contact['last_sync_payload'], TRUE),
      'The payload of the latest sync attempt should contain the expected values'
    );
  }

  public function testUpdateNonExistentContacts(): void {
    // ...
  }

  public function testRateLimitError(): void {
    // ...
  }

}
