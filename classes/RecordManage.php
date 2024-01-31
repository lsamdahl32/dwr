<?php
/**
 * RecordManage v2
 * a class for editing and adding records using the column array
 * based on manageInclude.php
 * Created by PhpStorm.
 * User: Lee
 * Date: 12/4/2019; Renamed from RecordManage 2/26/2020
 * Lee updated all escaping to use gls_esc_xxx functions 8/19/2020
 * Highly modified for use with ATReports.php class and full AJAX operation 6/1/2021
 *
 */

require_once(PLMPATH . 'classes/RecordLocking.php');

define('MYSQLI_CODE_DUPLICATE_KEY', 1062);
define('MYSQLI_CODE_CONSTRAINT_FAILED', 1451);

class RecordManage
{
    private string $db = '';
    private DBEngine $dbe; // dbEngine instance
    private string $identifier;
    private array $dbVars = array();
    private string $table = '';
    private string $keyField = '';
    private string $keyFieldType = ''; // NOTE - AVOID STRING KEYFIELD TYPES
    private $currentID = 0;
    private $parentKey = 0; // if a sub table, this is the primary key of the parent table
    private string $parentKeyField = ''; // if a sub table, this is the primary key field name of the parent table
    private $errorArray = array();
    private bool $showDuplicate = false;
    private string $copyField = '';
    private string $insertCallback = '';
    private string $updateCallback = ''; // if JS function of same name exists, call it too
    private string $mode = '';
    private bool $allowEditing = true;
    private bool $allowDelete = false;
    private bool $saveReturnsToEdit = false;
    private bool $saveAndAddNew = false;
    private $deleteRestrictions = array();
    private bool $noProfileMode = false;
    private string $msg = '', $labelWidth = '185px', $editTitle = 'Record', $subTitle = '', $addComment = '';
    private string $previewButton = '';
    private string $loggingCallback = '';
    private bool $useLocking = true; // use this to disable record locking
    private RecordLocking $locking;
    private bool $hasTinymce = false;
    private array $tinymceSettings = array();
//    private AtLogging $atLog;
    private string $modeCallback = ''; // JS callback
    private bool $editModeNoColumns = false;
    private string $customProfileCallback = ''; // same name for PHP function and JS function to fill the form
    private bool $allowHideProfile = false;
    private string $imagePath = '';


    /**
     * RecordManage constructor.
     *
     * @param string $identifier
     * @param string $db
     * @param string $table
     * @param string $keyField
     * @param string|null $keyFieldType     (s or i)
     * @param bool $useLocking
     * @param float|null $recordLockTimeout - in minutes
     * @param bool $debug
     */
    public function __construct(string $identifier, string $db, string $table, string $keyField, ?string $keyFieldType = 'i', bool $useLocking = true, ?float $recordLockTimeout = 5, bool $debug = false)
    {
        $this->identifier = $identifier;
        $this->db = $db;
        $this->table = $table;
        $this->keyField = $keyField;
        $this->keyFieldType = $keyFieldType;
        $this->useLocking = $useLocking;
        if ($this->useLocking) $this->locking = new RecordLocking($db, $table, $keyField, $recordLockTimeout);

        $this->dbe = new DBEngine($this->db, $debug, false, true);
    }

    /**
     * @param array $editColumnArray
     * @return string - any message
     */
    public function processFromPOST(array $editColumnArray):string
    {
        $addNewRecord = false;
        if (isset($_POST['mode' . $this->identifier]) and $_POST['mode' . $this->identifier] == 'add') {
            $this->setCurrentID(0);
            $addNewRecord = true;
        }
        if (isset($_POST['duplicatebutton']) and $_POST['duplicatebutton'] == 'Duplicate') {
            $this->setCurrentID(0);
            $addNewRecord = true;
            $_POST[$this->copyField] = $_POST[$this->copyField] . ' (copy)';
        }
        foreach ($editColumnArray as $col) {
            if ($col['edit'] or $col['editadd']) {
                if (isset($_POST[$col['field']])) {
                    if ($col['type'] == 's' or $col['type'] == 'i') {
                        if ($col['format'] == 'currency' or $col['format'] == 'accounting') {
                            $this->dbVars[$col['field']]['data'] = $_POST[$col['field']]; // type double (or float)
                            $this->dbVars[$col['field']]['bindtype'] = 'd'; // type double (or float)
                        } else {
                            if (isset($col['allowBackslashes']) and $col['allowBackslashes']) {
                                $this->dbVars[$col['field']]['data'] = mysqli_real_escape_string($this->dbe->dblink, trim($_POST[$col['field']]));
                            } else {
                                $this->dbVars[$col['field']]['data'] = stripslashes(trim($_POST[$col['field']])); // remove clean_param for strings to prevent html entities and double escaping 4/6/20
                            }
                            $this->dbVars[$col['field']]['bindtype'] = $col['type'];
                        }
                    } elseif ($col['type'] == 'd' or $col['type'] == 'dt') { // date type
                        $this->dbVars[$col['field']]['data'] = niceDate($_POST[$col['field']], true, 'Y-m-d H:i:s');
                        $this->dbVars[$col['field']]['bindtype'] = 's';
                    } elseif ($col['type'] == 'm') { // time
                        $this->dbVars[$col['field']]['data'] = date('H:i:s', strtotime($_POST[$col['field']]));
                        $this->dbVars[$col['field']]['bindtype'] = 's';
                    } elseif ($col['type'] == 'b') { // boolean
                        $this->dbVars[$col['field']]['data'] = $_POST[$col['field']];
                        $this->dbVars[$col['field']]['bindtype'] = 'i';
                    } elseif ($col['type'] == 'r') { // radio buttons
                        $this->dbVars[$col['field']]['data'] = stripslashes($_POST[$col['field']]);
                        $this->dbVars[$col['field']]['bindtype'] = 's';
                    } elseif ($col['type'] == 't') { // textarea field
                        $this->dbVars[$col['field']]['data'] = stripcslashes(trim($_POST[$col['field']]));
                        $this->dbVars[$col['field']]['bindtype'] = 's';
                        if (isset($col['mceNonEditable'])) {
                            $this->dbVars[$col['field']]['data'] = $this->unprotectMceCodes($this->dbVars[$col['field']]['data']);
                        }
                    } elseif ($col['type'] == 'ms') { // multi-select list box - POST is an array
                        // do nothing until after save
                    } elseif ($col['type'] == 'yn') { // Yes/No dropdown for enum field
                        $this->dbVars[$col['field']]['data'] = stripslashes($_POST[$col['field']]);
                        $this->dbVars[$col['field']]['bindtype'] = 's';
//                    } elseif ($col['type'] == 'upload') { // file upload hidden field
//                        $this->dbVars[$col['field']]['data'] = $_POST[$col['field']];
//                        $this->dbVars[$col['field']]['bindtype'] = 's';
                    }
                } elseif ($col['editType'] == 'multi_checkbox' or $col['editType'] == 'multi_select') {
                    // do nothing until after record is saved
                } else { // boolean (tinyint(1)) where checkbox is not checked
                    if ($col['type'] == 'b') {
                        $this->dbVars[$col['field']]['data'] = 0;
                        $this->dbVars[$col['field']]['bindtype'] = 'i';
                    }
                }
            } else {
                if ($this->isRequired($col) and isset($_POST[$col['field']])) {
                    // hidden fields
                    $this->dbVars[$col['field']]['data'] = stripslashes(trim($_POST[$col['field']]));
                    $this->dbVars[$col['field']]['bindtype'] = $col['type'];
                }
            }
        }
//            print_r($_POST).'<br>';
//            print_r($this->dbVars);
//        exit;
        // validate input
        $this->errorArray = $this->checkValidation($this->dbVars, !$addNewRecord, $this->currentID, $editColumnArray);
//            print_r($this->errorArray);
//            print_r($this->dbVars);
        if (count($this->errorArray) == 0) {
            if (true) {//!isRepeatPost($_POST)) { // prevent re-entry
//                hashPost($_POST);
                // if post mode = add, insert new record
                if ($addNewRecord) {
                    $result = $this->dbe->insertRow($this->table, $this->dbVars);
                    if ($result) {
                        if (is_bool($result)) {
                            $this->setCurrentID($this->dbVars[$this->keyField]['data']);
                        } else {
                            $this->setCurrentID($result);
                        }
                        $this->saveRelatedTables($editColumnArray, $result);
                        if (isset($this->insertCallback) and function_exists($this->insertCallback)) { // perform any custom action upon updating
                            call_user_func($this->insertCallback, $this->dbVars, $this->currentID);
                        }
                        $errormsg = 'The record was added.';
                        if (is_callable($this->loggingCallback)) {
                            call_user_func($this->loggingCallback, $this->currentID, $this->table, 'insert', $this->dbVars, $errormsg);
                        } else {
                            // call AtLogging class
//                            if ($this->atLog) {
//                                $this->atLog->insertLog($this->table, $this->currentID, serialize($this->dbVars), 'Record added by RecordManage class.');
//                            }
                        }
                    } else {
//                        echo $this->dbe->error_msg;
                        $errormsg = 'The new record was NOT saved. ' . $this->dbe->error_msg;
                    }
                } else {
                    // else update existing record
                    if (($this->useLocking and $this->locking->isLockedByMe($this->currentID)) or !$this->useLocking) {
                        if (!is_callable($this->loggingCallback)) { // call logging class before save to get the old data
                            if ($this->atLog) {
//                                $this->atLog->prepareUpdateLog($this->db, $this->table, '', $this->keyField, $this->currentID, serialize($this->dbVars), 'Record updated by RecordManage class.');
                            }
                        }
                        $result2 = 0;
                        $result = $this->dbe->updateRow($this->table, $this->dbVars, $this->keyField, $this->currentID, $this->keyFieldType);
                        $this->unlock_record();
                        if ($result > -1) $result2 = $this->saveRelatedTables($editColumnArray, $result);
                        if ($result + $result2 > 0) {
                            if (isset($this->updateCallback) and function_exists($this->updateCallback)) { // perform any custom action upon updating
                                call_user_func($this->updateCallback, $this->dbVars, $this->currentID);
                            }
                            $errormsg = 'The changes were saved.';
                            if (is_callable($this->loggingCallback)) {
                                call_user_func($this->loggingCallback, $this->currentID, $this->table, 'update', $this->dbVars, $errormsg);
                            } else {
                                // call AtLogging class to save the log entry
//                                if ($this->atLog) {
//                                    $this->atLog->commitLog();
//                                }
                            }
                        } else {
                            $errormsg = 'No changes were saved. ' . $this->dbe->error_msg;
                            if ($this->atLog) { // clear the data in the logging class
//                                $this->atLog->cancelCommit();
                            }
                        }
                    } else {
                        $errormsg = 'The record lock failed. No changes were saved.';
                    }
                }
            } else {
                // user double-clicked! ignore.
            }
        } else {
            $errormsg = 'The record was NOT saved. See below:'.print_r($this->errorArray, 1);
        }
        return $errormsg;
    }

