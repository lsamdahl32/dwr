<?php
/**
 * amenities.php
 *
 * @author Lee Samdahl
 *
 * @created 4/4/23
 */

ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once(PLMPATH . "classes/ReportTable.php");
require_once(PLMPATH . "classes/ATReports.php");
require_once(PLMPATH . "classes/ATSubTable.php");

if (!isset($_SESSION['is_logged_in']) or $_SESSION['is_logged_in'] == false) {
    header('location: login.php');
}

$table = 'amenities';
$keyField = 'amenityID';
$keyFieldType = 'i';
$orderBy = array('sortOrder ASC');// default sort order

$db = 'plm';

$rt1 = new ReportTable($db, $table, $keyField, 'amen');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list','profile','edit','add'), $table, $keyField, 'i', false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(true); // whether or not to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Amenities');
$atr->setAllowDelete(true);
$atr->setEditTitle('Amenity');
$atr->setProfileLabelWidth('100');
$atr->setrtEmptyMessage('No amenities were found.');
$atr->setBreadcrumbs(true);
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');
$atr->setUseATSubTableClass(true);

$hasTabbar = array(
    'rms' => array('name'=>'Rooms', 'function'=>'showRooms', 'elementID'=>'showRooms', 'width'=>''),
);
$tabbarCallback = 'tabbarCallbackamen';
$tabbarBodyID = 'tab_body_amen';
$tabbarUseAjax = false;
$atr->setTabbar($hasTabbar, $tabbarUseAjax, $tabbarBodyID, $tabbarCallback);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'amenityID', 'heading'=>'Action', 'type'=>'link:amenities.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'amenity', 'heading'=>'Amenity', 'type'=>'s', 'width'=>'2', 'format'=>'', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'description', 'heading'=>'Description', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'height'=>'2', 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'sortOrder', 'heading'=>'Sort Order', 'type'=>'i', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true,));
$rt1->addColumn(array('field'=>'price', 'heading'=>'Price', 'type'=>'i', 'width'=>'.8', 'step'=>'.01', 'format'=>'currency', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'pricePer', 'heading'=>'Price Per', 'type'=>'s', 'width'=>'.8', 'format'=>array('Night','Stay'), 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'Night'));
$rt1->addColumn(array('field'=>'isAvailable', 'heading'=>'Available', 'type'=>'b', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>true));
$rt1->addColumn(array('field'=>'isOptional', 'heading'=>'Is Optional', 'type'=>'b', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'createdOn', 'heading'=>'Created On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

$atr->process();

?>
<script>
    // scripts visible to showNotes function
    // set globals
    var DB = "<?=$db?>";
    var curId;

    function tabbarCallbackamen(tabID, currentTab, currTabID) {
        curId = $("#" + currTabID).attr("data-param0");
        if (currTabID === 'rms') {
            $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " "+rmamen.settings.subTitle);
            atst_rms.tabbarCallback(curId);
        }
    }
    //
    // $(document).ready(function () {
    //     $(window).resize(function () {
    //         var frame = $('#amenitiesView iframe', window.parent.document);
    //         var height = $(this).height();
    //         frame.height(height + 15);
    //     });
    // });

</script>
<?php

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


function showRooms($id)
{
    global $db;

    $table = 'roomAmenities';
    $keyField = 'roomAmenityID';
    $keyFieldType = 'i';
    $nameModifier = 'rms'; // should be same as table and tab
    $orderBy = array('sortOrder ASC','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field' => 'roomAmenityID', 'heading'=>'Action', 'type'=>'callback:selectRow_rms', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Amenity', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `Amenity` as item FROM `amenities` ORDER BY `sortOrder`',
                          'format'=>'', 'useKeys'=>true, 'profileOrder'=>1, 'width' => 1.2, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true,  'show' => true, 'required'=>true));
    $rt2->addColumn(array('field' => 'roomID', 'heading' => 'Room', 'type' => 'i', 'lookup'=>'SELECT `roomID` as id, `roomName` as item FROM `rooms` ORDER BY `roomName`',
                          'width' => 2, 'profileOrder'=>2, 'search' => true, 'edit' => true, 'show' => true, 'required'=>true));

    $mres = new ATSubTable($db, $rt2, array('list','edit','add'), $table, $keyField, $keyFieldType, $nameModifier, 'amen');

    $mres->setAllowAll(true);
    $mres->setAllowDelete(true);
    $mres->setEditTitle('Rooms');
    $mres->setSaveAndAddNew(true);
    $mres->setLinkField('amenityID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No rooms have been assigned this Amenity.');

    $mres->process();

}
