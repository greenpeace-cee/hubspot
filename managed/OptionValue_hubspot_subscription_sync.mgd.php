<?php

use CRM_Hubspot_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_hubspot_subscription_sync',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('HubSpot Subscription Sync'),
        'value' => '57',
        'name' => 'hubspot_subscription_sync',
        'weight' => 57,
        'description' => E::ts('Sync of email subscriptions from/back to HubSpot'),
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
];
