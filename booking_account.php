<?php
/**
 * Booking Account
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 * @created 4/4/23
 *
 * @created 3/28/2023
 *
 * @var $msg
 * @var $room
 * @var $dateRange
 * @var $price
 * @var $contactBy
 */
require_once ('./includes/general_functions.php');

$guest = $_SESSION['guest'];

$additionalHeaders = '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">';
require_once('./page_header.php');
require_once('./page_nav.php');

?>

    <main>
        <div class="titlebar">
            <h1>The Desert Willow Ranch B&B</h1>
            <h2>Stay With Us</h2>
        </div>
        <div class="bookingSteps">
            <h1>Step 1 - Email</h1>
        </div>
        <section>
            <div style="display: flex; flex-flow: row wrap; justify-content:  space-between; align-items: baseline;">
                <h3>Booking Summary</h3>
                <button type="button" id="backButton" class="buttonBar">
                    <i class="bi-arrow-return-left"></i>&nbsp;
                    Change
                </button>
            </div>
            <div class="form_rows">
                <label for="name" class="form_label">Room</label>
                <div class="form_cell">
                    <?=$room['roomName']?>
                </div>
            </div>
            <div class="form_rows">
                <label for="name" class="form_label">Check In/Out</label>
                <div class="form_cell">
                    <div><span id="date_range"><?=$dateRange['range']?></span> | <span id="num_nights"><?=$dateRange['nights']?></span> night(s)</div>
                </div>
            </div>
            <div class="form_rows">
                <label for="name" class="form_label">Base Price</label>
                <div class="form_cell" >
                    <span>Room</span> <span>$<?=number_format($price,2)?> per night</span> <span>$<?=number_format($price * $dateRange['nights'], 2)?> for <?=$dateRange['nights']?> nights.</span>
                </div>
            </div>
        </section>
        <section>
            <form action="booking.php?action=booking_address" id="billing_account" method="post" accept-charset="UTF-8">
                <input type="hidden" name="transID" value="<?=$_SESSION['transID']?>" />
                <input type="hidden" name="page_from" value="billing_account" />
                <h2 style="flex-basis: 100%;">Enter Your Email Address</h2>
                <div class="form_error_container"><?=implode('<br>', $msg) ?></div>
                <div class="form_rows">
                    <label for="email" class="form_label">Email</label>
                    <div class="form_cell">
                        <input type="email" name="email" id="email"  value="<?=gls_esc_attr($guest['email'])?>" />
                        <span class="form_error"><i class="bi-asterisk"></i></span>
                    </div>
                </div>
                <span class="form_error"><i class="bi-asterisk"></i> = Required</span>
                <button type="submit" class="book_now_btn" data-roomID="1">
                    Continue
                    <img src="./images/pointing-right-finger-svgrepo-com.svg" class="finger_point_right"/>
                </button>
            </form>

        </section>

    </main>
<?php

require_once('./page_footer.php');

?>
<script>
    $( document).ready(function () {
        $("#backButton").on('click' , function () {
            location = 'roomSearch.php';
        });
    });
</script>
