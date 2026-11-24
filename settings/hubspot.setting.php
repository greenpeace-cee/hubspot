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
  'hubspot_default_mailing_campaign_id' => [
    'name'        => 'hubspot_default_mailing_campaign_id',
    'title'       => E::ts('HubSpot Default Mailing Campaign ID'),
    'description' => E::ts('Fallback campaign ID for HubSpot Mailings, that cannot be linked to an existing campaign'),
    'type'        => 'Integer',
    'html_type'   => 'text',
    'default'     => NULL,
    'is_domain'   => 1,
    'is_contact'  => 0,
  ],
];
