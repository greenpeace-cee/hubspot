<?php
namespace Civi\Api4;

use Civi\Api4\Action\HubspotFormSubmission\Process;

/**
 * HubspotFormSubmission entity.
 *
 * Provided by the HubSpot extension.
 *
 * @package Civi\Api4
 */
class HubspotFormSubmission extends Generic\DAOEntity {
  /**
   * Process Pending HubSpot Form Submission
   *
   * @param bool $checkPermissions
   * @return \Civi\Api4\Action\HubspotFormSubmission\Process
   */
  public static function process($checkPermissions = TRUE): Process {
    $action = new Process(__CLASS__, __FUNCTION__);
    return $action->setCheckPermissions($checkPermissions);
  }
}
