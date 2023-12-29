<?php
/**
 * Class DBEngine
 * by Lee Samdahl - 2/27/2020, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 * @version 2.0
 *
 */

class DBEngine
{
    public $dblink; // the connection
    public string $error_msg;
    public $error_num;
    private string $dbname; // the db name from DB_SETTINGS[]
    private string $bindtypes = '';
    private ?array $bindvalues = array();
    private bool $debug = false;
    private bool $errorsOnly = false;
    private bool $suppressText = false;
    private bool $inTransaction = false;

    /**
     * DBEngine constructor.
     *
     * @param string $dbname
     * @param bool $debug - show debug information
     * @param bool $errorsOnly - only show errors
     * @param bool $suppressText - show no text output, even for errors
     */
    public function __construct(string $dbname = 'plm', bool $debug = false, bool $errorsOnly = false, bool $suppressText = false)
    {
        $this->dbname = $dbname;
        $this->debug = $debug;
        $this->errorsOnly = $errorsOnly;
        $this->suppressText = $suppressText;
        $this->dblink = $this->connect($this->dbname);
        $this->error_msg = '';

//        mysqli_set_charset($this->dblink, "utf8");
        mysqli_set_charset($this->dblink, "utf8mb4");
    }

    /**
     * 6/24/2020 Added use of mysqli_sql_exception class and moved connection processing to inside this class
     * @param string $dbname
     * @return false|mysqli
     */
    private function connect(string $dbname)
    {
        mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

        $dbCreds = DB_SETTINGS[$dbname];

        try {

            if ($dbCreds['ssl'] == true ) {
                $dblink = mysqli_init();

                $key = NULL;
                $cert = NULL;
                $ca = '/etc/ssl/mysql/rds/rds-combined-ca-bundle.pem'; // changed to AWS RDS pem
                $capath = NULL;
                $cipher = NULL;

                mysqli_ssl_set($dblink, $key, $cert, $ca, $capath, $cipher);
                $connected = mysqli_real_connect($dblink, $dbCreds['host'], $dbCreds['username'], $dbCreds['password'], $dbCreds['db_name'], $dbCreds['port']);
                return $dblink;

            } else {

                return mysqli_connect($dbCreds['host'], $dbCreds['username'], $dbCreds['password'], $dbCreds['db_name'], $dbCreds['port']);
            }
        } catch (mysqli_sql_exception $e) {
            $this->error_msg = $e->getMessage();
            $this->error_num = $e->getCode();
            if (!$this->suppressText) {
                echo 'An error occurred connecting to your database: '.$this->error_num.' - '.$this->error_msg;
            }
            return false;
        }
    }

