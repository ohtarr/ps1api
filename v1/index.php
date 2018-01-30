<?php
//List of valid ACTIONs that get translated into PS1 scripts... Not completely necessary but OK for now...
$ACTIONS = array(
					'dhcpstatus' => array(
										'script'	=> 'DHCPstatus.ps1',
										'required'	=> array(
																'scopeID',
															),
										),
					'dhcpadd' => array(
										'script'	=> 'DHCPScopeAdd.ps1',
										'required'	=> array(
																'scopeID',
																'startRange',
																'endRange',
																'subnetMask',
																'gatewayAddress',
																'scopeName',
																'scopeDescription',
															),
										),
					'dhcpremove' => array(
										'script'	=> 'DHCPScopeDelete.ps1',
										'required'	=> array(
																'scopeID',
															),
										),
					'serveradd' => array(
										'script'	=> 'DHCPstatus.ps1',
										'required'	=> array(
																'scopeID',
															),
										),
					'loopbacktest' => array(
										'script'	=> 'LoopBackTest.ps1',
										'required'	=> array(
																'dataCenter',
																'HostResource',
																'newVMHostName',
																'needCommVault',
																'ObjectDesc',
																'ObjectManager',
																'ObjectDescOU',																
															),
										),
					'serverou' => array(
										'script'	=> 'serverou.ps1',
										
										),
				);

// Enforce HTTPS for all traffic
if( !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')	{
	header("HTTP/1.1 301 Moved Permanently");
	header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit();
}

// All output from this point forward must be JSON
header('Content-Type: application/json');

// Response array we will convert to JSON
$RESPONSE = array();

// Handle non-post requests as an error
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request method not supported';
	exit(json_encode($RESPONSE));
}

// If the action supplied isn't in our approved list, piss off
if( !isset($_POST['action']) || !in_array($_POST['action'],array_keys($ACTIONS)) ) {
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request action invalid';
	exit(json_encode($RESPONSE));
}

// Setup some stuff before we continue
$REQUEST = $_POST;							// copy the POST superglobal so we can edit it
$ACTION = $_POST['action'];					// set our action var to posted action - do not reference $_POST after this line
//$RESPONSE['action'] = $ACTIONS[$ACTION];	// save the performed action as a response to the requester
unset($REQUEST['action']);					// remove the action from our request as we processed that key
$RESPONSE['request'] = $REQUEST;			// save the remaining requested things for requester troubleshooting

// Start building our commandline thingy
$COMMAND = 'powershell.exe';
// appended chunks to the commandline must begin with space
$COMMAND .= ' ./' . $ACTIONS[$ACTION]['script'];

// check that all the required parameters for the ps1 are included
foreach($ACTIONS[$ACTION]['required'] as $PARAM) {
	// If the required parameter is missing or evaluates to false, shit brix
	if(!isset($REQUEST[$PARAM]) || !$REQUEST[$PARAM]) {
		$RESPONSE['success']= false;
		$RESPONSE['error']	= "Missing required parameter {$PARAM}";
		exit(json_encode($RESPONSE));
	}
}

// now add all the parameters requested to the command in order provided
foreach($REQUEST as $KEY => $VALUE) {
	if(is_array($VALUE)) {
		// if its an array, we need to combine the elements into @(1,2,3) format
		$COMMAND .= ' -' . $KEY . ' @(' . implode(',',$VALUE) . ')';
	}else{
		// simple command strings get single quotes around them for safety
		$COMMAND .= ' -' . $KEY . ' ' . $VALUE;
	}
}

// return the command we executed to the requester for debugging purposes
$RESPONSE['command'] = $COMMAND;
// Run our shitty command
$OUTPUT = shell_exec($COMMAND);
// try to decode the ps1 output as JSON
$RESPONSE['response'] = json_decode($OUTPUT, true);
// If we cant decode the script output as JSON
if(json_last_error() !== JSON_ERROR_NONE) {
	// return the raw text output instead
	$RESPONSE['response'] = trim($OUTPUT);
}

// return output to the requester as a JSON formatted thingy
exit(json_encode($RESPONSE));
