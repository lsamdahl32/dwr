<?php
/**
 * bookings.php
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
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/classes/PLManager.php');

if (!isset($_SESSION['is_logged_in']) or $_SESSION['is_logged_in'] == false) {
    header('location: login.php');
}

$table = 'bookings';
$keyField = 'bookingID';
$keyFieldType = 'i';
$orderBy = array('checkIn DESC');// default sort order

$db = 'plm';

if (isset($_POST['process'])) {
    if ($_POST['process'] == 'getGuestDetails') {
        getGuestDetails();
    } elseif ($_POST['process'] == 'getAvailableAmenities') {
        getAvailableAmenities();
    } elseif ($_POST['process'] == 'addAmenity') {
        addAmenity();
    } elseif ($_POST['process'] == 'deleteAmenity') {
        deleteAmenity();
    } elseif ($_POST['process'] == 'makePayment') {
        makePayment();
    }
    exit;
}

$rt1 = new ReportTable($db, $table, $keyField, 'boo');

$rt1->setQryOrderBy($orderBy[0]); // default sort order

$atr = new ATReports($db, $rt1, array('list','profile','edit','add'), $table, $keyField, 'i', false);

$atr->setLimit(25); // set this to specify how many results initially to view on a page
$atr->setDoInitialSearch(true);
$atr->setAllowAll(false); // whether or not to show "All" in the results per page - set to false for very large result sets

$atr->setTitle('Bookings');
$atr->setAllowDelete(true);
$atr->setEditTitle('Booking');
$atr->setProfileLabelWidth('100');
$atr->setAdditionalHeaders('<script src="' . PLMSite . 'admin/js/plm_admin.js"></script>');
$atr->setrtEmptyMessage('No bookings were found.');
$atr->setBreadcrumbs(true);
$atr->setSubTitleFields(array('Guest','-','Room')); // using Component which is heading will cause the lookup to occur from the column_array 3/17/2020
$atr->setUseATSubTableClass(true);

$hasTabbar = array(
    'gue' => array('name'=>'Guest', 'function'=>'showGuest', 'elementID'=>'showGuest', 'width'=>''),
    'amen' => array('name'=>'Amenities', 'function'=>'showAmenities', 'elementID'=>'showAmenities', 'width'=>''),
);
$tabbarCallback = 'tabbarCallbackboo';
$tabbarBodyID = 'tab_body_boo';
$tabbarUseAjax = false;
$atr->setTabbar($hasTabbar, $tabbarUseAjax, $tabbarBodyID, $tabbarCallback);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'bookingID', 'heading'=>'Action', 'type'=>'link:guests.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'checkIn', 'heading'=>'Check In', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'checkOut', 'heading'=>'Check Out', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>2, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'roomID', 'heading'=>'Room', 'type'=>'i', 'lookup'=> 'SELECT `roomID` AS id, `roomName` AS item FROM `rooms` WHERE 1', 'profile'=>true, 'profileOrder'=>3, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>true, 'showEdit'=>true, 'show'=>true, 'required'=>true ));
$rt1->addColumn(array('field'=>'guestID', 'heading'=>'Guest', 'type'=>'i', 'lookup'=>'SELECT `guestID` AS id, concat(firstName," ", lastName) AS item FROM `guests` WHERE 1 ',
                      'width'=>'2.2', 'profile'=>true, 'profileOrder'=>4, 'saveAdd'=>true, 'search'=>false, 'sort'=>true, 'edit'=>true, 'editadd'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'bookingStatusID', 'heading'=>'Status', 'type'=>'i', 'width'=>'1', 'lookup'=> 'SELECT `bookingStatusID` AS id, `status` AS item FROM `bookingStatus` WHERE 1', 'default'=>1,'format'=>'', 'profile'=>true, 'profileOrder'=>5, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'bookedBy', 'heading'=>'Booked By', 'type'=>'s', 'width'=>'1', 'format'=>array('Website','Phone','Email','Text','Other'), 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'totalPrice', 'heading'=>'Total Price', 'type'=>'s', 'width'=>'1', 'format'=>'currency', 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'comments', 'heading'=>'Comments', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'paymentStatusID', 'heading'=>'Pmt Status', 'type'=>'i', 'width'=>'1', 'lookup'=> 'SELECT `paymentStatusID` AS id, `status` AS item FROM `paymentStatus` WHERE 1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>2, 'required'=>true));
$rt1->addColumn(array('field'=>'paidBy', 'heading'=>'Paid By', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'txnID', 'heading'=>'Trans ID', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
//$rt1->addColumn(array('field'=>'ipAddress', 'heading'=>'IP', 'type'=>'s', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'checkInTime', 'heading'=>'Checked In', 'type'=>'dt', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>11, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'checkOUtTime', 'heading'=>'Checked Out', 'type'=>'dt', 'width'=>'1.5', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>12, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>13, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'bookedOn', 'heading'=>'Booked On', 'type'=>'d', 'width'=>'1.5', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>false, 'edit'=>false, 'sort'=>true, 'show'=>true));

$atr->process();

?>
<script>
    // set globals
    var DB = "<?=$db?>";
    var curId;
    var theDate = '';

    $( document ).ready(function () {
        $("#editRecordboo").after('<button id="makePayment" type="button" class="buttonBar" title="Edit this record\'s details."><i class="bi-credit-card-2-front"></i>&nbsp;Make Payment</button>');
        
        $("#makePayment").on('click', function () {
            $.post('bookings.php', {
                process: 'makePayment',
                bookingID: curId,
            }, function (result) {
                if (result === 'Success') {
                } else {
                    jqAlert(result);
                }
            });
        });

        $("#newAmenities").on('change', function () {
            // add optional amenity to this booking
            $.post('bookings.php', {
                process: 'addAmenity',
                bookingID: curId,
                amenityID: this.value
            }, function (result) {
                if (result === 'Success') {
                    atst_amen.tabbarCallback(curId);
                    // rtamen.setLinkID(curId);
                    // rtamen.refresh();
                    // rtamen.setResizable();
                    getAvailableAmenities();
                } else {
                    jqAlert(result);
                }
            });

        })

        $('#checkIn_booe').on('change', function () {
            alert("Here");
        });

    });

    function tabbarCallbackboo(tabID, currentTab, currTabID) {
        curId = $("#" + currTabID).attr("data-param0");
        if (currTabID === 'gue') {
            $.post('bookings.php', {
                process: 'getGuestDetails',
                curId: curId
            }, function (result) {
                // console.log(result);
                let obj = JSON.parse(result);
                if (Object.keys(obj).length > 0) {
                    // console.log(obj);
                    Object.keys(obj).forEach(function (key) {
                        // show any error messages
                        $("#" + key).html(obj[key]);
                    });
                }
            });

        } else if (currTabID === 'amen') {
            // refresh the grid
            atst_amen.tabbarCallback(curId);
            // rtamen.setLinkID(curId);
            // rtamen.refresh();
            // rtamen.setResizable();
            getAvailableAmenities();
        }
    }

    function getAvailableAmenities() {
        // refresh the available amenities dropdown
        $.post('bookings.php', {
            process: 'getAvailableAmenities',
            curId: curId
        }, function (result) {
            $("#newAmenities").html(result);
        });
    }

    function deleteAmenities(id) {
        $.post('bookings.php', {
            process: 'deleteAmenity',
            bookingAmenityID: id
        }, function (result) {
            if (result === 'Success') {
                atst_amen.tabbarCallback(curId);
                // rtamen.setLinkID(curId);
                // rtamen.refresh();
                // rtamen.setResizable();
                getAvailableAmenities();
            }
        });
    }

    function modeChangedgue(mode, id) {
        if (mode === 'view') { // refresh the grid
            rmgue.setParID(curId);
            rmgue.refresh();
        }
    }

    function searchBookingID(id) {
        // force a search for this bookingID
        $("#myForm #searchBtn").append('<input type="hidden" name="bookingID" value="' + id + '"/>').trigger('click');
    }

    // create a new booking from the calendar widget
    function newBooking(date) {
        rmRefreshCallback = 'insertDate';
        theDate = date;
        atr.addRecord();
    }

    // insert the check in date
    function insertDate() {
        $('#checkIn_booe').val(theDate);
        rmRefreshCallback = '';
        theDate = '';
    }
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
function customValidation(&$dbVars, bool $update=false, $id=0, array $column_array = array()):array
{
    global $db;
    $plm = new PLManager();

    $arrayOfErrors = array();
    // adding a past booking in admin module is OK, but room must be available for all dates in booking.
    // check for correct booking dates
    if (date('Y-m-d H:i:s', strtotime($dbVars['checkOut']['data'])) <= date('Y-m-d H:i:s', strtotime($dbVars['checkIn']['data']))) {
        $arrayOfErrors['checkOut'] = "Check Out must be later than check in.";
    }

    //    // check for overlapping dates with other bookings for same room
    if ($plm->checkRoomAvailability($dbVars['checkIn']['data'], $dbVars['checkOut']['data'], $dbVars['roomID']['data'])) {
        // is ok
    } else {
        $arrayOfErrors['checkIn'] = "This room is not available for all of these dates.";
    }
    return $arrayOfErrors;
}

function showGuest($id)
{

    // show raw html
    ?>
    <h2>Guest Details</h2>
    <div class="profile-flex-cols">
        <div class="profile-left-col">
            <div class="form_rows">
                <div class="form_label">
                    Name:
                </div>
                <div class="form_cell" id="guestName">
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    Title:
                </div>
                <div class="form_cell" id="guestTitle">
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    Address:
                </div>
                <div class="form_cell" id="guestAddress">
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    Phone:
                </div>
                <div class="form_cell" id="guestPhone">
                </div>
            </div>
        </div>
        <div class="profile-right-col">
            <div class="form_rows">
                <div class="form_label">
                    Email:
                </div>
                <div class="form_cell" id="guestEmail">
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    Contact By:
                </div>
                <div class="form_cell" id="guestContactBy">
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    Comments:
                </div>
                <div class="form_cell" id="guestComments">
                </div>
            </div>
        </div>
    </div>


    <?php

}

function getGuestDetails()
{
    global $db;

    $id = $_POST['curId'];
    if ($id > 0) {
        $dbe = new DBEngine($db, false);

        $pq_query = 'SELECT concat(`firstName`," ",`lastName`) as guestName,
                            `title` as guestTitle,
                            concat(`street`,"<br>",`city`,", ",`state`," ",`zip`,"<br>",`country`) as guestAddress,
                            `phone` as guestPhone,
                            `email` as guestEmail,
                            `contactBy` as guestContactBy,
                            g.`comments` as guestComments                            
                        FROM `guests` g INNER JOIN `bookings` b ON b.`guestID` = g.`guestID` 
                        WHERE b.`bookingID` = ? ';
        $dbe->setBindtypes('i');
        $dbe->setBindvalues(array($id));
        $rows = $dbe->execute_query($pq_query);

        if ($rows) {
            echo json_encode($rows[0]);
        } else {
            echo json_encode(array('guestName'=>'', 'guestTitle'=>'', 'guestAddress'=>'', 'guestPhone'=>'', 'guestEmail'=>'', 'guestContactBy'=>'', 'guestComments'=>'',));
        }
        $dbe->close();
    }

}

function getAvailableAmenities()
{
    global $db;

    $dbe = new DBEngine($db);
    $pq_query = 'SELECT a.* FROM `amenities` a LEFT JOIN `bookingAmenities` b ON b.`amenityID` = a.`amenityID` WHERE a.`isOptional` = ? AND b.`amenityID` IS NULL ORDER BY a.`sortOrder` ';
    $dbe->setBindtypes('i');
    $dbe->setBindvalues(array(1));
    $amens = $dbe->execute_query($pq_query);
    $dbe->close();
    echo '<option value="0">None</option>';
    if ($amens) {
        foreach ($amens as $item) {
            echo '<option value="' . $item['amenityID'] . '">' . $item['amenity'] . '</option>';
        }
    }
}

function addAmenity()
{
    global $db;

    $amenityID = $_POST['amenityID'];
    $bookingID = $_POST['bookingID'];

    if (intval($amenityID) > 0 and intval($bookingID) > 0) {
        $dbe = new DBEngine($db);

        $row = $dbe->getRowWhere('amenities', 'amenityID', $amenityID);
        if ($row) {
            $data = array(
                'amenityID' => $amenityID,
                'bookingID' => $bookingID,
                'price'     => $row['price'],
                'sortOrder' => $row['sortOrder'],
            );
            $result = $dbe->insertRow('bookingAmenities', $data);
            $dbe->close();
            if ($result > 0) {
                echo 'Success';
                return;
            }
        }
    }
    echo 'An error has occurred.';
}

function deleteAmenity()
{
    global $db;

    $bookingAmenityID = $_POST['bookingAmenityID'];
    if (intval($bookingAmenityID) > 0) {
        $dbe = new DBEngine($db);

        $result = $dbe->deleteRow('bookingAmenities', 'bookingAmenityID', $bookingAmenityID);
        if ($result > 0) {
            echo 'Success';
            return;
        }
    }
    echo 'An error has occurred. ' . $result;
}

function showAmenities($id)
{
    global $db;

    $table = 'bookingAmenities';
    $keyField = 'bookingAmenityID';
    $keyFieldType = 'i';
    $nameModifier = 'amen'; // should be same as table and tab
    $orderBy = array('sortOrder','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field' => 'bookingAmenityID', 'heading'=>'Action', 'type'=>'callback:deleteAmenities', 'format'=>'Delete', 'altIcon'=>'<i class="bi-x-circle smaller_icon" style="color: darkred;"></i>', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'required'=>false));
    $rt2->addColumn(array('field' => 'bookingID', 'heading' => 'Booking', 'type' => 'i', 'lookup'=>'SELECT b.`bookingID` as id, concat(b.`checkIn`,"-",g.`lastName`) as item FROM `bookings` b INNER JOIN `guests` g ON g.`guestID` = b.`guestID` ORDER BY `checkIn` DESC',
                          'width' => 2, 'profileOrder'=>0, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'editAdd'=>true, 'show' => true, 'required'=>true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Amenity', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `Amenity` as item FROM `amenities` ORDER BY `sortOrder`','format'=>'', 'useKeys'=>true, 'profileOrder'=>1, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Price', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `price` as item FROM `amenities` ORDER BY `sortOrder`','format'=>'currency', 'useKeys'=>true, 'profileOrder'=>1, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));

    $mres = new ATSubTable($db, $rt2, array('list'), $table, $keyField, $keyFieldType, $nameModifier);

    $mres->setAllowAll(true);
    $mres->setLinkField('bookingID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No amenities have been added for this booking.');

    $mres->process();

    ?>
    <script>
        $(document).ready(function () {
            $("#reportMode_amen div.outputLimitSelector").after('<div class="good_color">Add Amenity&nbsp;<select id="newAmenities" class="good_color" title="Add New Amenity"><option value="0">None</option></select></div>');
        });
    </script>
    <?php

}

function makePayment()
{
    require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/authorizenet/authorizenetAPI.php');

    $_SESSION['amountPaid'] = 100;
    $_POST['cardNum'] = '4111111111111111';
    $_POST['expDate'] = '09-2024';
    $_POST['cvv'] = '1234';
    $_SESSION['booking']['bookingID'] = 20;
    $_SESSION['guest'] = array('firstName' => 'Lee','lastName'=>'Samdahl','company'=>'','street'=>'1234 5th St.','city'=>'Tucson','state'=>'AZ','zip'=>'85748','country'=>'USA');

    $anet = new authorizenetAPI();

    $response = $anet->chargeCreditCard(100,'4111111111111111','09-2024','1234',20,'room',$_SESSION['guest']);
//
//    if ($response != null) {
//        // Check to see if the API request was successfully received and acted upon
//        if ($response->getMessages()->getResultCode() == 'Ok') {
//            // Since the API request was successful, look for a transaction response
//            // and parse it to display the results of authorizing the card
//            $tresponse = $response->getTransactionResponse();
//            return $tresponse->getTransId();
//        } else {
//            // todo error
//        }
//    } else {
//        // todo error
//    }
//    print_r($response); exit;

}