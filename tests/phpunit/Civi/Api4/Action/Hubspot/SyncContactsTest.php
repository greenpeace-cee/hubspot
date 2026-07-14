<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use GuzzleHttp\Psr7\Response;

/**
 * @group headless
 */
class SyncContactsTest extends TestBase {

  public function setUp(): void {
    foreach (self::loadAllContacts(['id']) as $contact) {
      Api4\Contact::update(FALSE)
        ->addValue('hubspot_sync.hubspot_id',        NULL)
        ->addValue('hubspot_sync.has_changes',       FALSE)
        ->addValue('hubspot_sync.ownership_score',   0)
        ->addValue('hubspot_sync.owned_by:abbr',     self::OWNER_COUNTRY)
        ->addValue('hubspot_sync.last_sync_date',    NULL)
        ->addValue('hubspot_sync.last_sync_failed',  FALSE)
        ->addValue('hubspot_sync.last_sync_payload', NULL)
        ->addWhere('id', '=', $contact['id'])
        ->execute();
    }
  }

  private static function mapToHubspotProps(array $contact): array {
    return [
      'firstname'         => $contact['first_name'],
      'lastname'          => $contact['last_name'],
      'date_of_birth'     => $contact['birth_date'],
      'email'             => $contact['hubspot_sync.email'],
      'civicrm_id'        => $contact['id'],
      'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      'owned_by'          => self::getIsoCode($contact['hubspot_sync.owned_by']),
      'ownership_score'   => $contact['hubspot_sync.ownership_score'],
    ];
  }

  public function testInitialSync(): void {
    $contacts = self::loadAllContacts(['*', 'hubspot_sync.*']);

    self::$mockHandler->append(MockResponses::batchCreateContacts(201, [
      'contacts' => array_map('self::mapToHubspotProps', $contacts),
    ]));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['contact_type', '=', 'Individual'],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => count($contacts),
      'scheduledForUpdate' => 0,
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-create-contacts');

    $request = self::shiftHistory()['request'];

    $this->assertEquals(
      'POST',
      $request->getMethod(),
      'Should have sent a POST request to the HubSpot API'
    );

    $this->assertEquals(
      '/crm/v3/objects/contacts/batch/create',
      $request->getUri()->getPath(),
      'Should have sent a request to the HubSpot API batch create endpoint'
    );

    $this->assertEquals(
      [
        'inputs' => array_map(
          fn ($contact) => [ 'properties' => self::mapToHubspotProps($contact) ],
          $contacts
        )
      ],
      json_decode((string) $request->getBody(), TRUE),
      'Should have sent the expected payload to the HubSpot API'
    );

    foreach (self::loadAllContacts(['*', 'hubspot_sync.*']) as $contact) {
      $this->assertIsNumeric(
        $contact['hubspot_sync.hubspot_id'],
        'The returned HubSpot contact ID should have been saved'
      );

      $this->assertFalse(
        $contact['hubspot_sync.has_changes'],
        'The "has_changes" flag should have been reset'
      );

      $this->assertEquals(
        self::OWNER_COUNTRY,
        self::getIsoCode($contact['hubspot_sync.owned_by']),
        'The contact should be owned by ' . self::OWNER_COUNTRY
      );

      $this->assertEquals(
        0,
        $contact['hubspot_sync.ownership_score'],
        'The initial ownership score should be 0'
      );

      $this->assertEqualsWithDelta(
        time(),
        strtotime($contact['hubspot_sync.last_sync_date']),
        24 * 60 * 60,
        'The timestamp of the last sync should have been updated'
      );

      $this->assertFalse(
        $contact['hubspot_sync.last_sync_failed'],
        'The "last_sync_failed" flag should be set to FALSE'
      );

      $this->assertEquals(
        [
          'firstname'         => $contact['first_name'],
          'lastname'          => $contact['last_name'],
          'date_of_birth'     => $contact['birth_date'],
          'email'             => $contact['hubspot_sync.email'],
          'owned_by'          => self::OWNER_COUNTRY,
          'ownership_score'   => $contact['hubspot_sync.ownership_score'],
          'civicrm_id'        => $contact['id'],
          'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
        ],
        json_decode($contact['hubspot_sync.last_sync_payload'], TRUE),
        'The payload of the latest sync should contain the expected values'
      );
    }
  }

