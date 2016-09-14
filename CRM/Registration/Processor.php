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

define('ICA_EVENT_SUBMISSION_PREFIX',  'GA2017');

/**
 * Will process registration requests coming in via API
 */
class CRM_Registration_Processor {
  protected static $contribution_override = array('financial_type_id', 'payment_instrument_id', 'is_pay_later');
  protected static $participant_override  = array();
  protected static $line_item_override    = array();

  protected $data;
  protected $custom_field_map;
  protected $custom_field_names;

  function __construct($data) {
    $this->custom_field_map = array();
    $this->custom_field_names = NULL;
    $this->updateData($data);
  }

  public function updateData($data) {
    $this->data = $data;
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
    // step 0: add extra data
    $this->enrichData();

    // step 1: create participant objects
    $master_participant = $this->createParticipant($this->data['participant']);
    if (!empty($this->data['additional_participants'])) {
      foreach ($this->data['additional_participants'] as &$additional_participant) {
        $this->createParticipant($additional_participant, $master_participant);
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


    // step 4: send out emails
    $this->sendConfirmationEmail($this->data['participant']);
  }

  /**
   * Turn the participant data into 'Participant' (registration) entities
   */
  protected function createParticipant(&$pdata, $master_participant = NULL) {
    // derive some values
    $pdata['custom_registration_id'] = $this->data['registration_id'];
    $pdata['register_date']          = $this->data['submission_date'];
    $pdata['event_id']               = $this->data['event_id'];
    $pdata['custom_created_version'] = $this->data['created_version'];
    if ($master_participant) {
      $pdata['registered_by_id']    = $master_participant['participant_id'];
      $pdata['custom_main_contact'] = $master_participant['contact_id'];
    }

    // set the organisation
    if (!empty($this->data['organisation']['contact_id'])) {
      $pdata['custom_registered_organisation'] = $this->data['organisation']['contact_id'];
    }

    // set "partner of" for partners
    if (!empty($pdata['partner_of'])) {
      $partner_of = $this->getParticipantData($pdata['partner_of']);
      if (empty($partner_of)) {
        throw new Exception("Referenced participant '{$pdata['partner_of']}' isn't part of this submission.", 1);
      } else {
        $pdata['custom_partner_of'] = $partner_of['contact_id'];
      }
    }

    // resolve custom fields
    $this->resolveCustomFields($pdata);
    // look up participant roles (automatic lookup doesn't work for arrays)
    if (is_array($pdata['participant_role'])) {
      $role_ids = array();
      foreach ($pdata['participant_role'] as $role_name) {
        if (is_numeric($role_name)) {
          $role_ids[] = $role_name;
        } else {
          $role_id = CRM_Core_OptionGroup::getValue('participant_role', $role_name, 'label');
          if ($role_id) {
            $role_ids[] = $role_id;
          } else {
            throw new Exception("Unknown participant role '{$role_name}'", 1);
          }
        }
      }
      $pdata['participant_role'] = $role_ids;
    }

    // and create participant
    $result = civicrm_api3('Participant', 'create', $pdata);
    $pdata['participant_id'] = $result['id'];

    return $pdata;
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
        throw new Exception("Inconsistent currencies in 'participant_fee_currency'", 1);
      }
    }

    // compile contribution data
    $contribution_data = array(
      'contact_id'             => $main_participant['contact_id'],
      'trxn_id'                => $this->data['registration_id'],
      'currency'               => $currency,
      'total_amount'           => $total,
      'financial_type_id'      => 4, // default (Event Fee)
      'receive_date'           => $this->data['submission_date'],
      );

    // process payment_mode
    switch ($this->data['payment_mode']) {
      case 'online':
        $contribution_data['is_pay_later']           = 0;
        $contribution_data['payment_instrument_id']  = 1; // Credit Card
        $contribution_data['contribution_status_id'] = CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');
        break;

      case 'offline':
        $contribution_data['is_pay_later']           = 1;
        $contribution_data['payment_instrument_id']  = 1; // Credit Card
        $contribution_data['contribution_status_id'] = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
        break;
      
      default:
        error_log("Unknown payment mode '{$this->data['payment_mode']}'.");
      case 'eft':
        $contribution_data['is_pay_later']           = 1;
        $contribution_data['payment_instrument_id']  = 5; // EFT
        $contribution_data['contribution_status_id'] = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
        break;
    }

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
      $this->createRegistrationLineItem($main_participant, $contribution['id'], $contribution_data['financial_type_id']);
    }
    foreach ($other_participants as $other_participant) {
      $this->createRegistrationLineItem($other_participant, $contribution['id'], $contribution_data['financial_type_id']);
    }

