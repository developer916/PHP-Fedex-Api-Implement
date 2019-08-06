<?php
// Copyright 2009, FedEx Corporation. All rights reserved.
// Version 12.0.0

require_once('fedex-common.php');

//The WSDL is not included with the sample code.
//Please include and reference in $path_to_wsdl variable.
$path_to_wsdl = "OpenShipService_v7.wsdl";

define('SHIP_LABEL', 'shiplabel.pdf');  // PNG label file. Change to file-extension .pdf for creating a PDF label (e.g. shiplabel.pdf)

ini_set("soap.wsdl_cache_enabled", "0");

$client = new SoapClient($path_to_wsdl, array('trace' => 1)); // Refer to http://us3.php.net/manual/en/ref.soap.php for more information


try{
	if(setEndpoint('changeEndpoint')){
		$newLocation = $client->__setLocation(setEndpoint('endpoint'));
	}
	
	/*
		To OpenShip methods are being ran in the following order:
			CreateOpenShipment
			ModifyOpenShipment
			AddPackagesToOpenShipment
			ModifyPackageInOpenShipment
			ValidateOpenShipment
			ConfirmOpenShipment
		If any of these methods is not successful the process stops.  If the OpenShipment is created the
		DeleteOpenShipment method is run.	
	*/
	$index='';
	$responseCreateOpenShipment = $client->createOpenShipment(buildCreateOpenShipmentRequest()); // FedEx web service invocation
	if(isSuccess($client, $responseCreateOpenShipment)){
		processCreateOpenShipmentResponseSuccess($client, $responseCreateOpenShipment);
		$responseModifyOpenShipment = $client->modifyOpenShipment(buildModifyOpenShipmentRequest($index));
		if(isSuccess($client, $responseModifyOpenShipment)){
			processModifyOpenShipmentSuccess($client, $responseModifyOpenShipment);
			$responseAddPackagesToOpenShipment = $client->addPackagesToOpenShipment(buildAddPackagesToOpenShipmentRequest($index));
			if(isSuccess($client, $responseAddPackagesToOpenShipment)){
				processAddPackageToOpenShipmentResponseSuccess($client, $responseAddPackagesToOpenShipment);
				$responseModifyPackageInOpenShipment= $client->modifyPackageInOpenShipment(
					buildModifyPackageInOpenShipmentRequest($index, 
					$responseAddPackagesToOpenShipment)); //need tracking number to modify package
					if(isSuccess($client, $responseModifyPackageInOpenShipment)){
						processModifyPackageInOpenShipmentSuccess($client, $responseModifyPackageInOpenShipment);
						$responseValidateOpenShipment = $client->validateOpenShipment(buildValidateOpenShipmentRequest($index));
						if(isSuccess($client, $responseValidateOpenShipment)){
							processValidateOpenShipmentSuccess($client, $responseValidateOpenShipment);
							$responseConfirmOpenShipment = $client->confirmOpenShipment(buildConfirmOpenShipmentRequest($index));
							if(isSuccess($client, $responseConfirmOpenShipment)){
								processConfirmOpenShipmentSuccess($client, $responseConfirmOpenShipment);
							}
							else{
								processConfirmOpenShipmentFailure($client, $responseConfirmOpenShipment);
								deleteOpenShipment($client, $index);
							}
						}else{
							processValidateOpenShipmentFailure($client, $responseValidateOpenShipment);
							deleteOpenShipment($client, $index);
						}
					}else{
						processModifyPackageInOpenShipmentFailure($client, $responseModifyPackageInOpenShipment);
						deleteOpenShipment($client, $index);
					}
			}else{
				processAddPackageToOpenShipmentResponseFailure($client, $responseAddPackagesToOpenShipment);
				deleteOpenShipment($client, $index);
			}
		}else{
			processModifyOpenShipmentSuccess($client, $responseModifyOpenShipment);
			deleteOpenShipment($client, $index);
		}
	}else{
		processCreateOpenShipmentResponseFailure($client, $responseCreateOpenShipment);
		deleteOpenShipment($client, $index);
	}

} catch (SoapFault $exception) {
    printFault($exception, $client);
}



