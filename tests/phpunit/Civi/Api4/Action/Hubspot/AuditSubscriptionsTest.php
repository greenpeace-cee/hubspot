<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use GuzzleHttp\Psr7\Response;

/**
 * @group headless
 */
class AuditSubscriptionsTest extends TestBase {

  public function testAuditSubscriptions(): void {
    $log = self::captureLog('hubspot-audit');
    $hubspot_subscription_id = MockResponses::generateHubspotId();

    $subscription = Api4\Subscription::create(FALSE)
      ->addValue('name', 'community_newsletter')
      ->addValue('title', 'GPAT - Community NL')
      ->addValue('hubspot_id', $hubspot_subscription_id)
      ->execute()
      ->first();

    $contact = self::loadAllContacts(['id', 'hubspot_sync.email'])[0];

    Api4\Contact::update(FALSE)
      ->addValue('hubspot_sync.sync_status:name', 'successful')
      ->addValue('hubspot_sync.hubspot_id', MockResponses::generateHubspotId())
      ->addWhere('id', '=', $contact['id'])
      ->execute();

    Api4\SubscriptionContact::create(FALSE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('subscription_id', $subscription['id'])
      ->addValue('subscription_status:name', 'opt_in')
      ->execute();

    // Otherwise the subscription change counts as pending and the contact is skipped
    Api4\Activity::create(FALSE)
      ->addValue('activity_type_id:name', 'hubspot_subscription_sync')
      ->addValue('source_contact_id', $contact['id'])
      ->addValue('activity_date_time', date('Y-m-d H:i:s', strtotime('+1 minute')))
      ->execute();

    // Only this contact is eligible for auditing
    Api4\Hubspot::auditSubscriptions(FALSE)->setSampleSize(1)->execute();

    self::$mockHandler->append(new Response(200, [], json_encode([
      'results' => [
        [
          'subscriberIdString' => $contact['hubspot_sync.email'],
          'statuses'           => [
            [
              'subscriptionId' => (int) $hubspot_subscription_id,
              'status'         => 'UNSUBSCRIBED',
            ],
          ],
        ],
      ],
    ])));

    $this->processQueueItems('hubspot-audit-subscriptions');

    $this->assertEquals(
      [$hubspot_subscription_id => ['civicrm' => 'SUBSCRIBED', 'hubspot' => 'UNSUBSCRIBED']],
      $log->contexts[0]['diff']
    );
  }

}
