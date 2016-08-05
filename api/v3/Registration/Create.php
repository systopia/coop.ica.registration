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
 * API entry for event registration
 *
 * This is not a CiviCRM entity, it will create a series of 
 * Participant, Contribution and LineItem objects
 * 
 * @return array API result array
 * @access public
 */
function civicrm_api3_registration_create($params) {
  $processor = new CRM_Registration_Processor($params);

  // verify input data
  $error = $processor->verify();
  if (!empty($error)) {
    return civicrm_api3_create_error($error);
  }

  // match/create all the contacts involved that have no ID using XCM
  if (empty($params['participant']['contact_id'])) {
    $xcm_query = civicrm_api3('Contact', 'getorcreate', $params['participant']);
    $params['participant']['contact_id'] = $xcm_query['id'];
  }
  if (!empty($params['additional_participants'])) {
    foreach ($params['additional_participants'] as &$participant) {
      if (empty($participant['contact_id'])) {
        $xcm_query = civicrm_api3('Contact', 'getorcreate', $participant);
        $participant['contact_id'] = $xcm_query['id'];
      }
    }
  }

  // finally, run the registration process
  $processor->updateData($params);
  $registration = $processor->createRegistration();

  // and return the good news (otherwise an Exception would have occurred)
  return civicrm_api3_create_success($registration);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_registration_create_spec(&$params) {
  $params['participant'] = array(
    'name'         => 'participant',
    'api.required' => 1,
    'title'        => 'Main Participant',
    'description'  => 'array containing all the participant data',
    );
  $params['additional_participants'] = array(
    'name'         => 'additional_participants',
    'api.required' => 0,
    'title'        => 'Additional Participants',
    'description'  => 'An array of additional participants, each one formatted like the "participant" parameter',
    );
  $params['event_id'] = array(
    'name'         => 'event_id',
    'api.required' => 1,
    'title'        => 'Event ID',
    'description'  => 'The event you want to register participants for',
    );
  $params['submission_date'] = array(
    'name'         => 'submission_date',
    'api.required' => 0,
    'title'        => 'Submission Date',
    'api.default'  => date('YmdHis'),
    'description'  => 'Timestamp of the submission (YmdHis), default is now'
    );
}

