<?php

namespace Civi\Hubspot;

use Civi;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;

/**
 * Delete a contact in HubSpot when its corresponding contact in Civi is deleted.
 */
class DeleteContact extends AutoSubscriber {

  public static function getSubscribedEvents(): array {
    return [
      'hook_civicrm_postCommit' => 'deleteHubspotContact',
    ];
  }

  public static function deleteHubspotContact(GenericHookEvent $event): void {
    if (
      $event->action !== 'edit'
      || $event->entity !== 'Individual'
      || !$event->object->is_deleted
    ) return;

    Civi::log()->debug('MergeContacts::deleteHubspotContact', [
      '$event->action' => $event->action,
      '$event->entity' => $event->entity,
      '$event->id'     => $event->id,
      '$event->object' => $event->object,
      '$event->params' => $event->params,
    ]);
  }

}
