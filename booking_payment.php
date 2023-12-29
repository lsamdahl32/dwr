<?php
/**
 * Booking Account
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 * @created 4/4/23
 *
 * @created 3/28/2023
 *
 * @var $room
 * @var $dateRange
 * @var $balanceDue
 * @var $total
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
            <h1>Step 3 - Payment Info</h1>
        </div>
        <section>
            <div style="display: flex; flex-flow: row wrap; justify-content:  space-between; align-items: baseline;">
                <h3>Billing Address</h3>
                <button type="button" id="changeAddressButton" class="buttonBar" title="Change the name or address.">
                    <i class="bi-arrow-return-left"></i>&nbsp;
                    Change
                </button>
            </div>
            <div><?=gls_esc_html($guest['firstName'] . ' ' . $guest['lastName'])?></div>
            <address><?=gls_esc_html($guest['street'])?><br>
                <?=gls_esc_html($guest['city'] . ', ' . $guest['state'] . ' ' . $guest['zip'])?><br>
                <?=gls_esc_html($guest['country'])?>
            </address>
        </section>
        <section>
            <div style="display: flex; flex-flow: row wrap; justify-content:  space-between; align-items: baseline;">
                <h3>Booking Summary</h3>
                <button type="button" id="backButton" class="buttonBar" data-roomID="<?=$room['roomName']?>" title="Change the dates or room.">
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
                <label for="name" class="form_label">Extra Items</label>
                <div class="form_cell">
                    None
                </div>
            </div>
            <?php foreach ($_SESSION['bookingTotals'] as $key => $bt_item) {
                $item = unserialize($bt_item);
                ?>
                <div class="order_total <?=($item->getIsTotal())?'total_line':''?>">
                    <div class="form_label"><?=$item->getItemType()?></div>
                    <div class="form_cell">
                        $<?=number_format($item->getAmount(), 2)?>
                    </div>
                </div>
            <?php }
            if (ALLOW_DEPOSITS) {
                if (DEPOSIT_TYPE == 'Percent') {
                    $depositAmt = DEPOSIT_PERCENT . '% of the total bill';
                } else {
                    $depositAmt = '$' . number_format(DEPOSIT_AMOUNT, 2);
                }
                echo '<p>' . str_replace('{depositAmt}', $depositAmt, DEPOSIT_TEXT) . '</p>';
            }
            ?>
        </section>
        <section>
            <h3>Enter Your Payment Information</h3>
            <div class="form_error_container"><?=implode('<br>', $msg) ?></div>
            <form action="booking.php?action=booking_process" id="paymentForm" method="post" accept-charset="UTF-8">
                <input type="hidden" name="transID" value="<?=$_SESSION['transID']?>" />
                <input type="hidden" name="page_from" value="billing_payment" />
                <input type="hidden" name="email" value="<?=$_SESSION['guest']['email']?>" />
                <div class="form_rows">
                    <label for="cardName" class="form_label">Cardholder Name:</label>
                    <div class="form_cell">
                        <input type="text" name="cardName" id="cardName" value="<?= gls_esc_attr($_POST['cardName'])?>" required />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <?php if (ALLOW_DEPOSITS) { ?>
                <div class="form_rows">
                    <label for="amountPaid" class="form_label">Amount to Charge:</label>
                    <div class="form_cell">$
                        <input type="number" name="amountPaid" id="amountPaid" required min="<?=$balanceDue?>" max="<?=$total?>" value="<?=number_format($balanceDue,2)?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <?php } else { ?>
                    <input type="hidden" name="amountPaid" value="<?=$total?>" />
                <?php } ?>
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
                <span class="form_error">* = Required</span>
                <button type="submit" class="book_now_btn" data-roomID="1">
                    Confirm Booking
                    <img src="./images/pointing-right-finger-svgrepo-com.svg" style="width: 30px;" class="finger_point_right"/>
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

        $("#changeAddressButton").on('click' , function () {
            let data = {};
            $('#paymentForm').serializeArray().map(function(x){data[x.name] = x.value;});
            // console.log(data);
            post_to_url('booking.php?action=booking_address', data, 'post', '_self'); // redirect to booking_account.php
        });
    });
</script>
