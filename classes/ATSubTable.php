<?php
/**
 * ATSubTable Class
 * Support for sub-tables on tabs
 * Will show a ReportTable class table as a minimum, can include profile mode, edit mode, or add mode
 * JavaScript support is encapsulated in ATSubTable.js
 * @author Lee Samdahl
 * @created 5/24/2023
 *
 */

class ATSubTable
{
    private string $db = '';
    private ReportTable $rt; // primary ReportTable class instance
    private RecordManage $rm; // instance of RecordManage class
    private array $reportStructure = array('list'); // possible elements are 'list', 'profile', 'edit', 'add'
    private string $table = '';
    private string $keyField = '';
    private string $keyFieldType = 'i';
    private array $column_array = array();
    private bool $hasManage = false;
    private string $nameModifier = ''; // must be unique for each sub table on a tab bar
    private string $linkField = '', $parentKeyField = '';
    private int $linkID = 0, $parentKey = 0;
    private string $parentNameModifier = '';
//    private bool $prefChange = false; // todo store preference changes?
    private int $transitionTime = 200;
    private int $offset = 0;
    private string $mode = 'list';
    private int $recordLockTimeout = 5;
    private string $title = '';
    private bool $allowAll = false;
    private int $limit = 10;
    private string $qrySelect = '*';
    private string $qryGroupBy = '';
    private string $rtEmptyMessage = 'No Records Found';
    private bool $allowAddMode = false;
    private string $addModeComments = '';
    private bool $allowEditing = false, $allowDelete = false, $saveReturnsToEdit = false, $saveAndAddNew = false;
    private string $editTitle = 'Record';
    private bool $noProfileMode = false;
    private array $deleteRestrictions = array();
    private bool $showDuplicate = false;
    private string $copyField = '', $manageLoggingFunction = '';
    private string $profileLabelWidth = '84';
    private bool $editModeNoColumns = false;
    private array $where = array(1);
    private string $insertCallback = '', $updateCallback = '', $rmModeCallback = '', $rtRefreshCallback = '', $rmRefreshCallback = '';    
    private string $imagePath = '';
//    public string $infoText = '';
//    public bool $allowTableEditing = false; // use direct call to ReportTable

    /**
     * ATSubTable constructor.
     *
     * @param string $db
     * @param ReportTable $rt
     * @param array $reportStructure
     * @param string $table
     * @param string $keyField
     * @param string $keyFieldType
     * @param string $nameModifier
     * @param string $parentNameModifier
     */
    public function __construct(string $db, ReportTable $rt, array $reportStructure, string $table, string $keyField, string $keyFieldType, string $nameModifier, string $parentNameModifier = '')
    {
        $this->db = $db;
        $this->reportStructure = $reportStructure;
        $this->rt = $rt;
        $this->table = $table;
        $this->keyField = $keyField;
        $this->keyFieldType = $keyFieldType;
        $this->nameModifier = $nameModifier;
        $this->parentNameModifier = $parentNameModifier;

        $this->rt->setTable($table);
        $this->rt->setKeyField($keyField);
//        $this->rt->setRefreshCallback('rtCallback');
        $this->rt->setShowAsList(false);

        if (in_array('profile', $this->reportStructure) or in_array('edit', $this->reportStructure) or in_array('add', $this->reportStructure)) {
            $this->hasManage = true;
            if (in_array('edit', $this->reportStructure)) $this->allowEditing = true; // if 'edit' present, default to allowing editing
            if (in_array('add', $this->reportStructure)) $this->allowAddMode = true; // if 'add' present, default to allowing adding
            if (!in_array('profile', $this->reportStructure)) $this->noProfileMode = true; // if 'profile' is not present, set no profile mode
            require_once(PLMPATH . "classes/RecordManage.php");
            $this->rm = new RecordManage($this->rt->getIdentifier(), $this->db, $this->table, $this->keyField, $this->keyFieldType, true, $this->recordLockTimeout);
        }
    }

