<?php

namespace Civi\HubSpot;

use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotPortal;

class Listener {
  // TODO: enable
  public static $enabled = FALSE;

  const OBJECTS = ['Individual'];

  public static function queuePush($op, $objectName, $objectId, $objectRef) {
    // TODO: push to queue instead
    $hubspotContact = HubspotContact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $objectId)
      ->execute()
      ->first();
    if (empty($hubspotContact['id'])) {
      $hubspotPortal = HubspotPortal::get(FALSE)
        ->execute()
        ->first();
      $hubspotContact = HubspotContact::create()
        ->addValue('contact_id', $objectId)
        ->addValue('hubspot_portal_id', $hubspotPortal['id'])
        ->execute()
        ->first();
    }
    HubspotContact::push(FALSE)
      ->addWhere('id', '=', $hubspotContact['id'])
      ->execute();
  }
}
