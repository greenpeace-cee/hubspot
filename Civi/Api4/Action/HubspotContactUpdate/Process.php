<?php

namespace Civi\Api4\Action\HubspotContactUpdate;

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Generic\BasicBatchAction;
use Civi\Api4\GroupContact;
use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotContactUpdate;
use Civi\Api4\HubspotPortal;
use Civi\Api4\Phone;
use Civi\HubSpot\ConflictException;
use Civi\HubSpot\Converter;
use Civi\HubSpot\MergedContactException;
use Civi\HubSpot\OutOfDateException;

class Process extends BasicBatchAction {

  /**
   * Criteria for selecting $ENTITIES to process.
   *
   * @var array
   */
  protected $where = [];

  /**
   * @var \SevenShores\Hubspot\Factory
   */
  private $client;

  private $item;
  private $hubspotPortal;
  private $payload = [];
  private $civiPayload = [];
  private $updateData = [];

  public function __construct($entityName, $actionName) {
    parent::__construct($entityName, $actionName, [
      'id',
      'update_type_id',
      'hubspot_portal_id',
      'hubspot_vid',
      'hubspot_timestamp',
      'inbound_payload',
    ]);
  }

  public function getSelect() {
    return [
      'id',
      'update_type_id',
      'hubspot_portal_id',
      'hubspot_vid',
      'hubspot_timestamp',
      'inbound_payload',
    ];
  }

