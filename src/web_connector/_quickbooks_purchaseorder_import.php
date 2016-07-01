<?php
/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_purchaseorder_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
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
				<PurchaseOrderQueryRq>
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
    if (!empty($idents['iteratorRemainingCount']))
    {
        // Queue up another request
        $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
        $Queue->enqueue(QUICKBOOKS_IMPORT_PURCHASEORDER, null, QB_PRIORITY_PURCHASEORDER, array( 'iteratorID' => $idents['iteratorID'] ));
    }

    return parseResponse($xml, 'PurchaseOrderQuery');
}