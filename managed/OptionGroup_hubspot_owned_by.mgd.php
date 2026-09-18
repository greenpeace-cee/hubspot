<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_HubSpot_Sync_owned_by',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'HubSpot_Sync_owned_by',
        'title' => E::ts('HubSpot Sync :: owned_by'),
        'data_type' => 'Int',
        'is_reserved' => FALSE,
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
  [
    'name' => 'OptionGroup_HubSpot_Sync_owned_by_OptionValue_AT',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'HubSpot_Sync_owned_by',
        'label' => E::ts('AT'),
        'value' => '1',
        'name' => 'AT',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
];
