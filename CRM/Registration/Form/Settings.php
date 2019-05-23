<?php
/*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2019 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

// old values:
//define('ICA_EVENT_SUBMISSION_PREFIX',   'GA2019');
//define('ICA_EVENT_CONFIRMATION_SENDER', '"International Co-operative Alliance" <secretariat.malaysia2017@ica.coop>');
//define('ICA_EVENT_CONFIRMATION_BCC',    'secretariat.malaysia2017@ica.coop');

use CRM_Registration_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Registration_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {
    // add form elements
    $this->add(
        'select',
        'default_event',
        E::ts('Default Event'),
        $this->getEventList(),
        ['class' => 'crm-select2'],
        TRUE
    );

    $this->add(
      'text',
      'registration_prefix',
      E::ts('Registration Prefix'),
      [],
      TRUE
    );

    $this->add(
        'text',
        'confirmation_sender',
        E::ts('Sender (Confirmation)'),
        ['class' => 'huge'],
        TRUE
    );

    $this->add(
        'text',
        'confirmation_bcc',
        E::ts('BCC (Confirmation)'),
        ['class' => 'huge'],
        TRUE
    );


    // set the defaults
    $this->setDefaults(CRM_Registration_Configuration::getSettings());

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    // clean up values
    unset($values['qfKey'], $values['entryURL'], $values['_qf_default'], $values['_qf_Settings_submit']);
    $values['confirmation_sender'] = decode_entities($values['confirmation_sender']);

    //CRM_Core_Error::debug_log_message("Settings: " . json_encode($values));
    CRM_Registration_Configuration::setSettings($values);
    CRM_Core_Session::setStatus(E::ts('Settings stored'));

    parent::postProcess();
  }

  /**
   * Get a list of currently active events
   */
  protected function getEventList() {
    $event_list = [];
    $event_query = civicrm_api3('Event', 'get', [
        'is_active'    => '1',
        'return'       => 'title,id',
        'option.limit' => 0]);
    foreach ($event_query['values'] as $event) {
      $event_list[$event['id']] = $event['title'];
    }

    return $event_list;
  }

}