  /**
   * @param array $item
   *
   * @return array
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function doTask($item) {
    // TODO: add lock
    $tx = new \CRM_Core_Transaction();
    try {
      $this->item = $item;
      $this->hubspotPortal = HubspotPortal::get(FALSE)
        ->addWhere('id', '=', $this->item['hubspot_portal_id'])
        ->execute()
        ->first();
      $this->client = \SevenShores\Hubspot\Factory::create($this->hubspotPortal['api_key']);
      $this->payload = Converter::getFlatPayload($item['inbound_payload']);
      $this->civiPayload = Converter::getCiviPayload($this->payload);
      $this->updateData = [];

      // first, detect and discard out-of-date updates
      $count = HubspotContactUpdate::get(FALSE)
        ->selectRowCount()
        ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
        ->addWhere('hubspot_vid', '=', $this->item['hubspot_vid'])
        ->addWhere('hubspot_timestamp', '>', $this->item['hubspot_timestamp'])
        ->addWhere('update_type_id:name', '=', 'inbound')
        ->addWhere('update_status_id:name', 'IN', ['pending', 'completed', 'conflicted'])
        ->execute();
      if ($count->rowCount > 0) {
        throw new OutOfDateException('Superseded by a more recent inbound contact update');
      }

      // pre-process merges found within the payload. merges may impact conflict handling later on
      $this->processMerge();

      // detect conflicts (dupes)
      $matches = $this->getMatches($item);
      if (count($matches) > 1) {
        throw new ConflictException('Found multiple match candidates. Please resolve duplicates (Format: CiviCRM Contact ID / Match Parameter(s)): ' . json_encode($matches));
      }
      $contactId = NULL;
      $hubspotContactId = NULL;
      if (count($matches) == 1) {
        $contactId = array_keys($matches)[0];
        // check if matching CiviCRM contact is associated with a differing HubspotContact
        $hubspotContact = HubspotContact::get(FALSE)
          ->addSelect('id', 'hubspot_vid', 'is_merge')
          ->addWhere('hubspot_portal_id', '=', $this->item['hubspot_portal_id'])
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('is_merge', '=', FALSE)
          ->execute()
          ->first();
        $hubspotContactId = $hubspotContact['id'];
        if (!empty($hubspotContact['hubspot_vid']) && $hubspotContact['hubspot_vid'] != $this->item['hubspot_vid']) {
          throw new ConflictException("Identified CiviCRM contact {$contactId} which is already associated with HubSpot contact VID {$hubspotContact['hubspot_vid']}");
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

      $contact = $this->executeAndLogApi('Contact', 'save', $this->getContactParameters($contactId));
      if (!empty($contact)) {
        $contactId = $contact[0]['id'];
      }
      $this->executeAndLogApi('Email', 'save', $this->getEmailParameters($contactId));
      $this->executeAndLogApi('Phone', 'save', $this->getPhoneParameters($contactId));
      $this->executeAndLogApi('GroupContact', 'save', $this->getGroupContactParameters($contactId));
      $this->setCompleted($hubspotContactId, $contactId);
      $tx->commit();
      // regenerate uniqueID to get per-update log_conn_id values
      CRM_Core_DAO::executeQuery(
        'SET @uniqueID = %1', [
          1 => [
            uniqid() . \CRM_Utils_String::createRandom(4, CRM_Utils_String::ALPHANUMERIC),
            'String'
          ]
        ]
      );
    }
    catch (ConflictException $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('update_status_id:name', 'conflicted')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (OutOfDateException | MergedContactException $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('update_status_id:name', 'discarded')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (\Exception $e) {
      $tx->rollback();
      unset($tx);
      HubspotContactUpdate::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('update_status_id:name', 'failed')
        ->addValue('status_details', "{$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}: " . $e->getTraceAsString())
        ->execute();
    }
    finally {
      return HubspotContactUpdate::get(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->execute()
        ->first();
    }
  }

  protected function setCompleted($hubspotContactId, $contactId) {
    $record = [
      'contact_id' => $contactId,
      'hubspot_portal_id' => $this->item['hubspot_portal_id'],
      'hubspot_vid' => $this->item['hubspot_vid'],
    ];
    if (!empty($hubspotContactId)) {
      $record['id'] = $hubspotContactId;
    }
    $hubspotContact = HubspotContact::save(FALSE)
      ->setRecords([$record])
      ->execute()
      ->first();

    if ($this->payload['civicrm_id'] != $contactId && empty($this->hubspotPortal['config']['readonly'])) {
      $this->client->contacts()->update($hubspotContact['hubspot_vid'], [
        ['property' => 'civicrm_id', 'value' => $contactId],
      ]);
    }

    HubspotContactUpdate::update(FALSE)
      ->addWhere('id', '=', $this->item['id'])
      ->addValue('hubspot_contact_id', $hubspotContact['id'])
      ->addValue('update_data', $this->updateData)
      ->addValue('update_status_id:name', 'completed')
      ->addValue('status_details', NULL)
      ->execute();
  }

  protected function executeAndLogApi($entity, $action, $params) {
    if (is_null($params)) {
      return FALSE;
    }
    $this->updateData[$entity][$action][] = $params;
    $params = array_merge($params, ['checkPermissions' => FALSE]);
    return civicrm_api4($entity, $action, $params);
  }

  protected function getMatches(array $item): array {
    $matches = [];

    // look for vid duplicates
    $hubspotContacts = HubspotContact::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('hubspot_portal_id', '=', $item['hubspot_portal_id'])
      ->addWhere('hubspot_vid', '=', $item['hubspot_vid'])
      ->addWhere('is_merge', '=', FALSE)
      ->execute();
    foreach ($hubspotContacts as $hubspotContact) {
      $matches[$hubspotContact['contact_id']][] = 'hubspot_vid';
    }

    if (!empty($this->payload['civicrm_id'])) {
      // look for civicrm_id duplicates
      $contacts = Contact::get(FALSE)
        ->addWhere('id', '=', $this->payload['civicrm_id'])
        ->addWhere('is_deleted', '=', FALSE)
        ->execute();
      foreach ($contacts as $contact) {
        $matches[$contact['id']][] = 'civicrm_id';
      }

      // look for identity tracker duplicates
      if (function_exists('identitytracker_civicrm_install')) {
        // identitytracker is enabled
        $contacts = civicrm_api3('Contact', 'findbyidentity', [
          'identifier_type' => 'internal',
          'identifier' => $this->payload['civicrm_id']
        ]);
        foreach ($contacts['values'] as $contact) {
          $matches[$contact['id']][] = 'civicrm_id_identity_tracker';
        }
      }
    }

    if (!empty($this->payload['email'])) {
      // look for email duplicates
      $emails = Email::get(FALSE)
        ->addSelect('contact_id')
        ->setGroupBy([
          'contact_id',
        ])
        ->addWhere('email', '=', $this->payload['email'])
        ->addWhere('contact_id.is_deleted', '=', FALSE)
        ->execute();
      foreach ($emails as $email) {
        $matches[$email['contact_id']][] = 'email';
      }
    }

    if (!empty($this->payload['phone'])) {
      // look for phone + name duplicates
      $contacts = Contact::get(FALSE)
        ->addSelect('id')
        ->setGroupBy([
          'id',
        ])
        ->setJoin([
          ['Phone AS phone', 'INNER'],
        ])
        ->addWhere('first_name', '=', $this->payload['firstname'])
        ->addWhere('last_name', '=', $this->payload['lastname'])
        ->addWhere('is_deleted', '=', FALSE)
        ->addWhere('phone.phone_numeric', '=', preg_replace('/[^\d]/', '', $this->payload['phone']))
        ->execute();
      foreach ($contacts as $contact) {
        $matches[$contact['id']][] = 'phone_and_name';
      }
    }

    if (count($matches) > 0) {
      // remove any contacts flagged with is_merge
      $mergedContacts = HubspotContact::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('hubspot_portal_id', '=', $item['hubspot_portal_id'])
        ->addWhere('contact_id', 'IN', array_keys($matches))
        ->addWhere('is_merge', '=', TRUE)
        ->execute();
      foreach ($mergedContacts as $mergedContact) {
        unset($matches[$mergedContact['contact_id']]);
      }
    }

    return $matches;
  }

  protected function getContactParameters($contactId = NULL): ?array {
    $contact = [];
    if (!empty($contactId)) {
      $contact = Contact::get(FALSE)
        ->addSelect('id', 'first_name', 'last_name', 'formal_title')
        ->addWhere('id', '=', $contactId)
        ->execute()
        ->first();
    }
    $record = [];
    foreach (['first_name', 'last_name', 'formal_title'] as $fieldName) {
      if ((empty($contact[$fieldName]) && !empty($this->civiPayload[$fieldName])) || ((string) $contact[$fieldName] != (string) $this->civiPayload[$fieldName])) {
        $record[$fieldName] = $this->civiPayload[$fieldName];
      }
    }
    if (!empty($record) && !empty($contact)) {
      $record['id'] = $contact['id'];
    }
    if (empty($contact)) {
      $record['contact_type:name'] = 'Individual';
    }
    return empty($record) ? NULL : ['records' => [$record]];
  }

  /**
   * @param $contactId
   *
   * @return array[]
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getEmailParameters($contactId): ?array {
    if (empty($this->civiPayload['email'])) {
      return NULL;
    }
    $email = Email::get(FALSE)
      ->addSelect('id', 'email')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();
    if (!empty($email['email']) && $email['email'] == $this->civiPayload['email']) {
      return NULL;
    }
    $record = [
      'location_type_id:name' => 'Main',
      'contact_id' => $contactId,
      'email' => $this->civiPayload['email'],
      'is_primary' => TRUE,
    ];
    if (!empty($email['id'])) {
      $record['id'] = $email['id'];
    }
    return ['records' => [$record]];
  }

  /**
   * @param $contactId
   *
   * @return array[]
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getPhoneParameters($contactId): ?array {
    if (empty($this->civiPayload['phone'])) {
      return NULL;
    }
    $phone = Phone::get(FALSE)
      ->addSelect('id', 'phone_numeric')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();
    if (!empty($phone['phone_numeric']) && $phone['phone_numeric'] == preg_replace('/[^\d]/', '', $this->civiPayload['phone'])) {
      return NULL;
    }
    $record = [
      'location_type_id:name' => 'Main',
      'contact_id' => $contactId,
      'phone' => $this->civiPayload['phone'],
      'is_primary' => TRUE,
    ];
    if (!empty($phone['id'])) {
      $record['id'] = $phone['id'];
    }
    return ['records' => [$record]];
  }

  /**
   * @param $contactId
   *
   * @return array[]
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getGroupContactParameters($contactId): ?array {
    if (empty($this->hubspotPortal['config']['subscription_map'])) {
      return NULL;
    }
    $groupNames = array_values($this->hubspotPortal['config']['subscription_map']);
    $groupContacts = GroupContact::get(FALSE)
      ->addSelect('id', 'group_id:name', 'status')
      ->addWhere('group_id:name', 'IN', $groupNames)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()
      ->indexBy('group_id:name');
    $records = [];
    foreach ($this->hubspotPortal['config']['subscription_map'] as $fieldName => $groupName) {
      if ($this->payload[$fieldName] == 'true' && (empty($groupContacts[$groupName]) || $groupContacts[$groupName]['status'] != 'Added')) {
        $record = [
          'group_id:name' => $groupName,
          'contact_id' => $contactId,
          'status' => 'Added',
        ];
        if (!empty($groupContacts[$groupName]['id'])) {
          $record['id'] = $groupContacts[$groupName]['id'];
        }
        $records[] = $record;
      }
      if ((empty($this->payload[$fieldName]) || $this->payload[$fieldName] == 'false') && !empty($groupContacts[$groupName]) && $groupContacts[$groupName]['status'] == 'Added') {
        $records[] = [
          'id' => $groupContacts[$groupName]['id'],
          'group_id:name' => $groupName,
          'contact_id' => $contactId,
          'status' => 'Removed',
        ];
      }
    }
    return empty($records) ? NULL : ['records' => $records];
  }


  /**
   * Process contacts merged in HubSpot
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function processMerge() {
    if (count($this->payload['merged-vids']) > 0) {
      foreach ($this->payload['merged-vids'] as $mergedVid) {
        if ($mergedVid != $this->payload['canonical-vid']) {
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

}
