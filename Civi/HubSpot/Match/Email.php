<?php

namespace Civi\HubSpot\Match;

use SevenShores\Hubspot\Exceptions\BadRequest;

class Email extends AbstractMatch {

  public function matchInbound() {
    $matches = [];
    if (!empty($this->contactUpdate->payload['email'])) {
      // look for email duplicates
      $emails = \Civi\Api4\Email::get(FALSE)
        ->addSelect('contact_id')
        ->setGroupBy([
          'contact_id',
        ])
        ->addWhere('email', '=', $this->contactUpdate->payload['email'])
        ->addWhere('contact_id.is_deleted', '=', FALSE)
        ->execute();
      foreach ($emails as $email) {
        $matches[$email['contact_id']][] = 'email';
      }
    }
    return $matches;
  }

  public function matchOutbound() {
    $matches = [];
    try {
      if (!empty($this->contactUpdate->contactPrefetch['email_primary.email'])) {
        $vids = $this->extractVids(
          $this->contactUpdate->client->contacts()->getByEmail(
            $this->contactUpdate->contactPrefetch['email_primary.email'],
            [
              'property' => ['lastmodifieddate'],
              'propertyMode' => 'value_only',
              'formSubmissionMode' => 'none',
              'showListMemberships' => FALSE,
            ]
          )
        );
        foreach ($vids as $vid) {
          $matches[$vid][] = 'email';
        }
      }
    } catch (BadRequest $e) {
      // ignore 404
      if ($e->getCode() != 404) {
        throw $e;
      }
    }
    return $matches;
  }

}
