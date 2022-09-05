<?php

# Bank Transfer Payment Gateway Module
if (!defined("WHMCS")) die("This file cannot be accessed directly");
class IPPGateway {

    private $company_id;
    private $company_key2;

    function __construct($id,$key) {
        $this->company_id = $id;
        $this->company_key2 = $key;
    }

    public function checkout_id($data){
        return $this->curl("https://api.ippeurope.com/payments/checkout_id", "POST", [], $data)->content;
    }
    public function payment_status($transaction_id,$transaction_key){
        $data = ["transaction_id" => $transaction_id, "transaction_key" => $transaction_key];
        var_dump($data);
        return $this->curl("https://api.ippeurope.com/payments/status", "POST", [], $data)->content;
    }
    public function request($url, $data){
        return $this->curl("https://api.ippeurope.com/".$url, "POST", [], $data);
    }
    private function curl($url, $type = 'POST', $query = [], $data = [], $headers = []){
        $data["id"] = $this->company_id;
        $data["key2"] = $this->company_key2;
        $data["origin"] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$url.php?".http_build_query($query, "", "&", PHP_QUERY_RFC3986));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        if($type == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (is_array($headers) && sizeof($headers) > 0) {
            curl_setopt($ch, CURLOPT_HEADER, $headers);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }
        $server_output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($server_output);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $json;
        }
        return $json;
    }
}

function ippgateway_config() {

    $configarray = array(
     "FriendlyName" => array(
        "Type" => "System",
        "Value" => "IPPGateway"
        ),
        'accountID' => array(
            'FriendlyName' => 'Account ID',
            'Type' => 'text',
            'Size' => '14',
            'Default' => '',
            'Description' => 'Enter your 14 digit Account ID here',
        ),
        // a password field type allows for masked text input
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),
    );

    return $configarray;

}

function ippgateway_link($params) {
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $postfields = array();
    $postfields['invoice_id'] = $invoiceId;
    $postfields['description'] = $description;
    $postfields['amount'] = $amount;
    $postfields['currency'] = $currencyCode;
    $postfields['first_name'] = $firstname;
    $postfields['last_name'] = $lastname;
    $postfields['email'] = $email;
    $postfields['address1'] = $address1;
    $postfields['address2'] = $address2;
    $postfields['city'] = $city;
    $postfields['state'] = $state;
    $postfields['postcode'] = $postcode;
    $postfields['country'] = $country;
    $postfields['phone'] = $phone;
    $postfields['ipn'] = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php?id='.$invoiceId."&Amount=".$amount;
    $postfields['return_url'] = $returnUrl;

    $gateway    = new IPPGateway($accountId,$secretKey);
    $data   = $postfields;
    $data["currency"] = $postfields['currency'];
    $data["amount"] = number_format(str_replace(",",".",$postfields['amount']),2,"","");
    $data["order_id"] = $invoiceId;
    $data["rebill"] = 1;
    $data["transaction_type"] = "ECOM";

    $data = $gateway->checkout_id($data);


    $data_url = $data->checkout_id;
    $cryptogram = $data->cryptogram;
    $htmlOutput = "";
    if(!isset($_GET["transaction_id"]) && parse_url($_SERVER['REQUEST_URI'])["path"] === "/viewinvoice.php") {
        $htmlOutput .= '<div id="checkout-payment"><button class="ShowPayment">' . $langPayNow . '</button></div>';
        $htmlOutput .= "<div id='ippgateway_form' style='display:none;'><div class='payment_background'></div><form action='".$returnUrl."' class='search-form paymentWidgets' data-brands='VISA MASTER' data-theme='divs'>";
        $htmlOutput .= '<input type="hidden" name="id" value="'.$invoiceId.'" />';
        $htmlOutput .= '<input type="hidden" name="ipn" value="'.$postfields['ipn'].'" />';
        $htmlOutput .= '</form></div>';
        $htmlOutput .= '<script src="'.$systemUrl.'modules/gateways/ippgateway/assets/js.js"></script>';
        $htmlOutput .= '<link href="'.$systemUrl.'modules/gateways/ippgateway/assets/css.css" rel="stylesheet">';
        $htmlOutput .= '<script src="https://pay.ippeurope.com/pay.js?checkoutId='.$data_url.'&cryptogram='.$cryptogram.'"></script>';
    }
    if(isset($_GET["transaction_id"])) {
        $htmlOutput .= "<script>setTimeout(function(){ window.location.reload(1); }, 5000);</script>";
    }

    return $htmlOutput;
}
