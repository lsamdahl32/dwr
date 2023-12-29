<?php
/**
 * guests.php
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

$table = 'guests';
$keyField = 'guestID';
$keyFieldType = 'i';
$orderBy = array('lastName ASC, firstName ASC');// default sort order

$db = 'plm';

$rt1 = new ReportTable($db, $table, $keyField, 'gue');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list','profile','edit','add'), $table, $keyField, 'i', false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(false);  // whether or not to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Guests');
$atr->setAllowDelete(true);
$atr->setEditTitle('Guest');
$atr->setProfileLabelWidth('100');
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');
$atr->setrtEmptyMessage('No guests were found.');
$atr->setBreadcrumbs(true);
$atr->setSubTitleFields(array('firstName','lastName')); // using Component which is heading will cause the lookup to occur from the column_array 3/17/2020
$atr->setUseATSubTableClass(true);
$atr->setWhere(array('guestID <> 1')); // do not show the special "Admin Blackout" guest.

$hasTabbar = array(
    'boo' => array('name'=>'Bookings', 'function'=>'showBookings', 'elementID'=>'showBookings', 'width'=>''),
);
$tabbarCallback = 'tabbarCallbackgue';
$tabbarBodyID = 'tab_body_gue';
$tabbarUseAjax = false;
$atr->setTabbar($hasTabbar, $tabbarUseAjax, $tabbarBodyID, $tabbarCallback);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'guestID', 'heading'=>'Action', 'type'=>'link:guests.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'firstName', 'heading'=>'First Name', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'lastName', 'heading'=>'Last Name', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>2, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'title', 'heading'=>'Title', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true,));
$rt1->addColumn(array('field'=>'street', 'heading'=>'Street', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'city', 'heading'=>'City', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'state', 'heading'=>'State', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'zip', 'heading'=>'Zip', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'country', 'heading'=>'Country', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
$rt1->addColumn(array('field'=>'phone', 'heading'=>'Phone', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'email', 'heading'=>'Email', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'comments', 'heading'=>'Comments', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'profile'=>true, 'profileOrder'=>13, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'createdOn', 'heading'=>'Created On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>15, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'contactBy', 'heading'=>'Contact By', 'type'=>'s', 'width'=>'1.5', 'format'=>array('Phone','Email','Text'), 'profile'=>true, 'profileOrder'=>11, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true));

$atr->process();

?>
<script>
    // set globals
    var DB = "<?=$db?>";
    var curId;

    function tabbarCallbackgue(tabID, currentTab, currTabID) {
        curId = $("#" + currTabID).attr("data-param0");
        $("#breadcrumbs").html(' <?=BI_CARET_RIGHT?>' + " "+rmgue.settings.subTitle);
        if (currTabID === 'boo') {
            atst_boo.tabbarCallback(curId);
        }
    }
    //
    // $(document).ready(function () {
    //     $(window).resize(function () {
    //         var frame = $('#guestsView iframe', window.parent.document);
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

    $rt2->addColumn(array('field'=>'bookingID', 'heading'=>'Action', 'type'=>'callback:selectRow_boo', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field'=>'guestID', 'heading'=>'Guest', 'type'=>'i', 'lookup'=> 'SELECT `guestID` AS id, concat(`lastName`,", ",`firstName`) AS item FROM `guests` WHERE 1', 'profileOrder'=>1, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'roomID', 'heading'=>'Room', 'type'=>'i', 'lookup'=> 'SELECT `roomID` AS id, `roomName` AS item FROM `rooms` WHERE 1', 'profileOrder'=>2, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>true, 'showEdit'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'checkIn', 'heading'=>'Check In', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>3, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'checkOut', 'heading'=>'Check Out', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>4, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'bookingStatusID', 'heading'=>'Status', 'type'=>'i', 'lookup'=> 'SELECT `bookingStatusID` AS id, `status` AS item FROM `bookingStatus` WHERE 1', 'profileOrder'=>5, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'bookedBy', 'heading'=>'Booked By', 'type'=>'s', 'width'=>'1', 'format'=>array('Website','Phone','Email','Text'), 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'totalPrice', 'heading'=>'Total Price', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>'USA', 'required'=>true));
    $rt2->addColumn(array('field'=>'paymentStatusID', 'heading'=>'Pmt Status', 'type'=>'i', 'lookup'=> 'SELECT `paymentStatusID` AS id, `status` AS item FROM `paymentStatus` WHERE 1', 'profileOrder'=>8, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>false, 'showEdit'=>true, 'editAdd'=>true, 'show'=>true, 'required'=>true ));
    $rt2->addColumn(array('field'=>'paidBy', 'heading'=>'Paid By', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'bookedOn', 'heading'=>'Booked On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>11, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

    $mres = new ATSubTable($db, $rt2, array('list','edit'), $table, $keyField, $keyFieldType, $nameModifier, 'gue');

    $mres->setAllowAll(true);
    $mres->setAllowDelete(true);
    $mres->setEditTitle('Bookings');
    $mres->setLinkField('guestID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No bookings have been added for this guest.');

    $mres->process();

}
