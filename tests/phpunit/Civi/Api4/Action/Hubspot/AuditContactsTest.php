<?php

declare(strict_types = 1);
namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use GuzzleHttp\Psr7\Response;

/**
 * @group headless
 */
class AuditContactsTest extends TestBase {

  public function testAuditContacts(): void {
    $log = self::captureLog('hubspot-audit');
    $contact = self::loadAllContacts(['id', 'first_name'])[0];
    $hubspot_id = MockResponses::generateHubspotId();

    Api4\Contact::update(FALSE)
      ->addValue('hubspot_sync.sync_status:name', 'successful')
      ->addValue('hubspot_sync.hubspot_id', $hubspot_id)
      ->addValue('hubspot_sync.last_sync_payload', json_encode(['firstname' => $contact['first_name']]))
      ->addWhere('id', '=', $contact['id'])
      ->execute();

    civicrm_api4('Hubspot', 'auditContacts', [
      'where' => [['id', '=', $contact['id']]],
      'samplePercentage' => 100,
      'checkPermissions' => FALSE,
    ]);

    self::$mockHandler->append(new Response(200, [], json_encode([
      'results' => [
        [
          'id'         => $hubspot_id,
          'properties' => ['firstname' => 'Someone else'],
        ],
      ],
    ])));

    $this->processQueueItems('hubspot-audit-contacts');

    $this->assertEquals(
      ['firstname' => ['civicrm' => $contact['first_name'], 'hubspot' => 'Someone else']],
      $log->contexts[0]['diff']
    );
  }

}
