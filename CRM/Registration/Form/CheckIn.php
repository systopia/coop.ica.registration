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


use CRM_Registration_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Registration_Form_CheckIn extends CRM_Core_Form {
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts("Event Check-In Desk"));

    // search form elements
    $this->add(
      'select',
      'event_id',
      E::ts('Event'),
      $this->getEvents(),
      TRUE
    );
    $this->add(
        'text',
        'registration_id',
        E::ts('Registration ID'),
        ['size' => 16],
        FALSE
    );
    $this->add(
        'select',
        'country_id',
        E::ts('Country'),
        $this->getCountries(),
        FALSE
    );
    $this->add(
        'text',
        'participant_name',
        E::ts('Participant Name'),
        ['size' => 32],
        FALSE
    );
    $this->add(
        'text',
        'badge_name',
        E::ts('Badge Name'),
        ['size' => 32],
        FALSE
    );
    $this->add(
        'text',
        'organisation_name',
        E::ts('Organisation Name'),
        ['size' => 32],
        FALSE
    );
    $this->add(
        'text',
        'registered_with',
        E::ts('Registered With'),
        ['size' => 32],
        FALSE
    );

    // TODO: set defaults event_id

    // find/assign participants
    $this->participants = $this->findParticipants();
    $this->assign('participants', $this->participants);

    $this->addButtons(array(
      array(
          'type'      => 'submit',
          'name'      => E::ts('Find'),
          'isDefault' => TRUE,
      ),
    ));
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    // TODO: export all

    parent::postProcess();
  }

  /**
   * Get the list of countries in the custom country column
   *
   * @return array ID => name
   */
  public function getCountries() {
    return [];
  }

  /**
   * Get the list of events in the custom country column
   *
   * @return array ID => title
   */
  public function getEvents() {
    $event_search = civicrm_api3('Event', 'get', [
        'is_template'  => 0,
        'option.limit' => 0,
        'option.sort'  => 'id desc',
        'return'       => 'id,title',
    ]);
    $event_list = [];
    foreach ($event_search['values'] as $event) {
      $event_list[$event['id']] = $event['title'];
    }
    return $event_list;
  }

  /**
   * Run an SQL query to find the participants matching this form's values
   *
   * @return array list of participant data array
   */
  public function findParticipants() {
    $participants  = [];

    // get fields an tables
    $registration_data = CRM_Registration_CustomData::getGroupSpecs('GA_Registration');
    $REGISTRATION_JOIN = CRM_Registration_CustomData::createSQLJoin('GA_Registration', 'registration', 'participant.id');
    $SELECT_BADGE_TYPE = CRM_Registration_CustomData::createSQLSelect('GA_Registration', 'badge_type', 'registration', 'badge_type');

    // build query
    $query_parameters = [];
    $criteria = $this->exportValues();
    $where_clauses = [];
    if (!empty($criteria['event_id'])) {
      $i = count($query_parameters);
      $where_clauses[] = "participant.event_id = %{$i}";
      $query_parameters[$i] = [$criteria['event_id'], 'Integer'];
    }
    if (!empty($criteria['registration_id'])) {
      $i = count($query_parameters);
      $registration_id_field = CRM_Registration_CustomData::getCustomField('GA_Registration', 'registration_id');
      $where_clauses[] = "`registration`.`{$registration_id_field['column_name']}` LIKE CONCAT('%', %{$i}, '%')";
      $query_parameters[$i] = [$criteria['registration_id'], 'String'];
    }

    // avoid empty query
    if (count($where_clauses) < 2) {
      return $participants;
    }

    // run query
    $WHERE_CLAUSE = '(' . implode(') AND (', $where_clauses) . ')';
    Civi::log()->debug("
      SELECT
          contact.sort_name    AS contact_sort_name,
          status.label         AS participant_status,
          $SELECT_BADGE_TYPE
      FROM civicrm_participant participant
      LEFT JOIN civicrm_contact contact                ON participant.contact_id = contact.id 
      LEFT JOIN civicrm_participant_status_type status ON status.id = participant.status_id
      {$REGISTRATION_JOIN} 
      WHERE {$WHERE_CLAUSE}
      ORDER BY contact.sort_name DESC");
    $query = CRM_Core_DAO::executeQuery("
      SELECT
          contact.sort_name    AS contact_sort_name,
          status.label         AS participant_status,
          $SELECT_BADGE_TYPE
      FROM civicrm_participant participant
      LEFT JOIN civicrm_contact contact                ON participant.contact_id = contact.id 
      LEFT JOIN civicrm_participant_status_type status ON status.id = participant.status_id
      {$REGISTRATION_JOIN} 
      WHERE {$WHERE_CLAUSE}
      ORDER BY contact.sort_name DESC", $query_parameters);

    while ($query->fetch()) {
      $participants[] = [
          'sort_name'  => $query->contact_sort_name,
          'status'     => $query->status,
          'badge_type' => $query->badge_type,
      ];
    }
    return $participants;
  }
}
