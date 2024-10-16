<?php

namespace Civi\Api4\Action\HubspotPortal;

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Generic\BasicBatchAction;
use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotContactUpdate;
use Civi\Api4\HubspotFormSubmission;
use Civi\Api4\HubspotPortal;
use Civi\HubSpot\Converter;
use Civi\HubSpot\Listener;

class Poll extends BasicBatchAction {

  /**
   * Number of API records to fetch at a time.
   *
   * @var int
   */
  protected $batchSize = 10;

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

  private $hubspotPortal;

  public function __construct($entityName, $actionName) {
    parent::__construct($entityName, $actionName);
  }

  public function getSelect() {
    return [
      'id',
      'api_key',
      'config',
    ];
  }

  /**
   * @param array $item
   * @return array
   */
  protected function doTask($item) {
    // TODO: add lock
    $this->hubspotPortal = $item;
    Listener::$enabled = FALSE;
    $this->client = \SevenShores\Hubspot\Factory::createWithOAuth2Token($item['api_key']);
    $countUpdates = $this->getContactUpdates();
    $countFormSubmissions = $this->getFormSubmissions();

    return [
      'updates' => $countUpdates,
      'form_submissions' => $countFormSubmissions,
      'state' => $this->hubspotPortal['config']['state'] ?? NULL,
    ];
  }

  protected function getContactUpdates() {
    $maxTimestamp = $lastTimestamp = NULL;
    $count = 0;
    do {
      $query = [
        'count' => $this->batchSize,
        'property' => [
          'title',
          'firstname',
          'lastname',
          'phone',
          'email',
          'city',
          'zip',
          'address',
          'country',
          'civicrm_id',
          'lastmodifieddate',
          'createdate'
        ],
        'formSubmissionMode' => 'none',
      ];
      if (!empty($this->hubspotPortal['config']['subscription_map'])) {
        // request subscription properties
        $query['property'] = array_merge(
          $query['property'],
          array_keys($this->hubspotPortal['config']['subscription_map'])
        );
      }
      if (!empty($offset)) {
        $query['vidOffset'] = $offset;
      }
      $result = $this->client->contacts()->recent($query);
      foreach ($result->contacts as $contact) {
        $contact = (array) $contact;
        $lastTimestamp = $contact['addedAt'];
        if (empty($maxTimestamp) || $lastTimestamp > $maxTimestamp) {
          $maxTimestamp = $contact['addedAt'];
        }
        $existingUpdateCount = HubspotContactUpdate::get(FALSE)
          ->selectRowCount()
          ->addWhere('hubspot_portal_id', '=', $this->hubspotPortal['id'])
          ->addWhere('hubspot_vid', '=', $contact['vid'])
          ->addWhere('hubspot_timestamp', '=', $contact['addedAt'])
          ->execute();
        if ($existingUpdateCount->rowCount > 0) {
          // we've already seen this update, continue to the next item.
          // we can't break out of the loop because we may have crashed during
          // previous runs, and since results are returned in reverse-chronological
          // order, that would make us miss records. we must rely on
          // contact_recently_updated_last_timestamp instead, which is only
          // set after all updates are fetched without crashing.
          continue;
        }
        HubspotContactUpdate::create(FALSE)
          ->addValue('hubspot_portal_id', $this->hubspotPortal['id'])
          ->addValue('hubspot_vid', $contact['vid'])
          ->addValue('hubspot_timestamp', $contact['addedAt'])
          ->addValue('update_type_id:name', 'inbound')
          ->addValue('inbound_payload', $contact)
          ->addValue('update_status_id:name', 'pending')
          ->execute();
        $count++;
      }
      $offset = $result->{'vid-offset'};
      if (!empty($lastTimestamp) && $lastTimestamp <= $this->hubspotPortal['config']['state']['contact_recently_updated_last_timestamp']) {
        // last timestamp was already processed previously, stop looping
        break;
      }
    } while (count($result->contacts ?? []) > 0 && $result->{'has-more'});
    if (!empty($maxTimestamp)) {
      $this->hubspotPortal['config']['state']['contact_recently_updated_last_timestamp'] = $maxTimestamp;
      HubspotPortal::update(FALSE)
        ->addWhere('id', '=', $this->hubspotPortal['id'])
        ->addValue('config', $this->hubspotPortal['config'])
        ->execute();
    }
    return $count;
  }

