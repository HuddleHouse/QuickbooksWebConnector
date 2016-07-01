<?php
require_once './_quickbooks_database_builder.php';
/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_purchaseorder_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
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
				<PurchaseOrderQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<!--<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>-->
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</PurchaseOrderQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

    return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_purchaseorder_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_PURCHASEORDER, null, QB_PRIORITY_PURCHASEORDER, array( 'iteratorID' => $idents['iteratorID'] ));
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
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/PurchaseOrderQueryRs');
        $arr = createDatabaseTable($List);

        foreach ($List->children() as $PurchaseOrder)
        {


//            $arr = array(
//                'TxnID' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet TxnID'),
//                'time_created' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet time_created'),
//                'time_modified' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet time_modified'),
//                'RefNumber' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet RefNumber'),
//                'Customer_list_id' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet CustomerRef ListID'),
//                'Customer_FullName' => $PurchaseOrder->getChildDataAt('PurchaseOrderRet CustomerRef FullName'),
//            );


            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            
            
            // Process all child elements of the Purchase Order
            foreach ($PurchaseOrder->children() as $Child)
            {
                if ($Child->name() == 'PurchaseOrderLineRet')
                {
                    // Loop through line items

                    $PurchaseOrderLine = $Child;

                    $lineitem = array(
                        'TxnID' => $arr['TxnID'],
                        'TxnLineID' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet TxnLineID'),
                        'Item_list_id' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet ItemRef ListID'),
                        'Item_FullName' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet ItemRef FullName'),
                        'Descrip' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet Desc'),
                        'Quantity' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet Quantity'),
                        'Rate' => $PurchaseOrderLine->getChildDataAt('PurchaseOrderLineRet Rate'),
                    );

                }
                else if ($Child->name() == 'DataExtRet')
                {
                    // Loop through custom fields

                    $DataExt = $Child;

                    $dataext = array(
                        'DataExtName' => $Child->getChildDataAt('DataExtRet DataExtName'),
                        'DataExtValue' => $Child->getChildDataAt('DataExtRet DataExtValue'),
                    );

                }
            }
        }
    }

    return true;
}