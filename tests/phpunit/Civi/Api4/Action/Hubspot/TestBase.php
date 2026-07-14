<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use CRM_Hubspot_ApiClient;
use CRM_Hubspot_CountryIsoResolverTrait as CountryIsoResolverTrait;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;

class TestBase extends TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use CountryIsoResolverTrait;

  const HUBSPOT_ACCOUNT_ID = 19946500;
  const OWNER_COUNTRY = 'AT';

  private static array $_contactIds;
  private static array $_historyContainer;
  protected static MockHandler $mockHandler;

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()->installMe(__DIR__)->apply();
  }

  public static function setUpBeforeClass(): void {
    Test::headless()->installMe(__DIR__)->apply(TRUE);

    self::$mockHandler = new MockHandler();
    self::$_historyContainer = [];
    $history_mw = Middleware::history(self::$_historyContainer);
    $handler_stack = HandlerStack::create(self::$mockHandler);
    $handler_stack->push($history_mw);
    CRM_Hubspot_ApiClient::$handlerStack = $handler_stack;

    Api4\HubspotAccount::create(FALSE)
      ->addValue('account_id', self::HUBSPOT_ACCOUNT_ID)
      ->addValue('name', 'Test account GPCEE')
      ->addValue('base_uri', 'https://api.hubapi.com')
      ->addValue('api_key', 'pat-abc-00000000-1111-2222-3333-444444444444')
      ->addValue('owner_country', self::getCountryId(self::OWNER_COUNTRY))
      ->execute()
      ->first();

    foreach (range(1, 10) as $i) {
      self::$_contactIds[] = (int) Api4\Contact::create(FALSE)
        ->addValue('contact_type',                   'Individual')
        ->addValue('first_name',                     'Contact')
        ->addValue('last_name',                      "#$i")
        ->addValue('birth_date',                     date('Y-m-d', random_int(0, pow(10, 9))))
        ->addValue('hubspot_sync.email',             "contact-$i@example.org")
        ->addValue('hubspot_sync.owned_by:abbr',     self::OWNER_COUNTRY)
        ->execute()
        ->first()['id'];
    }

  }

  public function tearDown(): void {
    self::$_historyContainer = [];
    self::$mockHandler->reset();

    parent::tearDown();
  }

  protected static function loadAllContacts(array $select = ['*']): array {
    return (array) civicrm_api4('Contact', 'get', [
      'select' => $select,
      'where' => [
        ['id', 'IN', self::$_contactIds],
      ],
      'orderBy' => [ 'id' => 'ASC' ],
      'limit' => count(self::$_contactIds),
      'checkPermissions' => FALSE,
    ]);
  }

  protected static function loadSingleContact(int $contact_id, array $select = ['*']): ?array {
    return civicrm_api4('Contact', 'get', [
      'select' => $select,
      'where' => [
        ['id', '=', $contact_id],
      ],
      'limit' => 1,
      'checkPermissions' => FALSE,
    ])->first();
  }

  protected function processQueueItems(string $queue_name, string $expected_outcome = 'ok'): void {
    $queue_result = Api4\Queue::runItems(FALSE)
      ->setQueue($queue_name)
      ->execute()
      ->first();

    $this->assertEquals($expected_outcome, $queue_result['outcome'], "The outcome of the queue runner should be '$expected_outcome'");

    if ($expected_outcome === 'ok') {
      $queue_items = (array) Api4\QueueItem::get(FALSE)
        ->addWhere('queue_name', '=', $queue_name)
        ->execute();

      $this->assertEmpty($queue_items, 'All queue items should have been processed');
    }
  }

  protected static function shiftHistory(int $n = 1): array {
    while ($n > 1) {
      array_shift(self::$_historyContainer);
      $n--;
    }

    return array_shift(self::$_historyContainer);
  }

}
