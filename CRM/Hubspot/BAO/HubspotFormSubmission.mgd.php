<?php
return [
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status',
    'entity'  => 'OptionGroup',
    'cleanup' => 'never',
    'params'  => [
      'version'   => 3,
      'name'      => 'hubspot_form_submission_status',
      'title'     => 'Hubspot Form Submission Status',
      'data_type' => 'Integer',
      'is_active' => 1,
    ],
  ],
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status_pending',
    'entity'  => 'OptionValue',
    'cleanup' => 'never',
    'params'  => [
      'version'         => 3,
      'option_group_id' => 'hubspot_form_submission_status',
      'value'           => 1,
      'name'            => 'pending',
      'label'           => 'Pending',
      'is_active'       => 1,
    ],
  ],
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status_completed',
    'entity'  => 'OptionValue',
    'cleanup' => 'never',
    'params'  => [
      'version'         => 3,
      'option_group_id' => 'hubspot_form_submission_status',
      'value'           => 2,
      'name'            => 'completed',
      'label'           => 'Completed',
      'is_active'       => 1,
    ],
  ],
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status_failed',
    'entity'  => 'OptionValue',
    'cleanup' => 'never',
    'params'  => [
      'version'         => 3,
      'option_group_id' => 'hubspot_form_submission_status',
      'value'           => 3,
      'name'            => 'failed',
      'label'           => 'Failed',
      'is_active'       => 1,
    ],
  ],
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status_conflicted',
    'entity'  => 'OptionValue',
    'cleanup' => 'never',
    'params'  => [
      'version'         => 3,
      'option_group_id' => 'hubspot_form_submission_status',
      'value'           => 4,
      'name'            => 'conflicted',
      'label'           => 'Conflicted',
      'is_active'       => 1,
    ],
  ],
  [
    'module'  => 'hubspot',
    'name'    => 'hubspot_form_submission_status_discarded',
    'entity'  => 'OptionValue',
    'cleanup' => 'never',
    'params'  => [
      'version'         => 3,
      'option_group_id' => 'hubspot_form_submission_status',
      'value'           => 5,
      'name'            => 'discarded',
      'label'           => 'Discarded',
      'is_active'       => 1,
    ],
  ],
];
