<?php
require_once('../vendor/autoload.php');					// This is the dependancy autoloader managed via composer
$start = \metaclassing\Utility::microtimeTicks();		// get the current microtime for performance tracking
if( !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')	{
	header("HTTP/1.1 301 Moved Permanently");			// Enforce HTTPS for all traffic
	header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit();
}
header('Content-Type: application/json');				// All output from this point forward must be JSON
$RESPONSE = array();									// Response array we will convert to JSON
if ($_SERVER['REQUEST_METHOD'] != 'POST') {				// Handle non-post requests as an error
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request method not supported';
	exit(json_encode($RESPONSE));
}
if ( !isset($_POST['action']) || !$_POST['action'] ) {	// Handle missing actions as an error
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request action invalid';
	exit(json_encode($RESPONSE));
}
$REQUEST = $_POST;										// copy the POST superglobal so we can edit it
$ACTION = $_POST['action'];								// set our action var to posted action - do not reference $_POST after this line
unset($REQUEST['action']);								// remove the action from our request as we processed that key
foreach($REQUEST as $key => $value)
{
	unset($REQUEST[$key]);
	$REQUEST[strtolower($key)] = $value;
}
$DEBUG = 0;
if (isset($REQUEST['debug']) && $REQUEST['debug']) {	// IF we are asked to perform debugging
	$DEBUG = $REQUEST['debug'];							// set our internal debug flag
	unset($REQUEST['debug']);
	$RESPONSE['debug']['debuglevel'] = $DEBUG;						// set the debug flag for our response and application
	$RESPONSE['debug']['action'] = $ACTION;						// save the performed action as a response to the requester
	$RESPONSE['debug']['request'] = $REQUEST;					// save the remaining requested things for requester troubleshooting
}
$TRY = $ACTION . '.ps1';								// Look for our PS1 file to try and execute...
if(!file_exists($TRY)) {								// if the file doesn't exist, pop smoke
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request action not found';
	exit(json_encode($RESPONSE));
}
$PS1 = file_get_contents($TRY);							// Otherwise get the contents of our PS1 file
if ($DEBUG)
{
	$RESPONSE['debug']['filename'] = $TRY;
}
$REGEX = "/\[parameter\(mandatory=[$]true\)\]\s+\[\S+\][$](\w+)/i";

if(preg_match_all($REGEX,$PS1,$HITS)) {					// Look through any mandatory parameters in the PS1
	foreach($HITS[1] as $HIT) {							// and make sure each one is set in the request
		$PARAM = strtolower($HIT);								// [2] is param name, [1] is the data type (unused currently)
		if($DEBUG)
		{
			$RESPONSE['debug']['mandparams'][] = $PARAM;
		}
		if(!isset($REQUEST[$PARAM]) || !$REQUEST[$PARAM]) {
			$RESPONSE['success']= false;				// if the required parameter is not set, pop smoke
			$RESPONSE['error']	= 'Mandatory parameter ' . $PARAM . ' is missing or empty';
			exit(json_encode($RESPONSE));
		}
	}
}

$COMMAND = 'powershell.exe -noninteractive -file';		// Start building our commandline syntax
$COMMAND .= ' ./' . $TRY;								// appended chunks to the commandline must begin with space
foreach($REQUEST as $KEY => $VALUE) {					// now add all the parameters requested to the command in order provided
	if(is_array($VALUE)) {								// if its an array, we need to combine the elements into @(1,2,3) format
		$COMMAND .= ' -' . $KEY . ' @("' . implode('","',$VALUE) . '")';
	}else{												// otherwise simple command strings get appended
		$COMMAND .= ' -' . $KEY . ' "' . $VALUE . '"';
	}
}
if($DEBUG)
{
	$RESPONSE['debug']['command'] = $COMMAND;					// return the full command to the requester
}
$OUTPUT = shell_exec($COMMAND);							// run the actual powershell command
$RESPONSE['psresponse'] = json_decode($OUTPUT, true);		// try to decode the ps1 output as JSON
if (isset($RESPONSE['psresponse']['success'])) {
	$RESPONSE['success'] = $RESPONSE['psresponse']['success'];// if we get a success code from powershell, copy to our response
}
if (isset($RESPONSE['psresponse']['error'])) {
	$RESPONSE['error'] = $RESPONSE['psresponse']['error'];	// if we get a success code from powershell, copy to our response
}
if(json_last_error() !== JSON_ERROR_NONE) {				// If we cant decode the script output as JSON
	$RESPONSE['psresponse'] = trim($OUTPUT);				// return the raw text output instead
}
$end = \metaclassing\Utility::microtimeTicks();			// get the current microtime for performance tracking
$RESPONSE['time'] = $end - $start;						// calculate the total time we executed
exit(json_encode($RESPONSE));							// terminate and respond with json
