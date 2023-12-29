<?php
/**
 * ReportTable Class
 * New version that works with RTCols Class for column contents
 * Lee updated all escaping to use gls_esc_xxx functions 8/19/2020
 *
 * Created by PhpStorm.
 * User: Lee
 * Date: 2/25/2020
 */
require_once(PLMPATH . 'classes/RTCols.php');

class ReportTable
{
    private ?string $db = '';
    private string $ajaxHandler = '';
    private array $columns = array(); // an array of RTCols objects
    private ?string $table = '';
    private string $qrySelect = '';
    private array $qryWhere = array(), $qryOrderBy = array();
    private ?array $pq_bindvalues = array();
    private string $pq_bindtypes = '';
    private string $qryGroupBy = '';
    private int $countRows = 0;
    private int $limit = 0, $offset = 0, $totPages = 0;
    private int $page = 1;
    private $rows = array(); // the query result rows for the table, note can be type array or false
    private array $output = array(); // the processed output of the table, in rows and columns
    private ?string $keyField = '';
    private string $subtotals = '';
    private bool $allowEditing = false,  $subtotalBySort = false, $showGrandTotal = false;
// private $sectionHeadings = false; // works with subtotals to add an extra row at top of section
    private int $actualColumns = 0;
    private ?string $identifier = '';
    private string $linkField = '';
    private $linkID = 0;
    private string $rowClass = '', $colClass = 'rtColumnsHover'; // classes for hover effects, cols will only show if sort = true
    private string $header_color = '';
    private string $qryFunction = ''; // alternative to using db query in class. Requires a callback function that processes the data and return assoc array or false if empty
    private string $refreshCallback = ''; // a js function that will be called every refresh
    private string $editColumn = ''; // allow column to be edited
    private bool $showAsList = false; // if true, output a search results listing instead of a table
    private bool $noColResize = false; // if true, disable column resizing
    private bool $flushColWidths = false; // if true, stored column widths will be flushed, should be set whenever changing the column structure
    private string $emptyMessage = '';
    private bool $hasProfileMode = false; // if true and record count of query = 1, the table data is not created or displayed and refreshCallback is called


    /**
     * ReportTable constructor.
     *
     * @param string|null $db
     * @param string|null $table
     * @param string|null $keyField
     * @param string|null $identifier
     * @param string|null $ajaxHandler
     */
    public function __construct(?string $db, ?string $table = '', ?string $keyField = '', ?string $identifier = 'rt1', ?string $ajaxHandler = '')
    {

        $this->db = $db;
        if ($table != '') $this->table = $table;
        if ($keyField != '') $this->keyField = $keyField;
        $this->identifier = $identifier;
        if ($ajaxHandler != '') $this->ajaxHandler = $ajaxHandler;

    }

    /**
     * addColumn
     * Convert column array input to RTCols class
     *
     * @param array $col
     */
    public function addColumn(array $col)
    {
        $num = count($this->columns);
        if (!isset($col['order'])) {
            $col['order'] = $num;
        }
        $col['origOrder'] = $num;
        $this->columns[$num] = new RTCols($col['field'], $col['heading'], $col['type'], $col);
    }

    /**
     * clearColumns
     * clear all column items and start new
     */
    public function clearColumns()
    {
        // use this function to clear all column items and start new
        $this->columns = array();
    }

    /**
     * processQuery
     *
     * @param bool $debug
     */
    public function processQuery(bool $debug = false)
    {
        if ($this->qryFunction != '' and is_callable($this->qryFunction)) {
            $rows = call_user_func($this->qryFunction, $this->limit, $this->offset, $this->qryWhere, $this->qryOrderBy);
            if ($rows) {
                $this->rows = $rows;
            }
        } else {
            // query the database
            $dbe = new DBEngine($this->db, $debug);
            if (count($this->qryWhere) > 1 and $this->qryWhere[0] == '1') array_shift($this->qryWhere); // remove the default first element of the where clause
            $pq_query = $this->qrySelect . ' WHERE ' . implode(' AND ', $this->qryWhere);
            if ($this->qryGroupBy != '') {
                $pq_query .= ' GROUP BY ' . $this->qryGroupBy;
            }
            $pq_query .= ' ORDER BY ' . implode(',', $this->qryOrderBy);
            if ($this->limit > 0) { // limit 0 or -1 means no limit, all records returned
                $pq_query .= ' LIMIT ' . $this->offset . ',' . $this->limit;
            }
            $dbe->setBindtypes($this->pq_bindtypes);
            $dbe->setBindvalues($this->pq_bindvalues);
            $this->rows = $dbe->execute_query($pq_query);  // execute query
            if ($this->limit < 1 and $this->rows) { // limit 0 or -1 means no limit, all records returned
                $this->countRows = count($this->rows);
            }

            $dbe->close();
        }
    }

    /**
     * processPagination
     *
     * @param bool $debug
     */
    public function processPagination(bool $debug = false)
    {
        if ($this->qryFunction != '' and is_callable($this->qryFunction)) {
            // Pagination must be handled in the callback function.
            $junk = call_user_func($this->qryFunction, $this->limit, $this->offset, $this->qryWhere, $this->qryOrderBy);
        } else {
            // get total count of filtered records for pagination
            $dbe = new DBEngine($this->db, $debug);
            $pq_query = 'SELECT count(*) AS cnt FROM ' . $this->table . ' WHERE ' . implode(' AND ', $this->qryWhere);
            if ($this->qryGroupBy != '') {
                $pq_query .= ' GROUP BY ' . $this->qryGroupBy;
            }
            $dbe->setBindtypes($this->pq_bindtypes);
            $dbe->setBindvalues($this->pq_bindvalues);
            $allrows = $dbe->execute_query($pq_query);  // execute query
            $dbe->close();
            if ($this->qryGroupBy != '') {
                $count_rows = count($allrows);
            } else {
                $count_rows = $allrows[0]['cnt'];
            }
            $this->countRows = (int) $count_rows;

            $this->totPages = 1;
            if ($this->limit == -1) { // all records
                $this->limit = $this->countRows;
            }
            if ($this->countRows > 0 and $this->limit > 0) { // avoid division by zero error
                if ($this->countRows / $this->limit < 1) {
                    $this->totPages = 1;
                } elseif (($this->countRows % $this->limit) != 0) { // modulus
                    $this->totPages = round(($this->countRows / $this->limit) + .5);
                } else {
                    $this->totPages = round($this->countRows / $this->limit);
                }
            } else {
                $this->totPages = 1;
            }
            if ($this->totPages > 1) {
                $this->page = round(($this->offset / $this->limit) + 1);
            } else {
                $this->page = 1;
                $this->offset = 0;
            }
            if ($this->page > $this->totPages) {
                $this->offset = 0;
                $this->page = 1;
            }
        }
    }

