<?php

namespace Civi\HubSpot\Match;

use Civi\Api4\Contact;

class Phone extends AbstractMatch {

  public function matchInbound() {
    $matches = [];
    if (!empty($this->contactUpdate->payload['phone'])) {
      // look for phone duplicates
      $contacts = Contact::get(FALSE)
        ->addSelect('id')
        ->setGroupBy([
          'id',
        ])
        ->setJoin([
          ['Phone AS phone', 'INNER'],
        ])
        ->addWhere('is_deleted', '=', FALSE)
        ->addWhere('phone.phone_numeric', '=', preg_replace('/[^\d]/', '', $this->contactUpdate->payload['phone']));

      if ($this->config['include_name']) {
        $contacts->addWhere('first_name', '=', $this->contactUpdate->payload['firstname']);
        $contacts->addWhere('last_name', '=', $this->contactUpdate->payload['lastname']);
      }
      $contacts = $contacts->execute();
      foreach ($contacts as $contact) {
        $matches[$contact['id']][] = 'phone_and_name';
      }
    }
    return $matches;
  }

  public function matchOutbound() {
    // TODO: cannot implement with legacy API
    return [];
  }

}