    /**
     * execute_query
     * Perform the actual query
     * 6/24/2020 Added use of mysqli_sql_exception class and moved query processing to inside this class
     *
     * @param string $query - the SQL query
     * @return array | false - false if error or no records found | associative array of results
     */
    public function execute_query(string $query)
    {
        mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

        if ($this->dblink) { // only query if connected
            // clear any previous errors
            $this->error_msg = '';
            $this->error_num = '';
            try {
                $stmt = mysqli_prepare($this->dblink, $query);

                if ($this->debug && !$this->errorsOnly) {
                    echo "<br />SqlState1: " . mysqli_stmt_sqlstate($stmt);
                }

                if ($stmt) {
                    $bindparam = '';
                    $bindargs = array();
                    if ($this->bindtypes != '') { // added by Lee 2/28/2020
                        if (strlen($this->bindtypes) == count($this->bindvalues)) {
                            //Bind provided parameters to the statement
                            $bindargs = $this->bindvalues;
                            array_unshift($bindargs, $this->bindtypes);    //prepend the types to bindargs array
                            $bindparam = @call_user_func_array(array($stmt, 'bind_param'), $this->refValues($bindargs));
                        } else {
                            throw new mysqli_sql_exception("Number of elements in type definition string doesn't match number of bind variables. ".$this->bindtypes.' ['.implode(',', $this->bindvalues).']');
                        }
                    }

                    //Execute the prepared statement
                    $result = mysqli_stmt_execute($stmt);

                    if ($this->debug) $bindresult = $result;

                    if ($this->debug && !$this->errorsOnly) {
                        $store_result = mysqli_stmt_store_result($stmt);
                        echo "<br />SqlState2: " . mysqli_stmt_sqlstate($stmt);
                    }

                    //Figure out how many fields were returned by the statement execution
                    //Then, bind variables to those fields
                    $meta = mysqli_stmt_result_metadata($stmt);
                    if (!$meta) {
                        //if getting metadata fails (was probably an insert/update/delete query), return affected rows
                        return mysqli_stmt_affected_rows($stmt);
                    }

                    $row = array();
                    $resultParams = array();
                    while ($field = $meta->fetch_field()) {
                        $resultParams[] = &$row[$field->name];
                    }

                    $result = call_user_func_array(array($stmt, 'bind_result'), $this->refValues($resultParams));

                    $results = array();

                    //Loop through the statement resultset (this assigns values to the bound variables)
                    //With each iteration, write the results into an associative array
                    $result = null;
                    while ($stmt->fetch()) {
                        $x = array();
                        foreach ($row as $key => $val) {
                            $x[$key] = $val;
                        }
                        $results[] = $x;
//                        if (memory_get_usage() > ((intval(ini_get("memory_limit")) * 1024 * 1024) * .99)) {
//                            // if memory usage exeeds 99% of capacity, throw error and exit
//                            throw new mysqli_sql_exception("Query results are too large for available memory.");
//                        }
                    }
                    $result = $results;

                    if ($this->debug && !$this->errorsOnly) {
                        if ($bindresult) { $bindresult="TRUE"; } elseif ($bindresult==false) { $bindresult="FALSE"; } else { $bindresult="UNKNOWN"; }

                        $debugResults =  "<br />BindParam:  " . $bindparam . "<br />";
                        if ($this->bindtypes != '') { // added by Lee 2/28/2020
                            $debugResults .= "BindArgs: " . var_export($bindargs, true) . "<br />";
                            $debugResults .= $this->refValues($bindargs).": " . var_export($this->refValues($bindargs), true) . "<br />";
                        } else { // added by Lee 2/28/2020
                            $debugResults = $debugResults . "BindArgs:  None <br />";
                        }

                        $debugResults = $debugResults . "Num Parameters: " . mysqli_stmt_param_count($stmt) . "<br />";
                        $debugResults = $debugResults . "Query: " . $query . "<br />";
                        if ($store_result) {
                            $debugResults = $debugResults . "Result Rows: " . mysqli_stmt_num_rows($stmt) . "<br />";
                        } else {
                            $debugResults = $debugResults . "STORE RESULTS FAILED!<br />";
                        }
                        $debugResults = $debugResults . "BindResult: " . $bindresult . "<br />";
                        $debugResults = $debugResults . "Data: " . var_export($result,true) . "<br /><br />";
                        $debugResults = $debugResults . "Charset: " . var_export(mysqli_get_charset($this->dblink),true) . "<br /><br />";

                        if (!$this->suppressText) echo $debugResults;
                    }
                }

                //Close connections and resources
                mysqli_stmt_free_result($stmt);
                mysqli_stmt_close($stmt);

            } catch (mysqli_sql_exception $e) {
                $this->error_msg = $e->getMessage();
                $this->error_num = $e->getCode();

                if ($this->debug && !$this->suppressText) {
                    echo 'An error occurred querying your database: '.$this->error_num.' - '.$this->error_msg.', '.$e->getTrace()[0]['file'].' Full Query: '.$query.' Line# '.$e->getTrace()[0]['line'].'<br/>';
//                        print_r($e->getTrace());
                } elseif (!$this->suppressText) {
                    // give very generic standard error
                    echo "<br />We're sorry, your request could not be processed at this time.<br />" . $this->error_msg;
                }
                return false;
            }

            return $result;

        } else {
            return false;
        }
    }

    /**
     * close the connection
     */
    public function close()
    {
        if (is_object($this->dblink) and $this->dblink->connect_errno == 0) {
            @mysqli_close($this->dblink);
        }
    }

    /**
     * getRowWhere()
     * Get a single db row based on a single field in WHERE
     *
     * @param string $table        - table name
     * @param string $keyField     - field name of primary key
     * @param $id                  - the primary key
     * @param string|null $orderby - the order by clause for SQL
     * @return array | false
     */
    function getRowWhere(string $table, string $keyField, $id, ?string $orderby='')
    {
        if ($orderby!=''){
            $orderby = ' ORDER BY '.$orderby;
        }
        if ($keyField != '' and !empty($id)) {
            $pq_query = "SELECT * FROM " . $table;
            $pq_query .= " WHERE `" . $keyField . "` = ? " . $orderby;
            $this->bindtypes = "s";
            $this->bindvalues = array($id);
            $rows = $this->execute_query($pq_query);
            if ($rows) {
                return $rows[0];
            } else {
                return false;
            }
        } else {
            if (!$this->suppressText) {
//                echo 'Error: Parameter missing.';
            }
            return false;
        }
    }

