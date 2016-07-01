<?php

/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_invoice_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
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
				<InvoiceQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</InvoiceQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

    return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_invoice_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, null, QB_PRIORITY_INVOICE, array( 'iteratorID' => $idents['iteratorID'] ));
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
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InvoiceQueryRs');

        foreach ($List->children() as $Invoice)
        {
            $arr = array(
                'TxnID' => $Invoice->getChildDataAt('InvoiceRet TxnID'),
                'time_created' => $Invoice->getChildDataAt('InvoiceRet time_created'),
                'time_modified' => $Invoice->getChildDataAt('InvoiceRet time_modified'),
                'RefNumber' => $Invoice->getChildDataAt('InvoiceRet RefNumber'),
                'Customer_list_id' => $Invoice->getChildDataAt('InvoiceRet CustomerRef ListID'),
                'Customer_FullName' => $Invoice->getChildDataAt('InvoiceRet CustomerRef FullName'),
                'ShipAddress_Addr1' => $Invoice->getChildDataAt('InvoiceRet ShipAddress Addr1'),
                'ShipAddress_Addr2' => $Invoice->getChildDataAt('InvoiceRet ShipAddress Addr2'),
                'ShipAddress_City' => $Invoice->getChildDataAt('InvoiceRet ShipAddress City'),
                'ShipAddress_State' => $Invoice->getChildDataAt('InvoiceRet ShipAddress State'),
                'ShipAddress_PostalCode' => $Invoice->getChildDataAt('InvoiceRet ShipAddress PostalCode'),
                'BalanceRemaining' => $Invoice->getChildDataAt('InvoiceRet BalanceRemaining'),
            );

            QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing invoice #' . $arr['RefNumber'] . ': ' . print_r($arr, true));

            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            // Store the invoices in MySQL
            $mysqli->query("
				REPLACE INTO
					qb_invoice
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));

            // Remove any old line items
            $mysqli->query("DELETE FROM qb_invoice_lineitem WHERE TxnID = '" . $mysqli->real_escape_string($arr['TxnID']) . "' ") or die(trigger_error($mysqli->error));

            // Process the line items
            foreach ($Invoice->children() as $Child)
            {
                if ($Child->name() == 'InvoiceLineRet')
                {
                    $InvoiceLine = $Child;

                    $lineitem = array(
                        'TxnID' => $arr['TxnID'],
                        'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
                        'Item_list_id' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
                        'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
                        'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
                        'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
                        'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
                    );

                    foreach ($lineitem as $key => $value)
                    {
                        $lineitem[$key] = $mysqli->real_escape_string($value);
                    }

                    // Store the lineitems in MySQL
                    $mysqli->query("
						INSERT INTO
							qb_invoice_lineitem
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