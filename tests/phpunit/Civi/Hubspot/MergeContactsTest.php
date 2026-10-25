<?php

declare(strict_types = 1);
namespace Civi\Hubspot;

use Civi\Api4;
use Civi\Api4\Action\Hubspot\TestBase;

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

}
