<?php

use Civi\Api4;

trait CRM_Hubspot_CountryIsoResolverTrait {

  private static array $_isoCodes = [];

  protected static function getIsoCode(int $country_id): ?string {
    if (isset(self::$_isoCodes[$country_id])) return self::$_isoCodes[$country_id];

    $country = Api4\Country::get(FALSE)
      ->addSelect('id', 'iso_code')
      ->addWhere('id', '=', $country_id)
      ->execute()
      ->first();

    if (is_null($country)) return NULL;

    self::$_isoCodes[$country_id] = $country['iso_code'];

    return $country['iso_code'];
  }

  protected static function getCountryId(string $iso_code): ?int {
    $country_id = array_search($iso_code, self::$_isoCodes);

    if (is_int($country_id)) return $country_id;

    $country = Api4\Country::get(FALSE)
      ->addSelect('id', 'iso_code')
      ->addWhere('iso_code', '=', $iso_code)
      ->execute()
      ->first();

    if (is_null($country)) return NULL;

    self::$_isoCodes[$country['id']] = $country['iso_code'];

    return $country['id'];
  }

}
