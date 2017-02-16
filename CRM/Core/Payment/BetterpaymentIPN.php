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
class CRM_Core_Payment_BetterpaymentIPN extends CRM_Core_Payment_BaseIPN {

  /**
   * Constructor function.
   */
  public function __construct() {
    // we need the raw-query-strings for validation
    $rawPostQuery = file_get_contents("php://input");
    $rawGetQuery = $_SERVER['QUERY_STRING'];
    $this->validateQuery($rawPostQuery);
    $this->validateQuery($rawGetQuery);

    // get all parameters
    $params = array_merge($_GET, $_REQUEST);
    $this->validateParams($params);
    $this->setInputParameters($params);
    parent::__construct();
  }

  /**
   * Query-validation
   *
   * @param string query
   *
   * @throws CRM_Core_Exception
   */
  public function validateQuery($query) {
    // get the incoming-key of the payment-processor
    $inKey =  civicrm_api3('PaymentProcessor', 'getvalue', array(
        'id' => $_GET['processor_id'],
        'return' => 'password')
      );

    // checksum-validation
    $regex = '/^(.+)&checksum=([a-z0-9]+)$/';
    if (preg_match($regex, $query, $matches) == 1) {
      $query = $matches[1];
      $checksum = $matches[2];
    } else {
      error_log('Checksum of post-query missing!');
      throw new CRM_Core_Exception("Checksum of post-query missing!");
    }
    if (sha1($query . $inKey) != $checksum) {
      error_log('Checksum of post-query is invalid!');
      throw new CRM_Core_Exception("Checksum of post-query is invalid!");
    }
  }

  /**
   * Param-validation
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   */
  public function validateParams($params) {
    $keys = array('order_id', 'transaction_id', 'status_code', 'module');
    foreach($keys as $key) {
      if (!isset($params[$key])) {
        $error_msg = "$key is not set!";
        error_log($error_msg);
        throw new CRM_Core_Exception($error_msg);
      }
    }
  }

  /**
   * Process IPN
   *
   * @return bool|void
   */
  public function main() {
    $params = $this->_inputParameters;
    if ($params['status_code'] == 8) {
      civicrm_api3('contribution', 'completetransaction', array(
        'id' => $params['order_id'],
        'trxn_id' => $params['transaction_id'])
      );
    } else {
      $error_msg = "Could not complete transaction. Status-Code = $params[status_code]";
      error_log($error_msg);
      civicrm_api3('contribution', 'create', array(
        'id' => $params['order_id'],
        'trxn_id' => $params['transaction_id'],
        'contribution_status_id' => 4, // failed
      ));
    }
    return TRUE;
  }
}
