<?php

use Civi\Api4;
use CRM_Core_Session as Session;
use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchMailingImporter extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    $email_message_records = Api4\EmailMessage::save(FALSE)->setMatch(['external_id']);

    foreach ($batch as $mailing) {
      $email_message_records->addRecord([
        'campaign_id.name' => $mailing['civicrm_campaign'],
        'external_id'      => $mailing['id'],
        'subject'          => $mailing['subject'],
        'from_name'        => $mailing['from']['fromName'],
        'from_email'       => $mailing['from']['replyTo'],
        'publish_date'     => $mailing['publishDate'],
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
          $event_time = new DateTimeImmutable('@' . (string) ($event['created'] / 1000));
          $timezone = new DateTimeZone(date_default_timezone_get());
          $event_day = $event_time->setTimezone($timezone)->setTime(0, 0);

          $online_mailing_activity_id = self::getOrCreateOnlineMailingActivity([
            'activity_date_time' => $event_day->format('c'),
            'email_message_id'   => $email_message['id'],
            'subject'            => substr("HubSpot Mailing ({$email_message['external_id']}): {$email_message['subject']}", 0, 255),
          ])['id'];

          $contact_id = self::getContactIdForEmail($event['recipient']);

          if (is_null($contact_id)) continue;

          $email_recipient_records["$online_mailing_activity_id:$contact_id"] = [
            'activity_id'   => $online_mailing_activity_id,
            'contact_id'    => $contact_id,
            'received_date' => $event_time->format('c'),
          ];
        }

        if (empty($email_recipient_records)) continue;

        civicrm_api4('EmailRecipient', 'save', [
          'checkPermissions' => FALSE,
          'records' => array_values($email_recipient_records),
          'match' => ['activity_id', 'contact_id'],
        ]);
      }
    }

    return TRUE;
  }

  private static function getContactIdForEmail(string $email): ?int {
    $contact = Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('hubspot_sync.email', '=', $email)
      ->addWhere('hubspot_sync.sync_status', 'NOT IN', ['deleted', 'merged'])
      ->addWhere('is_deleted', '=', FALSE)
      ->setLimit(1)
      ->execute()
      ->first();

    if (is_null($contact)) return NULL;

    return $contact['id'];
  }

  private static function getDeliveredEmailEvents(array $params = []): Generator {
    $has_more = TRUE;
    $offset = NULL;

    while ($has_more) {
      $params['offset'] = $offset;
      $response = ApiClient::getEmailEvents($params);
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
      if ($exception->getResponse()->getStatusCode() === 404) {
        return NULL;
      } else {
        throw $exception;
      }
    }
  }

  private static function getOrCreateOnlineMailingActivity(array $params): array {
    return Api4\Activity::save(FALSE)
      ->addRecord([
        'activity_date_time'                          => $params['activity_date_time'],
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

}
