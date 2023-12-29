<?php
/**
 * PL Manager Admin Interface
 * Side Menu Home Page Template
 * @author Lee Samdahl
 *
 * @created 4/4/23
 */
ini_set('display_errors', 1); // todo remove for live version
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/classes/PLManager.php');

if (!isset($_SESSION['is_logged_in']) or $_SESSION['is_logged_in'] == false) {
    header('location: login.php');
}

// set up widgets array
$widgets = array(
    array('widgetID'=>1, 'title'=>'Today', 'includeFile'=>'TodayWidget.php', 'className'=>'TodayWidget', 'sortOrder'=>1, 'columns'=>1),
    array('widgetID'=>2, 'title'=>'Calendar', 'includeFile'=>'CalendarWidget.php', 'className'=>'CalendarWidget', 'sortOrder'=>2, 'columns'=>2),
    array('widgetID'=>3, 'title'=>'YearToDate', 'includeFile'=>'YTDWidget.php', 'className'=>'YTDWidget', 'sortOrder'=>3, 'columns'=>1),
//    array('widgetID'=>4, 'title'=>'Reports', 'includeFile'=>'Reports.php', 'className'=>'Reports', 'sortOrder'=>4, 'columns'=>1), // todo this
);

// handle AJAX calls from widgets
if (isset($_POST['widget']) and isset($_POST['process'])) {
    $id = $_POST['widgetID'];
    // get the widget record
//    $pq_query = 'SELECT * FROM `at_widgets` w INNER JOIN `at_user_widgets` u ON u.`widgetID` = w.`widgetID` WHERE w.`className` = ? AND u.`uwID` = ?  ';
//    $dba->setBindtypes('si');
//    $dba->setBindvalues(array($_POST['widget'], $id));
//    $widget = $dba->execute_query($pq_query);  // execute query
    foreach ($widgets as $widget) {
        if ($id == $widget['widgetID']) {
            // instantiate the widget
            include_once('./widgets/' . $widget['includeFile']);
            $wid = new $widget['className']($id);

            // call the processAJAXRequests function of the widget
            $wid->processAJAXRequests($_POST['process']);
            break;
        }
    }
//    $dba->close();
    exit;

} elseif (isset($_POST['process']) and $_POST['process'] == 'returnToSite') {
    // logout and return the url to the calling site
    unset($_SESSION['is_logged_in']);
    echo PLMSite . 'index.php';
    exit;
    
} elseif (isset($_POST['process']) and $_POST['process'] == 'searchBookings') {
    $plm = new PLManager();
    $rows = $plm->searchForBookings($_POST['search']);
    $output = '';
    if ($rows) { // format as a table
        $output = '<table class="report_table"><tr class="report_hdr_row"><th class="report_cell">Select</th><th class="report_cell">Name</th><th class="report_cell">Phone</th><th class="report_cell">Email</th><th class="report_cell">Check In</th></tr>';
        foreach($rows as $row) {
            // open button, Name, phone, email, checkIn
            $output .= '<tr>';
            $output .= '<td class="report_cell"><button class="buttonBar searchResultsOpen" data-bookingID="' . $row['bookingID'] . '"><i class="bi-arrow-return-right smaller_icon"></i>&nbsp;Open</button></td>';
            $output .= '<td class="report_cell">' . gls_esc_html($row['firstName'] . ' ' . $row['lastName']) . '</td>';
            $output .= '<td class="report_cell">' . gls_esc_html($row['phone']) . '</td>';
            $output .= '<td class="report_cell">' . gls_esc_html($row['email']) . '</td>';
            $output .= '<td class="report_cell">' . gls_esc_html($row['checkIn']) . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';
    } else {
        $output = 'There were no bookings found.';
    }
    echo $output;
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title><?=COMPANY_NAME?> Admin</title>
    <meta http-equiv="Content-Language" content="en-us">

    <link rel="stylesheet" href="./css/normalize.css" type="text/css">
    <link href="./css/side_menu_home.css" rel="stylesheet" type="text/css"/>
    <link href="./css/plm_admin.css" rel="stylesheet" type="text/css"/>

    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

    <script src="./js/plm_admin.js"></script>
    <?php
    if (isset($additionalHeaders) and $additionalHeaders !== '') {
        echo $additionalHeaders;
    }
    ?>
</head>

<body>
<div id="home_main_grid" class="home_main_grid_lcol_open">
    <div id="home_header">
        <div id="home_header_left">
            <div class="report_page_title" >
                <i class="bi-calendar-range" style="font-size: 24px;"></i>
                <h1><?=COMPANY_NAME?> Admin</h1>
            </div>
            <div id="home_header_left_extra">
            </div>
        </div>
        <div id="home_header_right">
            <button type="button" class="buttonBar" id="btn_return_to_site">
                <i class="bi-arrow-return-left"></i>&nbsp;
                Return to Website
            </button>
            <a class="buttonBar" href="./help.html"
               target="_blank" title="Open help documentation for PLM Admin.">
                <i class="bi-question-square"></i>
            </a>
        </div>
    </div>
    <div id="home_left_col">
        <div id="home_left_col_header">
            <button class="buttonBar" id="home_show_hide_menu" title="Show/Hide Menu">
                <i id="homeShowMenu" class="bi-caret-right" style="font-size: 24px; display: none;"></i>
                <i id="homeHideMenu" class="bi-caret-left" style="font-size: 24px;"></i>
            </button>
            <h3 id="home_left_col_heading">Menu</h3>
        </div>
        <div id="home_left_col_menu" >
            <!-- menu inserted here -->
            <div id="adminMenu">
                <ul>
                    <li data-page="Dashboard">
                        <a id="Dashboard" class="menuSelected" href="#" data-type="dashboardView">
                            Dashboard
                        </a>
                    </li>
                    <li data-page="Guests">
                        <a id="Guests" href="#" data-type="guestsView">
                            Guests
                        </a>
                    </li>
                    <li data-page="Bookings">
                        <a id="Bookings" href="#" data-type="bookingsView">
                            Bookings
                        </a>
                    </li>
                    <li data-page="Rooms">
                        <a id="Rooms" href="#" data-type="roomsView" >
                            Rooms
                        </a>
                    </li>
                    <li data-page="Amenities">
                        <a id="Amenities" href="#" data-type="amenitiesView" >
                            Amenities
                        </a>
                    </li>
                    <li data-page="Attractions">
                        <a id="Attractions" href="#" data-type="attractionsView">
                            Attractions
                        </a>
                    </li>
                    <li data-page="Settings">
                        <a id="Settings" href="#" data-type="settingsView">
                            Settings
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div id="home_right_col" >
        <div id="home_right_col_main_pages" >
            <div class="home_right_col_content" id="dashboardView" style="display: block;" >
                <div id="home_right_col_titlebar">
                    <h1>Dashboard</h1>
                    <div id="home_right_col_titlebar_right">
                        <input id="searchBookings" placeholder="Search for bookings" style="font-size: large;" title="Search for current or past bookings by guest name, email, or phone #."/>
                        <button class="buttonBar " type="button" id="btnSearchBookings" >
                            <i class="bi-search" style="font-size: 18px;"></i>
                        </button>
                    </div>
                </div>
                <section id="widgetsDiv" class="widgetsDiv">
                    <div class="card" id="searchBookingsResults" style="display: none; order: 0; flex-basis: 340px;">
                        <div class="card-body">
                            <header>
                                <h3>Search Results</h3>
                                <button class="widgetSettingsBtn buttonBar" id="btnCloseSearchResults" title="Close Search Results" >
                                    <i class="bi bi-x-square"></i>
                                </button>
                            </header>
                            <div class="widgetBody" id="searchBookingsResultsContents"></div>
                        </div> 
                    </div>
                    <!-- Other Widgets -->
                    <?php
                    $i = 1;
                    foreach ($widgets as $widget) {
                        include_once('./widgets/'.$widget['includeFile']);
                        $wid = new $widget['className']($widget['widgetID']);
                        if ($widget['columns'] > 1) {
                            $size = 'flex-basis: ' .  ($widget['columns'] * 400) . 'px;';
                        } else {
                            $size = '';
                        }
                        ?>
                        <div class="card dragToSort" id="card_<?=$widget['widgetID']?>" draggable="true" data-widClassName="<?=$widget['className']?>" style="order: <?=$i?>; <?=$size?>">
                            <div class="card-body" style="position:relative;">
                                <div class="widgetSettings" id="widgetSettings<?=$widget['widgetID']?>" style="display: none;">
                                    <div id="widgetCustomSettings"><?=$wid->showCustomSettings()?></div>
                                </div>
                                <header>
                                    <h3><?=$wid->showHeading()?></h3>
                                    <?=$wid->showDescription()?>

                                    <button class="widgetSettingsBtn buttonBar" title="Widget Settings" data-widgetID="<?=$widget['widgetID']?>">
                                        <i class="bi-list"></i>
                                    </button>
                                </header>
                                <div class="widgetBody" id="widgetBody<?=$widget['widgetID']?>">
                                    <?php $wid->showBody(); ?>
                                </div>
                                <?php $wid->showJavascript(); ?>
                            </div>
                        </div>
                        <?php
                        $i++;
                    }
                    ?>
                </section>
                <?php require(PLMPATH . "admin/footer.php"); ?>
            </div>
            <div class="home_right_col_content" id="guestsView" style="display: none; ">
                <iframe src="./guests.php" ></iframe>
            </div>
            <div class="home_right_col_content" id="bookingsView" style="display: none;">
                <iframe src="./bookings.php"></iframe>
            </div>
            <div class="home_right_col_content" id="roomsView" style="display: none;">
                <iframe src="./rooms.php"></iframe>
            </div>
            <div class="home_right_col_content" id="amenitiesView" style="display: none;">
                <iframe src="./amenities.php"  "></iframe>
            </div>
            <div class="home_right_col_content" id="attractionsView" style="display: none;">
                <iframe src="./attractions.php"></iframe>
            </div>
            <div class="home_right_col_content" id="settingsView" style="display:none;">
                <iframe src="./settings.php"></iframe>
            </div>
        </div>
    </div>
</div>

<script>

    $( document ).ready(function () {

        $("#home_show_hide_menu").on('click', function () {
            if ($("#homeShowMenu").is(':visible')) {
                showHideMenu(true);
            } else {
                showHideMenu(false);
            }
        });

        $("#btn_return_to_site").on('click', function () {
            $.post('index.php', {
                process: 'returnToSite',
            }, function (data) {
                window.open(data); // new window
            });
        });        

        $("#btnSearchBookings").on('click', function() {
            searchForBookings();
        });

        // if enter is pressed in #searchBookings, call search function
        $("#searchBookings").on('keyup', function (event) {
            if (event.keyCode === 13) {
                searchForBookings();
            }
        });

        $("#btnCloseSearchResults").on('click', function() {
            $('#searchBookingsResults').hide(200);
        });

        $('#searchBookingsResults').on('click', ".searchResultsOpen", function() {
            // open bookings page
            parent.$("#Bookings").click();
            parent.$('#bookingsView > iframe')[0].contentWindow.searchBookingID($(this).attr('data-bookingID'));
        });

    });

    function showHideMenu(state) {
        if (state) {
            $("#homeShowMenu").hide();
            $("#homeHideMenu").show();
            $("#home_main_grid").addClass('home_main_grid_lcol_open').removeClass('home_main_grid_lcol_closed')
            $("#home_left_col").css('z-index', 3);
        } else {
            $("#homeShowMenu").show();
            $("#homeHideMenu").hide();
            $("#home_main_grid").addClass('home_main_grid_lcol_closed').removeClass('home_main_grid_lcol_open')
            $("#home_left_col").css('z-index', 0);
        }
    }

    // Widgets
    $("#widgetsDiv").on('click', '.widgetSettingsBtn', function () {
        let userWidgetID = $(this).attr('data-widgetID');
        $(".widgetSettings").hide(300);
        $(document).off('click.closeSettings');
        $("#widgetSettings" + userWidgetID).show(300);
        // trap clicks outside of current widget
        $(document).on('click.closeSettings', function (e) {
            if ($(e.target).closest("#card_"+userWidgetID).length === 0) {
                $(".widgetSettings").hide(300);
                $(document).off('click.closeSettings');
            }
        });

        // drag and drop widgets
    }).on('drop', '.dragToSort', function (e) { // Drag and drop to sort widgets
        //drop
        e.preventDefault();
        e.stopPropagation();
        target = e.currentTarget.id;
        // console.log(e.currentTarget.id);
        if (source !== '' && target !== '') {
            // console.log('Here '+source+' - '+target);
            let sourceEl = $("#" + source);
            let sourceOrder = Number(sourceEl.css('order'));
            let targetOrder = Number($("#" + target).css('order'));
            // reset the css order for each
            if (targetOrder < sourceOrder) {
                $("#widgetsDiv").children().each(function () {
                    let order = Number($(this).css('order'));
                    if (order >= targetOrder) {
                        $(this).css('order', Number(order + 1));
                        saveWidgetSetting(this.id.substring(5), 'sortOrder', order + 1);
                    }
                    // console.log(this.id + ' - ' + $(this).css('order'));
                });
            } else if (targetOrder > sourceOrder) {
                $($("#widgetsDiv").children().get().reverse()).each(function () {
                    let order = $(this).css('order');
                    if (order <= targetOrder) {
                        $(this).css('order', Number(order - 1));
                        saveWidgetSetting(this.id.substring(5), 'sortOrder', order - 1);
                    }
                    // console.log(this.id + ' - ' + $(this).css('order'));
                });
            }
            // now change the source's order
            sourceEl.css('order', targetOrder);
            saveWidgetSetting(source.substring(5), 'sortOrder', targetOrder);
        }

    }).on('dragstart', '.dragToSort', function (e) {
        //start drag
        source = e.target.id;
        // console.log(source);

    }).on('dragover', '.dragToSort', function (e) {
        //drag over
        // console.log(e.currentTarget.id);
        e.preventDefault();

    }).on('dragend', '.dragToSort', function () {
        $(".canDropHere").removeClass("canDropHere");
        source = '';

    }).on('dragenter', '.dragToSort', function (e) {
        e.stopPropagation();
        e.preventDefault();
        $("#" + e.currentTarget.id).addClass("canDropHere");
        return false;

    }).on('dragleave', '.dragToSort', function (e) {
        e.stopPropagation();
        e.preventDefault();
        $(".canDropHere").removeClass("canDropHere");
        return false;

    }).on('click', '.refreshWidget', function () {
        let widgetID = $( this ).attr('data-widgetID');
        let widget = $( this ).attr('data-widget');
        $(".widgetSettings").hide(300);
        $.post("index.php", {
            widget:   widget,
            process:  'refresh',
            widgetID: widgetID
        }, function (data) {
            // console.log(data);
            $("#widgetBody" + widgetID).html(data);
        });
    });


</script>

<?php