    /**
     * showStructure
     * output (nearly) all HTML to the browser
     */
    public function showStructure()
    {
        if ($this->hasManage) {

            if ($this->parentKeyField == '') $this->parentKeyField = $this->linkField;
            if ($this->parentKey == 0) $this->parentKey = $this->linkID;

            $this->rm->setAddComment($this->addModeComments);
            $this->rm->setAllowEditing($this->allowEditing);
            $this->rm->setAllowDelete($this->allowDelete);
            $this->rm->setSaveReturnsToEdit($this->saveReturnsToEdit);
            $this->rm->setEditTitle($this->editTitle);
            $this->rm->setModeCallback('modeChanged_' . $this->nameModifier);
            $this->rm->setMode($this->mode);
            $this->rm->setEditModeNoColumns($this->editModeNoColumns);
            $this->rm->setNoProfileMode($this->noProfileMode);
            $this->rm->setDeleteRestrictions($this->deleteRestrictions);
            $this->rm->setShowDuplicate($this->showDuplicate);
            $this->rm->setCopyField($this->copyField);
            $this->rm->setSaveAndAddNew($this->saveAndAddNew);
            if ($this->manageLoggingFunction != '') {
                $this->rm->setLoggingCallback($this->manageLoggingFunction);
            } else {
//                $this->rm->setAtLog($atLog = new AtLogging(basename($_SERVER["SCRIPT_NAME"])));
            }
            $this->rm->setInsertCallback($this->insertCallback);
            $this->rm->setUpdateCallback($this->updateCallback);
            $this->rm->setLabelWidth($this->profileLabelWidth);
            $this->rm->setImagePath($this->imagePath);
            $this->additionalHeaders .= '<script src="' . PLMSite . 'js/rmShared.min.js"></script>';
        }

    ?>
    <div id="reportMode_<?=$this->nameModifier?>">

        <h3><?=$this->title?></h3>
        <span class="errorMsg"></span>
        <?php
        $this->showReport();
        ?>
    </div>
    <?php if ($this->hasManage and $this->allowEditing) { ?>
    <div id="editMode_<?=$this->nameModifier?>" style="display: none;">
        <?php
        $this->rm->setMode('edit');
        $this->rm->profileEntry($this->column_array, true);
        $this->rm->setMode($this->mode);
        ?>
    </div>
    <?php }

    }

    /**
     * showReport
     * output the report body HTML and initial content
     */
    private function showReport()
    {
//        global $agent;
//        if (isset($agent) and $agent->is_mobile()) {
//            $this->rt->setNoColResize(true); // column resize on mobile is awkward - 10/29/1019
//        }
        $this->rt->setHasProfileMode(in_array('profile', $this->reportStructure));
        $this->rt->setShowAsList(false);
//        $this->rt->setAllowEditing($this->allowTableEditing); // go directly to ReportTable class for this
        $this->rt->setEmptyMessage($this->rtEmptyMessage);
        $this->rt->setSubtotalBySort(false);
        $this->rt->setShowGrandTotal(false);
        $this->rt->setLinkField($this->linkField);
        $this->rt->setLinkID($this->linkID);
        $this->rt->setRowClass('productColumnsRows');

        $this->where[] = '`' . $this->linkField . '` = ? ';
        $pq_bindtypes = 'i';
        $pq_bindvalues = array($this->linkID);
        $this->rt->setQueryAll('SELECT ' . $this->qrySelect . ' FROM ' . $this->table . ' ', $this->where, $this->qryGroupBy, $this->rt->getQryOrderBy()[0], $this->rt->getQryOrderBy()[1], $this->rt->getQryOrderBy()[2], $pq_bindtypes, $pq_bindvalues);
        if (isset($_POST['limit_' . $this->nameModifier]) and $_POST['limit_' . $this->nameModifier] != '') {
            $this->limit = clean_param($_POST['limit_' . $this->nameModifier], 'i');
        }
        $this->rt->setLimit($this->limit);
        $this->rt->setOffset($this->offset);
        ?>
        <div class="report_pagination">
            <?php
            $this->rt->outputPagination('top');
            $this->rt->outputSortFields();
            $this->rt->outputLimitSelector($this->allowAll);
            if ($this->allowAddMode) {
                ?>
            <button type="button" class="buttonBar good_color" id="addNewRecord_<?=$this->nameModifier?>"  >
                <i class="bi bi-plus-square"></i>
                &nbsp;Add New <?=$this->editTitle?>
            </button>
                <?php
            }
            $this->rt->outputRecordsFound('results', $this->rt->getCountRows());
            ?>
        </div>

        <div class="report_scrolling_table">
            <div id="report_container">
                <!-- The report itself -->
                <?php
                $this->rt->showTable(true);
                ?>
            </div>
        </div>

        <!-- pagination at bottom -->
        <?php $this->rt->outputPagination('bottom');

    }

