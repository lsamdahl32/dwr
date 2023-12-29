<?php

class CalendarWidget
{
    private int $widID;
    private string $db = 'plm';
    private string $currentCalendar;
    private string $weekStart, $weekEnd;
    private string $monthStart, $monthEnd;
    private string $yearStart;

    private array $colorArray = array('#1a5173','#cf2424','#f58a4b','#46ac82','#46ac82','#008ce4','#3366cc','#054c86','#0681d8','#929292');

    public function __construct($widID)
    {
        $this->widID = $widID;
        $this->currentCalendar = 'Month';

        $this->weekStart = date('Y-m-d', strtotime('sunday -1 week'));
        $this->monthStart = date('Y-m-d', strtotime('first day of this month'));
        $this->monthEnd = date('Y-m-d', strtotime('last day of this month'));

        $this->yearStart = date('Y');
    }


    /**
     * @param string $process
     */
    public function processAJAXRequests(string $process)
    {
        if ($process == 'refresh') {
            $this->currentCalendar = $_POST['currCalendar'];
            if ($this->currentCalendar == 'Week') {
                echo '<div>' . $this->getWeek($this->weekStart) . '</div>';

            } elseif ($this->currentCalendar == 'Month') {
                echo $this->getCalendar('month', $this->monthStart, $this->monthEnd);

            } elseif ($this->currentCalendar == 'Year') {
                for($i = 0; $i < 12; $i++) {
                    $start = date('Y-m-d', mktime(0,0,0, $i + 1, 1, $this->yearStart));
                    $end = date('Y-m-d', mktime(0,0,0, $i + 2, -1, $this->yearStart));
                    echo '<div>' . $this->getCalendar('year', $start, $end) . '</div>';
                }
            }
        } elseif ($process == 'calendarNav') {
            $this->currentCalendar = $_POST['currCalendar'];
            // must not be less than the current period
            if ($this->currentCalendar == 'Week') {
                $this->weekStart = $_POST['start'];
                echo '<div>' . $this->getWeek($this->weekStart) . '</div>';

            } elseif ($this->currentCalendar == 'Month') {
                $this->monthStart = $_POST['start'];
                $this->monthEnd = date('Y-m-t', strtotime($this->monthStart));
                echo $this->getCalendar('month', $this->monthStart, $this->monthEnd);

            } elseif ($this->currentCalendar == 'Year') {
                $this->yearStart = intval($_POST['start']);
                for($i = 0; $i < 12; $i++) {
                    $start = date('Y-m-d', mktime(0,0,0, $i + 1, 1, $this->yearStart));
                    $end = date('Y-m-d', mktime(0,0,0, $i + 2, -1, $this->yearStart));
                    echo '<div>' . $this->getCalendar('year', $start, $end) . '</div>';
                }
            }
        }
    }

    public function showDescription()
    {
        return '';
    }

    public function showHeading(): string
    {
        return 'Upcoming Bookings';
    }

    public function showBody()
    {
        ?>
        <div style="overflow-y: auto;">
            <div class="calendarButtons">
                <button type="button" class="buttonBar btnCalendarNav" id="btnCalendarPrev">
                    <i class="bi-arrow-left"></i>&nbsp;
                    Previous
                </button>
                <button type="button" class="buttonBar btnCalendar" id="btnCalendarWeek">
                    <i class="bi-calendar-week"></i>&nbsp;
                    Week
                </button>
                <button type="button" class="buttonBar btnCalendar" id="btnCalendarMonth">
                    <i class="bi bi-calendar-month"></i>&nbsp;
                    Month
                </button>
                <button type="button" class="buttonBar btnCalendar" id="btnCalendarYear">
                    <i class="bi bi-calendar"></i>&nbsp;
                    Year
                </button>
                <button type="button" class="buttonBar btnCalendarNav" id="btnCalendarNext">
                    <i class="bi-arrow-right"></i>&nbsp;
                    Next
                </button>
            </div>
            <div class="calendars calendarWeek" id="calendarWeek" style="display: <?=($this->currentCalendar == 'Week')? 'block;':'none;'?>">
            </div>
            <div class="calendars calendarMonth" id="calendarMonth" style="display: <?=($this->currentCalendar == 'Month')? 'block;':'none;'?>">
            </div>
            <div class="calendars calendarYear" id="calendarYear" style="display: <?=($this->currentCalendar == 'Year')? 'flex;':'none;'?>;">
            </div>
        </div>
        <?php
    }

