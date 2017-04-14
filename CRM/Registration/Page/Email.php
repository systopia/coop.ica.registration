<?php
/*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2016 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'CRM/Core/Page.php';

class CRM_Registration_Page_Email extends CRM_Core_Page {

  public function run() {
    // first: verify that the contribution is eligible
    $result = self::verifyContribution(CRM_Utils_Array::value('cid', $_REQUEST));
    if (is_string($result)) {
      // this is an error:
      $this->assign('error', $result);
    } else {
      // the contribution is valid, call the registration processor
      try {
        $contribution = $result;
        $sent_to = array();
        $processor = new CRM_Registration_Processor($params);
        $processor->sendPaymentInvoice($contribution, $sent_to);
        $this->assign('sent_to', $sent_to);
      } catch (Exception $e) {
        // an error after all? ok...
        $this->assign('error', $e->getMessage());
      }
    }

    parent::run();
  }


  /**
   * Check if the following is true for the given contribtuion ID
   *  - contribution exists
   *  - contribution is GA payment
   *  - contribution is in status completed
   *
   * @return contribution data as array, or an error message as a string
   */
  public static function verifyContribution($contribution_id) {
    try {
      // check if parameter is a real ID
      if (empty($contribution_id) || !is_numeric($contribution_id)) {
        return ts("Invalid contribution ID '{$contribution_id}'");
      }

      // check if contribution exists
      try {
        $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
      } catch (Exception $e) {
        return ts("Couldn't load contribution [{$contribution_id}]");
      }

      // check if trxn_id is set and starts with 'GA'
      if (empty($contribution['trxn_id']) || substr($contribution['trxn_id'], 0, 2) != 'GA') {
        return ts("Contribution [{$contribution_id}] is not an event registration.");
      }

      // check if trxn_id is set
      if ($contribution['financial_type_id'] != 4) {
        return ts("Contribution [{$contribution_id}] is not of type event fee.");
      }

      // check if in status completed
      if (!in_array($contribution['contribution_status_id'], array(1,2))) {
        return ts("Contribution [{$contribution_id}] is not marked as completed/pending.");
      }

      // all good: return contribution data
      return $contribution;

    } catch (Exception $e) {
      // fallback: return exception message
      return $e->getMessage();
    }
  }
}
