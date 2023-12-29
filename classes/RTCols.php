<?php
/**
 * Class RTCols
 *
 * Support for ReportTable Class
 * Represents columns in a report or fields in an edit page
 * Lee updated all escaping to use gls_esc_xxx functions 8/19/2020
 */

class RTCols
{
    public string $field = "";
    public string $heading = "";
    public string $type = "s";
    public bool $search = false;
    public bool $edit = false;
    public bool $sort = false;
    public bool $show = true;
    private string $rawValue = "";
    private string $value = "";
    private string $html = "";
    public int $order = 0; // the current order of the columns
    public int $origOrder = 0; // remember the original order of the cols
    public array $attributes = array();

    /**
     * RTCols constructor.
     *
     * @param string $field
     * @param string $heading
     * @param string $type
     * @param array $attributes
     */
    function __construct(string $field, string $heading, string $type = "s", array $attributes = array())
    {
        $this->field = $field;
        $this->heading = $heading;
        $this->type = $type;
        // process the attributes array
        $this->attributes = $attributes;
        if (isset($this->attributes['search'])) $this->search = $this->attributes['search'];
        if (isset($this->attributes['edit'])) $this->edit = $this->attributes['edit'];
        if (isset($this->attributes['sort'])) $this->sort = $this->attributes['sort'];
        if (isset($this->attributes['show'])) $this->show = $this->attributes['show'];
        if (isset($this->attributes['order'])) $this->order = $this->attributes['order'];
        if (isset($this->attributes['origOrder'])) $this->origOrder = $this->attributes['origOrder'];
        // depending on type, do special processing

    }

    /**
     * displayValue
     * This will return the processed cell contents without any html
     * @return string
     */
    public function displayValue():string
    {
        return $this->value;
    }

    /**
     * displayHtml
     * This will return the processed cell contents with html tags (such as <td></td>)
     * @return string
     */
    public function displayHtml():string
    {
        return $this->html;
    }

    /**
     * displayHeading
     * Process and return the column heading for this column
     *
     * @param string|null $orderBy0
     * @param bool $allowEditing
     * @param int $colNum
     * @return string
     */
    public function displayHeading(?string $orderBy0, bool $allowEditing, int $colNum):string
    {

        // output html for heading cell <th> with formatting
        $output = '<th class="report_cell';
        if ($this->sort) {
            $output .= ' ColSort';
        }
        if (isset($this->attributes['sortField'])) {
            $sortField = $this->attributes['sortField'];
        } else {
            $sortField = $this->field;
        }
        $output .= '" data-fieldname="' . $sortField . '" style="';
        if (isset($this->attributes['width'])) {
            if (substr($this->type, 0, 5) == 'link:' and $this->attributes['format'] != '' and $this->attributes['width'] == '.5') { // for addition of icons with link:'s 10/5/22
                $this->attributes['width'] = '.6';
            }
            $output .= 'width: ' . (round(($this->attributes['width'] * 96), 0)  + 11) . 'px; ';
        }
        if (isset($this->attributes['max-width'])) {
            $output .= 'max-width: ' . $this->attributes['max-width'] . 'in; ';
        }
        if ($this->attributes['format'] == 'currency' or $this->attributes['format'] == 'accounting' or $this->attributes['format'] == 'numeric' or $this->attributes['format'] == 'percent' or (isset($this->attributes['align']) and ($this->attributes['align'] == 'right' or $this->attributes['align'] == 'decimal'))) {
            $output .= 'text-align: right;';
        } elseif ($this->type == 'b' or substr($this->type, 0, 5) == 'link:' or substr($this->type, 0, 9) == 'callback:' or $this->attributes['format'] == 'center' or (isset($this->attributes['align']) and $this->attributes['align'] == 'center')){
            $output .= 'text-align: center;';
        }
        $output .= '"';
        if ($this->sort) {
            $output .= ' title="Click to sort by this column"';
        }
        $output .= '>';
        if ($this->type == 'c'){
            // checkbox - if click the heading toggle between select all or select none
            $output .= '<a id="SelectAllCB" title="Click to select all or none"><label for="cbheading">' . $this->heading . '</label>' ;
            $output .= '<input type="checkbox" id="cbheading" readonly="readonly" /></a>';
        } else {
            $output .= $this->heading;
        }
        if ($allowEditing and $this->attributes['inlineEdit'] == true) {
            $output .= '<button type="button" class="editColumn buttonBar" id="editCol'.$colNum.'" title="Edit this column">
                            <i class="bi-pencil" style="font-size: 1em;"></i>
                        </button>';
        }
        if ($this->sort) { // initial sort order indicators
            if (strpos(' '.$orderBy0, $sortField) > 0) {
                if (substr($orderBy0, -5) != ' DESC') {
                    $output .= '<span class="sortArrows" title="Sorted ascending"><i class="bi-caret-up" style="font-size: 1em;"></i></span>';
                } else {
                    $output .= '<span class="sortArrows" title="Sorted descending"><i class="bi-caret-down" style="font-size: 1em;"></i></span>';
                }
            }
        }
        $output .= '</th>';
        return $output;
    }

