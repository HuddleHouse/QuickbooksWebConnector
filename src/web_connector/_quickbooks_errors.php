<?php

/**
 * Handle a 500 not found error from QuickBooks
 *
 * Instead of returning empty result sets for queries that don't find any
 * records, QuickBooks returns an error message. This handles those error
 * messages, and acts on them by adding the missing item to QuickBooks.
 */
function _quickbooks_error_e500_notfound($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
{
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();

    if ($action == QUICKBOOKS_IMPORT_INVOICE)
    {
        return true;
    }
    else if ($action == QUICKBOOKS_IMPORT_CUSTOMER)
    {
        return true;
    }
    else if ($action == QUICKBOOKS_IMPORT_SALESORDER)
    {
        return true;
    }
    else if ($action == QUICKBOOKS_IMPORT_ITEM)
    {
        return true;
    }
    else if ($action == QUICKBOOKS_IMPORT_PURCHASEORDER)
    {
        return true;
    }

    return false;
}


/**
 * Catch any errors that occur
 *
 * @param string $requestID
 * @param string $action
 * @param mixed $ID
 * @param mixed $extra
 * @param string $err
 * @param string $xml
 * @param mixed $errnum
 * @param string $errmsg
 * @return void
 */
function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
{
    $message = '';
    $message .= 'Request ID: ' . $requestID . "\r\n";
    $message .= 'User: ' . $user . "\r\n";
    $message .= 'Action: ' . $action . "\r\n";
    $message .= 'ID: ' . $ID . "\r\n";
    $message .= 'Extra: ' . print_r($extra, true) . "\r\n";
    //$message .= 'Error: ' . $err . "\r\n";
    $message .= 'Error number: ' . $errnum . "\r\n";
    $message .= 'Error message: ' . $errmsg . "\r\n";

    mail(QB_QUICKBOOKS_MAILTO,
        'QuickBooks error occured!',
        $message);
}