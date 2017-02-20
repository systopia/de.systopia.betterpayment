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

use Civi\Payment\Exception\PaymentProcessorException;

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
   */
  protected $_mode = null;

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
    $this->apiKey = $paymentProcessor['user_name'];
    $this->outKey = $paymentProcessor['signature'];
    $this->inKey = $paymentProcessor['password'];
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
      $error[] = ts('The "Api Key" is not set in the Administer CiviCRM Payment Processor.');
    }
    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('The "Incoming Key" is not set in the Administer CiviCRM Payment Processor.');
    }
    if (empty($this->_paymentProcessor['signature'])) {
      $error[] = ts('The "Outcoming Key" is not set in the Administer CiviCRM Payment Processor.');
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

  /**
   * Param-validation
   *
   * @param array params
   * @param array keys
   *
   * @throws CRM_Core_Exception
   */
  static function validateParams($params, $keys) {
    foreach($keys as $key) {
      if (empty($params[$key])) {
        self::error("$key is not set!");
      }
    }
  }

  /**
   * throw an error; use prefix for msg
   *
   * @param string log-message
   * @param bool throw error or only error-log
   *
   * @throws CRM_Core_Exception
   */
  static function error($msg, $throwError = True) {
    $msg = 'CRM_Core_Payment_Betterpayment: ' .  $msg;
    error_log($msg);
    if ($throwError) throw new CRM_Core_Exception($msg);
  }

  /**
   * get NotifyUrl with URL-query
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   * @return string NotifyUrlWithQuery
   */
  function getNotifyUrlWithQuery($params) {
    $queryParams = array(
      'module' => $this->_component,
      'mode' => $this->_mode,
    );
    self::validateParams($queryParams, array('module', 'mode'));

    if ($this->_component == 'event') {
      $queryParams['participant_id'] = $params['participantID'];
      self::validateParams($queryParams, array('participant_id'));
    }

    $url = $this->getNotifyUrl();
    $query = $this->getUrlQuery($queryParams);
    $checksum = sha1($query . $this->inKey);
    return $url . $query . "&checksum=${checksum}";
  }

  /**
   * get common parameters (see betterpayment-specifications)
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   * @return array common parameters
   */
  function getCommonParams($params) {
    $commonParams = array(
      'payment_type' => 'cc',
      'api_key' => $this->apiKey,
      'order_id' => $params['contributionID'],
      'amount' => $params['amount'],
      'postback_url' => $this->getNotifyUrlWithQuery($params),
    );
    self::validateParams($commonParams, array(
      'payment_type',
      'api_key',
      'order_id',
      'amount',
      'postback_url',
    ));
    // error_log(print_r($commonParams['postback_url'], 1));
    return $commonParams;
  }

  /**
   * get email-address
   * depending on the module email-key differs
   *
   * @param array params
   *
   * @return string email-address
   */
  function getEmail($params) {
    if ($this->_component == 'contribute') {
      $billingLocationID = CRM_Core_BAO_LocationType::getBilling();
      return $params["email-${billingLocationID}"];
    } elseif ($this->_component == 'event') {
      return $params["email-Primary"];
    } else {
      // this is just for case and untested
      return $params["email"];
    }
  }

  /**
   * get billing address (see betterpayment-specifications)
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   * @return array billing address
   */
  function getBillingAddress($params) {
    $billingLocationID = CRM_Core_BAO_LocationType::getBilling();
    $billingAddress = array(
      'first_name' => $params["billing_first_name"],
      'last_name' => $params["billing_last_name"],
      'email' => $this->getEmail($params),
      'address' => $params["billing_street_address-${billingLocationID}"],
      'city' => $params["billing_city-${billingLocationID}"],
      'postal_code' => $params["billing_postal_code-${billingLocationID}"],
      'country' => $params["billing_country-${billingLocationID}"],
      'state' => $params["billing_state_province-${billingLocationID}"],
    );
    self::validateParams($billingAddress, array(
      'first_name',
      'last_name',
      'email',
      'address',
      'postal_code',
      'country',
    ));
    return $billingAddress;
  }

  /**
   * get redirect urls (see betterpayment-specifications)
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   * @return array redirect urls
   */
  function getRedirectionUrls($params) {
    $redirectionUrls = array(
      'success_url' => $this->getReturnSuccessUrl($params['qfKey']),
      'error_url' => $this->getCancelUrl($params['qfKey'], NULL),
    );
    self::validateParams($redirectionUrls, array('success_url', 'error_url'));
    return $redirectionUrls;
  }

  /**
   * get url-query from parameter-array
   *
   * @param array params
   *
   * @return string query-string
   */
  function getUrlQuery($params) {
    $args = array();
    foreach ($params as $key => $value) {
      if ($value === NULL) continue;
      $value = urlencode($value);
      $args[] = "{$key}={$value}";
    }
    $query = implode('&', $args);
    return $query;
  }

  /**
   * Get billing fields required for this processor.
   *
   * We apply the existing default of returning fields only for payment processor type 1. Processors can override to
   * alter.
   *
   * @param int $billingLocationID
   *
   * @return array
   */
  public function getBillingAddressFields($billingLocationID = NULL) {
    if (!$billingLocationID) {
      // Note that although the billing id is passed around the forms the idea that it would be anything other than
      // the result of the function below doesn't seem to have eventuated.
      // So taking this as a param is possibly something to be removed in favour of the standard default.
      $billingLocationID = CRM_Core_BAO_LocationType::getBilling();
    }
    return array(
      'first_name' => 'billing_first_name',
      'middle_name' => 'billing_middle_name',
      'last_name' => 'billing_last_name',
      'street_address' => "billing_street_address-{$billingLocationID}",
      'city' => "billing_city-{$billingLocationID}",
      'country' => "billing_country_id-{$billingLocationID}",
      'state_province' => "billing_state_province_id-{$billingLocationID}",
      'postal_code' => "billing_postal_code-{$billingLocationID}",
    );
  }

  /**
   * @param array $params
   * @param string $component
   *
   * @throws Exception
   */
  public function doTransferCheckout(&$params, $component = 'contribute') {
    // error_log(print_r($params, 1));
    $this->_component = $component;
    $PaymentParams = $this->getCommonParams($params);
    $PaymentParams = array_merge($PaymentParams, $this->getBillingAddress($params));
    $PaymentParams = array_merge($PaymentParams, $this->getRedirectionUrls($params));

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $PaymentParams);

    $baseUrl = $this->_paymentProcessor['url_site'];
    $url = "$baseUrl/rest/authorize";
    $result = $this->invokeAPI($url, $PaymentParams);

    if ($result['status_code'] == 1 && $result['client_action'] == 'redirect') {
      CRM_Utils_System::redirect($result['action_data']['url']);
    } else {
      self::error("Api-Response is invalid: " . json_encode($result));
    }
  }

  /**
   * Hash_call: Function to perform the API call to PayPal using API signature.
   *
   * @methodName is name of API  method.
   * @nvpStr is nvp string.
   * returns an associative array containing the response from the server.
   *
   * @param string $url
   * @param array $args
   *
   * @return array|object
   * @throws \Exception
   */
  public function invokeAPI($url, $args) {

    //setting the curl parameters.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);

    //turning off the server and peer verification(TrustManager Concept).
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, Civi::settings()->get('verifySSL'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, Civi::settings()->get('verifySSL') ? 2 : 0);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    //setting the nvpreq as POST FIELD to curl
    $query = $this->getUrlQuery($args);
    $checksum = sha1($query . $this->outKey);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "${query}&checksum=${checksum}");

    //getting response from server
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new PaymentProcessorException(curl_error($ch));
    } else {
      curl_close($ch);
    }

    // decode json-response
    $result = json_decode($response, true);

    if ($result['error_code'] != 0) {
      self::error("Apicall-error: " . json_encode($result));
    }

    return $result;
  }

  /**
   * Process incoming notification.
   */
  static public function handlePaymentNotification() {
    $betterpaymentIPN = new CRM_Core_Payment_BetterpaymentIPN();
    $betterpaymentIPN->main();
  }
}
