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

$db_user = getenv('DB_USER');
//.getenv('DB_PASSWORD').getenv('DB_NAME').getenv('DB_HOST').getenv('DB_PORT');

// const "DB_SETTINGS" =  array(
// 	'plm' => array(
// 		'username' => getenv('DB_USER'),
// 		'password' => getenv('DB_PASSWORD'),
// 		'db_name' => getenv('DB_NAME'),
// 		'host' => getenv('DB_HOST'),
// 		'port' => getenv('DB_PORT'),
//         'ssl'   => false
// 	),
// 	// other databases can be added here
// );

define("DB_SETTINGS", array(
	'plm' => array(
		'username' => getenv('DB_USER'),
		'password' => getenv('DB_PASSWORD'),
		'db_name' => getenv('DB_NAME'),
		'host' => getenv('DB_HOST'),
		'port' => getenv('DB_PORT'),
        'ssl'   => false
	),
	// other databases can be added here
));