  public function testSuccessfulUpdate(): void {
    $contact_ids = array_map(fn ($contact) => (int) $contact['id'], self::loadAllContacts(['id']));

    foreach ($contact_ids as $contact_id) {
      Api4\Contact::update(FALSE)
        ->addValue('hubspot_sync.hubspot_id', MockResponses::generateHubspotId())
        ->addValue('hubspot_sync.has_changes', TRUE)
        ->addValue('hubspot_sync.ownership_score', 10)
        ->addValue('hubspot_sync.last_sync_date', date('Y-m-d H:i:s', strtotime('last week')))
        ->addWhere('id', '=', $contact_id)
        ->execute();
    }

    $contacts = self::loadAllContacts(['*', 'hubspot_sync.*']);

    self::$mockHandler->append(MockResponses::batchUpdateContacts(200, [
      'ids'        => array_map(fn ($contact) => $contact['hubspot_sync.hubspot_id'], $contacts),
      'properties' => array_map('self::mapToHubspotProps', $contacts),
    ]));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['contact_type', '=', 'Individual'],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 0,
      'scheduledForUpdate' => count($contacts),
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-update-contacts');

    $request = self::shiftHistory()['request'];

    $this->assertEquals(
      'POST',
      $request->getMethod(),
      'Should have sent a POST request to the HubSpot API'
    );

    $this->assertEquals(
      '/crm/v3/objects/contacts/batch/update',
      $request->getUri()->getPath(),
      'Should have sent a request to the HubSpot API batch update endpoint'
    );

    $this->assertEquals(
      [
        'inputs' => array_map(fn ($contact) => [
          'id'         => $contact['hubspot_sync.hubspot_id'],
          'properties' => self::mapToHubspotProps($contact),
        ], $contacts),
      ],
      json_decode((string) $request->getBody(), TRUE),
      'Should have sent the expected payload to the HubSpot API'
    );

    foreach (self::loadAllContacts(['*', 'hubspot_sync.*']) as $contact) {
      $this->assertFalse(
        $contact['hubspot_sync.has_changes'],
        'The "has_changes" flag should have been reset'
      );

      $this->assertEquals(
        self::OWNER_COUNTRY,
        self::getIsoCode($contact['hubspot_sync.owned_by']),
        'The contact should still be owned by ' . self::OWNER_COUNTRY
      );

      $this->assertEquals(
        10,
        $contact['hubspot_sync.ownership_score'],
        'The ownership score should have been updated'
      );

      $this->assertEqualsWithDelta(
        time(),
        strtotime($contact['hubspot_sync.last_sync_date']),
        24 * 60 * 60,
        'The timestamp of the last sync should have been updated'
      );

      $this->assertFalse(
        $contact['hubspot_sync.last_sync_failed'],
        'The "last_sync_failed" flag should be set to FALSE'
      );

      $this->assertEquals(
        [
          'firstname'         => $contact['first_name'],
          'lastname'          => $contact['last_name'],
          'date_of_birth'     => $contact['birth_date'],
          'email'             => $contact['hubspot_sync.email'],
          'owned_by'          => self::OWNER_COUNTRY,
          'ownership_score'   => $contact['hubspot_sync.ownership_score'],
          'civicrm_id'        => $contact['id'],
          'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
        ],
        json_decode($contact['hubspot_sync.last_sync_payload'], TRUE),
        'The payload of the latest sync should contain the expected values'
      );
    }
  }