    /**
     * showTable
     * Output the table structure and the heading line
     */
    public function showTable(bool $structureOnly = false)
    {
        // output the html structure of the table, includes header row but no data rows
        global $currencySymbol;
        if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;

        // resort the array by 'order'
        $cols = sortArray($this->columns, 'order', false, true);
        $totWidth = 0;
        // set total width of the table by adding up the columns - 9/23/2019
        foreach ($cols as $col) {
            if ($col->show and !isset($col->attributes['sectionheading'])) {
                if (isset($col->attributes['width'])) {
                    $totWidth += $col->attributes['width'];
                } else {
                    $totWidth += 1; // default to 1 inch if width not set
                }
            }
        }
        ?>
        <div class="double-scroll" id="double-scroll<?=$this->identifier?>">
            <div class="report_table_overlay" id="report_table_overlay_<?=$this->identifier?>" title="Loading&hellip;"></div>
            <table class="report_table" id="report_table_<?=$this->identifier?>" style="width: <?=gls_esc_attr($totWidth)?>in;">
                <tr class="report_hdr_row" <?=($this->header_color != '')?'style="background-color: '.$this->header_color.';"':''?>>
                <?php
                $i = 0;
                foreach ($cols as $col) {
                    if ($col->show and !isset($col->attributes['sectionheading'])) {
                        echo $col->displayHeading($this->qryOrderBy[0], $this->allowEditing, $this->actualColumns);

                        $this->actualColumns++;
                    }
//                    if ($col['subtotal']) { // check for a subtotal field - currently only one allowed - no longer used 11/4/2019 only supporting subtotalbysort
//                        $this->subtotals = $col['field'];
//                    }
    //                if ($col['sectionheading']) {
    //                    // add a section header row
    //                    $sectionHeadings = $col; // set it to store the $col array so we can access the field and header later without looping the $cols
    //                }
                    $i++;
                }
                ?>
                </tr>
                <?php
//                $this->processQuery();
                if (!$structureOnly) $this->outputTable();

                if ($this->refreshCallback != '') {
                    ?>
                    <script>
                    //$( document ).ready( function () {
                    //    window["<?//=$this->refreshCallback?>//"]();
                    //});
                    </script>
                    <?php
                }
                ?>
            </table>
        </div>
        <?php
    }

    /**
     * showListing
     * If in listing mode, output the table structure
     */
    public function showListing(bool $structureOnly = false)
    {
        global $currencySymbol;
        if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;

        // show results in listing format

        // resort the array by 'order'
//        $this->columns = sortArray($this->columns, 'order');

        ?>
        <div class="report_table_overlay" id="report_table_overlay_<?=$this->identifier?>" title="Loading&hellip;"></div>
        <table class="report_table" id="report_table_<?=$this->identifier?>" style="width: 100%; /*min-width: 946px;*/">
            <tr class="report_hdr_row"></tr>
        <?php

//        $this->processQuery();
        if (!$structureOnly) $this->outputTable();

        if ($this->refreshCallback != '') {
            ?>
            <script>
            // $( document ).ready( function () {
                //window["<?//=$this->refreshCallback?>//"]();
            // });
            </script>
            <?php
        }
        ?>
        </table>
        <?php

    }

    /**
     * processAjaxRequests
     *
     * @param bool $debug
     */
    public function processAjaxRequests(bool $debug = false)
    {
        if (isset($_POST['rt_process']) and $_POST['identifier'] == $this->identifier) {
            if ($_POST['rt_process'] == 'load') {
                // Ajax to fill table
                $identifier = $_POST['identifier'];
                $this->setQryOrderBy($_POST['sort1'], $_POST['sort2'], $_POST['sort3']);
                $this->setLimit($_POST['limit']);
                $this->setOffset($_POST['offset']);
                $this->setShowAsList(($_POST['showAsList']=='true')?true:false);
                if ($this->allowEditing) {
                    $this->setEditColumn($_POST['editCol']);
                }
                if (isset($_POST['bindtypes'])) {
                    $this->setPqBindtypes($_POST['bindtypes']);
                    $this->setPqBindvalues($_POST['bindValues']);
                    $this->setQryWhere($_POST['where']);
                }
                $this->processPagination($debug);
                $this->processQuery($debug);
                if ($this->subtotalBySort) {
                    $this->subtotals = substr($this->qryOrderBy[0], 0, strpos($this->qryOrderBy[0], ' ')); // enable subtotals based on primary sort field
                }
                if ($this->totPages > 1) {
                    $this->showGrandTotal = false;
                }
                if ($this->hasProfileMode and $this->getCountRows() == 1) {
                    // for profile mode, do not create the table or output it
                    // send the count and the single row // send all JSON 9/27/23 (Lee)
                    echo json_encode(array('countRows'=>$this->getCountRows(),'rows'=>$this->rows)); // added 5/25/21 to support hasProfileMode
                } else {
                    $this->processTable();
                    ob_start(); // trap the output of the next line into a variable 9/27/23 (Lee)
                    $this->outputTable();
                    $output = ob_get_contents();
                    ob_end_clean();
                    echo json_encode(array('countRows'=>$this->getCountRows(),'rows'=>$output));
                }
                exit;
            } elseif ($_POST['rt_process'] == 'getSort') {
                if (isset($_POST['bindtypes'])) {
                    if ($_POST['where'] == array()) $_POST['where'] = array('1');
                    $this->setPqBindtypes($_POST['bindtypes']);
                    $this->setPqBindvalues($_POST['bindValues']);
                    $this->setQryWhere($_POST['where']);
                }
                $this->setQryOrderBy($_POST['sort1'], $_POST['sort2'], $_POST['sort3']);
                echo json_encode(array('sort'=>$this->getSortFieldHeadings()));
                exit;
            } elseif ($_POST['rt_process'] == 'totPages') {
                // calculate and send the total pages and total rows in query
                if (isset($_POST['bindtypes'])) {
                    if ($_POST['where'] == array()) $_POST['where'] = array('1');
                    $this->setPqBindtypes($_POST['bindtypes']);
                    $this->setPqBindvalues($_POST['bindValues']);
                    $this->setQryWhere($_POST['where']);
                }
                $this->setLimit($_POST['limit']);
                $this->setOffset($_POST['offset']);
                $this->processPagination($debug);
                echo json_encode(array('totPages'=>$this->getTotPages(), 'countRows'=>number_format($this->countRows))); // send all JSON 9/27/23 (Lee)
                exit;
            } elseif ($_POST['rt_process'] == 'saveColWidths') {
                // do nothing
                exit;
            }
        }
    }