    // finally remove the default line item
    $original_line_item = civicrm_api3('LineItem', 'getsingle', array('contribution_id' => $contribution['id'], 'entity_table' => 'civicrm_contribution', 'entity_id' => $contribution['id']));
    civicrm_api3('LineItem', 'delete', array('id' => $original_line_item['id']));
  }







  /**
   * create a registration line item
   */
  protected function createRegistrationLineItem($participant, $contribution_id, $financial_type_id) {
    $line_item_data = array(
      'entity_table'      => 'civicrm_participant',
      'entity_id'         => $participant['participant_id'],
      'contribution_id'   => $contribution_id,
      'label'             => $participant['participant_fee_level'],
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
   * compile and send out confirmation email for a received registration
   */
  protected function sendConfirmationEmail($participant) {
    // first: check if we have an email address
    if (empty($participant['email'])) {
      error_log("coop.ica.registration: No valid email address submitted.");
      return;
    }
    $email = $participant['email'];
    $name  = $participant['first_name'] . ' ' . $participant['last_name'];

    // NOW: find the right template
    $template_pattern = ICA_EVENT_SUBMISSION_PREFIX . ' Registration Confirmation ';
    $languages = array('FR','ES', 'EN');  // TODO: get from contact
    $language_used = 'EN';
    $template = NULL;
    foreach ($languages as $language) {
      $templates = civicrm_api3('MessageTemplate', 'get', array('msg_title' => $template_pattern . $language, 'return' => 'id'));
      if ($templates['count'] == 1) {
        $template = reset($templates['values']);
        $language_used = $language;
        break;
      }
    }
    if (empty($template)) {
      error_log("coop.ica.registration: Unable to find message template '{$template_pattern}EN'. No email sent.");
      return;
    }

    // add all the variables
    $smarty_variables = array(
      'registration_id'  => $this->data['registration_id'],
      'participant'      => $this->renderParticiant($participant, $language_used),
      'organisation'     => $this->renderParticiant($this->data['organisation'], $language_used),
      );

    // and send the template via email
    civicrm_api3('MessageTemplate', 'send', array(
      'id'              => $template['id'],
      'contact_id'      => $participant['contact_id'],
      'to_name'         => $participant['first_name'] . ' ' . $participant['last_name'],
      'to_email'        => $participant['email'],
      'from'            => "TODO",
      'reply_to'        => "todo@to.do",
      'template_params' => $smarty_variables,
      // 'pdf_filename'    => 
      // 'bcc'    => 
      ));
  }



  

  /**
   * Prepare participant for rendering (in emails, etc.)
   */
  protected function renderParticiant($participant, $language) {
    // first make sure the custom IDs are being replaced
    $participant = $this->renameCustomFields($participant);

    // resolve country
    if (!empty($participant['country'])) {
      // TODO: respect language
      $country = civicrm_api3('Country', 'getsingle', array('iso_code' => $participant['country']));
      $participant['country'] = $country['name'];
    }

    // add/embed partner
    if (!empty($participant['participant_key'])) {
      foreach ($this->data['additional_participants'] as $additional_participant) {
        if (!empty($additional_participant['partner_of']) 
              && $participant['participant_key'] == $additional_participant['partner_of']) {
          $participant['partner'] = $this->renameCustomFields($additional_participant);
        break;
        }
      }
    }

    // resolve roles
    if (!empty($participant['participant_role'])) {
      $role_titles = array();
      foreach ($participant['participant_role'] as $role_id) {
        $role_titles[] = CRM_Core_OptionGroup::getLabel('participant_role', $role_id, FALSE);
      }
      $participant['participant_role'] = $role_titles;
    }


    return $participant;
  }

  /**
   * restore custom field IDs to their field name
   */
  protected function renameCustomFields($original_array) {
    if ($this->custom_field_names == NULL) {
      $this->custom_field_names = array_flip($this->custom_field_map);
    }

    $new_array = array();
    foreach ($original_array as $old_key => $value) {
      if (!empty($this->custom_field_names[$old_key])) {
        // if this is a custom field with ID, replace by custom field name
        $new_key = substr($this->custom_field_names[$old_key], 7);
      } else {
        $new_key = $old_key;
      }

      if (is_array($value)) {
        $new_array[$new_key] = $this->renameCustomFields($value);
      } else {
        $new_array[$new_key] = $value;
      }
    }
    
    return $new_array;
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
      if (!isset($this->custom_field_map["custom_{$custom_field['name']}"])) {
        $this->custom_field_map["custom_{$custom_field['name']}"] = "custom_{$custom_field['id']}";
        $this->custom_field_names = NULL;
      } elseif ($this->custom_field_map["custom_{$custom_field['name']}"] != "custom_{$custom_field['id']}") {
        throw new Exception("Custom field '{$custom_field['name']}' is ambiguous!", 1);
      }
    }

    // now check if there's any unresolved fields
    $still_missing_fields = array_diff($missing_custom_fields, array_keys($this->custom_field_map));
    if (!empty($still_missing_fields)) {
      $field_list = implode("','", $still_missing_fields);
      throw new Exception("Custom field(s) '$field_list' couldn't be resolved!", 1);
    }
  }

  /**
   * get the participant referenced by the participant_key
   *  this is used to e.g. link the registrant to a spouse
   */
  protected function getParticipantData($participant_key) {
    if ($this->data['participant']['participant_key'] == $participant_key) {
      return $this->data['participant'];
    } elseif ($this->data['organisation']['participant_key'] == $participant_key) {
      return $this->data['organisation'];
    } else {
      foreach ($this->data['additional_participants'] as $additional_participant) {
        if ($additional_participant['participant_key'] == $participant_key) {
          return $additional_participant;
        }
      }
    }

    // not found
    return NULL;
  }

  /**
   * add some extra data
   */
  protected function enrichData() {
    // get extension version
    $mapper = CRM_Extension_System::singleton()->getMapper();
    $info   = $mapper->keyToInfo('coop.ica.registration');
    if (empty($this->data['created_version'])) {
      $this->data['created_version'] = "n/a";
    }
    $this->data['created_version'] = $this->data['created_version'] . ' | ' . $info->version;

    // TODO: more?
  }
}
