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
  const SEARCH_LIMIT = 100;

  /** @var string used for action mapping */
  protected $command = NULL;

  /** @var array stores the search result */
  protected $participants = NULL;

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
        FALSE,
        ['class' => 'crm-select2']
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
    $this->add(
        'select',
        'badge_status',
        E::ts('Badge Status'),
        $this->getBadgeStatusList(),
        FALSE,
        ['class' => 'crm-select2']
    );

    // set defaults: event_id
    $defaults = Civi::settings()->get('ica_registration_checkin_defaults');
    if (is_array($defaults)) {
      $this->setDefaults($defaults);
    }

    // find/assign participants
    $this->participants = $this->findParticipants();
    $this->assign('participants', $this->participants);
    if (count($this->participants) >= self::SEARCH_LIMIT) {
      CRM_Core_Session::setStatus(E::ts("Search limit of %1 exceeded, not all participants matching the criteria listed!", [1 => self::SEARCH_LIMIT]), E::ts("Warning: Search Limit"), 'warn');
    }

    // add buttons
    $this->addButtons([
        [
            'type'      => 'find',
            'name'      => E::ts('Find'),
            'isDefault' => TRUE,
            'icon'      => 'fa-search',
        ],
        [
            'type'      => 'printall',
            'name'      => E::ts('Preview All'),
            'isDefault' => TRUE,
            'icon'      => 'fa-print',
        ],
        [
            'type'      => 'registerall',
            'name'      => E::ts('Register All'),
            'isDefault' => TRUE,
            'icon'      => 'fa-check',
        ],
        [
            'type'      => 'clear',
            'name'      => E::ts('Reset'),
            'isDefault' => TRUE,
            'icon'      => 'fa-trash-o',
        ],
    ]);
    parent::buildQuickForm();
  }

  /**
   * Redirect all (custom) actions ('find', 'all_print', and 'reset')
   * to submit
   */
  public function handle($command) {
    switch ($command) {
      case 'find':
      case 'printall':
      case 'registerall':
      case 'clear':
      case 'submit':
        $this->command = $command;
        $command = 'submit';
        break;
      default:
        break;
    }
    parent::handle($command);
  }

  public function postProcess() {
    $values = $this->exportValues();

    // first: store defaults
    $defaults = [
        'event_id' => CRM_Utils_Array::value('event_id', $values, '')
    ];
    Civi::settings()->set('ica_registration_checkin_defaults', $defaults);

    switch ($this->command) {
      case 'clear':
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/participant/checkin', 'reset=1'));
        break;

      case 'printall':
      case 'registerall':
        if (empty($this->participants)) {
          CRM_Core_Session::setStatus(E::ts("No participants found."), E::ts("Nothing to do"), 'info');
        } else {
          $participant_ids = [];
          foreach ($this->participants as $participant) {
            if (CRM_Registration_Configuration::canBeRegistered($participant['participant_id'], $participant['badge_status_id'], $participant['status_name'])) {
              $participant_ids[] = $participant['participant_id'];
            }
          }
          if (empty($participant_ids)) {
            CRM_Core_Session::setStatus(E::ts("No eligible participants found."), E::ts("Nobody to register"), 'info');
          } else {
            CRM_Registration_Page_PrintBadge::printBadges($participant_ids, ($this->command == 'registerall'));
          }
        }

      default:
      case 'find':
        // nothing to do here, really. find will run automatically
    }

    parent::postProcess();
  }

  /**
   * Get the list of countries in the custom country column
   *
   * @return array ID => name
   */
  public function getCountries() {
    return ['' => E::ts("- any -")] + CRM_Core_PseudoConstant::country();
  }

  /**
   * Get all badge statuses
   */
  public function getBadgeStatusList() {
    $status_list = ['' => E::ts("- any -")];
    $status_search = civicrm_api3('OptionValue', 'get', [
        'option.limit'    => 0,
        'return'          => 'value,label',
        'option_group_id' => 'badge_status',
        'is_active'       => 1
    ]);
    foreach ($status_search['values'] as $status) {
      $status_list[$status['value']] = $status['label'];
      $status_list[$status['value']] = $status['label'];
    }
    return $status_list;
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
    CRM_Registration_CustomData::cacheCustomGroups(['Location_and_Language', 'GA_Registration']);
    $registration_data = CRM_Registration_CustomData::getGroupSpecs('GA_Registration');
    $REGISTRATION_JOIN = CRM_Registration_CustomData::createSQLJoin('GA_Registration', 'registration', 'participant.id');
    $LOCATION_JOIN     = CRM_Registration_CustomData::createSQLJoin('Location_and_Language', 'location_and_language', 'participant.contact_id');
    $REGISTRATION_ORG  = CRM_Registration_CustomData::getCustomField('GA_Registration', 'registered_organisation');
    $MAIN_CONTACT      = CRM_Registration_CustomData::getCustomField('GA_Registration', 'main_contact');
    $BADGE_TYPE        = CRM_Registration_CustomData::getCustomField('GA_Registration', 'badge_type');
    $BADGE_COLOR       = CRM_Registration_CustomData::getCustomField('GA_Registration', 'badge_color');
    $BADGE_STATUS      = CRM_Registration_CustomData::getCustomField('GA_Registration', 'badge_status');
    $SLCT_BADGE_STATUS = CRM_Registration_CustomData::createSQLSelect('GA_Registration', 'badge_status', 'registration', 'badge_status_id');

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
    if (!empty($criteria['participant_name'])) {
      $i = count($query_parameters);
      $badge_field = CRM_Registration_CustomData::getCustomField('GA_Registration', 'badge');
      $where_clauses[] = "registration.{$badge_field['column_name']} LIKE CONCAT('%', %{$i}, '%')";
      $query_parameters[$i] = [$criteria['participant_name'], 'String'];
    }
    if (!empty($criteria['organisation_name'])) {
      $i = count($query_parameters);
      $badge_field = CRM_Registration_CustomData::getCustomField('GA_Registration', 'organisation_badge');
      $where_clauses[] = "registration.{$badge_field['column_name']} LIKE CONCAT('%', %{$i}, '%')";
      $query_parameters[$i] = [$criteria['organisation_name'], 'String'];
    }
    // disabled: search in contact/organisation display name
    //    if (!empty($criteria['participant_name'])) {
    //      $i = count($query_parameters);
    //      $where_clauses[] = "`contact`.`display_name` LIKE CONCAT('%', %{$i}, '%')";
    //      $query_parameters[$i] = [$criteria['participant_name'], 'String'];
    //    }
    //    if (!empty($criteria['organisation_name'])) {
    //      $i = count($query_parameters);
    //      $where_clauses[] = "`organisation`.`display_name` LIKE CONCAT('%', %{$i}, '%')";
    //      $query_parameters[$i] = [$criteria['organisation_name'], 'String'];
    //    }
    if (!empty($criteria['registered_with'])) {
      $i = count($query_parameters);
      $where_clauses[] = "`main_contact`.`display_name` LIKE CONCAT('%', %{$i}, '%')";
      $query_parameters[$i] = [$criteria['registered_with'], 'String'];
    }
    if (!empty($criteria['country_id'])) {
      $i = count($query_parameters);
      $country_field = CRM_Registration_CustomData::getCustomField('Location_and_Language', 'Country');
      $where_clauses[] = "`location_and_language`.`{$country_field['column_name']}` = %{$i}";
      $query_parameters[$i] = [$criteria['country_id'], 'Integer'];
    }
    if (!empty($criteria['badge_status'])) {
      $i = count($query_parameters);
      $where_clauses[] = "`registration`.`{$BADGE_STATUS['column_name']}` = %{$i}";
      $query_parameters[$i] = [$criteria['badge_status'], 'String'];
    }

    // avoid empty query
    if (count($where_clauses) < 2) {
      return $participants;
    }

    // run query
    $WHERE_CLAUSE = '(' . implode(') AND (', $where_clauses) . ')';
    $sql_query = "
      SELECT
          participant.id       AS participant_id,
          contact.id           AS contact_id,
          contact.contact_type AS contact_type,
          contact.sort_name    AS contact_sort_name,
          status.label         AS participant_status,
          status.name          AS participant_status_name,
          badge_type.label     AS badge_type,
          badge_color.label    AS badge_color,
          badge_status.label   AS badge_status,
          {$SLCT_BADGE_STATUS}
      FROM civicrm_participant participant
      {$REGISTRATION_JOIN}
      {$LOCATION_JOIN} 
      LEFT JOIN civicrm_contact contact                ON contact.id = participant.contact_id 
      LEFT JOIN civicrm_contact organisation           ON organisation.id = registration.`{$REGISTRATION_ORG['column_name']}` 
      LEFT JOIN civicrm_contact main_contact           ON organisation.id = registration.`{$MAIN_CONTACT['column_name']}` 
      LEFT JOIN civicrm_participant_status_type status ON status.id = participant.status_id
      LEFT JOIN civicrm_option_value badge_type        ON badge_type.value = registration.{$BADGE_TYPE['column_name']} AND badge_type.option_group_id = {$BADGE_TYPE['option_group_id']}
      LEFT JOIN civicrm_option_value badge_color       ON badge_color.value = registration.{$BADGE_COLOR['column_name']} AND badge_color.option_group_id = {$BADGE_COLOR['option_group_id']}
      LEFT JOIN civicrm_option_value badge_status      ON badge_status.value = registration.{$BADGE_STATUS['column_name']} AND badge_status.option_group_id = {$BADGE_STATUS['option_group_id']}
      WHERE {$WHERE_CLAUSE}
      GROUP BY participant.id
      ORDER BY contact.sort_name DESC
      LIMIT " . self::SEARCH_LIMIT;
    //Civi::log()->debug($sql_query);
    $query = CRM_Core_DAO::executeQuery($sql_query, $query_parameters);

    while ($query->fetch()) {
      $participants[] = [
          'participant_id'  => $query->participant_id,
          'contact_id'      => $query->contact_id,
          'sort_name'       => $query->contact_sort_name,
          'status'          => $query->participant_status,
          'status_name'     => $query->participant_status_name,
          'badge_type'      => $query->badge_type,
          'badge_color'     => $query->badge_color,
          'badge_status'    => $query->badge_status,
          'badge_status_id' => $query->badge_status_id,
          'links'           => $this->generateActionLinks($query)
      ];
    }
    return $participants;
  }

  /**
   * Generate the actions links for the participant data
   *
   * @param $data CRM_Core_DAO search result
   * @return array links to display in the table
   */
  protected function generateActionLinks($participant) {
    $links = [];

    // add edit link
    $url = CRM_Utils_System::url('civicrm/contact/view/participant', "reset=1&action=update&id={$participant->participant_id}&cid={$participant->contact_id}");
    $links[] = "<a href=\"{$url}\" class=\"action-item crm-hover-button crm-popup\" title=\"Edit Participant\">Edit</a>";

    // add preview link
    $url = CRM_Utils_System::url('civicrm/participant/printbadge', "ids={$participant->participant_id}");
    $links[] = "<a href=\"{$url}\" class=\"action-item crm-hover-button\" title=\"Preview Badge\">Preview</a>";

    // add print link
    if (CRM_Registration_Configuration::canBeRegistered($participant->participant_id, $participant->badge_status_id, $participant->participant_status_name)) {
      $url = CRM_Utils_System::url('civicrm/participant/printbadge', "register=1&ids={$participant->participant_id}");
      $links[] = "<a href=\"{$url}\" class=\"action-item crm-hover-button\" title=\"Print Badge\">Register</a>";
    }

    return $links;
  }
}
