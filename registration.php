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

require_once 'registration.civix.php';

define('EVENT_FEE_INVOICE_PREFIX', 'IN-GA2017-');

/**
 * Implements hook_civicrm_invoiceNumber().
 */
function registration_civicrm_invoiceNumber(&$invoice_id, $contributionBAO) {
  // only interfere with event fee contributions
  if ($contributionBAO->financial_type_id != 4) {
    return;
  }

  if ($contributionBAO->invoice_id) {
    // the invoice ID is already set
    $invoice_id = $contributionBAO->invoice_id;
  } else {
    $prefix = EVENT_FEE_INVOICE_PREFIX;
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
  }  
}

/**
 * Implements hook_civicrm_invoiceParams().
 */
function registration_civicrm_invoiceParams(&$tplParams, $contributionBAO) {
  // find and add billing address
  $billing_address = NULL;

  // first try with type Billing
  $addresses = civicrm_api3('Address', 'get', array(
    'contact_id'       => $contributionBAO->contact_id,
    'location_type_id' => 'Billing',
    ));
  foreach ($addresses['values'] as $address) {
    $billing_address = $address;
    if ($address['is_billing'] || $address['is_primary']) {
      break;
    }
  }

  // then try is_billing
  if (!$billing_address) {
    $addresses = civicrm_api3('Address', 'get', array(
      'contact_id' => $contributionBAO->contact_id,
      'is_billing' => 1,
      ));
    foreach ($addresses['values'] as $address) {
      $billing_address = $address;
      if ($address['is_primary']) {
        break;
      }
    }
  }

  // if still empty, try others
  if (!$billing_address) {
    $addresses = civicrm_api3('Address', 'get', array(
      'contact_id' => $contributionBAO->contact_id,
      'is_billing' => 0,
      ));
    foreach ($addresses['values'] as $address) {
      $billing_address = $address;
      if ($address['is_primary']) {
        break;
      }
    }
  }

  // set the parameters
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
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function registration_civicrm_config(&$config) {
  _registration_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function registration_civicrm_xmlMenu(&$files) {
  _registration_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function registration_civicrm_install() {
  _registration_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function registration_civicrm_uninstall() {
  _registration_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function registration_civicrm_enable() {
  _registration_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function registration_civicrm_disable() {
  _registration_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function registration_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _registration_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function registration_civicrm_managed(&$entities) {
  _registration_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function registration_civicrm_caseTypes(&$caseTypes) {
  _registration_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function registration_civicrm_angularModules(&$angularModules) {
_registration_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function registration_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _registration_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
