<?php
namespace Civi\Api4;

use Civi\Api4\Action\HubspotContactUpdate\Process;

/**
 * HubspotContactUpdate entity.
 *
 * Provided by the HubSpot extension.
 *
 * @package Civi\Api4
 */
class HubspotContactUpdate extends Generic\DAOEntity {
  /**
   * Process Pending HubSpot Contact Update
   *
   * @param bool $checkPermissions
   * @return \Civi\Api4\Action\HubspotContactUpdate\Process
   */
  public static function process($checkPermissions = TRUE): Process {
    $action = new Process(__CLASS__, __FUNCTION__);
    return $action->setCheckPermissions($checkPermissions);
  }
}
