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

  public static function getFeeForRole($role_label) {
    $role_array = array(
      'International Member'  => 750.00,
      'Partner'               => 100.00,
      'Youth'                 => 200.00,
      'Participant'           => 950.00,
      'Not Attending'         => 0.00,
    );
    return $role_array[$role_label];
  }

  /**
   * Parse query array from civi api and filter out non fee Participant roles.
   * returns array
   */
  public static function filterNonFeeParticipantRoles($roles) {
    $role_array = array(
      'International Member',
      'Partner',
      'Youth',
      'Participant',
      'Not Attending',
    );
    $result = array();
    foreach ($roles as $role) {
      if (in_array($role['label'], $role_array)) {
        $result[] = $role;
      }
    }
    error_log("Returning reulst: " . json_encode($result));
    return $result;
  }

}
