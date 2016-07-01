<?php


/**
 * Build a request to import sales orders already in QuickBooks into our application
 */
function _quickbooks_salesorder_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
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
		<?qbxml version="12.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<SalesOrderQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</SalesOrderQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

    return $xml;
}


/**
 * Handle a response from QuickBooks
 */
function _quickbooks_salesorder_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_SALESORDER, null, QB_PRIORITY_SALESORDER, array( 'iteratorID' => $idents['iteratorID'] ));
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
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/SalesOrderQueryRs');

        foreach ($List->children() as $SalesOrder)
        {
            $arr = array(
                'TxnID' => $SalesOrder->getChildDataAt('SalesOrderRet TxnID'),
                'time_created' => $SalesOrder->getChildDataAt('SalesOrderRet time_created'),
                'time_modified' => $SalesOrder->getChildDataAt('SalesOrderRet time_modified'),
                'RefNumber' => $SalesOrder->getChildDataAt('SalesOrderRet RefNumber'),
                'Customer_list_id' => $SalesOrder->getChildDataAt('SalesOrderRet CustomerRef ListID'),
                'Customer_FullName' => $SalesOrder->getChildDataAt('SalesOrderRet CustomerRef FullName'),
                'ShipAddress_Addr1' => $SalesOrder->getChildDataAt('SalesOrderRet ShipAddress Addr1'),
                'ShipAddress_Addr2' => $SalesOrder->getChildDataAt('SalesOrderRet ShipAddress Addr2'),
                'ShipAddress_City' => $SalesOrder->getChildDataAt('SalesOrderRet ShipAddress City'),
                'ShipAddress_State' => $SalesOrder->getChildDataAt('SalesOrderRet ShipAddress State'),
                'ShipAddress_PostalCode' => $SalesOrder->getChildDataAt('SalesOrderRet ShipAddress PostalCode'),
                'BalanceRemaining' => $SalesOrder->getChildDataAt('SalesOrderRet BalanceRemaining'),
            );

            QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing sales order #' . $arr['RefNumber'] . ': ' . print_r($arr, true));

            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            // Store the invoices in MySQL
            $mysqli->query("
				REPLACE INTO
					qb_salesorder
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));

            // Remove any old line items
            $mysqli->query("DELETE FROM qb_salesorder_lineitem WHERE TxnID = '" . $mysqli->real_escape_string($arr['TxnID']) . "' ") or die(trigger_error($mysqli->error));

            // Process the line items
            foreach ($SalesOrder->children() as $Child)
            {
                if ($Child->name() == 'SalesOrderLineRet')
                {
                    $SalesOrderLine = $Child;

                    $lineitem = array(
                        'TxnID' => $arr['TxnID'],
                        'TxnLineID' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet TxnLineID'),
                        'Item_list_id' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet ItemRef ListID'),
                        'Item_FullName' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet ItemRef FullName'),
                        'Descrip' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet Desc'),
                        'Quantity' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet Quantity'),
                        'Rate' => $SalesOrderLine->getChildDataAt('SalesOrderLineRet Rate'),
                    );

                    foreach ($lineitem as $key => $value)
                    {
                        $lineitem[$key] = $mysqli->real_escape_string($value);
                    }

                    // Store the lineitems in MySQL
                    $mysqli->query("
						INSERT INTO
							qb_salesorder_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ") or die(trigger_error($mysqli->error));
                }
            }
        }
    }

    return true;
}