    /**
     * displaySearchCriteria
     * returns formatted label and input field for search criteria
     *
     * @param int $searchHeadingWidth
     * @param string $operator
     * @param string $value1
     * @param string $value2
     */
    public function displaySearchCriteria(int $searchHeadingWidth, string $operator, string $value1 = "", string $value2 = "")
    {
        global $db;
        // output html for search criteria display
        if (isset($this->attributes['search']) and !isset($this->attributes['forceShow'])) {
            if (in_array($this->type, array('s', 'i', 'b', 't', 'yn', 'tf', 'd', 'dt', '=', 'bytes', 'lookup'))) { // exclude links, functions, callbacks, and lookups

                if (isset($this->attributes['searchName'])) {
                    $field = $this->attributes['searchName'];
                } else {
                    // remove any periods in field name
                    $field = str_replace('.', '', $this->field);
                }

                if (isset($this->attributes['help']) and $this->attributes['help'] != '') {
                    $help = ' title="' . $this->attributes['help'] . '"';
                } else {
                    $help = '';
                }
                if (isset($this->attributes['newSearchColumn']) and $this->attributes['newSearchColumn']) {
                    $basis = ' flex-basis: 100%;';
                } else {
                    $basis = '';
                }

//                echo '<div class="ar_search_items" style="min-width: '.(300 + $searchHeadingWidth).'px;'.$basis.'"'.$help.'>
//                            <span class="ar_search_label" id="search_label_'.$field.'" style="width: '.$searchHeadingWidth.'px;" >' . $this->heading . ':</span>';
                echo '<div class="ar_search_items" style="'.$basis.'"'.$help.'>
                            <span class="ar_search_label" id="search_label_'.$field.'" style="width: '.$searchHeadingWidth.'px;" >' . $this->heading . ':</span>';
                if (($this->type == 'yn') or ($this->type == 'tf') or ($this->type=='b')) {
                    echo '
                                <select name="' . $field . '_so" id="' . $field . '_so" class="ar_search_yn_select">';
                    $operator_array = setOperatorArray($this->type);
                    if (isset($this->attributes['altLabels'])) { // something other than Yes and No
                        $operator_array[1] = array('value' => "0", 'text' => $this->attributes['altLabels'][0]);
                        $operator_array[2] = array('value' => "1", 'text' => $this->attributes['altLabels'][1]);
                    }
                    foreach ($operator_array as $op_array) {
                        ?>
                        <option value=<?php echo '"' . $op_array['value'] . '"';
                        if ($operator == $op_array['value']) echo ' selected="selected"'; ?>><?php echo $op_array['text']; ?></option>
                        <?php
                    }
                    echo '</select>';

                } elseif ((($this->type=='s') or ($this->type=='i')) and isset($this->attributes['format']) and is_array($this->attributes['format'])) {
                    // drop-down list is in format[]
//                    echo '<input type="hidden" name="' . $field . '_so" id="' . $field . '_so" value="=" />'; // change to get value from _so instead of _in1 9/3/2020
                    echo '<select name="' . $field . '_so" id="' . $field . '_so" class="ar_search_select">';
                    echo '<option value="" selected >All</option>'."\n";
                    $operator_array = $this->attributes['format'];
                    foreach ($operator_array as $key => $op_array) {
                        if (isset($this->attributes['useKeys']) and $this->attributes['useKeys']) {
                            echo '<option value="' . $key . '" ';
                            if ($operator === $key) echo ' selected ';
                        } else {
                            echo '<option value="' . $op_array . '" ';
                            if ($operator === $op_array) echo ' selected ';
                        }
                        echo '>'.$op_array.'</option>'."\n";
                    }
                    echo '</select>';

                } elseif (((($this->type=='s') or ($this->type=='i')) and (isset($this->attributes['lookup']) and !is_array($this->attributes['lookup']))) or (isset($this->attributes['lookup']) and !is_array($this->attributes['lookup']))) {
                    // col['lookup'] is sql statement for select with 2 fields, id and item
                    $dbe = new DBEngine($db);
                    $pq_query = $this->attributes['lookup'];
                    $dbe->setBindtypes("");
                    $dbe->setBindvalues(array());
                    $lookupRows = $dbe->execute_query($pq_query);  // execute query
                    $dbe->close();
                    echo '<input type="hidden" name="' . $field . '_so" id="' . $field . '_so" value="Yes" />'; // value Yes is just a dummy to force evaluation
                    echo '
                                <select name="' . $field . '_in1" id="' . $field . '_in1" class="ar_search_select">';
                    if ($this->type=='i') {
                        echo '<option value="0" selected="selected">All</option>';
                    } else {
                        echo '<option value="All" selected="selected">All</option>';
                    }
                    if (isset($this->attributes['altAllowNoneLabel'])) {
                        $label = $this->attributes['altAllowNoneLabel'];
                    } else {
                        $label = 'None';
                    }
                    if (isset($this->attributes['allowNone']) and $this->attributes['allowNone']) {
                        echo '<option value="999999"';
                        if ($value1 === "None") {
                            echo ' selected="selected"';
                        }
                        echo '>'.$label.'</option>';
                    }
                    foreach ($lookupRows as $item) {
                        ?>
                        <option value=<?php echo '"' . $item['id'] . '"';
                        if ($value1 == $item['id']) echo ' selected="selected"'; ?>><?php echo $item['item']; ?></option>
                        <?php
                    }
                    echo '</select>';

                } elseif (isset($this->attributes['lookup']) and is_array($this->attributes['lookup'])) { //(($this->type=='s') or ($this->type=='i')) and
                    // col['lookup'] is array
                    echo '<input type="hidden" name="' . $field . '_so" id="' . $field . '_so" value="Yes" />';
                    echo '
                        <select name="' . $field . '_in1" id="' . $field . '_in1" class="ar_search_select">';
//                            if (isset($this->attributes['useKeys']) and array_keys($this->attributes['lookup'])[0] == 0) {
//                                echo '<option value=0 selected="selected">All</option>';
//                            } else {
                    echo '<option value="All" selected="selected">All</option>';
//                            }
                    foreach ($this->attributes['lookup'] as $key => $item) {
                        if (isset($this->attributes['useKeys'])) {
                            echo '<option value= "' . $key . '" ';
                            if (isset($value1) and $value1 != "All" and $value1 == $key) echo ' selected ';
                            echo '>' . $item . '</option>';
                        } else {
                            echo '<option value="' . $item . '" ';
                            if (isset($value1) and $value1 === $item) echo ' selected ';
                            echo '>' . $item . '</option>';
                        }
                    }
                    echo '</select>';
                } elseif ($this->type == 'lookup' and isset($this->attributes['format']['linkTable'])) {
                    // many to many relationship with a linking table, make a select box containing all items from other table
                    $dbe = new DBEngine($this->attributes['format']['db']);
                    if (isset($this->attributes['format']['sourceCriteria'])) {
                        $sourceCriteria = ' WHERE '.$this->attributes['format']['sourceCriteria'];
                    } else {
                        $sourceCriteria = '';
                    }
                    $pq_query = 'SELECT `'.$this->attributes['format']['sourceField'].'` as id, `'.$this->attributes['format']['displayField'].'` as item FROM `'. $this->attributes['format']['sourceTable'].'` '.$sourceCriteria.' ORDER BY `'.$this->attributes['format']['displayField'].'`';
                    $dbe->setBindtypes("");
                    $dbe->setBindvalues(array());
                    $lookupRows = $dbe->execute_query($pq_query);  // execute query
                    $dbe->close();
                    echo '<input type="hidden" name="' . $field . '_so" id="' . $field . '_so" value="Yes" />'; // value Yes is just a dummy to force evaluation
                    echo '
                                <select name="' . $field . '_in1" id="' . $field . '_in1"  class="ar_search_select">
                                <option value=0 selected="selected">All</option>';
                    foreach ($lookupRows as $item) {
                        ?>
                        <option value=<?php echo '"' . $item['id'] . '"';
                        if ($value1 == $item['id']) echo ' selected="selected"'; ?>><?php echo $item['item']; ?></option>
                        <?php
                    }
                    echo '</select>';
                } else {
                    if ($this->type == 'd' or $this->type == 'dt') { // added datetime-local
                        echo '<div class="ar_search_inputs" id="' . $field . '_inputs">
                                        ';
                        if ($this->type == 'd') {
                            echo '<input type="date" name="' . $field . '_in1" id="' . $field . '_in1" title="Enter the value to search" value="' . niceDate($value1, false, 'Y-m-d') . '"/>';
                        } else {
                            echo '<input type="datetime-local" name="' . $field . '_in1" id="' . $field . '_in1" title="Enter the value to search" value="' .  niceDate($value1, false, 'Y-m-d\TH:i:s') . '"/>';
                        }
                    } else {
                        echo '<div class="ar_search_inputs" id="' . $field . '_inputs">
                                        ';
                        echo '<input type="text" size="15" name="' . $field . '_in1" id="' . $field . '_in1" title="Enter the value to search" value="' . gls_esc_attr($value1) . '"/>';
                    }
                    if ($this->type == 'd' or $this->type == 'dt') {
                        if ($this->type == 'd') {
                            echo '<input type="date" class="' . $field . '_btwn" name="' . $field . '_in2" id="' . $field . '_in2" title="Enter the ending value" style="display: none; margin-top: 4px;" value="' . niceDate($value2, false, 'Y-m-d') . '"/>';
                        } else {
                            echo '<input type="datetime-local" class="' . $field . '_btwn" name="' . $field . '_in2" id="' . $field . '_in2" title="Enter the ending value" style="display: none; margin-top: 4px;" value="' .  niceDate($value2, false, 'Y-m-d\TH:i:s') . '"/>';
                        }
                    } else {
                        echo '<input type="text" class="' . $field . '_btwn" size="15" name="' . $field . '_in2" id="' . $field . '_in2" placeholder="To" title="Enter the ending value" style="display: none; margin-top: 4px;" value="' . gls_esc_attr($value2) . '"/>';
                    }
                    echo '</div>';
                    if ($this->type == 'd' or $this->type == 'dt') {
                        echo '<select name="' . $field . '_so" id="' . $field . '_so" class="ar_search_select" tabindex=-1 style="width: 140px; ">';
                    } else {
                        echo '<select name="' . $field . '_so" id="' . $field . '_so" class="ar_search_select" tabindex=-1 >';
                    }
                    $operator_array = setOperatorArray($this->type);
                    if (isset($this->attributes['nullSearch']) and $this->attributes['nullSearch'] == true) {
                        $operator_array[] = array('value' => "IS NULL", 'text' => 'is empty');
                    }
                    foreach ($operator_array as $op_array) {
                        ?>
                        <option value=<?php echo '"' . $op_array['value'] . '"';
                        if ($operator == $op_array['value']) echo ' selected="selected"'; ?>><?php echo $op_array['text']; ?></option>
                        <?php
                    }
                    echo '</select>';
                }
                echo '</div>';
            }
        }
    }

