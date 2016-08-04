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
 * Will process registration requests coming in via API
 */
class CRM_Registration_Processor {
  protected static $contribution_override = array('financial_type_id', 'payment_instrument_id', 'is_pay_later');
  protected static $participant_override  = array();
  protected static $line_item_override    = array();

  protected $data;
  protected $custom_field_map;

  function __construct(&$data) {
    $this->data = $data;
    $this->custom_field_map = array();
  }

  /**
   * Verify that the data is (proabably) sufficient to run the process
   *
   * This should catch the most common problems
   *
   * @return string  error message describing most urgent problem
   *         NULL    if everything's fine
   */
  public function verify() {
    // TODO
  }

  /**
   * main function to perform the registration process
   */
  public function createRegistration() {
    // step 1: create participant objects
    $master_participant_id = $this->createParticipant($this->data['participant']);
    if (!empty($this->data['additional_participants'])) {
      foreach ($this->data['additional_participants'] as $additional_participant) {
        $this->createParticipant($additional_participant, $master_participant_id);
      }
    }

    // step 2: create contribution + line items
    if (empty($this->data['additional_participants'])) {
      $this->createRegistrationPayment($this->data['participant']);
    } else {
      $this->createRegistrationPayment($this->data['participant'], $this->data['additional_participants']);
    }

    // step 3: add relationships
    // TODO: later
  }

  /**
   * Turn the participant data into 'Participant' (registration) entities
   */
  protected function createParticipant(&$pdata, $master_participant_id = NULL) {
    // derive some values
    $pdata['register_date'] = $this->data['submission_date'];
    if ($master_participant_id) {
      $pdata['registered_by_id'] = $master_participant_id;
    }

    // and create participant
    $result = civicrm_api3('Participant', 'create', $pdata);
    $pdata['participant_id'] = $result['id'];
  }

  /**
   * create a contribution, along with the line items for the individual participants
   */
  protected function createRegistrationPayment($main_participant, $other_participants = array()) {
    // first, calculate the total amount
    $total     = $main_participant['participant_fee_amount'];
    $currency  = $main_participant['participant_fee_currency'];
    foreach ($other_participants as $other_participant) {
      $total += $other_participant['participant_fee_amount'];
      if ($other_participant['participant_fee_currency'] != $currency) {
        throw new Exception("Inconsisten currencies in 'participant_fee_currency'", 1);        
      }
    }

    // compile contribution data
    $contribution_data = array(
      'contact_id'            => $main_participant['contact_id'],
      'trxn_id'               => $this->data['registration_id'],
      'currency'              => $currency,
      'total'                 => $total_amount,
      'is_pay_later'          => 1,
      'payment_instrument_id' => 5, // default (EFT)
      'financial_type_id'     => 4, // default (Event Fee)
      'receive_date'          => $this->data['submission_date'],
      );

    // override if respective values are present
    foreach (self::$contribution_override as $field_name) {
      if (isset($this->data[$field_name])) {
        $contribution_data[$field_name] = $this->data[$field_name];
      }
    }

    // and create the contribution
    $contribution = civicrm_api3('Contribution', 'create', $contribution_data);


    // now create all line items
    if ($main_participant['participant_status'] == "Registered") {
      $this->createLineItem($main_participant, $contribution['id'], $contribution_data['financial_type_id']);
    }
    foreach ($other_participants as $other_participant) {
      $this->createRegistrationLineItem($other_participant, $contribution['id'], $contribution_data['financial_type_id']);
    }
  }







  /**
   * create a registration line item
   */
  protected function createRegistrationLineItem($participant, $contribution_id, $financial_type_id) {
    $line_item_data = array(
      'entity_table'      => 'civicrm_participant',
      'entity_id'         => $participant['participant_id'],
      'contribution_id'   => $contribution_id,
      'qty'               => 1,
      'unit_price'        => $participant['participant_fee_amount'],
      'line_total'        => $participant['participant_fee_amount'],
      'participant_count' => 1,
      'financial_type_id' => $financial_type_id,
      );

    // override if respective values are present
    foreach (self::$line_item_override as $field_name) {
      if (isset($participant[$field_name])) {
        $line_item_data[$field_name] = $participant[$field_name];
      }
    }

    // and create line item
    civicrm_api3('LineItem', 'create', $line_item_data);
  }

  /**
   * resolve custom fields of format 'custom_myfieldname' to 'custom_43' (which is understood by the API)
   */
  protected function resolveCustomFields(&$data) {
    // extract all custom fields/values
    $custom_values = array();
    foreach ($data as $key => $value) {
      if ('custom_' == substr($key, 0, 7)) {
        if (preg_match("/^custom_\d+$/", $key)) {
          // this has already been resolved (e.g. custom_24)
          continue; 
        } else {
          // this has NOT been resolved (e.g. custom_registration_id)
          $custom_values[$key] = $value;
        }        
      }
    }

    // see if we know them already
    $used_custom_fields    = array_keys($custom_values);
    $known_custom_fields   = array_intersect_key($used_custom_fields, $this->custom_field_map);
    $missing_custom_fields = array_diff($used_custom_fields, $known_custom_fields);
    $this->lookupCustomFields($missing_custom_fields);

    // replace the custom fields in $data
    foreach ($custom_values as $key => $value) {
      $new_key = $this->custom_field_map[$key];
      $data[$new_key] = $value;
      unset($data[$key]);
    }
  }

  /**
   * query/find given custom field names and store results in $custom_field_map
   */
  protected function lookupCustomFields($missing_custom_fields) {
    if (empty($missing_custom_fields)) return;

    $missing_field_names = array();
    foreach ($missing_custom_fields as $key) {
      $missing_field_names[] = substr($key, 7);
    }

    // look up and store field mappings
    $field_lookup = civicrm_api3('CustomField', 'get', array('name' => array('IN' => $missing_field_names)));
    foreach ($field_lookup['values'] as $custom_field) {
      if (!isset($custom_field_map["custom_{$custom_field['name']}"])) {
        $custom_field_map["custom_{$custom_field['name']}"] = "custom_{$custom_field['id']}";
      } elseif ($custom_field_map["custom_{$custom_field['name']}"] != "custom_{$custom_field['id']}") {
        throw new Exception("Custom field '{$custom_field['name']}' is ambiguous!", 1);
      }
    }

    // now check if there's any unresolved fields
    $still_missing_fields = array_diff($missing_custom_fields, array_keys($this->custom_field_map));
    if (!empty($still_missing_fields)) {
      $field_list = implode("','", $still_missing_fields);
      throw new Exception("Custom field(s) '$still_missing_fields' couldn't be resolved!", 1);
    }
  }
}
