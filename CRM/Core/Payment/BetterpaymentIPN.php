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

// BetterPayment transaction status IDs
define('BP_TXN_STATUS_STARTED',         1);
define('BP_TXN_STATUS_PENDING',         2);
define('BP_TXN_STATUS_COMPLETE',        3);
define('BP_TXN_STATUS_ERROR',           4);
define('BP_TXN_STATUS_CANCELLED',       5);
define('BP_TXN_STATUS_DECLINED',        6);
define('BP_TXN_STATUS_REFUNDED',        7);
define('BP_TXN_STATUS_AUTHORISED',      8);
define('BP_TXN_STATUS_REGISTERED',      9);
define('BP_TXN_STATUS_DEBTCOLLECTION', 10);
define('BP_TXN_STATUS_DEBTPAID',       11);
define('BP_TXN_STATUS_REVERSED',       12);
define('BP_TXN_STATUS_CHARGEBACK',     13);


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
    // first find/load the contribution
    $contribution_id = CRM_Core_Payment_Betterpayment::getContributionID($params['order_id']);
    if (empty($contribution_id)) {
      self::error("Bad order number: " . json_encode($params));
      return;
    }

    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    if ($contribution['is_error'] == 1) {
      self::error("Contribution not found: " . json_encode($params));
      return;
    }

    // process status update
    switch ($params['status_code']) {
      case BP_TXN_STATUS_COMPLETE:
      case BP_TXN_STATUS_AUTHORISED:
        $new_contribution_status = 1; // "completed"
        break;

      case BP_TXN_STATUS_STARTED:
      case BP_TXN_STATUS_PENDING:
      case BP_TXN_STATUS_REGISTERED:
        $new_contribution_status = 5; // "in progress"
        break;

      case BP_TXN_STATUS_CANCELLED:
      case BP_TXN_STATUS_REVERSED:
        $new_contribution_status = 3; // "cancelled"
        break;

      case BP_TXN_STATUS_ERROR:
      case BP_TXN_STATUS_DECLINED:
        $new_contribution_status = 4; // "failed"
        break;

      default:
        self::error("Unknown status-code: {$params[status_code]}");
        return;
    }

    // store status update
    $result = civicrm_api3('Contribution', 'create', array(
      'id'                     => $contribution_id,
      'trxn_id'                => $params['transaction_id'],
      'contribution_status_id' => $new_contribution_status
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
    // FIXME: exception will be thrown
    if ($participant['is_error'] == 1) {
      self::error("Participant not found: " . json_encode($params));
      return;
    }

    switch ($contribution_status) {
      case 1: // "completed"
        $new_participant_status = 1; // particpant status: "registered"
        break;

      case 2: // "pending"
      case 4: // "in progress"
        $new_participant_status = 6; // particpant status: "pending"
        break;

      case 3: // "cancelled"
        $new_participant_status = 4; // particpant status: "cancelled"
        break;

      default:
        self::error("Unexpected contribution status: {$contribution_status}");
        return;

    }

    if ($new_participant_status != $participant['participant_status_id']) {
      $result = civicrm_api3('Participant', 'create', array(
        'id'                    => $params['participant_id'],
        'participant_status_id' => $new_participant_status
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
    // error_log("Received IPN: " . json_encode($params));
    $this->validateParams($params);

    $contribution = $this->updateContribution($params);

    if ($params['module'] == 'event') {
      $this->updateParticipant($params, $contribution['contribution_status_id']);
    }

    return TRUE;
  }
}
