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

  return civicrm_api3_create_success(civicrm_api3('Registration', 'create', json_decode('{
       "submission_date":"20160819154607",
       "created_version":"1.0",
       "registration_id":"GA2017-3-78",
       "event_id":"1",
       "additional_participants":[
          {
             "participant_key":"additional_1",
             "participant_fee_amount":750,
             "participant_fee_currency":"EUR",
             "contact_type":"Individual",
             "participant_status":"Registered",
             "participant_role":[
                "Group Participant",
                "Participant"
             ],
             "participant_fee_level":"International Member",
             "first_name":"kjhlkjhlk",
             "last_name":"lklkjhl",
             "prefix_id":"2",
             "custom_Position":"asdasd",
             "custom_Languages":"French",
             "custom_organisation_badge":"participanttest org",
             "participant_note":"kljhlkjh",
             "email":"endres@systopia.de"
          },
          {
             "participant_key":"additional_2",
             "participant_fee_amount":100,
             "participant_fee_currency":"EUR",
             "contact_type":"Individual",
             "participant_status":"Registered",
             "participant_role":[
                "Partner"
             ],
             "participant_fee_level":"Partner",
             "partner_of":"additional_1",
             "first_name":"asdkljkl",
             "last_name":"jhlhlkjh",
             "custom_badge":"lkjhlkjhl"
          }
       ],
       "organisation":{
          "contact_type":"Organization",
          "organization_name":"asdasdqw",
          "phone":"asdasd",
          "street_address":"igkjg",
          "supplemental_address_1":"jhlk",
          "supplemental_address_2":"h;kjh;",
          "postal_code":"jhgllkjh",
          "city":"d",
          "country":"AL"
       },
       "participant":{
          "participant_key":"main_participant",
          "participant_fee_amount":200,
          "participant_fee_currency":"EUR",
          "contact_type":"Individual",
          "participant_status":"Registered",
          "participant_role":[
             "Main Contact",
             "Participant"
          ],
          "participant_fee_level":"Youth",
          "first_name":"asdasd",
          "last_name":"asdasd",
          "prefix_id":"2",
          "custom_Position":"kjhoihl",
          "custom_Languages":[
             "Spanish",
             "French",
             "Malaysian"
          ],
          "custom_represented_organisation":"16742",
          "custom_badge":"asdsadkajl",
          "custom_organisation_badge":"hilhkj",
          "participant_note":"asdasdew",
          "email":"endres@systopia.de"
       }
    }', true)
  ));


  // // return civicrm_api3_create_success(civicrm_api3('Registration', 'create', array(
  //   'submission_date'  => date('YmdHis'),
  //   'registration_id'  => 'TEST-' . rand(),
  //   'event_id'         => 1,
  //   'participant' => array(
  //       'first_name'                 => 'Harold',
  //       'last_name'                  => 'Testbloke',
  //       'email'                      => 'harold@testblo.ke',
  //       'contact_type'               => 'Individual',
  //       'participant_status'         => 'Registered',
  //       'participant_role'           => 'Attendee',
  //       'participant_fee_amount'     => '100',
  //       'participant_fee_currency'   => 'EUR',
  //       'participant_fee_level'      => 'International',
  //       'custom_badge'               => 'Harry',
  //       'custom_organisation_badge'  => 'Testorg',
  //       'custom_Languages'           => 'English',
  //       'custom_created_version'     => '0.1',
  //     ),
  //   'additional_participants' => array(
  //     0 => array(
  //         'first_name'                 => 'George',
  //         'last_name'                  => 'Testbloke',
  //         'email'                      => 'george@testblo.ke',
  //         'contact_type'               => 'Individual',
  //         'participant_status'         => 'Registered',
  //         'participant_role'           => 'Attendee',
  //         'participant_fee_amount'     => '200',
  //         'participant_fee_currency'   => 'EUR',
  //         'participant_fee_level'      => 'Youth',
  //         'custom_badge'               => 'George',
  //         'custom_organisation_badge'  => 'Testorg',
  //         'custom_Languages'           => 'Spanish',
  //       ),
  //     1 => array(
  //         'first_name'                 => 'Sarah',
  //         'last_name'                  => 'Testblokette',
  //         'email'                      => 'sarah@testbloke.te',
  //         'contact_type'               => 'Individual',
  //         'participant_status'         => 'Registered',
  //         'participant_role'           => 'Partner',
  //         'participant_fee_amount'     => '400',
  //         'participant_fee_currency'   => 'EUR',
  //         'participant_fee_level'      => 'Spouse',
  //         'custom_badge'               => 'Sarah (George)',
  //         'custom_organisation_badge'  => '',
  //         'custom_Languages'           => 'Spanish',
  //       ),
  //   ))));
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_registration_test_spec(&$params) {}

