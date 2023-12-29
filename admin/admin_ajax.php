<?php
/**
 * plm_admin_ajax.php
 * Handler for AJAX calls from admin pages
 * All input via POST
 * All output to be JSON
 * @author Lee Samdahl, Gleesoft.com
 * @date 4/25/23
 */

ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
error_reporting(E_ALL & ~E_NOTICE);

$process = $_POST['process'];

if (isset($_POST['process'])) {
    $process = clean_param($_POST['process'], 's');
} elseif (isset($_GET['process'])) {
    $process = clean_param($_GET['process'], 's');
} else {
    exit();
}

switch ($process) {

    case 'doSaveSessionVar':
        $var = clean_param($_POST['var'], 's');
        $val = clean_param($_POST['val'], 's');
        $_SESSION[$var] = $val;
        break;

    case 'unlockRecord': // use the record locking class to unlock a record
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db);
        $table = clean_param($_POST['table'], 's');
        $keyname = clean_param($_POST['keyname'], 's');
        $id = clean_param($_POST['id'], 's');
        $requireSameUser = clean_param($_POST['requireSameUser'], 's');
        require_once(PLMPATH . "classes/RecordLocking.php");
        $recLock = new RecordLocking($db, $table, $keyname);
        $recLock->removeRecordLock($id, $requireSameUser);
        // no response
        break;

    case 'resetRecordLockTime': // use the record locking class to lock a record or extend a lock timeout
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db);
        $table = clean_param($_POST['table'], 's');
        $keyname = clean_param($_POST['keyname'], 's');
        $id = clean_param($_POST['id'], 's');
        require_once(PLMPATH ."classes/RecordLocking.php");
        $recLock = new RecordLocking($db, $table, $keyname);
        // check if already locked
        if ($recLock->lockRecord($id)) {
            echo '1';
        } else {
            echo '0';
        }
        break;

    case 'getRecords':
        // query the db and return records
        // note that the potential for SQL injection is high with this routine, it should never
        // be used in a public-facing project
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db, false);
        $select = clean_param($_POST['select'], 's', true, $dbe->dblink);
        $where = explode(',', clean_param($_POST['where'], 's', true, $dbe->dblink)); // comma separated list of where clauses to be joined by AND
        $dbe->setBindtypes(clean_param($_POST['bindtypes'], 's'));
        $dbe->setBindvalues(explode(',', clean_param($_POST['bindvalues'], 's'))); // comma separated list of values
        $order = clean_param($_POST['order'], 's', true, $dbe->dblink); // comma separated list of order by fields
        $pq_query = $select;
        if (count($where) != 0) $pq_query .= ' WHERE ' . implode(' AND ', $where);
        if ($order != '') $pq_query .= ' ORDER BY ' . $order;
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            echo "Success|" . json_encode($rows);
        }
        // if not found or error, return nothing
        break;

    // GET processes
    case 'updateField':
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db);
        $table = clean_param($_POST['table'], 's', true, $dbe->dblink);
        $keyField = clean_param($_POST['keyField'], 's', true, $dbe->dblink);
        $key = clean_param($_POST['key'], 's'); // some tables use strings for primary keys
        $field = clean_param($_POST['field'], 's', true, $dbe->dblink);
        $useRecordLocks = clean_param($_POST['useRecordLocks'], 'i');
        $isDate = clean_param($_POST['isDate'], 's');

        if (isset($_POST['bindtype'])) {
            $bindtype = clean_param($_POST['bindtype'], 's');
        } else {
            $bindtype = 's';
        }
        $value = $_POST['value']; // removed clean_param since will be used in parameterized query 8/31/21

        if ($isDate == 'true') {
            $value = date('Y-m-d H:i:s', strtotime($value));
        }

        // check if data has been changed, if so, return a message
        $oldData = '';

        $pq_query = 'SELECT * FROM ' . $table . ' WHERE `' . $keyField . '` = ? ';
        $dbe->setBindtypes("s"); // some tables use strings for primary keys
        $dbe->setBindvalues(array($key));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            if ($rows[0][$field] != $value) {
                $oldData = $rows[0][$field];
                // data is changed - proceed to update
                if ($useRecordLocks) {
                    require_once(PLMPATH ."classes/RecordLocking.php");
                    $recLock = new RecordLocking($db, $table, $keyField);
                    $locked = $recLock->lockRecord($key);
                } else {
                    $locked = true;
                }
                if ($locked) {
                    $pq_query = 'UPDATE ' . $table . ' SET ' . $field . ' = ? WHERE ' . $keyField . ' = ? ';
                    $dbe->setBindtypes($bindtype . "s");
                    $dbe->setBindvalues(array($value, $key));
                    $rows = $dbe->execute_query($pq_query);  // execute query
                    if ($rows) {
//                        changeLog(basename($_SERVER["SCRIPT_NAME"]), $table, $field, $key, $oldData, $value, 'via updateField', 'atools_dev');
                        // initialize the logging class
                        $atlog = new AtLogging(basename($_SERVER["HTTP_REFERER"]));
                        $atlog->commitLog($table, $field, $key,'UPDATE', $oldData, $value, 'via manageAjax.php updateField');
                        echo 'Success';
                    } else {
                        echo 'Error - record not saved.|' . $oldData;
                    }
                    if ($useRecordLocks) {
                        $recLock->removeRecordLock($key);
                    }
                } else {
                    echo 'The Record is locked by another user.<br/>Please wait a few minutes and try again.|'.$oldData;
                }
            } else {
                echo ''; // The value was unchanged. - ignore
            }
        } else {
            echo 'Error - record not found.';
        }
        break;

    case 'deleteRecord':
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db, false, false, true);
        $table = clean_param($_POST['table'], 's', true, $dbe->dblink);
        $keyField = clean_param($_POST['keyField'], 's', true, $dbe->dblink);
        $key = clean_param($_POST['key'], 'i');

        // initialize the logging class
        $atlog = new AtLogging(basename($_SERVER["HTTP_REFERER"]));
        $atlog->prepareDeleteLog($db, $table, $keyField, $key, 'via manageAjax.php deleteRecord');
        // perform delete
        $pq_query = 'DELETE FROM ' . $table . ' WHERE ' . $keyField . ' = ? LIMIT 1 ';
        $dbe->setBindtypes("i");
        $dbe->setBindvalues(array($key));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            $atlog->commitLog(); // commit the log entry
            echo 'Success';
        } else {
            $atlog->cancelCommit();
            echo $dbe->error_num . '|' . $dbe->error_msg;
        }
        break;

    case 'insertRecord':
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db, false, false, true);
        $table = clean_param($_POST['table'], 's', true, $dbe->dblink);
        $keyField = clean_param($_POST['keyField'], 's', true, $dbe->dblink);

        $pq_bindtypes = "";
        $pq_bindvalues = array();
        $data = array(); // for logging

        $pq_query = 'INSERT INTO ' . $table . ' SET ';
        foreach ($_POST as $key => $param) {
            if ($key != 'db' and $key != 'table' and $key != 'keyField') {
                $pq_query .= clean_param($key, 's', true, $dbe->dblink) . ' = ?, ';
                $pq_bindtypes .= "s";
                $pq_bindvalues[] = clean_param($param, 's');
                $data[$key] = $param;
            }
        }
        $pq_query = substr($pq_query, 0, strlen($pq_query) - 2);
        //    echo $pq_query;
        //    echo ' '.$pq_bindtypes;
        //    print_r($pq_bindvalues);
        $dbe->setBindtypes($pq_bindtypes);
        $dbe->setBindvalues($pq_bindvalues);
        $result = $dbe->execute_query($pq_query);  // execute query
        if ($result) {
            $newID = mysqli_insert_id($dbe->dblink);
            // initialize the logging class
            $atlog = new AtLogging(basename($_SERVER["HTTP_REFERER"]));
            $atlog->insertLog($table, $newID, serialize($data), 'via manageAjax.php insertRecord');
            echo 'Success|'.$newID;
        } else {
            echo $dbe->error_num . '|' . $dbe->error_msg;
        }
        break;

    case 'lookupRecord':
        // lookup and only return if one and only one record found
        $db = clean_param($_POST['db'], 's');
        $dbe = new DBEngine($db);
        $table = clean_param($_POST['table'], 's', true, $dbe->dblink);
        $keyField = clean_param($_POST['keyField'], 's', true, $dbe->dblink);
        $key = clean_param($_POST['key'], 's', true, $dbe->dblink);
        if (isset($_POST['select'])) {
            $select = clean_param($_POST['select'], 's', true, $dbe->dblink);
        } else {
            $select = '*';
        }

        $pq_query = 'SELECT '.$select.' FROM `'.$table.'`
                     WHERE `'.$keyField.'` LIKE "'.$key.'" LIMIT 2 ';
        $dbe->setBindtypes("");
        $dbe->setBindvalues(array());
        $records = $dbe->execute_query($pq_query);  // execute query
        if ($records) {
            if (count($records) == 1) {
                echo "Success|" . json_encode($records[0]);
            }
        }
        // if not found or error, return nothing
        break;

}

function showBookings($id)
{

}