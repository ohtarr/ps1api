<?php
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

// If the action is missing or blank, piss off
if( !isset($_POST['action']) || !$_POST['action'] ) {
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

// Look for our PS1 file to try and execute...
$TRY = $_POST['action'] . '.ps1';
if(!file_exists($TRY)) {
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Request action not found';
	exit(json_encode($RESPONSE));
}

//ini_set('auto_detect_line_endings',true);
// Since it exists, get its contents
$PS1 = file_get_contents($TRY);

// and parse them for MANDATORY parameters
$REGEX = "/param\((.+)\)/misU";
if(preg_match($REGEX,$PS1,$HITS)) {
	$PARAMS = $HITS[1];
}else{
	$RESPONSE['success']= false;
	$RESPONSE['error']	= 'Could not identify requested action parameters';
	exit(json_encode($RESPONSE));
}

// Look through any mandatory parameters in the PS1 file and make sure they are set
$REGEX = "/\s+\[parameter\(\s+mandatory=.+?\)\]\s+\[(\S+)\]\s+\$(\w+)/msiU";
if(preg_match($REGEX,$PARAMS,$HITS)) {
	print_r($HITS);
	foreach($HITS as $HIT) {
		$PARAM = $HIT[2];		// [1] is the data type in case we ever want that...
		if(!isset($REQUEST[$PARAM]) || !$REQUEST[$PARAM]) {
			$RESPONSE['success']= false;
			$RESPONSE['error']	= 'Mandatory parameter ' . $PARAM . ' is missing or empty';
			exit(json_encode($RESPONSE));
		}
	}
}

// Start building our commandline thingy
$COMMAND = 'powershell.exe -noninteractive';
// appended chunks to the commandline must begin with space
$COMMAND .= ' ./' . $TRY;

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
