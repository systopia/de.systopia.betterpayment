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
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function betterpayment_civicrm_install() {
  _betterpayment_civix_civicrm_install();
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
      'is_default'                => 0,
      'user_name_label'           => 'Api Key',
      'password_label'            => 'Incoming Key',
      'signature_label'           => 'Outcoming Key',
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
