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


/**
 *
 */
class CRM_Core_Payment_Betterpayment extends CRM_Core_Payment {
  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  static protected $_mode = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode             = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName    = ts('Betterpayment');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton( $mode, &$paymentProcessor ) {
      $processorName = $paymentProcessor['name'];
      if (self::$_singleton[$processorName] === null ) {
          self::$_singleton[$processorName] = new CRM_Core_Payment_Betterpayment( $mode, $paymentProcessor );
      }
      return self::$_singleton[$processorName];
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig( ) {
    $config = CRM_Core_Config::singleton();

    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('The "Bill To ID" is not set in the Administer CiviCRM Payment Processor.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  function getCommonParams() {
    $commonParams = array(
      'payment_type' => 'cc',
      'api_key' => '',
      'order_id' => '',
      'amount' => '',
      'postback_url' => $this->getNotifyUrl(),
    );
    return $commonParams;
  }

  function getBillingAddress() {
    //   $stateName = CRM_Core_PseudoConstant::stateProvinceAbbreviation($value);
    //   $countryName = CRM_Core_PseudoConstant::countryIsoCode($value);

    $billingAddress = array(
      'address' => '',
      'city' => '',
      'postal_code' => '',
      'country' => '',
      'first_name' => '',
      'last_name' => '',
      'email' => '',
      'address2' => '',
      'state' => '',
      'phone' => '',
    );
    return $billingAddress;
  }

  function getRedirectionUrls() {
    $redirectionUrls = array(
      'success_url' => $this->getReturnSuccessUrl(),
      'error_url' => $this->getReturnFailUrl(),
    );
    return $redirectionUrls;
  }

  function getUrlQuery($PaymentParams) {
    $query = '';
    foreach ($PaymentParams as $key => $value) {
      if ($value === NULL) continue;
      $value = urlencode($value);
      if (strpos(strrev($key), strrev('_url')) === 0) {
        $value = str_replace('%2F', '/', $value);
      }
      $query .= "&{$key}={$value}";
    }
    return $query;
  }

  function getChecksum($query, $key) {
    return sha1($query, $key);
  }

  /**
   * @param array $params
   * @param string $component
   *
   * @throws Exception
   */
  public function doTransferCheckout(&$params, $component = 'contribute') {

    $PaymentParams = $this->getCommonParams();
    $PaymentParams = array_merge($PaymentParams, $this->getBillingAddress());
    $PaymentParams = array_merge($PaymentParams, $this->getRedirectionUrls());

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $paypalParams);

    $query = $this->getUrlQuery($PaymentParams);
    $checksum = $this->getChecksum($query, $this->$outKey);
    $baseUrl = $this->_paymentProcessor['url_site'];
    $URL = "{$baseUrl}?{$query}&checksum={$checksum}";

    CRM_Utils_System::redirect($URL);
  }

}
