<?php

namespace Civi\HubSpot;

use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotPortal;

class Listener {
  public static $enabled = FALSE;

  const OBJECTS = ['Individual'];

  /**
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function queuePush($op, $objectName, $objectId, $objectRef) {
    // TODO: join = array!
    $hubspotPortals = HubspotPortal::get(FALSE)
      ->addSelect('id', 'hubspot_contact.id', 'hubspot_contact.is_merge')
      ->addJoin(
        'HubspotContact AS hubspot_contact',
        'LEFT',
        ['id', '=', 'hubspot_contact.hubspot_portal_id'],
        ['hubspot_contact.contact_id', '=', $objectId]
      )
      ->execute();
    foreach ($hubspotPortals as $hubspotPortal) {
      if ($hubspotPortal['hubspot_contact.is_merge'] || !self::shouldContactSync($objectId, $hubspotPortal['id'], $hubspotPortal['hubspot_contact.id'])) {
        continue;
      }
      $record = [
        'contact_id' => $objectId,
        'hubspot_portal_id' => $hubspotPortal['id'],
        'is_dirty' => TRUE,
      ];
      if (!empty($hubspotPortal['hubspot_contact.id'])) {
        $record['id'] = $hubspotPortal['hubspot_contact.id'];
      }
      HubspotContact::save(FALSE)
        ->setRecords([$record])
        ->execute();
    }
  }

  /**
   * Determines whether a contact should be synced to a specific HubSpot portal
   *
   * @param $contactId
   * @param $hubspotPortalId
   * @param $hubspotContactId
   *
   * @return bool
   */
  public static function shouldContactSync($contactId, $hubspotPortalId, $hubspotContactId = NULL) {
    // just sync everyone for now
    return TRUE;
  }
}
