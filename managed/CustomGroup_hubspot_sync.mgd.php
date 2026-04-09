<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
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
    'name' => 'CustomGroup_hubspot_sync_CustomField_has_changes',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'has_changes',
        'label' => E::ts('Has Contact Property Changes?'),
        'column_name' => 'has_changes',
        'data_type' => 'Boolean',
        'html_type' => 'Toggle',
        'default_value' => '0',
        'is_view' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_hubspot_sync_CustomField_current_score',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'current_score',
        'label' => E::ts('Current Ownership Score'),
        'column_name' => 'current_score',
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
    'name' => 'CustomGroup_hubspot_sync_CustomField_owned_by',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'hubspot_sync',
        'name' => 'owned_by',
        'label' => E::ts('Owned By'),
        'column_name' => 'owned_by',
        'data_type' => 'Country',
        'html_type' => 'Select',
        'text_length' => 2,
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
        'data_type' => 'String',
        'html_type' => 'TextArea',
        'text_length' => 4096,
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
