<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_civicrm_hubspot_event',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => E::ts('HubSpot Event'),
        'value' => 'HubspotEvent',
        'name' => 'civicrm_hubspot_event',
        'grouping' => 'event_type_id',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
];
