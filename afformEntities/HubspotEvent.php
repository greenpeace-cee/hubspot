<?php
return [
  'type' => 'primary',
  'icon' => 'fa-bolt',
  'defaults' => "{
    data: {
      contact_id: 'user_contact_id'
    }
  }",
  'boilerplate' => [
    ['#tag' => 'af-field', 'name' => 'event_type_id'],
    // "Now" default: afform_default is read by current core, default_date_type by older versions (e.g. 5.69)
    ['#tag' => 'af-field', 'name' => 'event_date', 'defn' => ['afform_default' => 'now', 'default_date_type' => 'now']],
  ],
  'alterFields' => [
    'hubspot_id' => FALSE,
    'sync_date' => FALSE,
    'sync_failed' => FALSE,
    'created_date' => FALSE,
    'modified_date' => FALSE,
  ],
];
