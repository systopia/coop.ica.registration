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

define('MAX_LINE_COUNT', 100);

/**
 * TODO DOKU
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Registration_Form_RegistrationPaymentEdit extends CRM_Core_Form {


  protected $cid               = NULL;
  protected $contribution      = NULL;
  protected $registration_id   = NULL;
  protected $line_items        = NULL;
  protected $role2label        = NULL;
  protected $role2amount       = NULL;
  protected $participants      = NULL;
  protected $participant2label = NULL;

  /**
   * Create form
   */
  public function buildQuickForm() {
    // load contribution + line items
    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (!$this->cid) {
      // TODO: errpr
    }

    $this->contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $this->cid));
    $this->registration_id = $this->contribution['trxn_id'];
    $this->line_items = civicrm_api3('LineItem', 'get', array(
      'contribution_id' => $this->cid,
      'sequential'      => 0,
      'options.limit'   => 0))['values'];


    // load possible roles
    $role_query = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'participant_role',
      'options.limit'   => 0,
      'sequential'      => 1,
      ));
    $this->role2amount = array();
    $this->role2label  = array();
    foreach ($role_query['values'] as $role) {
      $this->role2label[$role['value']]  = $role['label'];
      $this->role2amount[$role['value']] = CRM_Registration_Configuration::getFeeForRole($role['value']);
    }
    $this->role2amount[count($this->role2amount) + 1] = 0;
    $this->role2label[count($this->role2label) + 1] = "Not Participating anymore";

    // load participants
    $this->populateParticipants();

    // generate lines
    $this->assign('line_numbers',   range(1, MAX_LINE_COUNT));
    $this->assign('max_line_count', MAX_LINE_COUNT);
    for ($i=1; $i <= MAX_LINE_COUNT; $i++) {
      $this->add('select',
        "participant_id_{$i}",
        'Participant',
        $this->participant2label,
        FALSE,
        array('class' => 'participant-id')
      );

      $this->add('select',
        "participant_role_{$i}",
        '(new) role',
        $this->role2label,
        FALSE,
        array('class' => 'participant-role')
      );

      $this->add('text',
        "participant_amount_{$i}",
        'amount'
      );

    }

    // TODO: one contribution (sum) line
    $this->add('static',
      "contribution_sum_description",
      'contribution_sum_description',
      "<b>Contribution Sum:</b>"
    );
    $this->add('text',
      "contribution_sum",
      'accumulated_amount'
    );


    $this->assign('role2amount', json_encode($this->role2amount));
    // FIXE: test code!
    $this->assign('line_count', count($this->participants));


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

    $participant_selector = CRM_Core_DAO::executeQuery("
      SELECT entity_id AS participant_id
      FROM {$registration_group_table} WHERE {$registration_id_column} = '{$this->registration_id}'");
    $participant_ids = array();
    while ($participant_selector->fetch()) {
      $participant_ids[] = $participant_selector->participant_id;
    }

    if (!empty($participant_ids)) {
      $this->participants = civicrm_api3('Participant', 'get', array(
      'id'            => array('IN' => $participant_ids),
      'options.limit' => 0))['values'];
    }
    foreach ($this->participants as $particpant) {
      $this->participant2label[$particpant['id']] = "{$particpant['display_name']} ({$particpant['participant_fee_level']})";
    }
  }


  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    $values = array();

    // FIXME: example code
    // use $this->participants
    for ($i=1; $i <= MAX_LINE_COUNT; $i++) {
      $values["participant_role_{$i}"] = $i;
    }

    return $values;
  }

  // validate()



  public function postProcess() {
    $values = $this->exportValues();
    // $options = $this->getColorOptions();
    // CRM_Core_Session::setStatus(ts('You picked color "%1"', array(
    //   1 => $options[$values['favorite_color']],
    // )));
    parent::postProcess();
  }

}
