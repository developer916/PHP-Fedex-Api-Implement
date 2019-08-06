<?php
// Copyright 2009, FedEx Corporation. All rights reserved.
// Version 9.0.0

$RecipientEmail = $_POST['StreetLines'];
$ShipperEmail = $_POST['ShipperEmail'];

$RecipientAddress = array(
		'Address' => array(
			'StreetLines' => array($_POST['StreetLines']),
            'City' => $_POST['City'],
            'StateOrProvinceCode' => $_POST['StateOrProvinceCode'],
            'PostalCode' => $_POST['PostalCode'],
            'CountryCode' => $_POST['CountryCode'],
            'Residential' => false)
		);
		
$ShipperAddress = array('StreetLines' => array($_POST['ShipperStreetLines']),
                                          'City' => $_POST['ShipperCity'],
                                          'StateOrProvinceCode' => $_POST['ShipperStateOrProvinceCode'],
                                          'PostalCode' => $_POST['ShipperPostalCode'],
                                          'CountryCode' => $_POST['ShipperCountryCode']);
										  		
	
$WeightDetails = array('0' => array('Weight' => array('Value' => $_POST['Weight'],
                                                                                    'Units' => $_POST['WeightUnits']),
                                                                                    'Dimensions' => array('Length' => $_POST['Length'],
                                                                                        'Width' => $_POST['Width'],
                                                                                        'Height' => $_POST['Height'],
                                                                                        'Units' => $_POST['Units'])));		
//echo "<pre>";
//print_r($WeightDetails);			
require_once('fedex-common.php');

$newline = "";
//The WSDL is not included with the sample code.
//Please include and reference in $path_to_wsdl variable.
$path_to_wsdl = "RateService_v9.wsdl";

ini_set("soap.wsdl_cache_enabled", "0");
 
$client = new SoapClient($path_to_wsdl, array('trace' => 1)); // Refer to http://us3.php.net/manual/en/ref.soap.php for more information

$request['WebAuthenticationDetail'] = array('UserCredential' =>
                                      array('Key' => getProperty('key'), 'Password' => getProperty('password'))); 
$request['ClientDetail'] = array('AccountNumber' => getProperty('shipaccount'), 'MeterNumber' => getProperty('meter'));
$request['TransactionDetail'] = array('CustomerTransactionId' => ' *** Rate Request v9 using PHP ***');
$request['Version'] = array('ServiceId' => 'crs', 'Major' => '9', 'Intermediate' => '0', 'Minor' => '0');
$request['ReturnTransitAndCommit'] = true;
$request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; // valid values REGULAR_PICKUP, REQUEST_COURIER, ...
$request['RequestedShipment']['ShipTimestamp'] = date('c');
//$request['RequestedShipment']['ServiceType'] = 'INTERNATIONAL_ECONOMY'; // valid values STANDARD_OVERNIGHT, PRIORITY_OVERNIGHT, FEDEX_GROUND, ...
$request['RequestedShipment']['PackagingType'] = $_POST['package']; // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
$request['RequestedShipment']['TotalInsuredValue']=array('Ammount'=>1000,'Currency'=>'USD');
$request['RequestedShipment']['Shipper'] = array('Address' => $ShipperAddress); // getProperty('address1')
$request['RequestedShipment']['Recipient'] = $RecipientAddress;
$request['RequestedShipment']['ShippingChargesPayment'] = array('PaymentType' => 'SENDER',
                                                        'Payor' => array('AccountNumber' => getProperty('billaccount'),
                                                                     'CountryCode' => 'US'));
$request['RequestedShipment']['RateRequestTypes'] = 'ACCOUNT'; 
$request['RequestedShipment']['RateRequestTypes'] = 'LIST'; 
$request['RequestedShipment']['PackageCount'] = '2';
$request['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';  //  Or PACKAGE_SUMMARY
$request['RequestedShipment']['RequestedPackageLineItems'] = $WeightDetails; 
																					/*	,
                                                                   '1' => array('Weight' => array('Value' => 5.0,
                                                                                    'Units' => 'LB'),
                                                                                    'Dimensions' => array('Length' => 20,
                                                                                        'Width' => 20,
                                                                                        'Height' => 10,
                                                                                        'Units' => 'IN')));*/
try 
{
	if(setEndpoint('changeEndpoint'))
	{
		$newLocation = $client->__setLocation(setEndpoint('endpoint'));
	}
	
	$response = $client ->getRates($request);
       
    if ($response -> HighestSeverity != 'FAILURE' && $response -> HighestSeverity != 'ERROR')
    {  	
    	$rateReply = $response -> RateReplyDetails;		
	echo "<pre>";
	
		
    	echo '<table border="1">';
        echo '<tr><td>Service Type</td><td>Amount</td><td>Delivery Date</td></tr><tr>';
    	$serviceType = '<td>'.$rateReply -> ServiceType . '</td>';
        $amount = '<td>$' . number_format($rateReply->RatedShipmentDetails[0]->ShipmentRateDetail->TotalNetCharge->Amount,2,".",",") . '</td>';
        if(array_key_exists('DeliveryTimestamp',$rateReply)){
        	$deliveryDate= '<td>' . $rateReply->DeliveryTimestamp . '</td>';
        }else if(array_key_exists('TransitTime',$rateReply)){
        	$deliveryDate= '<td>' . $rateReply->TransitTime . '</td>';
        }else {
        	$deliveryDate='';
        }
        echo $serviceType . $amount. $deliveryDate;
        echo '</tr>';
        echo '</table>';
		
        $data = array();
		$data['status'] = 'Success';
		$data['Service_Type'] = $rateReply -> ServiceType;
		$data['Amount'] = number_format($rateReply->RatedShipmentDetails[0]->ShipmentRateDetail->TotalNetCharge->Amount,2,".",",");
		$data['Delivery_Date'] = $rateReply->TransitTime;
		echo json_encode($data);
     //   printSuccess($client, $response);
    }
    else
    {
		$data = array();
		$data['status'] = 'Error';
		echo json_encode($data);
       // printError($client, $response);
    } 
    
  //  writeToLog($client);    // Write to log file   

} catch (SoapFault $exception) {
  // printFault($exception, $client);       
		$data['status'] = 'Error';
		echo json_encode($data);   
}

?>