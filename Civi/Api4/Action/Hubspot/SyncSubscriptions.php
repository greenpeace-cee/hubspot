<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Core_Session as Session;
use CRM_Hubspot_ApiClient as ApiClient;
use CRM_Hubspot_BatchSubscriptionUpdater as BatchSubscriptionUpdater;
use DateTimeImmutable;

/**
 * Sync contact subscriptions to HubSpot
 */
class SyncSubscriptions extends Api4\Generic\AbstractAction {

  const SUBSCRIPTION_STATUS_EVENT = 'e_updated_email_subscription_status_v2';

  private static array $hubspotSubscriptionDefinitions;
  private static array $subscriptions;

  public function _run(Api4\Generic\Result $result) {
    $result['received'] = 0;
    $result['sent'] = 0;

    $latest_sync_activity = Api4\Activity::get(FALSE)
      ->addSelect('activity_date_time')
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->addOrderBy('activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $from = new DateTimeImmutable($latest_sync_activity['activity_date_time'] ?? '@0');

    foreach (self::getSubscriptionEvents($from) as $event) {
      $contact_id = self::resolveHubspotContactId($event['hubspot_contact_id']);

      if (is_null($contact_id)) continue;

      $subscription_id = self::resolveHubspotSubscriptionId($event['hubspot_subscription_id']);

      if (is_null($subscription_id)) {
        $subscription_id = self::importSubscription($event['hubspot_subscription_id']);
      }

      if (in_array($event['change'], ['SUBSCRIBED', 'UNSUBSCRIBED'])) {
          $status = $event['change'] === 'SUBSCRIBED' ? 'opt_in' : 'opt_out';

          Api4\SubscriptionContact::save(FALSE)
            ->addRecord([
              'contact_id'               => $contact_id,
              'subscription_id'          => $subscription_id,
              'subscription_status:name' => $status,
            ])
            ->setMatch(['contact_id', 'subscription_id'])
            ->execute();

          $result['received']++;
      }
    }

    $subscription_updater = new BatchSubscriptionUpdater([ 'queue_name' => 'hubspot-sync-subscriptions' ]);

    foreach (self::getRecentSubscriptionChanges($from) as $contact_subscription) {
      $status = $contact_subscription['status'] === 'opt_in' ? 'SUBSCRIBED' : 'UNSUBSCRIBED';

      $subscription_updater->add([
        'channel'            => 'EMAIL',
        'subscriberIdString' => $contact_subscription['email'],
        'subscriptionId'     => $contact_subscription['subscription'],
        'statusState'        => $status,
      ]);

      $result['sent']++;
    }

    Api4\Activity::create(FALSE)
      ->addValue('activity_type_id:name', 'hubspot_subscription_sync')
      ->addValue('source_contact_id',     Session::getLoggedInContactId())
      ->addValue('subject',               "Received: {$result['received']}, Sent: {$result['sent']}")
      ->execute();

    $subscription_updater->flush();
  }

  private static function getRecentSubscriptionChanges(DateTimeImmutable $from) {
    $page_size = 100;
    $offset = 0;

    while (TRUE) {
      $subscription_contact_results = Api4\SubscriptionContact::get(FALSE)
        ->addSelect(
          'CONCAT(contact_id.hubspot_sync.email) AS email',
          'CONCAT(subscription_id.hubspot_id) AS subscription',
          'subscription_status:name'
        )
        ->addWhere('modified_date', '>', $from->format('Y-m-d H:i:s'))
        ->setLimit($page_size)
        ->setOffset($offset)
        ->execute();

      if ($subscription_contact_results->countFetched() === 0) return;

      foreach ($subscription_contact_results as $contact_subscription) {
        $contact_subscription['status'] = $contact_subscription['subscription_status:name'];
        unset($contact_subscription['subscription_status:name']);

        yield $contact_subscription;
      }

      $offset += $page_size;
    }
  }

  private static function getSubscriptionEvents(DateTimeImmutable $from) {
    while (TRUE) {
      $events_result = ApiClient::getEvents([
        'after'          => $page ?? NULL,
        'eventType'      => self::SUBSCRIPTION_STATUS_EVENT,
        'limit'          => 10,
        'occurredAfter'  => $from->format('c'),
        'sort'           => 'occurredAt',
      ]);

      $event_page = json_decode((string) $events_result->getBody(), TRUE);
      $results = $event_page['results'];

      if (empty($results)) break;

      $latest_time = self::toTimestampMilliseconds(end($results)['occurredAt']);
      $earliest_time = self::toTimestampMilliseconds(reset($results)['occurredAt']);

      $events = array_reduce(
        $results,
        fn ($events, $event) => $events + [
          $event['id'] => [
            'hubspot_contact_id' => $event['objectId'],
            'date'               => $event['occurredAt'],
          ],
        ],
        []
      );

      $timeline_response = ApiClient::getSubscriptionsTimeline($earliest_time, $latest_time);
      $timeline = json_decode((string) $timeline_response->getBody(), TRUE)['timeline'];

      foreach ($timeline as $item) {
        foreach ($item['changes'] as $change) {
          $event_id = $change['causedByEvent']['id'];

          $events[$event_id] += [
            'hubspot_subscription_id' => $change['subscriptionId'],
            'change'                  => $change['change'],
          ];
        }
      }

      $contacts_response = ApiClient::batchGetContacts(
        array_values(array_map(fn ($event) => $event['hubspot_contact_id'], $events)),
        ['email']
      );

      $hubspot_contact_emails = array_reduce(
        json_decode((string) $contacts_response->getBody(), TRUE)['results'],
        fn ($emails, $result) => $emails + [ $result['id'] => $result['properties']['email'] ],
        []
      );

      foreach ($events as $event) {
        $email = $hubspot_contact_emails[$event['hubspot_contact_id']] ?? NULL;

        if (empty($email)) continue;

        yield $event;
      }

      if (!isset($event_page['paging']['next'])) break;

      $page = $event_page['paging']['next']['after'] ?? '';
    }
  }

  private static function importSubscription(string $hubspot_subscription_id): int {
    if (!isset(self::$hubspotSubscriptionDefinitions)) {
      $response = ApiClient::getSubscriptionDefinitions();
      self::$hubspotSubscriptionDefinitions = json_decode((string) $response->getBody(), TRUE)['subscriptionDefinitions'];
    }

    foreach (self::$hubspotSubscriptionDefinitions as $subscription) {
      if ($subscription['id'] !== $hubspot_subscription_id) continue;

       return (int) Api4\Subscription::create(FALSE)
        ->addValue('hubspot_id',  $hubspot_subscription_id)
        ->addValue('name',        strtolower(preg_replace('/[^\w]+/', '_', $subscription['name'])))
        ->addValue('title',       $subscription['name'])
        ->addValue('description', $subscription['description'])
        ->execute()
        ->first()['id'];
    }
  }

  private static function resolveHubspotContactId(string $hubspot_contact_id): ?int {
    return Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('hubspot_sync.hubspot_id', '=', $hubspot_contact_id)
      ->setLimit(1)
      ->execute()
      ->first()['id'] ?? NULL;
  }

  private static function resolveHubspotSubscriptionId(string $hubspot_subscription_id): ?int {
    if (isset(self::$subscriptions[$hubspot_subscription_id])) {
      return self::$subscriptions[$hubspot_subscription_id];
    }

    $subscription = Api4\Subscription::get(FALSE)
      ->addSelect('id')
      ->addWhere('hubspot_id', '=', $hubspot_subscription_id)
      ->setLimit(1)
      ->execute()
      ->first();

    if (is_null($subscription)) return NULL;

    self::$subscriptions[$hubspot_subscription_id] = $subscription['id'];

    return $subscription['id'];
  }

  private static function toTimestampMilliseconds(string|DateTimeImmutable $datetime): int {
    if (is_string($datetime)) {
      $datetime = new DateTimeImmutable($datetime);
    }

    return $datetime->getTimestamp() * 1000 + ((int) $datetime->format('v'));
  }

}
