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

class CRM_Registration_Page_PrintBadge extends CRM_Core_Page {

  /**
   * Use XPortX to generate the badge PDFs
   * @param $participant_ids array   comma-separated list of IDs
   * @param $register        boolean should the participant be registered (status changed to "Attended")
   */
  public static function printBadges($participant_ids, $register) {
    if (empty($participant_ids)) {
      throw new Exception(E::ts("No participant IDs passed."));
    }

    // register participants
    if (!empty($register)) {
      foreach ($participant_ids as $participant_id) {
        civicrm_api3('Participant', 'create', [
            'id'                    => $participant_id,
            'participant_status_id' => 'Attended'
        ]);
      }
    }

    $badge_export_config = CRM_Registration_Configuration::getBadgeExporterConfig();
    if ($badge_export_config) {
      $export = new CRM_Xportx_Export($badge_export_config);
      $export->writeToStream($participant_ids);
    } else {
      throw new Exception(E::ts("XPortX not installed, or no export configuration present"));
    }
  }

  public function run() {
    $filtered_participant_ids = [];
    $register_participants = CRM_Utils_Request::retrieve('register', 'String', $this, NULL, FALSE);
    $raw_participant_ids = CRM_Utils_Request::retrieve('ids', 'String');
    $raw_participant_id_list = explode(',', $raw_participant_ids);
    foreach ($raw_participant_id_list as $raw_participant_id) {
      $participant_id = (int) $raw_participant_id;
      if ($participant_id) {
        $filtered_participant_ids[] = $participant_id;
      }
    }

    self::printBadges($filtered_participant_ids, $register_participants);
  }
}
