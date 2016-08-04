<?php
/*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2016 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/



/**
 * TEST FOR Registration.create
 */
function civicrm_api3_registration_test($params) {
  return civicrm_api3_create_success(civicrm_api3('Registration', 'create', array(
    'submission_date'  => date('YmdHis'),
    'registration_id'  => 'TEST-0001',
    'participant' => array(
        'first_name'               => 'Harold',
        'last_name'                => 'Testbloke',
        'email'                    => 'harold@testblo.ke',
        'contact_type'             => 'Individual',
        'participant_role'         => 'Main Contact',
        'participant_fee_amount'   => '100',
        'participant_fee_currency' => 'EUR',
        'participant_fee_level'    => 'Spouse',
      ),
    'additional_participants' => array(
      0 => array(
          'first_name'               => 'George',
          'last_name'                => 'Testbloke',
          'email'                    => 'george@testblo.ke',
          'contact_type'             => 'Individual',
          'participant_role'         => 'Youth',
          'participant_fee_amount'   => '200',
          'participant_fee_currency' => 'EUR',
          'participant_fee_level'    => 'Spouse',
        ),
      1 => array(
          'first_name'               => 'Sarah',
          'last_name'                => 'Testblokette',
          'email'                    => 'sarah@testbloke.te',
          'contact_type'             => 'Individual',
          'participant_role'         => 'Spouse',
          'participant_fee_amount'   => '400',
          'participant_fee_currency' => 'EUR',
          'participant_fee_level'    => 'Spouse',
        ),
    ))));
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_registration_test_spec(&$params) {}

