<?php

declare(strict_types = 1);
namespace Civi\Hubspot;

use Civi\Api4;
use Civi\Api4\Action\Hubspot\MockResponses;
use Civi\Api4\Action\Hubspot\TestBase;
use CRM_Queue_TaskContext;

/**
 * @group headless
 */
class MergeContactsTest extends TestBase {

  public function testMergeSubscriptions(): void {
    // subscription => [primary's status, duplicate's status, expected status]
    $cases = [
      'primary_opt_out_duplicate_opt_in' => ['opt_out', 'opt_in', 'opt_in'],
      'primary_opt_in_duplicate_opt_out' => ['opt_in', 'opt_out', 'opt_in'],
      'both_opt_in' => ['opt_in', 'opt_in', 'opt_in'],
      'both_opt_out' => ['opt_out', 'opt_out', 'opt_out'],
      'primary_only' => ['opt_out', NULL, 'opt_out'],
      'duplicate_only' => [NULL, 'opt_in', 'opt_in'],
    ];

    [$primary_id, $duplicate_id] = array_map(
      fn () => Api4\Contact::create(FALSE)
        ->addValue('contact_type', 'Individual')
        ->addValue('first_name', 'Merge')
        ->addValue('last_name', 'Test')
        ->execute()
        ->first()['id'],
      range(1, 2)
    );

    $subscription_ids = [];
    $kept_row_ids = [];

    foreach ($cases as $name => [$primary_status, $duplicate_status]) {
      $subscription_ids[$name] = Api4\Subscription::create(FALSE)
        ->addValue('name', $name)
        ->addValue('title', $name)
        ->execute()
        ->first()['id'];

      $row_ids = [];

      foreach ([$primary_id => $primary_status, $duplicate_id => $duplicate_status] as $contact_id => $status) {
        if (is_null($status)) {
          continue;
        }

        $row_ids[$contact_id] = Api4\SubscriptionContact::create(FALSE)
          ->addValue('contact_id', $contact_id)
          ->addValue('subscription_id', $subscription_ids[$name])
          ->addValue('subscription_status:name', $status)
          ->execute()
          ->first()['id'];
      }

      // the primary's row is kept if it has one
      $kept_row_ids[$name] = $row_ids[$primary_id] ?? $row_ids[$duplicate_id];
    }

    $merge_result = Api4\Contact::mergeDuplicates(FALSE)
      ->setContactId($primary_id)
      ->setDuplicateId($duplicate_id)
      ->execute()
      ->first();

    $this->assertCount(1, $merge_result['merged']);

    $merged_subscriptions = Api4\SubscriptionContact::get(FALSE)
      ->addSelect('id', 'contact_id', 'subscription_id', 'subscription_status:name')
      ->addWhere('subscription_id', 'IN', array_values($subscription_ids))
      ->execute();

    // Exactly one row per subscription, none left on the duplicate
    $this->assertCount(count($cases), $merged_subscriptions);
    $merged_subscriptions = $merged_subscriptions->indexBy('subscription_id');
    $this->assertCount(count($cases), $merged_subscriptions);

    foreach ($cases as $name => [, , $expected_status]) {
      $row = $merged_subscriptions[$subscription_ids[$name]];
      $this->assertEquals($kept_row_ids[$name], $row['id'], "Kept row for $name");
      $this->assertEquals($primary_id, $row['contact_id'], "Contact for $name");
      $this->assertEquals($expected_status, $row['subscription_status:name'], "Status for $name");
    }
  }

  public function testSyncRecordsAreNotMerged(): void {
    $primary_hubspot_id = MockResponses::generateHubspotId();
    $duplicate_hubspot_id = MockResponses::generateHubspotId();

    // Conflicting HubSpot IDs must not prevent a safe merge,
    // and the primary's empty sync email must not be filled in
    $primary_id = self::createContact($primary_hubspot_id, NULL);
    $duplicate_id = self::createContact($duplicate_hubspot_id, 'duplicate@example.org');

    $result = civicrm_api3('Contact', 'merge', [
      'to_keep_id'   => $primary_id,
      'to_remove_id' => $duplicate_id,
      'mode'         => 'safe',
    ]);
    $this->assertCount(1, $result['values']['merged'], 'The contacts should have been merged');

    $primary = self::loadSingleContact($primary_id, ['hubspot_sync.hubspot_id', 'hubspot_sync.email']);
    $this->assertEquals($primary_hubspot_id, $primary['hubspot_sync.hubspot_id']);
    $this->assertNull($primary['hubspot_sync.email']);

    $duplicate = self::loadSingleContact($duplicate_id, ['hubspot_sync.hubspot_id']);
    $this->assertEquals($duplicate_hubspot_id, $duplicate['hubspot_sync.hubspot_id']);
  }

  public function testPrimaryTakesOverDuplicatesHubspotContact(): void {
    $hubspot_id = MockResponses::generateHubspotId();

    // The primary has no sync record at all
    $primary_id = (int) Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Merge')
      ->addValue('last_name', 'Candidate')
      ->execute()
      ->first()['id'];

    $duplicate_id = self::createContact($hubspot_id, NULL);

    civicrm_api3('Contact', 'merge', [
      'to_keep_id'   => $primary_id,
      'to_remove_id' => $duplicate_id,
    ]);

    // No mock response is queued, so a merge request to HubSpot would throw
    MergeContacts::mergeHubspotContacts(new CRM_Queue_TaskContext(), $primary_id, $duplicate_id);

    $primary = self::loadSingleContact($primary_id, ['hubspot_sync.hubspot_id']);
    $this->assertEquals($hubspot_id, $primary['hubspot_sync.hubspot_id']);

    $duplicate = self::loadSingleContact($duplicate_id, ['hubspot_sync.sync_status']);
    $this->assertEquals('merged', $duplicate['hubspot_sync.sync_status']);

    // Running the task again must not merge the HubSpot contact into itself
    MergeContacts::mergeHubspotContacts(new CRM_Queue_TaskContext(), $primary_id, $duplicate_id);
  }

  private static function createContact(string $hubspot_id, ?string $email): int {
    return (int) Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Merge')
      ->addValue('last_name', 'Candidate')
      ->addValue('hubspot_sync.hubspot_id', $hubspot_id)
      ->addValue('hubspot_sync.email', $email)
      ->execute()
      ->first()['id'];
  }

}