    /**
     * processTable
     * Processes the data into the output array
     */
    public function processTable()
    {
        global $currencySymbol;
        // create array of output row data
        $this->output = array(); // need to reset this every time
        $i = 0; // count of rows
        if ($this->rowClass != '') {
            $rowClasses = ' class="'.$this->rowClass.'" ';
        } else {
            $rowClasses = '';
        }
        if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;
        // resort the array by 'order'
        $cols =  sortArray($this->columns, 'order', false, true);
        $subtots = array();
        $grandTots = array();
        $oldid = '';
        $hideRow = '';
        foreach ($this->rows as $row) {
            if ($this->subtotals and !$this->showAsList) { // If Subtotals included in this report
                if ($row[$this->subtotals] <> $oldid) {
                    if ($oldid != '') {
                        // create a subtotal line
                        $colArray = array();
                        for ($j=0; $j< count($cols); $j++) {
                            $fld = $cols[$j]->field;
                            if ($cols[$j]->field == $this->subtotals) {
                                $dispHTML = '<td class="report_subtotal">Total for '. $subtots['item'].'</td>';
                                $dispValue = 'Total for '.$subtots['item'];
                                if (!$cols[$j]->show) {
                                    $j++;
                                }
                            } else {
                                if ($cols[$j]->show) {
                                    if ($cols[$j]->attributes['format'] == 'currency') {
                                        $dispHTML =  '<td class="report_subtotal" style="text-align: right;">'.$currencySymbol.number_format($subtots[$fld], 2).'</td>';
                                        $dispValue = $currencySymbol.number_format($subtots[$fld], 2);
                                        $subtots[$fld] = 0;
                                    } elseif ($cols[$j]->attributes['format'] == 'accounting') {
                                        if ($subtots[$fld] < 0) {
                                            $dispHTML =  '<td class="report_subtotal bad_color" style="text-align: right;">'.$currencySymbol . ' (' .number_format(abs($subtots[$fld]), 2).')</td>';
                                            $dispValue = $currencySymbol . ' (' . number_format(abs($subtots[$fld]), 2) . ')';
                                        } else {
                                            $dispHTML = '<td class="report_subtotal good_color" style="text-align: right;">' . $currencySymbol . ' ' . number_format($subtots[$fld], 2).'</td>';
                                            $dispValue = $currencySymbol . ' ' . number_format($subtots[$fld], 2);
                                        }
                                        $subtots[$fld] = 0;
                                    } elseif ($cols[$j]->attributes['format'] == 'numeric' or (isset($cols[$j]->attributes['align']) and ($cols[$j]->attributes['align'] == 'right' or $cols[$j]->attributes['align'] == 'decimal'))) {
                                        $dispHTML =  '<td class="report_subtotal" style="text-align: right;">'.$subtots[$fld].'</td>';
                                        $dispValue = $subtots[$fld];
                                        $subtots[$fld] = 0;
                                    } else {
                                        $dispHTML =  '<td class="report_subtotal"></td>';
                                        $dispValue = '';
                                    }
                                }
                            }
                            $colArray[] = array('field'=>$fld,'value'=>$dispValue, 'html'=>$dispHTML);
                        }
                        $colArray[] = array('field'=>'subtotalRow','value'=>'','html'=>''); // dummy field used for export routines
                        $this->output[] = array('id' => '', 'attributes' => '', 'cols' => $colArray);

                    }
//                    if ($sectionHeadings) {
//                        // add a section header row
//                        echo '<tr><td colspan="'.$actualColumns.'" class="report_sectionHeading">'.$sectionHeadings['heading'].$row[$sectionHeadings['field']].'</td></tr>';
//                        $subtots['item'] = $row[$sectionHeadings['field']];
//                    }
                    $oldid = $row[$this->subtotals];
                }
            } // End If Subtotals Included
            // If Expander Rows Included
            if (isset($cols[0]->attributes['expander']) and !$this->showAsList) { // note that expander field must be first in array and that field must be the unique id for that row
                if (!is_bool($cols[0]->attributes['expander'])) { // boolean means that the existing row is preserved and following rows are hidden for expander
                    // look ahead to see if next record's id is same, retain the first row and hide following.
                    if ($this->rows[$i+1][$cols[0]->field] == $row[$cols[0]->field]) {
                        // next record is same id  - hide row
                        $hideRow = 'class="expandr' . str_replace(' ', '', $row[$cols[0]->field]) . ' report_expander_row" style="display: none;" ';
                    }
                } else { //if type is string look back and see if it is same. Expander contains the field name of total to be accumulated
                    if ($this->rows[$i-1][$cols[0]->field] == $row[$cols[0]->field]) {
                        // hide current row until id changes, then display a summary row.
                        $hideRow = 'class="expandr' . str_replace(' ', '', $row[$cols[0]->field]) . ' report_expander_row" style="display: none;" ';
                    }
                }
            } // End If Expander Rows Included
            // initialize the colArray
            $colArray = array();
            if ($this->keyField != '') {
                $rowID = $row[$this->keyField];
                $rowAttributes = $rowClasses . $hideRow;
            } else {
                $rowID = '';
                $rowAttributes = $rowClasses . $hideRow;
            }
            $j = 0; // this will count the active columns
            $listParts = array();
            foreach ($cols as $col) {
                $fld = $col->field;
                if ($col->show) {
                    // get the display from the RTCols Class for the current column
                    $col->setRawValue($row[$fld], $j, $row, $this->keyField, $this->allowEditing, $this->editColumn, $this->showAsList);
                    $dispValue = $col->displayValue();
                    $dispHTML = $col->displayHtml();

                    // expander row?
                    if (isset($col->attributes['expander']) and $col->attributes['expander'] and is_bool($col->attributes['expander']) and $hideRow == '') { // first expander row, show button
                        $temp = '<button type="button" class="expandr buttonBar" id="expandr' . str_replace(' ', '', $row[$cols[0]->field]) . '" title="Click to expand or contract additional data.">
                                    <i class="bi-caret-right"></i>
                                </button>';
                        $dispHTML ='<td class="report_cell rt_col'. str_replace(' ', '', $row[$cols[0]->field]);
                        if ($row['hiLiteRow']) $dispHTML .= ' report_sectionHeading';
                        $dispHTML .= '">' . $temp . '</td>';
                        $dispValue = "";
                    } elseif (isset($cols[0]->attributes['expander']) and $col->attributes['expander'] and is_bool($col->attributes['expander']) and $hideRow != '') {
                        // show nothing
                        $dispValue = "";
                        $dispHTML = '<td class="report_cell rt_col'. str_replace(' ', '', $row[$cols[0]->field]).'"></td>';
                    }

                    // subtotal?
                    if ($this->subtotals and ($col->attributes['format'] == 'currency' or $col->attributes['format'] == 'accounting' or $col->attributes['format'] == 'numeric')) {
                        $subtots[$fld] += $row[$fld]; // accumulate the subtotal
                        $grandTots[$fld] += $row[$fld]; // accumulate the grandtotal
                    }
                    //
                    if ($col->field == $this->subtotals) {
                        $subtots['item'] = $dispValue;
                    }

                    // create array for list view format
                    if ($this->showAsList) {
                        if ((isset($col->attributes['lfPosition']) and $col->attributes['lfPosition'] != 'none') or !isset($col->attributes['lfPosition'])) {
                            $listPartsItem = array();
                            if (!isset($col->attributes['lfPosition'])) $col->attributes['lfPosition'] = 'left';
                            // set the heading
                            if (isset($col->attributes['lfHeading'])) {
                                $listPartsItem['heading'] = ($col->attributes['lfHeading'] != '') ? $col->attributes['lfHeading'] . ':' : '';
                            } else {
                                $listPartsItem['heading'] = $col->attributes['heading'] . ':';
                            }
                            // set the content
//                            if (($this->allowEditing and $col->attributes['inlineEdit']) or $col->attributes['allowHtml']) { // the below was not working - 3/27/2020
                            if (($this->allowEditing and $col->attributes['inlineEdit']) or ($col->attributes['type'] == 'yn') or ($col->attributes['type'] == 'b') or $col->attributes['allowHtml']) {
                                $disp = $dispHTML;
                            } else {
                                $disp = $dispValue;
                            }
                            if (isset($col->attributes['lfFields'])) {
                                $disp = ''; // start over
                                // loop the array and construct the content
                                foreach ($col->attributes['lfFields'] as $lfField) {
                                    if ($col->attributes['type'] == 'lookup') {
                                        $temp = '';
                                        if (isset($col->attributes['format']['linkField'])) {
                                            $temp = lookup($col->attributes['format']['db'], $col->attributes['format']['table'], $col->attributes['format']['keyfield'], $row[$col->attributes['format']['linkField']], $lfField);
                                        } else {
//                                            $temp = lookup($col->attributes['format']['db'], $col->attributes['format']['table'], $col->attributes['format']['keyfield'], $row[$fld], $lfField);
                                            $temp = lookup($col->attributes['format'], $row[$fld]);
                                        }
                                        if ($temp != '') {
                                            $disp .= $temp;
                                        } else {
                                            $disp .= $lfField;
                                        }
                                    } else {
                                        if (isset($row[$lfField]) or array_key_exists($lfField, $row)) {
                                            $disp .= $row[$lfField];
                                        } else {
                                            $disp .= $lfField;
                                        }
                                    }
                                }
                            }
                            // special cases
                            if ($col->attributes['lfPosition'] == 'title') {
                                if (substr($col->attributes['type'], 0, 5) == 'link:') {
                                    $disp = '<a name="link' . $row[$fld] . '" class="blocklink doLink" data-fieldname="' . $col->field . '" data-fielddata="' . $row[$fld] . '" data-type="' . substr($col->attributes['type'], 5) . '" title="Click to open this record">' . $listPartsItem['heading'] . ' ' . $disp . '</a>';
                                } elseif (substr($col->type, 0, 9) == 'callback:') {
                                    $disp = '<a onclick="' . substr($col->type, 9) . '(' . $row[$fld] . ',' . $row[$this->keyField] . ')" class="bluelink" title="Click to ' . strtolower($col->attributes['format']) . ' this record">' . $listPartsItem['heading'] . ' ' . $disp . '</a>';
                                }
                            }
                            if ($col->attributes['format'] == 'Login As') {
                                $listPartsItem['heading'] = '';
                                $disp = str_replace('Login As', 'Login As ' . $row['username'], $dispHTML);
                            }
                            if ($col->attributes['imagePreview']) {
                                $disp = '<img src="' . $dispValue . '">';
                            }

                            $listPartsItem['value'] = stripslashes(strip_tags($disp, '<p><a><i><em><b><br><strong><input><select><option><textarea><span><div><img>'));

                            $listParts[$col->attributes['lfPosition']][] = $listPartsItem;

                        }
                    }
                    $j++;
                }
                // Add this column to the array
                $colArray[] = array('field'=>$fld,'value'=>$dispValue, 'html'=>$dispHTML);
            }
            // handle some special cases
            if (isset($row['hiLiteRow'])) {
                $colArray[] = array('field'=>'hiLiteRow','value'=>'','html'=>'');
            }
            if (isset($row['subtotalRow'])) {
                $colArray[] = array('field'=>'subtotalRow','value'=>'','html'=>'');
            }
            // add the above row to the array
            $this->output[] = array('id' => $rowID, 'attributes' => $rowAttributes, 'cols' => $colArray, 'listParts'=>$listParts);

            // EXPANDER ROWS
            if (isset($cols[0]->attributes['expander']) and $cols[0]->attributes['expander'] and !$this->showAsList) { // note that expander field must be first in array
                // look ahead to see if next record's id is same
                if ($this->rows[$i + 1][$cols[0]->field] == $row[$cols[0]->field]) {
                    // next record is same id  - accumulate value if field type is integer
                    if (!is_bool($cols[0]->attributes['expander'])) {
                        $subtots['expander'] += $row[$cols[0]->attributes['expander']];
                        $subtots['record'] = $row;
                    }
                } else { // next record is a different id
                    if ($hideRow != '') {
                        if (!is_bool($cols[0]->attributes['expander'])) {
                            $subtots['expander'] += $row[$cols[0]->attributes['expander']]; // accumulate current row
                            // create a subtotal row with an expander button
                            $colArray = array();
                            $dispHTML = '';
                            foreach ($cols as $col) {
                                $fld = $col->field;
                                if ($col->show) {
                                    if ($col->attributes['expander']) {
                                        $dispHTML = '<td class="report_subtotal" style="text-align: center;">
                                                    <button type="button" class="expandr buttonBar" id="expandr' . str_replace(' ', '', $row[$cols[0]->field]) . '" title="Click to expand or contract additional data.">
                                                        <i class="bi-caret-right"></i>
                                                    </button>
                                                </td>';
                                        $dispValue = '';
                                    } else {
                                        if ($col->field == $cols[0]->attributes['expander']) {
                                            $dispHTML = '<td class="report_subtotal" style="text-align: right;">$' . number_format($subtots['expander'], 2) . '</td>';
                                            $dispValue = $currencySymbol . number_format($subtots['expander'], 2);
                                        } elseif ($col->attributes['format'] == 'numeric') {
                                            $dispHTML = '<td class="report_subtotal" style="text-align: right;">' . $subtots['record'][$col->field] . '</td>';
                                            $dispValue = $subtots['record'][$col->field];
                                            $subtots[$fld] = 0;
                                        } elseif ($col->type == 'd' or $col->type == 'dt') {
                                            // date type - use format if present
                                            $dispHTML = '<td  class="report_subtotal">' . niceDate($subtots['record'][$col->field], true, $col->attributes['format']) . '</td>';
                                            $dispValue = niceDate($subtots['record'][$col->field], true, $col->attributes['format']);
                                        } else {
                                            if (!$col->attributes['expanderHide']) {
                                                $dispHTML = '<td class="report_subtotal">' . $subtots['record'][$col->field] . '</td>';
                                                $dispValue = $subtots['record'][$col->field];
                                            } else {
                                                $dispHTML = '<td class="report_subtotal"></td>';
                                                $dispValue = '';
                                            }
                                        }
                                    }
                                }
                                $colArray[] = array('field'=>$fld,'value'=>$dispValue, 'html'=>$dispHTML);
                            }
                            $colArray[] = array('field'=>'subtotalRow','value'=>'','html'=>'');
                            $this->output[] = array('id' => '', 'attributes' => '', 'cols' => $colArray);
                        } else {
                            // expander is first row preserve - don't show totals
                        }
                        $row[$cols[0]->attributes['expander']] = $subtots['expander'];
                        $subtots['expander'] = 0;
                        $subtots['record'] = array();
                    }
                    $hideRow = '';
                }
            }
            $i++;
        }
        if ($this->subtotals and $this->rows and !$this->showAsList) {
            // show a final subtotal line
            $colArray = array();
            $dispHTML = '';
            for ($j=0; $j< count($cols); $j++) {
                $fld = $cols[$j]->field;
                if ($cols[$j]->field == $this->subtotals) {
                    $dispHTML = '<td class="report_subtotal">Total for '. $subtots['item'].'</td>';
                    $dispValue = 'Total for '.$subtots['item'];
                    if (!$cols[$j]->show) {
                        $j++;
                    }
                } else {
                    if ($cols[$j]->show) {
                        if (isset($cols[$j]->attributes['currencyField'])) { // points to a field in the table that contains the currency code
                            if ($row[$cols[$j]->attributes['currencyField']] == 'USD' or $cols[$j]->attributes['currencyField'] == 'USD') {
                                $curr = '$';
                            } elseif ($row[$cols[$j]->attributes['currencyField']] == 'EURO' or $cols[$j]->attributes['currencyField'] == 'EURO') {
                                $curr = '€';
                            } elseif ($row[$cols[$j]->attributes['currencyField']] == 'GBP' or $cols[$j]->attributes['currencyField'] == 'GBP') {
                                $curr = '£';
                            }
                        } else {
                            $curr = $currencySymbol;
                        }
                        if ($cols[$j]->attributes['format'] == 'currency') {
                            $dispHTML = '<td class="report_subtotal" style="text-align: right;">' . $currencySymbol . number_format($subtots[$fld], 2) . '</td>';
                            $dispValue = $currencySymbol . number_format($subtots[$fld], 2);
                            $subtots[$fld] = 0;
                        } elseif ($cols[$j]->attributes['format'] == 'accounting') {
                            if ($subtots[$fld] < 0) {
                                $dispHTML =  '<td class="report_subtotal bad_color" style="text-align: right;">'.$currencySymbol . ' (' .number_format(abs($subtots[$fld]), 2).')</td>';
                                $dispValue = $currencySymbol . ' (' . number_format(abs($subtots[$fld]), 2) . ')';
                            } else {
                                $dispHTML = '<td class="report_subtotal good_color" style="text-align: right;">' . $curr . ' ' . number_format($subtots[$fld], 2).'</td>';
                                $dispValue = $currencySymbol . ' ' . number_format($subtots[$fld], 2);
                            }
                            $subtots[$fld] = 0;
                        } elseif ($cols[$j]->attributes['format'] == 'numeric' or (isset($cols[$j]->attributes['align']) and ($cols[$j]->attributes['align'] == 'right' or $cols[$j]->attributes['align'] == 'decimal'))) {
                            $dispHTML =  '<td class="report_subtotal" style="text-align: right;">'.$subtots[$fld].'</td>';
                            $dispValue = $subtots[$fld];
                            $subtots[$fld] = 0;
                        } else {
                            $dispHTML =  '<td class="report_subtotal"></td>';
                            $dispValue = '';
                        }
                    }
                }
                $colArray[] = array('field'=>$fld,'value'=>$dispValue, 'html'=>$dispHTML);
            }
            $colArray[] = array('field'=>'subtotalRow','value'=>'','html'=>''); // dummy field used for export routines
            $this->output[] = array('id' => '', 'attributes' => '', 'cols' => $colArray);
        }
        if ($this->showGrandTotal and $this->subtotals and !$this->showAsList and count($this->output) > 0) {
            // show a grand total line
            $colArray = array();
            $dispHTML = '';
            for ($j=0; $j< count($cols); $j++) {
                $fld = $cols[$j]->field;
                if ($cols[$j]->field == $this->subtotals) {
                    $dispHTML = '<td class="report_subtotal">Grand Total</td>';
                    $dispValue = 'Grand Total';
                    if (!$cols[$j]->show) {
                        $j++;
                    }
                } else {
                    if ($cols[$j]->show) {
                        if ($cols[$j]->attributes['format'] == 'currency') {
                            $dispHTML = '<td class="report_subtotal" style="text-align: right;">'.$currencySymbol.number_format($grandTots[$fld], 2).'</td>';
                            $dispValue = $currencySymbol.number_format($grandTots[$fld], 2);
                        } elseif ($cols[$j]->attributes['format'] == 'accounting') {
                            if ($grandTots[$fld] < 0) {
                                $dispHTML =  '<td class="report_subtotal bad_color" style="text-align: right;">'.$currencySymbol . ' (' .number_format(abs($grandTots[$fld]), 2).')</td>';
                                $dispValue = $currencySymbol . ' (' . number_format(abs($grandTots[$fld]), 2) . ')';
                            } else {
                                $dispHTML = '<td class="report_subtotal good_color" style="text-align: right;">' . $curr . ' ' . number_format($grandTots[$fld], 2).'</td>';
                                $dispValue = $currencySymbol . ' ' . number_format($grandTots[$fld], 2);
                            }
                        } elseif ($cols[$j]->attributes['format'] == 'numeric') {
                            $dispHTML = '<td class="report_subtotal" style="text-align: right;">'.$grandTots[$fld].'</td>';
                            $dispValue = $grandTots[$fld];
                        } else {
                            $dispHTML = '<td class="report_subtotal"></td>';
                            $dispValue = '';
                        }
                    }
                }
                $colArray[] = array('field'=>$fld, 'value'=>$dispValue, 'html'=>$dispHTML);
            }
            $colArray[] = array('field'=>'subtotalRow','value'=>'','html'=>''); // dummy field used for export routines
            $this->output[] = array('id' => '', 'attributes' => '', 'cols' => $colArray);
        }
    }

