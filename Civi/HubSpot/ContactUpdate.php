<?php

namespace Civi\HubSpot;

use Civi\Api4\Contact;
use Civi\Api4\GroupContact;
use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotContactUpdate;
use Civi\Api4\HubspotPortal;

class ContactUpdate {
  public \SevenShores\Hubspot\Factory $client;
  public array $item;
  public array $payload = [];
  public array $civiPayload = [];
  public array $contactPrefetch = [];
  public int $contactId;
  public int $hubspotVid;

  public bool $isInitialSync;
  public int $hubspotContactId;
  public array $hubspotPortal;

  private array $updateData = [];

  public function __construct(array $item) {
    $this->item = $item;
    $this->hubspotPortal = HubspotPortal::get(FALSE)
      ->addWhere('id', '=', $this->item['hubspot_portal_id'])
      ->execute()
      ->first();
    if (!empty($this->item['hubspot_contact_id'])) {
      $this->hubspotContactId = $this->item['hubspot_contact_id'];
    }
    $this->client = \SevenShores\Hubspot\Factory::createWithAccessToken($this->hubspotPortal['api_key']);
    if (!empty($item['inbound_payload'])) {
      $this->payload = Converter::getFlatPayload($item['inbound_payload']);
      $this->civiPayload = Converter::getCiviPayload($this->payload);
    }
  }

