<?php
use CRM_Hubspot_ExtensionUtil as E;

return [
  'name' => 'SubscriptionContact',
  'table' => 'civicrm_subscription_contact',
  'class' => 'CRM_Hubspot_DAO_SubscriptionContact',
  'getInfo' => fn() => [
    'title' => E::ts('SubscriptionContact'),
    'title_plural' => E::ts('SubscriptionContacts'),
    'description' => E::ts('Connects a Contact to a Subscription'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique SubscriptionContact ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
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
    'subscription_id' => [
      'title' => E::ts('Subscription ID'),
      'description' => E::ts('FK to Subscription'),
      'data_type' => 'Int',
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'entity_reference' => [
        'entity' => 'Subscription',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
      'required' => TRUE,
    ],
    'subscription_status' => [
      'title' => E::ts('Subscription Status'),
      'description' => E::ts('Status of the Subscription'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'pseudoconstant' => [
        'option_group_name' => 'subscription_status',
      ],
      'required' => TRUE,
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'description' => E::ts('Date of the account creation'),
      'data_type' => 'Date',
      'sql_type' => 'datetime',
      'input_type' => 'Date',
      'required' => TRUE,
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'modified_date' => [
      'title' => E::ts('Modified Date'),
      'description' => E::ts('Date of the last account modification'),
      'data_type' => 'Date',
      'sql_type' => 'datetime',
      'input_type' => 'Date',
      'required' => TRUE,
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
