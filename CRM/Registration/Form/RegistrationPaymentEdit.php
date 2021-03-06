<?php
/*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2017 SYSTOPIA                            |
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
 * TODO DOKU
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Registration_Form_RegistrationPaymentEdit extends CRM_Core_Form {


  protected $cid                      = NULL;
  protected $contribution             = NULL;
  protected $new_contribution         = NULL;
  protected $registration_id          = NULL;
  protected $line_items               = NULL;
  protected $role2label               = NULL;
  protected $role2amount              = NULL;
  protected $participants             = NULL;
  protected $participant2label        = NULL;
  protected $contribStatus2label      = NULL;
  protected $paymentInstrument2label  = NULL;

  /**
   * Check
   */
  public function preProcess() {
    // load contribution_id from URL
    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (empty($this->cid)) {
      CRM_Core_Error::fatal("No contribution ID (cid) given.");
    } else {
      $this->add('hidden', 'cid', $this->cid);
    }

    // initialize variables/data
    $this->initializeInternalData();

    // load participants
    $this->populateParticipants();

    // load contribution stati
    $this->setContributionStati();
  }

  /**
   * Create form
   */
  public function buildQuickForm() {

    // generate lines
    $this->assign('line_numbers',   range(1, count($this->participants)));
    $i = 1;
    foreach ($this->participants as $participant) {
      $this->add('text',
        "participant_id_{$i}",
        'Participant',
        'readonly'
      );

      $fee_level_roles = $this->getfee_levelRoles_for_participant($participant);

      $this->add('select',
        "participant_role_{$i}",
        '(new) role',
        $fee_level_roles,
        FALSE,
        array('class' => 'participant-role')
      );

      $this->add('text',
        "participant_amount_{$i}",
        'amount',
        'readonly'
      );
      $i++;
    }

    $this->add('select',
      "contribution_status",
      'contribution_status',
      $this->contribStatus2label,
      FALSE,
      array('class' => 'contribution-status')
    );
    $this->add('static',
      "contribution_sum_description",
      'contribution_sum_description',
      "<b>Contribution</b>"
    );
    $this->add('text',
      "contribution_sum",
      'accumulated_amount',
      'readonly'
    );

    $this->assign('role2amount', json_encode($this->role2amount));
    $this->assign('status2label', json_encode($this->contribStatus2label));
    $this->assign('line_count', count($this->participants));

    //
    $this->add('select',
      "payment_method",
      'Payment Method',
      $this->paymentInstrument2label,
      FALSE,
      array('class' => 'contribution-paymentMethod')
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    parent::buildQuickForm();
  }

  /**
   * get the participants connected to the registration id
   * and set participant2label as well
   */
  protected function populateParticipants() {
    $this->participants = array();
    $this->participant2label = array();

    // participant API doesn't work here see CRM-16036
    // so we'll use SQL (and need to find the right table)

    // first: find the custom field / group involved
    $registration_id_field    = CRM_Registration_CustomData::getCustomField('GA_Registration', 'registration_id');
    $registration_id_column   = $registration_id_field['column_name'];
    $registration_group_table = CRM_Registration_CustomData::getGroupTable('GA_Registration');
    if (empty($registration_id_field) || empty($registration_group_table)) {
      // error: Field not found
      return;
    }

    $reg_id = $this->findTransactionIndex($this->registration_id);

    $participant_selector = CRM_Core_DAO::executeQuery("
      SELECT entity_id AS participant_id
      FROM {$registration_group_table} WHERE {$registration_id_column} = '{$reg_id['transaction_number']}'");
    $participant_ids = array();
    while ($participant_selector->fetch()) {
      $participant_ids[] = $participant_selector->participant_id;
    }

    if (!empty($participant_ids)) {
      $this->participants = civicrm_api3('Participant', 'get', array(
      'id'            => array('IN' => $participant_ids),
      'options.limit' => 0))['values'];
    }
    foreach ($this->participants as $key => $p) {
      if ($p['participant_status'] != "Cancelled") {
        $this->participant2label[$p['id']] = "{$p['display_name']} [{$p['id']}]";
      } else {
        unset($this->participants[$key]);
      }
    }
    $this->participant2label[0] = "";
  }

  public function setContributionStati() {
    $stati = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'options.limit'   => 0,
      'option_group_id' => "contribution_status",
    ))['values'];
    $this->contribStatus2label = array();
    // this should result in a valid contribStatus2label
    $this->contribStatus2label = CRM_Registration_Configuration::filterContributionStati($stati, $this->contribution['contribution_status']);
  }

  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    $values = array();

    // FIXME: example code
    $i = 1;
    foreach ($this->participants as $participant) {
      // set participant for lineItem as default value
      $values["participant_id_{$i}"] = $this->participant2label[$participant['id']];
      $role = $this->getRole($participant['participant_role']);
      $fee_level = array_search($participant['participant_fee_level'], $this->role2label);
      if (!isset($fee_level)) {
        $values["participant_role_{$i}"] = array_search($role, $this->role2label);
      } else {
        $values["participant_role_{$i}"] = $fee_level;
      }
      $i++;
    }

    $values["contribution_status"] = array_search($this->contribution['contribution_status'], $this->contribStatus2label);
    $values["payment_method"] = $this->contribution['payment_instrument_id'];
    return $values;
  }

  public function postProcess() {
    $values = $this->exportValues();

    // we reuse the add Line item Function from this class; but initialize it with NULL
    // don't need any other values
    $processor          = new CRM_Registration_Processor(NULL);
    $participants2role  = array();
    $total              = 0.00;

    // create new contribution
    $this->createNewContribution($values, $participants2role, $total);

    // create Line Items for new Contribution
    foreach ($participants2role as $id => $value) {
      // update Participants fee level&amount
      $this->updateParticipantData($value['fee_amount'], $value['fee_level'], $id);
      // get participant data for Line Item creation
      $participant = $this->getParticipant($id);

      // FixMe
      if ($participant['participant_fee_level'] != 'Not Attending' ) {
        // create Line items
        $processor->createRegistrationLineItem($participant, $this->new_contribution['id'], $this->new_contribution['financial_type_id']);
        $this->updateParticipantPayment($id, $this->contribution['id'], $this->new_contribution['id']);
      } else {
        // remove old ParticipantPayment and create a new one
        $this->updateParticipantPayment($id, $this->contribution['id'], $this->new_contribution['id'], True);
      }

    }
    // remove the original Line Item
    $this->removeOriginalLineItem();
    //    set old transaction to cancelled
    $this->cancelOldContribution();

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view/contribution', "action=view&reset=1&id={$this->new_contribution['id']}&cid={$this->new_contribution['contact_id']}"));
    parent::postProcess();
  }

