<?php
/*
 * Utility class easing PayZen SOAP V5 webservices use
 *
 * Currently only supports `createPayment` requests
 *
 * @version 0.5
 *
 * @depends UUID.php, the external UUID generator
 * @link https://gist.github.com/dahnielson/508447
 *
 */

require "UUID.php";


class payzenSoapV5ToolBox {
 /**************** CLASS CONSTANTS **************/
 // The toolbox handles 4 log levels
 const NO_LOG  = 0; // Default log level - No logs
 const ERROR   = 1; // Narrower log level - Only errors
 const WARNING = 2; // Intemediate log level - Warning and errors
 const NOTICE  = 3; // Wider log level - All logs

 // The toolbox uses V5 UUID, wich needs a valid UUID value as namespace
 public $uuidNameSpace = '1546058f-5a25-4334-85ae-e68f2a44bbaf';

 /**************** CLASS PROPERTIES **************/
 // Container for PayZen SOAP plat-form localisation
 public $payzenPlatForm = [                           
     'wsdl'   => 'https://secure.payzen.eu/vads-ws/v5?wsdl' // URL of PayZen SOAP V5 WSDL
   , 'ns'     => 'http://v5.ws.vads.lyra.com/Header'
 ];

 //  Container for specific PHP SOAP Client configuration
 public $soapClientOptions = [
    'trace'    => 1                // Enable trace to get access to request and response details
  , 'encoding' => 'UTF-8'          // PayZen SOAP V5 expects utf-8 encoded data
 ];

 // Container for PayZen user's account informations
 public $payzenAccount;

 // Callback method used by logging mechanism
 public $logMethod;

 // Toolbox log level. The log entries with greater level will be ignored 
 public $logLevel;



 /**************** CLASS METHOD - PUBLIC **************/
 /*
  * Constructor, stores the PayZen user's account informations
  *
  * @param $shopid string, the account shop id as provided by Payzen
  * @param $cert_test string, certificate, test-version, as provided by PayZen
  * @param $cert_prod string, certificate, production-version, as provided by PayZen
  * @param $mode string ("TEST" or "PRODUCTION"), the PayZen mode to operate
  */
 public function __construct($shopId, $cert_test, $cert_prod, $mode = 'TEST'){
  $this->payzenAccount = [
   'shopId' => $shopId
    ,'cert'   => [
       'TEST'       => $cert_test
     , 'PRODUCTION' => $cert_prod
    ]
    ,'mode'   => $mode
  ];


  $this->logLevel = self::NO_LOG; // No logging by default

  // self::defaultLog is the default logging method
  $this->logMethod = function($level, $message, $data = null){
   $this->defaultLog($level, $message, $data);
  };
 }

 /*
  * Main function, creates and sends a `createPayment` PayZen request
  *
  * @param $amount string, the amount to charge, using the smallest unit of the choosen currency
  * @param $currency string, the code of the currency to use
  * @param $cardNumber string, the number of the payment or credit card
  * @param $expiryMonth string, the card expiry month, two digits formatted
  * @param $expiryYear string, the card expiry year, four digits formatted
  * @param $cardSecurityCode string, the card CSC
  * @param $scheme string, the card type ("AMEX", "CB", "MASTERCARD", "VISA", "MAESTRO", "E-CARTEBLEUE" or "CB")
  * @param $orderId string, the order identifier
  *
  * @return Object, PayZen SOAP response
  *
  * @throws SOAPFaut, on PHP SoapClient errors
  */
 public function simpleCreatePayment($amount, $currency, $cardNumber, $expiryMonth, $expiryYear, $cardSecurityCode, $scheme, $orderId) {
  $this->logNotice("createPayment requested");
  // Formats the `commonRequest` part
  $commonRequest = $this->buildCommonRequest();
  // Formats the `paymentRequest` part
  $paymentRequest = $this->buildPaymentRequest($amount, $currency);
  // Formats the `cardRequest` part
  $cardRequest = $this->buildCardRequest($cardNumber, $expiryMonth, $expiryYear, $cardSecurityCode, $scheme);
  // Formats the `orderRequest` part
  $orderRequest = $this->buildOrderRequest($orderId);

  // Builds SOAP headers
  $soapHeaders = $this->buildSoapHeaders();

  // Builds the SOAP request body
  $createPaymentWorkload = [
      'commonRequest'   => $commonRequest
    , 'paymentRequest'  => $paymentRequest
    , 'orderRequest'    => $orderRequest
    , 'cardRequest'     => $cardRequest
    , 'customerRequest' => [] // Mandatory, but can be empty
    , 'techRequest'     => [] // Mandatory, but can be empty
  ];

  // Sets-up the whole SOAP request
  $soapClient = new SoapClient($this->payzenPlatForm['wsdl'], $this->soapClientOptions);
  $soapClient->__setSoapHeaders($soapHeaders);

  // Sends the `createPayment` request
  $this->logNotice('SOAP request is ready, sending it', $soapClient->__getLastRequestHeaders());
  $res = $soapClient->createPayment($createPaymentWorkload);
  $code = $res->createPaymentResult->commonResponse->responseCode; 
  $message = @$res->createPaymentResult->commonResponse->responseCodeDetail; 
  if($code === 0) {
   $this->logNotice('Response received, request was successful: code is 0', $soapClient->__getLastRequestHeaders());
  }else{
   $this->logWarning("Response received, request wasn't successful: code is $code , message is ".($message ? : '[NONE]'));
  }

  $this->logNotice('Request HTTP headers', $soapClient->__getLastRequestHeaders());
  $this->logNotice('Request HTTP body', $this->formatXML($soapClient->__getLastRequest()));

  $this->logNotice('Response HTTP headers', $soapClient->__getLastResponseHeaders());
  $this->logNotice('Response HTTP body', $this->formatXML($soapClient->__getLastResponse()));

  // Validates the response's SOAP header
  $this->checkResponseHeaders($soapClient->__getLastResponse());

  return $res;
 }


