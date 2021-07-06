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

use CRM_Registration_ExtensionUtil as E;

/**
 * Implements hook_civicrm_invoiceParams().
 */
function registration_civicrm_invoiceParams(&$tplParams, $contributionBAO) {
  CRM_Registration_Invoicing::adjustInvoice($tplParams, $contributionBAO);
}

/**
 * Redirect "Email Invoice" button to our own interpretation
 *
 * @todo use registration_civicrm_alterMenu(&$items) with 4.7+
 */
function registration_civicrm_preProcess($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Task_Invoice') {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $store = NULL, FALSE);
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

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function registration_civicrm_navigationMenu(&$menu)
{
  _registration_civix_insert_navigation_menu($menu, 'Events', [
      'label'      => E::ts('Check-In Desk'),
      'name'       => 'registration_checkin',
      'url'        => 'civicrm/participant/checkin',
      'permission' => 'edit event participants',
      'operator'   => 'OR',
      'separator'  => 0,
  ]);
  _registration_civix_navigationMenu($menu);
}