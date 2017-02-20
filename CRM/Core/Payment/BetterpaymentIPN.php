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
    $this->setInputParameters($params);
    parent::__construct();
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
    $msg = 'CRM_Core_Payment_BetterpaymentIPN: ' .  $msg;
    error_log($msg);
    if ($throwError) throw new CRM_Core_Exception($msg);
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
      self::error('Checksum of post-query missing!');
    }
    if (sha1($query . $inKey) != $checksum) {
      self::error('Checksum of post-query is invalid!');
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
    CRM_Core_Payment_Betterpayment::validateParams($params, array(
      'order_id',
      'transaction_id',
      'status_code',
      'module',
      'mode',
    ));
    if ($params['module'] == 'event') {
      CRM_Core_Payment_Betterpayment::validateParams($params, array('participant_id'));
    }
  }

  /**
   * Update Contribution
   *
   * @param array params
   *
   * @throws CRM_Core_Exception
   * @return contribution
   */
  public function updateContribution(&$params) {
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $params['order_id']));
    if ($contribution['is_error'] == 1) {
      self::error("Contribution not found: " . json_encode($params));
    }

    switch ($params['status_code']) {
      case 3:
        // completed
        $status_id = 1; break;
      case 8:
        // testapi only gives as status-code 8 (authorized)
        $status_id = ($params['mode'] == 'test') ? 1 : 2; break;
      case 1:
      case 2:
      case 9:
        // pending
        $status_id = 2; break;
      case 5:
      case 12:
        // canceled
        $status_id = 3; break;
      case 4:
      case 6:
        // error
        $status_id = 4; break;
      default:
        self::error("Unknown status-code: $params[status_code]");
    }

    $result = civicrm_api3('Contribution', 'create', array(
      'id' => $params['order_id'],
      'trxn_id' => $params['transaction_id'],
      'contribution_status_id' => $status_id
    ));
    return reset($result['values']);
  }

  /**
   * Update Participant
   *
   * @param array params
   * @param int contribution_status
   *
   * @throws CRM_Core_Exception
   * @return participant|void
   */
  public function updateParticipant(&$params, $contribution_status) {
    $participant = civicrm_api3('Participant', 'getsingle', array('id' => $params['participant_id']));
    if ($participant['is_error'] == 1) {
      self::error("Participant not found: " . json_encode($params));
    }

    switch ($contribution_status) {
      case 1:
        // completed
        $status_id = 1; break;
      case 2:
        // pending
        $status_id = 6; break;
      case 3:
        // canceled
        $status_id = 4; break;
      case 4:
        // pending because of error while payment
        $status_id = 6; break;
    }

    if ($status_id != $participant['participant_status_id']) {
      $result = civicrm_api3('Participant', 'create', array(
        'id' => $params['participant_id'],
        'participant_status_id' => $status_id
      ));
      return reset($result['values']);
    }
  }


  /**
   * Process IPN
   *
   * @return bool|void
   */
  public function main() {
    $params = $this->_inputParameters;
    $this->validateParams($params);

    $contribution = $this->updateContribution($params);

    if ($params['module'] == 'event') {
      $this->updateParticipant($params, $contribution['contribution_status_id']);
    }

    return TRUE;
  }
}
