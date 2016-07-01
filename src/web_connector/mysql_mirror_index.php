<?php

/**
 * Example QuickBooks SOAP Server / Web Service
 *
 * This is an example Web Service which imports Invoices currently stored
 * within QuickBooks desktop editions and then stores those invoices in a MySQL
 * database. It communicates with QuickBooks via the QuickBooks Web Connector.
 *
 * If you have not already looked at the more basic docs/server.php,
 * you may want to consider looking at that example before you dive into this
 * example, as the requests and processing are a bit simpler and the
 * documentation a bit more verbose.
 *
 *
 * @author Keith Palmer <keith@consolibyte.com>
 *
 * @package QuickBooks
 * @subpackage Documentation
 */

// I always program in E_STRICT error mode...
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

// Support URL
if (!empty($_GET['support']))
{
	header('Location: http://www.consolibyte.com/');
	exit;
}

// We need to make sure the correct timezone is set, or some PHP installations will complain
if (function_exists('date_default_timezone_set'))
{
	// * MAKE SURE YOU SET THIS TO THE CORRECT TIMEZONE! *
	// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
	date_default_timezone_set('America/New_York');
}

require_once '../QuickBooks.php';
require_once './_quickbooks_errors.php';

$user = 'quickbooks';
$pass = 'password';
$dsn = 'mysqli://quick:quick@localhost/quick';
$mysqli = new mysqli("localhost", "quick", "quick", "quick");

define('QB_QUICKBOOKS_DSN', $dsn);
define('QB_PRIORITY_INVENTORYADJUSTMENT', 7);
define('QB_PRIORITY_INVENTORYSITE', 6);
define('QB_PRIORITY_ITEMSITES', 5);
define('QB_PRIORITY_PURCHASEORDER', 9);
define('QB_PRIORITY_ITEM', 3);
define('QB_PRIORITY_CUSTOMER', 11);
define('QB_PRIORITY_SALESORDER', 10);
define('QB_PRIORITY_INVOICE', 0);

define('QB_QUICKBOOKS_MAX_RETURNED', 400);
define('QB_QUICKBOOKS_CONFIG_LAST', 'last');
define('QB_QUICKBOOKS_CONFIG_CURR', 'curr');
define('QB_QUICKBOOKS_MAILTO', 'keith@consolibyte.com');


/**
 *
 * 1. add array with function names below.
 * 2. update login success hook
 * 3. create new file with request and response functions
 * 4. include said file below
 *
 */
$map = array(
	QUICKBOOKS_IMPORT_SALESRECEIPT => array( '_quickbooks_salesreceipt_import_request', '_quickbooks_salesreceipt_import_response' ),
	QUICKBOOKS_IMPORT_PURCHASEORDER => array( '_quickbooks_purchaseorder_import_request', '_quickbooks_purchaseorder_import_response' ),
	QUICKBOOKS_IMPORT_INVOICE => array( '_quickbooks_invoice_import_request', '_quickbooks_invoice_import_response' ),
	QUICKBOOKS_IMPORT_CUSTOMER => array( '_quickbooks_customer_import_request', '_quickbooks_customer_import_response' ),
	QUICKBOOKS_IMPORT_SALESORDER => array( '_quickbooks_salesorder_import_request', '_quickbooks_salesorder_import_response' ),
	QUICKBOOKS_IMPORT_ITEM => array( '_quickbooks_item_import_request', '_quickbooks_item_import_response' ),
    QUICKBOOKS_QUERY_INVENTORYSITE => array( '_quickbooks_inventory_site_import_request', '_quickbooks_inventory_site_import_response' ),
    QUICKBOOKS_QUERY_ITEMSITES => array( '_quickbooks_item_sites_import_request', '_quickbooks_item_sites_import_response' ),
    QUICKBOOKS_QUERY_INVENTORYADJUSTMENT => array( '_quickbooks_inventory_adjustment_import_request', '_quickbooks_inventory_adjustment_import_response' ),
	);

require_once './_quickbooks_customer_import.php';
require_once './_quickbooks_invoice_import.php';
require_once './_quickbooks_item_import.php';
require_once './_quickbooks_item_sites_import.php';
require_once './_quickbooks_login_success_hook.php';
require_once './_quickbooks_purchaseorder_import.php';
require_once './_quickbooks_salesorder_import.php';
require_once './_quickbooks_inventory_site_import.php';
require_once './_quickbooks_inventory_adjustment_import.php';
require_once './_quickbooks_response_parser.php';
require_once './_quickbooks_database_builder.php';


// Error handlers
$errmap = array(
	500 => '_quickbooks_error_e500_notfound', 			// Catch errors caused by searching for things not present in QuickBooks
	1 => '_quickbooks_error_e500_notfound',
	'*' => '_quickbooks_error_catchall', 				// Catch any other errors that might occur
	);

// An array of callback hooks
$hooks = array(
	QuickBooks_WebConnector_Handlers::HOOK_LOGINSUCCESS => '_quickbooks_hook_loginsuccess', 	// call this whenever a successful login occurs
	);

// Logging level
//$log_level = QUICKBOOKS_LOG_NORMAL;
//$log_level = QUICKBOOKS_LOG_VERBOSE;
//$log_level = QUICKBOOKS_LOG_DEBUG;				// Use this level until you're sure everything works!!!
$log_level = QUICKBOOKS_LOG_DEVELOP;

$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN;		// A pure-PHP SOAP server (no PHP ext/soap extension required, also makes debugging easier)
$soap_options = array();
$handler_options = array(		// See the comments in the QuickBooks/Server/Handlers.php file
	'deny_concurrent_logins' => false,
	);
$driver_options = array();
$callback_options = array();

// If we haven't done our one-time initialization yet, do it now!
if (!QuickBooks_Utilities::initialized($dsn))
{
	// Create the example tables
	$file = dirname(__FILE__) . '/example.sql';
	if (file_exists($file))
	{
		$contents = file_get_contents($file);
		foreach (explode(';', $contents) as $sql)
		{
			if (!trim($sql))
			{
				continue;
			}

			$mysqli->query($sql) or die(trigger_error($mysqli->error));
		}
	}
	else
	{
		die('Could not locate "./example.sql" to create the demo SQL schema!');
	}

	// Create the database tables
	QuickBooks_Utilities::initialize($dsn);

	// Add the default authentication username/password
	QuickBooks_Utilities::createUser($dsn, $user, $pass);
}

// Initialize the queue
QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);

// Create a new server and tell it to handle the requests
$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, $soap_options, $handler_options, $driver_options, $callback_options);
$response = $Server->handle(true, true);




/**
 * Get the last date/time the QuickBooks sync ran
 *
 * @param string $user		The web connector username
 * @return string			A date/time in this format: "yyyy-mm-dd hh:ii:ss"
 */
function _quickbooks_get_last_run($user, $action)
{
	$type = null;
	$opts = null;
	return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $type, $opts);
}

/**
 * Set the last date/time the QuickBooks sync ran to NOW
 *
 * @param string $user
 * @return boolean
 */
function _quickbooks_set_last_run($user, $action, $force = null)
{
	$value = date('Y-m-d') . 'T' . date('H:i:s');

	if ($force)
	{
		$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
	}

	return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $value);
}

/**
 *
 *
 */
function _quickbooks_get_current_run($user, $action)
{
	$type = null;
	$opts = null;
	return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $type, $opts);
}

/**
 *
 *
 */
function _quickbooks_set_current_run($user, $action, $force = null)
{
	$value = date('Y-m-d') . 'T' . date('H:i:s');

	if ($force)
	{
		$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
	}

	return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $value);
}

