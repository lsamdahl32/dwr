<?php
/**
 * bookings.php
 *
 * @author Lee Samdahl
 *
 * @created 4/4/23
 */

//  ini_set('display_errors', 1);
//  ini_set('display_startup_errors', 1);
//  error_reporting(E_ALL);
 
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
        getAvailableAmenities($_POST['curId']);
    } elseif ($_POST['process'] == 'addAmenity') {
        addAmenity();
    } elseif ($_POST['process'] == 'deleteAmenity') {
        deleteAmenity();
    } elseif ($_POST['process'] == 'getBalanceDue') {
        echo getBalanceDue($_POST['bookingID']);
    } elseif ($_POST['process'] == 'makePayment') {
        echo json_encode(makePayment());
    } elseif ($_POST['process'] == 'recalcTotals') {
        recalculateBookingTotals($_POST['bookingID']);
        echo 'Success';
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
$atr->setUpdateCallback('updateBooking');

$hasTabbar = array(
    'gue' => array('name'=>'Guest', 'function'=>'showGuest', 'elementID'=>'showGuest', 'width'=>''),
    'amen' => array('name'=>'Amenities', 'function'=>'showAmenities', 'elementID'=>'showAmenities', 'width'=>''),
    'tot' => array('name'=>'Totals', 'function'=>'showBookingTotals', 'elementID'=>'showBookingTotals', 'width'=>''),
    'pay' => array('name'=>'Payments', 'function'=>'showPayments', 'elementID'=>'showPayments', 'width'=>''),
);
$tabbarCallback = 'tabbarCallbackboo';
$tabbarBodyID = 'tab_body_boo';
$tabbarUseAjax = false;
$atr->setTabbar($hasTabbar, $tabbarUseAjax, $tabbarBodyID, $tabbarCallback);

// column array for the report table, width is in inches
$rt1->addColumn(array('field'=>'bookingID', 'heading'=>'Action', 'type'=>'link:guests.php', 'format'=>'View', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true, 'forceShow'=>true, 'required'=>false));
$rt1->addColumn(array('field'=>'checkIn', 'heading'=>'Check In', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>1, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'checkOut', 'heading'=>'Check Out', 'type'=>'d', 'width'=>'1', 'format'=>'m/d/Y', 'profile'=>true, 'profileOrder'=>2, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'nights', 'heading'=>'Nights', 'type'=>'i', 'width'=>'.8', 'format'=>'', 'profile'=>true, 'profileOrder'=>3, 'search'=>false, 'edit'=>false, 'showEdit' => true, 'sort'=>false, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'roomID', 'heading'=>'Room', 'type'=>'i', 'lookup'=> 'SELECT `roomID` AS id, `roomName` AS item FROM `rooms` WHERE 1', 'profile'=>true, 'profileOrder'=>3, 'width'=>'1', 'search'=>true, 'sort'=>true, 'edit'=>true, 'showEdit'=>true, 'show'=>true, 'required'=>true ));
$rt1->addColumn(array('field'=>'guestID', 'heading'=>'Guest', 'type'=>'i', 'lookup'=>'SELECT `guestID` AS id, concat(firstName," ", lastName) AS item FROM `guests` WHERE 1 ',
                      'width'=>'2.2', 'profile'=>true, 'profileOrder'=>4, 'saveAdd'=>true, 'search'=>false, 'sort'=>true, 'edit'=>true, 'editadd'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'bookingStatusID', 'heading'=>'Status', 'type'=>'i', 'width'=>'1', 'lookup'=> 'SELECT `bookingStatusID` AS id, `status` AS item FROM `bookingStatus` WHERE 1', 'default'=>1,'format'=>'', 'profile'=>true, 'profileOrder'=>5, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'bookedBy', 'heading'=>'Booked By', 'type'=>'s', 'width'=>'1', 'format'=>array('Website','Phone','Email','Text','Other'), 'profile'=>true, 'profileOrder'=>5, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'comments', 'heading'=>'Comments', 'type'=>'t', 'width'=>'3', 'format'=>'', 'size'=>0, 'profile'=>true, 'profileOrder'=>6, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>false));
$rt1->addColumn(array('fieldGroup'=>'Account', 'field'=>'totalLodging', 'heading'=>'Total Lodging', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'min'=>0, 'step'=>.01, 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'edit'=>false, 'editadd'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('fieldGroup'=>'Account', 'field'=>'totalPrice', 'heading'=>'Total Price', 'type'=>'i', 'width'=>'1', 'format'=>'currency',  'min'=>0, 'step'=>.01, 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'edit'=>false, 'editadd'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('fieldGroup'=>'Account', 'field'=>'amountPaid', 'heading'=>'Amount Paid', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'min'=>0, 'step'=>.01, 'profile'=>true, 'profileOrder'=>11, 'search'=>true, 'edit'=>false, 'editadd'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('fieldGroup'=>'Account', 'field'=>'amountDue', 'heading'=>'Amount Due', 'type'=>'i', 'width'=>'1', 'format'=>'currency', 'min'=>0, 'step'=>.01, 'profile'=>true, 'profileOrder'=>12, 'search'=>true, 'edit'=>false, 'editadd'=>true, 'sort'=>true, 'show'=>true, 'required'=>true));
$rt1->addColumn(array('field'=>'checkInTime', 'heading'=>'Checked In', 'type'=>'dt', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>7, 'search'=>true, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'checkOUtTime', 'heading'=>'Checked Out', 'type'=>'dt', 'width'=>'1.5', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>8, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'modifiedOn', 'heading'=>'Modified On', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>13, 'search'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
$rt1->addColumn(array('field'=>'bookedOn', 'heading'=>'Booked On', 'type'=>'d', 'width'=>'1.5', 'format'=>'m/d/Y H:i:s', 'profile'=>true, 'profileOrder'=>14, 'search'=>false, 'edit'=>false, 'sort'=>true, 'show'=>true));

$atr->process();

?>
<div class="jqmWindow" id="makeAPayment" >
    <h2>Make a Payment</h2>
    <div>
        <div class="form_rows">
            <label for="paymentTypeCash" class="form_label">Type:</label>
            <div class="form_cell">
                <input type="radio" name="paymentType" id="paymentTypeCash" value="cash" />&nbsp;Cash<br>
                <input type="radio" name="paymentType" id="paymentTypeCheck" value="check" />&nbsp;Check<br>
                <input type="radio" name="paymentType" id="paymentTypeCredit" value="credit" checked />&nbsp;Credit<br>
            </div>
        </div>
        <div class="form_rows">
            <label for="amountPaid" class="form_label">Amount:</label>
            <div class="form_cell">$
                <input type="number" name="amountPaid" id="amountPaid" required min="<?=$balanceDue?>" max="<?=$total?>" step=".01" value="<?=number_format($balanceDue,2)?>" />
                <span class="form_error">*</span>
            </div>
        </div>
        <div id="checkData" style="display: none;">
            <div class="form_rows">
                <label for="checkNumber" class="form_label">Check Number:</label>
                <div class="form_cell">
                    <input type="text" name="checkNumber" id="checkNumber" value="<?= gls_esc_attr($_POST['checkNumber'])?>" required />
                    <span class="form_error">*</span>
                </div>
            </div>
        </div>
        <div id="creditData">
            <div class="form_rows">
                <label for="cardName" class="form_label">Cardholder Name:</label>
                <div class="form_cell">
                    <input type="text" name="cardName" id="cardName" value="<?= gls_esc_attr($_POST['cardName'])?>" required />
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <label for="cardNum" class="form_label">Card Number:</label>
                <div class="form_cell">
                    <input type="text" name="cardNum" id="cardNum" value="<?= gls_esc_attr($_POST['cardNum'])?>" required />
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <label for="expDate" class="form_label">Expiration Date:</label>
                <div class="form_cell">
                    <!-- <input type="text" name="expDate" id="cardNum" expDate /> -->
                    <select name='expireMM' id='expireMM'>
                        <option value=''>Month</option>
                        <option value='01' <?=($_POST['expireMM'] == '01') ? 'selected' : ''?>>January</option>
                        <option value='02' <?=($_POST['expireMM'] == '02') ? 'selected' : ''?>>February</option>
                        <option value='03' <?=($_POST['expireMM'] == '03') ? 'selected' : ''?>>March</option>
                        <option value='04' <?=($_POST['expireMM'] == '04') ? 'selected' : ''?>>April</option>
                        <option value='05' <?=($_POST['expireMM'] == '05') ? 'selected' : ''?>>May</option>
                        <option value='06' <?=($_POST['expireMM'] == '06') ? 'selected' : ''?>>June</option>
                        <option value='07' <?=($_POST['expireMM'] == '07') ? 'selected' : ''?>>July</option>
                        <option value='08' <?=($_POST['expireMM'] == '08') ? 'selected' : ''?>>August</option>
                        <option value='09' <?=($_POST['expireMM'] == '09') ? 'selected' : ''?>>September</option>
                        <option value='10' <?=($_POST['expireMM'] == '10') ? 'selected' : ''?>>October</option>
                        <option value='11' <?=($_POST['expireMM'] == '11') ? 'selected' : ''?>>November</option>
                        <option value='12' <?=($_POST['expireMM'] == '12') ? 'selected' : ''?>>December</option>
                    </select> 
                    <select name='expireYY' id='expireYY'>
                        <option value=''>Year</option>
                        <?php 
                        for ($i = date('Y'); $i < date('Y') + 6; $i++) {                        
                            echo '<option value="' . $i . '" ';
                            if ($_POST['expireYY'] == $i) echo ' selected ';
                            echo '>' . $i . '</option>';
                        }
                        ?>
                    </select> 
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <label for="cvv" class="form_label">CVV Number:</label>
                <div class="form_cell">
                    <input type="text" name="cvv" id="cvv" value="<?= gls_esc_attr($_POST['cvv'])?>" />
                    <span class="form_error">*</span>
                </div>
            </div>
        </div>
        <span class="form_error">* = Required</span>
    </div>
    <div style="display: flex; flex-flow: row wrap; justify-content: space-around;">
        <button type="button" class="buttonBar jqmClose" id="btnCancelPayment" style="color: red;">
            <i class="bi-x-circle"></i>&nbsp;
            Cancel
        </button>
        <button type="button" class="buttonBar jqmClose" id="btnSubmitPayment">
            <i class="bi-check2"></i>&nbsp;
            Make Payment
        </button>
    </div>

</div>
<script>
    // set globals
    var DB = "<?=$db?>";
    var curId;
    var theDate = '';

    $( document ).ready(function () {
        $("#editRecordboo").after('<button id="makePayment" type="button" class="buttonBar" title="Edit this record\'s details."><i class="bi-credit-card-2-front"></i>&nbsp;Make Payment</button>');
        
        $("#makePayment").on('click', function () {
            // need to use ajax to get the current balance due
            // and set the value and the max on the amount field
            // if no balance due, do not open the dialog
            $.post('bookings.php', {
                process: 'getBalanceDue',
                bookingID: curId
            },function(result) {
                if (result === '0.00') {
                    jqAlert('No balance due.');
                } else {
                    $("#amountPaid").val(result);
                    $("#amountPaid").attr('max', result);
                    $("#makeAPayment").jqmShow();
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
                    getAvailableAmenities();
                } else {
                    jqAlert(result);
                }
            });

        })

        $('#checkIn_booe').on('change', function () {
            alert("Here");
        });

        $("#recalcBookingTotals").on('click', function () {
            $.post('bookings.php', {
                process: 'recalcTotals',
                bookingID: curId
            }, function (result) {
                if (result === 'Success') {
                    atst_tot.tabbarCallback(curId);
                    // rtboo.refresh();
                    rmboo.refresh();   
                } else {
                    jqAlert(result);
                }
            });
        });

        $('input[name="paymentType"]').on('change', function() {
            switch ($('input[name="paymentType"]:checked').val()) {
                case 'cash':
                    $('#checkData').hide(200);
                    $('#creditData').hide(200);
                    break;
                case 'check':
                    $('#checkData').show(200);
                    $('#creditData').hide(200);
                    break;
                case 'credit':
                    $('#checkData').hide(200);
                    $('#creditData').show(200);
                    break;            
            }
        });

        $("#btnSubmitPayment").on('click', function () {
            $.post('bookings.php', {
                process: 'makePayment',
                bookingID: curId,
                amountPaid: $("#amountPaid").val(),
                paymentType: $('input[name="paymentType"]:checked').val(),
                checkNumber: $("#checkNumber").val(),
                cardName: $("#cardName").val(),
                cardNum: $("#cardNum").val(),
                expDate: $("#expireYY").val() + "-" + $("#expireMM").val(),
                cvv: $("#cvv").val()
            }, function (result) {
                console.log(result);
                var obj = JSON.parse(result);
                if (obj['result'] === 'Success') {
                    jqAlert('The payment was added.');
                    atst_pay.tabbarCallback(curId);
                    atst_tot.tabbarCallback(curId);
                    // rtboo.refresh();
                    rmboo.refresh();   
                } else {
                    jqAlert(obj['messages'][0]);
                }
            });
        });

    });

    // Make Payment dialog
    $('#makeAPayment').jqm({
        modal:  true,
        toTop:  true,
        onShow: function (hash) {
            hash.o.prependTo('body');
            hash.w.css('opacity', 1).fadeIn();
        },
        onHide: function (hash) {
            hash.w.fadeOut('2000', function () {
                hash.o.remove();
            });
        }
    });

    function updateBooking(obj) {
        $("#recalcBookingTotals").click();
    }

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
            
        } else if (currTabID === 'tot') {
            // refresh the grid
            atst_tot.tabbarCallback(curId);

        } else if (currTabID === 'pay') {
            // refresh the grid
            atst_pay.tabbarCallback(curId);
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

    if (isset($dbVars['checkIn'])) { // if from the main module table (bookings)
        // adding a past booking in admin module is OK, but room must be available for all dates in booking.
        // check for correct booking dates
        if (date('Y-m-d H:i:s', strtotime($dbVars['checkOut']['data'])) <= date('Y-m-d H:i:s', strtotime($dbVars['checkIn']['data']))) {
            $arrayOfErrors['checkOut'] = "Check Out must be later than check in.";
        } else {

            //    // check for overlapping dates with other bookings for same room
            if ($plm->checkRoomAvailability($dbVars['checkIn']['data'], $dbVars['checkOut']['data'], $dbVars['roomID']['data'], $id)) {
                // is ok, set nights
                $nights = $plm->getNightCount($dbVars['checkIn']['data'], $dbVars['checkOut']['data']);
                $dbVars['nights']['data'] = $nights;
                // set price
                $dbVars['totalLodging']['data'] = ($plm->getRoomPrice($dbVars['roomID']['data'], $dbVars['checkIn']['data']) * $nights);

            } else {
                $arrayOfErrors['checkIn'] = "This room is not available for all of these dates.";
            }
        }
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

function getAvailableAmenities($id)
{
    global $db;

    $dbe = new DBEngine($db);
    $pq_query = 'SELECT a.* FROM `amenities` a LEFT JOIN `bookingAmenities` b ON b.`amenityID` = a.`amenityID` WHERE a.`isOptional` = ? AND (b.`amenityID` IS NULL OR b.`bookingID` <> ?) ORDER BY a.`sortOrder` ';
    $dbe->setBindtypes('ii');
    $dbe->setBindvalues(array(1, $id));
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
                          'width' => 2, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'editAdd'=>true, 'show' => true, 'required'=>true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Amenity', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `Amenity` as item FROM `amenities` ORDER BY `sortOrder`','format'=>'', 'useKeys'=>true, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Price', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `price` as item FROM `amenities` ORDER BY `sortOrder`','format'=>'currency', 'useKeys'=>true, 'width' => 1.2, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'amenityID', 'heading' => 'Price Per', 'type' => 'i', 'lookup'=>'SELECT `amenityID` as id, `PricePer` as item FROM `amenities` ', 'format'=>'',
                          'useKeys'=>true, 'width' => .8, 'search' => false, 'edit' => false, 'show' => true));

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

function showBookingTotals($id)
{
    global $db;

    $table = 'bookingTotals';
    $keyField = 'bookingTotalID';
    $keyFieldType = 'i';
    $nameModifier = 'tot'; // should be same as table and tab
    $orderBy = array('sortOrder','enteredOn','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field'=>'bookingTotalID', 'heading'=>'Action', 'type'=>'callback:selectRow_tot', 'format'=>'Edit', 'width'=>'.6', 'ifCount'=>'notIsTotal', 'search'=>false, 'edit'=>false, 'show'=>true));
    // $rt2->addColumn(array('field' => 'bookingID', 'heading' => 'Booking', 'type' => 'i', 'lookup'=>'SELECT b.`bookingID` as id, concat(b.`checkIn`,"-",g.`lastName`) as item FROM `bookings` b INNER JOIN `guests` g ON g.`guestID` = b.`guestID` ORDER BY `checkIn` DESC',
    //                       'width' => 2, 'profileOrder'=>0, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'editAdd'=>true, 'show' => true));
    $rt2->addColumn(array('field' => 'enteredOn', 'heading'=>'Date', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'search'=>false, 'edit'=>false, 'sort'=>true, 'show'=>true));
    $rt2->addColumn(array('field' => 'item', 'heading' => 'Item', 'type' => 's', 'format'=>'', 'profileOrder'=>1, 'width' => 1.2, 'search' => false, 'edit' => false, 'show' => true));
    $rt2->addColumn(array('field' => 'isCredit', 'heading' => 'Type', 'type' => 's', 'showFunction'=>'isCreditDebit', 'format'=>'', 'profileOrder'=>2, 'width' => .5, 'search' => true, 'edit' => false, 'show' => true));
    $rt2->addColumn(array('field' => 'amount', 'heading' => 'Amount', 'type' => 'i', 'format'=>'currency', 'step'=>.01, 'profileOrder'=>3, 'width' => 1, 'search' => true, 'edit' => true, 'show' => true));
    $rt2->addColumn(array('field' => 'isOverride', 'heading' => 'Override', 'type' => 'b', 'format'=>'', 'profileOrder'=>4, 'width' => .8, 'search' => false, 'edit' => true, 'show' => true, 'comment'=>'If this is set, it will prevent the amount from changing during recalculations.'));
    $rt2->addColumn(array('field' => 'sortOrder', 'heading' => 'Sort Order', 'type' => 'i', 'format'=>'', 'width' => .8, 'search' => false, 'edit' => false, 'show' => true));

    $mres = new ATSubTable($db, $rt2, array('list', 'edit'), $table, $keyField, $keyFieldType, $nameModifier, 'boo');

    $mres->setQrySelect(' *, NOT isTotal as notIsTotal ');
    $mres->setAllowAll(true);
    $mres->setAllowDelete(false);
    $mres->setAllowEditing(true);
    $mres->setEditTitle('Totals');
    $mres->setLinkField('bookingID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No totals have been added for this booking.');
    $mres->setUpdateCallback('updateBookingTotals');

    $mres->process();


    ?>
    <script>
        $(document).ready(function () {
            $("#reportMode_tot div.outputLimitSelector").after('<button id="recalcBookingTotals" type="button" class="buttonBar" title="Recalc this booking."><i class="bi bi-calculator"></i>&nbsp;Recalc Booking</button>');

            
            $("#report_table_tot .productColumnsRows").each(function () {
                console.log($(this).html());
            });
        });


        function updateBookingTotals(obj) {
            $("#recalcBookingTotals").click();
        }
    </script>
    <?php
}

function recalculateBookingTotals($id)
{
    global $db;
    $dbe = new DBEngine($db, false);

    $lodging = 0;
    $extra = 0;
    $tax = 0;
    $totalPaid = 0;
    $pqQuery = 'SELECT * FROM `bookingTotals` WHERE `bookingID` = ? ORDER BY `sortOrder`';
    $dbe->setBindtypes('i');
    $dbe->setBindvalues(array($id));
    $rows = $dbe->execute_query($pqQuery);
    if ($rows) {
        $total = 0;
        foreach ($rows as $row) {
            if ($row['isTotal']) {
                // save if amount has changed
                if ($row['amount'] != abs($total)) { // always store amount as positive
                    $pqQuery = 'UPDATE `bookingTotals` SET `amount` = ? WHERE `bookingTotalID` = ?';
                    $dbe->setBindtypes('di');
                    $dbe->setBindvalues(array(abs($total), $row['bookingTotalID']));
                    $result = $dbe->execute_query($pqQuery);
                }                
            } else {
                $amount = 0;
                if ($row['type'] == 'addl') {
                    if (!$row['isOverride']) {
                        // add up any amenities for this booking
                        $pqQuery = 'SELECT ba.*, a.`pricePer`, b.`nights` FROM `bookingAmenities` ba
                                    INNER JOIN `amenities` a ON a.`amenityID` = ba.`amenityID` 
                                    INNER JOIN `bookings` b ON b.`bookingID` = ba.`bookingID`
                                    WHERE ba.`bookingID` = ? AND ba.`price` > 0 ORDER BY ba.`sortOrder`';
                        $dbe->setBindtypes('i');
                        $dbe->setBindvalues(array($id));
                        $amenities = $dbe->execute_query($pqQuery);
                        if ($amenities) {
                            $amount = 0;
                            foreach ($amenities as $amenity) {
                                if ($amenity['pricePer'] == 'Night') {
                                    $amount += $amenity['price'] * $amenity['nights']; // times number of nights
                                } else {
                                    $amount += $amenity['price'];
                                }
                            }
                            if ($row['amount'] != $amount) {
                                $pqQuery = 'UPDATE `bookingTotals` SET `amount` = ? WHERE `bookingTotalID` = ?';
                                $dbe->setBindtypes('di');
                                $dbe->setBindvalues(array($amount, $row['bookingTotalID']));
                                $result = $dbe->execute_query($pqQuery);
                            }
                        }
                    } else {
                        $amount = $row['amount'];
                    }
                    $extra += $amount;
                } elseif ($row['type'] == 'sub') {
                    if (!$row['isOverride']) {
                        // get the totalPrice field from the bookings table
                        $pqQuery = 'SELECT `totalLodging` FROM `bookings` WHERE `bookingID` = ?';
                        $dbe->setBindtypes('i');
                        $dbe->setBindvalues(array($id));
                        $bookings = $dbe->execute_query($pqQuery);
                        if ($bookings) {
                            $amount = $bookings[0]['totalLodging'];
                            if ($row['amount'] != $amount) {
                                $pqQuery = 'UPDATE `bookingTotals` SET `amount` = ? WHERE `bookingTotalID` = ?';
                                $dbe->setBindtypes('di');
                                $dbe->setBindvalues(array($amount, $row['bookingTotalID']));
                                $result = $dbe->execute_query($pqQuery);
                            }
                        }
                    } else {
                        $amount = $row['amount'];
                    }
                    $lodging += $amount;
                } elseif ($row['type'] == 'tax') {
                    if (!$row['isOverride']) {
                        // recalculate the taxes
                        $amount = number_format((TAX_RATE * ($lodging + $extra)) + TAX_ADDL, 2); // taxes
                        if ($row['amount'] != $amount) {
                            $pqQuery = 'UPDATE `bookingTotals` SET `amount` = ? WHERE `bookingTotalID` = ?';
                            $dbe->setBindtypes('di');
                            $dbe->setBindvalues(array($amount, $row['bookingTotalID']));
                            $result = $dbe->execute_query($pqQuery);
                        }
                    } else {
                        $amount = $row['amount'];
                    }
                    $tax += $amount;
                } elseif ($row['type'] == 'pmt' or $row['type'] == 'dep') { // payments and overrides
                    $amount = $row['amount'];
                    $totalPaid += $amount;
                }
                // calculate total
                if ($row['isCredit']) {
                    $total += $amount;
                } else {
                    $total -= $amount;
                }
            }
        }
        // update values in bookings table
        $pqQuery = 'UPDATE `bookings` SET `totalPrice` = ?, `amountPaid` = ?, `amountDue` = ? WHERE `bookingID` = ?';
        $dbe->setBindtypes('dddi');
        $dbe->setBindvalues(array(($lodging + $extra + $tax), $totalPaid, abs($total), $id));
        $result = $dbe->execute_query($pqQuery);
    }

    $dbe->close();
}

function isCreditDebit($id, $val, $attr = '', $edit, $row)
{
    if (!$row['isTotal']) {
        if ($val == 1) {
            return 'Payment';
        } else {
            return 'Charge';
        }
    } else {
        return 'Total';
    }
}

function showPayments($id)
{
    global $db;

    $table = 'payments';
    $keyField = 'paymentID';
    $keyFieldType = 'i';
    $nameModifier = 'pay'; // should be same as table and tab
    $orderBy = array('paidOn DESC','','');

    $rt2 = new ReportTable($db, $table, $keyField, $nameModifier, basename($_SERVER['PHP_SELF']));

    $rt2->setQryOrderBy($orderBy); // default sort order

    $rt2->addColumn(array('field'=>'paymentID', 'heading'=>'Action', 'type'=>'callback:selectRow_pay', 'format'=>'Edit', 'width'=>'.6', 'search'=>false, 'edit'=>false, 'show'=>true));
    $rt2->addColumn(array('field' => 'bookingID', 'heading' => 'Booking', 'type' => 'i', 'lookup'=>'SELECT b.`bookingID` as id, concat(b.`checkIn`,"-",g.`lastName`) as item FROM `bookings` b INNER JOIN `guests` g ON g.`guestID` = b.`guestID` ORDER BY `checkIn` DESC',
                        'width' => 2, 'default'=>'parentID', 'search' => true, 'edit' => false, 'showEdit'=>true, 'editAdd'=>true, 'show' => true, 'required'=>true));
    $rt2->addColumn(array('field'=>'guestID', 'heading'=>'Guest', 'type'=>'i', 'lookup'=>'SELECT `guestID` AS id, concat(firstName," ", lastName) AS item FROM `guests` WHERE 1 ',
                        'width'=>'2.2', 'profile'=>true, 'profileOrder'=>4, 'saveAdd'=>true, 'search'=>false, 'sort'=>true, 'showEdit'=>true, 'edit'=>false, 'editadd'=>true, 'show'=>true, 'required'=>true));
    $rt2->addColumn(array('field'=>'type', 'heading'=>'Type', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>false, 'showEdit'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true, 'default'=>2, 'required'=>true));
    $rt2->addColumn(array('field' => 'amount', 'heading' => 'Amount', 'type' => 'i', 'format'=>'currency', 'step'=>.01, 'profileOrder'=>3, 'width' => 1, 'search' => true, 'showEdit'=>true, 'edit' => false, 'show' => true));
    $rt2->addColumn(array('field'=>'paymentStatusID', 'heading'=>'Pmt Status', 'type'=>'i', 'width'=>'1', 'lookup'=> 'SELECT `paymentStatusID` AS id, `status` AS item FROM `paymentStatus` WHERE 1', 'format'=>'', 'profile'=>true, 'profileOrder'=>8, 'search'=>false, 'edit'=>true, 'sort'=>true, 'show'=>true, 'default'=>2, 'required'=>true));
    $rt2->addColumn(array('field'=>'paidBy', 'heading'=>'Paid By', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>9, 'search'=>true, 'showEdit'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
    $rt2->addColumn(array('field'=>'txnID', 'heading'=>'Trans ID', 'type'=>'s', 'width'=>'1', 'format'=>'', 'profile'=>true, 'profileOrder'=>10, 'search'=>true, 'showEdit'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));
    $rt2->addColumn(array('field' => 'paidOn', 'heading'=>'Date', 'type'=>'d', 'width'=>'1.2', 'format'=>'m/d/Y H:i:s', 'search'=>false, 'showEdit'=>true, 'edit'=>false, 'sort'=>true, 'show'=>true));

    $mres = new ATSubTable($db, $rt2, array('list','edit'), $table, $keyField, $keyFieldType, $nameModifier);

    $mres->setAllowAll(true);
    $mres->setAllowDelete(false);
    $mres->setAllowEditing(true);
    $mres->setEditTitle('Payments');
    $mres->setLinkField('bookingID');
    $mres->setLinkID($id);
    $mres->setRtEmptyMessage('No payments have been made for this booking.');

    $mres->process();

}

function makePayment()
{
    global $db;
    
    $dbe = new DBEngine($db);
    $plm = new PLManager();

    $msg = array();
    $bookingID = $_POST['bookingID'];
    $amount = $_POST['amountPaid'];
    $paymentType = $_POST['paymentType'];
    $checkNumber = $_POST['checkNumber'];
    $cardName = $_POST['cardName'];
    $cardNum = $_POST['cardNum'];
    $expDate = $_POST['expDate'];
    $cvv = $_POST['cvv'];
    if ($amount == '') {
        $msg[] = 'Please enter an amount for the payment.';
    }

    $booking = $dbe->getRowWhere('bookings', 'bookingID', $bookingID);
    if ($booking) {
        $guest = $dbe->getRowWhere('guests', 'guestID', $booking['guestID']);
        if ($guest) {              
            switch ($paymentType) {
                case 'cash':
                    $paymentID = $plm->savePaymentRecord($bookingID, $guest['guestID'], $amount, $paymentType);
                    if ($paymentID > 0) {
                        $msg[] = 'Success';
                    } else {
                        $msg[] = 'Error saving payment record.';
                    }
                    break;
                case 'check':
                    if (!$checkNumber == '') {
                        $paymentID = $plm->savePaymentRecord($bookingID, $guest['guestID'], $amount, $paymentType, $checkNumber);
                        if ($paymentID > 0) {
                            $msg[] = 'Success';
                        } else {
                            $msg[] = 'Error saving payment record.';
                        }
                    } else {
                        $msg[] = 'Please enter a check number.';
                    }
                    break;
                case 'credit':
                    // validate entries
                    if (strlen($cardName) < 3) {
                        $msg[] = '<div class="form_error">Cardholder Name must be valid.</div>';
                    }
                    if (strlen($cardNum) < 12 or !is_numeric($cardNum)) {
                        $msg[] = '<div class="form_error">Card Number must be valid.</div>';
                    }
                    // exp date
                    if (strlen($expDate) == 7) {
                        $cardDate = DateTime::createFromFormat('Y-m', $expDate);
                        $currentDate = new DateTime('now');
                        $interval = $currentDate->diff($cardDate);
                        if ( $interval->invert == 1 ) {
                            // Expired
                            $msg[] = '<div class="form_error">The card has expired</div>';
                        }
                        $_POST['expDate'] = $expDate;
                    } else {
                        $msg[] = '<div class="form_error">You must select an expiration date.</div>';
                    }
                    if (strlen($cvv) < 3 or !is_numeric($cvv)) {
                        $msg[] = '<div class="form_error">CVV Number must be valid.</div>';
                    }
                    if (count($msg) == 0) {
                        $tresponse = $plm->makePayment($amount, $cardNum, $expDate, $cvv, $bookingID, 'Booking Charges', $guest);
                    
                        if ($tresponse) {
                            switch ($tresponse->getResponseCode()) { 
                                
                                case 1: // the payment was approved
                                    $paymentStatus = $plm::PMT_STATUS_COMPLETED;
                                    $txnID = $tresponse->getTransId();
                                    $paidBy = 'card-' . substr($cardNum, -4);
                                    
                                    $paymentID = $plm->savePaymentRecord($bookingID, $guest['guestID'], $amount, $paymentType, $checkNumber, $paidBy, $paymentStatus, $txnID);
                                    if ($paymentID > 0) {
                                        $msg[] = 'Success';
                                    } else {
                                        $msg[] = 'Error saving payment record.';
                                    }
                                    break;

                                case 2: // Declined
                                    $paymentStatus = $plm::PMT_STATUS_DENIED;
                                    $msg[] = 'Declined';
                                    break;

                                case 3: // Error
                                    $paymentStatus = $plm::PMT_STATUS_FAILED;
                                    $msg[] = $tresponse->getErrors()[0]->getErrorText();
                                    break;

                                case 4: // Held for Review
                                    $paymentStatus = $plm::PMT_STATUS_PENDING;
                                    $msg[] = 'Held for Review';
                                    break;

                                default:
                                    if ($tresponse->getErrors() != null) {
                                        $msg[] = $tresponse->getErrors()[0]->getErrorText();
                                    }
                                    break;
                            }
                        } else {
                            $msg[] = 'There was an error processing your payment. Please try again.A';
                        }
                    } else {
                        $msg[] = 'There was an error processing your payment. Please try again.B';
                    }
                    break;
            }
        } else {
            $msg[] = 'There was an error processing your payment. Please try again.C';
        }
    } else {
        $msg[] = 'There was an error processing your payment. Please try again.D';
    }
    if ($msg[0] == 'Success') {
        // also add a bookingTotals record
        $result = $plm->addBookingTotal($bookingID, 'pmt', 'Payment', $amount, $booking['roomID'], 6, 0, 1, $paymentID);
        // also adjust the bookings record totals
        recalculateBookingTotals($bookingID);
        return array('result'=>'Success');
    } else {
        return array('result'=>'Error', 'messages' => $msg);
    }
}

function getBalanceDue($bookingID)
{
    global $db;
    recalculateBookingTotals($bookingID);

    $dbe = new DBEngine($db);
    $booking = $dbe->getRowWhere('bookings', 'bookingID', $bookingID);
    $dbe->close();
    if ($booking) {
        return number_format($booking['amountDue'], 2);
    } else {
        return '0.00';
    }
}