  public function testEmailConflict_YieldWithLowerScore(): void {
    $contact = self::loadAllContacts(['*', 'hubspot_sync.*'])[0];
    $contact_id = (int) $contact['id'];
    $hubspot_id = MockResponses::generateHubspotId();

    self::$mockHandler->append(MockResponses::batchCreateContacts(400));

    self::$mockHandler->append(MockResponses::getContactByEmail(200, [
      'id' => MockResponses::generateHubspotId(),
      'properties' => [
        'email'           => $contact['hubspot_sync.email'],
        'owned_by'        => 'BG',
        'ownership_score' => 50,
      ],
    ]));

    self::$mockHandler->append(MockResponses::createContact(201, [
      'id' => $hubspot_id,
      'properties' => [
        'civicrm_id'        => $contact_id,
        'owned_by'          => 'AT',
        'ownership_score'   => 0,
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact_id,
      ],
    ]));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['id', '=', $contact_id],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 1,
      'scheduledForUpdate' => 0,
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-create-contacts');

    $get_primary_email_owner_req = self::shiftHistory(2)['request'];

    $this->assertEquals(
      'GET',
      $get_primary_email_owner_req->getMethod(),
      'Should have sent a GET request to the HubSpot API'
    );

    $this->assertEquals(
      "/crm/v3/objects/contacts/{$contact['hubspot_sync.email']}",
      $get_primary_email_owner_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API get contact endpoint'
    );

    $create_contact_req = self::shiftHistory()['request'];

    $this->assertEquals(
      'POST',
      $create_contact_req->getMethod(),
      'Should have sent a POST request to the HubSpot API'
    );

    $this->assertEquals(
      '/crm/v3/objects/contacts',
      $create_contact_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API create contact endpoint'
    );

    $contact = self::loadSingleContact($contact_id, ['hubspot_sync.*']);

    $this->assertIsNumeric(
      $contact['hubspot_sync.hubspot_id'],
      'The returned HubSpot contact ID should have been saved'
    );

    $this->assertFalse(
      $contact['hubspot_sync.has_changes'],
      'The "has_changes" flag should have been reset'
    );

    $this->assertEquals(
      'BG',
      self::getIsoCode($contact['hubspot_sync.owned_by']),
      'The contact should be owned by Bulgaria'
    );

    $this->assertEquals(
      0,
      $contact['hubspot_sync.ownership_score'],
      'The ownership score should be 0'
    );

    $this->assertEqualsWithDelta(
      time(),
      strtotime($contact['hubspot_sync.last_sync_date']),
      24 * 60 * 60,
      'The timestamp of the last sync should have been updated'
    );

    $this->assertFalse(
      $contact['hubspot_sync.last_sync_failed'],
      'The "last_sync_failed" flag should be set to FALSE'
    );

    $this->assertEquals(
      [
        'owned_by'          => self::OWNER_COUNTRY,
        'ownership_score'   => $contact['hubspot_sync.ownership_score'],
        'civicrm_id'        => $contact['id'],
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      ],
      json_decode($contact['hubspot_sync.last_sync_payload'], TRUE),
      'The payload of the latest sync attempt should contain the expected values'
    );
  }

  public function testEmailConflict_ReclaimWithHigherScore(): void {
    $contact_id = (int) self::loadAllContacts(['id'])[0]['id'];
    $hubspot_id = MockResponses::generateHubspotId();
    $other_hubspot_id = MockResponses::generateHubspotId();

    Api4\Contact::update(FALSE)
      ->addValue('hubspot_sync.hubspot_id', $hubspot_id)
      ->addValue('hubspot_sync.ownership_score', 60)
      ->addWhere('id', '=', $contact_id)
      ->execute();

    $contact = self::loadSingleContact($contact_id, ['hubspot_sync.*']);

    self::$mockHandler->append(MockResponses::batchUpdateContacts(400));

    self::$mockHandler->append(MockResponses::getContactByEmail(200, [
      'id' => $other_hubspot_id,
      'properties' => [
        'email'           => $contact['hubspot_sync.email'],
        'owned_by'        => 'BG',
        'ownership_score' => 50,
      ],
    ]));

    self::$mockHandler->append(MockResponses::updateContact(200, [
      'id' => $other_hubspot_id,
      'properties' => [
        'email'           => '',
        'owned_by'        => 'BG',
        'ownership_score' => 50,
      ],
    ]));

    self::$mockHandler->append(MockResponses::updateContact(200, [
      'id' => $hubspot_id,
      'properties' => [
        'civicrm_id'        => $contact_id,
        'email'             => $contact['hubspot_sync.email'],
        'owned_by'          => 'AT',
        'ownership_score'   => 60,
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact_id,
      ],
    ]));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['id', '=', $contact_id],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 0,
      'scheduledForUpdate' => 1,
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-update-contacts');

    $get_primary_email_owner_req = self::shiftHistory(2)['request'];

    $this->assertEquals(
      'GET',
      $get_primary_email_owner_req->getMethod(),
      'Should have sent a GET request to the HubSpot API'
    );

    $this->assertEquals(
      "/crm/v3/objects/contacts/{$contact['hubspot_sync.email']}",
      $get_primary_email_owner_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API get contact endpoint'
    );

    $update_other_contact_req = self::shiftHistory()['request'];

    $this->assertEquals(
      'PATCH',
      $update_other_contact_req->getMethod(),
      'Should have sent a PATCH request to the HubSpot API'
    );

    $this->assertEquals(
      "/crm/v3/objects/contacts/$other_hubspot_id",
      $update_other_contact_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API update contact endpoint'
    );

    $update_contact_req = self::shiftHistory()['request'];

    $this->assertEquals(
      'PATCH',
      $update_contact_req->getMethod(),
      'Should have sent a PATCH request to the HubSpot API'
    );

    $this->assertEquals(
      "/crm/v3/objects/contacts/$hubspot_id",
      $update_contact_req->getUri()->getPath(),
      'Should have sent a request to the HubSpot API update contact endpoint'
    );

    $contact = self::loadSingleContact($contact_id, ['hubspot_sync.*']);

    $this->assertFalse(
      $contact['hubspot_sync.has_changes'],
      'The "has_changes" flag should have been reset'
    );

    $this->assertEquals(
      self::OWNER_COUNTRY,
      self::getIsoCode($contact['hubspot_sync.owned_by']),
      'The contact should be owned by ' . self::OWNER_COUNTRY
    );

    $this->assertEquals(
      60,
      $contact['hubspot_sync.ownership_score'],
      'The ownership score should be 60'
    );

    $this->assertEqualsWithDelta(
      time(),
      strtotime($contact['hubspot_sync.last_sync_date']),
      24 * 60 * 60,
      'The timestamp of the last sync should have been updated'
    );

    $this->assertFalse(
      $contact['hubspot_sync.last_sync_failed'],
      'The "last_sync_failed" flag should be set to FALSE'
    );

    $this->assertEquals(
      [
        'email'             => $contact['hubspot_sync.email'],
        'owned_by'          => self::OWNER_COUNTRY,
        'ownership_score'   => $contact['hubspot_sync.ownership_score'],
        'civicrm_id'        => $contact['id'],
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      ],
      json_decode($contact['hubspot_sync.last_sync_payload'], TRUE),
      'The payload of the latest sync attempt should contain the expected values'
    );
  }

