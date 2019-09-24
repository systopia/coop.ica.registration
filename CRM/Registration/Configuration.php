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


class CRM_Registration_Configuration {

  public static $setting_fields = ['default_event', 'registration_prefix', 'confirmation_sender', 'confirmation_bcc'];

  protected static $_settings = NULL;

  /**
   * @var static var for role => fee mapping.
   * Additional values can be added here if needed
   */
  static $role_fee_mapping = array(
    'International Member'  => 650.00,
    'Partner'               => 250.00,
    'Youth'                 => 250.00,
    'Participant'           => 850.00,
    'Africa participant'    => 250.00,
    'Interpreter'           => 250.00,
    'Not Attending'         => 0.00,
  );

  /**
   * @var static array with the available roles
   * Additional values can be added here if needed
   */
  static $roles = array(
    0 => 'International Member',
    1 => 'Partner',
    2 => 'Youth',
    3 => 'Participant',
    4 => 'Africa participant'
  );

  /**
   * Get a sigle setting
   *
   * @param $key     string setting name
   * @param $default string setting default value
   * @return mixed value
   */
  public static function getSetting($key, $default = NULL) {
    $settings = self::getSettings();
    return CRM_Utils_Array::value($key, $settings, $default);
  }

  /**
   * Get the Registration settings
   *
   * @return array settings
   */
  public static function getSettings() {
    if (self::$_settings === NULL) {
      $settings = CRM_Core_BAO_Setting::getItem('coop.ica.registration', 'ica_registration');
      if (empty($settings) || !is_array($settings)) {
        self::$_settings = [];
      } else {
        self::$_settings = $settings;
      }
    }
    return self::$_settings;
  }

  /**
   * Update the Registration settings
   *
   * @param  array settings
   */
  public static function setSettings($settings) {
    // restrict to allowed values
    $filtered_settings = [];
    foreach (self::$setting_fields as $field) {
      if (array_key_exists($field, $settings)) {
        $filtered_settings[$field] = $settings[$field];
      }
    }

    CRM_Core_BAO_Setting::setItem($filtered_settings, 'coop.ica.registration', 'ica_registration');
    self::$_settings = $settings;
  }

  /**
   * @param $role_label
   *
   * @return null
   */
  public static function getFeeForRole($role_label) {
    if (empty(self::$role_fee_mapping[$role_label])) {
      error_log("Given role ('{$role_label}') is invalid. ");
      return NULL;
    }
    return self::$role_fee_mapping[$role_label];
  }

  /**
   * @param $fee
   *
   * @return false|int|string
   */
  public static function getRoleFromFee($fee) {
    return array_search($fee, self::$role_fee_mapping);
  }

  /**
   * Statically returns an array with the roles
   * @return array
   */
  public static function filterNonFeeParticipantRoles() {
    return self::$roles;
  }

  /**
   * Get a list of badge states that are considered to be ready-to-print
   * @return array
   */
  public static function getPrintableBadgeStates() {
    return ['1'];
  }

  /**
   * Parse civi API value array and filter out specific contribution stati
   * returns an array with contribution_status (optionValue) => label
   */
  public static function filterContributionStati($contributions_stati, $contributionStatus) {
    $contribution_status_labels = array(
      'Completed',
      'Pending',
    );
    if ($contributionStatus == 'Cancelled') {
      // we need to add canceled here as well!
      $contribution_status_labels[] = 'Cancelled';
    }
    $result = array();
    foreach ($contributions_stati as $contribution_status) {
      if (in_array($contribution_status['label'], $contribution_status_labels)) {
        $result[$contribution_status['value']] = $contribution_status['label'];
      }
    }
    return $result;
  }
}
