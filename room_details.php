<?php
/**
 * Room Details Templage
 * @author Lee Samdahl
 * @author Gleesoft, LLC
 *
 * @created 3/28/2023
 */

error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/classes/PLManager.php');

$plm = new PLManager();

if (!isset($_GET['roomID'])) {
    header('location: /dwr/roomSearch.php');
}

$roomID = intval($_GET['roomID']);

$room = $plm->getRoom($roomID);
if ($room == array()) {
    header('location: /dwr/roomSearch.php');
}

$dateRange = $plm->setDateRange($_SESSION['checkIn'], $_SESSION['checkOut']);
$roomPrice = $plm->getRoomPrice($roomID, $_SESSION['checkIn']);
$amenities = $plm->getRoomAmenities($roomID);
$slides = $plm->getRoomImages($roomID);

$additionalHeaders = '<script src="https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.umd.min.js"></script>
<script src="js/nivo/jquery.nivo.slider.pack.js" type="text/javascript"></script>
    <link rel="stylesheet" href="js/nivo/nivo-slider.css" type="text/css" />
    <link rel="stylesheet" href="js/nivo/themes/default/default.css" type="text/css" />';

require_once('./page_header.php');
require_once('./page_nav.php');
?>
    <main>
        <div class="titlebar">
            <h1>The Desert Willow Ranch B&B</h1>
            <h2>Stay With Us</h2>
        </div>
        <div style="margin-top: 1rem;">
            <section>
                <div class="room_details">
                    <div class="room_details_col_1">
                        <?php if (count($slides) > 0) { ?>
                        <div class="slider-wrapper theme-default" id="index-slideshow">
                            <div class="nivoSlider" id="slider">
                                <?php
                                foreach ($slides as $slide) {
                                    if (file_exists('./images/' . $slide["image"])) {
                                        echo '<img src="./images/' . $slide['image'] . '" />';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php } ?>
                        <section>
                            <h3>Properties:</h3>
                            <ul>
                                <li>Accommodates: <?=gls_esc_html($room['maxPersons'])?></li>
                                <li>Size: <?=gls_esc_html($room['size'])?></li>
                                <li>Beds: <?=gls_esc_html($room['beds'])?></li>
                            </ul>
                        </section>
                        <section>
                            <h3>More Info:</h3>
                            <p>
                                <?=gls_esc_html($room['details'])?>
                            </p>
                        </section>
                        <?php if (count($amenities) > 0) { ?>
                        <section>
                            <h3>Amenities:</h3>
                            <ul>
                                <?php
                                foreach ($amenities as $amenity) {
                                    echo '<li>' . gls_esc_html($amenity['amenity']) . '</li>';
                                }
                                ?>
                            </ul>
                        </section>
                        <?php } ?>
                        <section>
                            <h3>Check In and Out:</h3>
                            <p> Check-in: 04:00 PM&nbsp;|&nbsp;Check-Out: 12:00 PM</p>
                        </section>
                        <section>
                            <h3>Terms:</h3>
                            <p><a href="#" >Our Policies</a></p>
                        </section>

                    </div>
                    <div class="room_details_col_2">
                        <div class="room_info2">
                            <div class="room_info_inner">
                                <h2><?=$room['roomName']?></h2>
                                <div class="room_price_line">From <span class="room_price">$<?=number_format($roomPrice,2)?></span> per night</div>
                                <div>
                                    Check In / Out
                                    <div id="date_range"><?=$dateRange['range']?></div>
                                    <div id="num_nights"><?=$dateRange['nights']?> night(s)</div>
                                </div>
                            </div>
                            <button type="button" class="book_now_btn" data-roomID="<?=$roomID?>">Book Now</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

    </main>
<?php

require_once('./page_footer.php');

?>
<script>
    $( document).ready(function () {
        $(".book_now_btn").on('click', function () {
            let roomID = $(this).attr('data-roomID');
            // append to search form data and submit
            let data = {};
            $('#myForm').serializeArray().map(function(x){data[x.name] = x.value;});
            data['process'] = 'book_now';
            data['roomID']  = roomID;
            // console.log(data);
            post_to_url('booking.php?action=booking_account', data, 'post', '_self'); // redirect to booking_account.php
        });

        $('#slider').nivoSlider({
            effect: 'sliceDown',                 // Specify sets like: 'fold,fade,sliceDown'
            slices: 16,                       // For slice animations
            boxCols: 8,                       // For box animations
            boxRows: 4,                       // For box animations
            animSpeed: 300,                   // Slide transition speed
            pauseTime: 4800,                  // How long each slide will show
            startSlide: 0,                    // Set starting Slide (0 index)
            directionNav: false,              // Next-Prev navigation
            controlNav: true,                 // 1,2,3... navigation
            controlNavThumbs: false,          // Use thumbnails for Control Nav
            pauseOnHover: true,               // Stop animation while hovering
            manualAdvance: false,             // Force manual transitions
            prevText: 'Prev',                 // Prev directionNav text
            nextText: 'Next',                 // Next directionNav text
            randomStart: false,               // Start on a random slide
            //beforeChange: function(){},       // Triggers before a slide transition
            //afterChange: function(){},        // Triggers after a slide transition
            //slideshowEnd: function(){},       // Triggers after all slides have been shown
            //lastSlide: function(){},          // Triggers when last slide is shown
            //afterLoad: function(){}           // Triggers when slider has loaded
        });
    });
</script>