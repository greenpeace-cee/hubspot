<?php

class CRM_Hubspot_HubspotBatchProcessor {

  const RETRIEVE_BATCH_SIZE = 100;
  const UPSERT_BATCH_SIZE = 10;

  private $apiClient;
  private $hubspotContacts = [];
  private $logger;
  private $upsertBatch = [];

  public function __construct(array $hubspot_account) {
    $this->apiClient = HubSpot\Factory::createWithAccessToken($hubspot_account['api_key']);
    $this->logger = Civi::log();
  }

  public function flushRemainingBatch() {
    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::flushRemainingBatch');

    if (empty($this->upsertBatch)) return;

    $response = $this->sendBatchUpsertRequest($this->upsertBatch);

    self::setHubspotSyncDate(array_map(
      fn ($contact) => [
        'civicrm_id' => $contact['properties']['civicrm_id'],
        'hubspot_id' => $contact['id'],
      ],
      $response['results']
    ));

    $this->upsertBatch = [];
  }

  public function preloadContacts(array $hubspot_contact_ids) {
    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::preloadContacts', [
      'hubspot_contact_ids' => $hubspot_contact_ids,
    ]);

    if (empty($hubspot_contact_ids)) return;

    $response = $this->apiClient->apiRequest([
      'method' => 'POST',
      'path'   => '/crm/v3/objects/contacts/batch/read',
      'body'   => [
        'inputs' => array_map(fn ($id) => [ 'id' => $id ], $hubspot_contact_ids),
        'properties' => [],
        'propertiesWithHistory' => [],
      ]
    ]);

    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::preloadContacts', [
      'response' => $response,
    ]);
  }

  public function pullContact(string $hubspot_contact_id) {
    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::pullContact', [
      'hubspot_contact_id' => $hubspot_contact_id,
    ]);

    $hubspot_contact = $this->hubspotContacts[$hubspot_contact_id] ?? NULL;

    if (isset($hubspot_contact)) unset($this->hubspotContacts[$hubspot_contact_id]);

    return $hubspot_contact;
  }

  private function sendBatchUpsertRequest(array $batch) {
    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::sendBatchUpsertRequest', [
      'batch' => $batch,
    ]);

    $request = [
      'method' => 'POST',
      'path'   => '/crm/v3/objects/contacts/batch/upsert',
      'body'   => [
        'inputs' => array_map(
          fn ($props) => [
            'id' => $props['email'],
            'idProperty' => 'email',
            'properties' => $props,
          ],
          $batch
        ),
      ],
    ];

    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::sendBatchUpsertRequest', [
      'request' => $request,
    ]);

    $response = json_decode((string) $this->apiClient->apiRequest($request)->getBody(), TRUE);

    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::sendBatchUpsertRequest', [
      'response' => $response,
    ]);

    return $response;
  }

  private static function setHubspotSyncDate(array $contacts) {
    $rows = [];
    $params = [];
    $index = 0;

    foreach ($contacts as $contact) {
      list($i, $j) = [++$index, ++$index];
      $rows[] = "(%$i, %$j, CURRENT_TIMESTAMP)";
      $params[$i] = [$contact['civicrm_id'], 'Integer'];
      $params[$j] = [$contact['hubspot_id'], 'String'];
    }

    CRM_Core_DAO::executeQuery("
      INSERT INTO civicrm_value_hubspot_sync (entity_id, hubspot_contact_id, sync_date)
        VALUES " . implode(', ', $rows) . "
      ON DUPLICATE KEY UPDATE
        sync_date = CURRENT_TIMESTAMP
    ", $params);
  }

  public function upsertContact(array $civi_contact) {
    $this->logger->debug('CRM_Hubspot_HubspotBatchProcessor::upsertContact', [
      'civi_contact' => $civi_contact,
    ]);

    $this->upsertBatch[] = [
      'firstname'  => $civi_contact['first_name'],
      'lastname'   => $civi_contact['last_name'],
      'email'      => $civi_contact['email'],
      'civicrm_id' => $civi_contact['id'],
    ];

    if (count($this->upsertBatch) < self::UPSERT_BATCH_SIZE) return;

    $response = $this->sendBatchUpsertRequest($this->upsertBatch);

    self::setHubspotSyncDate(array_map(
      fn ($hubspot_contact) => [
        'civicrm_id' => (int) $hubspot_contact['properties']['civicrm_id'],
        'hubspot_id' => (string) $hubspot_contact['id'],
      ],
      $response['results']
    ));

    $this->upsertBatch = [];
  }

}
