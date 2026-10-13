<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Core_Session as Session;
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
    parent::setUp();

    self::$subscriptions = (array) Api4\Subscription::save(FALSE)
      ->addRecord([
        'name'        => "community_newsletter",
        'title'       => "GPAT - Community NL",
        'description' => "Greenpeace AT Community Newsletter",
        'hubspot_id'  => MockResponses::generateHubspotId(),
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
    $contacts = array_slice(self::loadAllContacts(['id', 'hubspot_sync.*']), 0, 4);

    $subscription_sync_activities = (array) Api4\Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute();

    $this->assertEquals([], $subscription_sync_activities);

    $subscription_contacts = Api4\SubscriptionContact::get(FALSE)->selectRowCount()->execute();
    $this->assertEquals(0, $subscription_contacts->rowCount);

    Api4\SubscriptionContact::save(FALSE)
      ->addRecord([
        'contact_id' => $contacts[2]['id'],
        'subscription_id' => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ])
      ->addRecord([
        'contact_id' => $contacts[3]['id'],
        'subscription_id' => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ])
      ->execute();

    $events = [
      [
        'id'        => self::generateUUID(),
        'type'      => 'e_updated_email_subscription_status_v2',
        'change'    => 'SUBSCRIBED',
        'object_id' => $contacts[0]['hubspot_sync.hubspot_id'],
        'timestamp' => strtotime('yesterday'),
      ],
      [
        'id'        => self::generateUUID(),
        'type'      => 'e_updated_email_subscription_status_v2',
        'change'    => 'SUBSCRIBED',
        'object_id' => $contacts[1]['hubspot_sync.hubspot_id'],
        'timestamp' => strtotime('2 days ago'),
      ],
      [
        'id'        => self::generateUUID(),
        'type'      => 'e_updated_email_subscription_status_v2',
        'change'    => 'UNSUBSCRIBED',
        'object_id' => $contacts[2]['hubspot_sync.hubspot_id'],
        'timestamp' => strtotime('3 days ago'),
      ],
    ];

    self::$mockHandler->append(MockResponses::getEvents(200, [ 'events' => $events ]));

    self::$mockHandler->append(MockResponses::getSubscriptionsTimeline(200, [
      'items' => array_map(
        fn ($i) => [
          'change'          => $events[$i]['change'],
          'event_id'        => $events[$i]['id'],
          'email'           => $contacts[$i]['hubspot_sync.email'],
          'subscription_id' => self::$subscriptions[0]['hubspot_id'],
          'timestamp'       => $events[$i]['timestamp'],
        ],
        range(2, 0)
      )
    ]));

    self::$mockHandler->append(MockResponses::batchGetContacts(200, [
      'contacts' => array_map(fn ($contact) => [
        'hubspot_id' => $contact['hubspot_sync.hubspot_id'],
        'email'      => $contact['hubspot_sync.email'],
      ], $contacts),
    ]));

    $sync_result = (array) Api4\Hubspot::syncSubscriptions(FALSE)->execute();

    $this->assertEquals([
      'received' => 3,
      'sent'     => 4,
    ], $sync_result);

    self::$mockHandler->append(MockResponses::batchUpdateSubscriptionStatus(200));

    $this->processQueueItems('hubspot-sync-subscriptions');

    $subscription_sync_activities = (array) Api4\Activity::get(FALSE)
      ->addSelect('source_contact_id', 'subject')
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute();

    $this->assertEquals([
      [
        'id'                => $subscription_sync_activities[0]['id'],
        'source_contact_id' => Session::getLoggedInContactId(),
        'subject'           => 'Received: 3, Sent: 4',
      ],
    ], $subscription_sync_activities);

    $subscription_contacts = (array) Api4\SubscriptionContact::get(FALSE)
      ->addSelect('contact_id', 'subscription_id', 'subscription_status:name')
      ->addOrderBy('contact_id', 'ASC')
      ->execute();

    $this->assertEquals(4, count($subscription_contacts));

    $this->assertEquals([
      [
        'id'                       => $subscription_contacts[0]['id'],
        'contact_id'               => $contacts[0]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ],
      [
        'id'                       => $subscription_contacts[1]['id'],
        'contact_id'               => $contacts[1]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ],
      [
        'id'                       => $subscription_contacts[2]['id'],
        'contact_id'               => $contacts[2]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_out',
      ],
      [
        'id'                       => $subscription_contacts[3]['id'],
        'contact_id'               => $contacts[3]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ],
    ], $subscription_contacts);
  }

}
