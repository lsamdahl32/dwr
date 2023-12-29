<?php

class YTDWidget
{
    private int $widID;
    private string $db = 'plm';
    private int $numGuests = 0, $numBookings = 0;
    private float $totalRevenue = 0;

    public function __construct($widID)
    {
        $this->widID = $widID;
    }


    /**
     * @param string $process
     */
    public function processAJAXRequests(string $process)
    {

        $dbe = new DBEngine($this->db, false);

        // get count of guests year to date
        $pq_query = 'SELECT count(DISTINCT g.`guestID`) as cnt from `guests` g 
                    INNER JOIN `bookings` b ON b.`guestID` = g.`guestID`
                    WHERE b.`checkIn` >= ? ';
        $dbe->setBindtypes("s");
        $dbe->setBindvalues(array(date('Y-m-d', strtotime('first day of january ' . date('Y')))));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            $this->numGuests = $rows[0]['cnt'];
        }

        // get total bookings and revenue year to date
        $pq_query = 'SELECT count(*) as cnt, sum(`totalPrice`) as tot from `bookings`
                    WHERE `checkIn` >= ? ';
        $dbe->setBindtypes("s");
        $dbe->setBindvalues(array(date('Y-m-d', strtotime('first day of january ' . date('Y')))));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            $this->numBookings = $rows[0]['cnt'];
            $this->totalRevenue = is_null($rows[0]['tot']) ? 0 : $rows[0]['tot'];
        }


        if ($process == 'refresh') {
            $this->showBody();
        }
        $dbe->close();
    }

    public function showDescription()
    {
        return '';
    }

    public function showHeading(): string
    {
        return 'Year to Date';
    }

    public function showBody()
    {
        ?>
        <div style="overflow-y: auto;">
            <table class="report_table" style="width: 100%;">
                <tr>
                    <td>Guests</td>
                    <td><?=$this->numGuests?></td>
                </tr>
                <tr>
                    <td>Bookings</td>
                    <td><?=$this->numBookings?></td>
                </tr>
                <tr>
                    <td>Revenue</td>
                    <td>$<?=number_format($this->totalRevenue, 2)?></td>
                </tr>
            </table>
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
            $( document ).ready(function () {
               refreshYTD();

            });

            function refreshYTD() {
                let widID = <?=$this->widID?>;
                $.post("index.php", {
                    widget:   'YTDWidget',
                    process:  'refresh',
                    widgetID: widID
                }, function (data) {
                    // console.log(data);
                    $("#widgetBody" + widID).html(data);
                });
            }

        </script>
        <?php
    }

    public function showCustomSettings(): string
    {
        return '<button type="button" data-widgetID="'.$this->widID.'" data-widget="TodayWidget" class="buttonBar altBackground refreshWidget" >
                    <i class="bi bi-arrow-clockwise smaller_icon"></i>
                    &nbsp;Refresh
                </button>';
    }

}