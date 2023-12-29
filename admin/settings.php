<?php

/**
 * settings.php
 *
 * @author Lee Samdahl
 *
 * @created 6/8/23
 */

ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once(PLMPATH . "classes/ReportTable.php");
require_once(PLMPATH . "classes/ATReports.php");

if (!isset($_SESSION['is_logged_in']) or $_SESSION['is_logged_in'] == false) {
    header('location: login.php');
}

$table = 'configuration';
$keyField = 'configurationID';
$keyFieldType = 'i';
$orderBy = array('sortOrder ASC');// default sort order

$db = 'plm';

if (isset($_POST['process'])) {
    if ($_POST['process'] == 'getInput') {
        getInput();
    } elseif ($_POST['process'] == 'saveInput') {
        saveInput();
    }
    exit;
}


$rt1 = new ReportTable($db, $table, $keyField, 'conf');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list'), $table, $keyField, 'i', false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(false); // whether to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Settings');
$atr->setRtEmptyMessage('No settings were found.');
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'configurationID', 'heading'=>'Action', 'type'=>'callback:editSetting', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'title', 'heading'=>'Title', 'type'=>'s', 'width'=>'2', 'format'=>'', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>false, 'showEdit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'description', 'heading'=>'Description', 'type'=>'t', 'width'=>'4', 'format'=>'', 'size'=>0, 'height'=>'1', 'profile'=>true, 'profileOrder'=>2, 'search'=>false, 'edit'=>false, 'showEdit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'value', 'heading'=>'Value', 'type'=>'t', 'showFunction'=>'showValue', 'width'=>'4', 'format'=>'', 'size'=>0, 'height'=>'2', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));

$atr->process();

function getInput()
{
    global $db, $table, $keyField;
    // look up the record and assemble the input
    $dbe = new DBEngine($db);
    $id = $_POST['id'];

    $setting = $dbe->getRowWhere($table, $keyField, $id);
    if ($setting) {
        $output = array();
        $output['title'] = $setting['title'];
        if ($setting['input_type'] == 'select') {
            // parse the select_options
            $options = explode(',', $setting['select_options']);

            $output['input'] = '<select id="settingEdit' . $id . '" >';
            foreach ($options as $option) {
                $output['input'] .= '<option ' . (($setting['value'] == $option)?'selected':'') . '>' . $option . '</option>';
            }
            $output['input'] .= '</select>';
        } elseif ($setting['input_type'] == 'textarea') {
            $output['input'] = '<textarea id="settingEdit' . $id . '" style="height: 1.5in; width: 318px;" >' . $setting['value'] . '</textarea>';
        } elseif ($setting['input_type'] == 'checkbox') {
            $checked = '';
            if ($setting['value'] == 1) {
                $checked = 'checked';
            }
            $output['input'] = $setting['title'] . ':&nbsp;<input type="checkbox" id="settingEdit' . $id . '" value="1" ' . $checked . '/>';
        } else {
            $output['input'] = '<input type="' . $setting['input_type'] . '" id="settingEdit' . $id . '" value="' . $setting['value'] . '"/>';
        }
        echo 'Success' . json_encode($output);
    } else {
        echo 'Error';
    }
}

function saveInput()
{
    global $db, $table, $keyField;
    // validate the input and then save the record
    $dbe = new DBEngine($db);
    $id = $_POST['id'];
    $value = $_POST['value'];
    $msg = '';

    $setting = $dbe->getRowWhere($table, $keyField, $id);
    if ($setting) {
        if ($setting['value'] != $value) {

            switch ($setting['input_type']) {
                case 'select':
                case 'textarea':
                case 'text':
                    // must be text - ignore
                    break;
                case 'checkbox':
                case 'number':
                    // must be numeric
                    if (!is_numeric($value)) {
                        $msg = 'The value must be numerical.';
                    }
                    break;
                case 'date':
                    // date must be valid - blank date is allowed
                    if ($value != '' and date('Y-m-d', strtotime($value)) <= "1969-12-31") {
                        $msg = 'The value must be a date.';
                    }
                    break;
            }
            if ($msg == '') {
                // Save the record
                $data = array('value' => $value);
                $result = $dbe->updateRow($table, $data, $keyField, $id);
                if ($result > 0) {
                    $msg = 'The changes were saved.';
                } elseif ($result == 0) {
                    $msg = 'No changes were made.';
                } else {
                    $msg = 'An error occurred. The record was not saved.';
                }
            }
        } else {
            $msg = 'No changes were made.';
        }
    } else {
        $msg = 'An error occurred. The record was not saved.';
    }
    echo $msg;
    $dbe->close();
}

function showValue($id, $val, $col, $edit, $row)
{
    if ($row['input_type'] == 'checkbox') {
        return ($val == 1)?'True':'False';
    } else {
        return $val;
    }
}
?>
<script>

    function editSetting(id) {
        // get the input from Ajax

        $.post('settings.php', {
            process:    'getInput',
            id:         id
        }, function (data) {
            if (data.substring(0, 7) === "Success") {
                let obj = JSON.parse(data.substring(7));
                // console.log(obj);
                $("#confirmText1").html("Edit " + obj['title']);
                $("#delSerialsText").html(obj['input']);
                jqConfIcon('');
                $("#doConfirmAction").val("Save").off("click").on("click", function() {
                    $('#jqConfDialog').jqmHide();
                    // save the changes
                    let el = $('#settingEdit' + id);
                    let value = '';
                    if (el.is(':checkbox')) {
                        if (el.is(':checked')) {
                            value = 1;
                        } else {
                            value = 0;
                        }
                    } else {
                        value = el.val();
                    }
                    $.post('settings.php', {
                        process:    'saveInput',
                        id:         id,
                        value:      value
                    }, function (data) {
                        if (data !== 'The changes were saved.') {
                            jqAlert(data);
                        } else {
                            rtconf.refresh();
                        }
                    });
                });
                $('#jqConfDialog').jqmShow();
            }
        });
    }
</script>
