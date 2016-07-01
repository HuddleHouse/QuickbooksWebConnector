<?php

/**
 * Build a request to import customers already in QuickBooks into our application
 */
function _quickbooks_item_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    // Iterator support (break the result set into small chunks)
    /* check connection */
    if ($mysqli->connect_errno) {
        printf("Connect failed: %s\n", $mysqli->connect_error);
        exit();
    }

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
				<ItemQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . ' requestID="' . $requestID . '">
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<FromModifiedDate>' . $last . '</FromModifiedDate>
					<OwnerID>0</OwnerID>
				</ItemQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

    return $xml;
}


/**
 * Handle a response from QuickBooks
 */
function _quickbooks_item_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request

        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_ITEM, null, QB_PRIORITY_ITEM, array( 'iteratorID' => $idents['iteratorID'] ));
    }

    // Import all of the records
    $errnum = 0;
    $errmsg = '';
    $Parser = new QuickBooks_XML_Parser($xml);
    if ($Doc = $Parser->parse($errnum, $errmsg))
    {
        $Root = $Doc->getRoot();
        $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/ItemQueryRs');

        foreach ($List->children() as $Item)
        {
            $type = substr(substr($Item->name(), 0, -3), 4);
            $ret = $Item->name();

            $arr = array(
                'list_id' => $Item->getChildDataAt($ret . ' ListID'),
                'time_created' => $Item->getChildDataAt($ret . ' time_created'),
                'time_modified' => $Item->getChildDataAt($ret . ' time_modified'),
                'Name' => $Item->getChildDataAt($ret . ' Name'),
                'FullName' => $Item->getChildDataAt($ret . ' FullName'),
                'Type' => $type,
                'Parent_list_id' => $Item->getChildDataAt($ret . ' ParentRef ListID'),
                'Parent_FullName' => $Item->getChildDataAt($ret . ' ParentRef FullName'),
                'ManufacturerPartNumber' => $Item->getChildDataAt($ret . ' ManufacturerPartNumber'),
                'SalesTaxCode_list_id' => $Item->getChildDataAt($ret . ' SalesTaxCodeRef ListID'),
                'SalesTaxCode_FullName' => $Item->getChildDataAt($ret . ' SalesTaxCodeRef FullName'),
                'BuildPoint' => $Item->getChildDataAt($ret . ' BuildPoint'),
                'ReorderPoint' => $Item->getChildDataAt($ret . ' ReorderPoint'),
                'QuantityOnHand' => $Item->getChildDataAt($ret . ' QuantityOnHand'),
                'AverageCost' => $Item->getChildDataAt($ret . ' AverageCost'),
                'QuantityOnOrder' => $Item->getChildDataAt($ret . ' QuantityOnOrder'),
                'QuantityOnSalesOrder' => $Item->getChildDataAt($ret . ' QuantityOnSalesOrder'),
                'TaxRate' => $Item->getChildDataAt($ret . ' TaxRate'),
            );

            $look_for = array(
                'SalesPrice' => array( 'SalesOrPurchase Price', 'SalesAndPurchase SalesPrice', 'SalesPrice' ),
                'SalesDesc' => array( 'SalesOrPurchase Desc', 'SalesAndPurchase SalesDesc', 'SalesDesc' ),
                'PurchaseCost' => array( 'SalesOrPurchase Price', 'SalesAndPurchase PurchaseCost', 'PurchaseCost' ),
                'PurchaseDesc' => array( 'SalesOrPurchase Desc', 'SalesAndPurchase PurchaseDesc', 'PurchaseDesc' ),
                'PrefVendor_list_id' => array( 'SalesAndPurchase PrefVendorRef ListID', 'PrefVendorRef ListID' ),
                'PrefVendor_FullName' => array( 'SalesAndPurchase PrefVendorRef FullName', 'PrefVendorRef FullName' ),
            );

            foreach ($look_for as $field => $look_here)
            {
                if (!empty($arr[$field]))
                {
                    break;
                }

                foreach ($look_here as $look)
                {
                    $arr[$field] = $Item->getChildDataAt($ret . ' ' . $look);
                }
            }

            QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing ' . $type . ' Item ' . $arr['FullName'] . ': ' . print_r($arr, true));

            foreach ($arr as $key => $value)
            {
                $arr[$key] = $mysqli->real_escape_string($value);
            }

            //print_r(array_keys($arr));
            //trigger_error(print_r(array_keys($arr), true));

            // Store the customers in MySQL
            $mysqli->query("
				insert INTO
					qb_item
				(
					" . implode(", ", array_keys($arr)) . "
				) VALUES (
					'" . implode("', '", array_values($arr)) . "'
				)") or die(trigger_error($mysqli->error));
        }
    }

    return true;
}