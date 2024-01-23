<?php

namespace Civi\HubSpot\Sync;

use Civi\HubSpot\ContactUpdate;

abstract class AbstractSync {

  protected ContactUpdate $contactUpdate;

  /**
   * @var array
   */
  protected array $config;

  public function __construct(ContactUpdate $contactUpdate, array $config) {
    $this->contactUpdate = $contactUpdate;
    $this->config = $config;
  }

  protected function executeAndLogApi($entity, $action, array $params = []) {
    return $this->contactUpdate->executeAndLogApi($entity, $action, $params);
  }

  protected function sendAndLog(string $action, array $properties) {
    return $this->contactUpdate->sendAndLog($action, $properties);
  }

  public function sync() {
    $inbound = \CRM_Core_PseudoConstant::getKey(
      'CRM_Hubspot_BAO_HubspotContactUpdate',
      'update_type_id',
      'inbound'
    );
    if ($this->contactUpdate->item['update_type_id'] == $inbound && $this->config['inbound']) {
      $this->syncInbound();
    }
    elseif ($this->config['outbound']) {
      $this->syncOutbound();
    }
  }

  abstract protected function syncInbound();

  abstract protected function syncOutbound();

}
