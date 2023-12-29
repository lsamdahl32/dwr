<?php
/**
 * Class RecordLocking
 *
 * Manage record locking for editing
 * by Lee Samdahl - 2/20/2020
 */

class RecordLocking
{
    private string $lockDB = LOCKDB;
    private string $lockTable = 'recordLocks';
    private float $timeout = 60 * 5;
    private float $warningTimeout = 60 * 4.5;
    private string $db = ''; // this is the db name that will contain the locked table
    private string $table = ''; // this is the table that will contain the locked record
    private string $keyField = ''; // this is the primary key of the table that will contain the locked record

    /**
     * RecordLocking constructor.
     *
     * @param string $db
     * @param string $table
     * @param string $keyField
     * @param float|null $recordLockTimeout
     */
    public function __construct(string $db, string $table, string $keyField, ?float $recordLockTimeout = 5)
    {
        $this->db = $db;
        $this->table = $table;
        $this->keyField = $keyField;
        if ($recordLockTimeout != 5 and $recordLockTimeout >= 1) {
            $this->timeout = 60 * $recordLockTimeout;
            $this->warningTimeout = 60 * ($recordLockTimeout - .5);
        }
        $this->purgeExpiredLocks();
    }

    /**
     * Get Record Lock - this will return the record from recordLocks where $keyname == $id
     * @param int|string $id
     * @param bool $debug
     * @return false|array returns false if not found
     */
    private function getRecordLock($id, bool $debug = false)
    {
        // open db
        $dbe = new DBEngine($this->lockDB, $debug);
        $pq_query = 'Select * from `'.$this->lockTable.'` WHERE `db` = ? and `tableName` = ? and `keyField` = ? and `keyData` = ?';
        $dbe->setBindtypes("ssss");
        $dbe->setBindvalues(array($this->db, $this->table, $this->keyField, $id));
        $locks = $dbe->execute_query($pq_query);  // execute query
        $dbe->close();
        if ($locks){
            return $locks[0];
        } else {
            return false;
        }
    }

    /**************************************
     * Set Record Lock - saves a record in recordLocks
     *
     * @param int|string $id
     * @return bool
     */
    private function setRecordLock($id):bool
    {
        if ($id > 0) { // note expects numeric primary keys
            $dbe = new DBEngine($this->lockDB, false);
            $data = array(
                    'db'        => $this->db,
                    'tableName' => $this->table,
                    'KeyField'  => $this->keyField,
                    'keyData'   => $id,
                    'userID'    => $_SESSION['userID'],
                    'setTime'   => date('Y-m-d H:i:s')
            );
            $result = $dbe->insertRow($this->lockTable, $data);
            $dbe->close();
            if ($result) {
                return true;
            } else {
                return false;
            }
        }
        return true; // don't set locks when adding new records
    }

    /*******************************************
     * Is Locked by Me
     *
     * @param int|string $id
     * @return bool - return true if no lock set or locked by same user or lock is older than x mins
     */
    public function isLockedByMe($id):bool
    {
        $row = $this->getRecordLock($id, false);
        if ($row){
            if ($row['userID'] == $_SESSION['userID']){
                return true;
            } elseif (time() > (strtotime($row['setTime']) + $this->timeout)) {
                    return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /***************************************
     * Update Record Lock
     * reset the lock time so it won't expire
     *
     * @param int|string $id
     * @param bool $debug
     * @return int - 0 if no change, 1 if successful, -1 if error
     */
    private function updateRecordLock($id, bool $debug = false):int
    {
        $dbe = new DBEngine($this->lockDB);
        $data = array(
            'userID'    => $_SESSION['userID'],
            'setTime'   => date('Y-m-d H:i:s')
        );
        $result = $dbe->updateRow($this->lockTable, $data, 'lockID', $id, 'i');
        $dbe->close();
        return $result;
    }

    /*****************************************
     * Remove Record Lock
     *
     * @param int|string $id
     * @param bool $requireSameUser
     */
    public function removeRecordLock($id, bool $requireSameUser = false)
    {
        $row = $this->getRecordLock($id);
        if ($row) {
            if (($requireSameUser and $row['userID'] == $_SESSION['userID']) or !$requireSameUser) {
                $dbe = new DBEngine($this->lockDB, false);
                $dbe->deleteRow($this->lockTable, 'lockID', $row['lockID'], 'i');
                $dbe->close();
            }
        }
    }

    /**
     * Lock Record if not already locked by someone else
     * If locked by current user, reset the timeout interval
     *
     * @param int|string $currentID
     * @return bool  - true if lock was set or refreshed, false if locked by someone else
     */
    public function lockRecord($currentID):bool
    {
        // check if already locked
        $row = $this->getRecordLock($currentID, false);
        if ($row) {
            // if so check if lock is owned by current user
            if ($this->isLockedByMe($currentID)) {
                $result = $this->updateRecordLock($row['lockID']);
                if ($result > -1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                // if not expired and another user, return false
                return false;
            }
        } else {
            // if not locked, lock it and return true
            return $this->setRecordLock($currentID);
        }
    }

    /**
     * Purge expired locks
     *
     * The purpose of this function is to clean up the table and remove old records
     */
    private function purgeExpiredLocks()
    {
        $dbe = new DBEngine($this->lockDB);
        // count total records in lock table, if > 50 then run the purge
        $pq_query = 'SELECT count(*) as cnt FROM `'.$this->lockTable.'` WHERE `setTime` < ? ';
        $dbe->setBindtypes("s");
        $dbe->setBindvalues(array(date('Y-m-d H:i:s', strtotime("-15 minutes"))));
        $locks = $dbe->execute_query($pq_query);  // execute query
        if ($locks and $locks[0]['cnt'] > 50) {
            // get all records where setTime > 15 mins old (to be safe) and delete them
            $pq_query = 'DELETE FROM `' . $this->lockTable . '` WHERE `setTime` < ? ';
            $dbe->setBindtypes("s");
            $dbe->setBindvalues(array(date('Y-m-d H:i:s', strtotime("-15 minutes"))));
            $result = $dbe->execute_query($pq_query);  // execute query
        }
        $dbe->close();
    }

    /**
     * @return float
     */
    public function getTimeout():float
    {
        return $this->timeout;
    }

    /**
     * @return float
     */
    public function getWarningTimeout():float
    {
        return $this->warningTimeout;
    }


}
