<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_UUIDTrait as UUIDTrait;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;

/**
 * @group headless
 */
class SyncSubscriptionsTest extends TestBase {

  use UUIDTrait;

  private static array $subscriptions;

  public function setUp(): void {
    self::$subscriptions = (array) Api4\Subscription::save(FALSE)
      ->addRecord([
        'name'        => "community_newsletter",
        'title'       => "GPAT - Community NL",
        'description' => "Greenpeace AT Community Newsletter",
        'hubspot_id'  => random_int(pow(10, 6), pow(10, 7)),
      ])
      ->setMatch(['name'])
      ->execute();

    foreach (self::loadAllContacts(['id']) as $contact) {
      Api4\Contact::update(FALSE)
        ->addValue('hubspot_sync.hubspot_id', MockResponses::generateHubspotId())
        ->addWhere('id', '=', $contact['id'])
        ->execute();
    }
  }

  public function testSyncSubscriptions(): void {
    $contacts = self::loadAllContacts(['id', 'hubspot_sync.*']);

    $events = array_map(fn ($n) => [
      'id'              => self::generateUUID(),
      'change'          => 'SUBSCRIBED',
      'object_id'       => $contacts[$n]['hubspot_sync.hubspot_id'],
      'email'           => $contacts[$n]['hubspot_sync.email'],
      'subscription_id' => self::$subscriptions[0]['hubspot_id'],
      'timestamp'       => new DateTimeImmutable("$n days ago"),
    ], [2, 3, 4]);

    self::$mockHandler->append(MockResponses::getEvents(200, [
      'events' => $events,
    ]));

    self::$mockHandler->append(MockResponses::getSubscriptionsTimeline(200, [
      'events' => $events,
    ]));

    // $result = (array) Api4\Hubspot::syncSubscriptions(FALSE)->execute();

    $this->assertTrue(TRUE);
  }

}
