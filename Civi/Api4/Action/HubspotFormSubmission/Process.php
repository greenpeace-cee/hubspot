<?php

namespace Civi\Api4\Action\HubspotFormSubmission;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Generic\BasicBatchAction;
use Civi\Api4\HubspotContact;
use Civi\Api4\HubspotFormSubmission;
use Civi\Api4\HubspotPortal;
use Civi\HubSpot\ConflictException;
use Civi\HubSpot\Converter;
use Civi\HubSpot\InvalidPropertiesException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class Process extends BasicBatchAction {

  /**
   * Criteria for selecting $ENTITIES to process.
   *
   * @var array
   */
  protected $where = [];
  static $mediumMap = [];

  public function __construct($entityName, $actionName) {
    if (empty(self::$mediumMap)) {
      $medium = Activity::getFields(FALSE)
        ->setLoadOptions(TRUE)
        ->addSelect('options')
        ->addWhere('name', '=', 'medium_id')
        ->execute()
        ->first();
      foreach ($medium['options'] as $value => $label) {
        self::$mediumMap[$label] = $value;
      }
    }
    parent::__construct($entityName, $actionName, [
      'id',
      'hubspot_portal_id',
      'hubspot_contact_id',
      'hubspot_timestamp',
      'submission_data',
    ]);
  }

  /**
   * @param array $item
   * @return array
   */
  protected function doTask($item) {
    // TODO: add lock
    $tx = new \CRM_Core_Transaction();
    try {
      $submission = Converter::getSubmissionProperties($item['submission_data']);
      $this->checkRequiredProperties($submission);
      $matches = $this->getMatches($submission);
      $hubspotPortal = HubspotPortal::get(FALSE)
        ->addWhere('id', '=', $item['hubspot_portal_id'])
        ->execute()
        ->first();
      if (count($matches) > 1) {
        throw new ConflictException('Found multiple match candidates. Please resolve duplicates (Format: CiviCRM Contact ID / Match Parameter(s)): ' . json_encode($matches));
      }
      if (count($matches) == 1) {
        $contactId = array_keys($matches)[0];
        $hubspotContact = HubspotContact::get(FALSE)
          ->addSelect('id')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('hubspot_portal_id', '=', $item['hubspot_portal_id'])
          ->execute()
          ->first();
        if (!empty($hubspotContact['id'])) {
          $petition = civicrm_api3('Survey', 'getsingle', [
            'return' => ['id', 'title'],
            'id' => $submission['civicrm_petition'],
          ]);
          Activity::create(FALSE)
            ->addValue('source_record_id', $petition['id'])
            ->addValue('subject', $petition['title'])
            ->addValue('status_id:name', 'Completed')
            ->addValue('activity_type_id:name', 'Petition')
            ->addValue('activity_date_time', $submission['submittedAt'])
            ->addValue('target_contact_id', $contactId)
            ->addValue('source_contact_id', 1)
            ->addValue('medium_id', self::$mediumMap[$submission['civicrm_medium']] ?? self::$mediumMap[$hubspotPortal['config']['default_medium_label']] ?? NULL)
            ->addValue('campaign_id', $submission['civicrm_campaign'] ?? $hubspotPortal['config']['default_campaign_id'] ?? NULL)
            ->addValue('source_contact_data.first_name', $submission['firstname'])
            ->addValue('source_contact_data.last_name', $submission['lastname'])
            ->addValue('source_contact_data.email', $submission['email'])
            ->addValue('source_contact_data.phone', $this->normalizePhone($submission['phone']))
            ->addValue('source_contact_data.newsletter', $submission[$hubspotPortal['config']['default_newsletter_field'] ?? 'newsletter'] ?? FALSE)
            ->addValue('utm.utm_source', $submission['utm_source'] ?? NULL)
            ->addValue('utm.utm_medium', $submission['utm_medium'] ?? NULL)
            ->addValue('utm.utm_campaign', $submission['utm_campaign'] ?? NULL)
            ->addValue('utm.utm_content', $submission['utm_content'] ?? NULL)
            ->addValue('petition_information.external_identifier', 'HS-' . $item['id'])
            ->execute();
          HubspotFormSubmission::update(FALSE)
            ->addWhere('id', '=', $item['id'])
            ->addValue('hubspot_contact_id', $hubspotContact['id'])
            ->addValue('form_submission_status_id:name', 'completed')
            ->addValue('status_details', NULL)
            ->execute();
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
        else {
          HubspotFormSubmission::update(FALSE)
            ->addWhere('id', '=', $item['id'])
            ->addValue('form_submission_status_id:name', 'pending')
            ->addValue('status_details', 'Missing HubSpotContact for contact ID ' . $contactId)
            ->execute();
        }
      }
      else {
        HubspotFormSubmission::update(FALSE)
          ->addWhere('id', '=', $item['id'])
          ->addValue('form_submission_status_id:name', 'pending')
          ->addValue('status_details', 'Unable to identify contact')
        ->execute();
      }
      $tx->commit();
    }
    catch (ConflictException $e) {
      $tx->rollback();
      unset($tx);
      HubspotFormSubmission::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('form_submission_status_id:name', 'conflicted')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (InvalidPropertiesException $e) {
      $tx->rollback();
      unset($tx);
      HubspotFormSubmission::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('form_submission_status_id:name', 'discarded')
        ->addValue('status_details', $e->getMessage())
        ->execute();
    }
    catch (\Exception $e) {
      $tx->rollback();
      unset($tx);
      HubspotFormSubmission::update(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->addValue('form_submission_status_id:name', 'failed')
        ->addValue('status_details', "{$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}: " . $e->getTraceAsString())
        ->execute();
    }
    finally {
      return HubspotFormSubmission::get(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->execute()
        ->first();
    }
  }

  /**
   * @param array $submission
   *
   * @throws \Civi\HubSpot\InvalidPropertiesException
   */
  protected function checkRequiredProperties(array $submission) {
    $requiredFields = ['email', 'civicrm_petition'];
    $missing = [];
    foreach ($requiredFields as $field) {
      if (empty($submission[$field])) {
        $missing[] = $field;
      }
    }
    if (count($missing) > 0) {
      throw new InvalidPropertiesException('Required properties are missing: ' . implode(', ', $missing));
    }
  }

  protected function getMatches(array $submission): array {
    $matches = [];
    if (!empty($submission['email'])) {
      // look for email duplicates
      $emails = Email::get(FALSE)
        ->addSelect('contact_id')
        ->setGroupBy([
          'contact_id',
        ])
        ->addWhere('email', '=', $submission['email'])
        ->addWhere('contact_id.is_deleted', '=', FALSE)
        ->execute();
      foreach ($emails as $email) {
        $matches[$email['contact_id']][] = 'email';
      }
    }

    if (!empty($submission['phone'])) {
      // look for phone + name duplicates
      $contacts = Contact::get(FALSE)
        ->addSelect('id')
        ->setGroupBy([
          'id',
        ])
        ->setJoin([
          ['Phone AS phone', 'INNER'],
        ])
        ->addWhere('first_name', '=', $submission['firstname'])
        ->addWhere('last_name', '=', $submission['lastname'])
        ->addWhere('is_deleted', '=', FALSE)
        ->addWhere('phone.phone_numeric', '=', preg_replace('/[^\d]/', '', $submission['phone']))
        ->execute();
      foreach ($contacts as $contact) {
        $matches[$contact['id']][] = 'phone_and_name';
      }
    }

    return $matches;
  }

  protected function normalizePhone($phone) {
    if (empty($phone)) {
      return $phone;
    }
    try {
      $include_file = dirname( __FILE__ ) . '/../../../../../com.cividesk.normalize/packages/libphonenumber/PhoneNumberUtil.php';
      if (file_exists($include_file)) {
        require_once $include_file;
        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneProto = $phoneUtil->parse($phone, \CRM_Core_BAO_Country::defaultContactCountry() ?? 'AT');
        if ($phoneUtil->isValidNumber($phoneProto)) {
          return $phoneUtil->format($phoneProto, PhoneNumberFormat::INTERNATIONAL);
        }
      }
    } catch (\Exception $e) {
      // ignore
    }
    return $phone;
  }

}
