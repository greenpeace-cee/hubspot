<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_sync_status',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'sync_status',
        'title' => E::ts('HubSpot Sync Status'),
        'description' => E::ts('Status of the sync to HubSpot'),
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
    'name' => 'OptionGroup_sync_status_OptionValue_initial',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Initial (unsynced)'),
        'name' => 'initial',
        'value' => 'initial',
        'is_default' => TRUE,
        'description' => E::ts('The entity has not yet been synced to HubSpot'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_sync_status_OptionValue_successful',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Successful'),
        'name' => 'successful',
        'value' => 'successful',
        'description' => E::ts('The entity has been successfully synced to HubSpot'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_sync_status_OptionValue_failed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Failed'),
        'name' => 'failed',
        'value' => 'failed',
        'description' => E::ts('The last sync attempt has failed'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_sync_status_OptionValue_changed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Changed'),
        'name' => 'changed',
        'value' => 'changed',
        'description' => E::ts('The entity has been changed since the last sync'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_sync_status_OptionValue_deleted',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Deleted'),
        'name' => 'deleted',
        'value' => 'deleted',
        'description' => E::ts('The entity has been deleted'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_sync_status_OptionValue_merged',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'sync_status',
        'label' => E::ts('Merged'),
        'name' => 'merged',
        'value' => 'merged',
        'description' => E::ts('The entity has been merged'),
      ],
      'match' => [
        'name',
        'option_group_id',
      ],
    ],
  ],
];
