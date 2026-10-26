<?php

declare(strict_types = 1);
namespace Civi\Hubspot;

use Civi\Api4;
use Civi\Api4\Action\Hubspot\MockResponses;
use Civi\Api4\Action\Hubspot\TestBase;
use CRM_Contact_DAO_Contact;
use CRM_Queue_TaskContext;
use CRM_Utils_Hook;
use GuzzleHttp\Psr7\Response;

/**
 * @group headless
 */
class DeleteContactTest extends TestBase {

  public function testDeletionIsOnlyScheduledForActiveHubspotContacts(): void {
    self::trashContact(self::createSyncedContact(NULL, NULL));
    self::trashContact(self::createSyncedContact(MockResponses::generateHubspotId(), 'deleted'));
    self::trashContact(self::createSyncedContact(MockResponses::generateHubspotId(), 'merged'));
    self::trashContact(self::createSyncedContact(MockResponses::generateHubspotId(), 'successful'));

    $this->assertEquals(1, self::countQueueItems('hubspot-sync-delete-contacts'));
  }

  public function testDeletion(): void {
    $hubspot_id = MockResponses::generateHubspotId();
    $contact_id = self::createSyncedContact($hubspot_id, 'successful');
    self::trashContact($contact_id);

    self::$mockHandler->append(new Response(204));
    DeleteContact::deleteHubspotContact(new CRM_Queue_TaskContext(), $contact_id);

    $request = self::shiftHistory()['request'];
    $this->assertStringEndsWith('/crm/v3/objects/contacts/gdpr-delete', $request->getUri()->getPath());

    $contact = self::loadSingleContact($contact_id, ['hubspot_sync.hubspot_id', 'hubspot_sync.sync_status']);
    $this->assertEquals('deleted', $contact['hubspot_sync.sync_status']);
    $this->assertEquals($hubspot_id, $contact['hubspot_sync.hubspot_id'], 'The HubSpot ID should be kept');

    // Running the task again must not send another request (none is mocked, so it would throw)
    DeleteContact::deleteHubspotContact(new CRM_Queue_TaskContext(), $contact_id);
  }

  private static function createSyncedContact(?string $hubspot_id, ?string $sync_status): int {
    return (int) Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Deleted')
      ->addValue('last_name', 'Contact')
      ->addValue('hubspot_sync.hubspot_id', $hubspot_id)
      ->addValue('hubspot_sync.sync_status', $sync_status)
      ->execute()
      ->first()['id'];
  }

  /**
   * Moves the contact to the trash. hook_civicrm_postCommit is invoked manually,
   * since the test transaction is never committed.
   */
  private static function trashContact(int $contact_id): void {
    Api4\Contact::delete(FALSE)
      ->addWhere('id', '=', $contact_id)
      ->execute();

    $contact = new CRM_Contact_DAO_Contact();
    $contact->id = $contact_id;
    $contact->find(TRUE);

    CRM_Utils_Hook::postCommit('edit', 'Individual', $contact_id, $contact);
  }

}
