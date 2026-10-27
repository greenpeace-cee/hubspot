<?php

use Civi\Api4;
use CRM_Hubspot_ApiClient as ApiClient;

class CRM_Hubspot_BatchMailingImporter extends CRM_Hubspot_BatchProcessor {

  public static function processBatch(CRM_Queue_TaskContext $_context, array $batch): bool {
    foreach ($batch as $mailing) {
      $email_message_record = [
        'external_id'  => $mailing['id'],
        'subject'      => $mailing['subject'],
        'from_name'    => $mailing['from']['fromName'],
        'from_email'   => $mailing['from']['replyTo'],
        'publish_date' => $mailing['publishDate'],
      ];

      if (isset($mailing['campaign'])) {
        $campaign_response = ApiClient::getMarketingCampaign($mailing['campaign'], [
          'properties' => 'civicrm_campaign',
        ]);

        $campaign_response_body = json_decode((string) $campaign_response->getBody(), TRUE);
        $email_message_record['campaign_id.name'] = $campaign_response_body['properties']['civicrm_campaign'] ?? NULL;
      }

      Api4\EmailMessage::save(FALSE)
        ->addRecord($email_message_record)
        ->setMatch(['external_id'])
        ->execute();
    }

    return TRUE;
  }

}