    /**
     * @param array $column_array
     * @param string|null $msg
     * @param bool $showSearchOnly
     * @return string mode
     */
    public function processTableSave(array $column_array, ?string &$msg, ?bool &$showSearchOnly):string
    {
        $this->msg = '';
        $this->mode = 'view';
        if ((isset($_POST[$this->keyField]) and isset($_POST['btnSubmit' . $this->identifier])) or
            (isset($_POST['btnAdd' . $this->identifier])) or
            (isset($_POST['btnAdd'])) or
            ($this->allowDelete and isset($_POST['btnDelete' . $this->identifier])) or
            (isset($_POST['linkField']) and $_POST['linkField'] != '') or
            (isset($_POST['duplicatebutton']) and $_POST['duplicatebutton'] == 'Duplicate')) {

            if (isset($_POST[$this->keyField]) and isset($_POST['btnSubmit' . $this->identifier]) or (isset($_POST['duplicatebutton']) and $_POST['duplicatebutton'] == 'Duplicate')) { // from submit button in Edit or Add mode
                $this->setCurrentID($_POST[$this->keyField]);
                $this->msg = $this->processFromPOST($column_array);
                if (isset($_POST['mode' . $this->identifier]) and $_POST['mode' . $this->identifier] == 'add') {
                    if ($this->msg == 'The new record was NOT saved. ' . $this->dbe->error_msg) {
                        // add mode failed
                        $this->mode = 'add';
                        if ($this->dbe->error_num == MYSQLI_CODE_DUPLICATE_KEY) {
                            $this->msg .= ' This is a duplicate record.';
                        }
                    } elseif ($this->msg == 'The record was added.') {
                        // add mode succeeded
                        if ($this->saveReturnsToEdit) {
                            $this->mode = 'edit';
                        } else {
                            $this->mode = 'view';
                        }
                    } else {
                        // validation failure
                        $this->mode = 'add';
                    }
                } elseif (strpos($this->msg, 'NOT saved') > 0) {
                    // validation failure
                    $this->mode = 'edit';
                } else {
                    if ($this->saveReturnsToEdit) {
                        $this->mode = 'edit';
                    } else {
                        $this->mode = 'view';
                    }
                }
                $showSearchOnly = true;
            } elseif (isset($_POST['btnAdd' . $this->identifier]) or isset($_POST['btnAdd'])) { // enter Add mode
                $this->setCurrentID(0);
                $this->mode = 'add';
                $showSearchOnly = true;
            } elseif ($this->allowDelete and isset($_POST['btnDelete' . $this->identifier])) { // delete was confirmed
                $this->setCurrentID($_POST['btnDelete' . $this->identifier]);
                $deleteOK = true;
                if (isset($this->deleteRestrictions)) {
                    // restrict delete if related table has linked records
                    $pq_query = 'SELECT * FROM ' . $this->deleteRestrictions['table'] . ' WHERE ' . $this->deleteRestrictions['field'] . '= ? ';
                    $this->dbe->setBindtypes("i");
                    $this->dbe->setBindvalues(array($this->currentID));
                    $linkedrows = $this->dbe->execute_query($pq_query);  // execute query
                    if ($linkedrows) {
                        $this->msg = $this->deleteRestrictions['message'];
                        $this->mode = 'edit';
                        $showSearchOnly = true;
                        $deleteOK = false;
                    }
                }
                if ($deleteOK) {
                    if (!is_callable($this->loggingCallback)) { // call logging class before delete to get the old data
                        if ($this->atLog) {
//                            $this->atLog->prepareDeleteLog($this->db, $this->table, $this->keyField, $this->currentID, 'Record deleted by RecordManage class.');
                        }
                    }
                    $result = $this->dbe->deleteRow($this->table, $this->keyField, $this->currentID, 'i');
                    if ($result == 0) {
                        $this->msg = 'Unable to delete record.';
                        if ($this->dbe->error_num == MYSQLI_CODE_CONSTRAINT_FAILED) {
                            $this->msg .= ' There are related sub-records preventing this operation.';
                        }
                        $this->mode = 'view';
                        $showSearchOnly = true;
                    } else {
                        if (is_callable($this->loggingCallback)) {
                            call_user_func($this->loggingCallback,$this->currentID, $this->table, 'delete', array(), 'Record was deleted');
                        } else {
                            // call AtLogging class to save the log entry
//                            if ($this->atLog) {
//                                $this->atLog->commitLog();
//                            }
                        }
                        $this->msg = 'The record was deleted.';
                        $this->setCurrentID(0);
                        $this->mode = 'list';
                        $showSearchOnly = false;
                    }
                }
            } elseif ($_POST['linkField'] == $this->keyField) { // from View button in main list view
                $this->setCurrentID($_POST['linkID']);
                if ($this->currentID != '' and $this->currentID != '0') { // relies on implicit type conversion
                    $this->mode = 'view';
                    $showSearchOnly = true;
                }
            }
            // show success messages in green
            if ($this->msg == 'The record was added.' or $this->msg == 'The changes were saved.') {
                $this->msg = '<span class="good_color">' . $this->msg . '</span>';
            }
        }
        $msg = $this->msg;
        return $this->mode;
    }

    /**
     * @param array|false $row - can be false if adding a new record
     * @param array $editColumnArray
     */
    public function loadRecord($row, array $editColumnArray)
    {
        foreach ($editColumnArray as $col) {
            if (isset($row[$col['field']])) {
                $this->dbVars[$col['field']]['data'] = $row[$col['field']];
            } elseif ($col['type'] == 'i') {
                $this->dbVars[$col['field']]['data'] = 0;
            } else {
                $this->dbVars[$col['field']]['data'] = '';
            }
        }
    }

    /**
     * @param string|array $sql
     * @param string $name
     * @param $value
     * @param bool|null $allowNone
     * @param string|null $attributes
     * @param string|null $type
     * @param array|null $col
     * @return string
     */
    private function selectBox($sql, string $name, $value, ?bool $allowNone = false, ?string $attributes = '', ?string $type = 'i', ?array $col = array()):string
    {
        if (isset($col['width'])) {
            $width = $col['width'] . 'in';
        } else {
            $width = '100%';
        }
        $html = '<select name="' . $name . '" id="' . $name . '_' . $this->identifier . 'e" style="max-width: '.$width.';" ' . $attributes . '>' . "\n";
        if (isset($col['altAllowNoneLabel'])) {
            $label = $col['altAllowNoneLabel'];
        } else {
            $label = '--Please Select--';
        }
        if ($allowNone) {
            if ($type == 'i') {
                $html .= '<option value="0" selected="selected" >' . $label . '</option>' . "\n";
            } else { // use null for string types
                $html .= '<option value="" selected="selected" >' . $label . '</option>' . "\n";
            }
        }
        if (isset($col['addNew'])) {
            $html .= '<option value="add" >--Add New ' . $col['heading'] . '--</option>' . "\n";
        }
        if (is_array($sql)) { // simple array for select - no db lookup
            if ($col['useKeys']) {
                $rows = array();
                foreach ($sql as $key => $item) {
                    $rows[] = array('id' => $key, 'item' => $item);
                }
    //            $rows = $sql; // $sql is a "rows" db style array, field will be the key
            } else {
                $rows = array();
                foreach ($sql as $item) {
                    $rows[] = array('id' => $item, 'item' => $item);
                }
            }
        } else {
            if (isset($col['db'])) {
                $dbs = new DBEngine($col['db']);
            } else {
//                $dbs = $this->dbe;
                $dbs = new DBEngine($this->db);
            }
            $rows = $dbs->execute_query($sql);  // execute query
            $dbs->close();
        }
        if ($rows) {
            foreach ($rows as $row) {
                if ($value == $row['id']) {
                    $html .= '<option value="' . gls_esc_attr($row['id']) . '" selected="selected" >' . strip_tags($row['item']) . '</option>' . "\n";
                } else {
                    $html .= '<option value="' . gls_esc_attr($row['id']) . '" >' . strip_tags($row['item']) . '</option>' . "\n";
                }
            }
        }
        $html .= '</select>' . "\n";
        return $html;
    }

    /**
     * @param bool $requireSameUser
     * @return bool
     */
    private function unlock_record(?bool $requireSameUser = false):bool
    {
        // delete specified lock record if exists
        if ($this->useLocking) {
            $this->locking->removeRecordLock($this->currentID, $requireSameUser);
        }
        return true;
    }

    /**
     * @param array $column_array
     * @return array
     */
    public function getProfileJSON(array $column_array):array
    {
        global $currencySymbol;
        $output = array($this->keyField=>$this->currentID);
        if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;
        foreach ($column_array as $col) {
            if (((($col['profile'] and $this->mode != 'add') or $col['edit'])
                            or ($this->mode == 'add' and ($col['editadd'] or $col['showAdd'])))
                    and (substr($col['type'], 0, 5) != 'link:')
                    and (substr($col['type'], 0, 9) != 'callback:')) {

                if ((isset($col['ifEquals']) and ($this->dbVars[$col['ifEquals']['field']]['data'] == $col['ifEquals']['value'])) or !isset($col['ifEquals'])) {
                    if (isset($col['searchName'])) {
                        $output[$col['searchName']] = $this->showOneProfile($col);
                    } else {
                        $output[$col['field']] = $this->showOneProfile($col);
                    }
                }
            }
        }
        return $output;
    }

    /**
     * @param array $column_array
     * @return string
     */
    private function getEditPageJSON(array $column_array):string
    {
        $output = array($this->keyField=>$this->currentID);
        foreach ($column_array as $col) {
            if (($this->mode == 'edit' and ($col['edit'] or $col['showEdit'])) or ($this->mode == 'add' and ($col['edit'] or $col['showAdd'] or $col['editadd']))) {
                $output[$col['field']] = $this->showOneInput($col);
            }
        }
        return json_encode($output);
    }

