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
 * Delete a contact in HubSpot when its corresponding contact in Civi is deleted.
 */
class DeleteContact extends AutoSubscriber {

  use UpdateContactSyncTableTrait;

  public static function getSubscribedEvents(): array {
    return [
      'hook_civicrm_postCommit' => 'scheduleHubspotContactDeletion',
    ];
  }

  public static function scheduleHubspotContactDeletion(GenericHookEvent $event): void {
    if (
      $event->action !== 'edit'
      || $event->entity !== 'Individual'
      || !$event->object->is_deleted
    ) return;

    $contact_id = (int) $event->object->id;

    $queue = Civi::queue('hubspot-sync-delete-contacts', [
      'type'           => 'SqlParallel',
      'runner'         => 'task',
      'reset'          => TRUE,
      'retry_interval' => 2,
      'retry_limit'    => 2,
      'error'          => 'delete',
    ]);

    $queue_task = new CRM_Queue_Task(
       [__CLASS__, 'deleteHubspotContact'],
       [$contact_id],
       "Hubspot Contact Deletion {$contact_id}"
    );

    $queue_task->runAs = [
      'contactId' => CRM_Core_Session::getLoggedInContactID(),
      'domainId'  => 1,
    ];

    $queue->createItem($queue_task, [ 'release_time' => strtotime('+7 days') ]);
  }

  public static function deleteHubspotContact(CRM_Queue_TaskContext $_context, int $contact_id): bool {
    $contact = Api4\Contact::get(FALSE)
      ->addSelect('is_deleted', 'hubspot_sync.hubspot_id')
      ->addWhere('id', '=', $contact_id)
      ->execute()
      ->first();

    if (!$contact['is_deleted']) return TRUE;

    $merge_delete_count = Api4\Activity::get(FALSE)
      ->selectRowCount()
      ->addWhere('activity_type_id:name', '=', 'Contact Deleted by Merge')
      ->addWhere('target_contact_id', 'CONTAINS', $contact_id)
      ->execute();

    if ($merge_delete_count->rowCount > 0) return TRUE;

    CRM_Hubspot_ApiClient::gdprDeleteContact($contact['hubspot_sync.hubspot_id']);

    self::updateSyncRecord([
      'entity_id'   => $contact_id,
      'sync_status' => 'deleted',
    ]);

    return TRUE;
  }

}
