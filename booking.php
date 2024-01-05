<?php
/**
 * Booking Module
 * Perform functions to create a booking
 * This is the Controller module for the booking process
 *
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 * @created 4/4/23
 *
 */
// ini_set('display_errors', 1); // todo remove for live version
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/classes/PLManager.php');

if (!isset($_SESSION['transID'])) {
    if (isset($_POST['process']) and $_POST['process'] == 'book_now') {
        $_SESSION['transID'] = uniqid();
    } else {
        header('location: /dwr/roomSearch.php');
    }
} elseif (!isset($_POST['transID']) or $_POST['transID'] != $_SESSION['transID']) {
    header('location: /dwr/roomSearch.php');
}

$plm = new PLManager();

// get the details from the session and post
$msg = array();
$checkIn = (isset($_POST['checkIn']))?$_POST['checkIn']:$_SESSION['checkIn'];
$checkOut = (isset($_POST['checkOut']))?$_POST['checkOut']:$_SESSION['checkOut'];
$roomType = (isset($_POST['roomType']))?$_POST['roomType']:$_SESSION['roomType'];

if (isset($_POST['roomID'])) {
    $roomID = $_POST['roomID'];
} elseif (isset($_SESSION['roomID'])) {
    $roomID = $_SESSION['roomID'];
} else {
    // oops
    header('location: /dwr/roomSearch.php');
}

// todo validate all variables, make sure the date ranges are still correct for this roomID
// get the room type, room name, and price
$roomTypes = $plm->roomTypes();
$room = $plm->getRoom($roomID);
$dateRange = $plm->setDateRange($checkIn, $checkOut);
$price = $plm->getRoomPrice($roomID, $checkIn);

$subtotal = $price * $dateRange['nights'];
$additional = 0;  // todo Additional Charges
$tax = (TAX_RATE * ($subtotal + $additional)) + TAX_ADDL; // taxes
$total = $subtotal + $additional + $tax;
if (ALLOW_DEPOSITS and DEPOSIT_TYPE == "Percent") {
    $deposit = (round($total * (DEPOSIT_PERCENT / 100), 2));
} elseif (ALLOW_DEPOSITS and DEPOSIT_TYPE == "Amount") {
    $deposit = DEPOSIT_AMOUNT;
} else {
    $deposit = 0;
}
if ($deposit > 0) {
    $balanceDue = $deposit;
} else {
    $balanceDue = $total;
}
// get optional amenities into array
$amenities = $plm->getOptionalAmenities();

