<?php
namespace Civi\HubSpot;

use Civi\Api4\Contact;
use Civi\Api4\Group;
use Civi\Api4\HubspotPortal;
use Civi\Test;
use CRM_Hubspot_ExtensionUtil as E;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;

/**
 * @group headless
 */
class ContactUpdateTest extends TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  const DEFAULT_PORTAL_CONFIG = [
    'dry_run' => FALSE,
    'skip_identifier_sync' => TRUE,
    'match' => [
      'Civi\HubSpot\Match\HubspotVid' => [],
      'Civi\HubSpot\Match\CivicrmId' => [],
      'Civi\HubSpot\Match\Email' => [],
      'Civi\HubSpot\Match\Phone' => [
        'include_name' => TRUE,
      ],
    ],
    'sync' => [
      'Civi\HubSpot\Sync\Contact' => [
        'inbound' => TRUE,
        'outbound' => FALSE,
      ],
      'Civi\HubSpot\Sync\Email' => [
        'inbound' => TRUE,
        'outbound' => FALSE,
      ],
      'Civi\HubSpot\Sync\Phone' => [
        'inbound' => TRUE,
        'outbound' => FALSE,
      ],
      'Civi\HubSpot\Sync\Address' => [
        'inbound' => TRUE,
        'outbound' => FALSE,
      ],
      'Civi\HubSpot\Sync\GroupContact' => [
        'inbound' => TRUE,
        'outbound' => FALSE,
      ],
    ],
    'submission' => [
      'default' => [
        'Civi\HubSpot\Submission\Petition' => [
          'inbound' => TRUE,
          'outbound' => FALSE,
          'default_campaign_id' => '7',
          'default_medium_label' => 'Via Web',
        ],
      ],
    ],
    'subscription_map' => [
      'subscriptions_at_community_newsletter' => 'Community_NL',
    ],
  ];

  private int $communityGroupId;

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    HubspotPortal::create()
      ->addValue('hubspot_portal_identifier', 'default')
      ->addValue('name', 'default')
      ->addValue('api_key', 'test')
      ->addValue('config', self::DEFAULT_PORTAL_CONFIG)
      ->execute()
      ->first();
    $this->communityGroupId = Group::create(FALSE)
      ->addValue('name', 'Community_NL')
      ->addValue('title', 'Community_NL')
      ->execute()
      ->first()['id'];
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  public function testInboundCreate() {
    $contactUpdate = $this->createContactUpdate([
      'inbound_payload' => $this->getFixture('inbound-update.json')
    ]);
    $this->processContactUpdate($contactUpdate);
    $contact = Contact::get(FALSE)
      ->addSelect('*', 'email.*', 'phone.*', 'address.*', 'address.country_id:name', 'group_contact.*')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addJoin('Address AS address', 'LEFT')
      ->addJoin('GroupContact AS group_contact', 'LEFT')
      ->addWhere('email.email', '=', 'jane.doe@example.com')
      ->execute()
      ->first();
    $this->assertEquals('Jane', $contact['first_name']);
    $this->assertEquals('jane.doe@example.com', $contact['email.email']);
    $this->assertEquals('+436801233211', $contact['phone.phone']);
    $this->assertEquals('Wien', $contact['address.city']);
    $this->assertEquals('1030', $contact['address.postal_code']);
    $this->assertEquals('AT', $contact['address.country_id:name']);
    $this->assertEquals('Landstraße 999', $contact['address.street_address']);
    $this->assertEquals($this->communityGroupId, $contact['group_contact.group_id']);
    $this->assertEquals('Added', $contact['group_contact.status']);

    $contactUpdate = \Civi\Api4\HubspotContactUpdate::get(FALSE)
      ->addSelect('update_data', 'update_status_id:name', 'hubspot_contact_id')
      ->addWhere('id', '=', $contactUpdate['id'])
      ->execute()
      ->first();
    $this->assertEquals(5, count($contactUpdate['update_data']));
    $this->assertEquals('completed', $contactUpdate['update_status_id:name']);
    $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
      ->addWhere('id', '=', $contactUpdate['hubspot_contact_id'])
      ->execute()
      ->first();
    $this->assertEquals(1, $hubspotContact['hubspot_vid']);
  }

  public function testInboundMatchEmail() {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Jane')
      ->addValue('last_name', 'Doe')
      ->addChain('email', \Civi\Api4\Email::create()
        ->addValue('contact_id', '$id')
        ->addValue('email', 'jane.doe@example.com')
      )
      ->execute()
      ->first();
    $this->processContactUpdate($this->createContactUpdate([
      'inbound_payload' => $this->getFixture('inbound-update.json'),
    ]));
    $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()
      ->first();
    $this->assertEquals(1, $hubspotContact['hubspot_vid']);
  }

  public function testInboundMatchPhone() {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Jane')
      ->addValue('last_name', 'Doe')
      ->addChain('phone', \Civi\Api4\Phone::create()
        ->addValue('contact_id', '$id')
        ->addValue('phone', '+436801233211')
      )
      ->execute()
      ->first();
    $this->processContactUpdate($this->createContactUpdate([
      'inbound_payload' => $this->getFixture('inbound-update.json'),
    ]));
    $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()
      ->first();
    $this->assertEquals(1, $hubspotContact['hubspot_vid']);
  }
  public function testInboundMatchCivicrmId() {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Jane')
      ->addValue('last_name', 'Doe')
      ->execute()
      ->first();
    $payload = $this->getFixture('inbound-update.json');
    $payload['properties']['civicrm_id']['value'] = $contact['id'];
    $this->processContactUpdate($this->createContactUpdate([
      'inbound_payload' => $payload,
    ]));
    $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()
      ->first();
    $this->assertEquals(1, $hubspotContact['hubspot_vid']);
  }

  public function testInboundMatchHubspotVid() {
    $payload = $this->getFixture('inbound-update.json');
    $this->processContactUpdate($this->createContactUpdate([
      'inbound_payload' => $payload,
    ]));
    $hubspotContact = \Civi\Api4\HubspotContact::get(FALSE)
      ->execute()
      ->first();
    $payload['properties']['firstname']['value'] = 'John';
    $this->processContactUpdate($this->createContactUpdate([
      'inbound_payload' => $payload,
    ]));
    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('id', '=', $hubspotContact['contact_id'])
      ->execute()
      ->first();
    $this->assertEquals('John', $contact['first_name']);
  }

  public function testIncompleteAddress() {
    $payload = $this->getFixture('inbound-update.json');
    unset($payload['properties']['zip']);
    unset($payload['properties']['city']);
    unset($payload['properties']['address']);
    $contactUpdate = $this->createContactUpdate([
      'inbound_payload' => $payload,
    ]);
    $this->processContactUpdate($contactUpdate);
    $contact = Contact::get(FALSE)
      ->addSelect('*', 'email.*', 'address.*')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Address AS address', 'LEFT')
      ->addWhere('email.email', '=', 'jane.doe@example.com')
      ->execute()
      ->first();
    $this->assertNull($contact['address.id']);
  }

  protected function getFixture(string $filename): array {
    return json_decode(
      file_get_contents(__DIR__ . '/../../../fixtures/' . $filename),
      TRUE
    );
  }

  protected function createContactUpdate(array $values): array {
    $defaults = [
      'hubspot_vid' => $values['inbound_payload']['vid'] ?? NULL,
      'hubspot_timestamp' => $values['inbound_payload']['addedAt'] ?? NULL,
      'update_type_id:name' => empty($values['inbound_payload']) ? 'outbound' : 'inbound',
      'hubspot_portal_id.name' => 'default',
      'update_status_id:name' => 'pending',
    ];
    $values = array_merge($defaults, $values);
    return civicrm_api4('HubspotContactUpdate', 'create', [
      'values' => $values,
    ])->first();
  }

  protected function processContactUpdate(array $contactUpdate) {
    $update = new ContactUpdate($contactUpdate);
    $update->process();
    return \Civi\Api4\HubspotContactUpdate::get(FALSE)
      ->addSelect('*', 'update_status_id:name')
      ->addWhere('id', '=', $contactUpdate['id'])
      ->execute()
      ->first();
  }

}
