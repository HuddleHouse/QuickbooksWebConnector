<?php
/**
 * Created by Matt Huddleston
 * User: work
 * Date: 6/30/16
 * Time: 12:27 PM
 */

$tableNames = array();

function createDatabaseTable($data) {
    $matt = $data->asJSON();
    $arr = array();
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");

    $query = "select * from ". $data->name();

    if ($result = $mysqli->query($query)) {
        //table exists
        $i = 1;
    }
    else {
        //need to create the table

        $element = findElementWithMostChildren($data);
        $arr = buildArrayForSchema($element);

        /**
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
         */


        $query = "CREATE TABLE IF NOT EXISTS ". $data->name()." (";

        foreach($arr as $item) {
            $query .= generateSqlLine($item);
        }

        $query = substr($query, 0, -2);
        $query .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
        $matt = $mysqli->query($query);

    }

    return $data;
}


/**
 * Generates a line to create SQL table
 *
 * @param $data['data']
 * @return string
 */
function generateSqlLine($data) {
    $line = $data['name'] . " ";
    $type = gettype($data['data']);

    if($type == 'boolean') {
        $line .= "varchar(6), ";
    }
    else if($type == 'integer') {
        $line .= "integer(10), ";
    }
    else if($type == 'double') {
        $line .= "BIGINT, ";
    }
    else if($type == 'string' && $data['data'] != 'TimeCreated ' && $data['data'] != 'TimeModified ') {
        $line .= "varchar(255), ";
    }
    else if($type == 'NULL') {
        $line .=  '';
    }
    else {
        $line .= "datetime ,";
    }

    if($data['numKids'] > 0) {
        $line .= parseChildren($data);
    }

    return $line;
}

function parseChildren($data) {
    $children = $data['children'];
    $line = '';

    foreach($children as $child) {
        $newElement = buildArrayForSchemaFromElement($child, $data['name']);
        $newLine .= generateSqlLine($newElement);
    }

    return $line;
}

/**
 * Returns the element that has the most children
 *
 * @param $data
 * @return mixed
 */
function findElementWithMostChildren($data) {
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");

    foreach($data->children() as $child) {

        $arrData = array(
            'hasChildren' => $child->hasChildren(),
            'numKids' => $child->childCount(),
            'data' => $mysqli->real_escape_string($child->data()),
            'name' => $child->name(),
            'children' => $child->children()
        );
        $arr[] = $arrData;
    }

    //sort these by who has the most so we can make sure to get all of the fields.
    usort($arr, function($a, $b) {
        return $a['numKids'] <=> $b['numKids'];
    });

    //now we can get the element we wanted
    $element = array_pop($arr);

    return $element;
}

/**
 * This builds a formatted array containing data for other functions to access.
 *
 * @param $data
 * @return array
 */
function buildArrayForSchema($data) {
    $arr = array();
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");

    foreach($data['children'] as $child) {

        $arrData = array(
            'hasChildren' => $child->hasChildren(),
            'numKids' => $child->childCount(),
            'data' => $mysqli->real_escape_string($child->data()),
            'name' => $child->name(),
            'children' => $child->children()
        );
        $arr[] = $arrData;
    }

    return $arr;
}

/**
 * This builds a formatted array containing data for other functions to access.
 *
 * @param $data
 * @return array
 */
function buildArrayForSchemaFromElement($data, $parentsName = null) {
    $arr = array();
    $mysqli = new mysqli("localhost", "quick", "quick", "quick");

    $arrData = array(
        'hasChildren' => $data->hasChildren(),
        'numKids' => $data->childCount(),
        'data' => $mysqli->real_escape_string($data->data()),
        'name' => $parentsName . $data->name(),
        'children' => $data->children()
    );
    return $arrData;
}