 /*
  * Utility function, check the response SOAP headers for a correct authToken
  *
  * @param $response, string the response as XML string
  *
  * @throws Exception if the correct authToken is not found
  */
 public function checkResponseHeaders($response) {
  $dom = new DOMDocument;
  $dom->loadXML($response, LIBXML_NOWARNING);
  $path = new DOMXPath($dom);
  $headers = $path->query('//*[local-name()="Header"]/*');
  $responseHeader = array();
  foreach($headers as $headerItem) {
   $responseHeader[$headerItem->nodeName] = $headerItem->nodeValue;
  }
  foreach(["shopId", "timestamp", "requestId", "mode", "authToken"] as $name){
   if(! isset($responseHeader[$name])) throw new Exception("Missing `$name` header in PayZen SOAP response");
  }
  $expected = $this->buildAuthToken($responseHeader['requestId'], $responseHeader['timestamp'], 'response');
  if($responseHeader['authToken'] !== $expected) {
   $msg = sprintf("Bad response's authToken - Expected `%s`, found `%s`", $expected, $responseHeader['authToken']);
   $this->logError($msg);
   throw new Exception($msg);
  }
  $this->logNotice("Response authToken is correct (`$expected`)");
 }

 /*
  * Utility function, generates the PayZen authToken
  *
  * @param $requestId string, UUID for the request
  * @param $timeStamp string, timeStamp of the request
  * @param $format string ("request" or "response"), the expected format of the authToken
  *
  * @return string, the authToken
  */
 public function buildAuthToken($requestId, $timeStamp, $format = "request"){
  // the request's authToken must be based on $requestId.$timeStamp
  // the response's authToken must be based on $timeStamp.$requestId
  $data = ($format == 'request') ? $requestId.$timeStamp : $timeStamp.$requestId;
  return base64_encode(
    hash_hmac('sha256',
     $data,
     $this->payzenAccount['cert'][$this->payzenAccount['mode']], true
     ));
 }

 /*
  * Utility function, build the PayZen SOAP V5 headers
  *
  * @return Array of SOAPHeader objects, the complete headers definition
  *
  * @throws Exception, if UUID generation fails
  */
  public function buildSoapHeaders() {
   $timeStamp = gmdate("Y-m-d\TH:i:s\Z");
   $requestId = UUID::v5($this->uuidNameSpace, $timeStamp);
   if(false === $requestId) {
    throw new Exception("Failed to generate the mandatory UUID");
   }

   $payzenSoapHeaders = [
      'shopId'    => $this->payzenAccount['shopId']
    , 'requestId' => $requestId
    , 'timestamp' => $timeStamp
    , 'mode'      => $this->payzenAccount['mode']
    , 'authToken' => $this->buildAuthToken($requestId, $timeStamp)
   ];

    $soapHeaders = [];
    foreach($payzenSoapHeaders as $header => $value) {
     $soapHeaders[] = new SOAPHeader($this->payzenPlatForm['ns'], $header, $value);
    }

    $this->logNotice("PayZen SOAP headers built", $payzenSoapHeaders);
    return $soapHeaders;
  }


 /*
  * Utility function, format the "common request" informations as needed by Payzen
  * Currently only sets the submissionDate entry
  *
  * @return Array of strings, the `commonRequest` part of createPayment request
  */
 public function buildCommonRequest() {
  return [
    'submissionDate' => gmdate("Y-m-d\TH:i:s\Z")
  ];
 }

