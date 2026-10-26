<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  'hubspot_always_yield_email' => [
    'name'        => 'hubspot_always_yield_email',
    'type'        => 'Boolean',
    'html_type'   => 'checkbox',
    'default'     => FALSE,
    'title'       => E::ts('Always yield on email conflicts'),
    'description' => E::ts('If enabled, contacts never claim an email address that is already used by another HubSpot contact, regardless of ownership scores. The contact is synced without the email instead.'),
    'is_domain'   => 1,
    'is_contact'  => 0,
  ],
];
