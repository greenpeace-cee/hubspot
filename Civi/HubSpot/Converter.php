<?php

namespace Civi\HubSpot;

use Civi\Api4\Contact;

class Converter {
  public static function getHubspotPayload(array $portalConfig, int $contactId, array $customData) {
    $payload = [];
    $contact = Contact::get(FALSE)
      ->addSelect('*', 'email.email')
      ->setJoin([
        ['Email AS email', 'LEFT', NULL, ['id', '=', 'email.contact_id'], ['email.is_primary', '=', 1]],
      ])
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();
    $properties = [
      'firstname' => $contact['first_name'],
      'lastname' => $contact['last_name'],
      'email' => $contact['email.email'],
    ];
    $properties = array_merge($properties, $customData);
    foreach ($properties as $property => $value) {
      $payload[] = ['property' => $property, 'value' => $value];
    }
    return $payload;
  }

  /**
   *
   *
   * @param array $data flat HubSpot payload as returned by getFlatPayload()
   *
   * @return array
   */
  public static function getCiviPayload(array $data): array {
    return [
      'first_name' => $data['firstname'] ?? NULL,
      'last_name' => $data['lastname'] ?? NULL,
      'formal_title' => $data['title'] ?? NULL,
      'email' => $data['email'] ?? NULL,
      'phone' => $data['phone'] ?? NULL,
      'street_address' => $data['address'] ?? NULL,
      'city' => $data['city'] ?? NULL,
      'postal_code' => $data['zip'] ?? NULL,
      'country_id:name' => $data['country'] ?? NULL,
    ];
  }

  public static function getFlatPayload(array $data): array {
    $result = [];
    foreach ($data as $key => $value) {
      if (!is_array($value)) {
        $result[$key] = $value;
      }
      else {
        if ($key == 'properties') {
          foreach ($value as $propertyKey => $propertyValue) {
            $result[$propertyKey] = $propertyValue['value'] ?? NULL;
          }
        }
        else {
          $result[$key] = $value;
        }
      }
    }
    return $result;
  }

  public static function getSubmissionProperties(array $submission): array {
    $properties = [
      'submittedAt' => (new \DateTime())->setTimestamp(floor($submission['submittedAt'] / 1000))->format('Y-m-d H:i:s'),
    ];
    foreach ($submission['values'] as $value) {
      $properties[$value['name']] = $value['value'];
    }
    return $properties;
  }
}
