<?php

namespace Civi\HubSpot\Match;

use Civi\Api4\HubspotContact;

class HubspotVid extends AbstractMatch {

  public function matchInbound() {
    $matches = [];
    // look for vid duplicates
    $hubspotContacts = HubspotContact::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('hubspot_portal_id', '=', $this->contactUpdate->item['hubspot_portal_id'])
      ->addWhere('hubspot_vid', '=', $this->contactUpdate->item['hubspot_vid'])
      ->addWhere('is_merge', '=', FALSE)
      ->execute();
    foreach ($hubspotContacts as $hubspotContact) {
      $matches[$hubspotContact['contact_id']][] = 'hubspot_vid';
    }
    return $matches;
  }

  public function matchOutbound() {
    $matches = [];
    if (!empty($this->contactUpdate->item['hubspot_vid'])) {
      $vids = $this->extractVids(
        $this->contactUpdate->client->contacts()->getById(
          $this->contactUpdate->item['hubspot_vid'],
          [
            'property' => ['lastmodifieddate'],
            'propertyMode' => 'value_only',
            'formSubmissionMode' => 'none',
            'showListMemberships' => FALSE,
          ]
        )
      );
      foreach ($vids as $vid) {
        $matches[$vid][] = 'hubspot_vid';
      }
    }
    return $matches;
  }

}