    /**
     * outputTable
     * Display the actuol rows of the table
     */
    public function outputTable()
    {
        if (count($this->output) == 0) $this->processTable();

        $cols = sortArray($this->columns, 'order', false, true);
        $oldGroup = '';
        $extraPadding = '';
        $countRows = 0;
        foreach ($this->output as $row) {
            // If view as listing
            if ($this->showAsList) {
                // show the listParts array
                if (isset($row['listParts']['group'])) {
                    $extraPadding = 'style="padding-left: 48px;"';
                    if ($row['listParts']['group'][0]['value'] != $oldGroup) {
                        // show the group name
                        ?>
                        <tr>
                            <td class="rt_list_td rt_list_title">
                                <?=($row['listParts']['group'][0]['heading'] != '')?$row['listParts']['group'][0]['heading'].' ':'';?>
                                <?=$row['listParts']['group'][0]['value']?>
                            </td>
                        </tr>
                        <?php
                        $oldGroup = $row['listParts']['group'][0]['value'];
                    }
                }
                ?>
                <tr>
                    <td class="rt_list_td" <?=$extraPadding?>>
                        <?php if ($row['listParts']['title'][0]['value'] != '') { ?>
                        <div class="rt_list_title"><?=$row['listParts']['title'][0]['value']?></div>
                        <?php } ?>
                        <div class="rt_list_contents">
                            <div class="rt_list_left">
                                <?php
                                if (isset($row['listParts']['left'])) {
                                    foreach ($row['listParts']['left'] as $item) {
                                        if ($item['heading'] == '') {
                                            // no heading - left align item
                                            echo '<div class="form_rows"><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        } else {
                                            echo '<div class="form_rows"><div class="form_label">' . $item['heading'] . '</div><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <div class="rt_list_center">
                                <?php
                                if (isset($row['listParts']['center'])) {
                                    foreach ($row['listParts']['center'] as $item) {
                                        if ($item['heading'] == '') {
                                            // no heading - left align item
                                            echo '<div class="form_rows"><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        } else {
                                            echo '<div class="form_rows"><div class="form_label">' . $item['heading'] . '</div><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <div class="rt_list_right">
                                <?php
                                if (isset($row['listParts']['right'])) {
                                    foreach ($row['listParts']['right'] as $item) {
                                        if ($item['heading'] == '') {
                                            // no heading - left align item
                                            echo '<div class="form_rows"><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        } else {
                                            echo '<div class="form_rows"><div class="form_label">' . $item['heading'] . '</div><div class="form_cell"> ' . $item['value'] . '</div></div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php if (isset($row['listParts']['footer']) and $row['listParts']['footer'][0]['value'] != '') { ?>
                        <div class="rt_list_footer"><?=$row['listParts']['footer'][0]['value']?></div>
                        <?php } ?>
                    </td>
                </tr>
                <?php
            } else { // If view as table
                echo '<tr ';
                if ($row['id'] !== '') {
                    echo 'id="row_' . $row['id'].'" ';
                }
                echo $row['attributes'].'>';
                for ($i=0; $i< count($cols); $i++) {
                    if ($cols[$i]->show) {
                        echo $row['cols'][$i]['html'];
                    }
                }
                echo '</tr>';
            }
            $countRows++;
        }
        if ($countRows == 0 and $this->emptyMessage != '') {
            if ($this->showAsList) {
                echo '<tr class="rtEmptyMessage"><td class="rt_list_td">'.$this->emptyMessage.'</td></tr>';
            } else {
                echo '<tr class="rtEmptyMessage"><td colspan="'.count($cols).'" style="padding: 12px;">'.$this->emptyMessage.'</td></tr>';
            }
        }
    }

    /**
     * outputJavascript
     * Returns the supporting javascript for the table instance
     */
    public function outputJavascript()
    {
        // note: these functions are name-spaced as app{identifier} so all variables are local
        // only allow column resizing if not showing as list
        if (!$this->noColResize and !$this->showAsList) echo '<script src="'.PLMSite.'js/colResizable-1.6-LEE.js"></script>';
        ?>

        <script>

            var rt<?=$this->getIdentifier()?> = new rtShared('<?=$this->getIdentifier()?>', {
                sort1:          "<?=gls_esc_js($this->qryOrderBy[0])?>",
                sort2:          "<?=gls_esc_js($this->qryOrderBy[1])?>",
                sort3:          "<?=gls_esc_js($this->qryOrderBy[2])?>",
                limit:          <?=gls_esc_js($this->limit)?>,
                offset:         <?=gls_esc_js($this->offset)?>,
                page:           <?=gls_esc_js($this->page)?>,
                totPages:       <?=gls_esc_js($this->totPages)?>,
                filter:         "All",
                showAsList:     <?=($this->showAsList)?'true':'false'?>,
                noColResize:    <?=($this->noColResize)?'true':'false'?>,
                ajaxHandler:    "<?=gls_esc_js($this->ajaxHandler)?>",
                refreshCallback:"<?=gls_esc_js($this->refreshCallback)?>",
                flushColWidths: <?=($this->flushColWidths)?'true':'false'?>,
                colClass:       "<?=gls_esc_js($this->colClass)?>",
                linkField:      "<?=gls_esc_js($this->linkField)?>",
                linkID:         "<?=gls_esc_js($this->linkID)?>",
                hasProfileMode: <?=($this->hasProfileMode)?'true':'false'?>,
                disabled:       false
            });


        </script>
        <?php
    }

    /**
     * outputPagination
     * returns the html for pagination control
     * @param string $position
     */
    function outputPagination(string $position = 'top')
    {

        if ($position == 'top') {
            ?>
            <div class="pagination_top">
                <button class="buttonBar buttonBar<?=$this->identifier?> leftBtns<?=$this->identifier?>" id="firsttop<?=$this->identifier?>" title="First Page">
                    <i class="bi-chevron-bar-left smaller_icon"></i>
                </button>
                <button class="buttonBar buttonBar<?=$this->identifier?> leftBtns<?=$this->identifier?>" id="prevtop<?=$this->identifier?>" title="Previous Page">
                    <i class="bi-chevron-left smaller_icon"></i>
                </button>
                <span id="page_count_top<?=$this->identifier?>">
                        <?php echo ' Page ' . gls_esc_html($this->page) . ' of ' . gls_esc_html($this->totPages) . ' '; ?></span>
                <button class="buttonBar buttonBar<?=$this->identifier?> rightBtns<?=$this->identifier?>" id="nexttop<?=$this->identifier?>" title="Next Page">
                    <i class="bi-chevron-right smaller_icon"></i>
                </button>
                <button class="buttonBar buttonBar<?=$this->identifier?> rightBtns<?=$this->identifier?>" id="lasttop<?=$this->identifier?>" title="Last Page">
                    <i class="bi-chevron-bar-right smaller_icon"></i>
                </button>
            </div>
            <?php
        } else {
            ?>
            <div class="report_pagination pagination_bottom">
                <button class="buttonBar buttonBar<?=$this->identifier?> leftBtns<?=$this->identifier?>" id="firstbottom<?=$this->identifier?>" title="First Page">
                    <i class="bi-chevron-bar-left smaller_icon"></i>
                </button>
                <button class="buttonBar buttonBar<?=$this->identifier?> leftBtns<?=$this->identifier?>" id="prevbottom<?=$this->identifier?>" title="Previous Page">
                    <i class="bi-chevron-left smaller_icon"></i>
                </button>
                <span id="page_count_bottom<?=$this->identifier?>">
                        <?php echo ' Page ' . gls_esc_html($this->page) . ' of ' . gls_esc_html($this->totPages) . ' '; ?></span>
                <button class="buttonBar buttonBar<?=$this->identifier?> rightBtns<?=$this->identifier?>" id="nextbottom<?=$this->identifier?>" title="Next Page">
                    <i class="bi-chevron-right smaller_icon"></i>
                </button>
                <button class="buttonBar buttonBar<?=$this->identifier?> rightBtns<?=$this->identifier?>" id="lastbottom<?=$this->identifier?>" title="Last Page">
                    <i class="bi-chevron-bar-right smaller_icon"></i>
                </button>
            </div>
            <?php
        }
    }

    /**
     * outputSortFields
     * returns the html for showing the current sort order
     * added sort order dialog 2/2021
     */
    function outputSortFields()
    {
        $rowNames = array('Primary','Subsort 1','Subsort 2');
        ?>
        <div id="sortDisplay">
            <div class="sortDisplayFlex">
                <div id="sortFieldDisplay<?=$this->getIdentifier()?>">Sorted by: <?=gls_esc_html($this->getSortFieldHeadings())?></div>&nbsp;
                <button type="button" class="buttonBar" id="openCloseSortSelect<?=$this->getIdentifier()?>" title="Change the sorting order of results.">
                    <i class="bi-sort-down-alt"></i>
                </button>
            </div>
            <div class="rtSortSelect" id="rtSortSelect<?=$this->getIdentifier()?>">
                <?php for ($i = 0; $i < 3; $i++) {
                    $field = substr($this->qryOrderBy[$i],0,strpos($this->qryOrderBy[$i],' '));
                    $dir = trim(substr($this->qryOrderBy[$i], -4));
                    ?>
                <div class="rtSortRows">
                    <span class="ar_search_label"><?=$rowNames[$i]?>:</span>
                    <div>
                        <select name="sortBy<?=$this->getIdentifier() . ($i+1)?>" id="sortBy<?=$this->getIdentifier() . ($i+1)?>" >
                            <?php
                        if ($i > 0) {
                            echo '<option value="" selected >No Sort</option>';
                        }
                        foreach ($this->columns as $col) {
                            if ($col->sort) {
                                ?>
                            <option value="<?=$col->field?>" <?=($col->field == $field)?'selected':''?> ><?=$col->heading?></option>
                                <?php
                            }
                        }
                        ?>
                        </select>
                    </div>
                    <div>
                        <select name="sortDir<?=$this->getIdentifier() . ($i+1)?>" id="sortDir<?=$this->getIdentifier() . ($i+1)?>">
                            <option value="ASC"  <?=($dir == ' ASC')?'selected':''?> title="Sort Ascending">ASC</option>
                            <option value="DESC" <?=($dir == 'DESC')?'selected':''?> title="Sort Descending">DESC</option>
                        </select>
                    </div>
                </div>
                <?php } ?>
                <div class="rtSortSelectButtons">
                    <button type="button" class="buttonBar altBackground" style="font-size: 12px; margin-right: 16px;" id="rtRestoreDefaults" >
                        <i class="bi bi-arrow-counterclockwise smaller_icon"></i>
                        &nbsp;Restore Defaults
                    </button>
                    <button type="button" class="buttonBar altBackground" name="sortBtn<?=$this->getIdentifier()?>" id="sortBtn<?=$this->getIdentifier()?>" style="width: 60px;" >
                        <i class="bi bi-arrow-return-right smaller_icon"></i>
                        &nbsp;Sort
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * outputLimitSelector
     * returns the html for showing a selector for the number of records per page
     * @param $allowAll
     */
    function outputLimitSelector($allowAll)
    {
        ?>
        <div class="outputLimitSelector">
            Show
            <select name="limit" id="limitSelect<?=$this->identifier?>">
                <option value="5" <?php if ($this->limit > 0 and $this->limit <= 5) echo ' selected="selected"'; ?>>5
                </option>
                <option value="10" <?php if ($this->limit > 5 and $this->limit <= 10) echo ' selected="selected"'; ?>>
                    10
                </option>
                <option value="25" <?php if ($this->limit > 10 and $this->limit <= 25) echo ' selected="selected"'; ?>>
                    25
                </option>
                <option value="50" <?php if ($this->limit > 25 and $this->limit <= 50) echo ' selected="selected"'; ?>>
                    50
                </option>
                <option value="100" <?php if ($this->limit > 50) echo ' selected="selected"'; ?>>100</option>
                <?php if ($allowAll) { ?>
                    <option value="-1" <?php if ($this->limit == -1) echo ' selected="selected"'; ?>>- All -
                    </option>
                <?php } ?>
            </select>
            results<span id="perPage<?=$this->identifier?>"> per page</span>.&nbsp;
        </div>
        <?php
    }

    /**
     * outputFilterSelector
     * returns the html to show a dropdown to filter the selection
     *
     * @param string $label
     * @param array $rows
     * @param string $idField
     * @param string $dispField
     * @param $default
     */
    function outputFilterSelector(string $label, array $rows, string $idField, string $dispField, $default)
    {
        ?>
        <div>
            Filter by <?=gls_esc_html($label)?>&nbsp;
            <select name="filter" id="filterSelect<?=$this->identifier?>">
                <option value="All">All</option>
                <?php foreach ($rows as $row) { ?>
                <option value="<?=$row[$idField]?>" <?php if ($row[$idField] == $default) echo ' selected="selected"'; ?>><?=$row[$dispField]?></option>
                <?php } ?>
            </select>
        </div>
        <?php
    }

    /**
     * outputRecordsFound
     * returns the html to show the total records found
     *
     * @param string $label
     * @param int $value
     */
    function outputRecordsFound(string $label, int $value)
    {
        ?>
        <div class="outputRecordsFound">
            <strong><span id="countRows<?=$this->identifier?>"><?=number_format($value,0,'.',',')?></span> <?=$label?> found</strong>
        </div>
        <?php
    }

    /**
     * @param string $qrySelect
     */
    public function setQrySelect(string $qrySelect)
    {
        $this->qrySelect = $qrySelect;
    }

    /**
     * @param array $qryWhere
     */
    public function setQryWhere(array $qryWhere)
    {
        $this->qryWhere = $qryWhere;
    }

    /**
     * @param string $qryGroupBy
     */
    public function setQryGroupBy(string $qryGroupBy)
    {
        $this->qryGroupBy = $qryGroupBy;
    }

    /**
     * @param string|array $sort1
     * @param string|null $sort2
     * @param string|null $sort3
     */
    public function setQryOrderBy($sort1, ?string $sort2 = '', ?string $sort3 = '')
    {
        // column sorting routines
        if (is_array($sort1)) {
            $this->qryOrderBy = $sort1;
        } else { // if not array, limit to 3 levels of sorting
            // remove empty sort fields
            $this->qryOrderBy = array();
            if ($sort1 != '') {
                $this->qryOrderBy[] = $sort1;
            }
            if ($sort2 != '') {
                $this->qryOrderBy[] = $sort2;
            }
            if ($sort3 != '') {
                $this->qryOrderBy[] = $sort3;
            }
        }
    }

    /**
     * @param string $pq_bindtypes
     */
    public function setPqBindtypes(string $pq_bindtypes)
    {
        $this->pq_bindtypes = $pq_bindtypes;
    }

    /**
     * @param array|null $pq_bindvalues
     */
    public function setPqBindvalues(?array $pq_bindvalues)
    {
        $this->pq_bindvalues = $pq_bindvalues;
    }

    /**
     * @return string
     */
    public function getPqBindtypes():string
    {
        return $this->pq_bindtypes;
    }

    /**
     * @return array
     */
    public function getPqBindvalues():array
    {
        return $this->pq_bindvalues;
    }

    /**
     * This function combines the above 6 setters into one call
     *
     * @param string $qrySelect
     * @param array $qryWhere
     * @param string $qryGroupBy
     * @param string|null $sort1
     * @param string|null $sort2
     * @param string|null $sort3
     * @param string $pq_bindtypes
     * @param array|null $pq_bindvalues
     */
    public function setQueryAll(string $qrySelect, array $qryWhere, string $qryGroupBy, ?string $sort1, ?string $sort2 = '', ?string $sort3 = '', string $pq_bindtypes = '', ?array $pq_bindvalues = array())
    {
        $this->qrySelect = $qrySelect;
        $this->qryWhere = $qryWhere;
        if ($qryGroupBy != '') $this->qryGroupBy = $qryGroupBy; // in case previously set
        $this->setQryOrderBy($sort1, $sort2, $sort3);
        if ($pq_bindtypes != '') $this->pq_bindtypes = $pq_bindtypes;
        if ($pq_bindvalues != array()) $this->pq_bindvalues = $pq_bindvalues;
    }

    /**
     * @return string
     */
    public function getQrySelect():string
    {
        return $this->qrySelect;
    }

    /**
     * @return array
     */
    public function getQryOrderBy():array
    {
        return $this->qryOrderBy;
    }

    /**
     * getSortFieldHeadings
     * returns formatted names to represent sort order
     *
     * @return false|string
     */
    public function getSortFieldHeadings()
    {
        $output = '';
        foreach ($this->qryOrderBy as $sortField) {
            if (strpos($sortField,' ASC') or strpos($sortField, ' DESC')) {
                $fieldName = substr($sortField, 0, strpos($sortField, ' ')); // strip off ' ASC' or ' DESC'
                $direction = substr($sortField, strpos($sortField, ' '));
            } else {
                $fieldName = $sortField;
                $direction = ' ASC';
            }
            $foundIt = false;
            foreach ($this->columns as $col) {
                if (strpos(' '.$fieldName, $col->field) > 0) {
                    if (substr($col->type, 0, 5) == 'link:' or $col->heading == '') { // for links, show fieldname instead of heading - 11/27/2017
                        $output .= $fieldName;
                    } else {
                        $output .= $col->heading;
                    }
                    $foundIt = true;
                    break;
                }
            }
            if (!$foundIt) { // if not found, use fieldname
                $output .= $fieldName;
            }
            $output .= $direction . ', ';
        }
        $output = substr($output, 0, strlen($output)-2);
        return $output;
    }

    /**
     * hideShowColumn
     * Usually called after retrieving user preferences, this sets the visibility of the column
     *
     * @param int $i
     * @param bool $state
     */
    public function hideShowColumn(int $i, bool $state)
    {
        $this->columns[$i]->show = $state;
    }

    /**
     * setColumnOrder
     * Usually called after retrieving user preferences, this sets the display order of the column
     *
     * @param int $i
     * @param int $ord
     */
    public function setColumnOrder(int $i, int $ord)
    {
        $this->columns[$i]->order = $ord;
    }

    /**
     * setColumnWidth
     * Usually called after retrieving user preferences, this sets the width of the column
     *
     * @param int $i
     * @param string|null $width
     */
    public function setColumnWidth(int $i, ?string $width)
    {
        $this->columns[$i]->attributes['width'] = is_null($width)?'1':$width;
    }

    /**
     * @param string $keyField
     */
    public function setKeyField(string $keyField)
    {
        $this->keyField = $keyField;
    }

    /**
     * @param bool $allowEditing
     */
    public function setAllowEditing(bool $allowEditing)
    {
        $this->allowEditing = $allowEditing;
    }

    /**
     * @param bool $subtotalBySort
     */
    public function setSubtotalBySort(bool $subtotalBySort)
    {
        $this->subtotalBySort = $subtotalBySort;
        if ($this->subtotalBySort) {
            $this->subtotals = substr($this->qryOrderBy[0], 0, strpos($this->qryOrderBy[0], ' ')); // enable subtotals based on primary sort field
        }
    }

    /**
     * @param bool $showGrandTotal
     */
    public function setShowGrandTotal(bool $showGrandTotal)
    {
        $this->showGrandTotal = $showGrandTotal;
    }

    /**
     * getColumns
     * return the columns as an array
     * @return array
     */
    public function getColumns():array
    {
        $output = array(); // convert to old style array
        foreach ($this->columns as $col) {
            $output[] = $col->attributes;
        }
        return $output;
    }

    /**
     * @return array
     */
    public function getRows():array
    {
        return $this->rows;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;
    }

    /**
     * @param int $offset
     */
    public function setOffset(int $offset)
    {
        $this->offset = $offset;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page)
    {
        $this->page = $page;
    }

    /**
     * @return int
     */
    public function getCountRows():int
    {
        return $this->countRows;
    }

    /**
     * @return int
     */
    public function getLimit():int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOffset():int
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getPage():int
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function getTotPages():int
    {
        return $this->totPages;
    }

    /**
     * @param string $table
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }

    /**
     * @return string
     */
    public function getIdentifier():string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * @param mixed $ajaxHandler
     */
    public function setAjaxHandler($ajaxHandler)
    {
        $this->ajaxHandler = $ajaxHandler;
    }

    /**
     * @param string $linkField
     */
    public function setLinkField(string $linkField)
    {
        $this->linkField = $linkField;
    }

    /**
     * @param $linkID
     */
    public function setLinkID($linkID)
    {
        $this->linkID = $linkID;
    }

    /**
     * @param string $rowClass
     */
    public function setRowClass(string $rowClass)
    {
        $this->rowClass = $rowClass;
    }

    /**
     * @param string $colClass
     */
    public function setColClass(string $colClass)
    {
        $this->colClass = $colClass;
    }

    /**
     * @param string $header_color
     */
    public function setHeaderColor(string $header_color)
    {
        $this->header_color = $header_color;
    }

    /**
     * @param int $countRows
     */
    public function setCountRows(int $countRows)
    {
        $this->countRows = $countRows;
    }

    /**
     * @param int $totPages
     */
    public function setTotPages(int $totPages)
    {
        $this->totPages = $totPages;
    }

    /**
     * @param string $qryFunction
     */
    public function setQryFunction(string $qryFunction)
    {
        $this->qryFunction = $qryFunction;
    }

    /**
     * @param string $refreshCallback
     */
    public function setRefreshCallback(string $refreshCallback)
    {
        $this->refreshCallback = $refreshCallback;
    }

    /**
     * @return string
     */
    public function getEditColumn():string
    {
        return $this->editColumn;
    }

    /**
     * @param string $editColumn
     */
    public function setEditColumn(string $editColumn)
    {
        $this->editColumn = $editColumn;
    }

    /**
     * @return bool
     */
    public function isShowAsList():bool
    {
        return $this->showAsList;
    }

    /**
     * @param bool $showAsList
     */
    public function setShowAsList(bool $showAsList)
    {
        $this->showAsList = $showAsList;
    }

    /**
     * getOutput
     * returns the processed output in rows and columns like a database
     * @return array
     */
    public function getOutput():array
    {
        // reformat output into rows and columns
        $rows = array();
        foreach ($this->output as $row) {
            $clms = array();
            foreach ($row['cols'] as $col) {
                $fld = $col['field'];
                $clms[$fld] = $col['value'];
            }
            $rows[] = $clms;
        }
        return $rows;
    }

    /**
     * @param bool $noColResize
     */
    public function setNoColResize(bool $noColResize)
    {
        $this->noColResize = $noColResize;
    }

    /**
     * @param bool $flushColWidths
     */
    public function setFlushColWidths(bool $flushColWidths)
    {
        $this->flushColWidths = $flushColWidths;
    }

    /**
     * @param array $rows
     */
    public function setRows(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @param string $emptyMessage
     */
    public function setEmptyMessage(string $emptyMessage)
    {
        $this->emptyMessage = $emptyMessage;
    }

    /**
     * findColumn
     * Search the columns array for the desired column
     * @param $origOrderNumber
     * @param $field
     * @return mixed - a reference to the RTCols object
     */
    public function findColumn(int $origOrderNumber, string $field)
    {
        for ($i=0; $i < count($this->columns); $i++) {
            if ($this->columns[$i]->origOrder == $origOrderNumber and $this->columns[$i]->field == $field) {
                return $this->columns[$i];
            }
        }
        return $this->columns[$origOrderNumber]; // a fallback
    }

    /**
     * @param bool $hasProfileMode
     */
    public function setHasProfileMode(bool $hasProfileMode)
    {
        $this->hasProfileMode = $hasProfileMode;
    }

}