    /**
     * getRowsWhere()
     * return multi rows
     * if an array of where clauses are included, they must match bindtypes and bindvalues previously set
     *
     * @param string $table       - table name
     * @param array|null $select  - a flat array of field names for the select to be joined by commas
     * @param array|null $where   - a flat array of WHERE clauses to be joined by AND; if empty, all rows returned
     * @param array|null $orderby - a flat array of order by clauses
     * @param array|null $groupby - a flat array of group by clauses
     * @param int|null $limit
     * @return array | false
     */
    function getRowsWhere(string $table, ?array $select = array(), ?array $where = array(), ?array $orderby = array(), ?array $groupby = array(), ?int $limit = 0)
    {
        // check if any question marks are in $where, if so, make sure length of bindtypes and count of bindvalues are correct
        $qmarks = 0;
        foreach ($where as $item) {
            $qmarks += substr_count($item, '?');
        }
        if (strlen($this->bindtypes) == $qmarks and count($this->bindvalues) == $qmarks) {
            if (empty($select)) {
                $query = "SELECT * FROM " . $table . " ";
            } else {
                $query = "SELECT " . implode(', ', $select) . " FROM " . $table . " ";
            }
            if (!empty($where)) {
                $query .= ' WHERE ' . implode(' AND ', $where) . " ";
            }
            if (!empty($groupby)) {
                $query .= ' GROUP BY ' . implode(', ', $groupby) . " ";
            }
            if (!empty($orderby)) {
                $query .= ' ORDER BY ' . implode(', ', $orderby);
            }
            if ($limit > 0) {
                $query .= ' LIMIT ' . $limit;
            }

            if ($this->debug) echo $query . ' ' . $this->bindtypes;
            if ($this->debug) print_r($this->bindvalues);
            return $this->execute_query($query);
        } else {
            if (!$this->suppressText) {
                echo 'Error: Count of parameters does not agree in DBEngine.';
            }
            return false;
        }
    }


    /**
     * insertRow()
     * Insert one record to $table
     *
     * @param string $table
     * @param array $data - a key=>value array of new data to insert
     * @return int | false - the new record id or false
     */
    function insertRow(string $table, array $data)
    {
        // create insert query
        // return new id or false
        $query = 'INSERT INTO `' . $table . '` SET ';
        $this->bindvalues = array();
        $this->bindtypes = "";
        $query .= implode(', ', $this->processData($data));
        if ($this->debug) echo $query.' '.$this->bindtypes;
        if ($this->debug) print_r($this->bindvalues);
        $result = $this->execute_query($query);  // execute query
        // $result should eq affected rows, zero if none, -1 if error
        if ($result > -1) {
            if ($result > 0) {
                // get the new primary key value
                $key = mysqli_insert_id($this->dblink);
                if ($key) { // has a auto increment primary key
                    return $key;
                } else {
                    return $result;
                }
            } else {
                return false;
            }
        } else { // -1 if error
            return false;
        }
    }

    /**
     * updateRow()
     * Update a single db record
     *
     * @param string $table
     * @param array $data           - a key=>value array of new data to update
     * @param string $keyname       - field name of primary key
     * @param string $prikey        - the primary key
     * @param string|null $bindtype - s or i
     * @return int - the count of affected records (1 or 0 if no change) or -1 if error
     */
    function updateRow(string $table, array $data, string $keyname, string $prikey, ?string $bindtype = 'i'):int
    {
        // create update query
        // $data is array of fieldnames => {data, bindtype} or, for backward compatibility, 1 dimensional array of fieldname => data, and bindtype is inferred
        // return -1 for error; 0 for no change; or num records affected
        if (!empty($data)) {
            $query = 'UPDATE `' . $table . '` SET ';
            $this->bindvalues = array();
            $this->bindtypes = "";
            $query .= implode(', ', $this->processData($data));
            $query .= ' WHERE `' . $keyname . '`=? LIMIT 1 ';
            $this->bindtypes .= $bindtype;
            array_push($this->bindvalues, $prikey);

            if ($this->debug) {
                echo $query . ' ' . $this->bindtypes;
                print_r($this->bindvalues);
            }
            // $result returns affected rows, -1 if error
            return $this->execute_query($query);
        } else {
            if (!$this->suppressText) {
                echo 'Error: input array is empty.';
            }
            return -1; // simulate an update error
        }
    }

