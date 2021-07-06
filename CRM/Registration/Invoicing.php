<?php
/*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2021 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


class CRM_Registration_Invoicing {

  /** @var string general invoicing prefix    @todo: move to setting? */
  const EVENT_FEE_INVOICE_PREFIX = 'IN-GA2021-';

  /**
   * Implements the adjustInvoice (custom) hook
   *
   * @param array $tplParams
   *   data to be passed to the invoice template
   *
   * @param CRM_Contribute_BAO_Contribution $contributionBAO
   *   the contribution to be invoiced
   */
  public static function adjustInvoice(&$tplParams, $contributionBAO)
  {
    // DETERMINE the invoice_date: (see ICA-5075)
    //  1) load custom field for $contributionBAO->id
    $custom_field = CRM_Registration_CustomData::getCustomField('contribution_extra', 'invoice_date');
    $custom_field_key = 'custom_' . $custom_field['id'];
    $current_value_query = civicrm_api3('CustomValue', 'get', array(
        'entity_type'                => 'Contribution',
        'entity_id'                  => $contributionBAO->id,
        "return.{$custom_field_key}" => 1));
    $current_value = reset($current_value_query['values']);
    $invoice_date = $current_value['latest'];
    if (empty($invoice_date)) {
      // 2) if empty set to NOW
      $invoice_date = date('YmdHis');
      // Store if contribution not 'Pending' or 'in Progress'
      if ($contributionBAO->contribution_status_id != 2 && $contributionBAO->contribution_status_id != 5) {
        civicrm_api3('CustomValue', 'create', array(
            'entity_type'     => 'Contribution',
            'entity_id'       => $contributionBAO->id,
            $custom_field_key => $invoice_date));
      }
    }
    // 3) pass to template (hardcoded date format copied from core code)
    $tplParams['invoice_date'] = date('F j, Y', strtotime($invoice_date));
    if (isset($contributionBAO->cancel_date)) {
      $tplParams['cancel_date']  = date('F j, Y', strtotime($contributionBAO->cancel_date));
    }

    // 4) load and overwrite line items (see ICA-5311)
    $subTotal = 0.0;
    $line_items = civicrm_api3('LineItem', 'get', array(
        'contribution_id' => $contributionBAO->id,
        'sequential'      => 0,
        'option.limit'    => 0))['values'];
    foreach ($line_items as &$line_item) {
      $subTotal += $line_item['line_total'];
      $line_item['subTotal'] = $line_item['line_total'];
      $line_item['qty']      = (int) $line_item['qty'];
    }
    $tplParams['lineItem'] = $line_items;
    $tplParams['subTotal'] = $subTotal;

    // get/generate invoice ID
    if (empty($contributionBAO->invoice_id)) {
      $invoice_id = self::generateNewInvoiceNumber($contributionBAO->id);
      $tplParams['invoice_id'] = $invoice_id;
      $tplParams['invoice_number'] = $invoice_id;
    }

    // 5) FIND and add billing address
    $billing_address = CRM_Registration_Processor::getBillingAddress($contributionBAO->contact_id);
    if ($billing_address) {
      // look up some stuff
      if (!empty($billing_address['state_province_id'])) {
        $billing_address['stateProvinceAbbreviation'] = CRM_Core_PseudoConstant::stateProvinceAbbreviation($billing_address['state_province_id']);
      }
      if (!empty($billing_address['country_id'])) {
        $billing_address['country'] = CRM_Core_PseudoConstant::country($billing_address['country_id']);
      }

      $tplParams['street_address']            = CRM_Utils_Array::value('street_address', $billing_address, '');
      $tplParams['supplemental_address_1']    = CRM_Utils_Array::value('supplemental_address_1', $billing_address, '');
      $tplParams['supplemental_address_2']    = CRM_Utils_Array::value('supplemental_address_2', $billing_address, '');
      $tplParams['city']                      = CRM_Utils_Array::value('city', $billing_address, '');
      $tplParams['postal_code']               = CRM_Utils_Array::value('postal_code', $billing_address, '');
      $tplParams['stateProvinceAbbreviation'] = CRM_Utils_Array::value('stateProvinceAbbreviation', $billing_address, '');
      $tplParams['postal_code']               = CRM_Utils_Array::value('postal_code', $billing_address, '');
      $tplParams['country']                   = CRM_Utils_Array::value('country', $billing_address, '');
    }
  }

  /**
   * Generate a new ICA invoice number and write to DB
   *
   * @param integer $contribution_id
   *  the contribution ID
   *
   * @return string
   *   the new
   */
  public static function generateNewInvoiceNumber($contribution_id)
  {
    $prefix = self::EVENT_FEE_INVOICE_PREFIX;
    $counter_position = strlen($prefix) + 1;
    $last_id = CRM_Core_DAO::singleValueQuery("
      SELECT MAX(CAST(SUBSTRING(`invoice_id` FROM {$counter_position}) AS UNSIGNED))
      FROM `civicrm_contribution`
      WHERE `invoice_id` REGEXP '{$prefix}[0-9]+$';");
    if ($last_id) {
      $invoice_id = $prefix . ($last_id + 1);
    } else {
      $invoice_id = "{$prefix}1";
    }

    // update invoice_id on contribution, since it isn't stored stored automatically
    // store to invoice_id and invoice_number
    civicrm_api3('Contribution', 'create', array(
        'id'             => $contribution_id,
        'invoice_id'     => $invoice_id,
        'invoice_number' => $invoice_id,
    ));

    return $invoice_id;
  }
}
