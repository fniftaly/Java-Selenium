#!/usr/bin/php
<?php
// Before we ever get started on sending any messages make sure we're not already running
// We might want to consider this, especially if we hang on a send...
//exec('ps -aef | grep messagesender.php | grep -v grep', $running);
//if (count($running) > 1) {
//	die("The message sender process is running already and cannot be started again.\n\nProcess information:\n{$running[0]}");
//}

// Make sure we have enough memory available to this script
ini_set('memory_limit', '512M');

// Set the queue limit
$queuelimit = 200; // This will be better served coming from the setting model

// Path to the logs
$logpath = realpath(dirname(__FILE__)) . '/log/';

// Path to our application
$apppath = realpath(dirname(__FILE__) . '/..');

// Add the application path to the include path
set_include_path(get_include_path() . PATH_SEPARATOR . $apppath);

// Log file for today
$logfile = $logpath . date('Ymd') . '-messagesender';

// If there is no log for today, create it
if (!file_exists($logfile)) {
	touch($logfile);
}

// Get the Zend Framework loader setup
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

// Setup routing of model autoloaders
$loader = new Zend_Loader_Autoloader_Resource(array(
    'basePath'  => $apppath . '/application/',
    'namespace' => 'Application',
));

// Name, path, namepsace
$loader->addResourceType('model', 'models', 'Model');
       
// Get our config file
$config = new Zend_Config_Ini($apppath . '/application/configs/application.ini');
Zend_Registry::set('config', $config->production); // Because our models need it this way

// Get the messages model
$message = new Application_Model_Message();

// Set up our current timestamp
$timestamp = date('Y-m-d H:i:s');

// Log the start of this read
logWrite("####################################");
logWrite("Begin batch send: $timestamp");
logWrite("------------------------------------");

// Get the queue and counts
$queue = $message->getQueue();
$queueCount = count($queue);
$sendCount = 0;

// Log our counts
logWrite("Message Queue Count: $queueCount\nBegin send loop:");
//logWrite("")
if (is_array($queue)) {
	if (!empty($queue)) {
		foreach ($queue as $id => $message) {
			if ($message instanceof Application_Model_Message) {
				$log = "Message ID $id status: ";
				if ($message->send()) {
					$sendCount++;
					$log .= 'SENT';
					//exit;
				} else {
					$log .= 'FAILED - ' . $message->error;
				}
				
				logWrite($log);
			}
		}
	} else {
		logWrite("No messages to send");
	}
} else {
	logWrite("Message queue was not properly fetched");
}
logWrite("Messages Sent: $sendCount");

// Set up our current timestamp for closing out
$timestamp = date('Y-m-d H:i:s');

// Log the end of this send
logWrite("\n---------------------");
logWrite("Process complete\nEnd message send: $timestamp");
logWrite("####################################\n");

return 0;

/**
 * Simply writes a log message line to the log file
 * 
 * @param string $msg The message to write
 */
function logWrite($msg) {
	global $logfile;
	$fh = fopen($logfile, 'a');
	fwrite($fh, "$msg\n");
	fclose($fh);
}