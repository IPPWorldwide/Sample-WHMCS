<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../ippgateway.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$transactionId = $_GET["transaction_id"];
$transactionKey = $_GET["transaction_key"];
$id = $_GET["id"];
$Amount = $_GET["Amount"];


$gateway    = new IPPGateway($gatewayParams['accountID'],$gatewayParams['secretKey']);

$status = $gateway->payment_status($transactionId,$transactionKey);

var_dump($status);
if($status->result === "ACK") {
    $invoiceId = checkCbInvoiceID($id, $gatewayParams['name']);

    /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     *
     * @param string $transactionId Unique Transaction ID
     */
    checkCbTransID($transactionId);

    /**
     * Log Transaction.
     *
     * Add an entry to the Gateway Log for debugging purposes.
     *
     * The debug data can be a string or an array. In the case of an
     * array it will be
     *
     * @param string $gatewayName        Display label
     * @param string|array $debugData    Data to log
     * @param string $transactionStatus  Status
     */
    logTransaction($gatewayParams['name'], $_GET, "Success");

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $Amount,
        0,
        $gatewayModuleName
    );
}

