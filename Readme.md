PS1API

This is a simple PHP api to allow execution of powershell scripts via restful api (POST) calls.

Simply send a POST with "action" as the name of the ps1 file that is in the same folder as index.php.  Also include any and all parameters that are required for that ps1 file to exectue.

Example:

[
	"action":"DHCPaddScope",
	"scopeid":"10.0.0.0",
	"mask":"255.255.255.0",
	"gw":"10.0.0.1"
]

The above post parameters will run the DHCPaddScope.ps1 file that you place in the folder with index.php with the parameters "scopeid", "mask", and "gw".