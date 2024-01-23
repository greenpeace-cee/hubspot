<?php

namespace Civi\HubSpot\Match;

use Civi\HubSpot\ContactUpdate;

abstract class AbstractMatch {

  protected ContactUpdate $contactUpdate;

  /**
   * @var array
   */
  protected array $config;

  public function __construct(ContactUpdate $contactUpdate, array $config) {
    $this->contactUpdate = $contactUpdate;
    $this->config = $config;
  }

  public function match() {
    $inbound = \CRM_Core_PseudoConstant::getKey(
      'CRM_Hubspot_BAO_HubspotContactUpdate',
      'update_type_id',
      'inbound'
    );
    if ($this->contactUpdate->item['update_type_id'] == $inbound) {
      return $this->matchInbound();
    }
    else {
      return $this->matchOutbound();
    }
  }

  protected function extractVids(\SevenShores\Hubspot\Http\Response $response) {
    if (!empty($response->getData()->vid)) {
      return [$response->getData()->vid];
    }
    return [];
  }

  abstract protected function matchInbound();
  abstract protected function matchOutbound();

}
