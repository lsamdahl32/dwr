<?php
/**
 * Property and Lease Manager config file
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 * @created 4/4/23
 */

const PLMSite = 'https://gleesoft.com/dwr/';
define("PLMPATH", $_SERVER['DOCUMENT_ROOT'] . '/dwr/');
const LOCKDB = 'plm';
const PLMDB = 'plm';

const DB_SETTINGS =  array(
	'plm' => array(
		'username' => "dbu1914148",
		'password' => "MXtbC6bGEJQzW4n9GOSX#1",
		'db_name' => "dbs10541888",
		'host' => "db5012541054.hosting-data.io",
		'port' => 3306,
        'ssl'   => false
	),
	// other databases can be added here
);
