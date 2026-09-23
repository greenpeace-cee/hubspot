<?php

namespace Civi\Hubspot;

use Civi;
use Civi\Api4;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;
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
      'hook_civicrm_merge' => 'scheduleHubspotContactMerge',
    ];
  }

  public static function scheduleHubspotContactMerge(GenericHookEvent $event): void {
    if ($event->type !== 'sqls') return;

    $primary_id = (int) $event->mainId;
    $duplicate_id = (int) $event->otherId;

    $queue = Civi::queue('hubspot-sync-merge-contacts', [
      'type'           => 'SqlParallel',
      'runner'         => 'task',
      'reset'          => TRUE,
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

    $duplicate_contact = Api4\Contact::get(FALSE)
      ->addSelect('hubspot_sync.hubspot_id')
      ->addWhere('id', '=', $duplicate_id)
      ->execute()
      ->first();

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