    /**
     * process
     * A shorthand method to calling the four contained methods
     */
    public function process($debug = false)
    {
        // the column array must be set and imported from ReportTable class before any processing
        $this->setColumnArray($this->rt->getColumns());
        // AJAX requests are handled here
        $this->processAjaxRequests($debug);
        // set up all HTML structure
        $this->showStructure();
        // output all JavaScript
        $this->outputJS();
    }

    /**
     * processAjaxRequests
     *
     * @param bool $debug
     */
    public function processAjaxRequests(bool $debug = false)
    {
        // refresh the record manage class settings
        $this->setColumnArray($this->rt->getColumns());
        if ($this->hasManage) {

            if ($this->parentKeyField == '') $this->parentKeyField = $this->linkField;
            if ($this->parentKey == 0) $this->parentKey = $this->linkID;

            $this->rm->setParentKey($this->parentKey);
            $this->rm->setParentKeyField($this->parentKeyField);
            $this->rm->setAddComment($this->addModeComments);
            $this->rm->setAllowEditing($this->allowEditing);
            $this->rm->setAllowDelete($this->allowDelete);
            $this->rm->setSaveReturnsToEdit($this->saveReturnsToEdit);
            $this->rm->setEditTitle($this->editTitle);
            $this->rm->setModeCallback('modeChanged_' . $this->nameModifier);
            $this->rm->setMode($this->mode);
            $this->rm->setEditModeNoColumns($this->editModeNoColumns);
            $this->rm->setNoProfileMode($this->noProfileMode);
            $this->rm->setDeleteRestrictions($this->deleteRestrictions);
            $this->rm->setShowDuplicate($this->showDuplicate);
            $this->rm->setCopyField($this->copyField);
            if ($this->manageLoggingFunction != '') {
                $this->rm->setLoggingCallback($this->manageLoggingFunction);
            } else {
//                $this->rm->setAtLog(new AtLogging(basename($_SERVER["SCRIPT_NAME"])));
            }
            $this->rm->setInsertCallback($this->insertCallback);
            $this->rm->setUpdateCallback($this->updateCallback);
            $this->rm->setImagePath($this->imagePath);
        }
        $this->rt->setLimit($this->limit);
        $this->rt->setHasProfileMode(in_array('profile', $this->reportStructure));
//        $this->rt->setAllowEditing($this->allowTableEditing); // go directly to ReportTable class for this
        $this->rt->setEmptyMessage($this->rtEmptyMessage);
        $this->rt->setRowClass('productColumnsRows');
//        $orderBy = $this->rt->getQryOrderBy();
        $this->rt->setLinkField($this->linkField);
        $this->rt->setLinkID($this->linkID);
        $this->where[] = '`' . $this->linkField . '` = ? ';
        $pq_bindtypes = 'i';
        $pq_bindvalues = array($this->linkID);
        $this->rt->setQueryAll('SELECT ' . $this->qrySelect . ' FROM ' . $this->table . ' ', $this->where, $this->qryGroupBy, $this->rt->getQryOrderBy()[0], $this->rt->getQryOrderBy()[1], $this->rt->getQryOrderBy()[2], $pq_bindtypes, $pq_bindvalues);

        // the report table
        if (isset($_POST['rt_process']) and $_POST['identifier'] == $this->rt->getIdentifier()) {
            // called from rtShared.js
//            $this->checkColWidthAndSort(); // get any user preferences changes and save them
            $this->rt->processAjaxRequests($debug);
            exit;
        }
        // record manage class processes
        if (isset($_POST['rm_process']) and $_POST['identifier'] == $this->rm->getIdentifier()) {
            // called from rmShared.js
            $this->rm->processAjaxRequests($this->column_array, $debug);
            exit;
        }
        if (isset($_POST['atst_process'])) { // called from this module
            // future use
            exit;
        }

    }