    /**
     * deleteRow()
     * Deletes one or more rows
     *
     * @param string $table
     * @param string $keyField      - field name of primary key
     * @param int|string $prikey    - the primary key
     * @param string|null $bindtype - s or i
     * @return int - the count of affected records or false if error
     */
    function deleteRow(string $table, string $keyField, $prikey, ?string $bindtype = 'i'): int
    {
        // create delete query
        if ($keyField != '' and !empty($prikey)) {
            $pq_query = 'DELETE FROM `' . $table . '` WHERE `'.$keyField.'` = ?';
            $this->bindvalues = array($prikey);
            $this->bindtypes = $bindtype;
            if ($this->debug) echo $pq_query,$prikey;
            // $result returns affected rows, false if error
            return $this->execute_query($pq_query);
        } else {
            if (!$this->suppressText) {
//                echo 'Error: Parameter missing.';
            }
            return false;
        }
    }

    /**
     * processData
     * Extract "where" array and bindtype from input array
     * If value is array, then it contains both bindtype and value
     * @param array $data
     * @return array
     */
    private function processData(array $data):array
    {
        $where = array();
        $keys = array_keys($data);
        foreach ($keys as $key) {
            if (is_array($data[$key])) {
                // expects array of data=>x, bindtype=> {i,d,s, or b}
                if (is_null($data[$key]['data'])) {
                    $where[] = '`' . $key . '` = NULL';
                } else {
                    $where[] = '`' . $key . '` = ?';
                    $this->bindtypes .= $data[$key]['bindtype'];
                    array_push($this->bindvalues, $data[$key]['data']);
                }
            } else { // extract bindtype from the contents
                if (is_null($data[$key])) {
                    $where[] = '`' . $key . '` = NULL';
                } else {
                    if ($data[$key] != '') {
                        $where[] = '`' . $key . '` = ?';
                        $this->bindtypes .= $this->detectType($data[$key]);
                        array_push($this->bindvalues, $data[$key]);
                    } else {
                        $where[] = '`' . $key . '` = ""';
                    }
                }
            }
        }
        return $where;
    }

    /**
     * detectType
     * Detect value type to generate bind parameter
     * @param mixed $value
     * @return string
     **/
    protected function detectType($value):string
    {
        switch (gettype($value)) {
            case 'string':
                return 's';
            case 'integer':
                return 'i';
            case 'blob':
                return 'b';
            case 'double':
                return 'd';
        }
        return 's';
    }

    /**
     * InnoDB Transaction Processing wrappers
     *
     * beginTrans
     * @return bool - true if transaction is successfully begun
     */
    public function beginTrans():bool
    {
        try {
            mysqli_begin_transaction($this->dblink);
            $this->inTransaction = true;
        } catch (mysqli_sql_exception $e) {
            $this->inTransaction = false;
        }
        return $this->inTransaction;
    }

    /**
     * commitTrans
     */
    public function commitTrans()
    {
        mysqli_commit($this->dblink);
        $this->inTransaction = false;
    }

    /**
     * rollbackTrans
     */
    public function rollbackTrans()
    {
        mysqli_rollback($this->dblink);
        $this->inTransaction = false;
    }

    /**
     * Indicates that a transaction is in progress
     * @return bool
     */
    public function isInTransaction()
    {
        return $this->inTransaction;
    }

    /**
     * @param string $bindtypes
     */
    public function setBindtypes(string $bindtypes)
    {
        $this->bindtypes = $bindtypes;
    }

    /**
     * @param array|null $bindvalues
     */
    public function setBindvalues(?array $bindvalues)
    {
        $this->bindvalues = $bindvalues;
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param bool $errorsOnly
     */
    public function setErrorsOnly(bool $errorsOnly)
    {
        $this->errorsOnly = $errorsOnly;
    }

    /**
     * @param bool $suppressText
     */
    public function setSuppressText(bool $suppressText)
    {
        $this->suppressText = $suppressText;
    }

    private function refValues($arr){
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }

}