////////////////////////////////////////////////////////////////////////////////
/// Helper functions internal

  /**
   * Initiliazes internal data, mappings and arrays for form builder
   */
  private function initializeInternalData(){
    // initialize option Arrays
    $this->role2amount              = array();
    $this->role2label               = array();
    $this->paymentInstrument2label  = array();

    // line items
    $this->contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $this->cid));
    $this->registration_id = $this->contribution['trxn_id'];
    $this->line_items = civicrm_api3('LineItem', 'get', array(
      'contribution_id' => $this->cid,
      'sequential'      => 0,
      'options.limit'   => 0))['values'];

    $roles_with_event_fees = CRM_Registration_Configuration::filterNonFeeParticipantRoles();
    foreach ($roles_with_event_fees as $key => $role) {
      $this->role2label[$key]  = $role;
      $this->role2amount[$key] = CRM_Registration_Configuration::getFeeForRole($role);
    }

    // load possible payment Instruments
    $payment_instruments = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'option_group_id' => "payment_instrument",
    ))['values'];

    foreach ($payment_instruments as $payment_instrument) {
      $this->paymentInstrument2label[$payment_instrument['value']] = $payment_instrument['label'];
    }
  }

  /**
   * @param $values
   * @param &$participants2role
   * @param $total
   */
  private function createNewContribution($values, &$participants2role, &$total) {
    for ($i = 1; $i <= count($this->participants); $i++) {
      if (isset($values["participant_id_{$i}"]) && $values["participant_id_{$i}"] != "0") {
        // get ID from text field
        $pattern = "/\[(?P<id>[0-9]+)\]$/";
        $matches = array();
        if (!preg_match($pattern, $values["participant_id_{$i}"], $matches)) {
          continue;
        }
//        if (!isset($values["participant_role_{$i}"]) || $values["participant_amount_{$i}"]) {
//          continue;
//        }
        if (!isset($values["participant_role_{$i}"])) {
          $values["participant_role_{$i}"] = 'Not Attending';
        }
        $participants2role[$matches['id']] = array(
          "fee_level"   => $values["participant_role_{$i}"],
          "fee_amount"  => $values["participant_amount_{$i}"]
        );
        $total += $values["participant_amount_{$i}"];
      }
    }
    $transaction = $this->findTransactionIndex($this->contribution['trxn_id']);
    $transaction_index_counter = 1;
    if (!empty($transaction['index'])) {
      $transaction_index_counter = $transaction['index'] + 1;
    }
    // compile contribution data
    $contribution_data = array(
      'contact_id'             => $this->contribution['contact_id'],
      'trxn_id'                => $transaction['transaction_number'] . "_{$transaction_index_counter}",
      'currency'               => $this->contribution['currency'],
      'total_amount'           => $total,
      'financial_type_id'      => 4, // default (Event Fee)
      'receive_date'           => $this->contribution['receive_date'],
      'is_pay_later'           => $this->contribution['is_pay_later'],
      'payment_instrument_id'  => $values['payment_method'],
      'contribution_status_id' => $values['contribution_status'],
    );
    // and create the contribution
    $this->new_contribution = reset(civicrm_api3('Contribution', 'create', $contribution_data)['values']);
  }

  /**
   * find the current index and original transaction number
   *
   * @param $transactionId
   *
   * @return array (original_trxn => index)
   */
  private function findTransactionIndex($transactionId) {
    $regex_res = "";
    $index = "";
    preg_match("/(?P<original_trxn>[A-Za-z0-9]+-[0-9]+-[0-9]+)(?P<trxn_index>_[0-9]{0,3})?/", $transactionId, $regex_res);
    if (!empty($regex_res['trxn_index'])) {
      $index = explode("_", $regex_res['trxn_index'])[1];
    }
    return array(
      'transaction_number' => $regex_res['original_trxn'],
      'index'              => $index,
    );
  }

  private function getRole($role) {

    if (is_array($role)) {
      foreach ($role as $r) {
        switch ($r) {
          case 'Partner':
            return "Partner";
          case 'Participant':
            return "Participant";
          default:
            continue;
        }
      }
    } else {
      switch ($role) {
        case 'Partner':
          return "Partner";
        case 'Participant':
          return "Participant";
        default:
          continue;
      }
    }
    return "";
  }

  /**
   * @param $participant
   *
   * @return array
   */
  private function getfee_levelRoles_for_participant($participant) {

    $result = array();
    if (is_array($participant['participant_role'])) {
      foreach ($participant['participant_role'] as $role) {
        switch ($role) {
          case 'Partner':
            $result[array_search('Partner', $this->role2label)] = 'Partner';
            return $result;
          case 'Participant':
            foreach ($this->role2label as $key => $label) {
              if ($label != 'Partner') {
                $result[$key] = $label;
              }
            }
            return $result;
          default:
            continue;
        }
      }
    } else {
      switch ($participant['participant_role']) {
        case 'Partner':
          $result[array_search('Partner', $this->role2label)] = 'Partner';
          return $result;
        case 'Participant':
          foreach ($this->role2label as $key => $label) {
            if ($label != 'Partner') {
              $result[$key] = $label;
            }
          }
          return $result;
        default:
          error_log("Invalid Participant role.");
          return NULL;
      }
    }
    error_log("Invalid Role in 'particpant_role' for ID '{$participant['id']}'");
    return NULL;
  }

  /**
   * gets the old participantPayment id
   * creates new participant payment
   * deletes old participant payment
   * @param $participant_id
   * @param $old_contribution_id
   * @param $new_contribution_id
   */
  private function updateParticipantPayment($participant_id, $old_contribution_id, $new_contribution_id, $delete_only = False) {
    $old_participation_payment = civicrm_api3('ParticipantPayment', 'get', array(
      'participant_id' => $participant_id,
      'contribution_id' => $old_contribution_id,
    ));

    if (!$delete_only) {
      $new_participation_payment = civicrm_api3('ParticipantPayment', 'create', [
        'participant_id' => $participant_id,
        'contribution_id' => $new_contribution_id,
      ]);
    }

    if ($old_participation_payment['is_error'] == '0' and !empty($old_contribution_id)) {
      // need to delete Participant Payment manually, otherwise the old contribution is deleted as well,
      // which is not what we want in this case
      $query = "DELETE FROM  `civicrm_participant_payment` WHERE `civicrm_participant_payment`.`contribution_id` = {$old_contribution_id}";
      CRM_Core_DAO::executeQuery($query);
    }
  }

  /**
   * Removes the default Line items which is created when creating the contribution
   */
  private function removeOriginalLineItem() {
    // remove default Line Item
    $original_line_item = civicrm_api3(
      'LineItem',
      'getsingle',
      array(
        'contribution_id' => $this->new_contribution['id'],
        'entity_table' => 'civicrm_contribution',
        'entity_id' => $this->new_contribution['id'])
    );
    civicrm_api3('LineItem', 'delete', array('id' => $original_line_item['id']));
  }

  /**
   * Gather Participant data
   * @param $id
   */
  private function getParticipant($id) {
    $participant = civicrm_api3('Participant', 'getsingle', [
      'id' => $id,
    ]);
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id' => $participant['contact_id'],
    ]);
    $participant['first_name'] = $contact['first_name'];
    $participant['last_name'] = $contact['last_name'];

    return $participant;
  }

  /**
   * @param $fee_amount
   * @param $fee_level
   * @param $id
   */
  private function updateParticipantData($fee_amount, $fee_level, $id) {
    if (empty($fee_amount) && empty($fee_level)) {
      $params = array(
        'fee_level' => 'Not Attending',
        'fee_amount' => CRM_Registration_Configuration::$role_fee_mapping['Not Attending'],
        'id' => $id
      );
    } else {
      // FixMe:
      // This is a fix. If a Role is not available (Participant changed to Not Attending
      // and Participant Role is != participant/Partner, the dropdown for the role is empty, unset
      // For now we manually add 'Not Attending'
      if (!isset($this->role2label[$fee_level])) {
        $level = 'Not Attending';
      } else {
        $level = $this->role2label[$fee_level];
      }
      $params = array(
        'fee_level' => $level,
        'fee_amount' => $fee_amount,
        'id' => $id,
      );
    }

    $result = civicrm_api3('Participant', 'create', $params);
    if ($result['is_error'] != '0') {
      error_log("Couldn't properly update Participant with id {$id} and fee_amount: {$fee_amount} and {$fee_level}" );
    }
  }

  /**
   * Cancel the old contribution
   */
  private function cancelOldContribution() {
    $arguments = array(
      'financial_type_id'       => "4",
      'id'                      => $this->contribution['id'],
      'contribution_status_id'  => "3",
      'currency'                => $this->contribution['currency'],
      'cancel_date'             => date('YmdHis', strtotime("now")),
      'cancel_reason'           => "manually edited by coop.ica.register extension",
      ''
    );
    $result = civicrm_api3('Contribution', 'create', $arguments);
  }

}
