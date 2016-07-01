-- phpMyAdmin SQL Dump
-- version 2.11.9
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 28, 2009 at 07:35 AM
-- Server version: 5.0.67
-- PHP Version: 5.2.9

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: 'quickbooks_import'
--

-- --------------------------------------------------------

--
-- Table structure for table 'qb_customer'
--

CREATE TABLE IF NOT EXISTS qb_customer (
  list_id varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `Name` varchar(50),
  FullName varchar(255),
  FirstName varchar(40),
  MiddleName varchar(10),
  LastName varchar(40),
  Contact varchar(50),
  ShipAddress_Addr1 varchar(50),
  ShipAddress_Addr2 varchar(50),
  ShipAddress_City varchar(50),
  ShipAddress_State varchar(25),
  ShipAddress_Province varchar(25),
  ShipAddress_PostalCode varchar(16),
  PRIMARY KEY  (list_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Table structure for table 'qb_estimate'
--

CREATE TABLE IF NOT EXISTS qb_purchase_orders (
  TxnID varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `EditSequence` varchar(255),
  TxnNumber int(10),
  VendorListID varchar(40),
  VendorFullName varchar(255),
  InventorySiteListID varchar(40),
  InventorySiteFullName varchar(255),
  ShipToEntityListID varchar(40),
  ShipToEntityFullName varchar(255),
  TemplateListID varchar(40),
  TemplateFullName varchar(255),


  PRIMARY KEY  (TxnID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table 'qb_estimate_lineitem'
--

CREATE TABLE IF NOT EXISTS qb_estimate_lineitem (
  TxnID varchar(40) NOT NULL,
  TxnLineID varchar(40),
  Item_list_id varchar(40),
  Item_FullName varchar(255),
  Descrip text,
  Quantity int(10) unsigned,
  Rate float,
  PRIMARY KEY  (TxnID,TxnLineID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table 'qb_invoice'
--

CREATE TABLE IF NOT EXISTS qb_invoice (
  TxnID varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  RefNumber varchar(16),
  Customer_list_id varchar(40),
  Customer_FullName varchar(255),
  ShipAddress_Addr1 varchar(50),
  ShipAddress_Addr2 varchar(50),
  ShipAddress_City varchar(50),
  ShipAddress_State varchar(25),
  ShipAddress_Province varchar(25),
  ShipAddress_PostalCode varchar(16),
  BalanceRemaining float,
  PRIMARY KEY  (TxnID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table 'qb_invoice_lineitem'
--

CREATE TABLE IF NOT EXISTS qb_invoice_lineitem (
  TxnID varchar(40) NOT NULL,
  TxnLineID varchar(40),
  Item_list_id varchar(40),
  Item_FullName varchar(255),
  Descrip text,
  Quantity int(10) unsigned,
  Rate float,
  PRIMARY KEY  (TxnID,TxnLineID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------


--
-- Table structure for table 'qb_item'
--

CREATE TABLE IF NOT EXISTS qb_item (
  list_id varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `Name` varchar(50),
  FullName varchar(255),
  `Type` varchar(40),
  Parent_list_id varchar(40),
  Parent_FullName varchar(255),
  ManufacturerPartNumber varchar(40),
  SalesTaxCode_list_id varchar(40),
  SalesTaxCode_FullName varchar(255),
  BuildPoint varchar(40),
  ReorderPoint varchar(40),
  QuantityOnHand int(10) unsigned,
  AverageCost float,
  QuantityOnOrder int(10) unsigned,
  QuantityOnSalesOrder int(10) unsigned,
  TaxRate varchar(40),
  SalesPrice float,
  SalesDesc text,
  PurchaseCost float,
  PurchaseDesc text,
  PrefVendor_list_id varchar(40),
  PrefVendor_FullName varchar(255),
  PRIMARY KEY  (list_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table 'qb_item'
--

CREATE TABLE IF NOT EXISTS qb_inventory_adjustment (
  TxnID varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `EditSequence` varchar(50),
  TxnNumber varchar(40),
  AccountListID varchar(40),
  AccountFullName varchar(255),
  InventorySiteListID varchar(255),
  InventorySiteFullName varchar(60),
  TxnDate datetime,
  RefNumber float,
  Memo text,
  PRIMARY KEY  (TxnID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS qb_inventory_sites (
  list_id varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `Name` varchar(50),
  `EditSequence` varchar(255),
  description varchar(255),
  contact varchar(255),
  is_active varchar(10),
  is_default_site varchar(10),
  PRIMARY KEY  (list_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Table structure for table 'qb_salesorder'
--

CREATE TABLE IF NOT EXISTS qb_salesorder (
  TxnID varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  RefNumber varchar(16),
  Customer_list_id varchar(40),
  Customer_FullName varchar(255),
  ShipAddress_Addr1 varchar(50),
  ShipAddress_Addr2 varchar(50),
  ShipAddress_City varchar(50),
  ShipAddress_State varchar(25),
  ShipAddress_Province varchar(25),
  ShipAddress_PostalCode varchar(16),
  BalanceRemaining float,
  PRIMARY KEY  (TxnID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table 'qb_salesorder_lineitem'
--

CREATE TABLE IF NOT EXISTS qb_salesorder_lineitem (
  TxnID varchar(40) NOT NULL,
  TxnLineID varchar(40),
  Item_list_id varchar(40),
  Item_FullName varchar(255),
  Descrip text,
  Quantity int(10) unsigned,
  Rate float,
  PRIMARY KEY  (TxnID,TxnLineID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS qb_item_sites (
  ListID varchar(40) NOT NULL,
  time_created datetime,
  time_modified datetime,
  `EditSequence` varchar(255),
  ItemInventoryListID varchar(40),
  ItemInventoryFullName varchar(40),
  QuantityOnHand int(10),
  QuantityOnPurchaseOrders int(10),
  QuantityOnSalesOrders int(10),
  QuantityToBeBuiltByPendingBuildTxns int(10),
  QuantityRequiredByPendingBuildTxns int(10),
  QuantityOnPendingTransfers int(10),

  PRIMARY KEY  (ListID)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;