<?php

/**
 * Login success hook - perform an action when a user logs in via the Web Connector
 *
 *
 */
function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config)
{
    // For new users, we need to set up a few things
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    // Fetch the queue instance
    $Queue = QuickBooks_WebConnector_Queue_Singleton::getInstance();
    $date = '1983-01-02 12:01:01';

//    $Queue->enqueue(QUICKBOOKS_IMPORT_INVOICE, 1, QB_PRIORITY_INVOICE);
//    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_INVOICE))
//    {
//        // And write the initial sync time
//        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_INVOICE, $date);
//    }

    $Queue->enqueue(QUICKBOOKS_IMPORT_CUSTOMER, 1, QB_PRIORITY_CUSTOMER);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_CUSTOMER, $date);
    }
    
    $Queue->enqueue(QUICKBOOKS_IMPORT_SALESORDER, 1, QB_PRIORITY_SALESORDER);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_SALESORDER))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_SALESORDER, $date);
    }

    $Queue->enqueue(QUICKBOOKS_IMPORT_ITEM, 1, QB_PRIORITY_ITEM);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_IMPORT_ITEM))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_IMPORT_ITEM, $date);
    }

    $Queue->enqueue(QUICKBOOKS_QUERY_INVENTORYSITE, 1, QB_PRIORITY_INVENTORYSITE);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_QUERY_INVENTORYSITE))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_QUERY_INVENTORYSITE, $date);
    }
    
    $Queue->enqueue(QUICKBOOKS_IMPORT_PURCHASEORDER, 1, QB_PRIORITY_PURCHASEORDER);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_QUERY_PURCHASEORDER))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_QUERY_PURCHASEORDER, $date);
    }
    
    $Queue->enqueue(QUICKBOOKS_QUERY_ITEMSITES, 1, QB_PRIORITY_ITEMSITES);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_QUERY_ITEMSITES))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_QUERY_ITEMSITES, $date);
    }
    
    $Queue->enqueue(QUICKBOOKS_QUERY_INVENTORYADJUSTMENT, 1, QB_PRIORITY_INVENTORYADJUSTMENT);
    if (!_quickbooks_get_last_run($user, QUICKBOOKS_QUERY_INVENTORYADJUSTMENT))
    {
        _quickbooks_set_last_run($user, QUICKBOOKS_QUERY_INVENTORYADJUSTMENT, $date);
    }
    
}