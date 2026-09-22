<?php

namespace Civi\Hubspot;

use Civi;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;

/**
 * Merge contacts in HubSpot when the corresponding contacts in Civi are merged.
 */
class MergeContacts extends AutoSubscriber {

  public static function getSubscribedEvents(): array {
    return [
      'hook_civicrm_merge' => 'mergeHubspotContacts',
    ];
  }

  public static function mergeHubspotContacts(GenericHookEvent $event): void {
    if ($event->type !== 'sqls') return;

    Civi::log()->debug('MergeContacts::mergeHubspotContacts', [
      '$event->type'    => $event->type,
      '$event->mainId'  => $event->mainId,
      '$event->otherId' => $event->otherId,
    ]);
  }

}
