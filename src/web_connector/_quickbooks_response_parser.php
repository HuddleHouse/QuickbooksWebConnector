<?php

function parseResponse($xml, $name){
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");
    $errnum = 0;
    $errmsg = '';
    $Parser = new QuickBooks_XML_Parser($xml);


        if ($Doc = $Parser->parse($errnum, $errmsg))
        {
            $Root = $Doc->getRoot();
            $List = $Root->getChildAt('QBXML/QBXMLMsgsRs/'. $name .'Rs');
            $arr = createDatabaseTable($List);

            foreach ($List->children() as $PurchaseOrder)
            {
                $arr = array();

                foreach($PurchaseOrder->children() as $child) {
                    $a = $child;
                    $arr[$child->name()] = $child->data();
                }

                foreach ($arr as $key => $value)
                {
                    $arr[$key] = $mysqli->real_escape_string($value);
                }

                try {
                    $query = "INSERT INTO ". $List->name() ."(" . implode(", ", array_keys($arr)) . ") VALUES ('" . implode("', '", array_values($arr)) . "') ";
                    $mysqli->query($query);
                }
                catch(\Exception $e) {
                    $i = 1;
                }
            }
        }
        return true;

    return true;
}