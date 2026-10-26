<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_BatchSubscriptionAuditor as BatchSubscriptionAuditor;
use CRM_Hubspot_SyncGuard;

/**
 * Compare the subscriptions of a random sample of contacts with HubSpot and log differences.
 */
class AuditSubscriptions extends Api4\Generic\AbstractAction {

  use \CRM_Hubspot_AuditSampleTrait;
  use \CRM_Hubspot_AuditSyncStateTrait;

  const QUEUE = 'hubspot-audit-subscriptions';

  public function _run(Api4\Generic\Result $result) {
    $result['scheduled'] = 0;

    // Unsent sync items would show up as differences, as the sync activity is recorded before they are sent
    CRM_Hubspot_SyncGuard::run('audit-subscriptions', [self::QUEUE, SyncSubscriptions::QUEUE], fn () => $this->audit($result));
  }

  private function audit(Api4\Generic\Result $result): void {
    $hubspot_subscription_ids = Api4\Subscription::get(FALSE)
      ->addSelect('hubspot_id')
      ->addWhere('hubspot_id', 'IS NOT EMPTY')
      ->execute()
      ->column('hubspot_id');

    // Same reference point SyncSubscriptions uses: local changes after its last run are still pending
    $last_subscription_sync = Api4\Activity::get(FALSE)
      ->addSelect('activity_date_time')
      ->addWhere('activity_type_id:name', '=', 'hubspot_subscription_sync')
      ->addOrderBy('activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first()['activity_date_time'] ?? '1970-01-01 00:00:00';

    $subscription_auditor = new BatchSubscriptionAuditor(['queue_name' => self::QUEUE]);

    $where = [
      ['hubspot_sync.hubspot_id', 'IS NOT EMPTY'],
      ['hubspot_sync.email', 'IS NOT EMPTY'],
      ['hubspot_sync.sync_status:name', '=', 'successful'],
    ];

    foreach ($this->sampleContactIds($where) as $contact_ids) {
      $contacts = Api4\Contact::get(FALSE)
        ->addSelect('id', 'hubspot_sync.email', 'hubspot_sync.last_sync_date')
        ->addWhere('id', 'IN', $contact_ids)
        ->execute();

      // Subscriptions without a local record are expected to be unset in HubSpot as well
      $statuses = array_fill_keys(
        $contact_ids,
        array_fill_keys($hubspot_subscription_ids, 'NOT_SPECIFIED')
      );

      $subscription_contacts = Api4\SubscriptionContact::get(FALSE)
        ->addSelect('contact_id', 'subscription_id.hubspot_id', 'subscription_status:name')
        ->addWhere('contact_id', 'IN', $contact_ids)
        ->addWhere('subscription_id.hubspot_id', 'IS NOT EMPTY')
        ->execute();

      foreach ($subscription_contacts as $subscription_contact) {
        // Same mapping SyncSubscriptions uses
        $statuses[$subscription_contact['contact_id']][$subscription_contact['subscription_id.hubspot_id']] =
          $subscription_contact['subscription_status:name'] === 'opt_in' ? 'SUBSCRIBED' : 'UNSUBSCRIBED';
      }

      $pending_contact_ids = self::contactsWithSubscriptionChangesSince($contact_ids, $last_subscription_sync);

      foreach ($contacts as $contact) {
        if (in_array($contact['id'], $pending_contact_ids)) {
          continue;
        }

        $subscription_auditor->add([
          'civicrm_id'             => $contact['id'],
          'last_sync_date'         => $contact['hubspot_sync.last_sync_date'],
          'last_subscription_sync' => $last_subscription_sync,
          'email'                  => $contact['hubspot_sync.email'],
          'statuses'               => $statuses[$contact['id']],
        ]);

        $result['scheduled']++;
      }
    }

    $subscription_auditor->flush();
  }

}