 /*
  * Utility function, format the payment informations as needed by Payzen
  *
  * @param $amount string, the amount to charge, using the smallest unit of the choosen currency
  * @param $currency string, the code of the currency to use, default to 978 (euro)
  *
  * @return Array of strings, the `paymentRequest` part of createPayment request
  */
 public function buildPaymentRequest($amount, $currency = '978') {
  return [
     'amount'   => $amount
   , 'currency' => $currency
  ];
 } 

 /*
  * Utility function, format the card informations as needed by Payzen
  *
  * @param $cardNumber string, the number of the payment or credit card
  * @param $expiryMonth string, the card expiry month, two digits formatted
  * @param $expiryYear string, the card expiry year, four digits formatted
  * @param $cardSecurityCode string, the card CSC,three-or-four digits formatted
  * @param $scheme string, the card type ("AMEX", "CB", "MASTERCARD", "VISA", "MAESTRO", "E-CARTEBLEUE" or "CB")
  *
  * @return Array of strings, the `cardRequest` part of createPayment request
  */
 public function buildCardRequest($cardNumber, $expiryMonth, $expiryYear, $cardSecurityCode, $scheme) {
  return [
      'number'           => $cardNumber
    , 'expiryMonth'      => $expiryMonth 
    , 'expiryYear'       => $expiryYear
    , 'cardSecurityCode' => $cardSecurityCode 
    , 'scheme'           => $scheme
  ];
 }

 /*
  * Utility function, format the order informations as needed by Payzen
  *
  * @param $orderId string, the order identifier
  *
  * @return Array of strings, the `orderRequest` part of createPayment request
  */
 public function buildOrderRequest($orderId) {
  return [
   'orderId' => $orderId     // Identifiant de commande
  ];
 }

 /*
  * Utility function, pretty-formats an XML string
  *
  * @param $xml string, the XML to process
  *
  * @return string, the XML, pretty-formatted
  */
 public function formatXML($xml){
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml);
  return $dom->saveXML();
 }


 /*
  * Setter to allow custom logging
  *
  * @param $f callable, callback method that must accept
  * 3 arguments, just like self::defaultLog()
  */
 public function setLogFunction(Callable $f) {
  $this->logMethod = $f;
 }


 /*
  * Customisation method. Sets the toolbox log level to NOTICE one
  * This is the wider level, every log entry will be processed
  */
 public function setNoticeLogLevel() {
  $this->logLevel = self::NOTICE;
 }


 /*
  * Customisation method. Sets the toolbox log level to WARNING one
  * Only the ERROR and WARNING messages will be processed
  */
 public function setWarningLogLevel() {
  $this->logLevel = self::WARNING;
 }

 /*
  * Customisation method. Sets the toolbox log level to WARNING one
  * Only the ERROR messages will be processed
  */
 public function setErrorLogLevel() {
  $this->logLevel = self::ERROR;
 }

 /*
  * Utility method. Sends a NOTICE log entry to the logging mechanism
  *  if the toolbox log level permits it.
  *
  * @param $message string, main log information, as a sentence
  * @param $data mixed, additionnal informations
  */
 public function logNotice($message, $data = null) {
  if($this->logLevel >= self::NOTICE)
   $this->_log('NOTICE', $message, $data);
 }

 /*
  * Utility method. Sends a WARNING log entry to the logging mechanism
  *  if the toolbox log level permits it.
  *
  * @param $message string, main log information, as a sentence
  * @param $data mixed, additionnal informations
  */
 public function logWarning($message, $data = null) {
  if($this->logLevel >= self::WARNING)
   $this->_log('WARNING', $message, $data);
 }

 /*
  * Utility method. Sends an ERROR log entry to the logging mechanism
  *  if the toolbox log level permits it.
  *
  * @param $message string, main log information, as a sentence
  * @param $data mixed, additionnal informations
  */
 public function logError($message, $data = null) {
  if($this->logLevel >= self::ERROR)
   $this->_log('ERROR', $message, $data);
 }


 /*
  * Utility method, formats and prints log informations
  * This is the default logging method, is no custom one
  * has been previously provided.
  *
  * @param $level string, severity level of the log informations
  * @param $message string, main log information, as a sentence
  * @param $data mixed, additionnal informations
  */
 protected function defaultLog($level, $message, $data = null) {
  printf("[%s][%s] %s %s\n",
      date('Y-m-d H:s:i')
    , $level
    , $message
    , $data ? "\n".print_r($data, true) : ''
    );
 }

 /*************** CLASS METHODS - PROTECTED *************/
 /*
  * Utility method, main passing point for log messages
  * Relays the log entry to the configured log method stored
  * in self::logMethod
  *
  * @param $level string, one of NOTICE, WARNING, ERROR
  * @param $message string, main log information, as a sentence
  * @param $data mixed, additionnal informations, as array or object
  */
 protected function _log($level, $message, $data = null){
  $log = $this->logMethod;
  $log($level, $message, $data);
 }
}
