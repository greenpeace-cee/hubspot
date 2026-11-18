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

    // Running a queue commits (see TestBase::setUp), so subscriptions and sync activities can outlive their test
    Api4\SubscriptionContact::delete(FALSE)->addWhere('id', '>', 0)->execute();

    Api4\Activity::delete(FALSE)
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute();

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
        ->addValue('hubspot_sync.sync_status', 'successful')
        ->addWhere('id', '=', $contact['id'])
        ->execute();
    }
  }

  public function testSyncSubscriptions(): void {
    $contacts = array_slice(self::loadAllContacts(['id', 'hubspot_sync.*']), 0, 6);

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
      ->addRecord([
        'contact_id' => $contacts[4]['id'],
        'subscription_id' => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ])
      ->addRecord([
        'contact_id' => $contacts[5]['id'],
        'subscription_id' => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_out',
      ])
      ->execute();

    // Only changes made before the sync starts are sent, and modified_date has a resolution of one second
    \CRM_Core_DAO::executeQuery('UPDATE civicrm_subscription_contact SET modified_date = modified_date - INTERVAL 1 MINUTE');

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

    // Two pages of events: [0, 1] and [2]
    foreach ([[0, 1], [2]] as $page => $event_indexes) {
      self::$mockHandler->append(MockResponses::getEvents(200, [
        'events' => array_map(fn ($i) => $events[$i], $event_indexes),
        'after'  => $page === 0 ? 'page-2-cursor' : NULL,
      ]));

      // One timeline item per request, so the first page of events needs two timeline pages
      foreach (array_reverse($event_indexes) as $n => $i) {
        self::$mockHandler->append(MockResponses::getSubscriptionsTimeline(200, [
          'items' => [
            [
              'change'          => $events[$i]['change'],
              'event_id'        => $events[$i]['id'],
              'email'           => $contacts[$i]['hubspot_sync.email'],
              'subscription_id' => self::$subscriptions[0]['hubspot_id'],
              'timestamp'       => $events[$i]['timestamp'],
            ],
          ],
          'offset' => $n < count($event_indexes) - 1 ? 'timeline-page-2-offset' : NULL,
        ]));
      }

      self::$mockHandler->append(MockResponses::batchGetContacts(200, [
        'contacts' => array_map(fn ($i) => [
          'hubspot_id' => $contacts[$i]['hubspot_sync.hubspot_id'],
          'email'      => $contacts[$i]['hubspot_sync.email'],
        ], $event_indexes),
      ]));
    }

    $sync_result = (array) Api4\Hubspot::syncSubscriptions(FALSE)
      ->setEventPageSize(2)
      ->setTimelinePageSize(1)
      ->setSubscriptionPageSize(2)
      ->execute();

    $this->assertEquals(0, self::$mockHandler->count(), 'Both pages of events should have been requested');

    parse_str(self::shiftHistory()['request']->getUri()->getQuery(), $first_page_query);
    $this->assertEquals(2, $first_page_query['limit']);
    $this->assertArrayNotHasKey('after', $first_page_query);

    parse_str(self::shiftHistory()['request']->getUri()->getQuery(), $first_timeline_page_query);
    $this->assertEquals(1, $first_timeline_page_query['limit']);
    $this->assertArrayNotHasKey('offset', $first_timeline_page_query);

    parse_str(self::shiftHistory()['request']->getUri()->getQuery(), $second_timeline_page_query);
    $this->assertEquals(1, $second_timeline_page_query['limit']);
    $this->assertEquals('timeline-page-2-offset', $second_timeline_page_query['offset']);

    // Skip the contacts request of the first page
    parse_str(self::shiftHistory(2)['request']->getUri()->getQuery(), $second_page_query);
    $this->assertEquals(2, $second_page_query['limit']);
    $this->assertEquals('page-2-cursor', $second_page_query['after']);

    $this->assertEquals([
      'received' => 3,
      // contacts #3 to #5 (two pages): subscriptions changed by this run's events are sent by the next run
      'sent'     => 3,
    ], $sync_result);

    // HubSpot already has the status of contact #3, a different one for #4 and none for #5
    self::$mockHandler->append(MockResponses::batchGetSubscriptionStatuses(200, [
      'subscribers' => [
        [
          'email'           => $contacts[3]['hubspot_sync.email'],
          'subscription_id' => self::$subscriptions[0]['hubspot_id'],
          'status'          => 'SUBSCRIBED',
        ],
        [
          'email'           => $contacts[4]['hubspot_sync.email'],
          'subscription_id' => self::$subscriptions[0]['hubspot_id'],
          'status'          => 'UNSUBSCRIBED',
        ],
      ],
    ]));

    self::$mockHandler->append(MockResponses::batchUpdateSubscriptionStatus(200));

    $this->processQueueItems('hubspot-sync-subscriptions');

    // Only the subscriptions that differ from HubSpot are written
    $this->assertEquals(
      [
        'inputs' => [
          [
            'channel'            => 'EMAIL',
            'subscriberIdString' => $contacts[4]['hubspot_sync.email'],
            'subscriptionId'     => self::$subscriptions[0]['hubspot_id'],
            'statusState'        => 'SUBSCRIBED',
          ],
          [
            'channel'            => 'EMAIL',
            'subscriberIdString' => $contacts[5]['hubspot_sync.email'],
            'subscriptionId'     => self::$subscriptions[0]['hubspot_id'],
            'statusState'        => 'UNSUBSCRIBED',
          ],
        ],
      ],
      json_decode((string) self::$mockHandler->getLastRequest()->getBody(), TRUE)
    );

    $subscription_sync_activities = (array) Api4\Activity::get(FALSE)
      ->addSelect('source_contact_id', 'subject')
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute();

    $this->assertEquals([
      [
        'id'                => $subscription_sync_activities[0]['id'],
        'source_contact_id' => Session::getLoggedInContactId(),
        'subject'           => 'Received: 3, Sent: 3',
      ],
    ], $subscription_sync_activities);

    $subscription_contacts = (array) Api4\SubscriptionContact::get(FALSE)
      ->addSelect('contact_id', 'subscription_id', 'subscription_status:name')
      ->addOrderBy('contact_id', 'ASC')
      ->execute();

    $this->assertEquals(6, count($subscription_contacts));

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
      [
        'id'                       => $subscription_contacts[4]['id'],
        'contact_id'               => $contacts[4]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_in',
      ],
      [
        'id'                       => $subscription_contacts[5]['id'],
        'contact_id'               => $contacts[5]['id'],
        'subscription_id'          => self::$subscriptions[0]['id'],
        'subscription_status:name' => 'opt_out',
      ],
    ], $subscription_contacts);
  }

  public function testSyncWindow(): void {
    $contact = self::loadAllContacts(['id'])[0];

    // No events, but a local subscription is created while the first sync is running
    self::$mockHandler->append(function () use ($contact) {
      Api4\SubscriptionContact::create(FALSE)
        ->addValue('contact_id', $contact['id'])
        ->addValue('subscription_id', self::$subscriptions[0]['id'])
        ->addValue('subscription_status:name', 'opt_in')
        ->execute();

      return MockResponses::getEvents(200);
    });

    $first_result = (array) Api4\Hubspot::syncSubscriptions(FALSE)->execute();
    $this->assertEquals(['received' => 0, 'sent' => 0], $first_result);

    $sync_date = new DateTimeImmutable(Api4\Activity::get(FALSE)
      ->addSelect('activity_date_time')
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute()
      ->single()['activity_date_time']);

    // The first sync only asked for events up to the date it stored ...
    parse_str(self::shiftHistory()['request']->getUri()->getQuery(), $first_query);
    $this->assertEquals($sync_date->format('c'), $first_query['occurredBefore']);

    // modified_date has a resolution of one second, so move the first sync and the subscription back in time
    $sync_date = $sync_date->modify('-1 minute');

    Api4\Activity::update(FALSE)
      ->addValue('activity_date_time', $sync_date->format('Y-m-d H:i:s'))
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->execute();

    \CRM_Core_DAO::executeQuery('UPDATE civicrm_subscription_contact SET modified_date = modified_date - INTERVAL 1 MINUTE');

    // ... and the second one continues from there (with one second of overlap), picking up the subscription created in between
    self::$mockHandler->append(MockResponses::getEvents(200));

    $second_result = (array) Api4\Hubspot::syncSubscriptions(FALSE)->execute();
    $this->assertEquals(['received' => 0, 'sent' => 1], $second_result);

    parse_str(self::shiftHistory()['request']->getUri()->getQuery(), $second_query);
    $this->assertEquals($sync_date->modify('-1 second')->format('c'), $second_query['occurredAfter']);
  }

  public function testSyncSubscriptionsIgnoresMergedContacts(): void {
    [$duplicate, $primary] = self::loadAllContacts(['id', 'hubspot_sync.*']);

    // A merged duplicate keeps the HubSpot ID that its primary contact now uses
    Api4\Contact::update(FALSE)
      ->addValue('hubspot_sync.hubspot_id', $primary['hubspot_sync.hubspot_id'])
      ->addValue('hubspot_sync.sync_status', 'merged')
      ->addWhere('id', '=', $duplicate['id'])
      ->execute();

    $event = [
      'id'        => self::generateUUID(),
      'type'      => 'e_updated_email_subscription_status_v2',
      'change'    => 'SUBSCRIBED',
      'object_id' => $primary['hubspot_sync.hubspot_id'],
      'timestamp' => strtotime('yesterday'),
    ];

    self::$mockHandler->append(MockResponses::getEvents(200, [ 'events' => [$event] ]));

    self::$mockHandler->append(MockResponses::getSubscriptionsTimeline(200, [
      'items' => [
        [
          'change'          => $event['change'],
          'event_id'        => $event['id'],
          'email'           => $primary['hubspot_sync.email'],
          'subscription_id' => self::$subscriptions[0]['hubspot_id'],
          'timestamp'       => $event['timestamp'],
        ],
      ],
    ]));

    self::$mockHandler->append(MockResponses::batchGetContacts(200, [
      'contacts' => [
        [
          'hubspot_id' => $primary['hubspot_sync.hubspot_id'],
          'email'      => $primary['hubspot_sync.email'],
        ],
      ],
    ]));

    Api4\Hubspot::syncSubscriptions(FALSE)->execute();

    $subscription_contacts = Api4\SubscriptionContact::get(FALSE)
      ->addSelect('contact_id')
      ->execute()
      ->column('contact_id');

    $this->assertEquals([$primary['id']], $subscription_contacts);
  }

}
