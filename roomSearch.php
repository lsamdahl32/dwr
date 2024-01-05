<?php
/**
 * Bookings module
 * @author Lee Samdahl
 * @author Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 *
 * @created 3/28/2023
 */
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/classes/PLManager.php');

$plm = new PLManager();


$checkIn = date('Y-m-d');
$checkOut = date('Y-m-d', strtotime('+1 day'));
$roomType = 1;

if (isset($_SESSION['checkIn'])) $checkIn = $_SESSION['checkIn'];
if (isset($_SESSION['checkOut'])) $checkOut = $_SESSION['checkOut'];
if (isset($_SESSION['roomType'])) $roomType = $_SESSION['roomType'];

if (isset($_POST['process'])) {
    if ($_POST['process'] == 'getSearchResults') {
        $checkIn = $_POST['check_in'];
        $checkOut = $_POST['check_out'];
        $roomType = $_POST['room_type'];
        $_SESSION['checkIn'] = $checkIn;
        $_SESSION['checkOut'] = $checkOut;
        $_SESSION['roomType'] = $roomType;

        echo json_encode($plm->getSearchResults($checkIn, $checkOut, $roomType));

    } elseif ($_POST['process'] == 'datesChanged') {
        $checkIn = $_POST['check_in'];
        $checkOut = $_POST['check_out'];

        echo json_encode($plm->setDateRange($checkIn, $checkOut));
    }
    exit;
}
// remove the transID
unset($_SESSION['transID']);

$range = $plm->setDateRange($checkIn, $checkOut);

$roomTypes = $plm->roomTypes();

$additionalHeaders = '';
require_once('./page_header.php');
require_once('./page_nav.php');
?>
    <main>
        <div class="titlebar">
            <h1>The Desert Willow Ranch B&B</h1>
            <h2>Stay With Us</h2>
        </div>
        <h2>Book a Room or RV Pad</h2>
        <form method="post" id="searchDates">
            <div id="date_entry">
                <label for="check_in">
                    Check In
                    <input type="date" name="check_in" id="check_in" min="<?= date("Y-m-d")?>" value="<?=date('Y-m-d', strtotime($checkIn)) ?>" />
                </label>
                <label for="check_out">
                    Check Out
                    <input type="date" name="check_out" id="check_out" min="<?= date("Y-m-d", strtotime('now +1 day'))?>" value="<?=date('Y-m-d', strtotime($checkOut)) ?>" />
                </label>
                <label for="room_type">
                    Room Type
                    <select name="root_type" id="room_type" >
                        <?php
                        if (count($roomTypes)) {
                            foreach ($roomTypes as $type) {
                                echo '<option value="' . $type['roomTypeID'] . '" ';
                                if ($type['roomTypeID'] == $roomType) echo 'selected';
                                echo '>' . $type['roomType'] . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
                <button type="button" id="btnSearch">Search</button>
            </div>
        </form>
        <div id="results">
            <div id="room_results">
                <div>Selected: <span id="date_range"><?=$range['range']?></span> | <span id="num_nights"><?=$range['nights']?></span> night(s)</div>
                <button type="button" id="clear_results">Clear</button>
            </div>
            <section id="searchResults">
            </section>
        </div>

    </main>
<?php

require_once('./page_footer.php');

?>
<script>
    $( document).ready(function () {
        $("#check_in").on('change', function () {
            updateDates();
        });
        $("#check_out").on('change', function () {
            updateDates();
        });

        $("#btnSearch").on('click', function () {
            $.post('roomSearch.php', {
                process:    'getSearchResults',
                check_in: $("#check_in").val(),
                check_out: $("#check_out").val(),
                room_type: $("#room_type").val()
            }, function (result) {
                // console.log(result);
                let obj = JSON.parse(result);
                    if (Object.keys(obj).length > 0) {
                    // console.log(obj);
                    $("#date_range").html(obj['range']);
                    $("#num_nights").html(obj['nights']);
                    if (obj['rooms'] !== undefined) {
                        let output = '';
                        Object.keys(obj['rooms']).forEach(function (key) {
                            // show any error messages
                            output += '<div class="room_details">' +
                                '<div class="room_image">' +
                                '<img class="room_image" src="images/' + obj['rooms'][key]['image']['image'] + '" />' +
                                '</div>' +
                                '<div class="room_info">' +
                                '<h2>' + obj['rooms'][key]['roomName'] + '</h2>' +
                                '<p class="room_description">' + obj['rooms'][key]['details'] + '</p>' +
                                '<p class="room_amenities"></p>' +
                                '<a class="more_info" href="./room_details.php?roomID=' + obj['rooms'][key]['roomID'] + '">More info >></a>' +
                                '<div class="room_price_line">From <span class="room_price">$' + obj['rooms'][key]['price'] + '</span> per night</div>' +
                                '<button type="button" class="book_now_btn" data-roomID="' + obj['rooms'][key]['roomID'] + '">Book Now</button>' +
                                '</div>' +
                                '</div>';
                        });
                        $("#searchResults").html(output);
                    } else {
                        $("#searchResults").html(obj['error']);
                    }
                }

            });
        });

        $("#clear_results").on('click', function () {
            var d = new Date();
            $("#check_in").val(d.toISOString().substring(0, 10));
            d.setDate(d.getDate() + 1);
            $("#check_out").val(d.toISOString().substring(0, 10));
            $("#searchResults").html('');
            $("#date_range").html('-');
            $("#num_nights").html('0');

        });

        $("#searchResults").on('click', '.book_now_btn', function () {
            let roomID = $(this).attr('data-roomID');
            // append to search form data and submit
            let data = {};
            $('#myForm').serializeArray().map(function(x){data[x.name] = x.value;});
            data['process'] = 'book_now';
            data['roomID']  = roomID;
            // console.log(data);
            post_to_url('booking.php?action=booking_account', data, 'post', '_self'); // redirect to booking_account.php
        });

        // preload the search if dates are already set
        if ($("#check_in").val() !== '' && $("#check_out").val() !== '') {
            $("#btnSearch").click();
        }
    });

    function updateDates() {
        $.post('roomSearch.php', {
            process:    'datesChanged',
            check_in: $("#check_in").val(),
            check_out: $("#check_out").val()
        }, function (result) {
            // console.log(result);
            let obj = JSON.parse(result);
            if (Object.keys(obj).length > 0) {
                // console.log(obj);
                $("#date_range").html(obj['range']);
                $("#num_nights").html(obj['nights']);
            }
        });
    }

</script>
