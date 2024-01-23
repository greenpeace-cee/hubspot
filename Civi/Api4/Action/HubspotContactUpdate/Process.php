<?php

namespace Civi\Api4\Action\HubspotContactUpdate;

use Civi\Api4\Generic\BasicBatchAction;
use Civi\HubSpot\ContactUpdate;

class Process extends BasicBatchAction {

  /**
   * Criteria for selecting $ENTITIES to process.
   *
   * @var array
   */
  protected $where = [];

  public function __construct($entityName, $actionName) {
    parent::__construct($entityName, $actionName);
  }

  /**
   * @param array $item
   *
   * @return array
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function doTask($item) {
    $update = new ContactUpdate($item);
    return $update->process();
  }

  public function getSelect() {
    return [
      'id',
      'update_type_id',
      'hubspot_portal_id',
      'hubspot_vid',
      'hubspot_timestamp',
      'inbound_payload',
      'hubspot_contact_id',
    ];
  }

}
