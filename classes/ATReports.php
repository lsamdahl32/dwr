<?php
/**
 * ATReports Class
 *
 * @author Lee Samdahl
 *
 * This class supports page construction and managing of columnar and list reports for
 * a wide variety of applications.
 */
class ATReports
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
    private array $tabbarArray = array();
    private Tabbar2 $tabbar;
    private bool $listSearchResults = false; // this is the setting to enable list mode in reporttable
    private bool $showAsListing = false; // this is current user preference for list mode
    private bool $prefChange = false;
    private int $transitionTime = 200;
    private int $offset = 0;
    private string $mode = 'list';
    private bool $tabbarUseAjax = true;
    private string $tabbarBodyID = 'tab_body';
    private int $recordLockTimeout = 5;
    private bool $useATSubTableClass = false;
    private bool $hasHomePage = false;
    private string $homePageCallback = '', $homeTitle = '';
    private bool $doInitialSearch = true;
    private string $title = '';
    private string $reportTitle = '';
    private bool $hideSearchCriteria = true;
    private string $additionalHeaders = '';
    private $breadcrumbs = false;
    private int $searchHeadingWidth = 100;
    private bool $showExport = true;
    private string $infoText = '';
    private bool $allowAll = false;
    private int $limit = 25;
    private bool $allowTableEditing = false;
    private array $subTitleFields = array();
    private string $qrySelect = '*';
    private array $where = array(1);
    private bool $showGrandTotal = false, $subtotalBySort = false;
    private string $rtEmptyMessage = 'No Records Found';

    // RecordManage class variables
    private bool $allowAddMode = false;
    private string $addModeComments = '';
    private bool $allowEditing = false, $allowDelete = false, $saveReturnsToEdit = false;
    private string $editTitle = 'Record';
    private bool $noProfileMode = false, $allowHideProfile = false;
    private array $deleteRestrictions = array();
    private bool $showDuplicate = false;
    private string $copyField = '', $manageLoggingFunction = '';
    private string $insertCallback = '', $updateCallback = '', $rmModeCallback = '', $rtRefreshCallback = '', $rmRefreshCallback = '';
    private string $previewButton = '', $profileLabelWidth = '185px';
    private bool $editModeNoColumns = false;
    private int $scaleFactor = 1;
    private string $imagePath = '';


    /**
     * ATReports constructor.
     *
     * @param string $db
     * @param ReportTable $rt
     * @param array $reportStructure
     * @param string $table
     * @param string $keyField
     * @param string $keyFieldType
     * @param bool $listSearchResults
     */
    public function __construct(string $db, ReportTable $rt, array $reportStructure, string $table, string $keyField, string $keyFieldType, bool $listSearchResults)
    {
        $this->db = $db;
        $this->reportStructure = $reportStructure;
        $this->rt = $rt;
        $this->table = $table;
        $this->keyField = $keyField;
        $this->keyFieldType = $keyFieldType;
        $this->listSearchResults = $listSearchResults;

        $this->rt->setTable($table);
        $this->rt->setKeyField($keyField);
        $this->rt->setRefreshCallback('rtCallback');

        if ($listSearchResults) {
            $this->showAsListing = true;
            $this->rt->setShowAsList(true);
        } else {
            $this->showAsListing = false;
            $this->rt->setShowAsList(false);
        }

        if (in_array('profile', $this->reportStructure) or in_array('edit', $this->reportStructure) or in_array('add', $this->reportStructure)) {
            $this->hasManage = true;
            if (in_array('edit', $this->reportStructure)) $this->allowEditing = true; // if 'edit' present, default to allowing editing
            if (in_array('add', $this->reportStructure)) $this->allowAddMode = true; // if 'add' present, default to allowing adding
            if (!in_array('profile', $this->reportStructure)) $this->noProfileMode = true; // if 'profile' is not present, set no profile mode
            require_once(PLMPATH . 'classes/RecordManage.php');
            $this->rm = new RecordManage($this->rt->getIdentifier(), $this->db, $this->table, $this->keyField, $this->keyFieldType, true, $this->recordLockTimeout);
        }
    }

    /**
     * showStructure
     * output (nearly) all HTML to the browser
     */
    public function showStructure()
    {
        $this->rt->setShowAsList($this->showAsListing);
        if (isset($_POST['results_mode']) and $this->listSearchResults) {
            if ($_POST['results_mode'] == 'table') {
                $this->showAsListing = false;
            } elseif ($_POST['results_mode'] == 'listing') {
                $this->showAsListing = true;
            }
            $this->prefChange = true;
        }
        if (isset($_POST['SettingsSave']) and $_POST['SettingsSave'] == 'SaveSearchSettings') {
            // search settings have changed
            // at least one column must be enabled for searching or show default settings
            $someSelected = false;
            for ($i = 0; $i < count($this->column_array); $i++) {
                $field = str_replace('.', '', $this->column_array[$i]['field']);
                if (isset($_POST[$field . '_srch'])) {
                    $someSelected = true;
                }
            }
            if ($someSelected) {
                for ($i = 0; $i < count($this->column_array); $i++) {
                    $field = str_replace('.', '', $this->column_array[$i]['field']);
                    if (isset($_POST[$field . '_srch'])) {
                        if (!$this->column_array[$i]['search']) {
                            $this->prefChange = true;
                            $this->column_array[$i]['search'] = 1;
                        }
                    } else {
                        if ($this->column_array[$i]['search']) {
                            $this->prefChange = true;
                            $this->column_array[$i]['search'] = 0;
                        }
                    }
                }
            }
        }
        $this->checkColWidthAndSort();
        $this->additionalHeaders .= '<script src="' . PLMSite . 'js/rtShared.js"></script>';

        if ($this->hasManage) {
            $this->rm->setAddComment($this->addModeComments);
            $this->rm->setAllowHideProfile($this->allowHideProfile);
            $this->rm->setAllowEditing($this->allowEditing);
            $this->rm->setAllowDelete($this->allowDelete);
            $this->rm->setSaveReturnsToEdit($this->saveReturnsToEdit);
            $this->rm->setEditTitle($this->editTitle);
            $this->rm->setModeCallback('modeChanged');
            $this->rm->setMode($this->mode);
            $this->rm->setEditModeNoColumns($this->editModeNoColumns);
            $this->rm->setNoProfileMode($this->noProfileMode);
            $this->rm->setDeleteRestrictions($this->deleteRestrictions);
            $this->rm->setShowDuplicate($this->showDuplicate);
            $this->rm->setCopyField($this->copyField);
            if ($this->manageLoggingFunction != '') {
                $this->rm->setLoggingCallback($this->manageLoggingFunction);
            } else {
//                $this->rm->setAtLog($atLog = new AtLogging(basename($_SERVER["SCRIPT_NAME"])));
            }
            $this->rm->setInsertCallback($this->insertCallback);
            $this->rm->setUpdateCallback($this->updateCallback);
            $this->rm->setPreviewButton($this->previewButton);
            $this->rm->setLabelWidth($this->profileLabelWidth);
            $this->rm->setImagePath($this->imagePath);
            $this->additionalHeaders .= '<script src="' . PLMSite . 'js/rmShared.js"></script>';

        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta name="viewport" content="width=device-width,initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
            <title><?php echo $this->title; ?></title>
            <link rel="stylesheet" href="./css/normalize.css">
            <link href="./css/side_menu_home.css" rel="stylesheet" type="text/css"/>
            <link href="./css/plm_admin.css" rel="stylesheet" type="text/css"/>
            <script src="https://code.jquery.com/jquery-3.6.1.min.js"
                    integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
            <script src="<?= PLMSite ?>js/jquery.doubleScroll.js"></script>
            <script src="<?= PLMSite ?>js/jqModal.js"></script>
            <link type="text/css" rel="stylesheet" media="all" href="<?= PLMSite ?>js/jqModal.css"/>
            <script src="<?= PLMSite ?>js/rtShared.js"></script>
            <?= $this->additionalHeaders ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
        </head>

        <body style="overflow-y: auto;">
        <section>
            <div>
                <div class="report_page_title">
                    <h1 id="report_page_title"><?= ($this->hasHomePage and $this->homeTitle != '') ? $this->homeTitle : $this->title; ?></h1>
                    <?php if (in_array('add', $this->reportStructure)) echo $this->newRecordLink(); ?>
                </div>
                <?php // also used for breadcrumbs
                $bcrumb = '';
                if ($this->breadcrumbs) {
                    if (is_array($this->breadcrumbs)) {
                        foreach ($this->breadcrumbs as $breadcrumb) {
                            $bcrumb .= $breadcrumb . ' ' . BI_CARET_RIGHT . ' ';
                        }
                    }
                    if ($this->hasHomePage) {
                        $bcrumb = '<a class="bluelink" href="' . basename($_SERVER['PHP_SELF']) . '" >' . $this->homeTitle . '</a> ' . '<span id="breadcrumbPageTitle" style="display: none;">' . BI_CARET_RIGHT . ' ' . $this->title . '</span> ' . ' <span id="breadcrumbs"></span>';
                    } else {
                        $bcrumb = $bcrumb . '<span id="breadcrumbPageTitle">' . $this->title . '</span> <span id="breadcrumbs"></span>';
                    }
                }
                if ($this->infoText != '') {
                    echo '<p class="infoText">' . $this->infoText . '</p>';
                }
                if ($bcrumb != '') {
                    echo '<p class="breadcrumbs">' . $bcrumb . '</p>';
                }
                ?>
            </div>
        </section>
        <?php // search bar
        ?>
        <div class="at-container-searchbar">
            <?php
            $this->showSearchCriteria();
            ?>
        </div>
        <?php // report body
        ?>
        <div class="at-container-reportbody">
            <?php // homepage mode
            if ($this->hasHomePage) { ?>
                <div id="at-homepage" <?= ($this->hasHomePage) ? '' : 'style="display: none;"' ?>>
                    <?php
                    if (is_callable($this->homePageCallback)) {
                        call_user_func($this->homePageCallback);
                    }
                    ?>
                </div>
                <?php
            }
            // list mode report
            ?>
            <div id="at-report" <?= ($this->hasHomePage) ? 'style="display: none;"' : '' ?>>
                <?php
                $this->showReport();
                ?>
            </div>
            <?php // profile, edit, and add modes
            if ($this->hasManage) {
                ?>
                <div id="at-manage" style="display: none;">
                    <?php
                    $this->rm->setMode('view');
                    $this->rm->profileEntry($this->column_array);
                    ?>
                    <?php if (count($this->tabbarArray) > 0) { ?>
                        <div id="at-tabs">
                            <?php
                            foreach ($this->tabbarArray as $key => $tab) {
                                $this->tabbar->addTab(new Tab($tab['name'], $key, $tab['function'], $tab['elementID'], $tab['width'], array()));
                            }

                            if (isset($_POST['tab_selected']) and $_POST['tabbar_identifier'] == $this->rt->getIdentifier()) {
                                $this->tabbar->setCurrentTab($_POST['tab_selected']);
                            } elseif (!isset($_SESSION[$this->rt->getIdentifier() . '_tab_selected'])) {
                                if (is_array($this->tabbarArray)) {
                                    $this->tabbar->setCurrentTab(array_keys($this->tabbarArray)[0]); // set initial tab
                                }
                            }
                            $this->tabbar->showTabs();

                            if ($this->tabbarBodyID != 'tab_body') { // use custom tab body
                                ?>
                                <div id="<?= $this->tabbarBodyID ?>" class="tab_body">
                                    <?php
                                    foreach ($this->tabbarArray as $key => $tab) {
                                        ?>
                                        <div id="<?= $tab['elementID'] ?>"
                                             class="tabContents_<?= $this->rt->getIdentifier() ?>"
                                             style="display: <?= ($this->tabbar->getCurrentTab() == $key) ? 'block' : 'none' ?>;">
                                            <?php
                                            if (!$this->tabbarUseAjax) {
                                                if (is_callable($tab['function'])) {
                                                    call_user_func($tab['function'], 0, 0);
                                                }
                                            }
                                            ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            <?php } ?>
        </div>
        <?php

        jqModalDivs();

        require_once(PLMPATH . 'admin/footer.php');
        ?>
        </body>
        </html>
        <?php

    }

    /**
     * showSearchCriteria
     * output the search box HTML
     */
    private function showSearchCriteria()
    {
        global $agent;

        if ((isset($agent) and $agent->is_mobile()) or ($this->hideSearchCriteria)) {
            $hideSearchCriteria = true; // hide search box and export options if selected or for mobile
        } else {
            $hideSearchCriteria = false;
        }
        if (!$this->doInitialSearch) {
            $hideSearchCriteria = false;
        }
        $orderBy = $this->rt->getQryOrderBy();
        ?>
        <form method="post" id="myForm" autocomplete="off" action="">
            <input type="hidden" name="SubmitRequest" value="authlog"/>
            <input type="hidden" name="sort1" id="sort1<?= $this->rt->getIdentifier() ?>" value="<?= $orderBy[0] ?>"/>
            <input type="hidden" name="sort2" id="sort2<?= $this->rt->getIdentifier() ?>" value="<?= $orderBy[1] ?>"/>
            <input type="hidden" name="sort3" id="sort3<?= $this->rt->getIdentifier() ?>" value="<?= $orderBy[2] ?>"/>
            <input type="hidden" name="limit" id="limit<?= $this->rt->getIdentifier() ?>"
                   value="<?= $this->rt->getLimit() ?>"/>
            <input type="hidden" name="offset" id="offset<?= $this->rt->getIdentifier() ?>"
                   value="<?= $this->rt->getOffset() ?>"/>
            <?php
            if (isset($_GET[$this->keyField]) and !isset($_POST['SubmitRequest'])) { // handle incoming links from other programs - only keyField allowed unless full search post configured.
                echo '<input type="hidden" name="' . $this->keyField . '_in1" id="' . $this->keyField . '_in1" value="' . $_GET[$this->keyField] . '" />';
                echo '<input type="hidden" name="' . $this->keyField . '_so" id="' . $this->keyField . '_so" value="=" />';
                $this->doInitialSearch = true;
            }
            if (isset($_POST['source'])) {
                echo '<input type="hidden" name="source" value="' . $_POST['source'] . '" />';
                echo '<input type="hidden" name="usrnm" value="' . $_POST['usrnm'] . '" />';
                echo '<input type="hidden" name="serial" value="' . $_POST['serial'] . '" />';
            }
            if (isset($subtotalBySort)) {
                echo '<input type="hidden" name="subtotalBySort" id="subtotalBySort" value="';
                if ($subtotalBySort) echo 'true"/>'; else echo 'false" />';
            }
            ?>
            <fieldset class="report_fieldset">
                <legend><b>Search Criteria:</b></legend>
                <div id="ar-searchSettings" <?php if ($hideSearchCriteria) echo 'style="display: none;"'; ?>>
                    <button class="openSettings buttonBar" type="button" data-type="search"
                            title="Select fields for Search Criteria">
                        <i class="bi-gear"></i>
                    </button>
                </div>
                <div class="ar-searchContents"
                     id="openSearchButton" <?php if (!$hideSearchCriteria) echo 'style="display: none;"'; ?>>
                    <button class="buttonBar doOpenSearch doShowAll" type="button">
                        <i class="bi-search"></i>
                        &nbsp;New Search
                    </button>
                    <button class="buttonBar doOpenSearch" id="btnModifySearch" type="button" style="display: none;">
                        <i class="bi-plus-circle"></i>
                        &nbsp;Modify Search
                    </button>
                    <span class="selectCriteria">Showing All Records</span>
                </div>
                <div class="ar-searchContents" id="search-fieldset"
                     style="<?php if ($hideSearchCriteria) echo 'display: none;'; ?>">
                    <div class="ar_search_fields">
                        <?php

                        $column = 1;
                        $lookupComment = '';
                        $column_array = sortArray($this->column_array, 'origOrder'); // use unsorted array for search criteria
                        $dbe = new DBEngine($this->db);
                        for ($i = 0; $i < count($column_array); $i++) {
                            $col = $column_array[$i];
                            $values = array();

                            if ($col['search'] and !isset($col['forceShow'])) { // don't allow link fields in search
                                if (isset($col['searchName'])) {
                                    $fld = $col['searchName'];
                                } else {
                                    // remove any periods in field name
                                    $fld = str_replace('.', '', $col['field']);
                                }
                                if (isset($_POST[$fld . '_so'])) {
                                    $operator = clean_param($_POST[$fld . '_so'], 's', true, $dbe->dblink); // this field can become literal SQL, so needs to be escaped
                                } else {
                                    $operator = '';
                                }

                                // get the posted data
                                // don't trim the data because user may be searching for empty field or field that contains spaces
                                if (isset($_POST[$fld . '_in1'])) {
                                    if ($col['type'] == 'd') { // date type
                                        $values['_in1'] = niceDate($_POST[$fld . '_in1'], false, 'Y-m-d');
                                    } else {
                                        if ($_POST[$fld . '_in1'] != '') {
                                            $values['_in1'] = $_POST[$fld . '_in1'];//$_POST[$fld . '_in1'], $typ);
                                        } else {
                                            $values['_in1'] = '';
                                        }
                                    }
                                } else {
                                    if (isset($_GET[$fld])) {
                                        if (isset($col['searchName'])) {
                                            $values['_in1'] = $_GET[$fld]; // col['type'] may not be the same as the searchName type, default to 's'
                                        } else {
                                            $values['_in1'] = $_GET[$fld];
                                        }
                                        $operator = '=';
                                    } elseif (isset($col['searchDefault'])) {
                                        $values['_in1'] = $col['searchDefault'];
                                        if (($col['type'] == 'yn') or ($col['type'] == 'tf') or ($col['type'] == 'b')) {
                                            $operator = $col['searchDefault'];
                                        } else {
                                            $operator = '=';
                                        }
                                    } else {
                                        $values['_in1'] = '';
                                    }
                                }
                                if (isset($_POST[$fld . '_in2'])) {
                                    if ($col['type'] == 'd') { // date type
                                        $values['_in2'] = niceDate($_POST[$fld . '_in2'], false, 'Y-m-d');
                                    } else {
                                        $values['_in2'] = $_POST[$fld . '_in2'];//$_POST[$fld . '_in2'], $typ);
                                    }
                                } else {
                                    $values['_in2'] = '';
                                }

                                $this->rt->findColumn($i, $col['field'])->displaySearchCriteria($this->searchHeadingWidth, $operator, $values['_in1'], $values['_in2']);
                            }
                            if ($col['type'] == 'lookup') {
                                $lookupComment .= $col['format']['comment'];
                            }
                        }
                        $dbe->close();
                        ?>
                    </div>
                    <span id="atLookupComment"><?= $lookupComment ?></span>
                    <div class="ar_search_buttons" id="atSearchButtons">
                        <button type="button" class="buttonBar" name="searchBtn" id="searchBtn">
                            <i class="bi-search"></i>
                            Search
                        </button>&nbsp;&nbsp;
                        <button type="button" name="showAll" id="showAll" style="display: none;"
                                class="buttonBar doShowAll" title="Clear Search Criteria">
                            <i class="bi-plus-circle-dotted"></i>&nbsp;
                            New Search
                        </button>
                        <span class="selectCriteria">Showing All Records</span>
                    </div>
                </div>
            </fieldset>
            <!-- Hidden inputs for Link: columns -->
            <input type="hidden" name="linkField" id="linkField" value="">
            <input type="hidden" name="linkID" id="linkID" value="">
            <input type="hidden" name="linkProcess" id="linkProcess" value="">
        </form>
        <div class="jqmWindow" id="jqSettingsDialog" style="display: none;">
            <div id="jqSettingsHeader">
                <i class="bi-gear"></i>
                &nbsp;
                <span id="settingsText1">Search Settings</span>
                <input type="hidden" name="settingsType" id="settingsType" value="search"/>
            </div>
            <div id="jqSettingsBody">
                <div id="settingsContent">
                </div>
                <hr>
                <div id="settingsButtons">
                    <button class="buttonBar altBackground" name="btnRestoreDefaults"
                            style="font-size: 12px; margin-right: 16px;" id="btnRestoreDefaults">
                        <i class="bi bi-arrow-counterclockwise smaller_icon"></i>
                        &nbsp;Restore Defaults
                    </button>
                    <input type="button" class="jqmClose buttonBar" style="width: 100px; margin-right: 16px;"
                           name="SettingsSave" id="SettingsSave" value="Ok"/>
                    <input id="doSettingsCancel" type="button" class="jqmClose buttonBar" style="width: 100px;"
                           value="Cancel"/>
                </div>
            </div>
        </div>

        <!-- Checkboxes for search settings dialog -->
        <div id="searchCheckboxesDiv" style="display: none;">
            <div id="searchCheckboxesComment">Select columns to include in search criteria.</div>
            <div id="searchCheckboxesList">
                <div style="flex-grow: 1;">
                    <?php
                    $i = 0;
                    foreach ($column_array as $col) {
                        if (isset($col['search']) and !isset($col['forceShow'])) {
                            if (in_array($col['type'], array('s', 'i', 'b', 't', 'yn', 'tf', 'd', 'dt', '=', 'bytes', 'lookup'))) { // exclude links, functions, callbacks, and lookups
                                if ($i % 14 == 0 and $i > 0) {
                                    echo '</div><div style="flex-grow: 1;">';
                                }
                                // remove any periods in field name
                                $field = str_replace('.', '', $col['field']);
                                if ($col['search']) {
                                    echo '<input type=\'checkbox\' class=\'searchCheckboxes\' name=\'' . $field . '_srch\' id=\'' . $field . '_srch\' checked />&nbsp;' . $col['heading'] . '<br/>';
                                } else {
                                    echo '<input type=\'checkbox\' class=\'searchCheckboxes\' name=\'' . $field . '_srch\' id=\'' . $field . '_srch\' />&nbsp;' . $col['heading'] . '<br/>';
                                }
                            }
                        }
                        $i++;
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Columns show/order settings dialog -->
        <div id="showOrderCheckboxes" style="display: none;">
            <?php
            if ($this->listSearchResults) {
                // select search mode, either listing or table
                ?>
                <div>
                    <input type="radio" name="set_results_mode"
                           id="set_results_mode_listing" <?= ($this->showAsListing) ? 'checked' : '' ?>
                           value="listing"/>
                    <label for="set_results_mode_listing">Show results as Listing</label><br/>
                    <input type="radio" name="set_results_mode"
                           id="set_results_mode_table" <?= (!$this->showAsListing) ? 'checked' : '' ?> value="table"/>
                    <label for="set_results_mode_table">Show results as Table</label>
                </div>
                <br/>
                <?php
            }
            ?>
            <div id="table_mode" <?= ($this->showAsListing) ? 'style="display: none;"' : '' ?>>
                <div id="table_modeSettingsButtons">
                    <button type="button" class="buttonBar altBackground" style="width: 108px;" id="sel_all">
                        <i class="bi-list-check" style="font-size: 24px;"></i>
                        &nbsp;Select All
                    </button>&nbsp;&nbsp;
                    <button type="button" class="buttonBar altBackground" style="width: 108px;" id="sel_none">
                        <i class="bi-list" style="font-size: 24px;"></i>
                        &nbsp;Select None
                    </button>
                    <div id="table_modeSettingsComment">Select columns to include in report.</div>
                </div>
                <?php
                // resort the array by 'order'
                $column_array = sortArray($column_array, 'order');
                ?>
                <div id="dragColumnNames">
                    <?php
                    $i = 0;
                    if (count($column_array) > 15) {
                        if (count($column_array) > 30) {
                            $colWidth = '33%';
                        } else {
                            $colWidth = '50%';
                        }
                    } else {
                        $colWidth = '100%';
                    }
                    foreach ($column_array as $key => $col) {
                        if (!isset($col['altSearch']) and !isset($col['db'])) {
                            $i++;
                            // remove any periods in field name
                            $field = $col['field'];

                            if (isset($col['forceShow'])) { // for link: type fields - must always show and be disabled
                                echo '<div id="colPos' . $key . '" style="width: ' . $colWidth . ';" >
                                                <input type="hidden" class="orderInputs" name="' . $field . $key . '_ord" id="' . $field . $key . '_ord" value="' . $key . '"/>
                                                <label for="' . $field . $key . '_sho" id="' . $field . $key . '_lab" style="display: inline-block;width: 100%;" >
                                                <input type="checkbox" class="showCheckboxes" name="' . $field . $key . '_sho" id="' . $field . $key . '_sho"';
                                echo ' checked="checked"';
                                echo ' disabled="disabled"';
                            } else {
                                echo '<div class="dragColumn" id="colPos' . $key . '" draggable="true" style="cursor: move; width: ' . $colWidth . ';">
                                                <input type="hidden" style="width: 20px;" class="orderInputs" name="' . $field . $key . '_ord" id="' . $field . $key . '_ord" value="' . $key . '"/>

                                                <input type="checkbox" class="showCheckboxes" name="' . $field . $key . '_sho" id="' . $field . $key . '_sho"';
                                if ($col['show']) {
                                    echo ' checked="checked"';
                                }
                                if ($col['type'] == 'c') {
                                    // checkbox - must be checked and disabled
                                    echo ' disabled="disabled"';
                                }
                            }
                            echo '/>&nbsp;<label for="' . $field . $key . '_sho" id="' . $field . $key . '_lab" >';
                            if (isset($col['altHeading'])) {
                                echo $col['altHeading'];
                            } else {
                                echo $col['heading'];
                            }
                            echo '</label>
                                                </div>' . "\n";
                        }
                    }
                    ?>
                    <!--                </div>-->
                </div>
                <?php
                if (isset($this->subtotalBySort)) { ?>
                    <div id="settingsSubtotalBySort">
                        <label for="subtotalBySortCB">Show subtotals on sort field:</label>&nbsp;
                        <input type="checkbox" name="subtotalBySortCB"
                               id="subtotalBySortCB" <?php echo ($this->subtotalBySort) ? ' checked ' : ''; ?> />
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php
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
        // if order field is missing from column_array, add it and set it to actual order
        for ($i = 0; $i < count($this->column_array); $i++) {
            if (!isset($this->column_array[$i]['order'])) {
                $this->column_array[$i]['order'] = $i;
            }
        }
        $this->rt->setHasProfileMode(in_array('profile', $this->reportStructure));
        $this->rt->setShowAsList($this->showAsListing);
        $this->rt->setAllowEditing($this->allowTableEditing);
        $this->rt->setEmptyMessage($this->rtEmptyMessage);
        $this->rt->setSubtotalBySort($this->subtotalBySort);
        $this->rt->setShowGrandTotal($this->showGrandTotal);
        if ($this->doInitialSearch) {
            // subtotal by sort
            $this->rt->setQueryAll('SELECT ' . $this->qrySelect . ' FROM ' . $this->table . ' ', $this->where, '', $this->rt->getQryOrderBy()[0], $this->rt->getQryOrderBy()[1], $this->rt->getQryOrderBy()[2]);
            if (isset($_POST['limit']) and $_POST['limit'] != '') {
                $this->limit = $_POST['limit'];
            }
            $this->rt->setLimit($this->limit);
            $this->rt->setOffset($this->offset);
            $this->rt->processPagination(false);
            $this->rt->processQuery(false);
            $this->rt->processTable();
        }
        ?>
        <h3>Search Results:</h3>
        <div class="report_pagination">
            <?php
            $this->rt->outputPagination('top');
            $this->rt->outputSortFields();
            $this->rt->outputLimitSelector($this->allowAll);
            $this->rt->outputRecordsFound('results', $this->rt->getCountRows());
            ?>
            <button class="refreshListing buttonBar" data-type="show" title="Refresh Listing">
                <i class="bi-arrow-clockwise"></i>
            </button>
            <button class="openSettings buttonBar" data-type="show" title="Report Settings">
                <i class="bi-gear"></i>
            </button>
        </div>

        <div class="report_scrolling_table">
            <div id="left_split_<?= $this->rt->getIdentifier() ?>" class="split left_split">
                <?php if ($this->listSearchResults and $this->showAsListing) { // the min-height below allows for the processing spinner ?>
                    <div class="arSearchResults">
                        <?php
                        $this->rt->showListing($this->doInitialSearch);
                        ?>
                    </div>
                <?php } else { ?>
                    <div id="report_container">
                        <!-- The report itself -->
                        <?php
                        $this->rt->showTable($this->doInitialSearch);
                        ?>
                    </div>
                <?php } ?>
            </div>
            <!-- hidden div for split table display -->
            <div id="right_split_<?= $this->rt->getIdentifier() ?>" class='ticket_show split'></div>
        </div>

        <!-- pagination at bottom -->
        <?php $this->rt->outputPagination('bottom');

        if ($this->showExport) {
            showExportOptions(false, 2, $this->scaleFactor);
        }
    }

    /**
     * setTabbar
     * initialize the Tabbar2 class
     *
     * @param array $hasTabbar
     * @param bool $tabbarUseAjax
     * @param string $tabbarBodyID
     * @param string|null $tabbarCallback
     */
    public function setTabbar(array $hasTabbar, bool $tabbarUseAjax, string $tabbarBodyID, ?string $tabbarCallback = '')
    {
        require_once(PLMPATH . 'classes/Tabbar2.php');
        $this->tabbar = new Tabbar2($this->rt->getIdentifier(), $tabbarUseAjax, $tabbarBodyID);
        if ($tabbarCallback != '') {
            $this->tabbar->setRefreshCallback($tabbarCallback);
        }
        $this->tabbarBodyID = $tabbarBodyID;
        $this->tabbarUseAjax = $tabbarUseAjax;
        $this->tabbarArray = $hasTabbar;
        $this->additionalHeaders .= "<script src='" . PLMSite . "js/tabbar2.js'></script>";
        if ($this->useATSubTableClass) $this->additionalHeaders .= '<script src="' . PLMSite . 'js/atSubTable.js"></script>';

        // in calling program set an array as follows:
        //    $hasTabbar = array( // the index for each item should be the same as the report table identifier, if any
        //                        'ap' => array('name'=>'Products', 'function'=>'showProducts', 'elementID'=>'', 'width'=>''),
        //                        'aa' => array('name'=>'Adjustments', 'function'=>'showAdjustments', 'elementID'=>'', 'width'=>''),
        //                        'sel' => array('name'=>'Log', 'function'=>'showArtistLog', 'elementID'=>'', 'width'=>''),
        //    );
    }

    /**
     * process
     * A shorthand method to calling the four contained methods
     */
    public function process()
    {
        // the column array must be set and imported from ReportTable class before any processing
        $this->setColumnArray($this->rt->getColumns());
        // AJAX requests are handled here
        $this->processAjaxRequests(false);
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
    public function processAjaxRequests(bool $debug)
    {
        // refresh the record manage class settings
        $this->rt->setShowAsList($this->showAsListing);
        $this->setColumnArray($this->rt->getColumns());
        if ($this->hasManage) {
            $this->rm->setAddComment($this->addModeComments);
            $this->rm->setAllowHideProfile($this->allowHideProfile);
            $this->rm->setAllowEditing($this->allowEditing);
            $this->rm->setAllowDelete($this->allowDelete);
            $this->rm->setSaveReturnsToEdit($this->saveReturnsToEdit);
            $this->rm->setEditTitle($this->editTitle);
            $this->rm->setModeCallback('modeChanged');
            $this->rm->setMode($this->mode);
            $this->rm->setEditModeNoColumns($this->editModeNoColumns);
            $this->rm->setNoProfileMode($this->noProfileMode);
            $this->rm->setDeleteRestrictions($this->deleteRestrictions);
            $this->rm->setShowDuplicate($this->showDuplicate);
            $this->rm->setCopyField($this->copyField);
            if ($this->manageLoggingFunction != '') {
                $this->rm->setLoggingCallback($this->manageLoggingFunction);
            } else {
//                $this->rm->setAtLog($atLog = new AtLogging(basename($_SERVER["SCRIPT_NAME"])));
            }
            $this->rm->setInsertCallback($this->insertCallback);
            $this->rm->setUpdateCallback($this->updateCallback);
            $this->rm->setPreviewButton($this->previewButton);
            $this->rm->setImagePath($this->imagePath);
        }
        $this->rt->setLimit($this->limit);
        $this->rt->setHasProfileMode(in_array('profile', $this->reportStructure));
        $this->rt->setAllowEditing($this->allowTableEditing);
        $this->rt->setEmptyMessage($this->rtEmptyMessage);
        $this->rt->setSubtotalBySort($this->subtotalBySort);
        $this->rt->setShowGrandTotal($this->showGrandTotal);
        $orderBy = $this->rt->getQryOrderBy();
        $this->rt->setQueryAll('SELECT ' . $this->qrySelect . ' FROM ' . $this->table . ' ', $this->where, '', $orderBy[0], $orderBy[1], $orderBy[2]);
        $this->rt->setShowAsList($this->showAsListing);

        // search criteria
        if ((isset($_POST['atr_process']) and $_POST['atr_process'] == 'search')) {
            // called from this module
//            echo 'search«' . json_encode($this->ProcessSearchRequest());
            echo json_encode($this->ProcessSearchRequest());
            exit;
        }
        // the main report table
        if (isset($_POST['rt_process']) and $_POST['identifier'] == $this->rt->getIdentifier()) {
            // called from rtShared.js
            $this->checkColWidthAndSort(); // get any user preferences changes and save them
            $this->rt->setShowAsList($this->showAsListing);
            $this->rt->processAjaxRequests(false);
        }
        // record manage class processes
        if (isset($_POST['rm_process']) and $_POST['identifier'] == $this->rm->getIdentifier()) {
            // called from rmShared.js
            $this->rm->processAjaxRequests($this->column_array, $debug);
            exit;
        }
        // report tables and record manage class on tabs
        foreach ($this->tabbarArray as $key => $tab) { // tab id must be the same as the table identifier
            if (isset($_POST['rt_process']) and $_POST['identifier'] == $key) {
                if (is_callable($tab['function'])) {
                    call_user_func($tab['function'], (int)$_POST['linkID']);
                }
                exit;
            }
            // record manage class on tabs
            if (isset($_POST['rm_process']) and $_POST['identifier'] == $key) {
                if (is_callable($tab['function'])) {
                    call_user_func($tab['function'], (int)$_POST['linkID']);
                }
                exit;
            }
        }
        // tab click
        if (isset($_POST['tabbar_identifier']) and $_POST['tabbar_identifier'] == $this->rt->getIdentifier()) {
            if ($_POST['process'] == 'refreshTabs') {
                $this->tabbar->refreshTabs();
            } else {
                if (is_callable($_POST['tab_function'])) {
                    call_user_func($_POST['tab_function'], (int)$_POST['tab_param0'], $_POST['tab_param1']);
                }
            }
            exit;
        }
        if (isset($_POST['atr_process'])) { // called from this module
            if ($_POST['atr_process'] == 'export') { // Exports
                $this->processExports();
                exit;

            } elseif ($_POST['atr_process'] == 'getSubTitle') {
                $id = $_POST['id'];
                $dba = new DBEngine($this->db, $debug);
                $row = $dba->getRowWhere($this->table, $this->keyField, $id);
                $dba->close();
                echo json_encode(array('subTitle' => $this->getSubTitle($this->subTitleFields, $row, $this->db)));
                exit;
            } elseif ($_POST['atr_process'] == 'tableLink') {
                if ($this->mode == '' or $this->mode == 'list') {
                    if ($this->noProfileMode and $this->allowEditing) {
                        $this->mode = 'edit';
                    } else {
                        $this->mode = 'view';
                    }
                }
                // get the full record
                $id = $_POST['linkID'];
                $dba = new DBEngine($this->db, $debug);
                $row = $dba->getRowWhere($this->table, $this->keyField, $id);
                $dba->close();
                $subTitle = $this->getSubTitle($this->subTitleFields, $row, $this->db);
                // send back the mode and the subtitle
                echo json_encode(array('mode' => $this->mode, 'subTitle' => $subTitle, 'id' => $id));
                exit;
            }
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

        // scripts for search and report tables
        ?>
        <script src="<?= PLMSite ?>js/atReports.js"></script>
        <script>
            const BI_CARET_RIGHT = '<?=BI_CARET_RIGHT?>';
            let rt_identifier = "<?=$this->rt->getIdentifier()?>";
            let rt = <?='rt' . $this->rt->getIdentifier()?>;
            let pageTitle = "<?=$this->title?>";
            let homeTitle = "<?=$this->homeTitle?>";
            let breadcrumbs = <?=($this->breadcrumbs) ? 'true' : 'false'?>;
            let keyField = "<?=$this->keyField?>";
            let transitionTime = <?=$this->transitionTime?>;
            let rtRefreshCallback = '<?=$this->rtRefreshCallback?>';
            let rmRefreshCallback = '<?=$this->rmRefreshCallback?>';

            <?php
            // support for profile(view), edit, and add modes
            if ($this->hasManage) { ?>
            let rm_identifier = "<?=$this->rm->getIdentifier()?>";
            let rm = <?='rm' . $this->rm->getIdentifier()?>;
            let tabbarArray = JSON.parse('<?=json_encode($this->tabbarArray)?>');
            <?php if (count($this->tabbarArray) > 0) { ?>
            let tab = <?='tabbar' . $this->rt->getIdentifier()?>;
            <?php } ?>
            let editTitle = "<?=$this->rm->getEditTitle()?>";
            let allowHideProfile = <?=($this->allowHideProfile) ? 'true' : 'false'?>;
            <?php } else { ?>
            let allowHideProfile = false;
            <?php } ?>
            var atr = new atrScripts({
                selfURL:            '<?=$_SERVER['REQUEST_URI']?>',
                listSearchResults:  <?=($this->listSearchResults) ? 'true' : 'false'?>,
                hasManage:          <?=($this->hasManage) ? 'true' : 'false'?>,
                hideSearchCriteria: <?=($this->hideSearchCriteria) ? 'true' : 'false'?>,
                hasHomePage:        <?=($this->hasHomePage) ? 'true' : 'false'?>,

            });

            $(document).ready(function () {
                // $("#footer_message_left").html(pageTitle); // show current report title in the footer

                <?php if ($this->doInitialSearch or isset($_POST[$this->keyField]) or isset($_POST['doSearch'])) { ?>
                $("#myForm #searchBtn").click(); // force an initial search function
                <?php } elseif ($this->hasHomePage) { ?>
                $("#report_page_title").html(homeTitle); // show home page
                // $("#footer_message_left").html(homeTitle); // show current report title in the footer
                $("#at-homepage").show();
                $("#at-report").hide();
                <?php } ?>

                // set initial state of inputs based on Select value
                <?php
                foreach ($this->column_array as $col) {
                    // remove any periods in field name
                    $field = str_replace('.', '', $col['field']);
                    if ($col['search']) {
                        echo 'doOnChange($("#' . $field . '_so").val(), "' . $field . '_so");' . "\n";
                    }
                }

                if (!$this->hideSearchCriteria or !$this->doInitialSearch) { ?>
                $('#myForm input:visible:enabled:first').focus();
                <?php } ?>

                $('#jqSettingsDialog').jqm({
                    modal:   true,
                    overlay: 88,
                    toTop:   true,
                    onShow:  function (hash) {
                        hash.o.prependTo('body');
                        hash.w.css('opacity', 1).fadeIn();
                    },
                    onHide:  function (hash) {
                        hash.w.fadeOut('2000', function () {
                            hash.o.remove();
                        });
                    }
                });
            });

            <?php
            // only include for profile(view), edit, and add modes
            if ($this->hasManage) { ?>

            $(document).ready(function () {

                $("#report_table_" + rt_identifier).off().on('click', '.doLink', function () { // cancel the submit() default handler in rtShared and replace it
                    openProfileOrEditMode($(this).attr("data-fieldname"), $(this).attr("data-fielddata"), $(this).attr("data-type"));
                });

            });

            function openProfileOrEditMode(linkField, linkID, linkProcess) {
                $("#linkField").val(linkField); // make sure hidden fields are updated
                $("#linkID").val(linkID);
                $("#linkProcess").val(linkProcess);
                let data = {};
                $('#myForm').serializeArray().map(function (x) {
                    data[x.name] = x.value;
                });
                data['atr_process'] = 'tableLink';
                // console.log(data);
                $.post('<?=$_SERVER['REQUEST_URI'] ?>', data, function (result) {
                    checkLoginStatus(result);
                    // console.log(result);
                    var obj = JSON.parse(result);
                    if (obj.mode === 'view') { // open profile view mode
                        // process result
                        rm.setCurrentID(obj.id); // need to set this first
                        rm.setMode('view');
                        rm.refresh(function () {
                            rm.settings.subTitle = obj.subTitle;
                            $("#profileHeading" + rm_identifier).html(obj.subTitle);
                            $("#at-report").hide(transitionTime);
                            $("#at-manage").show({
                                duration: transitionTime, done: function () {
                                    $("#at-tabs").show({
                                        duration: transitionTime, done: function () {
                                            loadAllTabs(obj.id);
                                        }
                                    });
                                }
                            });
                            if (typeof window[rmRefreshCallback] == 'function') {
                                window[rmRefreshCallback](obj);
                            }
                        });
                        if (breadcrumbs) {
                            if (atr.settings.hasHomePage) {
                                $("#breadcrumbPageTitle").html(' ' + BI_CARET_RIGHT + ' <a class="ReturntoSearchResults bluelink">' + pageTitle + '</a>');// make a link to return to search results
                            } else {
                                $("#breadcrumbPageTitle").html('<a class="ReturntoSearchResults bluelink">' + pageTitle + '</a>');// make a link to return to search results
                            }
                            $("#breadcrumbPageTitle").show();
                            $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' ' + obj.subTitle);
                        }
                    } else if (obj.mode === 'edit') { // for noProfileMode in the main table
                        rm.setCurrentID(obj.id); // need to set this first
                        rm.setMode('edit');
                        rm.refresh(function () {
                            $("#profileHeading" + rm_identifier).html(obj.subTitle + ' - Edit');
                            $(".readMode" + rm_identifier).hide(transitionTime);
                            $(".editMode" + rm_identifier).show(transitionTime);
                            if (typeof window[rmRefreshCallback] == 'function') {
                                window[rmRefreshCallback](obj);
                            }
                        });
                        if (rm.settings.editModeNoColumns) {
                            $("#profile-flex-cols" + rm_identifier).css('display', 'block');
                        }
                        if ($("#stateZone").length) {
                            rm.countryChange($("#countrySelect").val());
                        }
                        if (rt.settings.countRows > 1) {
                            $("#ReturntoSearchResults").show();
                        } else {
                            $("#ReturntoSearchResults").hide();
                        }
                        $("#at-report").hide(transitionTime);
                        $("#at-manage").show({
                            duration: transitionTime, done: function () {
                                $("#at-tabs").show({
                                    duration: transitionTime, done: function () {
                                        loadAllTabs(obj.id);
                                    }
                                });
                            }
                        });
                        rm.settings.mode = 'edit';
                        rm.settings.editmode = true;
                    }
                });
            }

            function loadAllTabs(id) {
                if (Object.keys(tabbarArray).length > 0) {
                    tab.refresh();
                    if (tab.settings.refreshCallback !== "") { // callback function after refresh
                        Object.keys(tabbarArray).forEach(function (key) {
                            $("#" + key).attr("data-param0", id);
                            window[tab.settings.refreshCallback](rt_identifier, 0, key);
                        });
                    } else { // just click the tab to refresh it
                        $(".tab_bar_buttons").attr("data-param0", id);
                        $("#" + tab.settings.currentTabID).click();
                    }
                }
            }

            function modeChanged(mode, id) {
                if ((mode === 'edit' || mode === 'view') && id > 0) {
                    $.post(atr.settings.selfURL, {
                        atr_process: 'getSubTitle',
                        mode:        mode,
                        id:          id
                    }, function (result) {
                        checkLoginStatus(result);
                        // console.log(result);
                        var obj = JSON.parse(result);
                        rm.settings.subTitle = obj['subTitle'];
                        if (mode === 'edit') {
                            if (breadcrumbs) {
                                $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + '' + " <a class='bluelink' onclick='rm.closeEdit()'> " + rm.settings.subTitle + "</a> " + '' + BI_CARET_RIGHT + ' Edit ' + editTitle);
                            }
                        } else if (mode === 'view') {
                            $("#at-tabs").show({
                                duration: transitionTime, done: function () {
                                    loadAllTabs(id);
                                }
                            });
                            if (breadcrumbs) {
                                $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + '' + rm.settings.subTitle);
                            }
                            $("#profileHeading" + rm_identifier).html(obj['subTitle']);
                        }
                        if (rt.settings.countRows === 1 && mode === 'view') {
                            $("#ReturntoSearchResults").hide();
                        }
                        <?php
                        if ($this->rmModeCallback != '') {
                            echo $this->rmModeCallback . '(mode, id);';
                        }
                        ?>
                    });
                } else if (mode === 'add') {
                    if (breadcrumbs) {
                        $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Add New ' + editTitle);
                    }
                    <?php
                    if ($this->rmModeCallback != '') {
                        echo $this->rmModeCallback . '(mode, id);';
                    }
                    ?>
                } else if (mode === 'list') {
                    if (atr.settings.hasHomePage) {
                        $("#at-homepage").hide();
                        $("#report_page_title").html(pageTitle);
                        $("#footer_message_left").html(pageTitle); // show current report title in the footer
                    }
                    rm.setDirty(false); // if coming from edit mode, clear the dirty flag
                    $("#at-manage").hide(transitionTime);
                    $("#at-report").show(transitionTime);
                    rt.refresh(); // refresh the grid upon returning from profile view
                    if (breadcrumbs) {
                        $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Search Results');
                    }
                    <?php
                    if ($this->rmModeCallback != '') {
                        echo $this->rmModeCallback . '(mode, id);';
                    }
                    ?>
                }
            }
            <?php } ?>
        </script>
        <?php

    }

    /**
     * ProcessSearchRequest
     * Evaluate POST values and create the search criteria
     *
     * @return array ['operator','values','where','pq_bindtypes','pq_bindvalues','selectCriteria']
     */
    private function ProcessSearchRequest(): array
    {
        // get the search criteria
        $operator = array();
        $values = array();
        $where = $this->where; // need to start with any default search criteria
        $pq_bindtypes = '';
        $pq_bindvalues = array();
        $selectCriteria = array();
//        print_r($_POST);
        $dbe = new DBEngine($this->db);
        // use for default searches - use for incoming links from other programs, only keyField supported 6/2/2021
        if ((isset($_POST[$this->keyField]) and $_POST[$this->keyField] != '') or (isset($_POST[$this->keyField . '_in1']) and $_POST[$this->keyField . '_in1'] != '')) {
            if (isset($_POST[$this->keyField]) and $_POST[$this->keyField] != '') {
                $val = $_POST[$this->keyField];
            } else {
                $val = $_POST[$this->keyField . '_in1'];
            }
            $operator[$this->keyField] = '=';
            $values[$this->keyField . '_in1'] = $val;
            $where[] = $this->keyField . ' = ? ';
            $pq_bindtypes = 'i';
            $pq_bindvalues[] = $val;
            $selectCriteria[] = $this->keyField . ' = ' . gls_esc_html($val);
        } else {
            for ($i = 0; $i < count($this->column_array); $i++) { // changed to for loop so column_array can be modified for temporary search fields 7/2/2019
                $col = $this->column_array[$i];
                if (isset($col['searchName'])) {
                    $fld = $col['searchName'];
                } else {
                    // remove any periods in field name
                    $fld = str_replace('.', '', $col['field']);
                }
                if (isset($col['search']) and !isset($col['forceShow'])) { // if the same field appears more than once in a column array, remove 'search' from the unwanted column 2/9/21
                    if (isset($_POST[$fld . '_so'])) {
                        $operator[$fld] = clean_param($_POST[$fld . '_so'], 's', true, $dbe->dblink); // this field can become literal SQL, so needs to be escaped
                    } else {
                        $operator[$fld] = '';
                    }
                    if ($operator[$fld] != '' and $operator[$fld] != 'All') {
                        // get the bindtype
                        if ($col['type'] != 'i' and $col['type'] != 'b') {
                            $typ = 's';
                        } else {
                            $typ = 'i';
                        }
                        // get the posted data
                        // don't trim the data because user may be searching for empty field or field that contains spaces
                        if (isset($_POST[$fld . '_in1'])) {
                            if ($col['type'] == 'd') { // date type
                                $values[$fld . '_in1'] = niceDate($_POST[$fld . '_in1'], false, 'Y-m-d');
                            } else {
                                if ($_POST[$fld . '_in1'] != '') {
                                    $values[$fld . '_in1'] = $_POST[$fld . '_in1'];//$_POST[$fld . '_in1'], $typ);
                                } else {
                                    $values[$fld . '_in1'] = '';
                                }
                            }
                        } else {
                            $values[$fld . '_in1'] = '';
                        }
                        if (isset($_POST[$fld . '_in2'])) {
                            if ($col['type'] == 'd') { // date type
                                $values[$fld . '_in2'] = niceDate($_POST[$fld . '_in2'], false, 'Y-m-d');
                            } else {
                                $values[$fld . '_in2'] = $_POST[$fld . '_in2'];//$_POST[$fld . '_in2'], $typ);
                            }
                        } else {
                            $values[$fld . '_in2'] = '';
                        }
                        // create WHERE sub-clause and add to $selectCriteria array
                        $altSearch = '';
                        $asParen = '';
                        if (isset($col['altSearch'])) {
                            if (strlen($values[$fld . '_in1']) > 0) {
                                $altSearch = $col['altSearch']['linkField'] . ' IN (SELECT  ' . $col['altSearch']['linkField'] . ' FROM ' . $col['altSearch']['table'] . ' WHERE ';// . $fld . ' = ? ))';
                                $asParen = ')';
                            }
                        }
//                                print_r($values);
                        if (isset($col['searchFunction'])) {
                            // function callback to determine search criteria
                            if (is_callable($col['searchFunction']) and strlen($values[$fld . '_in1']) > 0) {
                                $where[] = call_user_func($col['searchFunction'], $col, $values[$fld . '_in1'], $values[$fld . '_in2'], $operator[$fld], 'where');
                                $selectCriteria[] = call_user_func($col['searchFunction'], $col, $values[$fld . '_in1'], $values[$fld . '_in2'], $operator[$fld], 'selectCriteria');
                            }
                        } elseif ($col['type'] != 'lookup' and !isset($col['lookup'])) {
                            if ($operator[$fld] == 'like') {
                                if (strlen($values[$fld . '_in1']) > 0) {
                                    $where[] = '(' . $altSearch . $col['field'] . ' LIKE ?)' . $asParen;
                                    $pq_bindtypes .= $typ;
                                    $pq_bindvalues[] = '%' . $values[$fld . '_in1'] . '%';
                                    $selectCriteria[] = $col['heading'] . ' is Like "' . $values[$fld . '_in1'] . '"';
                                }
                            } elseif ($operator[$fld] == 'not like') {
                                $where[] = '(' . $altSearch . $col['field'] . ' NOT LIKE ?)' . $asParen;
                                $pq_bindtypes .= $typ;
                                $pq_bindvalues[] = '%' . $values[$fld . '_in1'] . '%';
                                $selectCriteria[] = $col['heading'] . ' is not Like "' . $values[$fld . '_in1'] . '"';
                            } elseif ($operator[$fld] == 'ign') {
                                // ignore it
                            } elseif ($operator[$fld] != 'range') {
                                if (($col['type'] == 'yn') or ($col['type'] == 'tf')) {// enum yes/no or true/false
                                    $where[] = '(' . $altSearch . $col['field'] . ' = ?)' . $asParen;
                                    $pq_bindtypes .= $typ;
                                    $pq_bindvalues[] = $operator[$fld];
                                    $selectCriteria[] = $col['heading'] . ' = "' . $operator[$fld] . '"';
                                } elseif ($col['type'] == 'b') { // boolean = yes/no
                                    $where[] = '(' . $altSearch . $col['field'] . ' = ?)' . $asParen;
                                    $pq_bindtypes .= $typ;
                                    $pq_bindvalues[] = $operator[$fld];
                                    $yesno = array('No', 'Yes');
                                    if (isset($col['altLabels'])) { // something other than Yes and No
                                        $yesno = $col['altLabels'];
                                    }
                                    $selectCriteria[] = $col['heading'] . ' = "' . $yesno[$operator[$fld]] . '"';
                                } elseif (is_array($col['format'])) { // added 9/2/2020 to change way of handling select boxes from format array
                                    $where[] = '(' . $altSearch . $col['field'] . ' = ?)' . $asParen;
                                    $pq_bindtypes .= $typ;
                                    $pq_bindvalues[] = $operator[$fld];
                                    $selectCriteria[] = $col['heading'] . ' = "' . $operator[$fld] . '"';
                                } else {
                                    if ($operator[$fld] == 'IS NULL') {
                                        $where[] = '(' . $altSearch . $col['field'] . ' ' . $operator[$fld] . ')' . $asParen;
                                        $selectCriteria[] = $col['heading'] . ' is empty';
                                    } else {
                                        // simple condition
                                        if ($col['type'] == 'd') {
//                                        $where[] = '(date(' . $col['field'] . ') ' . $operator[$fld] . ' ?)'; // remove any time portion for comparisons
                                            $where[] = '(' . $col['field'] . ' ' . $operator[$fld] . ' ?)'; // don't use date function to speed up queries
                                        } else {
                                            $where[] = '(' . $altSearch . $col['field'] . ' ' . $operator[$fld] . ' ?)' . $asParen;
                                        }
                                        $pq_bindtypes .= $typ;
                                        $pq_bindvalues[] = $values[$fld . '_in1'];
                                        $selectCriteria[] = $col['heading'] . ' ' . $operator[$fld] . ' "' . $values[$fld . '_in1'] . '"';
                                        if ($operator[$fld] == '=' and trim($values[$fld . '_in1']) == '') {
                                            // search for empty string - give warning to user
                                            $warningMsg = 'Note: You used "equals" in your search criteria but did not specify any text. This only returns records where that field is blank. If that was not your intention, please change "equals" to "LIKE %xxx%" and re-run your search. Or, if this doesn\'t work as expected, try using "is empty", if available';
                                        }
                                    }
                                }
                            } else {
                                // must be a Range
                                if ($col['type'] == 'd') {
//                                $where[] = '( date(`' . $col['field'] . '`) BETWEEN ? AND ?)'; // remove any time portion for comparisons
                                    $where[] = '( `' . $col['field'] . '` BETWEEN ? AND ?)'; // don't use date function to speed up queries
                                } else {
                                    $where[] = '(`' . $altSearch . $col['field'] . '` BETWEEN ? AND ?)' . $asParen;
                                }
                                $pq_bindtypes .= $typ . $typ;
                                $pq_bindvalues[] = $values[$fld . '_in1'];
                                $pq_bindvalues[] = $values[$fld . '_in2'];
                                $selectCriteria[] = $col['heading'] . ' is between "' . $values[$fld . '_in1'] . '" and "' . $values[$fld . '_in2'] . '"';
                            }

                        } else {
                            // process input if field is a lookup
                            if (isset($col['lookup'])) { // expects value of select
                                if (!isset($_POST[$fld . '_in1'])) { // handle tf or yn fields that also have lookup arrays
                                    $values[$fld . '_in1'] = $_POST[$fld . '_so']; // force string to get 'All' value
                                }
                                if (($typ == 's' and $values[$fld . '_in1'] != 'All') or ($typ == 'i' and $values[$fld . '_in1'] != 0)) {
                                    $where[] = '(' . $altSearch . $col['field'] . ' = ?)' . $asParen;
                                    $pq_bindtypes .= $typ;
                                    if ($values[$fld . '_in1'] == 999999) { // special code to search for none
                                        if ($typ == 'i') {
                                            $val = 0;
                                        } else {
                                            $val = '';
                                        }
                                        $pq_bindvalues[] = $val;
                                        $values[$fld . '_in1'] = 'None';
                                    } else {
                                        $pq_bindvalues[] = $values[$fld . '_in1'];
                                    }
                                    if (!is_array($col['lookup'])) {
                                        $selectCriteria[] = $col['heading'] . ' = ' . colLookup($this->db, $col['lookup'], $values[$fld . '_in1'], false, (isset($col['allowNone'])) and $col['allowNone'], (isset($col['altAllowNoneLabel'])) ? $col['altAllowNoneLabel'] : 'None');
                                    } else {
                                        if (isset($col['useKeys'])) {
                                            $selectCriteria[] = $col['heading'] . ' = "' . $col['lookup'][$values[$fld . '_in1']] . '"';
                                        } else {
                                            $selectCriteria[] = $col['heading'] . ' = "' . $values[$fld . '_in1'] . '"';
                                        }
                                    }
                                }
                            } elseif ($col['type'] == 'lookup' and isset($col['format']['linkTable'])) {
                                // many to many relationship with a linking table, return all records that have a linking record to the selected item
                                // won't work for foreign database
                                if ((!is_numeric($values[$fld . '_in1']) and $values[$fld . '_in1'] != 'All') or (is_numeric($values[$fld . '_in1']) and $values[$fld . '_in1'] != 0)) {
                                    $where[] = '(' . $this->keyField . ' IN (SELECT `' . $col['format']['keyName'] . '` FROM `' . $col['format']['linkTable'] . '` WHERE `' . $col['format']['linkField'] . '` = ? ))';
                                    $pq_bindtypes .= 'i'; // expects integer keys
                                    $pq_bindvalues[] = $values[$fld . '_in1'];
                                    $result = $dbe->getRowWhere($col['format']['sourceTable'], $col['format']['sourceField'], $values[$fld . '_in1']);
                                    $selectCriteria[] = $col['heading'] . ' contains ' . $result[$col['format']['displayField']];
                                }
                            } else {
                                if ($values[$fld . '_in1'] != '') {
                                    // 7/19/19 changed linkField to keyfield below
                                    $lookupArray = getLookup($col['format']['db'], $col['format']['table'], $col['format']['keyfield'], $col['format']['field'], $values[$fld . '_in1'], $operator[$fld], $values[$fld . '_in2'], (isset($col['format']['sourceCriteria'])) ? $col['format']['sourceCriteria'] : '');
//                                print_r($lookupArray);
                                    if ($lookupArray) {
                                        if (count($lookupArray) > 1) {
                                            if (isset($col['format']['linkField'])) {
                                                $where[] = '(' . $col['format']['linkField'] . ' IN (' . implode(', ', $lookupArray) . '))';
                                            } else {
                                                $where[] = '(' . $col['field'] . ' IN (' . implode(', ', $lookupArray) . '))';
                                            }
                                        } else {
                                            if (isset($col['format']['linkField'])) {
                                                $where[] = '(' . $col['format']['linkField'] . ' = ' . $lookupArray[0] . ')';
                                            } else {
                                                $where[] = '(' . $col['field'] . ' = ' . $lookupArray[0] . ')';
                                            }
                                        }
                                        $selectCriteria[] = $col['heading'] . ' ' . $operator[$fld] . ' "' . $values[$fld . '_in1'] . '"';
                                    } else {
                                        if (strlen($values[$fld . '_in1']) > 0) {
                                            $where[] = '(0 = 1)'; // force search to fail
                                        }
                                    }
                                }
                            }
                        }
                        if (!$col['search']) {
                            $this->column_array[$i]['search'] = true;
                        }

                    } else {
                        // operator not set for this field - ignore it
                        $operator[$fld] = '';
                        $values[$fld . '_in1'] = '';
                        $values[$fld . '_in2'] = '';
                    }
                }
            }
        }
//    print_r($where);
//    echo '<br/>';
//    print_r($pq_bindvalues);
//    echo '<br/>'.$pq_bindtypes;
        $output = array(
            'operator'       => $operator,
            'values'         => $values,
            'where'          => $where,
            'pq_bindtypes'   => $pq_bindtypes,
            'pq_bindvalues'  => $pq_bindvalues,
            'selectCriteria' => $selectCriteria,
        );

        return $output;
    }

    /**
     * newRecordLink
     * Output the add new record link
     *
     * @return string
     */
    private function newRecordLink(): string
    {
        if (isset($this->allowAddMode)) {
            return '<button type="button" class="buttonBar doOpenAddMode">&nbsp;&nbsp;<i class="bi bi-plus-square"></i>&nbsp;&nbsp;Add New ' . $this->editTitle . '&nbsp;</button>';
        }
        return '';
    }

    /**
     * processExports
     *
     * @throws phpmailerException
     */
    private function processExports()
    {
        if ($this->reportTitle == '') {
            $this->reportTitle = $this->title;
        }
        $filename = str_replace(' ', '_', strtolower($this->reportTitle));
        // get the current search criteria
        $filters = $this->ProcessSearchRequest();
        $this->rt->setSubtotalBySort($this->subtotalBySort);
        $this->rt->setShowGrandTotal($this->showGrandTotal);
        $this->setColumnArray($this->rt->getColumns());
        // reconstruct the table in memory
        $this->rt->setQueryAll('SELECT ' . $this->qrySelect . ' FROM ' . $this->table . ' ', $filters['where'], '', $_POST['sort1'], $_POST['sort2'], $_POST['sort3'], $filters['pq_bindtypes'], $filters['pq_bindvalues']);

        if ($_POST['currentpagePDF'] == 'true' or $_POST['exporttype'] != 'pdf') {
            $currPage = true;
            $this->rt->setLimit($_POST['limit']);
            $this->rt->setOffset($_POST['offset']);
        } elseif ($_POST['currentpagePDF'] == 'false') {
            $currPage = false; // will force pdf output to a temp file to save memory using MyPDF_lf class
            $this->rt->setLimit(-1); // code for All records
            $this->rt->setOffset(0);
        }
        $this->rt->processPagination(false);
        $this->rt->processQuery(false);
//        if (memory_get_usage() > ((ini_get("memory_limit") * 1024 * 1024) * .64)) {
//            // if memory usage exeeds 64% of capacity, exit
//            echo 'Records exceed available memory. Please limit your search criteria.<br/>' . memory_get_usage() . ' - ' . ((ini_get("memory_limit") * 1024 * 1024));
//            exit;
//        }
        $this->rt->processTable();
        $subtitle = implode(', ', $filters['selectCriteria']);

        $sendTo = '';
        if (isset($_POST['exportTo']) and $_POST['exportTo'] == 'email') {
            if (isset($_POST['destEmail'])) {
                $sendTo = $_POST['destEmail'];
            }
        }
        if (isset($_POST['scaleFactor'])) {
            $scaleFactor = $_POST['scaleFactor'];
            if (!is_numeric($scaleFactor)) {
                $scaleFactor = 1;
            } else {
                $scaleFactor = $scaleFactor / 100;
                if ($scaleFactor >= 2) {
                    $scaleFactor = 1.9;
                } elseif ($scaleFactor < .5) {
                    $scaleFactor = .5;
                }
            }
        }
        // resort the array by 'order'
//        echo '<pre>'; print_r($this->column_array); echo '</pre>';exit;
        $this->column_array = sortArray($this->column_array, 'order');
        if (isset($_POST['exporttype']) and $_POST['exporttype'] == 'pdf') {
            exportToPDF($this->reportTitle, $subtitle, $this->column_array, $this->rt->getOutput(), $scaleFactor, $sendTo, '', '', !$currPage);
        } else {
            if (isset($_POST['currentpageCSV']) and $_POST['currentpageCSV'] == 'true') $currPage = true; else $currPage = false;
            exportToCSV($filename, $this->column_array, $this->rt->getOutput(), $this->rt->getCountRows(), $filters, $this->table, $this->rt->getQryOrderBy(), null, $currPage, $this->reportTitle, $subtitle, $sendTo);
        }
    }

    /**
     * getSubTitle
     * Construct the subTitle value
     *
     * @param array $subTitleFields
     * @param array $row
     * @param string $db
     * @return string
     */
    private function getSubTitle(array $subTitleFields, array $row, string $db): string
    {
        $subTitle = '';
        foreach ($subTitleFields as $field) {
            if (!isset($row[$field])) {
                // if field is actually column heading and type is lookup, do lookup
                $foundit = false;
                foreach ($this->column_array as $col) {
                    if ($col['heading'] == $field and isset($col['lookup'])) {
                        $foundit = true;
                        $subTitle .= colLookup($db, $col['lookup'], $row[$col['field']]) . ' ';
                        break;
                    }
                }
                if (!$foundit) {
                    $subTitle .= $field . ' ';
                }
            } else {
                $subTitle .= $row[$field] . ' ';
            }
        }
        return $subTitle;
    }

    /**
     * @param array $column_array
     */
    private function setColumnArray(array $column_array)
    {
        $this->column_array = $column_array;
//        $this->getUserPreferences();
    }

    /**
     * getUserPreferences
     * Retrieve the user preferences and apply them
     */
    private function getUserPreferences()
    {
        // if order field is missing from column_array, add it and set it to actual order
        for ($i = 0; $i < count($this->column_array); $i++) {
            if (!isset($this->column_array[$i]['order'])) {
                $this->column_array[$i]['order'] = $i;
            }
        }
        // get user preferences
        // $valueArray = getPreferences(pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME));
        // if (isset($_POST['btnRestoreDefaults'])) {
        //     erasePreferences(pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME));
        //     $this->rt->setFlushColWidths(true);
        //     // also need to clear all POST vars
        //     unset($_POST);
        // } else {
        //     if ($valueArray) {
        //         $column_array = $this->column_array;
        //         $orderBy = $this->rt->getQryOrderBy();
        //         $showAsListing = $this->showAsListing;
        //         extract($valueArray);
        //         $this->column_array = mergeColumnArray($this->column_array, $column_array);
        //         $this->rt->setQryOrderBy($orderBy[0], $orderBy[1], $orderBy[2]);
        //         $this->showAsListing = $showAsListing;
        //         // send the prefs to reportTable class
        //         for ($i = 0; $i < count($this->column_array); $i++) {
        //             // set the order
        //             $field = removePeriodsInFieldName($this->column_array[$i]['field']);
        //             $this->rt->setColumnOrder($i, (int)$this->column_array[$i]['order']);
        //             $this->rt->setColumnWidth($i, $this->column_array[$i]['width']);
        //             if ($this->column_array[$i]['show']) {
        //                 $this->rt->hideShowColumn($i, true);
        //             } else {
        //                 $this->rt->hideShowColumn($i, false);
        //             }
        //         }
        //     }
        // }
        $this->prefChange = false;
    }

    /**
     * checkColWidthAndSort
     * Check if Column width or sort order has been changed and save preferences
     */
    public function checkColWidthAndSort()
    {
        $this->column_array = sortArray($this->column_array, 'order');
        $oldOrderBy = $this->rt->getQryOrderBy();
        $orderBy = $oldOrderBy;
        // column sorting routines
        if (isset($_POST['sort1'])) { // preserve default if coming from another program
            $orderBy[0] = $_POST['sort1'];
        }
        if (isset($_POST['sort2'])) {
            $orderBy[1] = $_POST['sort2'];
        }
        if (isset($_POST['sort3'])) {
            $orderBy[2] = $_POST['sort3'];
        }
        if ($orderBy[0] != $oldOrderBy[0] or $orderBy[1] != $oldOrderBy[1] or $orderBy[2] != $oldOrderBy[2]) {
            if (isset($_POST['rt_process']) and $_POST['rt_process'] == 'getSort') {
                $this->prefChange = true;
            }
        }
        // if column widths changed
        if (isset($_POST['rt_process']) and $_POST['rt_process'] == 'saveColWidths') {
            $j = 0;
            for ($i = 0; $i < count($this->column_array); $i++) {
                if ($this->column_array[$i]['show']) {
                    if ($j == $_POST['index']) {

                        $colWidth = $_POST['colWidths'][$j];
                        // convert to inches
                        $colWidthIn = round((float)((intval($colWidth) - 10) / 96), 1);
                        if ($this->column_array[$i]['width'] != $colWidthIn) {
                            $this->column_array[$i]['width'] = $colWidthIn;
                            $this->prefChange = true;
                        }
                        break;
                    }
                    $j++;
                }
            }
        }
        // columns to show and column order
        // at least one column must be enabled for showing
        $someShown = false;
        for ($i = 0; $i < count($this->column_array); $i++) {
            $field = $this->column_array[$i]['field'];
            if (isset($_POST[$field . $i . '_sho'])) {
                $someShown = true;
            }
        }
        if ($someShown) {
            for ((int)$i = 0; $i < count($this->column_array); $i++) {
                $field = $this->column_array[$i]['field'];
                if (isset($_POST[$field . $i . '_sho'])) {
                    if (!$this->column_array[$i]['show']) {
                        $this->prefChange = true;
                        $this->column_array[$i]['show'] = true;
                        $this->rt->hideShowColumn($i, true);
                    }
                } else {
                    if ($this->column_array[$i]['show']) {
                        $this->prefChange = true;
                        $this->column_array[$i]['show'] = false;
                        $this->rt->hideShowColumn($i, false);
                    }
                }
                if (isset($_POST[$field . $i . '_ord'])) {
//                    echo $field . ' ' . $_POST[$field . '_ord'].'<br/>';
                    $ordr = (int)$_POST[$field . $i . '_ord'];
                    if ($this->column_array[$i]['order'] != $ordr) {
                        $this->prefChange = true;
                        // set the order
                        $this->rt->setColumnOrder($i, $ordr);
                    }
                    $this->column_array[$i]['order'] = $ordr;
                }
            }
        } else {
            // use default show settings // changed 2/26/2019 by Lee
            for ($i = 0; $i < count($this->column_array); $i++) {
                if (!isset($this->column_array[$i]['order'])) {
                    // if column order element is missing set to default order of array
                    $this->column_array[$i]['order'] = $i;
                }
            }
        }
        // save any preferences change
        if ($this->prefChange) {
            $column_array = $this->column_array;
            $showAsListing = $this->showAsListing;
//            savePreferences(pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME), compact('column_array', 'orderBy', 'showAsListing'));
            $this->prefChange = false;
            $this->rt->setFlushColWidths(true);
            //    echo 'PREF CHANGE! ';
        }
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
     * @param bool $useATSubTableClass
     */
    public function setUseATSubTableClass(bool $useATSubTableClass): void
    {
        $this->useATSubTableClass = $useATSubTableClass;
    }

    /**
     * @param bool $hasHomePage
     */
    public function setHasHomePage(bool $hasHomePage): void
    {
        $this->hasHomePage = $hasHomePage;
    }

    /**
     * @param string $homePageCallback
     */
    public function setHomePageCallback(string $homePageCallback): void
    {
        $this->homePageCallback = $homePageCallback;
    }

    /**
     * @param string $homeTitle
     */
    public function setHomeTitle(string $homeTitle): void
    {
        $this->homeTitle = $homeTitle;
    }

    /**
     * @param bool $doInitialSearch
     */
    public function setDoInitialSearch(bool $doInitialSearch): void
    {
        $this->doInitialSearch = $doInitialSearch;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @param string $reportTitle
     */
    public function setReportTitle(string $reportTitle): void
    {
        $this->reportTitle = $reportTitle;
    }

    /**
     * @param bool $hideSearchCriteria
     */
    public function setHideSearchCriteria(bool $hideSearchCriteria): void
    {
        $this->hideSearchCriteria = $hideSearchCriteria;
    }

    /**
     * @param string $additionalHeaders
     */
    public function setAdditionalHeaders(string $additionalHeaders): void
    {
        $this->additionalHeaders .= $additionalHeaders;
    }

    /**
     * @param array|bool $breadcrumbs
     */
    public function setBreadcrumbs($breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * @param int $searchHeadingWidth
     */
    public function setSearchHeadingWidth(int $searchHeadingWidth): void
    {
        $this->searchHeadingWidth = $searchHeadingWidth;
    }

    /**
     * @param bool $showExport
     */
    public function setShowExport(bool $showExport): void
    {
        $this->showExport = $showExport;
    }

    /**
     * @param string $infoText
     */
    public function setInfoText(string $infoText): void
    {
        $this->infoText = $infoText;
    }

    /**
     * @param bool $allowAll
     */
    public function setAllowAll(bool $allowAll): void
    {
        $this->allowAll = $allowAll;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param bool $allowTableEditing
     */
    public function setAllowTableEditing(bool $allowTableEditing): void
    {
        $this->allowTableEditing = $allowTableEditing;
    }

    /**
     * @param array $subTitleFields
     */
    public function setSubTitleFields(array $subTitleFields): void
    {
        $this->subTitleFields = $subTitleFields;
    }

    /**
     * @param string $qrySelect
     */
    public function setQrySelect(string $qrySelect): void
    {
        $this->qrySelect = $qrySelect;
    }

    /**
     * @param array|int[] $where
     */
    public function setWhere(array $where): void
    {
        $this->where = $where;
    }

    /**
     * @param bool $showGrandTotal
     */
    public function setShowGrandTotal(bool $showGrandTotal): void
    {
        $this->showGrandTotal = $showGrandTotal;
    }

    /**
     * @param bool $subtotalBySort
     */
    public function setSubtotalBySort(bool $subtotalBySort): void
    {
        $this->subtotalBySort = $subtotalBySort;
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
     * @param string $addModeComments
     */
    public function setAddModeComments(string $addModeComments): void
    {
        $this->addModeComments = $addModeComments;
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
     * @param bool $allowHideProfile
     */
    public function setAllowHideProfile(bool $allowHideProfile): void
    {
        $this->allowHideProfile = $allowHideProfile;
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
     * @param string $previewButton
     */
    public function setPreviewButton(string $previewButton): void
    {
        $this->previewButton = $previewButton;
    }

    /**
     * @param string $profileLabelWidth
     */
    public function setProfileLabelWidth(string $profileLabelWidth): void
    {
        $this->profileLabelWidth = $profileLabelWidth;
    }

    /**
     * @param bool $editModeNoColumns
     */
    public function setEditModeNoColumns(bool $editModeNoColumns): void
    {
        $this->editModeNoColumns = $editModeNoColumns;
    }

    /**
     * @param int $scaleFactor
     */
    public function setScaleFactor(int $scaleFactor): void
    {
        $this->scaleFactor = $scaleFactor;
    }

    /**
     * @param string $imagePath
     */
    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }




}

/***************************************************
 * setOperatorArray
 * Used to help construct search criteria in reports
 * Called from RTCols class
 * @param string $type - the actual comparison operator or a short string indicating special comparisons
 * @return array - the operator (value) and a string description (text)
 */
function setOperatorArray(string $type = ''):array
{
    // used in v2 reports
    if ($type == '=') {
        $operator_array = array(
            array('value' => 'All', 'text' => '- All -'),
            array('value' => '=', 'text' => 'equals'),
        );
    } elseif ($type == 'yn') {
        $operator_array = array(
            array('value' => 'All', 'text' => '- All -'),
            array('value' => 'Yes', 'text' => 'Yes'),
            array('value' => 'No', 'text' => 'No'),
        );
    } elseif ($type == 'tf') {
        $operator_array = array(
            array('value' => 'All', 'text' => '- All -'),
            array('value' => 'true', 'text' => 'True'),
            array('value' => 'false', 'text' => 'False'),
        );
    } elseif ($type == 'b') {
        $operator_array = array(
            array('value' => 'All', 'text' => '- All -'),
            array('value' => '1', 'text' => 'Yes'),
            array('value' => '0', 'text' => 'No'),
        );
    } elseif ($type == 't' or $type == 'lookup') {
        $operator_array = array(
            array('value' => 'like', 'text' => 'LIKE %xxx%'),
            array('value' => '=', 'text' => 'equals'), // added 12/6/21
        );
    } elseif ($type == 'd') {
        $operator_array = array(
            array('value' => 'ign', 'text' => '- Ignore -'),
            array('value' => '=', 'text' => 'equals'),
            array('value' => '<', 'text' => 'less than'),
            array('value' => '<=', 'text' => 'less than or equal to'),
            array('value' => '>', 'text' => 'greater than'),
            array('value' => '>=', 'text' => 'greater than or equal to'),
            array('value' => '!=', 'text' => 'not equal to'),
            array('value' => 'range', 'text' => 'Range'),
        );
    } elseif ($type == 'i') { // added integer type 5/6/2019
        $operator_array = array(
            array('value' => 'ign', 'text' => '- Ignore -'),
            array('value' => '=', 'text' => 'equals'),
            array('value' => '<', 'text' => 'less than'),
            array('value' => '<=', 'text' => 'less than or equal to'),
            array('value' => '>', 'text' => 'greater than'),
            array('value' => '>=', 'text' => 'greater than or equal to'),
            array('value' => '!=', 'text' => 'not equal to'),
            array('value' => 'range', 'text' => 'Range'),
        );
    } else {
        $operator_array = array(
            array('value' => 'like', 'text' => 'LIKE %xxx%'),
            array('value' => 'not like', 'text' => 'NOT LIKE %xxx%'),
            array('value' => '=', 'text' => 'equals'),
            array('value' => '<', 'text' => 'less than'),
            array('value' => '<=', 'text' => 'less than or equal to'),
            array('value' => '>', 'text' => 'greater than'),
            array('value' => '>=', 'text' => 'greater than or equal to'),
            array('value' => '!=', 'text' => 'not equal to'),
            array('value' => 'range', 'text' => 'Range'),
        );
    }
    return $operator_array;
}

/**
 * getLookup
 * get an array of keyfields based on search criteria from a lookup field
 * Moved from common.php 5/11/23
 *
 * @param string $db
 * @param string $table
 * @param string $keyField
 * @param string $field
 * @param $value
 * @param string $operator
 * @param string $value2
 * @param string $criteria
 * @return array|bool - array of ID's | false - return a maximum of 50 records
 */
function getLookup(string $db, string $table, string $keyField, string $field, $value, string $operator = 'like', string $value2 = '', string $criteria = '')
{
    $dbe = new DBEngine($db);
    $pq_bindtypes = '';
    $pq_bindvalues = array();
    $where = '';
    if ($criteria != '') {
        $where = $criteria . ' AND ';
    }
    if ($operator == 'like') {
        // like %x% - doesn't seem to work as parameterized query
        if (strlen($value) > 0) {
            $where .= '(' . $field . ' LIKE "%' . $value . '%")';
        }
    } elseif ($value2 == '') {
        // simple = condition - note, boolean not supported
        $where .= '(' . $field . ' ' . $operator . ' ?)';
        $pq_bindtypes = 's';
        $pq_bindvalues = array($value);
    } else {
        // must be a Range
        $where .= '(' . $field . ' BETWEEN ? AND ?)';
        $pq_bindtypes = 'ss';
        $pq_bindvalues = array($value, $value2);
    }
    $pq_query = 'SELECT ' . $keyField . ' FROM ' . $table . ' WHERE ' . $where . ' ORDER BY ' . $keyField . ' LIMIT 50 ';
    if ($where != '') {
        //echo $pq_query;
        $dbe->setBindtypes($pq_bindtypes);
        $dbe->setBindvalues($pq_bindvalues);
        $lookuprows = $dbe->execute_query($pq_query);

        $dbe->close();
        if ($lookuprows) {
            $result = array();
            foreach ($lookuprows as $row) {
                $result[] = $row[$keyField];
            }
            return $result;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