    /**
     * @param array $column_array
     * @param bool $isSubTable
     */
    public function profileEntry(array $column_array, ?bool $isSubTable = false)
    {
        ?>
        <style>
            .form_label<?= $this->identifier ?> {
                width: <?=(is_numeric($this->labelWidth))? $this->labelWidth.'px':$this->labelWidth?>;
            }
            <?php if ($this->noProfileMode) { ?>
            .readMode<?= $this->identifier ?> {
                display: none;
            }
            .editMode<?= $this->identifier ?> {
                display: block;
            }
            <?php } else { ?>
            .readMode<?= $this->identifier ?> {
                display: block;
            }
            .editMode<?= $this->identifier ?> {
                display: none;
            }
            <?php } ?>
        </style>
        <div class="profileinfo profileinfo_padding" id="mcProfile">
            <div class="profileHeadingLine">
                <h2 id="profileHeading<?= $this->identifier ?>">
                    <?php echo $this->subTitle; ?>
                </h2>
                <div class="profileHeadingLineButtons">
                    <button class="refreshProfile buttonBar readMode<?= $this->identifier ?>" data-type="show" title="Refresh Record">
                        <i class="bi-arrow-clockwise"></i>
                    </button>
                    <?php if (!$isSubTable) {?>
                    <button class="buttonBar ReturntoSearchResults" id="ReturntoSearchResults"  title="Return to Search Results">
                        <i class="bi-arrow-return-left"></i>&nbsp;Return
                    </button>
                    <?php }
                    if ($this->allowHideProfile) { ?>
                        <button class="buttonBar" id="hideProfileView<?=$this->identifier ?>"  title="Hide Profile Section">
                            <i class="bi-arrows-collapse"></i>&nbsp;Hide
                        </button>
                    <?php } ?>
                </div>
                <div id="timeoutWarning<?= $this->identifier ?>" class="errorMsg" style="display: none;">
                    This edit page will expire within 30 seconds. Click
                    <a id="resetTimeout<?= $this->identifier ?>" class="bluelink">here</a> to reset timeout.
                </div>
            </div>
            <?php
            // resort the array by 'profile' and 'order'
            $column_array = sortArray($column_array, ['profileOrder', 'order'], [SORT_ASC, SORT_ASC]);
            ?>
            <div id="hideProfile<?=$this->identifier?>">
                <?php if ($this->addComment != '') echo '<p id="addComment'.$this->identifier.'" style="display: none;">' . $this->addComment . '</p>';?>
                <div id="saveMessage<?=$this->identifier?>" class="errorMsg" style="display: none; margin-bottom: 1em;"></div>
                <form id="myEditForm<?= $this->identifier ?>">
                    <input type="hidden" name="<?= $this->keyField ?>" id="<?= $this->keyField . '_' . $this->identifier . 'e' ?>" value="<?=$this->dbVars[$this->keyField]['data']?>"/>
                    <input type="hidden" name="linkField" id="linkField<?=$this->identifier?>" value="<?=$this->keyField?>" />
                    <input type="hidden" name="linkID" id="linkID<?=$this->identifier?>" value="" />
                    <input type="hidden" name="editLinkID<?=$this->identifier?>" id="editLinkID<?=$this->identifier?>" value="" />
                    <input type="hidden" name="mode" id="mode<?=$this->identifier?>" value="<?=$this->mode?>">
                    <?php
                    $hideEditMode = ''; // if a program uses a custom profile page, then hide the entire profile-flex-colsxx div
                    if ($this->customProfileCallback != '') {
                        if (function_exists($this->customProfileCallback)) { // show structure for custom profile
                            call_user_func($this->customProfileCallback, $this->dbVars, $this->currentID);
                            $hideEditMode = 'editMode'.$this->identifier;
                        }
                    }
                    ?>
                    <div class="<?=$hideEditMode?>">
                        <div class="profile-flex-cols" id="profile-flex-cols<?=$this->identifier?>">
                            <div class="profile-left-col">
                        <?php
                        $i = 0;
                        $j = 0;
                        $manualColumns = false;
                        foreach ($column_array as $col) { // count visible profile items
                            if (isset($col['newEditColumn'])) {
                                $manualColumns = true;
                                break;
                            }
                            if ((isset($col['profile']) and $col['profile']) or $col['edit'] or $col['showEdit'] or $col['showAdd'] or $col['editadd']) {
                                $j++;
                            }
                        }
                        if ($j == 0) $j = count($column_array); // if profile element not set
                        if ($j > 3) $j = floor(($j + 1) / 2); // find the midpoint if more than 3 items

                            $oldid = '';
                            foreach ($column_array as $col) {
                                if ((isset($col['profile']) and $col['profile']) or $col['edit'] or $col['showEdit'] or $col['showAdd'] or $col['editadd']) {
                                    // need to handle fields that only show up in edit mode


                                    if (isset($col['fieldGroup'])) {
                                        if ($col['fieldGroup'] != $oldid) {
                                            if ($oldid != '') {
                                                echo '</div></div>';
                                            }
                                            // fieldGroup changed
                                            echo '<div class="rmFieldGroup" ><span class="form_label form_label'.$this->identifier.'">' . $col['fieldGroup'] . '</span><div class="rmFieldGroupInner">';
                                            $oldid = $col['fieldGroup'];
                                        }
                                    } else {
                                        if ($oldid != '') {
                                            echo '</div></div>';
                                            $oldid = '';
                                        }
                                        if ((!$manualColumns and $i == $j) or ($manualColumns and $col['newEditColumn'])) { // if midpoint, then new column
//                                            if ($oldid != '') {
//                                                echo '</div></div>';
//                                                $oldid = '';
//                                            }
                                            echo '</div><div class="profile-right-col">';
                                        }
                                    }
                                    if (isset($col['help'])) {
                                        $help = ' title="' . $col['help'] . '"" ';
                                    } else {
                                        $help = '';
                                    }
                                    ?>
                                    <div <?=(!$col['profile'] and ($col['edit'] or $col['showEdit'] or $col['editadd'] or $col['showAdd']))?'class="editMode' . $this->identifier.'"':''?>>
                                        <div class="form_rows">
                                            <div class="form_label form_label<?= $this->identifier ?>">
                                                <?php
                                                echo $col['heading'];
                                                ?>:
                                            </div>
                                            <div class="form_cell" <?=$help?>>
                                                <div class="readMode<?= $this->identifier ?>" id="<?=(isset($col['searchName']))?$col['searchName'].'_rmc':$col['field'].'_rmc'?>">
                                                    <?php echo $this->showOneProfile($col); ?>
                                                </div>
                                                <?php if ($this->allowEditing) { ?>
                                                <div class="editMode<?= $this->identifier ?>">
                                                    <?php if (isset($col['button'])) echo '<div class="rmColumnHasButton">'; // if a button is included, use flexbox ?>
                                                    <?php
                                                    if ($col['field'] == 'password') {
                                                        if ($this->mode == 'add') {
                                                            echo '
                                                    <input type="password" name="password" id="password" size="16"
                                                           maxlength="32" ' . $col['attributes'] . '
                                                           autocomplete="new-password"/>';
                                                            if (substr($this->db, 0, 6) == 'atools') {
                                                                echo '
                                                    <i id="str4" class="bi-x-circle bad_color smaller_icon" title="Passwords must contain upper and lower case and numbers."></i>';
                                                            }
                                                        } elseif ($this->mode != 'add') {
                                                            echo '
                                                    <button id="change_password_button" type="button"
                                                           class="buttonBar"
                                                           style="width: 120px;" ';
                                                            if ($this->allowEditing) {
                                                                echo '>';
                                                            } else {
                                                                echo 'disabled' . '>';
                                                            }
                                                            echo '
                                                        <i class="bi-shield-exclamation smaller_icon"></i>
                                                        &nbsp;Change Password
                                                    </button>
                                                    <span id="new_password_span" style="display: none">
                                                        <span style="display: inline-block; width: 130px;">New Password:</span>
                                                        <input type="password" name="password" id="password" size="16" disabled
                                                               maxlength="32"
                                                               autocomplete="new-password" style="margin-bottom: 2px;"/>';
                                                            if (substr($this->db, 0, 6) == 'atools') {
                                                                echo '&nbsp;<span id="str4" class="bi-x-circle bad_color smaller_icon" title="Passwords must contain upper and lower case and numbers.">&nbsp;&nbsp;</span>';
                                                            }
                                                            echo '
                                                        <br/>
                                                        <span  style="display: inline-block; width: 130px;">Confirm New Password:</span>
                                                        <input type="password" name="password2" id="password2" size="16" disabled
                                                               maxlength="32"
                                                               autocomplete="new-password"/>
                                                        <span id="str5" class="bi-x-circle bad_color smaller_icon" title="Passwords must match">&nbsp;&nbsp;</span>
                                                        <div class="form_error" id="password_err">' . $this->errorArray['password'] . '</div>
                                                    </span>';
                                                            if ($this->isRequired($col)) echo '<span class="required errorMsg"> *</span>';
                                                            echo '<div class="form_error" id="password_'.$this->identifier.'e_err"></div>';
                                                        }
                                                    } else {
                                                    ?>
                                                    <div class="editModeInputs<?= $this->identifier ?>" data-field="<?=$col['field']?>">
                                                    <?php echo $this->showOneInput($col); ?>
                                                    </div>
                                                    <?php
                                                    }
                                                    if (isset($col['button'])) { // in-line buttons included with input
                                                        if (strpos($col['button']['onclick'], 'filemanager') > 0) { // responsive file manager button
                                                            if (!isset($col['button']['ifCount']) or (isset($col['button']['ifCount']) and $this->dbVars[$col['button']['ifCount']]['data'] != 0)) {
                                                                echo '
                            <a href="' . $col['button']['onclick'] . '/dialog.php?type=2&field_id=' . $col['field'] . '_' . $this->identifier . 'e&fldr=' . $col['button']['path'] . '"
                               class="btn iframe-btn buttonBar"
                               style="width: 80px;"
                               id="' . $col['button']['name'] . '"
                               title="' . $col['button']['help'] . '"';
                                                                if (!$this->allowEditing) echo 'disabled';
                                                                echo '><i class="bi-search smaller_icon"></i>&nbsp;' . $col['button']['value'] . '</a>';
                                                                if (isset($col['button']['comment'])) {
                                                                    echo '<div class="itemComment">' . $col['button']['comment'] . '</div>';
                                                                }
                                                            }
                                                        } else { // generic button
                                                            if (!isset($col['button']['width'])) $col['button']['width'] = "100px";
                                                            echo '
                        <button name="' . $col['button']['name'] . '" type="button"
                               class="buttonBar"
                               id="' . $col['button']['name'] . '"
                               style="width: ' . $col['button']['width'] . ';"
                               onClick="' . $col['button']['onclick'] . '()"';
                                                            if (!$this->allowEditing) echo 'disabled';
                                                            echo '>' . $col['button']['value'] . '</button>';
                                                        }
                                                        echo '</div>'; // close the flexbox div
                                                    }
                                                    if (isset($col['comment'])) {
                                                        echo '<div class="itemComment" id="' . $col['field'] . '_' . $this->identifier . 'e_comment">' . $col['comment'] . '</div>';
                                                    }
                                                    ?>
                                                </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $i++;
                                } else {
                                    if ($this->isRequired($col)) {
                                        // if a field is required, but not edited or shown, create a hidden input for it and set it to 'default'
                                        ?>
                                        <div class="editModeInputs<?= $this->identifier ?>" data-field="<?=$col['field']?>">
                                        <?php echo '<input type="hidden" name="' . $col['field'] . '" id="' . $col['field'] . '_' . $this->identifier . 'e" value="' . gls_esc_attr($col['default']) . '" />'; ?>
                                        </div>
                                        <?php
                                    }
                                }
                            }
                            if ($oldid != '') {
                                echo '</div></div>';
                            }
                            ?>
                            </div>
                        </div>
                    </div>
                    <span class="required errorMsg editMode<?= $this->identifier ?>">* = Required</span>
                    <div class="rmProfileButtons">
                        <?php if ($this->allowEditing or $isSubTable or $this->allowDelete) { // only show the <hr> if there are some buttons ?>
                            <hr>
                        <?php } ?>
                        <?php
                        if ($this->allowEditing) {
                            ?>
                            <div class="editMode<?= $this->identifier ?>">
                                <div class="rmEditModeButtons">
                                    <?php
                                    if ($this->saveAndAddNew) echo '<button type="button" name="saveAddNew" id="saveAddNew'.$this->identifier.'" class="buttonBar editMode'.$this->identifier.'" >
                                        <i class="bi-save"></i>&nbsp;
                                        Save and Add New
                                        </button>';
                                    ?>
                                    <button type="submit" name="btnSubmit<?= $this->identifier ?>" id="btnSubmit<?= $this->identifier ?>" class="buttonBar">
                                        <i class="bi-save"></i>&nbsp;
                                        Save
                                    </button>
                                    <?php if ($this->showDuplicate) echo '<input type="button" class="buttonBar" name="duplicatebutton" id="duplicatebutton'.$this->identifier.'" value="Duplicate" class="duplicatebutton"/>'; ?>
                                    <?php if ($this->allowDelete and $this->noProfileMode) { ?>
                                        <button type="button" class="btnDelete buttonBar" id="btnDeleteEdit<?= $this->identifier ?>"
                                               data-id="<?= gls_esc_attr($this->dbVars[$this->keyField]['data']) ?>" data-identifier="<?= $this->identifier ?>"
                                               title="Delete this record.">
                                            <i class="bi-x-octagon"></i>&nbsp;
                                            Delete
                                        </button>
                                    <?php } ?>
    <!--                                <input type="reset" value="Reset" id="resetForm<?//= $this->identifier ?>"-->
    <!--                                       class="buttonBar"/>-->
                                    <button id="btnClose<?= $this->identifier ?>" type="button" class="buttonBar"
                                            title="Cancel editing.">
                                        <i class="bi-arrow-return-left"></i>&nbsp;
                                        Close
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                        <div id="profileButtons" class="readMode<?= $this->identifier ?>">
                            <div class="rmReadModeButtons">
                                <?php if ($this->allowEditing) { ?>
                                <button id="editRecord<?= $this->identifier ?>" type="button" class="buttonBar" title="Edit this record's details.">
                                    <i class="bi-pencil"></i>&nbsp;
                                    Edit <?= $this->editTitle ?>
                                </button>
                                <?php } ?>
                                <?php if ($isSubTable) {?>
                                    <button id="btnCloseProfile<?= $this->identifier ?>" type="button" class="buttonBar"
                                            title="Return to list view.">
                                        <i class="bi-arrow-return-left"></i>&nbsp;
                                        Close
                                    </button>
                                <?php } ?>
                                <?php if ($this->allowDelete) { ?>
                                <button type="button" class="btnDelete buttonBar" id="btnDeleteView<?= $this->identifier ?>"
                                       data-id="<?= $this->currentID ?>"
                                       title="Delete this record.">
                                    <i class="bi-x-octagon"></i>&nbsp;
                                    Delete
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </form>
                <div id="contentAfterProfile<?= $this->identifier ?>"></div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array $col
     * @return string
     */
    private function showOneProfile(array $col):string
    {
        $output = '';
        if ($col['profile']) {
            if ($col['type'] == 'b') {
                if (isset($col['invertColors']) and $col['invertColors']) {
                    $yesClr = 'bad_color';
                    $noClr = 'good_color';
                } else {
                    $yesClr = 'good_color';
                    $noClr = 'bad_color';
                }
                if (!isset($col['altLabels'])) { // something other than Yes and No
                    $col['altLabels'] = array('No', 'Yes');
                }
                if ($col['format'] == 'NoColors') {
                    $output .= $col['altLabels'][$this->dbVars[$col['field']]['data']];
                } else {
                    if ($this->dbVars[$col['field']]['data'] == 1) {
                        $output .= '<span class="' . $yesClr . '">' . $col['altLabels'][1] . '</span>';
                    } else {
                        $output .= '<span  class="' . $noClr . '">' . $col['altLabels'][0] . '</span>';
                    }
                }
            } elseif ($col['type'] == 'lookup') {
                // lookup in other db table - 'format' element is array
                //                            $dispValue =lookup($col['format']['db'], $col['format']['table'], $col['format']['keyfield'], $this->dbVars[$col['field']]['data'], $col['format']['field']);
                $dispValue = lookup($col['format'], $this->dbVars[$col['field']]['data'], $this->dbVars[$this->keyField]['data']);
                $output .= $dispValue;
            } elseif (isset($col['lookup']) and is_array($col['lookup'])) {
                if (isset($col['useKeys'])) {
                    $output .= $col['lookup'][$this->dbVars[$col['field']]['data']];
                } else {
                    $output .= $this->dbVars[$col['field']]['data'];
                }
            } elseif (substr($col['type'], 0, 9) == 'function:' or isset($col['showFunction'])) {
                // link to external php function, pass the $col array and value
                if (isset($col['showFunction'])) {
                    $fn = $col['showFunction'];
                } else {
                    $fn = substr($col['type'], 9);
                }
                if (is_callable($fn)) {
                    $output .= call_user_func($fn, $this->dbVars[$this->keyField]['data'], $this->dbVars[$col['field']]['data'], $col, false, $this->dbVars);
                }
            } elseif (isset($col['lookup']) and !is_array($col['lookup'])) {
                $output .= '<span >';
                if (isset($col['link'])) {
                    $output .= '<a href="' . $col['link']['href'] . '?id=' . $col['link']['id'] . '" target="_self" class="bluelink" title="' . $col['link']['title'] . '" >';
                }
                if (isset($col['db'])) {
                    $cldb = $col['db'];
                } else {
                    $cldb = $this->db;
                }
                $output .= colLookup($cldb, $col['lookup'], $this->dbVars[$col['field']]['data'], false, (isset($col['allowNone']) and $col['allowNone']), (isset($col['altAllowNoneLabel'])) ? $col['altAllowNoneLabel'] : 'None');
                if (isset($col['link'])) {
                    $output .= '</a>';
                }
                $output .= '</span>';
            } elseif (isset($col['format']) and is_array($col['format']) and (isset($col['useKeys']) and $col['useKeys'])) {
                $output .= $col['format'][$this->dbVars[$col['field']]['data']];
//            } elseif ($col['type'] == 'upload') {
//                if (strlen($this->dbVars[$col['field']]['data']) > 0) {
//                    $output .= gls_esc_html(basename($this->dbVars[$col['field']]['data']));
//                }
            } elseif ($col['type'] == 'd' or $col['type'] == 'dt') {
                if (isset($col['format']) and $col['format'] != '') {
//                    $output .= date($col['format'], strtotime($this->dbVars[$col['field']]['data']));
                    $output .= niceDate($this->dbVars[$col['field']]['data'], true, $col['format']);
                } else {
                    $output .= niceDate($this->dbVars[$col['field']]['data']);
                }
            } else {
                if (isset($col['currencyField']) or $col['format'] == 'currency' or $col['format'] == 'accounting') {
                    if ($col['currencyField'] == 'USD') {
                        $curr = '$';
                    } elseif ($col['currencyField'] == 'EURO') {
                        $curr = '€';
                    } elseif ($col['currencyField'] == 'GBP') {
                        $curr = '£';
                    } else {
                        $curr = '$';
                    }
                } else {
                    $curr = '';
                }
                if ($col['type'] == 'i' and $col['format'] == 'percent') {
                    $this->dbVars[$col['field']]['data'] .= '%';
                }
                if ($col['allowHtml']) {
                    $output .= '<div class="profile_text_box" style="max-height: 300px;">';
                    $output .= $curr . $this->dbVars[$col['field']]['data'];
                    $output .= '</div>';
                } else {
                    if (isset($col['link'])) {
                        $output .= '<a href="' . $col['link']['href'] . '?id=' . $col['link']['id'] . '" target="_self" class="bluelink" title="' . $col['link']['title'] . '" >' . $col['link']['html'] . '</a>';
                    } elseif ($col['type'] == 't') { // textarea field, allow line feeds
                        $output .= '<div class="profile_text_box">' . str_replace("\r\n", "<br/>", stripcslashes($this->dbVars[$col['field']]['data'])) . '</div>';
                    } else {
                        $output .= $curr . gls_esc_html($this->dbVars[$col['field']]['data']);
                    }
                }

            }
        }
        if (isset($col['imagePreview']) and $col['imagePreview']) {
            $arr = explode(".", $this->dbVars[$col['field']]['data']);
            if (end($arr) != 'zip') {
                $output .= '
                <div class="imagePreview" id="'.$col['field'].'_preview">';
                if ($this->dbVars[$col['field']]['data'] != '') {
                    if (isset($col['imageWidth'])) {
                        $imageWidth = $col['imageWidth'];
                    } else {
                        $imageWidth = 'auto';
                    }
                    $output .= '
                    <img src="' . $this->imagePath . $this->dbVars[$col['field']]['data'].'"
                         style="width: '.$imageWidth.'; max-width: 200px;" alt="Preview">';
                }
                $output .= '</div>';
            }
        }
        return $output;
    }

    /**
     * @param array $col
     * @return string
     */
    private function showOneInput(array $col):string
    {
        global $currencySymbol;
        $output = '';
        $fld = $col['field'];
        if ($col['edit'] or $col['showEdit'] or $col['showAdd'] or $col['editadd']) {
            if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;
            if ($this->mode == 'add' and isset($col['default']) and intval($this->dbVars[$fld]['data']) == 0) { // type coersion intended for $this->dbVars // changed to intval() == 0 5/8/23
                if ($col['field'] == $this->parentKeyField and $col['default'] == 'parentID') {
                    $this->dbVars[$fld]['data'] = $this->parentKey;
                } else {
                    $this->dbVars[$fld]['data'] = $col['default']; // use for default values on add
                }
            }
            if ((!$col['edit'] or ($this->mode != 'add' and $fld == $this->keyField)) and (!($col['editadd'] and $this->mode == 'add'))) {
                $disabled = ' disabled ';
                if ($this->isRequired($col)) { // if field is required, but disabled, we need a hidden input for it
                    $fld = $fld . '_dis'; // rename the field to avoid duplicate id's
                    $output .= '<input type="hidden" name="' . $col['field'] . '" id="' . $col['field'] . '_' . $this->identifier . 'e" value="' . gls_esc_attr($this->dbVars[$col['field']]['data']) . '" />';
                }
            } else {
                $disabled = '';
            }
            if (isset($col['help']) and $col['help'] != '') {
                $help = ' title="' . $col['help'] . '"';
            } else {
                $help = '';
            }
            if (isset($col['attributes'])) {
                $attributes = $col['attributes'];
            } else {
                $attributes = '';
            }
            if (!$this->allowEditing or ($this->mode =='add' and $col['noAdd'])) {
                $disabled = ' disabled ';
            }
            if (isset($col['lookup'])) {
                // create a select box
                $output .= $this->selectBox($col['lookup'], $fld, $this->dbVars[$col['field']]['data'], $col['allowNone'], $disabled . $help . $attributes, $col['type'], $col);
            } else {
                if ($col['type'] != 'lookup' and isset($col['format']) and is_array($col['format'])) { // and (isset($col['useKeys']) and $col['useKeys'])
                    $output .= $this->selectBox($col['format'], $fld, $this->dbVars[$col['field']]['data'], $col['allowNone'], $disabled . $help . $attributes, $col['type'], $col);
                } elseif (substr($col['type'], 0, 9) == 'function:' or isset($col['showFunction'])) {
                    // link to external php function, pass the $col array and value
                    if (isset($col['showFunction'])) {
                        $fn = $col['showFunction'];
                    } else {
                        $fn = substr($col['type'], 9);
                    }
                    if (is_callable($fn)) {
                        if ((($this->mode == 'edit' or $this->mode == 'add') and $col['edit']) or ($this->mode == 'add' and $col['editadd'])) {
                            $output .= call_user_func($fn, $this->dbVars[$this->keyField]['data'], $this->dbVars[$col['field']]['data'], $col, true, $this->dbVars);
                        } else {
                            $output .= call_user_func($fn, $this->dbVars[$this->keyField]['data'], $this->dbVars[$col['field']]['data'], $col, false, $this->dbVars);
                        }
                    }
                } elseif ($col['type'] == 's') { // varchar
                    if (isset($col['inputType'])) {
                        $inputType = $col['inputType'];
                    } else {
                        $inputType = 'text';
                    }
                    $output .= '<input type="' . $inputType . '" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e" style="max-width: 100%" size="' . $col['size'] . '" maxlength="' . $col['maxlength'] . '"' . $disabled . $help . ' value="' . gls_esc_attr($this->dbVars[$col['field']]['data']) . '" ' . $attributes . ' />';
                } elseif ($col['type'] == 'i') { // numeric
                    if ($col['format'] == 'currency' or $col['format'] == 'accounting') {
                        if (isset($col['currencyField'])) { // points to a field in the table that contains the currency code
                            if ($this->dbVars[$col['currencyField']]['data'] == 'USD' or $col['currencyField'] == 'USD') {
                                $curr = '$';
                            } elseif ($this->dbVars[$col['currencyField']]['data'] == 'EURO' or $col['currencyField'] == 'EURO') {
                                $curr = '€';
                            } elseif ($this->dbVars[$col['currencyField']]['data'] == 'GBP' or $col['currencyField'] == 'GBP') {
                                $curr = '£';
                            }
                        } else {
                            $curr = $currencySymbol;
                        }
                        $output .= $curr . '<input type="number" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e" min="' . $col['min'] . '" max="' . $col['max'] . '" step="' . $col['step'] . '" style="width: ' . $col['size'] . 'em;"' . $disabled . $help . ' value="' . number_format($this->dbVars[$fld]['data'], 2,'.','') . '" ' . $attributes . ' />';
                    } else {
                        $output .= '<input type="number" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e" min="' . $col['min'] . '" max="' . $col['max'] . '" step="' . $col['step'] . '" style="width: ' . $col['size'] . 'em;"' . $disabled . $help . ' value="' . gls_esc_attr($this->dbVars[$fld]['data']) . '" ' . $attributes . ' />';
                    }
                } elseif ($col['type'] == 'd') { // date field
                    if ($disabled == '') {
                        $output .= '<input type="date" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $help . ' value="' . niceDate($this->dbVars[$fld]['data'], false, 'Y-m-d') . '" ' . $attributes . ' />';
                    } else {
                        $output .= '<input type="text" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $disabled . $help . ' value="' . niceDate($this->dbVars[$fld]['data'], true, $col['format']) . '" ' . $attributes . ' />';
                    }
                } elseif ($col['type'] == 'dt') { // date field
                    if ($disabled == '') {
                        $output .= '<input type="datetime-local" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $help . ' step=1 value="' . niceDate($this->dbVars[$fld]['data'], false, 'Y-m-d\TH:i:s') . '" ' . $attributes . ' />';
                    } else {
                        $output .= '<input type="text" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $disabled . $help . ' value="' . niceDate($this->dbVars[$fld]['data'], true, $col['format']) . '" ' . $attributes . ' />';
                    }
                } elseif ($col['type'] == 'm') { // time field
                    $output .= '<input  type="time" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $disabled . $help . ' value="' . gls_esc_attr($this->dbVars[$fld]['data']) . '" ' . $attributes . ' />';
                } elseif ($col['type'] == 't') { // textarea field
                    if (isset($col['tinymce'])) {
                        $this->hasTinymce = true;
                        $this->tinymceSettings = $col['tinymce'];
                        // allow custom content_css to exist in each record, will replace any set in column array, must contain an absolute path to file. (for EMS - 2/26/21)
                        if (isset($row['content_css']) and !is_null($row['content_css']) and file_exists($row['content_css'])) $this->tinymceSettings['content_css'] = $row['content_css'];
                        $tinyClass = ' class="mceEditor" ';
                        if (isset($col['mceNonEditable'])) {
                            $this->dbVars[$fld]['data'] = $this->protectMceCodes($this->dbVars[$fld]['data']);
                        }
                    } else {
                        $tinyClass = '';
                    }
                    if (strpos($col['width'], '%') > 0) {
                        $wid = $col['width'];
                    } else {
                        $wid = $col['width'] . 'in';
                    }
                    if (isset($col['height'])) {
                        $output .= '<textarea name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $tinyClass . $disabled . $help . ' style="width: 100%; max-width: ' . $wid . ';height: ' . $col['height'] . 'in;" ' . $attributes . '>' . stripcslashes($this->dbVars[$fld]['data']) . '</textarea>';
                    } else {
                        $output .= '<textarea name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"' . $tinyClass . $disabled . $help . ' style="width: 100%; max-width:' . $wid . ';height: 80px;" ' . $attributes . '>' . stripcslashes($this->dbVars[$fld]['data']) . '</textarea>';
                    }
                } elseif ($col['type'] == 'b') { // boolean (tinyint(1))
                    // if altLables is set, show a drop-down instead of a checkbox
                    if (isset($col['altLabels'])) {
                        $output .= '<select name="' . $fld . '"' . $disabled . $help . ' id="' . $fld . '_' . $this->identifier . 'e">';
                        for ($i = 0; $i < count($col['altLabels']); $i++) {
                            $output .= '<option value="' . $i . '" ';
                            if ($this->dbVars[$fld]['data'] == $i) $output .= 'selected';
                            if ($this->mode == 'add' and (isset($col['default']) and $col['default'] == $i)) $output .= 'selected';
                            $output .= ' ' . $attributes . '>' . $col['altLabels'][$i] . '</option>';
                        }
                        $output .= '</select>';
                    } else {
                        $output .= '<input type="checkbox" name="' . $fld . '"' . $disabled . $help . ' id="' . $fld . '_' . $this->identifier . 'e"';
                        if ($this->dbVars[$fld]['data'] == 1) $output .= 'checked="checked"';
                        if ($this->mode == 'add' and (isset($col['default']) and $col['default'] == 1)) $output .= 'checked="checked"';
                        $output .= ' value="1" ' . $attributes . '/>';
                    }
                } elseif ($col['type'] == 'yn') {
                    // select dropdown for Yes/No
                    $output .= '<select name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e"  ' . $disabled . '>';
                    $selectedYes = '';
                    $selectedNo = '';
                    if ($this->mode == 'add') {
                        if (isset($col['default']) and $col['default'] == 'Yes') $selectedYes = 'selected';
                        if (isset($col['default']) and $col['default'] == 'No') $selectedNo = 'selected';
                    } else {
                        if ($this->dbVars[$fld]['data'] == 'Yes') $selectedYes = 'selected';
                        if ($this->dbVars[$fld]['data'] == 'No') $selectedNo = 'selected';
                    }
                    $output .= '  <option value="Yes" ' . $selectedYes . '>Yes</option>';
                    $output .= '  <option value="No" ' . $selectedNo . '>No</option>';
                    $output .= '</select>';
                } elseif ($col['type'] == 'lookup') {
                    // lookup value and simply display it
                    // 'format' element is array
                    //                                    echo htmlspecialchars(lookup($col['format']['db'], $col['format']['table'], $col['format']['keyfield'], $this->dbVars[$fld]['data'], $col['format']['field']));
                    if ($col['edit'] and $col['editType'] == 'multi_checkbox') {
                        // multi check boxes for many to many related tables
                        $output .= $this->multiCheckbox($col, $this->currentID);
                    } elseif ($col['edit'] and $col['editType'] == 'multi_select') {
                        // multi select for many to many related tables - add/remove items
                        $output .= $this->multiSelect($col, $this->currentID);
                    } else {
                        $output .= lookup($col['format'], $this->dbVars[$fld]['data'], $this->dbVars[$this->keyField]['data']);
                    }
                }
            }
            if ($this->isRequired($col) and $disabled == '') {
                $output .= '<span class="errorMsg"> *</span>';
            }
            if (isset($col['addNewLink'])) {
                $output .= '&nbsp;<a href="' . $col['addNewLink'] . '" class="buttonBar"><i class="bi-pencil" style="font-size: 16px;"></i>&nbsp;Manage ' . $col['heading'] . '</a>';
            }
            $output .= '<div class="form_error" id="' . $fld . '_' . $this->identifier . 'e_err">' . $this->errorArray[$fld] . '</div>';
            if (isset($col['imagePreview']) and $col['imagePreview']) {
                $arr = explode(".", $this->dbVars[$fld]['data']);
                if (end($arr) != 'zip') {
                    $output .= '
                    <div class="imagePreview" id="' . $fld . '_preview">';
                    if ($this->dbVars[$fld]['data'] != '') {
                        if (isset($col['imageWidth'])) {
                            $imageWidth = $col['imageWidth'];
                        } else {
                            $imageWidth = 'auto';
                        }
                        $output .= '
                            <img src="' . $this->imagePath . $this->dbVars[$fld]['data'] . '"
                                 style="width: ' . $imageWidth . '; max-width: 500px;">';
                    }
                    $output .= '</div>';
                }
            }
        } elseif (isset($col['default']) and $this->isRequired($col)) {
            if ($this->mode == 'add' and isset($col['default']) and $this->dbVars[$fld]['data'] == '') { // type coersion intended for $this->dbVars
                if ($col['field'] == $this->parentKeyField and $col['default'] == 'parentID') {
                    $this->dbVars[$fld]['data'] = $this->parentKey;
                } else {
                    $this->dbVars[$fld]['data'] = $col['default']; // use for default values on add
                }
                $output = '<input type="hidden" name="' . $fld . '" id="' . $fld . '_' . $this->identifier . 'e" value="' . gls_esc_attr($this->dbVars[$fld]['data']) . '" />';
            }
        }
        return $output;
    }

    /**
     * @param array $column_array
     * @param bool|null $debug
     */
    public function processAjaxRequests(array $column_array, ?bool $debug = false)
    {
        if (isset($_POST['rm_process']) and $_POST['identifier'] == $this->identifier) {
            if ($_POST['rm_process'] == 'getProfile') {
                // return the processed profile values as JSON
                $id = $_POST['id'];
                $this->setCurrentID($id);
                $this->setMode('view');
                $row = $this->dbe->getRowWhere($this->table, $this->keyField, $id);
                $this->loadRecord($row, $column_array);
                echo json_encode(array_merge(array('msg'=>$this->getMsg()), $this->getProfileJSON($column_array)));
                exit;

            } elseif ($_POST['rm_process'] == 'getEditPage') { // new faster way to display an edit page 6/28/21
                $id = $_POST['id'];
                $this->setCurrentID($id);
                $this->setMode($_POST['mode']);
                $row = $this->dbe->getRowWhere($this->table, $this->keyField, $id);
                $this->loadRecord($row, $column_array);
                echo $this->getEditPageJSON($column_array);
                exit;

            } elseif ($_POST['rm_process'] == 'saveRecord') {
                $id = intval($_POST[$this->keyField]); // todo note expects integer keys only
                $this->setCurrentID($id);
                if ($id == 0 or $_POST['mode' . $this->identifier] == 'add') {
                    $this->setMode('add');
                } else {
                    $this->setMode('edit');
                }
                $mode = $this->processTableSave($column_array, $msg, $showSearchOnly);
                if (strip_tags($this->getMsg()) == 'The record was added.' or strip_tags($this->getMsg()) == 'The changes were saved.') {
                    // get the full record
                    $row = $this->dbe->getRowWhere($this->table, $this->keyField, $this->getCurrentID());
                    if ($mode != 'add') $this->loadRecord($row, $column_array);
                    // echo $this->getProfileJSON($column_array);
                    echo json_encode(array_merge(array('result'=>'Success','msg'=>$this->getMsg()), $this->getProfileJSON($column_array)));
                } else {
                    echo json_encode(array_merge(array('msg' => $this->getMsg()), $this->getErrorArray()));
                }
                exit;

            } elseif ($_POST['rm_process'] == 'deleteRecord') {
                $mode = $this->processTableSave($column_array, $msg, $showSearchOnly);
                if ($mode == 'list') { // delete was successful
                    echo json_encode(array('result' => 'Success','msg' => $this->getMsg(),'mode' => $mode));
                } else {
                    echo json_encode(array_merge(array('result'=>'Error','msg' => $this->getMsg(),'mode' => $mode), $this->getErrorArray()));
                }
                exit;
            }
        }
    }

    public function sharedJS()
    {

        ?>
        <script>
            // scripts visible to entire scope
            var rm<?=$this->identifier?> = new editModeScripts('<?=$this->identifier?>', {
                ajaxPath: "<?=PLMSite . 'admin/'?>",
                self: '<?=$_SERVER['REQUEST_URI']?>',
                parId: "<?=($this->parentKey == 0)? $this->currentID:$this->parentKey ?>",
                parKeyField: "<?=$this->parentKeyField?>",
                mode: "<?=$this->mode?>",
                editmode: <?=($this->mode == 'view')?'false':'true'?>,
                noProfileMode: <?=($this->noProfileMode)? 'true':'false'?>,
                useLocking: <?=($this->useLocking)? 'true':'false'?>,
                allowEditing: <?=($this->allowEditing)? 'true':'false'?>,
                allowDelete: <?=($this->allowDelete)? 'true':'false'?>,
                editTitle: <?=gls_esc_js($this->editTitle)?>,
                subTitle: <?=gls_esc_js($this->subTitle)?>,
                table: <?=gls_esc_js($this->table)?>,
                keyField: <?=gls_esc_js($this->keyField)?>,
                curId: <?=gls_esc_js($this->currentID)?>,
                timeout: <?=($this->useLocking)? $this->locking->getTimeout():0?>,
                warningTimeout: <?=($this->useLocking)? $this->locking->getWarningTimeout():0?>,
                DB: <?=gls_esc_js($this->db)?>,
                // tinyMCE settings from column array
                includeTiny: <?=($this->hasTinymce)? 'true':'false'?>,
                saveReturnsToEdit: <?=($this->saveReturnsToEdit)? 'true':'false'?>,
                modeCallback: <?=gls_esc_js($this->modeCallback)?>,
                editModeNoColumns: <?=($this->editModeNoColumns)? 'true':'false'?>,
                customProfileCallback: "<?=$this->customProfileCallback?>",
                updateCallback: "<?=$this->updateCallback?>",
            });

            <?php if ($this->hasTinymce) { ?>

            let tinySettings = {};
            tinySettings['width'] = "<?=$this->tinymceSettings['width'] ?? '88%'?>";
            tinySettings['height'] = "<?=$this->tinymceSettings['height'] ?? '310px'?>";
            tinySettings['allowDark'] = <?=isset($this->tinymceSettings['allowDark']) ? ($this->tinymceSettings['allowDark']) ? 'true' : 'false' : 'true'?>;
            tinySettings['content_css'] = "<?=$this->tinymceSettings['content_css'] ?? ''?>";
            tinySettings['readonly'] = <?=isset($this->tinymceSettings['readonly']) ? ($this->tinymceSettings['readonly']) ? 'true' : 'false' : 'false'?>;
            tinySettings['plugins'] = "<?=$this->tinymceSettings['plugins'] ?? 'link noneditable paste lists fullscreen visualchars nonbreaking code importcss'?>";
            tinySettings['toolbar'] = "<?=$this->tinymceSettings['toolbar'] ?? 'undo redo | styleselect | fontselect fontsizeselect bold italic removeformat | link image | bullist numlist indent outdent nonbreaking visualchars | code fullscreen'?>";
            tinySettings['menu'] = <?=$this->tinymceSettings['menu'] ?? '""'?>;
            tinySettings['menubar'] = "<?=$this->tinymceSettings['menubar'] ?? 'false'?>";
            tinySettings['toolbar_drawer'] = "<?=$this->tinymceSettings['toolbar_drawer'] ?? 'false'?>";
            tinySettings['templates'] = <?=$this->tinymceSettings['templates'] ?? '""'?>;
            // KB needs filemanager
            tinySettings['extra'] = <?=isset($this->tinymceSettings['includeFileManager']) ? '{
                    external_filemanager_path: "/js/responsive_filemanager/",
                    filemanager_title:         "Responsive Filemanager" ,
                    external_plugins:          { "filemanager" : "/js/responsive_filemanager/plugin.min.js"},
                    paste_data_images:         true
                    }' : '{}';?>;

            rm<?=$this->identifier?>.setTinySettings(tinySettings);

            <?php } ?>

        </script>

        <style>
            .jqmWindow {
                width: 600px;
                /*padding: 12px;*/
            }
        </style>
        <?php
    }

    /**
     * @param array $col
     * @return string
     */
    private function multiCheckbox(array $col):string
    {
        $output = '';
        // get all items from sourceTable, ordered by groupBy, displayField
        $where = '';
        if (isset($col['format']['sourceCriteria'])) {
            $where = ' WHERE ' . $col['format']['sourceCriteria'] . ' ';
        }
        if (isset($col['format']['groupBy'])) {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '`' . $where . ' ORDER BY ' . $col['format']['groupBy'] . ', ' . $col['format']['displayField'];
        } else {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '`' . $where . ' ORDER BY ' . $col['format']['displayField'];
        }
        $this->dbe->setBindtypes('');
        $this->dbe->setBindvalues(array());
        $rows = $this->dbe->execute_query($pq_query);  // execute query
        if (count($rows) > 0) {
            // show checkboxes in $numColumns columns
            if (!isset($col['format']['numColumns'])) {
                $col['format']['numColumns'] = 4;
            }
            $colWidth = 100 / $col['format']['numColumns'];
            $output .= '<div class="multiCheckboxes">';
            $oldid = '';
            $countColumns = 0;
            foreach ($rows as $row) {
                if (isset($col['format']['groupBy']) and $oldid != $row[$col['format']['groupBy']]) {
                    // show subheading line
                    if ($countColumns != 0) {
                        $output .= '</div>';
                        $countColumns = 0;
                    }
                    $output .= '</div><div ><div><b>' . $row[$col['format']['groupBy']] . '</b></div><hr></div><div class="multiCheckboxGroup">';
                    $oldid = $row[$col['format']['groupBy']];
                }
                if ($countColumns == 0) {
                    $output .= '<div class="multiCheckboxRow">';
                }
                // get checked state from linkTable, linkField
                $pq_query = 'Select * from `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['linkField'] . '` = ? AND `' . $col['format']['keyName'] . '` = ? ';
                $this->dbe->setBindtypes("ii");
                $this->dbe->setBindvalues(array($row[$col['format']['sourceField']], $this->currentID));
                $linkRows = $this->dbe->execute_query($pq_query);  // execute query
                if ($linkRows) {
                    $checked = ' checked ';
                } else {
                    $checked = '';
                }
                $output .= '<div class="multiCheckboxCell" style="flex-basis: ' . $colWidth . '%;">
                <input type="checkbox" name="' . $col['format']['linkTable'] . '_' . $col['format']['sourceTable'] . '_' . $row[$col['format']['sourceField']] . '"
                 id="' . $col['format']['linkTable'] . '_' . $col['format']['sourceTable'] . '_' . $row[$col['format']['sourceField']] . '_' . $this->identifier . 'e"
                 class="boxes" ' . $checked . ' />&nbsp;' . gls_esc_html($row[$col['format']['displayField']]) . '</div>';
                $countColumns++;
                if ($countColumns >= $col['format']['numColumns']) {
                    $output .= '</div>';
                    $countColumns = 0;
                }
            }
            if ($countColumns != 0) $output .= '</div>';
            $output .= '</div>';
        } else {
            $output = 'No items found.';
        }
        return $output;
    }

    /**
     * @param array $col
     * @return string
     */
    private function getMultiCheckboxes(array $col):string
    {
        $output = array();
        // get all items from sourceTable, ordered by groupBy, displayField
        $where = '';
        if (isset($col['format']['sourceCriteria'])) {
            $where = ' WHERE ' . $col['format']['sourceCriteria'] . ' ';
        }
        if (isset($col['format']['groupBy'])) {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '`' . $where . ' ORDER BY ' . $col['format']['groupBy'] . ', ' . $col['format']['displayField'];
        } else {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '`' . $where . ' ORDER BY ' . $col['format']['displayField'];
        }
        $this->dbe->setBindtypes('');
        $this->dbe->setBindvalues(array());
        $rows = $this->dbe->execute_query($pq_query);  // execute query
        if (count($rows) > 0) {
            // show checkboxes in $numColumns columns
            $oldid = '';
            foreach ($rows as $row) {
                // get checked state from linkTable, linkField
                $pq_query = 'Select * from `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['linkField'] . '` = ? AND `' . $col['format']['keyName'] . '` = ? ';
                $this->dbe->setBindtypes("ii");
                $this->dbe->setBindvalues(array($row[$col['format']['sourceField']], $this->currentID));
                $linkRows = $this->dbe->execute_query($pq_query);  // execute query
                if ($linkRows) {
                    $output[$col['format']['linkTable'] . '_' . $col['format']['sourceTable'] . '_' . $row[$col['format']['sourceField']]] = 'Yes';
                } else {
                    $output[$col['format']['linkTable'] . '_' . $col['format']['sourceTable'] . '_' . $row[$col['format']['sourceField']]] = 'No';
                }
            }
        }
        return $output;
    }

    /**
     * @param array $col
     * @return string
     */
    private function multiSelect(array $col):string
    {
        $output = '';

        $output .= '<div class="multiSelectRow">
            <div class="multiSelectList" id="multiSelect_' . $col['format']['linkTable'] . '_' . $this->identifier . 'e">';
        // get all items from source
        if (isset($col['format']['sourceCriteria'])) {
            $where = ' WHERE ' . $col['format']['sourceCriteria'] . ' ';
        }
        if (isset($col['format']['groupBy'])) {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '` ' . $where . ' ORDER BY ' . $col['format']['groupBy'] . ', ' . $col['format']['displayField'];
        } else {
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '` ' . $where . ' ORDER BY ' . $col['format']['displayField'];
        }
        $this->dbe->setBindtypes('');
        $this->dbe->setBindvalues(array());
        $rows = $this->dbe->execute_query($pq_query);  // execute query
        if (count($rows) > 0) {
            $pq_query = 'SELECT a.`' . $col['format']['linkKey'] . '` as linkKey, b.`' . $col['format']['sourceField'] . '` as sourceField, b.`' . $col['format']['displayField'] . '`
                        FROM `' . $col['format']['linkTable'] . '` a INNER JOIN `' . $col['format']['sourceTable'] . '` b ON b.`' . $col['format']['sourceField'] . '` = a.`' . $col['format']['linkField'] . '`
                        WHERE a.`' . $col['format']['keyName'] . '` = ? ORDER BY b.`' . $col['format']['displayField'] . '`';
            $this->dbe->setBindtypes("i");
            $this->dbe->setBindvalues(array($this->currentID));
            $linkRows = $this->dbe->execute_query($pq_query);  // execute query
            // create data items
            $data = 'data-db="'.$col['format']['db'].'" '.
                'data-identifier="'.$col['format']['linkTable'].'" '.
                'data-sourceTable="'.$col['format']['sourceTable'].'" '.
                'data-sourceField="'.$col['format']['sourceField'].'" '.
                'data-displayField="'.$col['format']['displayField'].'" '.
                'data-field="'.$col['field'].'" ';
            // show selected items in a column with delete buttons
            if ($linkRows) {
                foreach ($linkRows as $linkRow) {
                    $output .= '
                    <div class="multiSelect_items" id="multiSelect_Item_' . $col['format']['linkTable'] . '_' . $linkRow['sourceField'] . '" ' . $data . '>
                        <button class="buttonBar multiSelectRemove" type="button" data-identifier="' . $col['format']['linkTable'] . '" data-id="' . $linkRow['sourceField'] . '" title="Remove this item from this record.">
                            <i class="bi-trash" style="font-size: 14px;"></i>
                        </button>&nbsp;' . $linkRow[$col['format']['displayField']] . '<input type="hidden" name="' . $col['field'] . '[]" value="' . $linkRow['sourceField'] . '" />
                    </div>';
                }
            } else {
                $output .= 'No items found.';
            }
            $output .= '</div><div class="multiSelectList_select" >';
            // show select box with all items from source
            $output .= '<select class="multiSelect_Add" id="multiSelect_Add_' . $col['format']['linkTable'] . '" ' . $data . '>
            <option value="0" selected="selected">Select to Add</option>';
            foreach ($rows as $row) {
                $output .= '<option value="' . gls_esc_attr($row[$col['format']['sourceField']]) . '" >' . gls_esc_html($row[$col['format']['displayField']]) . '</option>';
            }
            $output .= '</select>';
        }
        $output .= '</div></div>';
        return $output;
    }

    /**
     * @param array $col
     * @return string[]
     */
    private function getMultiListboxes(array $col):array
    {
        $list = '';
        $pq_query = 'SELECT a.`' . $col['format']['linkKey'] . '` as linkKey, b.`' . $col['format']['sourceField'] . '` as sourceField, b.`' . $col['format']['displayField'] . '`
                    FROM `' . $col['format']['linkTable'] . '` a INNER JOIN `' . $col['format']['sourceTable'] . '` b ON b.`' . $col['format']['sourceField'] . '` = a.`' . $col['format']['linkField'] . '`
                    WHERE a.`' . $col['format']['keyName'] . '` = ? ORDER BY b.`' . $col['format']['displayField'] . '`';
        $this->dbe->setBindtypes("i");
        $this->dbe->setBindvalues(array($this->currentID));
        $linkRows = $this->dbe->execute_query($pq_query);  // execute query
        // create data items
        $data = 'data-db="'.$col['format']['db'].'" '.
                'data-identifier="'.$col['format']['linkTable'].'" '.
                'data-sourceTable="'.$col['format']['sourceTable'].'" '.
                'data-sourceField="'.$col['format']['sourceField'].'" '.
                'data-displayField="'.$col['format']['displayField'].'" '.
                'data-field="'.$col['field'].'" ';
        // show selected items in a column with delete buttons
        if ($linkRows) {
            foreach ($linkRows as $linkRow) {
                $list .= '
                <div class="multiSelect_items" id="multiSelect_Item_' . $col['format']['linkTable'] . '_' . $linkRow['sourceField'] . '" ' . $data . '>
                    <button class="buttonBar multiSelectRemove" type="button" data-identifier="' . $col['format']['linkTable'] . '" data-id="' . $linkRow['sourceField'] . '" title="Remove this item from this record.">
                        <i class="bi-trash" style="font-size: 14px;"></i>
                    </button>&nbsp;' . $linkRow[$col['format']['displayField']] . '<input type="hidden" name="' . $col['field'] . '[]" value="' . $linkRow['sourceField'] . '" />
                </div>';
            }
        } else {
            $list .= 'No items found.';
        }

        return array('multiSelect_' . $col['format']['linkTable'] => $list);

    }

    /**
     * @param array $editColumnArray
     * @param $result
     * @return array|bool|int|mixed
     */
    private function saveRelatedTables(array $editColumnArray, $result)
    {
        foreach ($editColumnArray as $col) {
            if ($col['edit'] or $col['editadd']) {
                if ($col['editType'] == 'multi_checkbox') {
                    $result = $this->saveMultiCheckboxes($col);
                } elseif ($col['editType'] == 'multi_select') {
                    $result = $this->saveMultiListbox($col);
                }
            }
        }
        return $result;
    }

    /**
     * @param array $col
     * @return array|bool|int
     */
    private function saveMultiCheckboxes(array $col)
    {
        $result = false;
        if ($col['edit'] or $col['editadd']) {
            // multi checkboxes for related table links - need to loop all records in related table and see if any Posts are present
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '` ';
            $this->dbe->setBindtypes("");
            $this->dbe->setBindvalues(array());
            $srcRows = $this->dbe->execute_query($pq_query);  // execute query
            if (count($srcRows) > 0) {
                foreach ($srcRows as $row) {
                    if (isset($_POST[$col['format']['linkTable'] . '_' . $col['format']['sourceTable'] . '_' . $row[$col['format']['sourceField']]])) {
                        // if POST found, make sure a record exists in link table
                        $pq_query = 'SELECT * FROM `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['linkField'] . '` = ? AND `' . $col['format']['keyName'] . '` = ? ';
                        $this->dbe->setBindtypes("ii");
                        $this->dbe->setBindvalues(array($row[$col['format']['sourceField']], $this->currentID));
                        $linkRows = $this->dbe->execute_query($pq_query);  // execute query
                        if ($linkRows) {
                            // do nothing if found
                        } else {
                            // add record
                            $data = array(
                                $col['format']['keyName']   => $this->currentID,
                                $col['format']['linkField'] => $row[$col['format']['sourceField']]
                            );
                            $result = $this->dbe->insertRow($col['format']['linkTable'], $data);
                        }
                    } else {
                        // POST not found, make sure a record DOES NOT exist in link table
                        $pq_query = 'DELETE FROM `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['linkField'] . '` = ? AND `' . $col['format']['keyName'] . '` = ? LIMIT 1 ';
                        $this->dbe->setBindtypes("ii");
                        $this->dbe->setBindvalues(array($row[$col['format']['sourceField']], $this->currentID));
                        $linkRows = $this->dbe->execute_query($pq_query);  // execute query
                        if ($linkRows) {
                            $result = 1;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param array $col
     * @return array|bool|int
     */
    private function saveMultiListbox(array $col)
    {
        // multi select listbox for related table links
        global $db;
        $result = false;
        if ($col['edit'] or $col['editadd']) {
            // first delete all linking records for this source record
            $pq_query = 'DELETE FROM `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['keyName'] . '` = ? ';
            $this->dbe->setBindtypes("i");
            $this->dbe->setBindvalues(array($this->currentID));
            $deleted = $this->dbe->execute_query($pq_query);  // execute query
            // need to loop all records in related table and see if any Posts match
            $pq_query = 'SELECT * FROM `' . $col['format']['sourceTable'] . '` ';
            $this->dbe->setBindtypes("");
            $this->dbe->setBindvalues(array());
            $srcRows = $this->dbe->execute_query($pq_query);  // execute query
            if (count($srcRows) > 0) {
                if (isset($_POST[$col['field']]) and is_array($_POST[$col['field']])) {
                    foreach ($srcRows as $row) {
                        foreach ($_POST[$col['field']] as $item) {
                            if ($item == $row[$col['format']['sourceField']]) {
                                // add record
                                $data = array(
                                        $col['format']['keyName']   => $this->currentID,
                                        $col['format']['linkField'] => $row[$col['format']['sourceField']]
                                );
                                $result = $this->dbe->insertRow($col['format']['linkTable'], $data);
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param array $dbVars
     * @param bool $update
     * @param int $id
     * @param array $column_array
     * @return array
     */
    private function checkValidation(array &$dbVars, bool $update = false, int $id = 0, array $column_array = array()):array
    {
        $arrayOfErrors = array();
        foreach ($column_array as $col) {
            if ($this->isRequired($col) and ($col['edit'] or ($col['editadd'] and !$update))) {
                if ($col['type'] == 'i') {
                    if ($dbVars[$col['field']]['data'] == 0) {
                        $arrayOfErrors[$col['field']] = $col['heading']." is required.";
                    }
                } elseif ($col['type'] == 's' or $col['type'] == 't' or $col['type'] == 'd') {
                    if (strlen($dbVars[$col['field']]['data']) == 0) { // required
                        $arrayOfErrors[$col['field']] = $col['heading']." is required.";
                    }
                }
            }
        }
        if (is_callable('customValidation')) {
            $customErrors = call_user_func_array('customValidation', array(&$dbVars, $update, $id, $column_array));
            $arrayOfErrors = array_merge($arrayOfErrors, $customErrors);
        }
        return $arrayOfErrors;
    }

    /**
     * @param array $col
     * @return bool
     */
    private function isRequired(array $col):bool
    {
        if (isset($col['required'])) {
            if (is_array($col['required'])) {
                if ($col['required'][$this->mode]) {
                    return true;
                } else {
                    return false;
                }
            } else {
                if ($col['required']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Called for TinyMCE to add protection for non-editable areas. Use {{,}} to delimit areas.
     * Must have "mceNonEditable" set in the column array
     *
     * @param string|null $input
     * @return string
     */
    private function protectMceCodes(?string $input):string
    {
        $output = str_replace('{{', '<span class="mceNonEditable">{{', $input);
        return str_replace('}}', '}}</span>', $output);
    }

    /**
     * Called for TinyMCE to remove protection
     *
     * @param string|null $input
     * @return string
     */
    private function unprotectMceCodes(?string $input):string
    {
        $output = str_replace('<span class="mceNonEditable">','', $input);
        return str_replace('}}</span>','}}', $output);
    }

    /**
     * @return array
     */
    public function getDbVars():array
    {
        return $this->dbVars;
    }

    /**
     * @param string $copyField
     */
    public function setCopyField(string $copyField)
    {
        $this->copyField = $copyField;
    }

    /**
     * @param string $updateCallback
     */
    public function setUpdateCallback(string $updateCallback)
    {
        $this->updateCallback = $updateCallback;
    }

    /**
     * @param string $insertCallback
     */
    public function setInsertCallback(string $insertCallback)
    {
        $this->insertCallback = $insertCallback;
    }

    /**
     * @param string $mode
     */
    public function setMode(string $mode)
    {
        $this->mode = $mode;
    }

    /**
     * @param bool $allowEditing
     */
    public function setAllowEditing(bool $allowEditing)
    {
        $this->allowEditing = $allowEditing;
    }

    /**
     * @param bool $allowDelete
     */
    public function setAllowDelete(bool $allowDelete)
    {
        $this->allowDelete = $allowDelete;
    }

    /**
     * @param bool $saveReturnsToEdit
     */
    public function setSaveReturnsToEdit(bool $saveReturnsToEdit)
    {
        $this->saveReturnsToEdit = $saveReturnsToEdit;
    }

    /**
     * @param bool $saveAndAddNew
     */
    public function setSaveAndAddNew(bool $saveAndAddNew)
    {
        $this->saveAndAddNew = $saveAndAddNew;
    }

    /**
     * @param array $deleteRestrictions
     */
    public function setDeleteRestrictions(array $deleteRestrictions)
    {
        $this->deleteRestrictions = $deleteRestrictions;
    }

    /**
     * @param bool $noProfileMode
     */
    public function setNoProfileMode(bool $noProfileMode)
    {
        $this->noProfileMode = $noProfileMode;
    }

    /**
     * @param string $labelWidth
     */
    public function setLabelWidth(string $labelWidth)
    {
        $this->labelWidth = $labelWidth;
    }

    /**
     * @param string $editTitle
     */
    public function setEditTitle(string $editTitle)
    {
        $this->editTitle = $editTitle;
    }

    /**
     * @return string
     */
    public function getEditTitle(): string
    {
        return $this->editTitle;
    }

    /**
     * @param string $subTitle
     */
    public function setSubTitle(string $subTitle)
    {
        $this->subTitle = $subTitle;
    }

    /**
     * @param string $addComment
     */
    public function setAddComment(string $addComment)
    {
        $this->addComment = $addComment;
    }

    /**
     * @param mixed $showDuplicate
     */
    public function setShowDuplicate($showDuplicate)
    {
        $this->showDuplicate = $showDuplicate;
    }

    /**
     * @param mixed $previewButton
     */
    public function setPreviewButton($previewButton)
    {
        $this->previewButton = $previewButton;
    }

    /**
     * @return int
     */
    public function getCurrentID():int
    {
        return $this->currentID;
    }

    /**
     * @return array
     */
    public function getErrorArray():array
    {
        return $this->errorArray;
    }

    /**
     * @return string
     */
    public function getMode():string
    {
        return $this->mode;
    }

    /**
     * @return string
     */
    public function getMsg():string
    {
        return $this->msg;
    }

    /**
     * @param int $currentID
     */
    public function setCurrentID(int $currentID)
    {
        if ($this->keyFieldType == 's' and (is_numeric($currentID) and $currentID == 0)) {
            $this->currentID = '';
        } else {
            $this->currentID = $currentID;
        }
    }

    /**
     * @return bool|false|mysqli
     */
    public function getDblink()
    {
        return $this->dbe->dblink;
    }

    /**
     * @param string $loggingCallback
     */
    public function setLoggingCallback(string $loggingCallback)
    {
        $this->loggingCallback = $loggingCallback;
    }

    /**
     * @param mixed $parentKey
     */
    public function setParentKey($parentKey)
    {
        $this->parentKey = $parentKey;
    }

    /**
     * @param AtLogging $atLog
     */
    public function setAtLog(AtLogging $atLog)
    {
        $this->atLog = $atLog;
    }

    /**
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param string $modeCallback
     */
    public function setModeCallback(string $modeCallback)
    {
        $this->modeCallback = $modeCallback;
    }

    /**
     * @param string $parentKeyField
     */
    public function setParentKeyField(string $parentKeyField)
    {
        $this->parentKeyField = $parentKeyField;
    }

    /**
     * @param bool $editModeNoColumns
     */
    public function setEditModeNoColumns(bool $editModeNoColumns)
    {
        $this->editModeNoColumns = $editModeNoColumns;
    }

    /**
     * @param string $customProfileCallback
     */
    public function setCustomProfileCallback(string $customProfileCallback)
    {
        $this->customProfileCallback = $customProfileCallback;
    }

    /**
     * @param bool $allowHideProfile
     */
    public function setAllowHideProfile(bool $allowHideProfile)
    {
        $this->allowHideProfile = $allowHideProfile;
    }

    /**
     * @param string $imagePath
     */
    public function setImagePath(string $imagePath):void
    {
        $this->imagePath = $imagePath;
    }


}
