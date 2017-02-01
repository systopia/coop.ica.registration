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
 * This will process changes to the registration fee payment
 * 
 * @return array API result array
 * @access public
 */
function civicrm_api3_registration_payment($params) { 
  // check input
  $status_id = CRM_Core_OptionGroup::getValue('contribution_status', $params['status'], 'name');
  if (!$status_id) {
    return civicrm_api3_create_error("Invalid status '{$params['status']}'!");
  }

  if (empty($params['timestamp']) /* TODO: || !parsedate */ ) {
    $params['timestamp'] = date('YmdHis');
  }

  // get the contribution (throws exception if not found)
  $contribution = civicrm_api3('Contribution', 'getsingle', array('trxn_id' => $params['registration_id']));
  // set the status ID
  try {
    _civicrm_api3_fix_API_UID();
    CRM_Registration_Processor::completePayment($params['registration_id'], $params['timestamp'], $contribution, $status_id);
  } catch (Exception $e) {
    civicrm_api3_create_error($e->getMessage());
  }

  // and return the good news (otherwise an Exception would have occurred)
  return civicrm_api3_create_success();
}

/**
 * Adjust Metadata for Payment action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_registration_payment_spec(&$params) {
  $params['registration_id'] = array(
    'name'         => 'registration_id',
    'api.required' => 1,
    'title'        => 'Registration ID',
    'description'  => 'Reference to the registration',
    );
  $params['status'] = array(
    'name'         => 'status',
    'api.required' => 1,
    'title'        => 'Transaction status',
    'description'  => 'Expects the name of one of the contribution status option values',
    );
  // $params['trxn_id'] = array(
  //   'name'         => 'trxn_id',
  //   'api.required' => 0,
  //   'title'        => 'transaction ID',
  //   'description'  => 'If the payment service provides a transaction ID, it will get stored for reference',
  //   );
  $params['timestamp'] = array(
    'name'         => 'timestamp',
    'api.required' => 0,
    'title'        => 'Timestamp of the transaction',
    'description'  => 'This will be handed down to the contributions\'s receive_date. Default is NOW',
    );
}


/**
 * Fixed API bug, where activity creation needs a valid userID
 *
 * Copied from https://github.com/CiviCooP/org.civicoop.apiuidfix
 * by Jaap Jansma, CiviCoop
 */
function _civicrm_api3_fix_API_UID() {
  // see https://github.com/CiviCooP/org.civicoop.apiuidfix
  $session = CRM_Core_Session::singleton();
  $userId = $session->get('userID');
  if (empty($userId)) {
    $valid_user = FALSE;

    // Check and see if a valid secret API key is provided.
    $api_key = CRM_Utils_Request::retrieve('api_key', 'String', $store, FALSE, NULL, 'REQUEST');
    if (!$api_key || strtolower($api_key) == 'null') {
      return; // nothing we can do
    }

    $valid_user = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $api_key, 'id', 'api_key');

    // If we didn't find a valid user, die
    if (!empty($valid_user)) {
      //now set the UID into the session
      $session->set('userID', $valid_user);
    }  
  }
}

