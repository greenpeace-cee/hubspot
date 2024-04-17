<?php

namespace Civi\Api4\Action\HubspotPortal;


use Civi\Api4\Generic\BasicBatchAction;
use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotContactUpdate;

class Push extends BasicBatchAction {

  /**
   * Criteria for selecting $ENTITIES to process.
   *
   * @var array
   */
  protected $where = [];

  public function __construct($entityName, $actionName) {
    return parent::__construct($entityName, $actionName);
  }

  protected function getSelect() {
    return ['id', 'config'];
  }

  /**
   * @param array $item
   * @return array
   */
  protected function doTask($item) {
    // TODO: add lock
    $contacts = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('HubspotContact AS hubspot_contact', 'LEFT',
        ['id', '=', 'hubspot_contact.contact_id'],
        ['hubspot_contact.hubspot_portal_id', '=', $item['id']]
      )
      ->addWhere('is_deleted', '=', FALSE)
      ->addWhere('hubspot_contact.id', 'IS NULL')
      ->addWhere('contact_type:name', '=', 'Individual')
      ->execute();
    $count = 0;
    foreach ($contacts as $contact) {
      HubspotContact::create(FALSE)
        ->addValue('contact_id', $contact['id'])
        ->addValue('hubspot_portal_id', $item['id'])
        ->addValue('is_dirty', TRUE)
        ->addValue('is_merge', FALSE)
        ->execute();
      $count++;
    }
    $total = 0;
    $hubspotContacts = HubspotContact::get(FALSE)
      ->addJoin('HubspotContactUpdate AS hubspot_contact_update', 'LEFT',
        ['id', '=', 'hubspot_contact_update.hubspot_contact_id'],
        ['hubspot_contact_update.update_type_id:name', '=', '"outbound"'],
        ['hubspot_contact_update.update_status_id:name', 'IN', ['pending', 'failed', 'conflicted']])
      ->addWhere('is_dirty', '=', TRUE)
      ->addWhere('hubspot_contact_update.id', 'IS NULL')
      ->addWhere('hubspot_portal_id', '=', $item['id'])
      ->execute();
    foreach ($hubspotContacts as $hubspotContact) {
      HubspotContactUpdate::create(FALSE)
        ->addValue('hubspot_portal_id', $item['id'])
        ->addValue('hubspot_contact_id', $hubspotContact['id'])
        ->addValue('hubspot_vid', $hubspotContact['hubspot_vid'] ?? NULL)
        ->addValue('update_type_id:name', 'outbound')
        ->addValue('update_status_id:name', 'pending')
        ->execute();
      $total++;
    }
    return ['new' => $count, 'total' => $total];
  }

}
