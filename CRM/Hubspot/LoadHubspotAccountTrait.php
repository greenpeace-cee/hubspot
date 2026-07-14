<?php

use Civi\Api4;

trait CRM_Hubspot_LoadHubspotAccountTrait {

  private static array $_hubspotAccount;

  private static function hubspotAccount(): array {
    if (!isset(self::$_hubspotAccount)) {
      self::$_hubspotAccount = Api4\HubspotAccount::get(FALSE)
        ->addSelect('*')
        ->execute()
        ->first();
    }

    return self::$_hubspotAccount;
  }

}
