<?php
/**
 * rooms.php
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

error_reporting(E_ALL);

$table = 'rooms';
$keyField = 'roomID';
$keyFieldType = 'i';
$orderBy = array('roomName ASC');// default sort order

$db = 'plm';

$rt1 = new ReportTable($db, $table, $keyField, 'rms');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list','profile','edit','add'), $table, $keyField, $keyFieldType, false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(true);  // whether or not to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Rooms');
$atr->setAllowDelete(true);
$atr->setEditTitle('Room');
$atr->setProfileLabelWidth('100');
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');
$atr->setRtEmptyMessage('No rooms were found.');
$atr->setBreadcrumbs(true);
$atr->setSubTitleFields(array('roomName')); // using Component which is heading will cause the lookup to occur from the column_array 3/17/2020
$atr->setUseATSubTableClass(true);

$hasTabbar = array(
    'boo' => array('name'=>'Bookings', 'function'=>'showBookings', 'elementID'=>'showBookings', 'width'=>''),
    'pri' => array('name'=>'Pricing', 'function'=>'showPricing', 'elementID'=>'showPricing', 'width'=>''),
    'amen' => array('name'=>'Amenities', 'function'=>'showAmenities', 'elementID'=>'showAmenities', 'width'=>''),
    'img' => array('name'=>'Images', 'function'=>'showImages', 'elementID'=>'showImages', 'width'=>''),
);
$tabbarCallback = 'tabbarCallbackrms';
$tabbarBodyID = 'tab_body_rms';
$tabbarUseAjax = false;
$atr->setTabbar($hasTabbar, $tabbarUseAjax, $tabbarBodyID, $tabbarCallback);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'roomID', 'heading'=>'Action', 'type'=>'link:rooms.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'roomName', 'heading'=>'Room Name', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'roomTypeID', 'heading'=>'Room Type', 'type'=>'i', 'width'=>'1', 'format'=>'', 'lookup'=> 'SELECT `roomTypeID` AS id, `roomType` AS item FROM `roomTypes` WHERE 1', 'profile'=>true, 'profileOrder'=>2, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'maxPersons', 'heading'=>'Occupancy', 'type'=>'i', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true,));
$rt1->addColumn(array('field'=>'minNights', 'heading'=>'Minimum Nights', 'type'=>'i', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'details', 'heading'=>'Details', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'height'=>'2', 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'size', 'heading'=>'Size', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'beds', 'heading'=>'Beds', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'isAvailable', 'heading'=>'Available', 'type'=>'b', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'sortOrder', 'heading'=>'Sort Order', 'type'=>'i', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true,));
$rt1->addColumn(array('field'=>'createdOn', 'heading'=>'Created On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>15, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

$atr->process();

?>
<script>
    // set globals
    var DB = "<?=$db?>";
    var curId;

    function tabbarCallbackrms(tabID, currentTab, currTabID) {
        curId = $("#" + currTabID).attr("data-param0");
        $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " "+rmrms.settings.subTitle);
        if (currTabID === 'pri') {
            atst_pri.tabbarCallback(curId);
        } else if (currTabID === 'boo') {
            atst_boo.tabbarCallback(curId);
            $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " "+rmrms.settings.subTitle);
        } else if (currTabID === 'amen') {
            atst_amen.tabbarCallback(curId);
        } else if (currTabID === 'img') {
            atst_img.tabbarCallback(curId);
        }
    }

    function editBooking(id) {
        // open bookings page
        parent.$("#Bookings").click();
        parent.$('#bookingsView > iframe')[0].contentWindow.searchBookingID(id);
    }

    // $(document).ready(function () {
    //     $(window).resize(function () {
    //         var frame = $('#roomsView iframe', window.parent.document);
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

function showBookings($id)
{
    global $db;

    $table = 'bookings';
    $keyField = 'bookingID';
    $keyFieldType = 'i';
    $nameModifier = 'boo'; // should be same as table and tab
    $orderBy = array('checkIn DESC','guestID','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field'=>'bookingID', 'heading'=>'Action', 'type'=>'callback:editBooking', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field'=>'guestID', 'heading'=>'Guest', 'type'=>'i', 'lookup'=> 'SELECT `guestID` AS id, concat(`lastName`,", ",`firstName`) AS item FROM `guests` WHERE 1', 'profileOrder'=>1, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'roomID', 'heading'=>'Room', 'type'=>'i', 'lookup'=> 'SELECT `roomID` AS id, `roomName` AS item FROM `rooms` WHERE 1', 'profileOrder'=>2, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'checkIn', 'heading'=>'Check In', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'checkOut', 'heading'=>'Check Out', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'bookingStatusID', 'heading'=>'Status', 'type'=>'i', 'lookup'=> 'SELECT `bookingStatusID` AS id, `status` AS item FROM `bookingStatus` WHERE 1', 'profileOrder'=>5, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'bookedBy', 'heading'=>'Booked By', 'type'=>'s', 'width'=>'1', 'format'=>array('Website','Phone','Email','Text'), 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'totalPrice', 'heading'=>'Total Price', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'paymentStatusID', 'heading'=>'Pmt Status', 'type'=>'i', 'lookup'=> 'SELECT `paymentStatusID` AS id, `status` AS item FROM `paymentStatus` WHERE 1', 'profileOrder'=>8, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'paidBy', 'heading'=>'Paid By', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'asOf', 'heading'=>'As Of', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
    $rt2->addColumn(array('field'=>'bookedOn', 'heading'=>'Booked On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>11, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

    $mres = new ATSubTable($db, $rt2, array('list'), $table, $keyField, $keyFieldType, $nameModifier);

    $mres->setAllowAll(true);
    $mres->setLinkField('roomID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No bookings have been added for this room.');

    $mres->process();

}

function showPricing($id)
{
    global $db;

    $table = 'pricing';
    $keyField = 'pricingID';
    $keyFieldType = 'i';
    $nameModifier = 'pri'; // should be same as table and tab
    $orderBy = array('asOf DESC','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field'=>'pricingID', 'heading'=>'Action', 'type'=>'callback:selectRow_pri', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field'=>'roomID', 'heading'=>'Room', 'type'=>'i', 'lookup'=> 'SELECT `roomID` AS id, `roomName` AS item FROM `rooms` WHERE 1', 'profile'=>true, 'profileOrder'=>1, 'width'=>'1',
                          'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'show'=>true, 'showAdd'=>true, 'required'=>true, 'default'=>'parentID'));
    $rt2->addColumn(array('field'=>'amount', 'heading'=>'Price', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'profile'=>true, 'profileOrder'=>8, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'asOf', 'heading'=>'As Of', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>14, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
    $rt2->addColumn(array('field'=>'createdOn', 'heading'=>'Created On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

    $mres = new ATSubTable($db, $rt2, array('list','edit','add'), $table, $keyField, $keyFieldType, $nameModifier, 'rms');

    $mres->setAllowAll(true);
    $mres->setAllowDelete(true);
    $mres->setEditTitle('Pricing');
    $mres->setLinkField('roomID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No pricing has been set for this Room.');

    $mres->process();

}

function showAmenities($id)
{
    global $db;

    $table = 'roomAmenities';
    $keyField = 'roomAmenityID';
    $keyFieldType = 'i';
    $nameModifier = 'amen'; // should be same as table and tab
    $orderBy = array('sortOrder ASC','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field' => 'roomAmenityID', 'heading'=>'Action', 'type'=>'callback:selectRow_amen', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field' => 'roomID', 'heading' => 'Room', 'type' => 'i', 'lookup'=>'SELECT `roomID` as id, `roomName` as item FROM `rooms` ORDER BY `roomName`',
                          'width' => 2, 'profileOrder'=>0, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'showAdd'=>true, 'show' => true, 'required'=>true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Amenity', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `Amenity` as item FROM `amenities` ORDER BY `sortOrder`','format'=>'',
                          'useKeys'=>true, 'profileOrder'=>1, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));

    $mres = new ATSubTable($db, $rt2, array('list','edit','add'), $table, $keyField, $keyFieldType, $nameModifier, 'rms');

    $mres->setAllowAll(true);
    $mres->setAllowDelete(true);
    $mres->setEditTitle('Amenity');
    $mres->setSaveAndAddNew(true);
    $mres->setLinkField('roomID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No amenities have been added to this Room.');

    $mres->process();

}

function showImages($id)
{
    global $db;

    $table = 'roomImages';
    $keyField = 'roomImageID';
    $keyFieldType = 'i';
    $nameModifier = 'img'; // should be same as table and tab
    $orderBy = array('sortOrder ASC','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field' => 'roomImageID', 'heading'=>'Action', 'type'=>'callback:selectRow_img', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field' => 'roomID', 'heading' => 'Room', 'type' => 'i', 'lookup'=>'SELECT `roomID` as id, `roomName` as item FROM `rooms` ORDER BY `roomName`',
                          'width' => 1.5, 'profileOrder'=>0, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'showAdd'=>true, 'show' => false, 'required'=>true));
    $rt2->addColumn(array('field' => 'altText', 'heading' => 'Alt Text', 'type' => 's', 'format'=>'', 'profileOrder'=>1, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'image', 'heading' => 'Image Filename', 'type' => 's', 'format'=>'', 'imagePreview'=>true, 'profileOrder'=>2, 'width' => 2, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'sortOrder', 'heading' => 'Sort Order', 'type' => 'i', 'format'=>'', 'profileOrder'=>3, 'width' => .8, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'online', 'heading' => 'Is Online', 'type' => 'b', 'format'=>'', 'profileOrder'=>4, 'width' => .8, 'search' => true, 'edit' => true, 'show' => true));

    $mres = new ATSubTable($db, $rt2, array('list','edit','add'), $table, $keyField, $keyFieldType, $nameModifier, 'rms');

    $mres->setAllowAll(true);
    $mres->setAllowDelete(true);
    $mres->setEditTitle('Images');
    $mres->setSaveAndAddNew(true);
    $mres->setLinkField('roomID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No amenities have been added to this Room.');
    $mres->setImagePath(IMAGEPATH);

    $mres->process();

}