<?php
use CRM_Hubspot_ExtensionUtil as E;

return [
  'name' => 'HubspotAccount',
  'table' => 'civicrm_hubspot_account',
  'class' => 'CRM_Hubspot_DAO_HubspotAccount',
  'getInfo' => fn() => [
    'title' => E::ts('HubspotAccount'),
    'title_plural' => E::ts('HubspotAccounts'),
    'description' => E::ts('Account enabling access to the HubSpot API'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'description' => E::ts('Unique entity ID'),
      'data_type' => 'Int',
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'account_id' => [
      'title' => E::ts('Account ID'),
      'description' => E::ts('Unique account identifier in HubSpot'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'name' => [
      'title' => E::ts('Account Name'),
      'description' => E::ts('Name of the HubSpot account'),
      'data_type' => 'String',
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
    ],
    'api_key' => [
      'title' => E::ts('API Key'),
      'description' => E::ts('HubSpot API key'),
      'data_type' => 'String',
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
    ],
    'config' => [
      'title' => E::ts('Account Configuration'),
      'description' => E::ts('HubSpot account configuration'),
      'data_type' => 'Text',
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'serialize' => CRM_Core_DAO::SERIALIZE_JSON,
      'required' => FALSE,
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
  'getIndices' => fn() => [
    'UI_hubspot_account_id' => [
      'fields' => [
        'account_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
  ],
  'getPaths' => fn() => [],
];
