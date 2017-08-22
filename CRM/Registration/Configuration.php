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

  /**
   * @var static var for role => fee mapping.
   * Additional values can be added here if needed
   */
  static $role_fee_mapping = array(
    'International Member'  => 750.00,
    'Partner'               => 100.00,
    'Youth'                 => 200.00,
    'Participant'           => 950.00,
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
  );

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
