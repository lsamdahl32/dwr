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
            <h1>Step 2 - Contact Info</h1>
        </div>
        <section>
            <div style="display: flex; flex-flow: row wrap; justify-content:  space-between; align-items: baseline;">
                <h3>Booking Summary</h3>
                <button type="button" id="backButton" class="buttonBar" title="Change the dates or room.">
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
            <?php if (!isset($guest['lastName'])) { ?>
                <h2>Enter Your Contact Information</h2>
                <p>No guest information was found for this email address. Please enter your contact information.</p>
            <?php } else { // account was not found, get new info ?>
                <div style="display: flex; flex-flow: row wrap; justify-content:  space-between; align-items: baseline;">
                    <h2>Confirm Your Contact Information</h2>
                    <button type="button" id="changeEmailButton" class="buttonBar" title="Change the email address.">
                        <i class="bi-arrow-return-left"></i>&nbsp;
                        Change
                    </button>
                </div>
            <?php } ?>
            <form action="booking.php?action=booking_amenities" id="billing_address" method="post" accept-charset="UTF-8">
                <input type="hidden" name="transID" value="<?=$_SESSION['transID']?>" />
                <input type="hidden" name="page_from" value="billing_address" />
                <h3>Billing Address</h3>
                <div class="form_error_container"><?=implode('<br>', $msg) ?></div>
                <div class="form_rows">
                    <label for="email" class="form_label">Email</label>
                    <div class="form_cell">
                        <input type="email" name="email" id="email" readonly value="<?=gls_esc_attr($guest['email'])?>" />
                    </div>
                </div>
                <div class="form_rows">
                    <label for="title" class="form_label">Title</label>
                    <div class="form_cell">
                        <input type="text" name="title" id="title"  value="<?=gls_esc_attr($guest['title'])?>" />
                    </div>
                </div>
                <div class="form_rows">
                    <label for="name" class="form_label">First Name</label>
                    <div class="form_cell">
                        <input type="text" name="firstName" id="firstName" value="<?=gls_esc_attr($guest['firstName'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="lastName" class="form_label">Last Name</label>
                    <div class="form_cell">
                        <input type="text" name="lastName" id="lastName"  value="<?=gls_esc_attr($guest['lastName'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="company" class="form_label">Company Name</label>
                    <div class="form_cell">
                        <input type="text" name="company" id="company"  value="<?=gls_esc_attr($guest['company'])?>"/>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="street" class="form_label">Street Address</label>
                    <div class="form_cell">
                        <input type="text" name="street" id="street"  value="<?=gls_esc_attr($guest['street'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="city" class="form_label">City</label>
                    <div class="form_cell">
                        <input type="text" name="city" id="city"  value="<?=gls_esc_attr($guest['city'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="state" class="form_label">State</label>
                    <div class="form_cell">
                        <input type="text" name="state" id="state"  value="<?=gls_esc_attr($guest['state'])?>" />
                    </div>
                </div>
                <div class="form_rows">
                    <label for="zip" class="form_label">Post/Zip Code</label>
                    <div class="form_cell">
                        <input type="text" name="zip" id="zip"  value="<?=gls_esc_attr($guest['zip'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="country" class="form_label">Country</label>
                    <div class="form_cell">
                        <input type="text" name="country" id="country"  value="<?=gls_esc_attr($guest['country'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="phone" class="form_label">Telephone</label>
                    <div class="form_cell">
                        <input type="text" name="phone" id="phone" value="<?=gls_esc_attr($guest['phone'])?>" />
                        <span class="form_error">*</span>
                    </div>
                </div>
                <div class="form_rows">
                    <label for="contactBy" class="form_label">Contact By</label>
                    <div class="form_cell">
                        <select name="contactBy" id="contactBy" >
                            <option value="Phone" <?=($guest['contactBy'] == 'Phone')?'selected':''?>>Phone</option>
                            <option value="Email" <?=($guest['contactBy'] == 'Email')?'selected':''?>>Email</option>
                            <option value="Text" <?=($guest['contactBy'] == 'Text')?'selected':''?>>Text</option>
                        </select>
                    </div>
                </div>
                <span class="form_error">* = Required</span>
                <button type="submit" class="book_now_btn" data-roomID="1">
                    Continue
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

        $("#changeEmailButton").on('click' , function () {
            let data = {};
            $('#billing_address').serializeArray().map(function(x){data[x.name] = x.value;});
            // console.log(data);
            post_to_url('booking.php?action=booking_account', data, 'post', '_self'); // redirect to booking_account.php
        });
    });
</script>
