<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_hubspot_sync_status',
    'entity' => 'OptionGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_initial',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_successful',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_failed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_changed',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_deleted',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
    'name' => 'OptionGroup_hubspot_sync_status_OptionValue_merged',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'hubspot_sync_status',
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
  [
    'name' => 'CustomGroup_hubspot_sync',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'hubspot_sync',
        'title' => E::ts('HubSpot Sync'),
        'table_name' => 'civicrm_value_hubspot_sync',
        'extends' => 'Individual',
        'collapse_display' => TRUE,
        'collapse_adv_display' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_hubspot_id',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'hubspot_id',
        'label' => E::ts('HubSpot Contact ID'),
        'column_name' => 'hubspot_id',
        'data_type' => 'String',
        'html_type' => 'Text',
        'text_length' => 24,
        'is_searchable' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_email',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'email',
        'label' => E::ts('Unique primary email address'),
        'column_name' => 'email',
        'data_type' => 'String',
        'html_type' => 'Text',
        'text_length' => 255,
        'is_searchable' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_ownership_score',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'ownership_score',
        'label' => E::ts('Current Ownership Score'),
        'column_name' => 'ownership_score',
        'data_type' => 'Int',
        'html_type' => 'Text',
        'default_value' => '0',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_owned_by',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'owned_by',
        'label' => E::ts('Owned by'),
        'column_name' => 'owned_by',
        'data_type' => 'String',
        'html_type' => 'Text',
        'text_length' => 6,
        'is_searchable' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_sync_status',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'sync_status',
        'label' => E::ts('Sync Status'),
        'column_name' => 'sync_status',
        'data_type' => 'String',
        'html_type' => 'Autocomplete-Select',
        'option_group_id.name' => 'hubspot_sync_status',
        'is_view' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_last_sync_date',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'last_sync_date',
        'label' => E::ts('Last Sync Date'),
        'column_name' => 'last_sync_date',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'date_format' => 'mm/dd/yy',
        'is_view' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_last_sync_payload',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'last_sync_payload',
        'label' => E::ts('Last Sync Payload'),
        'column_name' => 'last_sync_payload',
        'data_type' => 'Memo',
        'html_type' => 'TextArea',
        'text_length' => NULL,
        'attributes' => 'rows=4, cols=60',
        'is_view' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
