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

define('ICA_LANGUAGES_CUSTOM_FIELD',    '7');
define('ICA_COUNTRY_CUSTOM_FIELD',      '8');

define('DOMPDF_ENABLE_AUTOLOAD', FALSE); // apparently CRM/Utils/PDF/Utils.php isn't included

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
  protected $location_types;

  function __construct($data) {
    $this->custom_field_map   = array();
    $this->custom_field_names = NULL;
    $this->location_types     = array();
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
      $contribution = $this->createRegistrationPayment($this->data['participant']);
    } else {
      $contribution = $this->createRegistrationPayment($this->data['participant'], $this->data['additional_participants']);
    }
    $this->processOrganisation($this->data['organisation']);

    // step 3: add relationships

    // TODO: later


    // step 4: send out emails
    $this->sendConfirmationEmail($this->data['participant'], $contribution);
  }

  /**
   * Turn the participant data into 'Participant' (registration) entities
   */
  protected function createParticipant(&$pdata, $master_participant = NULL) {
    // derive some values
    $pdata['custom_registration_id']   = $this->data['registration_id'];
    $pdata['register_date']            = $this->data['submission_date'];
    $pdata['custom_created_version']   = $this->data['created_version'];
    $pdata['custom_validation_status'] = 1;  // pending

    if (!empty($pdata['event_id'])) {
      $pdata['event_id'] = (int) $this->data['event_id'];
    } else {
      $pdata['event_id'] = (int) CRM_Registration_Configuration::getSetting('default_event');
    }

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

    // copy languages to contact (https://projekte.systopia.de/redmine/issues/3787)
    $this->updateContactLanguages($pdata['contact_id'], $pdata['custom_languages_spoken']);

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

    // finally: copy/fill data into the contact
    $this->fillContactData($pdata['contact_id'], $pdata);

    // finally: set the (custom) country ID if not yet set (ICA-4766)
    $this->fillContactCountry($pdata['contact_id'], $this->data['organisation']['country']);

    return $pdata;
  }

  /**
   * Process organisation data.
   *
   * The organisation was already created/matched by XCM,
   *  but we want to process the billing address here
   */
  protected function processOrganisation($organisation) {

    // get some stuff
    $organisation_id         = $organisation['contact_id'];
    $billing_address         = $organisation['billing'];
    $billing_location_id     = $this->getLocationTypeID("Billing",     "Billing", "Billing Address location");
    $old_billing_location_id = $this->getLocationTypeID("Billing_old", "Old Billing", "Formerly used billing address location");
    $address_compare_attributes = array('postal_code', 'city', 'street_address', 'supplemental_address_1', 'supplemental_address_2');
    $address_already_exists  = FALSE;

    // first, make sure that there are no other valid billing addresses
    $current_billing_addresses = civicrm_api3('Address', 'get', array(
      'contact_id'       => $organisation_id,
      'location_type_id' => $billing_location_id));
    if ($current_billing_addresses['count'] > 0) {
      foreach ($current_billing_addresses['values'] as $current_billing_address) {
        // check if the address is identical
        $address_is_identical = TRUE;
        foreach ($address_compare_attributes as $attribute) {
          if (  isset($current_billing_address[$attribute])
             && isset($billing_address[$attribute])
             && strcasecmp($current_billing_address[$attribute], $billing_address[$attribute])) {
            $address_is_identical = FALSE;
            break;
          }
        }

        // if this address is identical,
        if ($address_is_identical) {
          $address_already_exists = TRUE;
          continue;
        }

        // change existing billing addresses to "Billing_old"
        civicrm_api3('Address', 'create', array(
          'id'               => $current_billing_address['id'],
          'is_billing'       => '0',
          'is_primary'       => '0',
          'location_type_id' => $old_billing_location_id
          ));
      }
    }

    // now: create the brand new billing address
    if (!$address_already_exists) {
      $billing_address['contact_id']       = $organisation_id;
      $billing_address['location_type_id'] = $billing_location_id;
      $billing_address['is_billing']       = 1;
      civicrm_api3('Address', 'create', $billing_address);
    }

    // process email
    if (!empty($organisation['billing']['email'])) {
      $billing_email = $organisation['billing']['email'];
      $billing_email_exists = FALSE;

      // find existing billing addresses
      $existing_emails = civicrm_api3('Email', 'get', array(
        'contact_id'       => $organisation_id,
        'location_type_id' => $billing_location_id,
        ));
      foreach ($existing_emails['values'] as $existing_email) {
        if ($existing_email['email'] == $billing_email) {
          $billing_email_exists = TRUE;
        } else {
          // bump all old billing emails to billing_old
          civicrm_api3('Email', 'create', array(
            'id'               => $existing_email['id'],
            'email'            => $existing_email['email'],
            'location_type_id' => $old_billing_location_id));
        }
      }

      // create a new one
      if (!$billing_email_exists) {
        civicrm_api3('Email', 'create', array(
          'contact_id'       => $organisation_id,
          'email'            => $billing_email,
          'location_type_id' => $billing_location_id));
      }
    }

    // finally: set the (custom) country ID if not yet set (ICA-4766)
    $this->fillContactCountry($organisation_id, $organisation['country']);
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
      'contact_id'             => $this->data['organisation']['contact_id'],
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

    return reset($contribution['values']);
  }


  /**
   * create a registration line item
   */
  public function createRegistrationLineItem($participant, $contribution_id, $financial_type_id) {
    if (empty($participant['participant_fee_amount'])) return;

    $line_item_data = array(
      'entity_table'      => 'civicrm_participant',
      'entity_id'         => $participant['participant_id'],
      'contribution_id'   => $contribution_id,
      'label'             => "Event Fee: {$participant['participant_fee_level']} | {$participant['first_name']} {$participant['last_name']}",
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

    // add the Participant <-> Contribution link (ParticipantPayment):
    //  (see ICA-5311)
    civicrm_api3('ParticipantPayment', 'create', array(
      'participant_id'  => $participant['participant_id'],
      'contribution_id' => $contribution_id));
  }



  /**
   * compile and send out confirmation email for a received registration
   */
  protected function sendConfirmationEmail($participant, $contribution) {
    // first: check if we have an email address
    if (empty($participant['email'])) {
      error_log("coop.ica.registration: No valid email address submitted.");
      return;
    }
    $email = $participant['email'];
    $name  = $participant['first_name'] . ' ' . $participant['last_name'];

    // NOW: find the right template
    $prefix = CRM_Registration_Configuration::getSetting('registration_prefix');
    $template_id = self::loadTemplate($prefix . ' Registration Confirmation ', $this->data['registration_language']);
    if (empty($template_id)) return;

    // prepare additional participants
    $additional_participants = array();
    foreach ($this->data['additional_participants'] as $additional_participant) {
      $additional_participants[] = $this->renderParticiant($additional_participant, $language_used);
    }

    // add all the variables
    list($domainEmailName, $domainEmailAddress) = CRM_Core_BAO_Domain::getNameAndEmail();
    $emailDomain = CRM_Core_BAO_MailSettings::defaultDomain();
    $rendered_participant = $this->renderParticiant($participant, $language_used);
    $smarty_variables = array(
      'registration_id'         => $this->data['registration_id'],
      'participant'             => $rendered_participant,
      'participant_attending'   => in_array('Participant', $rendered_participant['participant_role']),
      'organisation'            => $this->renderParticiant($this->data['organisation'], $language_used),
      'additional_participants' => $additional_participants,
      'payment_mode'            => $this->data['payment_mode'],
      'contribution'            => $contribution,
      );

    // create an invoice
    $invoice_pdf = $this->generateInvoicePDF($contribution, $participant['contact_id'], $this->data['registration_id']);
    $attachment  = array('fullPath' => $invoice_pdf,
                         'mime_type' => 'application/pdf',
                         'cleanName' => basename($invoice_pdf));


    // and send the template via email
    $registration_confirmation = array(
      'id'              => $template_id,
      'contact_id'      => $participant['contact_id'],
      'to_name'         => $participant['first_name'] . ' ' . $participant['last_name'],
      'to_email'        => $participant['email'],
      'from'            => CRM_Registration_Configuration::getSetting('confirmation_sender'),
      'reply_to'        => "do-not-reply@$emailDomain",
      'template_params' => $smarty_variables,
      'attachments'     => array($attachment),
      'bcc'             => CRM_Registration_Configuration::getSetting('confirmation_bcc'),
      );

    if (!empty($this->data['organisation']['billing']['email'])) {
      $registration_confirmation['cc'] = $this->data['organisation']['billing']['email'];
    }

    civicrm_api3('MessageTemplate', 'send', $registration_confirmation);
  }



  /**
   * method to complete a pending online contribution
   */
  public function completePayment($registration_id, $contribution, $timestamp, $requested_status_id = 1) {
    if ($requested_status_id != 1) {
      throw new Exception("Contribution can currently only be set to 'Completed'");
    }

    $status_inprogress = CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');
    if ($contribution['contribution_status_id'] != $status_inprogress) {
      throw new Exception("Contribution {$contribution['id']} not in expected status 'In Progress'");
    }

    // mark contribution as completed
    civicrm_api3('Contribution', 'create', array(
      'id'                     => $contribution['id'],
      'contribution_status_id' => 1,
      'receive_date'           => $timestamp));

    // update contribution data
    $contribution['contribution_status_id'] = 1;
    $contribution['receive_date'] = $timestamp;
    $contribution['trxn_id'] = $registration_id;

    // and send out the invoice
    return $this->sendPaymentInvoice($contribution);
  }

  /**
   * Send out the invoice for a previously checked contribution, i.e.
   *  - contribution is GA payment
   *  - contribution is in status completed
   *  - contribution has registration_id set as trxn_id
   */
  public function sendPaymentInvoice($contribution, &$sent_to = array()) {
    // extract params
    $registration_id = $contribution['trxn_id'];

    // create an invoice
    $invoice_pdf = $this->generateInvoicePDF($contribution, $contribution['contact_id'], $registration_id);
    $attachment  = array('fullPath' => $invoice_pdf,
                         'mime_type' => 'application/pdf',
                         'cleanName' => basename($invoice_pdf));

    // load the contact via the main participant
    //   ... unfortunately the Paticipant API is broken, so we have to do a SQL query
    $registration_id_customfield = civicrm_api3('CustomField', 'getsingle', array('name' => 'registration_id'));
    $registration_language_customfield = civicrm_api3('CustomField', 'getsingle', array('name' => 'registration_communication_language'));
    $registration_customgroup = civicrm_api3('CustomGroup', 'getsingle', array('id' => $registration_id_customfield['custom_group_id']));
    $participant_ids = array();
    $participant_query = CRM_Core_DAO::executeQuery("SELECT `entity_id` FROM `{$registration_customgroup['table_name']}` WHERE `{$registration_id_customfield['column_name']}` = '{$registration_id}';");
    while ($participant_query->fetch()) {
      $participant_ids[] = $participant_query->entity_id;
    }
    $participants = civicrm_api3('Participant', 'get', array(
      'role_id' => 'Main Contact',
      'id' => array('IN' => $participant_ids),
      ));
    if (empty($participants['id'])) {
      throw new Exception("Main contact for registration not found!");
    }
    $participant = reset($participants['values']);
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $participant['contact_id']));

    // record all email addresses the mail is sent to
    $sent_to[] = $contact['email'];
    $sent_to[] = CRM_Registration_Configuration::getSetting('confirmation_bcc');

    // find the right email template
    $registration_language = CRM_Utils_Array::value("custom_{$registration_language_customfield['id']}", $participant, 'EN');
    $prefix = CRM_Registration_Configuration::getSetting('registration_prefix');
    $template_id = self::loadTemplate($prefix . ' Payment Completion ', $registration_language);
    if (!$template_id) {
      throw new Exception("Message template not found!");
    }

    // find the billing email
    $billing_email = NULL;
    $billing_location_type = civicrm_api3('LocationType', 'get', array('name' => 'Billing'));
    if (!empty($billing_location_type['id'])) {
      $billing_emails = civicrm_api3('Email', 'get', array(
        'contact_id'       => $contribution['contact_id'],
        'location_type_id' => $billing_location_type['id']));
      foreach ($billing_emails['values'] as $billing_email_entity) {
        $billing_email = $billing_email_entity['email'];
      }
    }

    // render and send a confirmation email
    $emailDomain = CRM_Core_BAO_MailSettings::defaultDomain();
    $smarty_variables = array(
      'registration_id' => $registration_id,
      'contact' => $contact,
      'contribution' => $contribution);

    $payment_confirmation = array(
      'id'              => $template_id,
      'contact_id'      => $contact['id'],
      'to_name'         => $contact['first_name'] . ' ' . $contact['last_name'],
      'to_email'        => $contact['email'],
      'from'            => CRM_Registration_Configuration::getSetting('confirmation_sender'),
      'reply_to'        => "do-not-reply@$emailDomain",
      'template_params' => $smarty_variables,
      'attachments'     => array($attachment),
      'bcc'             => CRM_Registration_Configuration::getSetting('confirmation_bcc'),
      );

    if (!empty($billing_email)) {
      $payment_confirmation['cc'] = $billing_email;
      $sent_to[] = $billing_email;
    }

    civicrm_api3('MessageTemplate', 'send', $payment_confirmation);
  }

  /**
   * fill certain attributes with the contact base data (not the participant object)
   */
  protected function fillContactData($contact_id, $pdata) {
    // list of attributes that should be filled
    $fill_values = array(
      'job_title' => 'job_title',
      );

    $eligible_data = array();
    foreach ($fill_values as $contact_field => $submission_field) {
      if (!empty($pdata[$submission_field])) {
        $eligible_data[$contact_field] = $pdata[$submission_field];
      }
    }

    if (!empty($eligible_data)) {
      // there is data eligible for submission -> load current values
      $contact_data = civicrm_api3('Contact', 'getsingle', array(
        'id'     => $contact_id,
        'return' => implode(',', array_keys($eligible_data)),
        ));

      $update_data = array();
      foreach ($eligible_data as $contact_field => $value) {
        if (empty($contact_data[$contact_field])) {
          $update_data[$contact_field] = $value;
        }
      }

      if (!empty($update_data)) {
        $update_data['id'] = $contact_id;
        civicrm_api3('Contact', 'create', $update_data);
      }
    }

    // additionally: process phone (see #3833)
    if (!empty($pdata['phone'])) {
      $work_location_id = $this->getLocationTypeID("Work", "Work", "Work location");
      $phone_type_id    = CRM_Core_OptionGroup::getValue('phone_type', 'Phone', 'label');
      $request = array(
        'contact_id'       => $contact_id,
        'location_type_id' => $work_location_id,
        'phone_type_id'    => $phone_type_id,
        );

      // first: find any existing phone of that type
      $existing_phones = civicrm_api3('Phone', 'get', $request);
      foreach ($existing_phones['values'] as $phone) {
        // overwrite any of them...
        $request['id'] = $phone['id'];
      }

      // now create/overwrite a new phone
      $request['phone'] = $pdata['phone'];
      civicrm_api3('Phone', 'create', $request);
    }
  }


  /**
   * add the languages spoken according to the registration to the languages stored with the contact
   */
  protected function updateContactLanguages($contact_id, $languages_submitted = array()) {
    if (empty($contact_id)) return;

    if (!is_array($languages_submitted)) {
      $languages_submitted = array($languages_submitted);
    }

    // load languages from contact
    $custom_field = 'custom_' . ICA_LANGUAGES_CUSTOM_FIELD;
    $contact_data = civicrm_api3('Contact', 'get', array(
      'id'     => $contact_id,
      'return' => $custom_field,
      ));
    $contact_data = reset($contact_data['values']);
    $languages_on_record = $contact_data[$custom_field];

    if (!is_array($languages_on_record)) {
      $languages_on_record = array($languages_on_record);
    }

    // merge the two lists
    $combined_langugages = array_unique(array_merge($languages_on_record, $languages_submitted));

    // remove empty strings (happens)
    $empty_entry = array_search('', $combined_langugages);
    if (!($empty_entry===NULL)) {
      unset($combined_langugages[$empty_entry]);
    }

    if ($combined_langugages != $languages_on_record) {
      // store the merged list
      try {
        civicrm_api3('Contact', 'create', array(
          'id'          => $contact_id,
          $custom_field => $combined_langugages));
      } catch (API_Exception $e) {
        error_log("coop.ica.registration: error while updating languages: " . $e->getMessage());
      }
    }
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
   * get location type ID, creating type if necessary
   */
  protected function getLocationTypeID($name, $display_name, $description) {
    if ($this->location_types[$name]) {
      return $this->location_types[$name];
    }

    $location_type = civicrm_api3('LocationType', 'get', array('name' => $name));
    if (empty($location_type['id'])) {
      // we need to create it:
      $location_type = civicrm_api3('LocationType', 'create', array(
        'name'         => $name,
        'display_name' => $display_name,
        'description'  => $description
        ));
    }

    $this->location_types[$name] = (int) $location_type['id'];
    return $this->location_types[$name];
  }

  /**
   * Will set the contact's country if it hasn't been set yet
   * @see ICA-4766
   */
  protected function fillContactCountry($contact_id, $country) {
    if (!empty($country)) {
      // first: find out if the country is already set
      $custom_field = 'custom_' . ICA_COUNTRY_CUSTOM_FIELD;
      $contact_data = civicrm_api3('Contact', 'getsingle', array(
        'id'     => $contact_id,
        'return' => $custom_field,
        ));
      if (empty($contact_data[$custom_field])) {
        // field is not yet set -> set our country
        civicrm_api3('Contact', 'create', array(
          'id'          => $contact_id,
          $custom_field => $country,
          ));
      }
    }
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

    // set communication language for main participant only
    $this->data['participant']['custom_registration_communication_language'] = $this->data['registration_language'];

    // TODO: more?
  }

  /**
   * Get the billing address from the given contact, in the following order:
   *  1) location type 'Billing'
   *  2) billing flag
   *  3) primary
   *
   * @param $contact_id integer contact ID
   * @return array|null billing address
   */
  public static function getBillingAddress($contact_id) {
    $billing_address = NULL;
    if (empty($contact_id)) {
      return NULL;
    }

    // first try with type Billing
    $addresses = civicrm_api3('Address', 'get', array(
        'contact_id'       => $contact_id,
        'location_type_id' => 'Billing',
    ));
    foreach ($addresses['values'] as $address) {
      $billing_address = $address;
      if ($address['is_billing'] || $address['is_primary']) {
        break;
      }
    }

    // then try is_billing
    if (!$billing_address) {
      $addresses = civicrm_api3('Address', 'get', array(
          'contact_id' => $contact_id,
          'is_billing' => 1,
      ));
      foreach ($addresses['values'] as $address) {
        $billing_address = $address;
        if ($address['is_primary']) {
          break;
        }
      }
    }

    // if still empty, try others
    if (!$billing_address) {
      $addresses = civicrm_api3('Address', 'get', array(
          'contact_id' => $contact_id,
          'is_billing' => 0,
      ));
      foreach ($addresses['values'] as $address) {
        $billing_address = $address;
        if ($address['is_primary']) {
          break;
        }
      }
    }
    return $billing_address;
  }

  /**
   * helper function to generate an invoice PDF
   */
  protected function generateInvoicePDF($contribution, $contact_id, $file_name) {
    if ($this->isFinalInvoice($contribution)) {
      // GENERATE INVOICE
      $contact_ids = array($contact_id);
      $contribution_ids = array($contribution['id']);
      $params = array('forPage' => 1, 'output' => 'pdf_invoice');
      $invoice_html = CRM_Contribute_Form_Task_Invoice::printPDF($contribution_ids, $params, $contact_ids);

      // DISABLED: DOMPDF crashes with large amounts of line items (registrations) (see #5305)
      // $invoice_pdf  = CRM_Contribute_Form_Task_Invoice::putFile($invoice_html, $file_name . '.pdf');
      $template        = self::getInvoiceTemplate();
      $pdf_filename    = "{$contribution['trxn_id']}.pdf";
      $pf_invoice_pdf  = CRM_Utils_PDF_Utils::html2pdf($invoice_html, $pdf_filename, TRUE, $template->pdf_format_id);
      file_put_contents($pdf_filename, $pf_invoice_pdf);
      return $pdf_filename;

//      return $invoice_pdf;

    } else {
      // GENERATE PRO FORMA INVOICE
      $template = self::getInvoiceTemplate();
      if ($template) {
        // prepare renderer
        $config = CRM_Core_Config::singleton();
        $smarty = CRM_Core_Smarty::singleton();
        $smarty->assign('resourceBase', $config->userFrameworkResourceURL);

        // add contact
        $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contribution['contact_id']));
        $smarty->assign('display_name', $contact['display_name']);
        $smarty->assign('organization_name', $contact['organization_name']);

        // add registrant billing address
        $billing_address = self::getBillingAddress($contribution['contact_id']);
        if ($billing_address) {
          foreach ($billing_address as $key => $value) {
            if ($key == 'country_id') {
              $smarty->assign('country', CRM_Core_PseudoConstant::country($value));
            } else {
              $smarty->assign($key, $value);
            }
          }
        }

        // add ICA address data
        $domain = CRM_Core_BAO_Domain::getDomain();
        $locationDefaults = CRM_Core_BAO_Location::getValues(array('contact_id' => $domain->contact_id));
        $smarty->assign('domain_organization', $domain->name);
        $smarty->assign('domain_email', CRM_Utils_Array::value('email', CRM_Utils_Array::value('1', $locationDefaults['email'])));
        $smarty->assign('domain_phone', CRM_Utils_Array::value('phone', CRM_Utils_Array::value('1', $locationDefaults['phone'])));
        if (!empty($locationDefaults['address'][1])) {
          $ica_address = $locationDefaults['address'][1];
          foreach ($ica_address as $key => $value) {
            if ($key == 'country_id') {
              $smarty->assign('domain_country', CRM_Core_PseudoConstant::country($value));
            } else {
              $smarty->assign("domain_{$key}", $value);
            }
          }
        }

        // add contribution data
        foreach ($contribution as $key => $value) {
          $smarty->assign($key, $value);
        }
        $smarty->assign('contribution', $contribution);
        $smarty->assign('amount', $contribution['total_amount']);
        $smarty->assign('amountDue', $contribution['total_amount']);
        $smarty->assign('invoice_date', date('F j, Y'));
        // $smarty->assign('dueDate', date('F j, Y', strtotime("+21 days")));

        // add some random stuff
        $smarty->assign('invoice_id', '');
        $smarty->assign('refundedStatusId', CRM_Core_OptionGroup::getValue('contribution_status', 'Refunded', 'name'));
        $smarty->assign('cancelledStatusId', CRM_Core_OptionGroup::getValue('contribution_status', 'Cancelled', 'name'));

        // retreiving the subtotal and sum of same tax_rate
        $dataArray = array();
        $subTotal = 0;
        $lineItems = CRM_Price_BAO_LineItem::getLineItems($contribution['id'], 'contribution', NULL, TRUE, TRUE);
        foreach ($lineItems as $entity_id => $taxRate) {
          if (isset($dataArray[(string) $taxRate['tax_rate']])) {
            $dataArray[(string) $taxRate['tax_rate']] = $dataArray[(string) $taxRate['tax_rate']] + CRM_Utils_Array::value('tax_amount', $taxRate);
          } else {
            $dataArray[(string) $taxRate['tax_rate']] = CRM_Utils_Array::value('tax_amount', $taxRate);
          }
          $subTotal += CRM_Utils_Array::value('subTotal', $taxRate);
        }
        $smarty->assign('subTotal', $subTotal);
        $smarty->assign('lineItem', $lineItems);


        // FINALLY: generate invoice
        $pf_invoice_html = $smarty->fetch("string:" . $template->msg_html);
        $pdf_filename    = "{$contribution['trxn_id']}.pdf";
        $pf_invoice_pdf  = CRM_Utils_PDF_Utils::html2pdf($pf_invoice_html, $pdf_filename, TRUE, $template->pdf_format_id);
        // $pdf_filename    = tempnam(sys_get_temp_dir(), 'PF_INV_');
        file_put_contents($pdf_filename, $pf_invoice_pdf);
        return $pdf_filename;

      } else {
        // template NOT found!
        error_log("coop.ica.registration: Unable to find invoice template. No pro forma invoice PDF generated.");
        return NULL;
      }
    }
  }

  /**
   * Get the BAO of the template to be used for invoicing
   */
  protected static function getInvoiceTemplate() {
    // find the template
    $template_query = CRM_Core_DAO::executeQuery("
        SELECT msg_html, pdf_format_id
          FROM civicrm_msg_template
          LEFT JOIN civicrm_option_value ON workflow_id = civicrm_option_value.id
          LEFT JOIN civicrm_option_group ON civicrm_option_value.option_group_id = civicrm_option_group.id
        WHERE civicrm_msg_template.is_default = 1
          AND civicrm_option_group.name = 'msg_tpl_workflow_contribution'
          AND civicrm_option_value.label = 'Contribution Invoice Receipt'
        ORDER BY civicrm_msg_template.id DESC
        LIMIT 1;");
    if ($template_query->fetch()) {
      return $template_query;
    } else {
      return NULL;
    }
  }

  /**
   * Verify id this contribution gets a final invoice
   */
  protected static function isFinalInvoice($contribution) {
    return (   $contribution['contribution_status_id'] != 2
            && $contribution['contribution_status_id'] != 5
            && $contribution['contribution_status_id'] != 8);
  }

  /**
   * Endpoint for "Print Invoice" button
   */
  public static function getInvoicePDF() {
    // get the contribution
    $contribution_id = CRM_Utils_Request::retrieve('id', 'Positive', CRM_Core_DAO::$_nullObject, FALSE);
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));

    // get the contact
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Positive', CRM_Core_DAO::$_nullObject, FALSE);

    $processor = new CRM_Registration_Processor(NULL);
    $pdf_file = $processor->generateInvoicePDF($contribution, $contact_id, $contribution['trxn_id']);
    $pdf_data = file_get_contents($pdf_file);
    CRM_Utils_System::download(basename($pdf_file), 'application/pdf', $pdf_data);
  }


  /**
   * helper function to find the right template
   */
  protected static function loadTemplate($template_pattern, $language) {
    // resolve langage
    switch ($language) {
      case 'French':
      case 'Français':
      case 'FR':
        $language = 'FR';
        break;

      case 'Spanish':
      case 'Español':
      case 'ES':
        $language = 'ES';
        break;

      case 'German':
      case 'Deutsch':
      case 'DE':
        $language = 'DE';
        break;

      default:
      case 'EN':
      case 'English':
        $language = 'EN';
        break;
    }

    // try to load in the requested language
    $templates = civicrm_api3('MessageTemplate', 'get', array(
      'msg_title' => $template_pattern . $language,
      'return' => 'id'));

    // if not found, try to load in english
    if (empty($templates['id'])) {
      $templates = civicrm_api3('MessageTemplate', 'get', array(
        'msg_title' => $template_pattern . 'EN',
        'return' => 'id'));
    }

    if (empty($templates['id'])) {
      error_log("coop.ica.registration: Unable to find message template '{$template_pattern}EN'. No email sent.");
      return NULL;
    } else {
      return $templates['id'];
    }
  }
}
