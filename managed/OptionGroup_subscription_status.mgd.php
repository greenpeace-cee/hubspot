<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_subscription_status',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'subscription_status',
        'title' => E::ts('Subscription Status'),
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
  [
    'name' => 'OptionGroup_subscription_status_OptionValue_opt_in',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'subscription_status',
        'label' => E::ts('Opt-In'),
        'value' => '1',
        'name' => 'opt_in',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_subscription_status_OptionValue_opt_out',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'subscription_status',
        'label' => E::ts('Opt-Out'),
        'value' => '2',
        'name' => 'opt_out',
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
