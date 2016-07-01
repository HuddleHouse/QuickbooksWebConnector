<?php

/**
 * Build a request to import customers already in QuickBooks into our application
 */
function _quickbooks_customer_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    // Iterator support (break the result set into small chunks)
    $attr_iteratorID = '';
    $attr_iterator = ' iterator="Start" ';
    if (empty($extra['iteratorID']))
    {
        // This is the first request in a new batch
        $last = _quickbooks_get_last_run($user, $action);
        _quickbooks_set_last_run($user, $action);			// Update the last run time to NOW()

        // Set the current run to $last
        _quickbooks_set_current_run($user, $action, $last);
    }
    else
    {
        // This is a continuation of a batch
        $attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
        $attr_iterator = ' iterator="Continue" ';

        $last = _quickbooks_get_current_run($user, $action);
    }

    // Build the request
    $xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<FromModifiedDate>' . $last . '</FromModifiedDate>
					<OwnerID>0</OwnerID>
				</CustomerQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

    return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_customer_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, null, QB_PRIORITY_CUSTOMER, array( 'iteratorID' => $idents['iteratorID'] ));
    }

    // This piece of the response from QuickBooks is now stored in $xml. You
    //	can process the qbXML response in $xml in any way you like. Save it to
    //	a file, stuff it in a database, parse it and stuff the records in a
    //	database, etc. etc. etc.
    //
    // The following example shows how to use the built-in XML parser to parse
    //	the response and stuff it into a database.

    // Import all of the records
    $errnum = 0;
    $errmsg = '';
    $Parser = new QuickBooks_XML_Parser($xml);
    if ($Doc = $Parser->parse($errnum, $errmsg))
    {
        $Root = $Doc->getRoot();
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerQueryRs');

        foreach ($List->children() as $Customer)
        {
            $arr = array(
                'list_id' => $Customer->getChildDataAt('CustomerRet ListID'),
                'time_created' => $Customer->getChildDataAt('CustomerRet time_created'),
                'time_modified' => $Customer->getChildDataAt('CustomerRet time_modified'),
                'Name' => $Customer->getChildDataAt('CustomerRet Name'),
                'FullName' => $Customer->getChildDataAt('CustomerRet FullName'),
                'FirstName' => $Customer->getChildDataAt('CustomerRet FirstName'),
                'MiddleName' => $Customer->getChildDataAt('CustomerRet MiddleName'),
                'LastName' => $Customer->getChildDataAt('CustomerRet LastName'),
                'Contact' => $Customer->getChildDataAt('CustomerRet Contact'),
                'ShipAddress_Addr1' => $Customer->getChildDataAt('CustomerRet ShipAddress Addr1'),
                'ShipAddress_Addr2' => $Customer->getChildDataAt('CustomerRet ShipAddress Addr2'),
                'ShipAddress_City' => $Customer->getChildDataAt('CustomerRet ShipAddress City'),
                'ShipAddress_State' => $Customer->getChildDataAt('CustomerRet ShipAddress State'),
                'ShipAddress_PostalCode' => $Customer->getChildDataAt('CustomerRet ShipAddress PostalCode'),
            );

            QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing customer ' . $arr['FullName'] . ': ' . print_r($arr, true));

            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            // Store the invoices in MySQL
            $mysqli->query("
				REPLACE INTO
					qb_customer
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));
        }
    }

    return true;
}