    /**
     * @param bool $isFeatured
     */
    public function showJavascript(bool $isFeatured = false)
    {
        ?>
        <script>
            let widID = <?=$this->widID?>;
            let currCalendar = '<?=$this->currentCalendar?>';
            let weekStart = '<?=$this->weekStart?>';
            let monthStart = '<?=$this->monthStart?>';
            let yearStart = <?=$this->yearStart?>;

            $( document ).ready(function () {
                refreshCalendar();

                $("#widgetsDiv").on('click', '.btnCalendar', function () {
                    currCalendar = this.id.substring(11);
                    $(".calendars").hide(200);
                    $("#calendar" + currCalendar).show(200);
                    refreshCalendar();

                }).on('click','.btnCalendarNav', function () {
                    let direction = this.id.substring(11);
                    let start = '';
                    if (currCalendar === 'Week') {
                        var d = new Date(weekStart);
                        if (direction === 'Next') {
                            d.setDate(d.getDate() + 7);
                        } else {
                            const today = new Date();
                            // Get the first day of the current week (Sunday)
                            const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));

                            if (d.toISOString() < firstDay.toISOString()) {
                                // don't let month go below current week
                            } else {
                                d.setDate(d.getDate() - 7);
                            }
                        }
                        start = d.toISOString().substring(0, 10);
                    } else if (currCalendar === 'Month') {
                        var d = new Date(monthStart);
                        if (direction === 'Next') {
                            d.setMonth(d.getMonth() + 1);
                        } else {
                            let d2 = new Date();
                            if (d.getMonth() <= d2.getMonth() && d.getFullYear() === d2.getFullYear()) {
                                // don't let month go below current month
                            } else {
                                d.setMonth(d.getMonth() - 1);
                            }
                        }
                        d.setDate(1);
                        start = d.toISOString().substring(0, 10);
                    } else if (currCalendar === 'Year') {
                        if (direction === 'Next') {
                            start = yearStart + 1;
                        } else {
                            let d = new Date();
                            if (yearStart > d.getFullYear()) { // don't let year go below current year
                                start = yearStart - 1;
                            }
                        }
                    }

                    $.post("index.php", {
                        widget:   'CalendarWidget',
                        process:  'calendarNav',
                        widgetID: widID,
                        currCalendar: currCalendar,
                        start:  start,
                    }, function (data) {
                        // console.log(data);
                        if (currCalendar === 'Week') {
                            weekStart = start;
                            $("#calendar" + currCalendar).html(data);
                        } else if (currCalendar === 'Month') {
                            monthStart = start;
                            $("#calendar" + currCalendar).html(data);
                        } else if (currCalendar === 'Year') {
                            yearStart = start;
                            $("#calendar" + currCalendar).html(data);
                        }
                    });

                }).on('click','.calendars td', function () { // add a new booking from calendar
                    let thisDate = this.id.substring(7, 11) + '-' + this.id.substring(11, 13) + '-' + this.id.substring(13, 15);
                    let yourDate = new Date()
                    if (thisDate >= yourDate.toISOString().split('T')[0]) {
                        // open bookings page
                        parent.$("#Bookings").click();
                        parent.$('#bookingsView > iframe')[0].contentWindow.newBooking(thisDate);
                    }

                }).on('click', '.calBookings div', function(e) { // open existing booking from calendar
                    e.stopPropagation();
                    e.preventDefault();
                    bookingID = $(this).attr('data-bookingID');
                    if (Number(bookingID) > 0) {
                        // open bookings page
                        parent.$("#Bookings").click();
                        parent.$('#bookingsView > iframe')[0].contentWindow.searchBookingID(bookingID);
                    }
                });

            });

            function refreshCalendar() {
                $.post("index.php", {
                    widget:   'CalendarWidget',
                    process:  'refresh',
                    currCalendar: currCalendar,
                    widgetID: widID
                }, function (data) {
                    // console.log(data);
                    $("#calendar" + currCalendar).html(data);
                });
            }

        </script>
        <?php
    }

    public function showCustomSettings(): string
    {
        return '<button type="button" data-widgetID="'.$this->widID.'" data-widget="CalendarWidget" class="buttonBar altBackground refreshWidget" >
                    <i class="bi bi-arrow-clockwise smaller_icon"></i>
                    &nbsp;Refresh
                </button>';
    }

    private function getWeek($startDate)
    {
        $today    = date('Y-m-d');
        $cals = '<table>
                        <thead><tr class="report_hdr_row"><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
                        <tbody><tr>';
        for($i = 0; $i < 7; $i++) { // Sun - Sat
            $eachday = date('Y-m-d', strtotime($startDate . ' +' . $i . ' days'));
            // check if any bookings exist for this date and show lines
            $rows = $this->getBookingsForDate($eachday);
            $bookings = '';
            $bgColor = '';
            if ($rows) {
                $bookings = '<div class="calBookings">';
                foreach ($rows as $row) {
                    $title = 'Check In: ' . $row['checkIn'] . '; Check Out: ' . $row['checkOut'] . '. Click to open.';
                    $bookings .= '<div style="background-color: ' . $this->getBookingColor($row['roomID']) . '; color: white;" data-bookingID="' . $row['bookingID'] . '" title="' . $title . '">' . $row['roomName'] . '</div>';
                }
                $bookings .= '</div>';
            }
            if ($eachday < $today) {
                $bgColor = ' style="background-color: var(--past-background);"';
            }
            $id = 'calDate' . date('Ymd', strtotime($eachday));
            $cals .= '<td id="' . $id . '" ' . $bgColor . ' title="Click to add a new booking."><div class="calDate">' . date('M j, Y', strtotime($eachday)) . '</div>' . $bookings . '</td>';
        }
        $cals .= '</tr></tbody></table>';
        return $cals;
    }

    private function getCalendar($type, $startDate, $endDate)
    {
        // create the calendar html
        $start    = new DateTime($startDate);
        $start->modify('first day of this month');
        $end      = new DateTime($endDate);
        $interval = DateInterval::createFromDateString('1 month');
        $period   = new DatePeriod($start, $interval, $end);
        $today    = date('Y-m-d');

        $cals = '';
        foreach ($period as $dt) {
            // show the name of the month and year
            $cals .= '<div class="calendarMonthTitle">' . $dt->format("M Y") . "</div>";
            // get the day of the week of the first day
            $dow = date_format($dt, 'w');
            $cals .= '<table id="calendarTable">
                        <thead><tr class="report_hdr_row"><th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th></tr></thead>
                        <tbody><tr>';
            $j = 0;
            // create any blank days
            for ($i = 0; $i < $dow; $i++) {
                $cals .= '<td style="background-color: var(--past-background);"></td>';
                $j++;
            }
            // loops days of the month, show number and highlight if necessary
            for ($i = 0; $i < date_format($dt, 't'); $i++) {
                $eachday = date('Y-m-d', mktime(0, 0, 0, date_format($dt, 'n'), ($i + 1), date_format($dt, 'Y')));
                $bgColor = '';
                if ($eachday < $today) {
                    $bgColor = ' style="background-color: var(--past-background);"';
                }
                // check if any bookings exist for this date and show lines
                $bookings = '';
                $cellColor = $bgColor;
                $rows = $this->getBookingsForDate($eachday);
                if ($rows) {
                    if ($type == 'month') {
                        $bookings = '<div class="calBookings">';
                        foreach ($rows as $row) {
                            $title = 'Check In: ' . $row['checkIn'] . '; Check Out: ' . $row['checkOut'] . '. Click to open.';
                            $bookings .= '<div style="background-color: ' . $this->getBookingColor($row['roomID']) . '; color: white;" data-bookingID="' . $row['bookingID'] . '" title="' . $title . '">' . $row['roomName'] . '</div>';
                        }
                        $bookings .= '</div>';
                    } elseif ($type == 'year') {
                        $cellColor = 'style="background-color: ' . $this->getBookingColor(count($rows)) . '; color: white;" title="' . count($rows) . ' booking(s) on this date."';
                    }
                }
                $id = 'calDate' . date('Ymd', strtotime($eachday));
                $cals .= '<td id="' . $id . '" ' . $cellColor . ' title="Click to add a new booking."><div class="calDate">' . ($i + 1) . '</div>' . $bookings . '</td>';
                $j++;
                if ($j > 6) { // every 7 days, add a new row
                    $cals .= '</tr><tr>';
                    $j = 0;
                }
            }
            if ($j < 7) { // if did not fill last row
                for ($k = $j; $k < 7; $k++) {
                    $cals .= '<td style="background-color: var(--past-background);"></td>';
                }
            }
            $cals .= '</tr></tbody></table>';
        }
        return $cals;
    }

    private function getBookingsForDate($eachday)
    {
        $dbe = new DBEngine($this->db);
        $pq_query = 'SELECT b.bookingID, b.roomID, r.roomName, b.checkIn, b.checkOut 
                    FROM `bookings` b INNER JOIN `rooms` r ON r.`roomID` = b.`roomID` 
                    WHERE `checkIn` <= ? AND `checkOut` > ? ORDER BY `roomID` ';
        $dbe->setBindtypes('ss');
        $dbe->setBindvalues(array($eachday, $eachday));
        $rows = $dbe->execute_query($pq_query);
        $dbe->close();
        return $rows;
    }

    private function getBookingColor($roomID)
    {
        $colornum = ($roomID % 10);
        return $this->colorArray[$colornum];
    }
}