    /**
     * outputJS
     * JavaScript to manage the application state
     */
    public function outputJS()
    {

        if ($this->hasManage) {
            $this->rm->sharedJS();
        }
        $this->rt->outputJavascript();

        ?>
        <script>
            // these are the settings for this module
            var atst_<?=$this->nameModifier?> = new atrSubTable({
                selfURL: '<?=$_SERVER['REQUEST_URI']?>',
                hasManage: <?=($this->hasManage)?'true':'false'?>,
                nameModifier: '<?=$this->nameModifier?>',
                rt_identifier: "<?=$this->rt->getIdentifier()?>",
                rt: <?='rt'.$this->rt->getIdentifier()?>,
                pageTitle: "<?=$this->title?>",
                keyField: "<?=$this->keyField?>",
                transitionTime: <?=$this->transitionTime?>,
                rtRefreshCallback: '<?=$this->rtRefreshCallback?>',
                rmRefreshCallback: '<?=$this->rmRefreshCallback?>',
                <?php if ($this->hasManage) { // support for profile(view), edit, and add modes ?>
                rm_identifier: "<?=$this->rm->getIdentifier()?>",
                rm: <?='rm'.$this->rm->getIdentifier()?>,
                editTitle: "<?=$this->rm->getEditTitle()?>",
                <?php } ?>

            });

            /**
             * Mode Changed event handler from rmShared.js
             * @param mode
             * @param id
             * @private
             */
            function modeChanged_<?=$this->nameModifier?>(mode, id) {
                atst_<?=$this->nameModifier?>.modeChanged(mode, id);
                if (atst_<?=$this->nameModifier?>.settings.hasManage) {
                    <?php if ($this->parentNameModifier != '') { ?>
                    if (rm<?=$this->parentNameModifier?> !== undefined) {
                        let prm = rm<?=$this->parentNameModifier?>;
                        if (mode === "add") {
                            $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " <a class='bluelink' onclick='atst_<?=$this->nameModifier?>.setMode(\"list\")'>" + prm.settings.subTitle + "</a> " + ' <?=BI_CARET_RIGHT?> Add New <?=(isset($this->rm))?$this->rm->getEditTitle():'Record'?>');
                        } else if (mode === "edit") {
                            $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " <a class='bluelink' onclick='atst_<?=$this->nameModifier?>.setMode(\"list\")'>" + prm.settings.subTitle + "</a> " + ' <?=BI_CARET_RIGHT?> Edit <?=(isset($this->rm))?$this->rm->getEditTitle():'Record'?>');
                        } else {
                            $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " " + prm.settings.subTitle);
                        }
                    }
                    <?php }
                    if ($this->rmModeCallback != '') { ?>
                    // pass event along to any additional handlers in calling program
                    if (typeof <?=$this->rmModeCallback?> !== 'undefined') {
                        <?=$this->rmModeCallback?>(obj);
                    }
                    <?php } ?>
                }
            }

            function selectRow_<?=$this->nameModifier?>(id) {
                atst_<?=$this->nameModifier?>.setEditMode(id);
            }

        </script>
        <?php

    }

    /**
     * @param array $column_array
     */
    public function setColumnArray(array $column_array)
    {
        $this->column_array = $column_array;
    }

    /**
     * @param string $customProfileCallback
     */
    public function setCustomProfileCallback(string $customProfileCallback)
    {
        $this->rm->setCustomProfileCallback($customProfileCallback);
    }

    /**
     * @param int $transitionTime
     */
    public function setTransitionTime(int $transitionTime)
    {
        $this->transitionTime = $transitionTime;
    }

    /**
     * @param mixed $recordLockTimeout
     */
    public function setRecordLockTimeout($recordLockTimeout)
    {
        $this->recordLockTimeout = $recordLockTimeout;
    }

    /**
     * @param int $parentKey
     */
    public function setParentKey(int $parentKey): void
    {
        $this->parentKey = $parentKey;
    }

    /**
     * @param bool $allowEditing
     */
    public function setAllowEditing(bool $allowEditing): void
    {
        $this->allowEditing = $allowEditing;
    }

    /**
     * @param bool $allowDelete
     */
    public function setAllowDelete(bool $allowDelete): void
    {
        $this->allowDelete = $allowDelete;
    }

    /**
     * @param bool $saveReturnsToEdit
     */
    public function setSaveReturnsToEdit(bool $saveReturnsToEdit): void
    {
        $this->saveReturnsToEdit = $saveReturnsToEdit;
    }

    /**
     * @param string $editTitle
     */
    public function setEditTitle(string $editTitle): void
    {
        $this->editTitle = $editTitle;
    }

    /**
     * @param bool $noProfileMode
     */
    public function setNoProfileMode(bool $noProfileMode): void
    {
        $this->noProfileMode = $noProfileMode;
    }

    /**
     * @param string $profileLabelWidth
     */
    public function setProfileLabelWidth(string $profileLabelWidth): void
    {
        $this->profileLabelWidth = $profileLabelWidth;
    }

    /**
     * @param string $parentKeyField
     */
    public function setParentKeyField(string $parentKeyField): void
    {
        $this->parentKeyField = $parentKeyField;
    }

    /**
     * @param bool $saveAndAddNew
     */
    public function setSaveAndAddNew(bool $saveAndAddNew): void
    {
        $this->saveAndAddNew = $saveAndAddNew;
    }

    /**
     * @param string $linkField
     */
    public function setLinkField(string $linkField): void
    {
        $this->linkField = $linkField;
    }

    /**
     * @param int $linkID
     */
    public function setLinkID(int $linkID): void
    {
        $this->linkID = $linkID;
    }

    /**
     * @param string $rtEmptyMessage
     */
    public function setRtEmptyMessage(string $rtEmptyMessage): void
    {
        $this->rtEmptyMessage = $rtEmptyMessage;
    }

    /**
     * @param bool $allowAddMode
     */
    public function setAllowAddMode(bool $allowAddMode): void
    {
        $this->allowAddMode = $allowAddMode;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        if ($_POST['limitSub'] == '') {
            $_POST['limitSub'] = $limit;
        }
        $this->limit = $_POST['limitSub'];
    }

    /**
     * @param bool $allowAll
     */
    public function setAllowAll(bool $allowAll): void
    {
        $this->allowAll = $allowAll;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @param string $qrySelect
     */
    public function setQrySelect(string $qrySelect): void
    {
        $this->qrySelect = $qrySelect;
    }

    /**
     * @param string $qryGroupBy
     */
    public function setQryGroupBy(string $qryGroupBy): void
    {
        $this->qryGroupBy = $qryGroupBy;
    }

    /**
     * @param bool $editModeNoColumns
     */
    public function setEditModeNoColumns(bool $editModeNoColumns): void
    {
        $this->editModeNoColumns = $editModeNoColumns;
    }

    /**
     * @param string $addModeComments
     */
    public function setAddModeComments(string $addModeComments): void
    {
        $this->addModeComments = $addModeComments;
    }

    /**
     * @param array $deleteRestrictions
     */
    public function setDeleteRestrictions(array $deleteRestrictions): void
    {
        $this->deleteRestrictions = $deleteRestrictions;
    }

    /**
     * @param bool $showDuplicate
     */
    public function setShowDuplicate(bool $showDuplicate): void
    {
        $this->showDuplicate = $showDuplicate;
    }

    /**
     * @param string $copyField
     */
    public function setCopyField(string $copyField): void
    {
        $this->copyField = $copyField;
    }

    /**
     * @param string $manageLoggingFunction
     */
    public function setManageLoggingFunction(string $manageLoggingFunction): void
    {
        $this->manageLoggingFunction = $manageLoggingFunction;
    }

    /**
     * @param array|int[] $where
     */
    public function setWhere(array $where): void
    {
        $this->where = $where;
    }

    /**
     * @param string $insertCallback
     */
    public function setInsertCallback(string $insertCallback): void
    {
        $this->insertCallback = $insertCallback;
    }

    /**
     * @param string $updateCallback
     */
    public function setUpdateCallback(string $updateCallback): void
    {
        $this->updateCallback = $updateCallback;
    }

    /**
     * @param string $rmModeCallback
     */
    public function setRmModeCallback(string $rmModeCallback): void
    {
        $this->rmModeCallback = $rmModeCallback;
    }

    /**
     * @param string $rtRefreshCallback
     */
    public function setRtRefreshCallback(string $rtRefreshCallback): void
    {
        $this->rtRefreshCallback = $rtRefreshCallback;
    }

    /**
     * @param string $rmRefreshCallback
     */
    public function setRmRefreshCallback(string $rmRefreshCallback): void
    {
        $this->rmRefreshCallback = $rmRefreshCallback;
    }
    
    /**
     * @param string $imagePath
     */
    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

}