  protected function getFormSubmissions() {
    $forms = $this->client->forms()->all();
    $count = 0;
    foreach ($forms->data as $form) {

      $maxTimestamp = $lastTimestamp = NULL;
      $params = [];
      do {
        $submissions = $this->client->forms()->getSubmissions($form->guid, $params);
        foreach ($submissions['results'] as $submission) {
          $submission = (array) $submission;
          $lastTimestamp = $submission['submittedAt'];
          if (empty($maxTimestamp) || $lastTimestamp > $maxTimestamp) {
            $maxTimestamp = $submission['submittedAt'];
          }
          if ($submission['submittedAt'] <= $this->hubspotPortal['config']['state']['form_submission_last_timestamp'][$form->guid] ?? 0) {
            break;
          }
          $existingSubmissionCount = HubspotFormSubmission::get(FALSE)
            ->selectRowCount()
            ->addWhere('hubspot_portal_id', '=', $this->hubspotPortal['id'])
            ->addWhere('guid', '=', $form->guid)
            ->addWhere('hubspot_timestamp', '=', $submission['submittedAt'])
            ->addWhere('submission_data', '=', $submission)
            ->execute();
          if ($existingSubmissionCount->rowCount > 0) {
            continue;
          }
          HubspotFormSubmission::create(FALSE)
            ->addValue('hubspot_portal_id', $this->hubspotPortal['id'])
            ->addValue('guid', $form->guid)
            ->addValue('hubspot_timestamp', $submission['submittedAt'])
            ->addValue('submission_data', $submission)
            ->addValue('form_submission_status_id:name', 'pending')
            ->execute();
          $count++;
        }
        if ($lastTimestamp <= $this->hubspotPortal['config']['state']['form_submission_last_timestamp'][$form->guid] ?? 0) {
          // last timestamp was already processed previously, stop looping
          break;
        }
        $params['after'] = $submissions['paging']['next']['after'];
      } while (!empty($submissions['paging']['next']['after']));
      if (!empty($maxTimestamp)) {
        $this->hubspotPortal['config']['state']['form_submission_last_timestamp'][$form->guid] = $maxTimestamp;
        HubspotPortal::update(FALSE)
          ->addWhere('id', '=', $this->hubspotPortal['id'])
          ->addValue('config', $this->hubspotPortal['config'])
          ->execute();
      }
    }
    return $count;
  }

  protected function getOrCreateHubspotContact($item, $contactUpdate) {
    $hubspotContact = HubspotContact::get(FALSE)
      ->addSelect('id')
      ->addWhere('hubspot_portal_id', '=', $item['id'])
      ->addWhere('hubspot_vid', '=', $contactUpdate->vid)
      ->execute()
      ->first();
    if (!empty($hubspotContact['id'])) {
      return $hubspotContact['id'];
    }
    $contact = Contact::create(FALSE)
      ->addValue('first_name', $contactUpdate->properties->firstname->value)
      ->addValue('last_name', $contactUpdate->properties->lastname->value)
      ->addChain('email', Email::create()
        ->addValue('contact_id', '$id')
        ->addValue('email', $contactUpdate->properties->email->value)
      )
      ->addChain('hubspot_contact', HubspotContact::create()
        ->addValue('contact_id', '$id')
        ->addValue('hubspot_portal_id', $item['id'])
        ->addValue('hubspot_vid', $contactUpdate->vid)
      )
      ->execute()
      ->first();
    return $contact['hubspot_contact'][0]['id'];
  }

}