  public function testUpdateNonExistentContacts(): void {
    $contact_id = (int) self::loadAllContacts(['id'])[0]['id'];
    $hubspot_id = MockResponses::generateHubspotId();

    Api4\Contact::update(FALSE)
      ->addValue('hubspot_sync.hubspot_id', $hubspot_id)
        ->addValue('hubspot_sync.has_changes', TRUE)
        ->addValue('hubspot_sync.last_sync_date', date('Y-m-d H:i:s', strtotime('last week')))
      ->addWhere('id', '=', $contact_id)
      ->execute();

    self::$mockHandler->append(MockResponses::batchUpdateContacts(207, [ 'ids' => [$hubspot_id] ]));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['id', '=', $contact_id],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => 0,
      'scheduledForUpdate' => 1,
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-update-contacts');

    $contact = self::loadSingleContact($contact_id, ['*', 'hubspot_sync.*']);

    $this->assertFalse(
      $contact['hubspot_sync.has_changes'],
      'The "has_changes" flag should have been reset'
    );

    $this->assertEqualsWithDelta(
      time(),
      strtotime($contact['hubspot_sync.last_sync_date']),
      24 * 60 * 60,
      'The timestamp of the last sync should have been updated'
    );

    $this->assertTrue(
      $contact['hubspot_sync.last_sync_failed'],
      'The "last_sync_failed" flag should be set to TRUE'
    );

    $this->assertEquals(
      [
        'firstname'         => $contact['first_name'],
        'lastname'          => $contact['last_name'],
        'date_of_birth'     => $contact['birth_date'],
        'email'             => $contact['hubspot_sync.email'],
        'owned_by'          => self::OWNER_COUNTRY,
        'ownership_score'   => $contact['hubspot_sync.ownership_score'],
        'civicrm_id'        => $contact['id'],
        'unique_civicrm_id' => self::OWNER_COUNTRY . '-' . $contact['id'],
      ],
      json_decode($contact['hubspot_sync.last_sync_payload'], TRUE),
      'The payload of the latest sync should contain the expected values'
    );
  }

  public function testRateLimitError(): void {
    self::$mockHandler->append(MockResponses::batchCreateContacts(429));

    $sync_result = (array) civicrm_api4('Hubspot', 'syncContacts', [
      'select' => [
        'CONCAT(first_name) AS firstname',
        'CONCAT(last_name) AS lastname',
        'CONCAT(birth_date) AS date_of_birth',
        'CONCAT(hubspot_sync.hubspot_id) AS hubspot_id',
        'CONCAT(hubspot_sync.email) AS email',
        'CONCAT(hubspot_sync.owned_by:abbr) AS owned_by',
        'ABS(hubspot_sync.ownership_score) AS ownership_score',
      ],
      'where' => [
        ['contact_type', '=', 'Individual'],
      ],
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals([
      'scheduledForCreate' => count(self::loadAllContacts(['id'])),
      'scheduledForUpdate' => 0,
    ], $sync_result);

    $this->processQueueItems('hubspot-sync-create-contacts', 'retry');
  }

}
