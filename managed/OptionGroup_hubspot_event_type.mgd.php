<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_hubspot_event_type',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'hubspot_event_type',
        'title' => E::ts('HubSpot Event Type'),
        'data_type' => 'String',
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
