<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4\Generic;
use Civi\Api4\HubspotAccount;
use HubSpot;

/**
 * Query a list of contacts from the HubSpot API
 */
class ListContacts extends Generic\AbstractAction {

  public function _run(Generic\Result $result) {
    $account = HubspotAccount::get(FALSE)
      ->addSelect('api_key')
      ->execute()
      ->first();

    $client = HubSpot\Factory::createWithAccessToken($account['api_key']);
    $api_response = $client->crm()->contacts()->basicApi()->getPage();

    foreach ($api_response['results'] as $contact) {
      $result[] = $contact;
    }
  }

}

