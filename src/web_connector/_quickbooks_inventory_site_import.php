<?php

/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_inventory_site_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
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
        <InventorySiteQueryRq>
        </InventorySiteQueryRq>
    </QBXMLMsgsRq>
</QBXML>';

    return $xml;
}


/**
 * Handle a response from QuickBooks
 */
function _quickbooks_inventory_site_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_QUERY_INVENTORYSITE, null, QB_PRIORITY_INVENTORYSITE, array( 'iteratorID' => $idents['iteratorID'] ));
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
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InventorySiteQueryRs');

        foreach ($List->children() as $Site)
        {
            $arr = array(
                'list_id' => $Site->getChildDataAt('InventorySiteRet ListID'),
                'time_created' => $Site->getChildDataAt('InventorySiteRet TimeCreated'),
                'time_modified' => $Site->getChildDataAt('InventorySiteRet TimeModified'),
                'EditSequence' => $Site->getChildDataAt('InventorySiteRet EditSequence'),
                'Name' => $Site->getChildDataAt('InventorySiteRet Name'),
                'is_active' => $Site->getChildDataAt('InventorySiteRet IsActive'),
                'is_default_site' => $Site->getChildDataAt('InventorySiteRet IsDefaultSite'),
                'description' => $Site->getChildDataAt('InventorySiteRet SiteDesc'),
                'contact' => $Site->getChildDataAt('InventorySiteRet Contact'),
            );


            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            // Store the invoices in MySQL
            $mysqli->query("
				REPLACE INTO 
					qb_inventory_sites
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));


        }
    }

    return true;
}