<?php
/**
 * Booking Account
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 *
 * @created 3/28/2023
 *
 * @var $room
 * @var $dateRange
 *
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
        <h2>This transaction has been approved.</h2>
        <section>
            <h3>Billing Address</h3>
            <div><?=gls_esc_html($guest['firstName'] . ' ' . $guest['lastName'])?></div>
            <address><?=gls_esc_html($guest['street'])?><br>
                <?=gls_esc_html($guest['city'] . ', ' . $guest['state'] . ' ' . $guest['zip'])?><br>
                <?=gls_esc_html($guest['country'])?>
            </address>            
        </section>
        <section>
            <h3>Booking Summary</h3>
            <div class="form_rows">
                <label for="name" class="form_label">Confirmation Number</label>
                <div class="form_cell">
                    <?=$_SESSION['txnID']?>
                </div>
            </div>
            <div id="booking_summary">
                <div id="booking_summary_left">
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
                </div>
                <div>
                    <?php foreach ($_SESSION['bookingTotals'] as $key => $bt_item) {
                        $item = unserialize($bt_item); 
                        if ($item->getAmount() != 0 or $item->getIsTotal()) {
                            ?>
                            <div class="order_total <?=($item->getIsTotal())?'total_line':''?>">
                                <div class="form_label"><?=$item->getItemType()?></div>
                                <div class="form_cell">
                                    $<?=number_format($item->getAmount(), 2)?>
                                </div>
                            </div>
                            <?php 
                        }
                    } ?>
                </div>
            </div>
        </section>

    </main>
<?php

require_once('./page_footer.php');
