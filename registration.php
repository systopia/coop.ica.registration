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

    // update invoice_id on contribution, since it isn't stored stored automatically
    //  FIXME: report bug?
    civicrm_api3('Contribution', 'create', array(
      'id'         => $contributionBAO->id,
      'invoice_id' => $invoice_id,
      ));
  }
}

/**
 * Implements hook_civicrm_invoiceParams().
 */
function registration_civicrm_invoiceParams(&$tplParams, $contributionBAO) {
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


  // FIND and add billing address
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
 * Redirect "Email Invoice" button to our own interpretation
 *
 * @todo use registration_civicrm_alterMenu(&$items) with 4.7+
 */
function registration_civicrm_preProcess($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Task_Invoice') {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE);
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/registration/registration_email', "cid={$id}"));
  }
}

/**
 * Add new "send confirmation" action to contributions
 */
function registration_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if ($op == 'contribution.selector.row' && $objectName == 'Contribution') {
    // check if contribution is eligible:
    $validation = CRM_Registration_Page_Email::verifyContribution($objectId);
    if (is_array($validation)) { // that means it's ok
      $links[] = array(
          'name'  => ts('Email Confirmation'),
          'title' => ts('Email Confirmation'),
          'url'   => 'civicrm/registration/registration_email',
          'qs'    => "cid={$objectId}",
        );
    }
    // Add links for Contribution Registration Edit form:
    $links[] = array(
      'name'      => ts('Adjust Invoice'),
      'title'     => ts('Adjust Invoice'),
      'url'       => 'civicrm/registation/editpayment',
      'qs'        =>"cid={$objectId}",
      'class' => 'no-popup',
    );
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
  require_once 'CRM/Registration/CustomData.php';
  $customData = new CRM_Registration_CustomData('coop.ica.registration');
  $customData->syncCustomGroup(__DIR__ . '/resources/contribution_custom_group.json');

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
 * Implements hook_civicrm_buildForm()
 * @param $formName
 * @param $form
 */
function registration_civicrm_buildForm($formName, &$form) {
  error_log("Form: {$formName}");
  switch ($formName) {
    case 'CRM_Event_Form_ParticipantView':
      require_once 'CRM/Registration/GUI_changes.php';
      CRM_Registration_Gui_changes::buildForm_hook_participant_view($formName, $form);
      break;
    case 'CRM_Event_Form_Participant':
      require_once 'CRM/Registration/GUI_changes.php';
      CRM_Registration_Gui_changes::buildForm_hook_participant($formName, $form);
      break;
    default:
      break;
  }
}

/**
 * Hook implementation: Inject JS code adjusting summary view
 */
function registration_civicrm_pageRun(&$page) {
  $page_name = $page->getVar('_name');
  error_log("Page: {$page_name}");
  switch ($page_name) {
    case 'CRM_Contribute_Page_PaymentInfo':
//      require_once 'CRM/Registration/GUI_changes.php';
//      CRM_Registration_Gui_changes::page_run_hook($page);
//      break;
    default:
      break;
  }
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
