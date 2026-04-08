<?php

namespace Civi\Api4;

/**
 * Hubspot entity.
 *
 * Provided by the hubspot extension.
 *
 * @package Civi\Api4
 */
class Hubspot extends Generic\AbstractEntity {

  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

}
