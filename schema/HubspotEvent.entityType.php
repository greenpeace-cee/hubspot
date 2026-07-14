<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  'name' => 'HubspotEvent',
  'table' => 'civicrm_hubspot_event',
  'class' => 'CRM_Hubspot_DAO_HubspotEvent',
  'getInfo' => fn() => [
    'title' => E::ts('HubspotEvent'),
    'title_plural' => E::ts('HubspotEvents'),
    'description' => E::ts('Events in HubSpot'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'description' => E::ts('Unique Event ID'),
      'data_type' => 'Int',
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'hubspot_id' => [
      'title' => E::ts('HubSpot ID'),
      'description' => E::ts('HubSpot Event ID'),
      'data_type' => 'String',
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => FALSE,
    ],
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'description' => E::ts('FK to Contact'),
      'data_type' => 'Int',
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
      'required' => TRUE,
    ],
    'event_type_id' => [
      'title' => E::ts('HubSpot Event Type'),
      'description' => E::ts('Event type name in HubSpot'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'pseudoconstant' => [
        'option_group_name' => 'hubspot_event_type',
      ],
      'required' => TRUE,
    ],
    'sync_date' => [
      'title' => E::ts('Sync Date'),
      'description' => E::ts('Date of the event sync'),
      'sql_type' => 'timestamp',
      'input_type' => 'Date',
      'required' => FALSE,
    ],
    'sync_failed' => [
      'title' => E::ts('Sync Failed'),
      'description' => E::ts('Did the sync to HubSpot fail?'),
      'sql_type' => 'tinyint(1)',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'default' => FALSE,
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'description' => E::ts('Date of creation'),
      'sql_type' => 'timestamp',
      'input_type' => 'Date',
      'required' => TRUE,
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'modified_date' => [
      'title' => E::ts('Modified Date'),
      'description' => E::ts('Date of last modification'),
      'sql_type' => 'timestamp',
      'input_type' => 'Date',
      'required' => TRUE,
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
