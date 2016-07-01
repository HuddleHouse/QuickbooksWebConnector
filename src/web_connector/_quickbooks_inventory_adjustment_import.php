<?php

/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_inventory_adjustment_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
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
        <InventoryAdjustmentQueryRq>
        </InventoryAdjustmentQueryRq>
    </QBXMLMsgsRq>
</QBXML>';

    return $xml;
}


/**
 * Handle a response from QuickBooks
 */
function _quickbooks_inventory_adjustment_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
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
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InventoryAdjustmentQueryRs');

        foreach ($List->children() as $adjustment)
        {
            $name = "InventoryAdjustmentRet";
            $arr = array(
                'TxnID' => $adjustment->getChildDataAt('InventoryAdjustmentRet TxnID'),
                'time_created' => $adjustment->getChildDataAt('InventoryAdjustmentRet TimeCreated'),
                'time_modified' => $adjustment->getChildDataAt('InventoryAdjustmentRet TimeModified'),
                'EditSequence' => $adjustment->getChildDataAt('InventoryAdjustmentRet EditSequence'),
                'TxnNumber' => $adjustment->getChildDataAt('InventoryAdjustmentRet TxnNumber'),
                'AccountListID' => $adjustment->getChildDataAt('InventoryAdjustmentRet AccountRef ListID'),
                'AccountFullName' => $adjustment->getChildDataAt('InventoryAdjustmentRet AccountRef FullName'),
                'InventorySiteListID' => $adjustment->getChildDataAt('InventoryAdjustmentRet InventorySiteRef ListID'),
                'InventorySiteFullName' => $adjustment->getChildDataAt('InventoryAdjustmentRet InventorySiteRef FullName'),
                'TxnDate' => $adjustment->getChildDataAt('InventoryAdjustmentRet TxnDate'),
                'RefNumber' => $adjustment->getChildDataAt('InventoryAdjustmentRet RefNumber'),
                'Memo' => $adjustment->getChildDataAt('InventoryAdjustmentRet Memo'),
            );


            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            // Store the invoices in MySQL
            $mysqli->query("
				REPLACE INTO 
					qb_inventory_adjustment
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));


        }
    }

    return true;
}