  public function process() {
    $tx = new \CRM_Core_Transaction();
    try {
      $inbound = \CRM_Core_PseudoConstant::getKey(
        'CRM_Hubspot_BAO_HubspotContactUpdate',
        'update_type_id',
        'inbound'
      );
      if ($this->item['update_type_id'] == $inbound) {
        // first, detect and discard out-of-date updates
        $count = HubspotContactUpdate::get(FALSE)
          ->selectRowCount()
          ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
          ->addWhere('hubspot_vid', '=', $this->item['hubspot_vid'])
          ->addWhere('hubspot_timestamp', '>', $this->item['hubspot_timestamp'])
          ->addWhere('update_type_id:name', '=', 'inbound')
          ->addWhere('update_status_id:name', 'IN', [
            'pending',
            'failed',
            'conflicted'
          ])
          ->execute();
        if ($count->rowCount > 0) {
          throw new OutOfDateException('Superseded by a more recent inbound contact update');
        }
      }
      else {
        $hubspotContact = HubspotContact::get(FALSE)
          ->addSelect('contact_id')
          ->addWhere('id', '=', $this->item['hubspot_contact_id'])
          ->execute()
          ->first();
        $this->contactId = $hubspotContact['contact_id'];
        // TODO: handle outbound conflict
      }
      // pre-process merges found within the payload. merges may impact conflict handling later on
      $this->merge();
      if ($this->item['update_type_id'] != $inbound) {
        $this->prefetchContact();
      }
      $this->match();
      if ($this->item['update_type_id'] != $inbound) {
        $this->fetchPayload();
        $this->isInitialSync = empty($this->hubspotVid);
      }
      $this->sync();

      $this->setCompleted();
      $tx->commit();
      // regenerate uniqueID to get per-update log_conn_id values
      \CRM_Core_DAO::executeQuery(
        'SET @uniqueID = %1', [
          1 => [
            uniqid() . \CRM_Utils_String::createRandom(4, \CRM_Utils_String::ALPHANUMERIC),
            'String'
          ]
        ]
      );
    }
    catch (ConflictException $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $this->item['id'])
        ->addValue('update_status_id:name', 'conflicted')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (OutOfDateException | MergedContactException $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $this->item['id'])
        ->addValue('update_status_id:name', 'discarded')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (\Throwable $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $this->item['id'])
        ->addValue('update_status_id:name', 'failed')
        ->addValue('status_details', "{$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}: " . $e->getTraceAsString())
        ->execute();
    }
    finally {
      Listener::$enabled = TRUE;
      return HubspotContactUpdate::get(FALSE)
        ->addWhere('id', '=', $this->item['id'])
        ->execute()
        ->first();
    }
  }

  /**
   * Process contacts merged in HubSpot
   *
   * @return void
   */
  protected function merge() {
    if (count($this->payload['merged-vids'] ?? []) > 0) {
      foreach ($this->payload['merged-vids'] as $mergedVid) {
        if ($mergedVid != $this->payload['canonical-vid']) {
          // set is_merged=true for any HubspotContacts that have been merged
          // into the current (canonical) HubspotContact
          $this->executeAndLogApi('HubspotContact', 'update', [
            'where' => [
              ['hubspot_portal_id', '=', $this->item['hubspot_portal_id']],
              ['hubspot_vid', '=', $mergedVid],
            ],
            'values' => [
              'is_merge' => TRUE,
            ],
          ]);
        }
      }
    }
  }

  protected function prefetchContact() {
    $this->contactPrefetch = Contact::get(FALSE)
      ->addSelect(
        'first_name',
        'last_name',
        'email_primary.email',
        'email_primary.on_hold',
        'phone_primary.phone',
        'address_primary.street_address',
        'address_primary.city',
        'address_primary.postal_code',
        'address_primary.country_id:name'
      )
      ->addWhere('id', '=', $this->contactId)
      ->execute()
      ->first();

    if (!empty($this->hubspotPortal['config']['subscription_map'])) {
      // fetch current GroupContact values for synced subscriptions
      $groupNames = array_values($this->hubspotPortal['config']['subscription_map']);
      $this->contactPrefetch['groups'] = GroupContact::get(FALSE)
        ->addSelect('id', 'group_id:name', 'status')
        ->addWhere('group_id:name', 'IN', $groupNames)
        ->addWhere('contact_id', '=', $this->contactId)
        ->execute()
        ->indexBy('group_id:name');
    }
  }

  protected function fetchPayload() {
    if (empty($this->hubspotVid)) {
      return;
    }
    $query = [
      'property' => [
        'title',
        'firstname',
        'lastname',
        'phone',
        'email',
        'suppressed',
        'city',
        'zip',
        'address',
        'country',
        'civicrm_id',
        'lastmodifieddate',
        'createdate'
      ],
      'propertyMode' => 'value_only',
      'formSubmissionMode' => 'none',
      'showListMemberships' => FALSE,
    ];
    if (!empty($this->hubspotPortal['config']['subscription_map'])) {
      // request subscription properties
      $query['property'] = array_merge(
        $query['property'],
        array_keys($this->hubspotPortal['config']['subscription_map'])
      );
    }
    $result = $this->client->contacts()->getById($this->hubspotVid, $query);
    $contact = json_decode(json_encode($result->getData()), TRUE);
    HubspotContactUpdate::update(FALSE)
      ->addWhere('id', '=', $this->item['id'])
      ->addValue('inbound_payload', $contact)
      ->execute();
    $this->payload = Converter::getFlatPayload($contact);
    $this->civiPayload = Converter::getCiviPayload($this->payload);
  }

  protected function matchInbound() {
    $matches = [];
    foreach ($this->hubspotPortal['config']['match'] as $class => $config) {
      $matcher = new $class($this, $config);
      foreach ($matcher->match() as $contactId => $matchKeys) {
        $matches[$contactId] = array_merge($matches[$contactId] ?? [], $matchKeys);
      }
    }

    if (count($matches) > 0) {
      // remove any contacts flagged with is_merge
      $mergedContacts = HubspotContact::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
        ->addWhere('contact_id', 'IN', array_keys($matches))
        ->addWhere('is_merge', '=', TRUE)
        ->execute();
      foreach ($mergedContacts as $mergedContact) {
        unset($matches[$mergedContact['contact_id']]);
      }
    }
    if (count($matches) > 1) {
      throw new ConflictException('Found multiple match candidates. Please resolve duplicates (Format: CiviCRM Contact ID / Match Parameter(s)): ' . json_encode($matches));
    }
    if (count($matches) == 1) {
      $this->contactId = array_keys($matches)[0];
      // check if matching CiviCRM contact is associated with a differing HubspotContact
      $hubspotContact = HubspotContact::get(FALSE)
        ->addSelect('id', 'hubspot_vid', 'is_merge')
        ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
        ->addWhere('contact_id', '=', $this->contactId)
        ->addWhere('is_merge', '=', FALSE)
        ->execute()
        ->first();
      if (!empty($hubspotContact)) {
        $this->hubspotContactId = $hubspotContact['id'];
        $this->hubspotVid = $hubspotContact['hubspot_vid'];
        if (!empty($hubspotContact['hubspot_vid']) && $hubspotContact['hubspot_vid'] != $this->item['hubspot_vid']) {
          throw new ConflictException("Identified CiviCRM contact {$this->contactId} which is already associated with HubSpot contact VID {$hubspotContact['hubspot_vid']}");
        }
      }
    }

    // detect updates to merged HubSpot contacts
    $countHubspotContacts = HubspotContact::get(FALSE)
      ->selectRowCount()
      ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
      ->addWhere('hubspot_vid', '=', $this->item['hubspot_vid'])
      ->addWhere('is_merge', '=', TRUE)
      ->execute();
    if ($countHubspotContacts->rowCount > 0) {
      throw new MergedContactException('Ignoring update for merged HubSpot contact');
    }
  }

  protected function matchOutbound() {
    $matches = [];
    foreach ($this->hubspotPortal['config']['match'] as $class => $config) {
      $matcher = new $class($this, $config);
      foreach ($matcher->match() as $hubspotVid => $matchKeys) {
        $matches[$hubspotVid] = array_merge($matches[$hubspotVid] ?? [], $matchKeys);
      }
    }

    if (count($matches) > 0) {
      // remove any contacts flagged with is_merge
      $mergedContacts = HubspotContact::get(FALSE)
        ->addSelect('hubspot_vid')
        ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
        ->addWhere('hubspot_vid', 'IN', array_keys($matches))
        ->addWhere('is_merge', '=', TRUE)
        ->execute();
      foreach ($mergedContacts as $mergedContact) {
        unset($matches[$mergedContact['hubspot_vid']]);
      }
    }
    if (count($matches) > 1) {
      throw new ConflictException('Found multiple match candidates. Please resolve duplicates (Format: HubSpot VID / Match Parameter(s)): ' . json_encode($matches));
    }
    if (count($matches) == 1) {
      $this->hubspotVid = array_keys($matches)[0];
      // check if matching CiviCRM contact is associated with a differing HubspotContact
      $hubspotContact = HubspotContact::get(FALSE)
        ->addSelect('id', 'contact_id')
        ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
        ->addWhere('hubspot_vid', '=', $hubspotVid)
        ->addWhere('is_merge', '=', FALSE)
        ->execute()
        ->first();
      if (!empty($hubspotContact) && $hubspotContact['id'] != $this->hubspotContactId) {
        throw new ConflictException("Identified HubSpot VID {$this->hubspotVid} which is already associated with CiviCRM contact ID {$hubspotContact['contact_id']}");
      }
    }
  }
  protected function match() {
    $inbound = \CRM_Core_PseudoConstant::getKey(
      'CRM_Hubspot_BAO_HubspotContactUpdate',
      'update_type_id',
      'inbound'
    );
    if ($this->item['update_type_id'] == $inbound) {
      $this->matchInbound();
    }
    else {
      $this->matchOutbound();
    }
  }

  protected function sync() {
    foreach ($this->hubspotPortal['config']['sync'] as $class => $config) {
      $syncer = new $class($this, $config);
      $syncer->sync();
    }
  }

  public function executeAndLogApi($entity, $action, array $params = []) {
    $this->updateData[$entity][$action][] = $params;
    $params = array_merge($params, ['checkPermissions' => FALSE]);
    if (empty($this->hubspotPortal['config']['dry_run'])) {
      return \civicrm_api4($entity, $action, $params);
    }
    else {
      return NULL;
    }
  }

  public function sendAndLog(string $action, array $properties) {
    $expandedProperties = [];
    foreach ($properties as $propertyKey => $propertyValue) {
      $expandedProperties[] = ['property' => $propertyKey, 'value' => $propertyValue];
    }
    $this->updateData[$action][] = $expandedProperties;
    if (!empty($this->hubspotPortal['config']['dry_run'])) {
      return [];
    }
    switch ($action) {
      case 'create':
        return $this->client->contacts()->create($expandedProperties);
      case 'update':
        return $this->client->contacts()->update($this->item['hubspot_vid'], $expandedProperties);
      case 'delete':
        return $this->client->contacts()->delete($this->item['hubspot_vid']);
    }
  }

  protected function setCompleted() {
    $record = [
      'contact_id' => $this->contactId,
      'hubspot_portal_id' => $this->item['hubspot_portal_id'],
      'hubspot_vid' => $this->item['hubspot_vid'],
    ];
    if (!empty($this->hubspotContactId)) {
      $record['id'] = $this->hubspotContactId;
    }
    $hubspotContact = HubspotContact::save(FALSE)
      ->setRecords([$record])
      ->execute()
      ->first();

    if (empty($this->hubspotPortal['config']['skip_identifier_sync']) && (empty($this->payload['civicrm_id']) || $this->payload['civicrm_id'] != $this->contactId)) {
      $this->client->contacts()->update($hubspotContact['hubspot_vid'], [
        ['property' => 'civicrm_id', 'value' => $this->contactId],
      ]);
    }

    HubspotContactUpdate::update(FALSE)
      ->addWhere('id', '=', $this->item['id'])
      ->addValue('hubspot_contact_id', $hubspotContact['id'])
      ->addValue('hubspot_vid', $this->hubspotVid ?? NULL)
      ->addValue('update_data', $this->updateData)
      ->addValue('update_status_id:name', 'completed')
      ->addValue('status_details', NULL)
      ->execute();
  }
}