    /**
     * setRawValue
     * Performs all processing and formatting for a cell's contents
     *
     * @param string|null $rawValue
     * @param int $j
     * @param array $row
     * @param string $keyField
     * @param bool $allowEditing
     * @param string $editColumn
     * @param bool $showAsList
     */
    public function setRawValue(?string $rawValue, int $j, array $row, string $keyField, bool $allowEditing, string $editColumn, bool $showAsList)
    {
        global $currencySymbol, $db;

        if ($this->attributes['allowHtml']) { // added 6/27/22, only allow html if allowHtml is true
            $this->rawValue = $rawValue;
        } else {
            $this->rawValue = gls_esc_html($rawValue); // otherwise, escape it
        }

        if (!isset($currencySymbol)) $currencySymbol = CURRENCY_SYMBOL;
        $fld = $this->field;

        // construct the <td> tag
        if (!$showAsList) {
            $dispHTML = '<td class="report_cell rt_col' . $j . ' ';
            $hiLiteStyles = '';
            if (isset($row['hiLiteRow'])) {
                $dispHTML .= ' report_sectionHeading';
                if (!is_bool($row['hiLiteRow'])) {
                    // hiLiteRow contains a background color
                    $hiLiteStyles = $row['hiLiteRow'];
                }
            }
            if (isset($row['subtotalRow'])) {
                $dispHTML .= ' report_subtotal';
            }
            $dispHTML .= '" style="' . $hiLiteStyles;
            $dispValue = '';
            if (isset($this->attributes['align'])) { // change to favor 'align' setting over other settings 6/8/22
                if ($this->attributes['align'] == 'decimal') {
                    $dispHTML .= 'text-align: right;';
                } else {
                    $dispHTML .= 'text-align: '.$this->attributes['align'].';';
                }
            } else {
                if ($this->attributes['format'] == 'currency' or $this->attributes['format'] == 'accounting' or $this->attributes['format'] == 'numeric' or $this->attributes['format'] == 'percent') {
                    $dispHTML .= 'text-align: right;';
                } elseif ($this->type == 'c' or $this->type == 'b' or $this->type == 'r' or substr($this->type, 0, 5) == 'link:' or substr($this->type, 0, 9) == 'callback:' or $this->attributes['format'] == 'center') {
                    $dispHTML .= 'text-align: center;';
                }
            }
            $dispHTML .= '">';
        }
        // content
        if (isset($this->attributes['help']) and $this->attributes['help'] != '') {
            $help = ' title="' . $this->attributes['help'] . '"';
        } else {
            $help = '';
        }
        // cell data
        if ($this->type == 'c') {
            // check box type of cell
            if (isset($this->attributes['ifEquals'])) { // only show checkbox if condition is met
                if ($row[$this->attributes['ifEquals']['field']] == $this->attributes['ifEquals']['value']) {
                    $dispHTML .=  '<input type="checkbox"  name="cb' . $this->rawValue . '" id="cb' . $this->rawValue . '" class="selectCheckboxes"/>';
                    $dispValue =  '-';
                } else {
                    $dispHTML .= '-';
                    $dispValue =  '-';
                }
            } else {
                $dispHTML .=  '<input type="checkbox"  name="cb' . $this->rawValue . '" id="cb' . $this->rawValue . '" class="selectCheckboxes"/>';
                $dispValue =  '-';
            }
        } elseif ($this->type == 'r') {
            // radio buttons type of cell
            if (isset($this->attributes['ifEquals'])) { // only show radio if condition is met
                if ($row[$this->attributes['ifEquals']['field']] == $this->attributes['ifEquals']['value']) {
                    $dispHTML .=  '<input type="radio"  name="radio_' . $fld . '" id="ra' . $this->rawValue . '" class="selectRadios"/>';
                    $dispValue =  '-';
                } else {
                    $dispHTML .=  '-';
                    $dispValue =  '-';
                }
            } else {
                $dispHTML .=  '<input type="radio"  name="radio_' . $fld . '" id="ra' . $this->rawValue . '" class="selectRadios"/>';
                $dispValue =  '-';
            }
        } elseif (substr($this->type, 0, 9) == 'function:' or isset($this->attributes['showFunction'])){
            // link to external php function, pass the $col array and value
            if (isset($this->attributes['showFunction'])) {
                $fn = $this->attributes['showFunction'];
            } else {
                $fn = substr($this->type, 9);
            }
            if (is_callable($fn)) {
                $dispHTML .= call_user_func($fn, $row[$keyField], $this->rawValue, $this->attributes, ($this->attributes['inlineEdit'] and $allowEditing and ($editColumn == 'editCol'.$j or $showAsList))? true: false, $row);
                $dispValue = call_user_func($fn, $row[$keyField], $this->rawValue, $this->attributes, false, $row);
            }
        } elseif (substr($this->type, 0, 9) == 'callback:') {
            // link to javascript function - put the stated field in as parameter
            // ifCount used to add a conditional to the display. Should = field name of the int field - if <> 0 data will display
            if (!isset($row['hiLiteRow'])) { // block display of link for subtotal rows
                if (!isset($this->attributes['ifCount']) or (isset($this->attributes['ifCount']) and $row[$this->attributes['ifCount']] != 0)) {
                    if (!isset($this->rawValue)) { // if there is no actual field in table, just use the keyfield
                        $param = $row[$keyField];
                    } else {
                        $param = $this->rawValue;
                    }
                    if (!is_numeric($param)) {
                        $param = "'" . $param . "'";
                    }
                    if (isset($this->attributes['altIcon'])) {
                        $icon = $this->attributes['altIcon'];
                    } else {
                        $icon = '<i class="bi-arrow-return-right smaller_icon"></i>';
                    }
                    if ($help != '') { // if help is present use it for title
                        $dispHTML .= '<button type="button" onclick="' . substr($this->type, 9) . '(' . $param . ',' . $row[$keyField] . ')" class="buttonBar" ' . $help . '>' . $icon . '&nbsp;' . $this->attributes['format'] . '</button>';
                    } else {
                        $dispHTML .= '<button type="button" onclick="' . substr($this->type, 9) . '(' . $param . ',' . $row[$keyField] . ')" class="buttonBar" title="Click to ' . strtolower($this->attributes['format']) . ' this record">' . $icon . '&nbsp;' . $this->attributes['format'] . '</button>';
                    }
                } else { // $row[$this->attributes['ifCount']] == 0
                    $dispHTML .= '- - -';
                }
            }
            $dispValue = '';
        } elseif ($this->type == 'lookup'){
            // lookup in other db table - 'format' element is array
            if (isset($this->attributes['format']['linkField'])) {
//                            $dispValue = lookup($this->attributes['format']['db'], $this->attributes['format']['table'], $this->attributes['format']['keyfield'], $row[$this->attributes['format']['linkField']], $this->attributes['format']['field']);
                $dispValue = lookup($this->attributes['format'], $row[$this->attributes['format']['linkField']],  $row[$keyField]);
            } else {
//                            $dispValue = lookup($this->attributes['format']['db'], $this->attributes['format']['table'], $this->attributes['format']['keyfield'], $this->rawValue, $this->attributes['format']['field']);
                $dispValue = lookup($this->attributes['format'], $this->rawValue);
            }
            $dispHTML .= $dispValue;
        } elseif (isset($this->attributes['lookup'])) {
            if (is_array($this->attributes['lookup'])) {
                // select box - static array
                if (isset($this->attributes['useKeys'])) {
                    if ($this->attributes['inlineEdit'] and $allowEditing and ($editColumn == 'editCol'.$j or $showAsList)) {
                        $dispHTML .=  '<select name="'.$fld.'" id="'.$fld.'_'.$row[$keyField].'" style="width: 100%;" class="rtSaveChanges" data-keyValue="'.$row[$keyField].'" data-field="'.$fld.'" >';
                        foreach ($this->attributes['lookup'] as $key => $item) {
                            $dispHTML .=  '<option value="' . $key . '" ';
                            if ($this->rawValue == $key) {
                                $dispHTML .=  ' selected ';
                            }
                            $dispHTML .=  '>' . $item . '</option>';
                        }
                        $dispHTML .= '</select>';
                    } else {
                        $dispHTML .= $this->attributes['lookup'][$this->rawValue];
                    }
                    $dispValue = $this->attributes['lookup'][$this->rawValue];
                } else {
                    $dispHTML .=  $this->rawValue;
                    $dispValue = $this->rawValue;
                }
            } else {
                // select box lookup - sql is in $this->attributes['lookup']
                $ed = false;
                if ($this->attributes['inlineEdit'] and $allowEditing and ($editColumn == 'editCol'.$j or $showAsList)) $ed = true;
                if ($ed) {
                    $dispHTML .=  '<select name="'.$fld.'" id="'.$fld.'_'.$row[$keyField].'" style="width: 100%;" class="rtSaveChanges" data-keyValue="'.$row[$keyField].'" data-field="'.$fld.'" >';
                }
                if (isset($this->attributes['db'])) {
                    $dispHTML .= colLookup($this->attributes['db'], $this->attributes['lookup'], $this->rawValue, $ed, (isset($this->attributes['allowNone']) and $this->attributes['allowNone']), (isset($this->attributes['altAllowNoneLabel'])) ? $this->attributes['altAllowNoneLabel'] : 'None');
                    $dispValue = colLookup($this->attributes['db'], $this->attributes['lookup'], $this->rawValue, false, (isset($this->attributes['allowNone']) and $this->attributes['allowNone']), (isset($this->attributes['altAllowNoneLabel'])) ? $this->attributes['altAllowNoneLabel'] : 'None');
                } else {
                    $dispHTML .= colLookup($db, $this->attributes['lookup'], $this->rawValue, $ed, (isset($this->attributes['allowNone']) and $this->attributes['allowNone']), (isset($this->attributes['altAllowNoneLabel'])) ? $this->attributes['altAllowNoneLabel'] : 'None');
                    $dispValue = colLookup($db, $this->attributes['lookup'], $this->rawValue, false, (isset($this->attributes['allowNone']) and $this->attributes['allowNone']), (isset($this->attributes['altAllowNoneLabel'])) ? $this->attributes['altAllowNoneLabel'] : 'None');
                }
                if ($ed) {
                    $dispHTML .=  '</select>';
                }
            }
        } elseif (substr($this->type, 0, 5) == 'link:'){
            // link to other page
            //                        echo '<a href="?' . $this->attributes['field'] . '=' . $this->rawValue . '&process=' . substr($this->type, 5) . '&source=self" target="_self" class="bluelink" title="Click to '.strtolower($this->attributes['format']).' this record">' .$this->attributes['format'] . '</a>';
            if (isset($this->attributes['help']) and $this->attributes['help'] != '') { // allow unique help lines for links 9/5/2020
                $help = $this->attributes['help'];
            } else {
                if ($this->attributes['format'] != '') {
                    $help = "Click to " . strtolower($this->attributes['format']) . " this record";
                } else {
                    $help = "Click to open this record";
                }
            }
            if (isset($this->attributes['altIcon'])) {
                $icon = $this->attributes['altIcon'];
            } else {
                $icon = '<i class="bi-arrow-return-right smaller_icon"></i>';
            }
            if ($this->attributes['format'] != '') { // change link: type to buttonBar's with icon 10/5/22
                $dispHTML .=  '<a class="buttonBar doLink" data-fieldname="' . $this->field . '" data-fielddata="' . $this->rawValue . '" data-type="' . substr($this->type, 5) . '" title="' . $help . '">' . $icon . '&nbsp;' . $this->attributes['format'] . '</a>';
            } else {
                $dispHTML .=  '<a class="bluelink doLink" data-fieldname="' . $this->field . '" data-fielddata="' . $this->rawValue . '" data-type="' . substr($this->type, 5) . '" title="' . $help . '">' . $this->rawValue . '</a>';
            }
            $dispValue = '';
        } else {
            // all other types
            if ($this->type == 't') {
                // large field - use scrollable <div> in it
//                            if (strpos($this->attributes['width'],'%')>0) { // changed to 100% below - 9/20/2019
//                                $wid = $this->attributes['width'];
//                            } else {
//                                $wid = $this->attributes['width'].'in';
//                            }
                $wid = '100%';
                $hite = '100px';
                if (isset($this->attributes['max-height'])) { // changed to 100% below - 9/20/2019
                    $hite = $this->attributes['max-height'];
                }
                $dispHTML .= '<div style="width: ' . $wid . ';max-height: ' . $hite . '; overflow: auto; white-space: normal;">'; // added wrapping - 9/23/2019
            }
            if ($allowEditing and $this->attributes['inlineEdit'] and ($editColumn == 'editCol'.$j or $showAsList) and $this->type != 'yn' and isset($this->attributes['size'])) {
                if ($this->attributes['size']==0) { // text area
                    $dispHTML .= '<textarea style="width: 98%; height: 38px;" id="'.$fld.'_'.$row[$keyField].'" class="rtSaveChanges" data-keyValue="'.$row[$keyField].'" data-field="'.$fld.'">';
                } else {
                    $dispHTML .= '<input type="text" size="' . $this->attributes['size'] . '" maxlength="' . $this->attributes['maxlength'] . '" style="width: 100%;
                    max-width: '.$this->attributes['size'].'em; box-sizing: border-box;" id="'.$fld.'_'.$row[$keyField].'" onClick="this.select();"
                    class="rtSaveChanges" data-keyValue="'.$row[$keyField].'" data-field="'.$fld.'" value="';
                }
            }
            // show the field data
            if ($this->attributes['format'] == 'currency' or $this->attributes['format'] == 'accounting') {
                if (isset($this->rawValue)) {
                    if (isset($this->attributes['currencyField'])) { // points to a field in the table that contains the currency code
                        if ($row[$this->attributes['currencyField']] == 'USD' or $this->attributes['currencyField'] == 'USD') {
                            $curr = '$';
                        } elseif ($row[$this->attributes['currencyField']] == 'EURO' or $this->attributes['currencyField'] == 'EURO') {
                            $curr = '€';
                        } elseif ($row[$this->attributes['currencyField']] == 'GBP' or $this->attributes['currencyField'] == 'GBP') {
                            $curr = '£';
                        }
                    } else {
                        $curr = $currencySymbol;
                    }
                    if ($allowEditing and $this->attributes['inlineEdit'] and ($editColumn == 'editCol'.$j or $showAsList) and isset($this->attributes['size'])) {
                        $dispHTML .=  $this->rawValue;
                        $dispValue = $this->rawValue;
                    } else {
                        if ($this->rawValue !== '') {
                            if ($this->attributes['format'] == 'accounting') {
                                if ($this->rawValue < 0) {
                                    $dispHTML .= '<span class="bad_color">' . $curr . ' (' . number_format(abs($this->rawValue), 2) . ')</span>';
                                    $dispValue = $curr . ' (' . number_format(abs($this->rawValue), 2) . ')';
                                } else {
                                    $dispHTML .= '<span class="good_color">' . $curr . ' ' . number_format($this->rawValue, 2).'</span>';
                                    $dispValue = $curr . ' ' . number_format($this->rawValue, 2);
                                }
                            } else {
                                $dispHTML .= $curr . number_format($this->rawValue, 2);
                                $dispValue = $curr . number_format($this->rawValue, 2);
                            }
                        } else {
                            $dispHTML .=  '';
                            $dispValue = '';
                        }
                    }
                }
            } elseif ($this->type == 'd' or $this->type == 'dt') {
                // date type - use format if present
                if ($this->attributes['format'] != '') {
                    $dispHTML .= niceDate($this->rawValue, true, $this->attributes['format']);
                    $dispValue = niceDate($this->rawValue, true, $this->attributes['format']);
                } else {
                    $dispHTML .= niceDate($this->rawValue);
                    $dispValue = niceDate($this->rawValue);
                }
            } elseif ($this->type == 'b') { // boolean type - change to Yes or No
                if (isset($this->attributes['invertColors']) and $this->attributes['invertColors']) {
                    $yesClr = 'bad_color';
                    $noClr = 'good_color';
                } else {
                    $yesClr = 'good_color';
                    $noClr = 'bad_color';
                }
                if (!isset($this->attributes['altLabels'])) { // something other than Yes and No
                    $this->attributes['altLabels'] = array('No','Yes');
                }
                if ((isset($this->attributes['ifEquals']) and ($row[$this->attributes['ifEquals']['field']] == $this->attributes['ifEquals']['value'])) or !isset($this->attributes['ifEquals'])) {
                    if ($this->attributes['inlineEdit'] and $allowEditing and ($editColumn == 'editCol' . $j or $showAsList)) {
                        $dispHTML .= '<select name="' . $fld . '" id="' . $fld . '_' . $row[$keyField] . '" style="width: 100%;" class="rtSaveChanges" data-keyValue="' . $row[$keyField] . '" data-field="' . $fld . '" >';
                        for ($i = 0; $i < count($this->attributes['altLabels']); $i++) {
                            $dispHTML .= '<option value="' . $i . '" ';
                            if ($this->rawValue == $i) $dispHTML .= 'selected';
                            $dispHTML .= ' >' . $this->attributes['altLabels'][$i] . '</option>';
                        }
                        $dispHTML .= '</select>';
                    } else {
                        if ($this->attributes['format'] == 'NoColors') { // added 2/26/21
                            $dispHTML .= $this->attributes['altLabels'][$this->rawValue];
                            $dispValue = $this->attributes['altLabels'][$this->rawValue];
                        } else {
                            if ($this->rawValue == 1) {
                                $dispHTML .= '<span class="' . $yesClr . '">' . $this->attributes['altLabels'][1] . '</span>';
                                $dispValue = $this->attributes['altLabels'][1];
                            } else {
                                $dispHTML .= '<span class="' . $noClr . '">' . $this->attributes['altLabels'][0] . '</span>';
                                $dispValue = $this->attributes['altLabels'][0];
                            }
                        }
                    }
                }
            } elseif ($this->type != 'lookup' and isset($this->attributes['format']) and is_array($this->attributes['format'])) { // isset($this->attributes['format']) and is_array($this->attributes['format']) and (isset($this->attributes['useKeys']) and $this->attributes['useKeys'])) {
                // select box - static array in format key
                if (isset($this->attributes['useKeys'])) {
                    if ($this->attributes['inlineEdit'] and $allowEditing and ($editColumn == 'editCol'.$j or $showAsList)) {
                        $dispHTML .=  '<select name="'.$fld.'" id="'.$fld.'_'.$row[$keyField].'" style="width: 100%;" class="rtSaveChanges" data-keyValue="'.$row[$keyField].'" data-field="'.$fld.'" >';
                        foreach ($this->attributes['format'] as $key => $item) {
                            $dispHTML .=  '<option value="' . $key . '" ';
                            if ($this->rawValue == $key) {
                                $dispHTML .=  ' selected ';
                            }
                            $dispHTML .=  '>' . $item . '</option>';
                        }
                        $dispHTML .= '</select>';
                    } else {
                        $dispHTML .= $this->attributes['format'][$this->rawValue];
                    }
                    $dispValue = $this->attributes['format'][$this->rawValue];
                } else {
                    $dispHTML .=  $this->rawValue;
                    $dispValue = $this->rawValue;
                }
            } elseif ($this->type == 'bytes') {
                $dispHTML .= formatSizeUnits($this->rawValue);
                $dispValue = formatSizeUnits($this->rawValue);
            } else {
                $dispValue = $this->rawValue;
                if (isset($this->attributes['truncate']) and strlen($dispValue) > $this->attributes['truncate']) {
                    $dispValue = substr($dispValue, 0, $this->attributes['truncate']) . '&hellip;';
                }
                $dispHTML .= $dispValue;
                if ($this->attributes['format'] == 'percent') {
                    $dispValue .= '%';
                    $dispHTML .= '%';
                }
            }
        }
        $dispValue = stripslashes(strip_tags($dispValue,'<p><a><i><em><b><br><strong><input><select><option><textarea><span><div><img>'));

        // add closing tag for textarea and input fields
        if ($allowEditing and $this->attributes['inlineEdit'] and ($editColumn == 'editCol' . $j or $showAsList)) {
            if ($this->attributes['size'] == 0) { // text area
                $dispHTML .= '</textarea>';
            } else {
                $dispHTML .= '" />';
            }
        }

        // add closing tag for scrollable textarea
        if ($this->type == 't') {
            $dispHTML .=  '</div>';
        }

        // closing tag for table cell
        if (!$showAsList) {
            $dispHTML .= '</td>';
        }

        $this->value = $dispValue;
        $this->html = $dispHTML;
    }


}
