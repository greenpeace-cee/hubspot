<?php

namespace Civi\Api4\Action\Hubspot;

use Civi;
use Civi\Api4;
use CRM_Core_Session as Session;
use CRM_Hubspot_ApiClient as ApiClient;
use CRM_Hubspot_BatchSubscriptionUpdater as BatchSubscriptionUpdater;
use CRM_Hubspot_SyncGuard;
use DateTimeImmutable;

/**
 * Sync contact subscriptions to HubSpot
 */
class SyncSubscriptions extends Api4\Generic\AbstractAction {

  use \CRM_Hubspot_LoadHubspotAccountTrait;

  const SUBSCRIPTION_STATUS_EVENT = 'e_updated_email_subscription_status_v2';

  /**
   * Send Subscription changes to HubSpot (default, FALSE), or just apply changes from HubSpot locally?
   * Useful during testing
   *
   * @var bool
   */
  protected bool $skipApiWrites = FALSE;

  /**
   * Force-push all local subscriptions to HubSpot after fetching remote subscription changes,
   * ignoring the last sync date.
   *
   * This is intended to be used to push missed changes in previous sync windows,
   * e.g. for contacts newly-added to the hubspot sync table (if they had subscriptions prior to that)
   *
   * @var bool
   */
  protected bool $forceFromLocalSubscriptions = FALSE;

  private static array $hubspotSubscriptionDefinitions;
  private static array $subscriptions;

  const QUEUE = 'hubspot-sync-subscriptions';

  public function _run(Api4\Generic\Result $result) {
    $result['received'] = 0;
    $result['sent'] = 0;

    CRM_Hubspot_SyncGuard::run('sync-subscriptions', [self::QUEUE], fn () => $this->sync($result));
  }

  private function sync(Api4\Generic\Result $result): void {
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

      if (!isset($event['hubspot_subscription_id'])) {
        Civi::log('hubspot-sync')->warning('No subscription ID found for event: ' . json_encode($event));
        continue;
      }

      $subscription_id = self::resolveHubspotSubscriptionId($event['hubspot_subscription_id']);

      if (is_null($subscription_id)) continue;

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

    $subscription_updater = new BatchSubscriptionUpdater(['queue_name' => self::QUEUE, 'batch_size' => 100]);

    foreach (self::getRecentSubscriptionChanges($this->forceFromLocalSubscriptions ? NULL : $from) as $contact_subscription) {
      $status = $contact_subscription['status'] === 'opt_in' ? 'SUBSCRIBED' : 'UNSUBSCRIBED';

      $update = [
        'channel'            => 'EMAIL',
        'subscriberIdString' => $contact_subscription['email'],
        'subscriptionId'     => $contact_subscription['subscription'],
        'statusState'        => $status,
      ];

      if (!$this->skipApiWrites) {
        $subscription_updater->add($update);
      } else {
        Civi::log('hubspot-sync')->info('Subscription change (not sent due to skipApiWrites): ' . json_encode($update));
      }

      $result['sent']++;
    }

    // TODO: should we set a specific activity_date_time? This feels racy for long-running syncs ...
    // (e.g. set $to = now at start of sync, limit what we look at to < $to and store that date)
    Api4\Activity::create(FALSE)
      ->addValue('activity_type_id:name', 'hubspot_subscription_sync')
      ->addValue('source_contact_id',     Session::getLoggedInContactId())
      ->addValue('subject',               "Received: {$result['received']}, Sent: {$result['sent']}")
      ->execute();

    $subscription_updater->flush();
  }

  private static function getChangeEvents(int $earliest_time, int $latest_time): array {
    $change_events = [];
    $has_more = TRUE;
    $offset = NULL;

    while ($has_more) {
      $timeline_response = ApiClient::getSubscriptionsTimeline([
        'startTimestamp' => $earliest_time,
        'endTimestamp'   => $latest_time,
        'offset'         => $offset,
        'limit'          => 1000,
      ]);

      $timeline_response_body = json_decode((string) $timeline_response->getBody(), TRUE);
      $has_more = $timeline_response_body['hasMore'];
      $offset = $timeline_response_body['offset'];
      $timeline = $timeline_response_body['timeline'];

      foreach ($timeline as $item) {
        foreach ($item['changes'] as $change) {
          $event_id = $change['causedByEvent']['id'];
          $change_events[$event_id] = $change;
        }
      }
    }

    return $change_events;
  }

  private static function getRecentSubscriptionChanges(?DateTimeImmutable $from) {
    $page_size = 100;
    $offset = 0;

    while (TRUE) {
      $subscription_contact_query = Api4\SubscriptionContact::get(FALSE)
        ->addSelect(
          'CONCAT(contact_id.hubspot_sync.email) AS email',
          'CONCAT(subscription_id.hubspot_id) AS subscription',
          'subscription_status:name'
        )
        // only sync if we have an email to link the subscription to
        ->addWhere('contact_id.hubspot_sync.email', 'IS NOT EMPTY')
        ->addWhere('subscription_id.hubspot_id', 'IS NOT EMPTY')
        // not used here, but probably a good idea for the record to have a hubspot_id too
        ->addWhere('contact_id.hubspot_sync.hubspot_id', 'IS NOT NULL')
        // we don't really know which email is currently linked in hubspot unless the last status is successful
        ->addWhere('contact_id.hubspot_sync.sync_status', '=', 'successful')
        ->addWhere('contact_id.hubspot_sync.owned_by', '=', self::hubspotAccount()['owner_identifier'])
        ->addOrderBy('id', 'ASC')
        ->setLimit($page_size)
        ->setOffset($offset);

      if (!is_null($from)) {
        $subscription_contact_query->addWhere('modified_date', '>', $from->format('Y-m-d H:i:s'));
      }
      $subscription_contact_results = $subscription_contact_query->execute();

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
      $event_results = $event_page['results'];

      if (empty($event_results)) break;

      $events = array_map(
        fn ($event) => [
          'id'                 => $event['id'],
          'hubspot_contact_id' => $event['objectId'],
          'date'               => $event['occurredAt'],
        ],
        $event_results
      );

      $latest_time = self::toTimestampMilliseconds(end($events)['date']);
      $earliest_time = self::toTimestampMilliseconds(reset($events)['date']);
      $change_events = self::getChangeEvents($earliest_time, $latest_time);

      foreach ($events as $i => $event) {
        $event_id = $event['id'];

        if (!isset($change_events[$event_id])) continue;

        $events[$i]['hubspot_subscription_id'] = $change_events[$event_id]['subscriptionId'];
        $events[$i]['change'] = $change_events[$event_id]['change'];
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

  private static function toTimestampMilliseconds($datetime): int {
    if (is_string($datetime)) {
      $datetime = new DateTimeImmutable($datetime);
    }

    return $datetime->getTimestamp() * 1000 + ((int) $datetime->format('v'));
  }

}
