<?php

namespace Civi\Hubspot;

use Civi;
use Civi\Api4;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;
use CRM_Core_PseudoConstant;
use CRM_Core_Session;
use CRM_Hubspot_ApiClient;
use CRM_Hubspot_UpdateContactSyncTableTrait as UpdateContactSyncTableTrait;
use CRM_Queue_Task;
use CRM_Queue_TaskContext;

/**
 * Merge contacts in HubSpot when the corresponding contacts in Civi are merged.
 */
class MergeContacts extends AutoSubscriber {

  use UpdateContactSyncTableTrait;

  public static function getSubscribedEvents(): array {
    return [
      'hook_civicrm_merge' => [
        ['mergeSubscriptions'],
        ['scheduleHubspotContactMerge'],
      ],
    ];
  }

  /**
   * Resolve subscriptions that both contacts have before core moves them.
   *
   * Core moves civicrm_subscription_contact rows with a plain UPDATE, which
   * fails on the unique (contact_id, subscription_id) index when both contacts
   * have the same subscription. Like core does for group memberships in
   * CRM_Contact_BAO_GroupContact::mergeGroupContact(), we keep the primary's
   * row and opt it in if the duplicate is opted in, so mapped groups and
   * subscriptions end up with the same result.
   */
  public static function mergeSubscriptions(GenericHookEvent $event): void {
    if ($event->type !== 'sqls') {
      return;
    }

    $primary_id = (int) $event->mainId;
    $duplicate_id = (int) $event->otherId;
    $opt_in = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Hubspot_BAO_SubscriptionContact',
      'subscription_status',
      'opt_in'
    );

    // Opt in the primary wherever the duplicate is opted in
    $opt_in_primary_sql = "
      UPDATE civicrm_subscription_contact primary_sc
      INNER JOIN civicrm_subscription_contact duplicate_sc
        ON duplicate_sc.subscription_id = primary_sc.subscription_id
        AND duplicate_sc.contact_id = $duplicate_id
      SET primary_sc.subscription_status = $opt_in
      WHERE primary_sc.contact_id = $primary_id
        AND primary_sc.subscription_status <> $opt_in
        AND duplicate_sc.subscription_status = $opt_in
    ";

    // Drop the duplicate's row wherever the primary has one
    $delete_duplicate_sql = "
      DELETE duplicate_sc FROM civicrm_subscription_contact duplicate_sc
      INNER JOIN civicrm_subscription_contact primary_sc
        ON primary_sc.subscription_id = duplicate_sc.subscription_id
        AND primary_sc.contact_id = $primary_id
      WHERE duplicate_sc.contact_id = $duplicate_id
    ";

    // Must run before core's UPDATE of civicrm_subscription_contact
    array_unshift($event->data, $opt_in_primary_sql, $delete_duplicate_sql);
  }

  public static function scheduleHubspotContactMerge(GenericHookEvent $event): void {
    if ($event->type !== 'sqls') return;

    $primary_id = (int) $event->mainId;
    $duplicate_id = (int) $event->otherId;

    $queue = Civi::queue('hubspot-sync-merge-contacts', [
      'type'           => 'SqlParallel',
      'runner'         => 'task',
      'reset'          => FALSE,
      'retry_interval' => 2,
      'retry_limit'    => 2,
      'error'          => 'delete',
    ]);

    $queue_task = new CRM_Queue_Task(
       [__CLASS__, 'mergeHubspotContacts'],
       [$primary_id, $duplicate_id],
       "Hubspot Contact Merge $duplicate_id -> $primary_id"
    );

    $queue_task->runAs = [
      'contactId' => CRM_Core_Session::getLoggedInContactID(),
      'domainId'  => 1,
    ];

    $queue->createItem($queue_task);
  }

  public static function mergeHubspotContacts(
    CRM_Queue_TaskContext $_context,
    int $primary_id,
    int $duplicate_id
  ): bool {
    $merge_delete_count = Api4\Activity::get(FALSE)
      ->selectRowCount()
      ->addWhere('activity_type_id:name', '=', 'Contact Deleted by Merge')
      ->addWhere('target_contact_id', 'CONTAINS', $duplicate_id)
      ->execute();

    if ($merge_delete_count->rowCount < 1) return TRUE;

    $primary_contact = Api4\Contact::get(FALSE)
      ->addSelect('hubspot_sync.hubspot_id')
      ->addWhere('id', '=', $primary_id)
      ->execute()
      ->first();

    if (empty($primary_contact['hubspot_sync.hubspot_id'])) return TRUE;

    $duplicate_contact = Api4\Contact::get(FALSE)
      ->addSelect('hubspot_sync.hubspot_id')
      ->addWhere('id', '=', $duplicate_id)
      ->execute()
      ->first();

    if (empty($duplicate_contact['hubspot_sync.hubspot_id'])) return TRUE;

    $merge_response = CRM_Hubspot_ApiClient::mergeContacts(
      $primary_contact['hubspot_sync.hubspot_id'],
      $duplicate_contact['hubspot_sync.hubspot_id']
    );

    $merge_response_body = json_decode((string) $merge_response->getBody(), TRUE);
    $new_hubspot_id = $merge_response_body['id'];

    self::updateSyncRecord([
      'entity_id'   => $primary_id,
      'hubspot_id'  => $new_hubspot_id,
      'sync_status' => 'changed',
    ]);

    self::updateSyncRecord([
      'entity_id'   => $duplicate_id,
      'sync_status' => 'merged',
    ]);

    return TRUE;
  }

}
