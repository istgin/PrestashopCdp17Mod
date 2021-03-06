<?php
/**
 * Created by PhpStorm.
 * User: i.sutugins
 * Date: 14.5.9
 * Time: 11:49
 */




function getClientIp() {
    $ipaddress = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if(!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if(!empty($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    $ex = explode(",", $ipaddress);
    return trim($ex[0]);
}

function mapPaymentMethodToSpecs($method){
    $method = strtolower(str_replace(" ", "", $method));
    $IntrumMapping = array(
        'cashondelivery'	=> 'CASH-ON-DELIVERY',
        'checkmo'			=> 'INVOICE',
        'banktransfer'		=> 'PRE-PAY',
        'ccsave'			=> 'CREDIT-CARD',
        'paypal'			=> 'E-PAYMENT',
        'bankwire'			=> 'INVOICE',
        'bill'			    => 'INVOICE',
        'invoice'			=> 'INVOICE',
        'invoicepayment'	=> 'INVOICE',
        'visa'	            => 'CREDIT-CARD',
        'maestro'	        => 'CREDIT-CARD',
        'mastercard'	    => 'CREDIT-CARD',
		'bezahlenperrechnung' => 'INVOICE'
    );

    if(strpos($method, 'paypal')!==false){
        if(array_key_exists('paypal', $IntrumMapping)){
            return $IntrumMapping['paypal'];
        }
    }
    if(strpos($method, 'invoice')!==false){
        return $IntrumMapping['invoice'];
    }
    if(strpos($method, 'maestro')!==false){
        return $IntrumMapping['maestro'];
    }
    if(strpos($method, 'mastercard')!==false){
        return $IntrumMapping['mastercard'];
    }
    if(strpos($method, 'visa')!==false){
        return $IntrumMapping['visa'];
    }
    if(strpos($method, 'rechnung')!==false){
        return $IntrumMapping['bezahlenperrechnung'];
    }
    if(array_key_exists($method, $IntrumMapping)){

        return $IntrumMapping[$method];
    }
    return $method;
}

function CreatePrestaShopRequest(CartCore $cart, CustomerCore $customer, CurrencyCore $currency) {

	global $cookie;
    $invoice_address = new Address($cart->id_address_invoice);
    $shipping_address = new Address($cart->id_address_delivery);
    $country = new Country($invoice_address->id_country);
    $country_shipping = new Country($shipping_address->id_country);
    $request = new IntrumRequest();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid($customer->id."_"));
    $request->setCustomerReference($customer->id);
    $request->setFirstName($invoice_address->firstname);
    $request->setLastName($invoice_address->lastname);
    if ($customer->id_gender != '0') {
        $request->setGender($customer->id_gender);
    }
    if (substr($customer->birthday, 0, 4) != '0000') {
        try {
            $request->setDateOfBirth($customer->birthday);
        } catch (Exception $e) {

        }
    }

    $request->setFirstLine(trim($invoice_address->address1.' '.$invoice_address->address2));
    $request->setCountryCode(strtoupper($country->iso_code));
    $request->setPostCode($invoice_address->postcode);
    $request->setTown($invoice_address->city);
    $request->setLanguage(Context::getContext()->language->iso_code);

    $request->setTelephonePrivate($invoice_address->phone);
    $request->setMobile($invoice_address->phone_mobile);
    $request->setEmail($customer->email);

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = 'NO';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $cart->getOrderTotal(true, Cart::BOTH);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $currency->iso_code;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = getClientIp();
    $request->setExtraInfo($extraInfo);

    if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '' && !empty($cookie->intrumId)) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $cookie->intrumId;
        $request->setExtraInfo($extraInfo);
    }

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = $shipping_address->firstname;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = $shipping_address->lastname;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = trim($shipping_address->address1.' '.$shipping_address->address2);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = '';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = strtoupper($country_shipping->iso_code);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $shipping_address->postcode;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = $shipping_address->city;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Intrum Prestashop module 1.5';
    $request->setExtraInfo($extraInfo);	

    return $request;

}


function CreatePrestaShopRequestAfterPaid(Cart $cart, OrderCore $order, Currency $currency) {
    $customer = new Customer($order->id_customer);
    $invoice_address = new Address($order->id_address_invoice);
    $shipping_address = new Address($order->id_address_delivery);
    $country = new Country($invoice_address->id_country);
    $country_shipping = new Country($shipping_address->id_country);
    $request = new IntrumRequest();
    $request->setClientId(Configuration::get("INTRUM_CLIENT_ID"));
    $request->setUserID(Configuration::get("INTRUM_USER_ID"));
    $request->setPassword(Configuration::get("INTRUM_PASSWORD"));
    $request->setVersion("1.00");
    try {
        $request->setRequestEmail(Configuration::get("INTRUM_TECH_EMAIL"));
    } catch (Exception $e) {

    }
    $request->setRequestId(uniqid($customer->id."_"));
    $request->setCustomerReference($customer->id);
    $request->setFirstName($invoice_address->firstname);
    $request->setLastName($invoice_address->lastname);
    if ($customer->id_gender != '0') {
        $request->setGender($customer->id_gender);
    }
    if (substr($customer->birthday, 0, 4) != '0000') {
        try {
            $request->setDateOfBirth($customer->birthday);
        } catch (Exception $e) {

        }
    }

    $request->setFirstLine(trim($invoice_address->address1.' '.$invoice_address->address2));
    $request->setCountryCode(strtoupper($country->iso_code));
    $request->setPostCode($invoice_address->postcode);
    $request->setTown($invoice_address->city);
    $request->setLanguage(Context::getContext()->language->iso_code);

    $request->setTelephonePrivate($invoice_address->phone);
    $request->setMobile($invoice_address->phone_mobile);
    $request->setEmail($customer->email);

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = 'YES';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $order->total_paid_tax_incl;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $currency->iso_code;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = getClientIp();
    $request->setExtraInfo($extraInfo);

    if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '' && !empty($cookie->intrumId)) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $cookie->intrumId;
        $request->setExtraInfo($extraInfo);
    }

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = $shipping_address->firstname;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = $shipping_address->lastname;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = trim($shipping_address->address1.' '.$shipping_address->address2);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = '';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = strtoupper($country_shipping->iso_code);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $shipping_address->postcode;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = $shipping_address->city;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERID';
    $extraInfo["Value"] = $order->reference;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'PAYMENTMETHOD';
    $extraInfo["Value"] = mapPaymentMethodToSpecs($order->payment);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Intrum Prestashop module 1.5';
    $request->setExtraInfo($extraInfo);	

    return $request;

}