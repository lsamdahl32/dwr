<?php

/**
 * attractions.php
 *
 * @author Lee Samdahl
 *
 * @created 4/4/23
 */

        // ini_set('display_errors', 1);
        // ini_set('display_startup_errors', 1);
        // error_reporting(E_ALL);
ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once(PLMPATH . "classes/ReportTable.php");
require_once(PLMPATH . "classes/ATReports.php");

if (!isset($_SESSION['is_logged_in']) or $_SESSION['is_logged_in'] == false) {
    header('location: login.php');
}

$table = 'attractions';
$keyField = 'attractionID';
$keyFieldType = 'i';
$orderBy = array('sortOrder ASC');// default sort order

$db = 'plm';

$rt1 = new ReportTable($db, $table, $keyField, 'att');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list','profile','edit','add'), $table, $keyField, 'i', false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(true); // whether or not to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Attractions');
$atr->setAllowDelete(true);
$atr->setEditTitle('Attraction');
$atr->setProfileLabelWidth('100');
$atr->setRtEmptyMessage('No attractions were found.');
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');
$atr->setImagePath(IMAGEPATH);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'attractionID', 'heading'=>'Action', 'type'=>'link:attractions.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'title', 'heading'=>'Title', 'type'=>'s', 'width'=>'2', 'size'=>'40', 'format'=>'', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'description', 'heading'=>'Description', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'height'=>'2', 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>false, 'required'=>true));
$rt1->addColumn(array('field'=>'sortOrder', 'heading'=>'Sort Order', 'type'=>'i', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true,));
$rt1->addColumn(array('field'=>'url', 'heading'=>'Link', 'type'=>'s', 'width'=>'2', 'format'=>'', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'isAvailable', 'heading'=>'Available', 'type'=>'b', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'image', 'heading'=>'Image', 'type'=>'s', 'width'=>'1', 'imagePreview'=>true, 'format'=>'', 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'createdOn', 'heading'=>'Created On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>15, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

$atr->process();


/**
 * customValidation - additional validation checks
 * called from checkValidation in RecordManage class
 *
 * @param $dbVars
 * @param bool $update
 * @param $id
 * @param array $column_array
 * @return array
 */
//function customValidation(&$dbVars, bool $update=false, $id=0, array $column_array = array()):array
//{
//    global $db;
//    $arrayOfErrors = array();
//    // expiresOn must be future
//    if (!$update) {
//        if (date('Y-m-d H:i:s', strtotime($dbVars['expiresOn']['data'])) <= date('Y-m-d H:i:s')) {
//            $arrayOfErrors['expiresOn'] = "Expires On must be in the future.";
//        }
//    }
//    return $arrayOfErrors;
//}

?>
<script>

    // $(document).ready(function () {
    //     $(window).resize(function () {
    //         var frame = $('#attractionsView iframe', window.parent.document);
    //         var height = $(this).height();
    //         frame.height(height + 15);
    //     });
    // });
</script>
