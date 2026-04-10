<?php

class CRM_Hubspot_HubspotContact {

  public string|null $id = NULL;
  public string|null $firstName = NULL;
  public string|null $lastName = NULL;
  public string|null $email = NULL;
  public int|null $civicrmID = NULL;
  public string|null $ownedBy = NULL;
  public int|null $ownershipScore = NULL;

  private static function from(array $contact_data, array $property_mapping): self {
    $contact = new self();

    foreach ($contact_data as $key => $value) {
      foreach (array_keys($property_mapping, $key) as $prop) {
        $contact->$prop = $value;
      }
    }

    return $contact;
  }

  public static function fromHubspotProperties(array $hubspot_contact_data): self {
    return self::from($hubspot_contact_data, [
      'id'             => 'hs_object_id',
      'firstName'      => 'firstname',
      'lastName'       => 'lastname',
      'email'          => 'email',
      'civicrmID'      => 'civicrm_id',
      'ownedBy'        => 'owned_by',
      'ownershipScore' => 'ownership_score',
    ]);
  }

  public static function fromCiviProperties(array $civi_contact_data): self {
    return self::from($civi_contact_data, [
      'id'             => 'hubspot_id',
      'firstName'      => 'first_name',
      'lastName'       => 'last_name',
      'email'          => 'email',
      'civicrmID'      => 'id',
      'ownedBy'        => 'owned_by',
      'ownershipScore' => 'ownership_score',
    ]);
  }

  public function toHubspotProperties(): array {
    return [
      'firstname'       => $this->firstName,
      'lastname'        => $this->lastName,
      'email'           => $this->email,
      'civicrm_id'      => $this->civicrmID,
      'owned_by'        => $this->ownedBy,
      'ownership_score' => $this->ownershipScore,
    ];
  }

}
