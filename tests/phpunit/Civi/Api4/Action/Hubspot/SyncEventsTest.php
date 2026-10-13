<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;

/**
 * @group headless
 */
class SyncEventsTest extends TestBase {

  public function setUp(): void {
    parent::setUp();

    Api4\OptionValue::save(FALSE)
      ->addRecord([
        'option_group_id.name' => 'hubspot_event_type',
        'name'                 => 'civicrm_selection',
        'label'                => 'CiviCRM Selection'
      ])
      ->addRecord([
        'option_group_id.name' => 'hubspot_event_type',
        'name'                 => 'stop_civicrm_selection',
        'label'                => 'STOP CiviCRM Selection'
      ])
      ->addRecord([
        'option_group_id.name' => 'hubspot_event_type',
        'name'                 => 'lead_generation',
        'label'                => 'Lead Generation'
      ])
      ->setMatch(["option_group_id", "name"])
      ->execute();

    foreach (self::loadAllContacts(['id']) as $contact) {
      Api4\Contact::update(FALSE)
        ->addValue('hubspot_sync.hubspot_id', MockResponses::generateHubspotId())
        ->addWhere('id', '=', $contact['id'])
        ->execute();
    }
  }

  public function testSyncEvents(): void {
    $this->assertTrue(TRUE);
  }

}
