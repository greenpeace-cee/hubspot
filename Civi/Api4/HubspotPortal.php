<?php
namespace Civi\Api4;

use Civi\Api4\Action\HubspotPortal\Poll;
use Civi\Api4\Action\HubspotPortal\Push;

/**
 * HubspotPortal entity.
 *
 * Provided by the HubSpot extension.
 *
 * @package Civi\Api4
 */
class HubspotPortal extends Generic\DAOEntity {

  /**
   * Poll HubSpot Portal for relevant changes
   *
   * @param bool $checkPermissions
   * @return \Civi\Api4\Action\HubspotPortal\Poll
   */
  public static function poll($checkPermissions = TRUE): Poll {
    $action = new Poll(__CLASS__, __FUNCTION__);
    return $action->setCheckPermissions($checkPermissions);
  }

  /**
   * Push missing and changed contacts to HubSpot Portal
   *
   * @param bool $checkPermissions
   * @return \Civi\Api4\Action\HubspotPortal\Push
   */
  public static function push($checkPermissions = TRUE): Push {
    $action = new Push(__CLASS__, __FUNCTION__);
    return $action->setCheckPermissions($checkPermissions);
  }

}
