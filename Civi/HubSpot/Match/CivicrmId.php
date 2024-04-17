<?php

namespace Civi\HubSpot\Match;

use Civi\Api4\Contact;

class CivicrmId extends AbstractMatch {

  public function matchInbound() {
    $matches = [];
    if (!empty($this->contactUpdate->payload['civicrm_id'])) {
      // look for civicrm_id duplicates
      $contacts = Contact::get(FALSE)
        ->addWhere('id', '=', $this->contactUpdate->payload['civicrm_id'])
        ->addWhere('is_deleted', '=', FALSE)
        ->execute();

      foreach ($contacts as $contact) {
        $matches[$contact['id']][] = 'civicrm_id';
      }

      // look for identity tracker duplicates
      if (function_exists('identitytracker_civicrm_install')) {
        // identitytracker is enabled
        $contacts = civicrm_api3('Contact', 'findbyidentity', [
          'identifier_type' => 'internal',
          'identifier' => $this->contactUpdate->payload['civicrm_id']
        ]);
        foreach ($contacts['values'] as $contact) {
          $contactIsDeleted = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('is_deleted')
            ->addWhere('id', '=', $contact)
            ->execute()
            ->first();
          if (empty($contactIsDeleted['is_deleted'])) {
            $matches[$contact['id']][] = 'civicrm_id_identity_tracker';
          }
        }
      }
    }
    return $matches;
  }

  public function matchOutbound() {
    // TODO: cannot implement with legacy API
    return [];
  }

}