function isSuccess($client, $response){
	if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR'){
		return true;
	}else return false;
}
//This function creates the portion of OpenShip request common to all methods.
function buildTransactionDetail(){
	$request=array();
	$request['WebAuthenticationDetail'] = array(
		'UserCredential' =>array(
			'Key' => getProperty('key'), 
			'Password' => getProperty('password')
		)
	);
	$request['ClientDetail'] = array(
		'AccountNumber' => getProperty('shipaccount'), 
		'MeterNumber' => getProperty('meter')
	);
	$request['TransactionDetail'] = array('CustomerTransactionId' => '*** OpenShip Request using PHP ***');
	$request['Version'] = array(
		'ServiceId' => 'ship', 
		'Major' => '7', 
		'Intermediate' => '0', 
		'Minor' => '0'
	);
	return $request;
}
function deleteOpenShipment($client, $index){
	$responseDeleteOpenShipment = $client->deleteOpenShipment(buildDeleteOpenShipmentRequest($index));
	if(isSuccess($client, $responseDeleteOpenShipment)){
		processDeleteOpenShipmentSuccess($client, $responseDeleteOpenShipment);
	}else{
		processDeleteOpenShipmentFailure($client, $responseDeleteOpenShipment);
	}
}
function buildCreateOpenShipmentRequest(){
	$request=buildTransactionDetail();
	$request['RequestedShipment'] = array(
		'ShipTimestamp' => date('c'),
		'DropoffType' => 'REGULAR_PICKUP', // valid values REGULAR_PICKUP, REQUEST_COURIER, DROP_BOX, BUSINESS_SERVICE_CENTER and STATION
		'ServiceType' => 'PRIORITY_OVERNIGHT', // valid values STANDARD_OVERNIGHT, PRIORITY_OVERNIGHT, FEDEX_GROUND, ...
		'PackagingType' => 'YOUR_PACKAGING', // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
		'Shipper' => addShipper(),
		'Recipient' => addRecipient(),
		'ShippingChargesPayment' => addShippingChargesPayment(),
		'LabelSpecification' => addLabelSpecification(),
		'RateRequestTypes' => array('ACCOUNT'), // valid values ACCOUNT and LIST    
		'PackageCount' => 1,
		'RequestedPackageLineItems' => array(
			'0' => addPackageLineItem1()
		)
	);
	return $request;
}
function processCreateOpenShipmentResponseSuccess($client, $response){
	echo "Create OpenShipment was successful.<br>\n";
	printString($response->JobId, "Job Id");
	global $index;
	$index=$response->Index;
	printString($index, "Index");
	printString($response->CompletedShipmentDetail->MasterTrackingId->TrackingNumber, "Master Tracking Id");
	printOpenShipSuccess($client, $response);
}
function processCreateOpenShipmentResponseFailure($client, $response){
	echo "Create OpenShipment was not successful.<br>\n";
	echo "No other transactions will be processed.<br>\n";
	printOpenShipError($client, $response);
}
function buildModifyOpenShipmentRequest($index){
	$request=buildTransactionDetail();
	$request['Index'] = $index;
	$request['RequestedShipment'] = array(
		'ShipTimestamp' => date('c'),
		'DropoffType' => 'REGULAR_PICKUP', // valid values REGULAR_PICKUP, REQUEST_COURIER, DROP_BOX, BUSINESS_SERVICE_CENTER and STATION
		'ServiceType' => 'PRIORITY_OVERNIGHT', // valid values STANDARD_OVERNIGHT, PRIORITY_OVERNIGHT, FEDEX_GROUND, ...
		'PackagingType' => 'YOUR_PACKAGING', // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
		'Shipper' => addShipper(),
		'Recipient' => addModifiedRecipient(),
		'ShippingChargesPayment' => addShippingChargesPayment(),
		//'CustomsClearanceDetail' => addCustomClearanceDetail(), //used for international shipments
		'LabelSpecification' => addLabelSpecification(),
		'RateRequestTypes' => array('ACCOUNT'), // valid values ACCOUNT and LIST    
		'PackageCount' => 1,
	);
	return $request;
}
function processModifyOpenShipmentSuccess($client, $response){
	echo "Modify OpenShipment was successful.<br>\n";
	printString($response->JobId, "Job Id");
	printOpenShipSuccess($client, $response);
}
function processModifyOpenShipmentFailure($client, $response){
	echo "Modify OpenShipment was not successful.<br>\n";
	printOpenShipError($client, $response);
}
function buildAddPackagesToOpenShipmentRequest($index){
	$request=buildTransactionDetail();
	$request['Index'] = $index;
	$request['RequestedPackageLineItems'] = array(
		'0' => addPackageLineItem1()
	);
	return $request;
}
function processAddPackageToOpenShipmentResponseSuccess($client, $response){
	echo "Adding package to OpenShipment was successful.<br>\n";
	printString($response->JobId, "Job Id");
	printString($response->CompletedShipmentDetail->CompletedPackageDetails->SequenceNumber, "Package Sequence Number");
	printString($response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->TrackingNumber, "Tracking Number");
	printOpenShipSuccess($client, $response);
}
function processAddPackageToOpenShipmentResponseFailure($client, $response){
	echo "Adding package to OpenShipment was not successful.<br>\n";
	printOpenShipError($client, $response);
}
function buildValidateOpenShipmentRequest($index){
	$request=buildTransactionDetail();
	$request['Index'] = $index;
	return $request;
}
function processValidateOpenShipmentSuccess($client, $response){
	echo "Validate OpenShipment was successful.<br>\n";
	printOpenShipSuccess($client, $response);
}
function processValidateOpenShipmentFailure($client, $response){
	echo "Validate OpenShipment was not successful.<br>\n";
	//printOpenShipFailure($client, $response);
	printOpenShipError($client, $response);
}
function buildConfirmOpenShipmentRequest($index){
	$request=buildTransactionDetail();
	$request['Index'] = $index;	
	return $request;
}
function processConfirmOpenShipmentSuccess($client, $response){
	echo "Confirm OpenShipment was successful.<br>\n";
	printString($response->JobId, "Job Id");
	printOpenShipSuccess($client, $response);
	printAllLabels($response);
}
function processConfirmOpenShipmentFailure($client, $response){
	echo "Confirm OpenShipment was not successful.<br>\n";
	printOpenShipError($client, $response);
}
function buildDeleteOpenShipmentRequest($index){
	$request=buildTransactionDetail();
	$request['Index'] = $index;	
	return $request;
}
function processDeleteOpenShipmentSuccess($client, $response){
	echo "Deleting OpenShipment was successful.<br>\n";
	printOpenShipSuccess($client, $response);
}
function processDeleteOpenShipmentFailure($client, $response){
	echo "Deleting OpenShipment was not successful.<br>\n";
	//printOpenShipFailure($client, $response);
	printOpenShipError($client, $response);
}
function buildModifyPackageInOpenShipmentRequest($index, $response){
	$request=buildTransactionDetail();
	$request['Index'] = $index;
	$request['TrackingId'] = array(
		'TrackingIdType' => $response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->TrackingIdType,
		'FormId' => $response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->FormId,
		'TrackingNumber' => $response->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds->TrackingNumber);
	$request['RequestedPackageLineItem'] = addPackageLineItem1();
	return $request;
}
function processModifyPackageInOpenShipmentSuccess($client, $response){
	echo "Modifying OpenShipment Package was successful.<br>\n";
	printString($response->JobId, "Job Id");
	printOpenShipSuccess($client, $response);
}
function processModifyPackageInOpenShipmentFailure($client, $response){
	echo "Modifying OpenShipment Package was successful.<br>\n";
	//printOpenShipFailure($client, $response);
	printOpenShipError($client, $response);
}
function addShipper(){
	$shipper = array(
		'Contact' => array(
			'PersonName' => 'Sender Name',
			'CompanyName' => 'Sender Company Name',
			'PhoneNumber' => '0805522713'
		),
		'Address' => array(
			'StreetLines' => 'Address Line 1',
			'City' => 'Austin',
			'StateOrProvinceCode' => 'TX',
			'PostalCode' => '73301',
			'CountryCode' => 'US'
		)
	);
	return $shipper;
}
function addRecipient(){
	$recipient = array(
		'Contact' => array(
			'PersonName' => 'Recipient Name',
			'CompanyName' => 'Recipient Company Name',
			'PhoneNumber' => '1234567890'
		),
		'Address' => array(
			'StreetLines' => 'Address Line 1',
			'City' => 'Windsor',
			'StateOrProvinceCode' => 'CT',
			'PostalCode' => '06006',
			'CountryCode' => 'US',
			'Residential' => false
		)
	);
	return $recipient;	                                    
}
function addModifiedRecipient(){
	$modifiedRecipient = array(
		'Contact' => array(
			'PersonName' => 'Modified Recipient Name',
			'CompanyName' => 'Modified Recipient Company Name',
			'PhoneNumber' => '1234567890'
		),
		'Address' => array(
			'StreetLines' => 'Modified Address Line 1',
			'City' => 'Windsor',
			'StateOrProvinceCode' => 'CT',
			'PostalCode' => '06006',
			'CountryCode' => 'US',
			'Residential' => false			
		)
	);
	return $modifiedRecipient;
}
function addShippingChargesPayment(){
	$shippingChargesPayment = array(
		'PaymentType' => 'SENDER',
        'Payor' => array(
			'ResponsibleParty' => array(
				'AccountNumber' => getProperty('billaccount'),
				'Address' => array('CountryCode' => 'US')
			)
		)
	);
	return $shippingChargesPayment;
}
function addLabelSpecification(){
	$labelSpecification = array(
		'LabelFormatType' => 'COMMON2D', // valid values COMMON2D, LABEL_DATA_ONLY
		'ImageType' => 'PDF',  // valid values DPL, EPL2, PDF, ZPLII and PNG
		'LabelStockType' => 'PAPER_4X8'
	);
	return $labelSpecification;
}
function addSpecialServices(){
	$specialServices = array(
		'SpecialServiceTypes' => array('COD'),
		'CodDetail' => array(
			'CodCollectionAmount' => array(
				'Currency' => 'USD', 
				'Amount' => 80
			),
			'CollectionType' => 'ANY', // ANY, GUARANTEED_FUNDS
		)
	);
	return $specialServices; 
}
function addCustomClearanceDetail(){
	$customerClearanceDetail = array(
		'DutiesPayment' => array(
			'PaymentType' => 'SENDER', // valid values RECIPIENT, SENDER and THIRD_PARTY
			'Payor' => array(
				'ResponsibleParty' => array(
					'AccountNumber' => getProperty('dutyaccount'),
					'Contact' => null,
					'Address' => array('CountryCode' => 'US')
				)
			)
		),
		'DocumentContent' => 'NON_DOCUMENTS',                                                                                            
		'CustomsValue' => array(
			'Currency' => 'USD', 
			'Amount' => 400.0
		),
		'Commodities' => array(
			'0' => array(
				'NumberOfPieces' => 1,
				'Description' => 'Books',
				'CountryOfManufacture' => 'US',
				'Weight' => array(
					'Units' => 'LB', 
					'Value' => 1.0
				),
				'Quantity' => 4,
				'QuantityUnits' => 'EA',
				'UnitPrice' => array(
					'Currency' => 'USD', 
					'Amount' => 100.000000
				),
				'CustomsValue' => array(
					'Currency' => 'USD', 
					'Amount' => 400.000000
				)
			)
		),
		'ExportDetail' => array(
			'B13AFilingOption' => 'NOT_REQUIRED'
		)
	);
	return $customerClearanceDetail;
}
function addPackageLineItem1(){
	$packageLineItem = array(
		'SequenceNumber'=>1,
		'GroupPackageCount'=>1,
		'Weight' => array(
			//'Value' => 50.5,
			'Value' => 35,
			'Units' => 'LB'
		),
		'Dimensions' => array(
			'Length' => 108,
			'Width' => 5,
			'Height' => 5,
			'Units' => 'IN'
		),
		'CustomerReferences' => array(
			'0' => array(
				'CustomerReferenceType' => 'CUSTOMER_REFERENCE', 
				'Value' => 'CR1234'
			), // valid values CUSTOMER_REFERENCE, INVOICE_NUMBER, P_O_NUMBER and SHIPMENT_INTEGRITY
			'1' => array(
				'CustomerReferenceType' => 'INVOICE_NUMBER', 
				'Value' => 'IV1234'
			),
			'2' => array(
				'CustomerReferenceType' => 'P_O_NUMBER', 
				'Value' => 'PO1234'
			)
		)
	);
	return $packageLineItem;
}
function printAllLabels($response){
	$packageDetails=$response->CompletedShipmentDetail->CompletedPackageDetails;
	if(is_array($packageDetails)){
		foreach($packageDetails as $packageDetail){
			printLabel($packageDetail);
		}
	}else if(is_object($packageDetails)){
		printLabel($packageDetails);
	}
}
function printLabel($packageDetail){
	$labelName = $packageDetail->TrackingIds->TrackingNumber . SHIP_LABEL;
	$fp = fopen($labelName, 'wb');   
	fwrite($fp, ($packageDetail->Label->Parts->Image));
	fclose($fp);
	echo 'Label <a href="./'.$labelName.'">'.$labelName."</a> was generated.<br/>\n"; 
}
function printString($var, $description){
	if(!is_object($var)&&!is_array($var)){
		echo $description . ": " . $var . "<br/>\n";
	}
}
function printOpenShipSuccess($client, $response) {
    printRequestResponse($client);
	writeToLog($client);    // Write to log file
}
function printOpenShipError($client, $response){
	printNotifications($response -> Notifications);
    printRequestResponse($client, $response);
	writeToLog($client);    // Write to log file
}
?>