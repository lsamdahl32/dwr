<?php

class TodayWidget
{
    private int $widID;
    private string $today;
    private string $db = 'plm';
    private array $arrivingToday, $stayingToday, $departingToday;

    public function __construct($widID)
    {
        $this->widID = $widID;
        $this->today = date('Y-m-d');
    }


    /**
     * @param string $process
     */
    public function processAJAXRequests(string $process)
    {

        $dbe = new DBEngine($this->db, false);

        // get list of guests arriving today // todo need the room name also
        $pq_query = 'SELECT * from `guests` g 
                    INNER JOIN `bookings` b ON b.`guestID` = g.`guestID`
                    INNER JOIN `rooms` r ON r.`roomID` = b.`roomID`
                    WHERE `checkIn` = ? 
                    ORDER BY `lastName`,`firstName`';
        $dbe->setBindtypes("s");
        $dbe->setBindvalues(array($this->today));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            foreach ($rows as $row) {
                $this->arrivingToday[] = $row['lastName'] . ', ' . $row['firstName'] . '; Room: ' . $row['roomName'];
            }
        }

        // get list of guests staying today
        $pq_query = 'SELECT * from `guests` g 
                    INNER JOIN `bookings` b ON b.`guestID` = g.`guestID`
                    INNER JOIN `rooms` r ON r.`roomID` = b.`roomID`
                    WHERE `checkIn` < ? AND `checkOut` > ? 
                    ORDER BY `lastName`,`firstName`';
        $dbe->setBindtypes("ss");
        $dbe->setBindvalues(array($this->today, $this->today));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            foreach ($rows as $row) {
                $this->stayingToday[] = $row['lastName'] . ', ' . $row['firstName'] . '; Room: ' . $row['roomName'];
            }
        }

        // get list of guests departing today
        $pq_query = 'SELECT * from `guests` g 
                    INNER JOIN `bookings` b ON b.`guestID` = g.`guestID`
                    INNER JOIN `rooms` r ON r.`roomID` = b.`roomID`
                    WHERE `checkOut` = ? 
                    ORDER BY `lastName`,`firstName`';
        $dbe->setBindtypes("s");
        $dbe->setBindvalues(array($this->today));
        $rows = $dbe->execute_query($pq_query);  // execute query
        if ($rows) {
            foreach ($rows as $row) {
                $this->departingToday[] = $row['lastName'] . ', ' . $row['firstName'] . '; Room: ' . $row['roomName'];
            }
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
        return 'Today';
    }

    public function showBody()
    {
        ?>
        <div style="overflow-y: auto;">
            <h4>Arriving Today</h4>
            <table class="report_table" style="margin-left: 1rem;">
                <tr>
                    <?php
                    if (isset($this->arrivingToday) and count($this->arrivingToday) > 0) {
                        foreach ($this->arrivingToday as $item) {
                            echo '<td>' . $item . '</td>';
                        }
                    } else {
                        echo '<td>None</td>';
                    }
                    ?>
                </tr>
            </table>
            <h4>Staying Over Today</h4>
            <table class="report_table" style="margin-left: 1rem;">
                <tr>
                    <?php
                    if (isset($this->stayingToday) and count($this->stayingToday) > 0) {
                        foreach ($this->stayingToday as $item) {
                            echo '<td>' . $item . '</td>';
                        }
                    } else {
                        echo '<td>None</td>';
                    }
                    ?>
                </tr>
            </table>
            <h4>Departing Today</h4>
            <table class="report_table" style="margin-left: 1rem;">
                <tr>
                    <?php
                    if (isset($this->departingToday) and count($this->departingToday) > 0) {
                        foreach ($this->departingToday as $item) {
                            echo '<td>' . $item . '</td>';
                        }
                    } else {
                        echo '<td>None</td>';
                    }
                    ?>
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
                refreshToday();

            });

            function refreshToday() {
                let widID = <?=$this->widID?>;
                $.post("index.php", {
                    widget:   'TodayWidget',
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