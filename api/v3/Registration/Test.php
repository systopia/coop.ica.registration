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
  // return civicrm_api3_create_success(civicrm_api3('Registration', 'payment', json_decode('{
  //   "registration_id":"GA2017-999-03",
  //   "status":"Completed"
  //   }', true)
  // ));

  $data = json_decode('{
       "submission_date":"20160822094006",
       "registration_id":"GA2017-999-08",
       "event_id":"1",
       "payment_mode":"offline",
       "registration_language":"English",
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
             "custom_badge":"Supergirl",
             "first_name":"Super",
             "last_name":"Gal",
             "prefix_id":"1",
             "job_title":"Someorg",
             "custom_languages_spoken":"Malaysian",
             "custom_organisation_badge":"Someorg",
             "participant_note":"yeast",
             "email":"endres@systopia.de"
          },
          {
             "participant_fee_amount":100,
             "participant_key":"additional_partner_1",
             "participant_fee_currency":"EUR",
             "contact_type":"Individual",
             "participant_status":"Registered",
             "participant_role":[
                "Partner"
             ],
             "participant_fee_level":"Partner",
             "partner_of":"additional_1",
             "first_name":"Bernd",
             "last_name":"Brot",
             "custom_badge":"Omnomnom"
          }
       ],
       "created_version":"7.x-1.x-dev",
       "organisation":{
          "contact_type":"Organization",
          "organization_name":"The Small One",
          "phone":"12345677890",
          "street_address":"Franzstr. 11",
          "supplemental_address_1":"",
          "supplemental_address_2":"",
          "postal_code":"53111",
          "city":"Bonn",
          "country":"AL",
          "billing":{
            "street_address":"Franzstr. 11",
            "supplemental_address_1":"",
            "supplemental_address_2":"",
            "postal_code":"53111",
            "city":"Bonn",
            "country":"AL"
          }
       },
       "participant":{
          "participant_key":"main",
          "participant_fee_amount":750,
          "participant_fee_currency":"EUR",
          "contact_type":"Individual",
          "participant_status":"Registered",
          "participant_role":[
             "Main Contact",
             "Participant"
          ],
          "participant_fee_level":"International Member",
          "first_name":"Max",
          "last_name":"Power",
          "prefix_id":"3",
          "job_title":"Sebigboss",
          "custom_languages_spoken":[
             "English",
             "Malaysian"
          ],
          "custom_represented_organisation":"149",
          "custom_badge":"Maxpower",
          "custom_organisation_badge":"Sesmolone",
          "participant_note":"very hungry",
          "email":"endres@systopia.de"
       }
    }', true);
  $data['registration_id'] = $data['registration_id'] . rand();
  return civicrm_api3_create_success(civicrm_api3('Registration', 'create', $data));
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_registration_test_spec(&$params) {}

