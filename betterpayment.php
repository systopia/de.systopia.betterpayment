<?php
/*-------------------------------------------------------+
| SYSTOPIA Betterpayment Payment Processor               |
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

require_once 'betterpayment.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function betterpayment_civicrm_config(&$config) {
  _betterpayment_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function betterpayment_civicrm_xmlMenu(&$files) {
  _betterpayment_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function betterpayment_civicrm_install() {
  _betterpayment_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function betterpayment_civicrm_uninstall() {
  _betterpayment_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function betterpayment_civicrm_enable() {
  _betterpayment_civix_civicrm_enable();
  betterpayment_pp_install();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function betterpayment_civicrm_disable() {
  _betterpayment_civix_civicrm_disable();
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
function betterpayment_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _betterpayment_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function betterpayment_civicrm_managed(&$entities) {
  _betterpayment_civix_civicrm_managed($entities);
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
function betterpayment_civicrm_caseTypes(&$caseTypes) {
  _betterpayment_civix_civicrm_caseTypes($caseTypes);
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
function betterpayment_civicrm_angularModules(&$angularModules) {
_betterpayment_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function betterpayment_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _betterpayment_civix_civicrm_alterSettingsFolders($metaDataFolders);
}


/**
 * Will register the payment processor
 */
function betterpayment_pp_install() {
  $pp = civicrm_api3('PaymentProcessorType', 'get', array('name' => 'betterpayment'));
  if (empty($pp['id'])) {
    $pp = civicrm_api3('PaymentProcessorType', 'create', array(
      'name'                      => 'betterpayment',
      'title'                     => ts("BetterPayment", array('domain' => 'de.systopia.betterpayment')),
      'description'               => ts("BetterPayment.de payment processor.", array('domain' => 'de.systopia.betterpayment')),
      'is_active'                 => 1,
      'user_name_label'           => '',
      'class_name'                => 'Payment_Betterpayment',
      'url_site_default'          => 'https://api.betterpayment.de',
      'url_recur_default'         => '',
      'url_site_test_default'     => 'https://testapi.betterpayment.de',
      'url_recur_test_default'    => '',
      'billing_mode'              => '4',
      'is_recur'                  => '0',
      'payment_type'              => CRM_Core_Payment::PAYMENT_TYPE_CREDIT_CARD)
    );
  }
}
