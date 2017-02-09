<?php

session_start( );

require_once 'civicrm.config.php';
require_once 'CRM/Core/Config.php';

$config = CRM_Core_Config::singleton();

// Change this to fit your processor name.
require_once 'CRM/Core/Payment/BetterpaymentIPN.php';

// Change this to match your payment processor class.
CRM_Core_Payment_BetterpaymentIPN::main();
