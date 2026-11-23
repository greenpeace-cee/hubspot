<?php

use Civi\Api4;
use CRM_Core_Session as Session;
use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchMailingImporter extends CRM_Hubspot_BatchProcessor {

  private static array $campaignIds = [];
  private static array $contactsByEmail = [];
  private static array $onlineMailingActivities = [];

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    $email_message_records = Api4\EmailMessage::save(FALSE)->setMatch(['external_id']);

    foreach ($batch as $mailing) {
      $campaign_id = self::resolveCampaignName($mailing['civicrm_campaign']);
      $default_campaign_id = Civi::settings()->get('hubspot_default_mailing_campaign_id');

      $email_message_records->addRecord([
        'campaign_id' => $campaign_id ?? $default_campaign_id,
        'external_id' => $mailing['id'],
        'subject'     => $mailing['subject'] ?? NULL,
        'from_name'   => $mailing['from']['fromName'] ?? NULL,
        'from_email'  => $mailing['from']['replyTo'] ?? NULL,
        'publish_date'=> $mailing['publishDate'] ?? NULL,
      ]);
    }

    $email_messages = [];

    foreach ($email_message_records->execute() as $email_message) {
      $email_messages[$email_message['external_id']] = $email_message;
    }

    foreach ($batch as $mailing) {
      $email_message = $email_messages[$mailing['id']];

      foreach ($mailing['allEmailCampaignIds'] ?? [] as $email_campaign_id) {
        $email_campaign = self::getEmailCampaignById($email_campaign_id);

        if (is_null($email_campaign)) continue;
        if (empty($email_campaign['appId'])) continue;

        $delivered_events = self::getDeliveredEmailEvents([
          'appId'      => $email_campaign['appId'],
          'campaignId' => $email_campaign_id,
          'eventType'  => 'DELIVERED',
        ]);

        $email_recipient_records = [];

        foreach ($delivered_events as $event) {
          $contact = self::getContactWithEmail($event['recipient']);

          if (is_null($contact)) continue;

          $event_time = new DateTimeImmutable('@' . (string) ($event['created'] / 1000));
          $timezone = new DateTimeZone(date_default_timezone_get());
          $event_day = $event_time->setTimezone($timezone)->setTime(0, 0);

          $online_mailing_activity_id = self::getOrCreateOnlineMailingActivity([
            'activity_date_time' => $event_day->format('c'),
            'email_message_id'   => $email_message['id'],
            'subject'            => substr("HubSpot Mailing ({$email_message['external_id']}): {$email_message['subject']}", 0, 255),
          ])['id'];

          $email_recipient_records["$online_mailing_activity_id:{$contact['id']}"] = [
            'activity_id'   => $online_mailing_activity_id,
            'contact_id'    => $contact['id'],
            'received_date' => $event_time->format('c'),
          ];

          if (count($email_recipient_records) < 100) continue;

          self::saveEmailRecipients($email_recipient_records);
        }

        self::saveEmailRecipients($email_recipient_records);
      }
    }

    return TRUE;
  }

  private static function getContactWithEmail(string $email): ?array {
    if (empty(self::$contactsByEmail[$email])) {
      self::$contactsByEmail[$email] = Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->addWhere('hubspot_sync.email', '=', $email)
        ->addWhere('hubspot_sync.sync_status', 'NOT IN', ['deleted', 'merged'])
        ->addWhere('is_deleted', '=', FALSE)
        ->setLimit(1)
        ->execute()
        ->first();
    }

    return self::$contactsByEmail[$email] ?? NULL;
  }

  private static function getDeliveredEmailEvents(array $params = []): Generator {
    $has_more = TRUE;
    $offset = NULL;

    while ($has_more) {
      $params['offset'] = $offset;

      try {
        $response = ApiClient::getEmailEvents($params);
      } catch (GuzzleHttp\Exception\BadResponseException $exception) {
        $status_code = $exception->getResponse()->getStatusCode();

        if ($status_code === 429) sleep(60);

        throw $exception;
      }

      $response_payload = json_decode((string) $response->getBody(), TRUE);

      foreach ($response_payload['events'] as $events) yield $events;

      $has_more = $response_payload['hasMore'];
      $offset = $response_payload['offset'];
    }
  }

  private static function getEmailCampaignById(string $email_campaign_id): ?array {
    try {
      $email_campaign_response = ApiClient::getEmailCampaignById($email_campaign_id);
      return json_decode((string) $email_campaign_response->getBody(), TRUE);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      $status_code = $exception->getResponse()->getStatusCode();

      if ($status_code === 404) return NULL;

      if ($status_code === 429) sleep(60);

      throw $exception;
    }
  }

  private static function getOrCreateOnlineMailingActivity(array $params): ?array {
    $activity_date = $params['activity_date_time'];

    if (empty(self::$onlineMailingActivities[$activity_date])) {
      self::$onlineMailingActivities[$activity_date] = Api4\Activity::save(FALSE)
        ->addRecord([
          'activity_date_time'                          => $activity_date,
          'activity_type_id:name'                       => 'Online_Mailing',
          'online_mailing_information.email_message_id' => $params['email_message_id'],
          'source_contact_id'                           => Session::getLoggedInContactId(),
          'subject'                                     => $params['subject'],
        ])
        ->setMatch([
          'activity_type_id',
          'activity_date_time',
          'online_mailing_information.email_message_id',
        ])
        ->execute()
        ->first();
    }

    return self::$onlineMailingActivities[$activity_date] ?? NULL;
  }

  private static function resolveCampaignName(string $campaign_name): ?int {
    if (empty(self::$campaignIds[$campaign_name])) {
      $campaign = Api4\Campaign::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', $campaign_name)
        ->setLimit(1)
        ->execute()
        ->first();

      if (isset($campaign)) {
        self::$campaignIds[$campaign_name] = $campaign['id'];
      }
    }

    return self::$campaignIds[$campaign_name] ?? NULL;
  }

  private static function saveEmailRecipients(array &$records): void {
    if (empty($records)) return;

    civicrm_api4('EmailRecipient', 'save', [
      'checkPermissions' => FALSE,
      'records' => array_values($records),
      'match' => ['activity_id', 'contact_id'],
    ]);

    $records = [];
  }

}