switch ($_GET['action']) {
    case 'booking_account':
        // login with email address then get guest account or create new one
        // show name/ address info for confirmation
        if (!isset($_SESSION['guest'])) {
            $_SESSION['guest'] = array(
                'firstName' => '',
                'lastName'  => '',
                'company'   => '',
                'street'    => '',
                'city'      => '',
                'state'     => '',
                'zip'       => '',
                'country'   => 'USA',
                'phone'     => '',
                'email'     => '',
                'title'     => '',
                'contactBy' => 'Email',
            );
        }
        $_SESSION['checkIn'] = $checkIn;
        $_SESSION['checkOut'] = $checkOut;
        $_SESSION['nights'] = $dateRange['nights'];
        $_SESSION['roomType'] = $roomType;
        $_SESSION['roomID'] = $roomID;

        include 'booking_account.php';
        break;

    case 'booking_address':
        if (strlen($_POST['email']) < 7) {
            $msg[] = '<div class="form_error">Email may not be empty.</div>';
        }
        if (!isValidEmail($_POST['email'])) {
            $msg[] = '<div class="form_error">Email address is not valid.</div>';
        }
        if (count($msg) > 0) {
            // go back to fix errors
            include 'booking_account.php';
        } else {
            if (isset($_POST['transID']) and $_POST['page_from'] == 'billing_account') { // is postback from booking_account page
                // look up email address
                $row = $plm->getGuestByEmail($_POST['email']);
                if ($row) {
                    if ($row['guestID'] > 1) { // don't allow login with admin blackout user
                        $_SESSION['guest'] = $row;
                        // get payment details
                        $_SESSION['bookingTotals'] = array();
                        $_SESSION['bookingTotals']['sub'] = serialize(new BookingTotal('sub', $subtotal, $roomID));
                        $_SESSION['bookingTotals']['addl'] = serialize(new BookingTotal('addl', $additional, $roomID));
                        $_SESSION['bookingTotals']['tax'] = serialize(new BookingTotal('tax', $tax, $roomID));
                        $_SESSION['bookingTotals']['total'] = serialize(new BookingTotal('total', $total, $roomID));
                        if (ALLOW_DEPOSITS) {
                            $_SESSION['bookingTotals']['due'] = serialize(new BookingTotal('due', $deposit, $roomID));
                        } else {
                            $_SESSION['bookingTotals']['due'] = serialize(new BookingTotal('due', $balanceDue, $roomID));
                        }
                        // confirm booking button
                        include 'booking_payment.php';
                    } else {
                        $msg[] = 'This email address is not available.';
                        include 'booking_account.php';
                    }
                } else {
                    $_SESSION['guest']['email'] = $_POST['email'];
                    include 'booking_address.php';
                }
            } elseif (isset($_POST['transID']) and $_POST['page_from'] == 'billing_payment') {
                include 'booking_address.php';
            }
        }

        break;

    case 'booking_payment':
        if ($_POST['page_from'] == 'billing_address') {
            // get and validate the account info
            // validate
            if (strlen($_POST['firstName']) < 2) {
                $msg[] = '<div class="form_error">First Name may not be empty.</div>';
            }
            if (strlen($_POST['lastName']) < 2) {
                $msg[] = '<div class="form_error">Last Name may not be empty.</div>';
            }
            if (strlen($_POST['street']) < 10) {
                $msg[] = '<div class="form_error">Street Address may not be empty.</div>';
            }
            if (strlen($_POST['city']) < 3) {
                $msg[] = '<div class="form_error">City may not be empty.</div>';
            }
            if (strlen($_POST['zip']) < 5) {
                $msg[] = '<div class="form_error">Post/Zip Code may not be empty.</div>';
            }
            if (strlen($_POST['country']) < 2) {
                $msg[] = '<div class="form_error">Country may not be empty.</div>';
            }
            if (strlen($_POST['phone']) < 7) {
                $msg[] = '<div class="form_error">Telephone may not be empty.</div>';
            }
            // assign guest info to session
            $_SESSION['guest'] = array(
                'firstName' => $_POST['firstName'],
                'lastName'  => $_POST['lastName'],
                'company'   => $_POST['company'],
                'street'    => $_POST['street'],
                'city'      => $_POST['city'],
                'state'     => $_POST['state'],
                'zip'       => $_POST['zip'],
                'country'   => $_POST['country'],
                'phone'     => $_POST['phone'],
                'email'     => $_POST['email'],
                'title'     => $_POST['title'],
                'contactBy' => $_POST['contactBy'],
            );
        }
                if (count($msg) > 0) {
            // go back to fix errors
            include 'booking_address.php';
        } else {
            // get payment details

            $_SESSION['bookingTotals'] = array();
            $_SESSION['bookingTotals']['sub'] = serialize(new BookingTotal('sub', $subtotal, $roomID));
            $_SESSION['bookingTotals']['addl'] = serialize(new BookingTotal('addl', $additional, $roomID));
            $_SESSION['bookingTotals']['tax'] = serialize(new BookingTotal('tax', $tax, $roomID));
            $_SESSION['bookingTotals']['total'] = serialize(new BookingTotal('total', $total, $roomID));
            if (ALLOW_DEPOSITS) {
                $_SESSION['bookingTotals']['due'] = serialize(new BookingTotal('due', $deposit, $roomID));
            } else {
                $_SESSION['bookingTotals']['due'] = serialize(new BookingTotal('due', $balanceDue, $roomID));
            }
            // confirm booking button
            include 'booking_payment.php';
        }
        break;

    case 'booking_process':
        // ini_set('display_errors', 1);
        // ini_set('display_startup_errors', 1);
        // error_reporting(E_ALL);

        $cardName = $_POST['cardName'];
        $cardNum = $_POST['cardNum'];
        $expDate = $_POST['expireYY'] . '-' . $_POST['expireMM'];
        $cvv = $_POST['cvv'];
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
            $_SESSION['amountPaid'] = $_POST['amountPaid'];
            if ($_SESSION['amountPaid'] < $balanceDue) $_SESSION['amountPaid'] = $balanceDue;
            if ($_SESSION['amountPaid'] > $total) $_SESSION['amountPaid'] = $total;

            // submit to processor
            if ($plm->processBooking('website')) {

                unset($_POST);
                $_SESSION['due'] = 0;

                include 'booking_success.php';
            } else {
                // get the error message and go back
                if (isset($_SESSION['errorMessage'])) {
                    $msg[] = '<div class="form_error">An error occurred: ' . $_SESSION['errorMessage'] . '</div>';
                } 
                include 'booking_payment.php';
            }
        } else {
            include 'booking_payment.php';
        }

        break;

    default:
        // show error message

        break;

}