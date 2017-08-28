<?php
/*-------------------------------------------------------+
| ICA Registration Extension                             |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| Author: P. Batroff (batroff@systopia.de)               |
| http://www.systopia.de/                                |
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
 * Minor Changes to the UI
 */
class CRM_Registration_Gui_changes {

  public static function buildForm_hook_participant_view($formName, &$form) {
    $participant_id = CRM_Utils_Request::retrieve('id', 'Integer');
    $participant = civicrm_api3('Participant', 'getsingle', array(
      'sequential' => 1,
      'id' => $participant_id,
    ));
    $script = file_get_contents(__DIR__ . '/../../js/gui_changes_participant_view.js');
    $script = str_replace('__FEE-LEVEL__', $participant['participant_fee_level'], $script);
    $script = str_replace('__FEE-AMOUNT__', $participant['participant_fee_amount'], $script);
    CRM_Core_Region::instance('page-footer')->add(array(
      'script' => $script,
    ));
  }

  public static function buildForm_hook_participant($formName, &$form) {
    $script = file_get_contents(__DIR__ . '/../../js/gui_changes_participant.js');
    CRM_Core_Region::instance('page-footer')->add(array(
      'script' => $script,
    ));
  }

}
