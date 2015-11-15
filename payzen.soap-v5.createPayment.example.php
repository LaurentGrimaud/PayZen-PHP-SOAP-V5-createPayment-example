<?php
/*
 * `createPayment` example, using PayZen SOAP V5 webservices
 *
 * @version 0.5
 *
 */

 require "payzenSoapV5ToolBox.php";

 // Toolbox initialisation, using PayZen account informations
 $toolbox = new payzenSoapV5ToolBox (
     '[***CHANGE-ME***]' // shopId
   , '[***CHANGE-ME***]' // certificate, TEST-version
   , '[***CHANGE-ME***]' // certificate, PRODUCTION-version
   , 'TEST'              // mode choice, "TEST" or "PRODUCTION"
   );

/*
 * Toolbox can accept logging callback method
 * Use it if you need special logging, like database logging
 * or if you need to hook the toolbox to your own logging process
 *
 $toolbox->setLogFunction(function($level, $message, $data = null){
  printf(
        ">>>\nLOG TIME: %s\nLOG LEVEL: %s\nLOG MESSAGE: %s\nLOG DATA:\n %s\n<<<\n"
      , date('r')
      , $level
      , $message
     , print_r($data, true)
    );
  });
*/

// Sets the toolbox log level to 'NOTICE', to gain maximun feedback
// about the request process. Comment out this line to get rid of logs
$toolbox->setNoticeLogLevel();

 try {
  // `createPayment` request sending
  $reponse = $toolbox->simpleCreatePayment(
      '1234'              // payment amount - Change-it to reflect your needs
    , '978'               // payment currency code - Change-it to reflect your needs
    , '4970100000000003'  // customer payment or credit card number - Change-it to reflect your needs
    , '06'                // customer card expiry month - Change-it to reflect your needs
    , '2016'              // customer card expiry year - Change-it to reflect your needs
    , '123'               // customer card CSC - Change-it to reflect your needs
    , 'VISA'              // customer card scheme - Change-it to reflect your needs
    , '12345678'          // your order identifier - Change-it to reflect your needs
    );


  $responseInfo = $reponse->createPaymentResult->commonResponse;
  echo "Response code: {$responseInfo->responseCode}\n";

  $message = @$responseInfo->responseCodeDetail ? : '[NONE]';
  echo "Message: $message\n";

 }catch(Exception $e) {
  echo "\n### ERROR - Something's wrong, an exception raised during process:\n";
  echo $e;
 }
