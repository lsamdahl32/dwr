<?php
/**
 * authorizenetAPI.php
 * uses anet API
 * Created by PhpStorm.
 * User: Lee
 * Date: 9/25/2017
 * Time: 1:30 PM
 */

define("MERCHANT_LOGIN_ID", getenv('MERCHANT_LOGIN_ID')); // todo need these codes - this is for sandbox
define("MERCHANT_TRANSACTION_KEY", getenv('MERCHANT_TRANSACTION_KEY')); // todo need these codes - this is for sandbox

require $_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/authorizenet/vendor/authorizenet/authorizenet/autoload.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class authorizenetAPI
{

    private $merchantAuthentication;

    /**
     * Constructor
     *
     * @return	void
     */
    public function __construct()
    {
//        define("AUTHORIZENET_LOG_FILE", "phplog");


//        if (!isLoggedIn()) {
//            header( 'Location: '.ATOOLSSITE_URL_FULL . '/login.php' ) ;
//            exit();
//        }

        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $this->merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $this->merchantAuthentication->setName(MERCHANT_LOGIN_ID);
        $this->merchantAuthentication->setTransactionKey(MERCHANT_TRANSACTION_KEY);
    }

    public function chargeCreditCard($amount, $ccNum, $expDate, $cardCode, $invoiceID, $item, $customer)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName(MERCHANT_LOGIN_ID);
        $merchantAuthentication->setTransactionKey(MERCHANT_TRANSACTION_KEY);

        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($ccNum);
        $creditCard->setExpirationDate($expDate);
        $creditCard->setCardCode($cardCode);

        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setCreditCard($creditCard);

        // Create order information
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($invoiceID);
        $order->setDescription($item);

        // Set the customer's Bill To address
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerAddress->setFirstName($customer['firstName']);
        $customerAddress->setLastName($customer['lastName']);
        $customerAddress->setCompany($customer['company']);
        $customerAddress->setAddress($customer['street']);
        $customerAddress->setCity($customer['city']);
        $customerAddress->setState($customer['state']);
        $customerAddress->setZip($customer['zip']);
        $customerAddress->setCountry($customer['country']);

        // Set the customer's identifying information
        $customerData = new AnetAPI\CustomerDataType();
        $customerData->setType("individual");
        $customerData->setId($customer['guestID']);
        $customerData->setEmail($customer['email']);

        // Add values for transaction settings
        $duplicateWindowSetting = new AnetAPI\SettingType();
        $duplicateWindowSetting->setSettingName("duplicateWindow");
        $duplicateWindowSetting->setSettingValue("60");

        // Add some merchant defined fields. These fields won't be stored with the transaction,
        // but will be echoed back in the response.
//        $merchantDefinedField1 = new AnetAPI\UserFieldType();
//        $merchantDefinedField1->setName("customerLoyaltyNum");
//        $merchantDefinedField1->setValue("1128836273");
//
//        $merchantDefinedField2 = new AnetAPI\UserFieldType();
//        $merchantDefinedField2->setName("favoriteColor");
//        $merchantDefinedField2->setValue("blue");

        // Create a TransactionRequestType object and add the previous objects to it
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setPayment($paymentOne);
        $transactionRequestType->setBillTo($customerAddress);
        $transactionRequestType->setCustomer($customerData);
        $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
//        $transactionRequestType->addToUserFields($merchantDefinedField1);
//        $transactionRequestType->addToUserFields($merchantDefinedField2);

        // Assemble the complete transaction request
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);

        // Create the controller and get the response
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        return $response;
    }

    public function getBatchList($fromDate, $toDate)
    {

        $request = new AnetAPI\GetSettledBatchListRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication);
        $request->setIncludeStatistics(true);

        // both the first and last dates must be in the same time zone
        $firstSettlementDate=new DateTime();
        $firstSettlementDate->setTimestamp(strtotime($fromDate));
        $request->setFirstSettlementDate($firstSettlementDate);

        $lastSettlementDate=new DateTime();
        $lastSettlementDate->setTimestamp(strtotime($toDate));
        $request->setLastSettlementDate($lastSettlementDate);

        $controller = new AnetController\GetSettledBatchListController ($request);

        $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            return $response;
        } else {
            return false;
        }

    }

    public function getTransList($batchId)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $request = new AnetAPI\GetSettledBatchListRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication);
        $request->setIncludeStatistics(true);

        //Setting a valid batch Id for the Merchant
        $request = new AnetAPI\GetTransactionListRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication);
        $request->setBatchId($batchId);
//        $sort = new AnetAPI\TransactionListSortingType();
//        $request->setSorting($sort->setOrderBy('accountType'));

        $controller = new AnetController\GetTransactionListController($request);

        //Retrieving transaction list for the given Batch Id
        $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            return $response;
        } else {
            return false;
        }


    }
}
