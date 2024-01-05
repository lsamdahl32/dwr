<?php
/**
 * PLManager Class
 * Handle all front end processing for Property and Lease Management
 * @author Lee Samdahl
 * @company Gleesoft.com
 * @copyright 2023, Gleesoft, LLC
 *
 * @created 5/5/23
 */
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/authorizenet/authorizenetAPI.php');

class PLManager
{
    private $dbe;

    const PMT_STATUS_COMPLETED = 1;
    const PMT_STATUS_PENDING = 2;
    const PMT_STATUS_FAILED = 3;
    const PMT_STATUS_DENIED = 4;
    const PMT_STATUS_REFUNDED = 5;
    const PMT_STATUS_CANCELLED_REVERSAL = 6;
    const PMT_STATUS_REVERSED = 7;

    const BOOKING_STATUS_PENDING = 1;
    const BOOKING_STATUS_PROCESSING = 2;
    const BOOKING_STATUS_COMPLETED = 3;
    const BOOKING_STATUS_UPDATED = 4;
    const BOOKING_STATUS_CANCELLED = 5;
    const BOOKING_STATUS_VOIDED = 6;
    const BOOKING_STATUS_BLACKOUT = 7;

    public function __construct()
    {
        $this->dbe = new DBEngine(PLMDB);
    }

    /**
     * Returns an array of all roomTypes
     * in the format of the db records
     * @return array
     */
    public function roomTypes():array
    {
        $rows = $this->dbe->getRowsWhere('roomTypes',array(),array(),array('sortOrder'));
        if ($rows) {
            return $rows;
        } else {
            return array();
        }
    }

    /**
     * Get Room Record
     * @param int $roomID
     * @return array
     */
    public function getRoom(int $roomID):array
    {
        $room = $this->dbe->getRowWhere('rooms', 'roomID', $roomID);
        if ($room) {
            return $room;
        } else {
            return array();
        }
    }

    /**
     * Get Room Price
     * @param int $roomID
     * @param string $checkIn
     * @return mixed
     */
    public function getRoomPrice(int $roomID, $checkIn)
    {
        // get the most recent pricing
        $pq_query = 'SELECT * FROM `pricing` WHERE `roomID` = ? AND `asOf` <= ? ORDER BY `asOf` DESC ';
        $this->dbe->setBindtypes('is');
        $this->dbe->setBindvalues(array($roomID, date('Y-m-d 00:00:00', strtotime($checkIn))));
        $price = $this->dbe->execute_query($pq_query);
        if ($price) {
            return $price[0]['amount'];
        } else {
            return 'Not Set';
        }
    }

    /**
     * Get Room Amenities
     * @param int $roomID
     * @return array
     */
    public function getRoomAmenities(int $roomID)
    {
        $pq_query = 'SELECT * FROM `roomAmenities` r INNER JOIN `amenities` a ON a.`amenityID` = r.`amenityID`  WHERE r.`roomID` = ? AND r.`isOptional` = ? AND a.`isAvailable` = ? ORDER BY r.`sortOrder` ';
        $this->dbe->setBindtypes('iii');
        $this->dbe->setBindvalues(array($roomID, 0, 1));
        $amenities = $this->dbe->execute_query($pq_query);
        if ($amenities) {
            return $amenities;
        } else {
            return array();
        }
    }

    /**
     * Get Optional Amenities
     * @return mixed
     */
    public function getOptionalAmenities()
    {
        $pq_query = 'SELECT * FROM `amenities` WHERE `isOptional` = ? AND `isAvailable` = ? ORDER BY `sortOrder` ';
        $this->dbe->setBindtypes('ii');
        $this->dbe->setBindvalues(array(1, 1));
        $amenities = $this->dbe->execute_query($pq_query);
        if ($amenities) {
            return $amenities;
        } else {
            return 'No Options Available';
        }
    }

    /**
     * Get Room Images
     * @param int $roomID
     * @param bool $primaryOnly
     * @return array
     */
    public function getRoomImages(int $roomID, bool $primaryOnly = false)
    {
        $pq_query = 'SELECT * FROM `roomImages` WHERE `roomID` = ? AND `online` = ?  ORDER BY `sortOrder` ';
        $this->dbe->setBindtypes('ii');
        $this->dbe->setBindvalues(array($roomID,1));
        $images = $this->dbe->execute_query($pq_query);
        if ($images) {
            if ($primaryOnly) {
                return $images[0];
            } else {
                return $images;
            }
        } else {
            return array();
        }
    }

    /**
     * Get Search Results
     *  search for any rooms of roomType available for all dates between $checkIn and $checkOut
     * @param string $checkIn
     * @param string $checkOut
     * @param int $roomTypeID
     * @return array
     */
    public function getSearchResults($checkIn, $checkOut, $roomTypeID)
    {
        $output = array();
        // get the total nights in range
        $begin = new DateTime($checkIn);
        $end = new DateTime($checkOut);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        $nights = $end->diff($begin)->format("%a");
        // get all of roomType
        $pq_query = 'SELECT * FROM `rooms` WHERE `isAvailable` = ? AND `roomTypeID` = ? AND `minNights` <= ? ORDER BY `roomName`';
        $this->dbe->setBindtypes('iii');
        $this->dbe->setBindvalues(array(1, $roomTypeID, $nights));
        $rows = $this->dbe->execute_query($pq_query);
        if ($rows) {
            // loop by rooms
            for ($i = count($rows)-1; $i >= 0; $i--) {
                // get the pricing
                $rows[$i]['price'] = $this->getRoomPrice($rows[$i]['roomID'], $checkIn);
                // get main image for this room
                $rows[$i]['image'] = $this->getRoomImages($rows[$i]['roomID'], true);
                // loop by date in range
                foreach ($period as $dt) {
                    $day = $dt->format("Y-m-d");
                    // check for any bookings for that date
                    $pq_query = 'SELECT * FROM `bookings` WHERE `roomID` = ? AND ? BETWEEN `checkIn` AND DATE_SUB(`checkOut`, INTERVAL 1 DAY) ';
                    $this->dbe->setBindtypes('is');
                    $this->dbe->setBindvalues(array($rows[$i]['roomID'], $day));
                    $foundRows = $this->dbe->execute_query($pq_query);
                    // if found, remove from array
                    if ($foundRows) {
                        unset($rows[$i]);
                        break;
                    }
                }
            }
        } else {
            $rows = array();
        }
        if (count($rows) > 0) {
            // return resulting array
            $output['range'] = date('D, M j, Y', strtotime($checkIn)) . ' to ' . date('D, M j, Y', strtotime($checkOut));
            $output['nights'] = $nights;
            $output['rooms'] = $rows;
        } else {
            $output['range'] = date('D, M j, Y', strtotime($checkIn)) . ' to ' . date('D, M j, Y', strtotime($checkOut));
            $output['nights'] = $nights;
            $output['error'] = 'No rooms were found that fit this criteria.';
        }
        return $output;
    }

    /**
     * Set Date Range
     * @param string $checkIn
     * @param string $checkOut
     * @return array
     */
    public function setDateRange($checkIn, $checkOut)
    {
        $output = array();
        if (strtotime($checkIn) > strtotime('now -1 day') and strtotime($checkOut) > strtotime('now')) {
            $nights = $this->getNightCount($checkIn, $checkOut);

            $output['range'] = date('D, M j, Y', strtotime($checkIn)) . ' to ' . date('D, M j, Y', strtotime($checkOut));
            $output['nights'] = $nights;
        } else {
            $output['range'] = '-';
            $output['nights'] = '0';
        }

        return $output;
    }

    public function getNightCount($checkIn, $checkOut)
    {
        // get the total nights in range
        $begin = new DateTime($checkIn);
        $end = new DateTime($checkOut);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        return $end->diff($begin)->format("%a");
    }

    /**
     * Process Booking
     * todo needs to return errors
     * @param string $bookedBy
     * @return bool
     */
    public function processBooking($bookedBy):bool
    {
        $anet = new authorizenetAPI();

        if ($this->validateBookingItems($_SESSION['checkIn'], $_SESSION['checkOut'], $_SESSION['roomID'])) {
            // save pending booking and get bookingID (used as invoice number)
            $_SESSION['paymentStatus'] = self::PMT_STATUS_PENDING;
            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_PENDING;
            $this->dbe->beginTrans();
            $result = $this->saveGuestInfo();
            $bookingID = $this->saveNewBookingInfo($bookedBy);
            if ($result > 0 and $bookingID > 0) {
                $_SESSION['guest'] = $this->getGuestInfo($result);
                $_SESSION['booking'] = $this->getBookingInfo($bookingID);
                $totalCharge = unserialize($_SESSION['bookingTotals']['total'])->getAmount();
                $paymentID =$this->savePaymentRecord(
                                $bookingID, 
                                $_SESSION['guest']['guestID'], 
                                $totalCharge, 'credit', 
                                'card-' . substr($_POST['cardNum'], -4), 
                                '', 
                                $_SESSION['paymentStatus'], 
                                ''
                            );
            } else {
                $this->dbe->rollbackTrans();
                return false;
            }

            // For testing:
            // $_SESSION['guest']['zip'] = '85748';
            // $_SESSION['guest']['zip'] = '46282'; // declined
            // $_SESSION['guest']['zip'] = '46203'; // AVS - E, AVS data provided is invalid or AVS is not allowed for the card type that was used.
            // $_SESSION['guest']['zip'] = '46217'; // 
            // print_r($_SESSION['guest']);exit;

            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_PROCESSING;
            // send payment info to CC handler
            $response = $anet->chargeCreditCard(
                                $_SESSION['amountPaid'],
                                $_POST['cardNum'],
                                $_POST['expDate'],
                                $_POST['cvv'],
                                $_SESSION['booking']['bookingID'],
                                'Booking Charges', // todo add more detail?
                                $_SESSION['guest']
            );

            if ($response != null) {
                // echo 'HERE1 '; 
                // Check to see if the API request was successfully received and acted upon
                // print_r($response);
                if ($response->getMessages()->getResultCode() == 'Ok') {
                    // echo 'HERE2 ';
                    // Since the API request was successful, look for a transaction response
                    // and parse it to display the results of authorizing the card
                    $tresponse = $response->getTransactionResponse();
                            
                    // echo 'HERE3 '; 

                    switch ($tresponse->getResponseCode()) { 
                        
                        case 1: // the payment was approved
                            // echo 'HERE4 '; 
                            //    echo " Successfully created transaction with Transaction ID: " . $tresponse->getTransId() . "\n";
                            //    echo " Transaction Response Code: " . $tresponse->getResponseCode() . "\n";
                            //    echo " Message Code: " . $tresponse->getMessages()[0]->getCode() . "\n";
                            //    echo " Auth Code: " . $tresponse->getAuthCode() . "\n";
                            //    echo " Description: " . $tresponse->getMessages()[0]->getDescription() . "\n";
                            unset($_SESSION['transID']); // clear the transID to prevent re-entry
                            $_SESSION['paymentStatus'] = self::PMT_STATUS_COMPLETED;
                            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_COMPLETED;
                            $_SESSION['txnID'] = $tresponse->getTransId();
                            if ($this->saveBookingInfo($bookingID, $paymentID)) {
                                // echo 'HERE5 '; 
                                $this->dbe->commitTrans();
                                // log the response
                                $this->logResponse($tresponse);
                                $_SESSION['booking'] = $this->getBookingInfo($bookingID);
                                unset($_SESSION['errorCode']);
                                unset($_SESSION['errorMessage']);
                                return true;
                            } else {
                                // This should not happen!
                                $this->dbe->rollbackTrans();
                                // log the response
                                $this->logResponse($tresponse);
                                $_SESSION['errorCode'] = 0;
                                $_SESSION['errorMessage'] = 'Failed to save booking info.';
                            }
                            break;

                        case 2: // Declined
                            $_SESSION['paymentStatus'] = self::PMT_STATUS_DENIED;
                            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_CANCELLED;
                            // log the response
                            $this->logResponse($tresponse);
                            break;

                        case 3: // Error
                            $_SESSION['paymentStatus'] = self::PMT_STATUS_FAILED;
                            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_CANCELLED;
                            // log the response
                            $this->logResponse($tresponse);
                            break;

                        case 4: // Held for Review
                            $this->dbe->rollbackTrans();
                            $_SESSION['paymentStatus'] = self::PMT_STATUS_PENDING;
                            $_SESSION['bookingStatus'] = self::BOOKING_STATUS_CANCELLED;
                            // log the response
                            $this->logResponse($tresponse);
                            $this->setErrorMessages($tresponse);
                            break;

                        default:
                            // echo 'HERE6 '; 
                            if ($tresponse->getErrors() != null) {
                                // echo 'HERE7 '; 
                                $_SESSION['errorCode'] = $tresponse->getErrors()[0]->getErrorCode();
                                $_SESSION['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();
                            }
                            break;
                    } 
                } else {
                    $this->dbe->rollbackTrans();
                    // error 
                    // echo 'HERE9 '; 
                    $tresponse = $response->getTransactionResponse();
                    $_SESSION['paymentStatus'] = self::PMT_STATUS_FAILED;
                    $_SESSION['bookingStatus'] = self::BOOKING_STATUS_CANCELLED;
    
                    if ($tresponse != null && $tresponse->getErrors() != null) {
                        // echo 'HERE10 '; 
                        $this->logResponse($tresponse);
                        $_SESSION['errorCode'] = $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error Message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    } else {
                        // echo 'HERE11 '; 
                        $messages = $response->getMessages()->getMessage();
                        foreach ($messages as $message) {
                            $_SESSION['errorCode'] = $message->getCode() . "\n";
                            $_SESSION['errorMessage'] = $message->getText() . "\n";
                        }
                    }
                }
            } else {
                $this->dbe->rollbackTrans();
                // echo 'HERE12 '; 
                $_SESSION['paymentStatus'] = self::PMT_STATUS_FAILED;
                $_SESSION['bookingStatus'] = self::BOOKING_STATUS_CANCELLED;

                // print_r($tresponse);
                $_SESSION['errorCode'] = 0;
                $_SESSION['errorMessage'] = "Transaction Failed";
            }
        }
        return false;
    }

    private function setErrorMessages($tresponse)
    {
        // echo 'HERE6 '; 
        if ($tresponse->getErrors() != null) {
            // echo 'HERE7 '; 
            $_SESSION['errorCode'] = $tresponse->getErrors()[0]->getErrorCode();
            $_SESSION['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();
        } else {
            // echo 'HERE8 '; 
            $_SESSION['errorCode'] = $tresponse->getMessages()->getMessage()[0]->getCode();
            $_SESSION['errorMessage'] = $tresponse->getMessages()->getMessage()[0]->getText();
        }
    }

    /**
     * Get Guest Info
     * @param int $guestID
     * @return array
     */
    public function getGuestInfo($guestID)
    {
        $row = $this->dbe->getRowWhere('guests', 'guestID', $guestID);
        if ($row) {
            return $row;
        } else {
            return array();
        }
    }

    /**
     * check if a guest record exists by email address
     * @param string $email
     * @return mixed
     */
    public function getGuestByEmail($email)
    {
        return $this->dbe->getRowWhere('guests', 'email', $email);
    }

    /**
     * Save Guest Info
     * @return int
     */
    public function saveGuestInfo():int
    {
        // guest info is expected to be an array in SESSION item guest
        if (isset($_SESSION['guest']) and is_array($_SESSION['guest'])) {
            // check if a guest record exists by email address
            $row = $this->dbe->getRowWhere('guests', 'email', $_SESSION['guest']['email']);
            if ($row) {
                // has the data changed? Then change it
                $guestID = $row['guestID'];
                $result = $this->dbe->updateRow('guests', $_SESSION['guest'], 'guestID', $guestID);
            } else {
                $result = $this->dbe->insertRow('guests', $_SESSION['guest']);
                $guestID = $result;
            }
            $_SESSION['guest']['guestID'] = $guestID;
            return $guestID;
        } else {
            // todo save failed - bad guest array
            return 0;
        }
    }

    /**
     * Get Booking Info
     * @param int $bookingID
     * @return array
     */
    public function getBookingInfo($bookingID)
    {
        $row = $this->dbe->getRowWhere('bookings', 'bookingID', $bookingID);
        if ($row) {
            return $row;
        } else {
            return array();
        }
    }

    /**
     * Save New Booking Info
     * @param string $bookedBy
     * @return int
     */
    public function saveNewBookingInfo($bookedBy):int
    {
        if ($this->validateBookingItems($_SESSION['checkIn'], $_SESSION['checkOut'], $_SESSION['roomID'])) {
            $totalLodging  = unserialize($_SESSION['bookingTotals']['sub'])->getAmount();
            $totalCharge = unserialize($_SESSION['bookingTotals']['total'])->getAmount();
            $balanceDue = $_SESSION['amountPaid'];

            // save the booking
            $data = array(
                'roomID' => $_SESSION['roomID'],
                'guestID' => $_SESSION['guest']['guestID'],
                'checkIn'   => $_SESSION['checkIn'],
                'checkOut'  => $_SESSION['checkOut'],
                'nights'    => $_SESSION['nights'],
                'bookingStatusID' => $_SESSION['bookingStatus'],
                'bookedBy'  => $bookedBy,
                'totalLodging' => $totalLodging,
                'totalPrice'    => $totalCharge,
                'deposit'   => 0,
                'amountPaid' => 0,
                'amountDue' => $balanceDue,
                'ipAddress' => $_SERVER['REMOTE_ADDR'],
            );
            $result = $this->dbe->insertRow('bookings', $data);
            if ($result > 0) {
                return $result;
            } else {
                echo $this->dbe->error_msg; // todo handle error
            }
        }
        return 0;
    }

    public function savePaymentRecord($bookingID, $guestID, $amount, $paymentType, $checkNumber = '', $paidBy = '', $paymentStatus = 0, $txnID = '')
    {
        $data = array(
            'bookingID' => array('data'=>$bookingID, 'bindtype'=>'i'), 
            'guestID' => array('data'=>$guestID, 'bindtype'=>'i'),
            'type' => array('data'=>$paymentType, 'bindtype'=>'s'), 
            'amount' => array('data'=>$amount, 'bindtype'=>'d'), 
            'paidBy' => array('data'=>$paidBy, 'bindtype'=>'s'), 
            'txnID' => array('data'=>$txnID, 'bindtype'=>'s'),
            'checkNumber' => array('data'=>$checkNumber, 'bindtype'=>'s'), 
            'paymentStatusID' => array('data'=>$paymentStatus, 'bindtype'=>'i'), 
        );
        return $this->dbe->insertRow('payments', $data);
    }

    /**
     * Save Booking Info
     * @param int bookingID
     * @param int paymentID
     * @return int - the bookingID or zero if error
     */
    public function saveBookingInfo($bookingID, $paymentID):int
    {
        IF ($_SESSION['paymentStatus'] == self::PMT_STATUS_COMPLETED) {

            $totalCharge = unserialize($_SESSION['bookingTotals']['total'])->getAmount();
            $totalPaid = $_SESSION['amountPaid'];
            $balanceDue = $totalCharge - $totalPaid;

            // save the booking
            $data = array(
                'bookingStatusID' => array('data'=>$_SESSION['bookingStatus'], 'bindtype'=>'s'),
                'totalPrice'    =>  array('data'=>$totalCharge, 'bindtype'=>'d'),
                'deposit'   =>  array('data'=>$totalPaid, 'bindtype'=>'d'),
                'amountPaid' =>  array('data'=>$totalPaid, 'bindtype'=>'d'),
                'amountDue' =>  array('data'=>$balanceDue, 'bindtype'=>'d'),
            );
            $result = $this->dbe->updateRow('bookings', $data, 'bookingID', $bookingID);
            if ($result > 0) {
                // update the payments record
                $data = array(
                    'amount' => array('data'=>$totalPaid, 'bindtype'=>'d'),
                    'paymentStatusID' => array('data'=>$_SESSION['paymentStatus'], 'bindtype'=>'s'),
                    'txnID' => array('data'=>$_SESSION['txnID'], 'bindtype'=>'s'), // this comes from cc processing service
                );
                $result2 = $this->dbe->updateRow('payments', $data, 'paymentID', $paymentID);
                // update the booking totals
                $_SESSION['bookingTotals']['dep'] = serialize(new BookingTotal('dep', $totalPaid, $_SESSION['roomID']));
                $_SESSION['bookingTotals']['due'] = serialize(new BookingTotal('due', $balanceDue, $_SESSION['roomID']));
                // save the bookingTotals
                $i = 1;
                foreach ($_SESSION['bookingTotals'] as  $bt_item) {
                    $item = unserialize($bt_item);
                    $result2 = $this->addBookingTotal(
                        $bookingID,
                        $item->getType(),
                        $item->getItemType(),
                        $item->getAmount(),
                        $_SESSION['roomID'],
                        $item->getSortOrder(),
                        $item->getIsTotal(),
                        $item->getIsCredit(),
                        $paymentID
                    );
                    $i++;
                }

                return $result;
            }
        }
        return 0;
    }

    /**
     * Add a Booking Total record
     * @return mixed
     */
    public function addBookingTotal($bookingID, $type, $item, $amount, $roomID, $sortOrder, $isTotal, $isCredit, $paymentID = 0)
    {
        $data = array(
            'bookingID' => array('data'=>$bookingID, 'bindtype'=>'i'),
            'type'      => array('data'=>$type, 'bindtype'=>'s'),
            'item'      => array('data'=>$item, 'bindtype'=>'s'),
            'amount'    => array('data'=>$amount, 'bindtype'=>'d'),
            'roomID'    => array('data'=>$roomID, 'bindtype'=>'i'),
            'sortOrder' => array('data'=>$sortOrder, 'bindtype'=>'i'),
            'isTotal'   => array('data'=>$isTotal, 'bindtype'=>'i'),
            'isCredit'  => array('data'=>$isCredit, 'bindtype'=>'i'),
            'paymentID' => array('data'=>$paymentID, 'bindtype'=>'i'),
        );
        return $this->dbe->insertRow('bookingTotals', $data);

    }

    /**
     * Process the payment
     * @return mixed
     */
    public function makePayment($amount, $cardNum, $expDate, $cvv, $bookingID, $detail, $guest)
    {
        $anet = new authorizenetAPI();

        $response = $anet->chargeCreditCard(
                            $amount,
                            $cardNum,
                            $expDate,
                            $cvv,
                            $bookingID,
                            $detail,
                            $guest
        );

        if ($response != null) {
            // Check to see if the API request was successfully received and acted upon
            if ($response->getMessages()->getResultCode() == 'Ok') {
                // Since the API request was successful, look for a transaction response
                // and parse it to display the results of authorizing the card
                $tresponse = $response->getTransactionResponse();
                // log the response
                $this->logResponse($tresponse);
                return $tresponse;
            } else {
                // error 
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getErrors() != null) {
                    // log the response
                    $this->logResponse($tresponse);
                    return $tresponse;
                } else {
                    // Can't log the response
                    return $response;
                }
            }
        } else {
            // todo error
            // echo  "No response returned \n";
        }

        return false;
    }

    /**
     * Make sure all session values are present and within proper ranges
     * @param string $checkIn
     * @param string $checkOut
     * @param int $roomID
     * @return bool
     */
    public function validateBookingItems($checkIn, $checkOut, $roomID)
    {
        // verify that the dates are still open
        return $this->checkRoomAvailability($checkIn, $checkOut, $roomID);
    }

    /**
     * Make sure room is available for the given date range
     * @param string $checkIn
     * @param string $checkOut
     * @param int $roomID
     * @param int $bookingID // if present, ignore this record
     * @return bool
     */
    public function checkRoomAvailability($checkIn, $checkOut, $roomID, $bookingID = 0)
    {
        // make sure there is no overlap with other bookings and that room isAvailable = true
        $room = $this->dbe->getRowWhere('rooms', 'roomID', $roomID);
        if ($room and $room['isAvailable'] == 1) {

            $begin = new DateTime($checkIn);
            $end = new DateTime($checkOut);

            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($begin, $interval, $end);

            // loop by date in range
            foreach ($period as $dt) {
                $day = $dt->format("Y-m-d");
                // check for any bookings for that date
                $pq_query = 'SELECT * FROM `bookings` WHERE `roomID` = ? AND ? BETWEEN `checkIn` AND DATE_SUB(`checkOut`, INTERVAL 1 DAY) AND `bookingID` <> ? ';
                $this->dbe->setBindtypes('isi');
                $this->dbe->setBindvalues(array($roomID, $day, $bookingID));
                $foundRows = $this->dbe->execute_query($pq_query);
                // if found, remove from array
                if ($foundRows) {
                    return false;
                }
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     * Search for bookings by name, email, or phone #
     * ordered by checkIn date desc
     * @param string $search
     * @return bool|array
     */
    public function searchForBookings($search)
    {
        $pq_query = 'SELECT * FROM `guests` g INNER JOIN `bookings` b ON b.`guestID` = g.`guestID` 
                    WHERE `firstName` LIKE ? OR `lastName` LIKE ? OR `phone` = ? OR `email` LIKE ?
                    ORDER BY g.`guestID`, b.`checkIn` DESC ';
        $this->dbe->setBindtypes('ssss');
        $this->dbe->setBindvalues(array('%' . $search . '%', '%' . $search . '%', $search, '%' . $search . '%'));
        return $this->dbe->execute_query($pq_query);
    }

    /**
     * Log Auth.Net responses
     * @param mixed $tresponse
     */
    public function logResponse($tresponse)
    {
        $data = array(
            'responseCode' => $tresponse->getResponseCode(),
            'authCode'  => $tresponse->getAuthCode(),
            'avsResultCode' => $tresponse->getAvsResultCode(),
            'cvvResultCode' => $tresponse->getCvvResultCode(),
            'cavvResultCode' => $tresponse->getCavvResultCode(),
            'transid'   => $tresponse->getTransId(),
            'refTransId' => $tresponse->getRefTransID(),
            'accountNumber' => $tresponse->getAccountNumber(),
            'accountType' => $tresponse->getAccountType(),
            'messages' => serialize($tresponse->getMessages()),
            'errors' => serialize($tresponse->getErrors()),
            'transHashSha2' => $tresponse->getTransHashSha2(),
            'networkTransid' => $tresponse->getNetworkTransId(),
        );
        $result = $this->dbe->insertRow('txnLog', $data);
    }

}

class BookingTotal
{
    private $lineItem = array();
    private $itemTypes = array(
        'sub'   => array('description'=>'Total Lodging','sortOrder'=>1,'isTotal'=>false,'isCredit'=>false),
        'addl'  => array('description'=>'Additional Charges','sortOrder'=>2,'isTotal'=>false,'isCredit'=>false),
        'tax'   => array('description'=>'Tax','sortOrder'=>3,'isTotal'=>false,'isCredit'=>false),
        'total' => array('description'=>'Total','sortOrder'=>4,'isTotal'=>true,'isCredit'=>false),
        'dep'   => array('description'=>'Deposit','sortOrder'=>5,'isTotal'=>false,'isCredit'=>true),
        'pmt'   => array('description'=>'Payment','sortOrder'=>6,'isTotal'=>false,'isCredit'=>true),
        'due'   => array('description'=>'Amount Due','sortOrder'=>9,'isTotal'=>true,'isCredit'=>false),
    );

    public function __construct($itemType, $amount, $roomID)
    {
        if (!in_array($itemType, array_keys($this->itemTypes))) {
            die("Invalid itemType");
        } else {
            $this->lineItem = array(
                'itemType'  => $itemType,
                'amount'    => $amount,
                'isCredit'  => $this->itemTypes[$itemType]['isCredit'],
                'roomID'    => $roomID,
                'isTotal'   => $this->itemTypes[$itemType]['isTotal'],
                'sortOrder' => $this->itemTypes[$itemType]['sortOrder']
            );
        }
    }

    public function getBookingItem()
    {
        return $this->lineItem;
    }

    public function getType()
    {
        return $this->lineItem['itemType'];
    }

    public function getItemType()
    {
        return $this->itemTypes[$this->lineItem['itemType']]['description'];
    }

    public function getAmount()
    {
        return floatval($this->lineItem['amount']);
    }

    public function getRoomID()
    {
        return $this->lineItem['roomID'];
    }

    public function getSortOrder()
    {
        return $this->lineItem['sortOrder'];
    }

    public function getIsCredit()
    {
        return $this->lineItem['isCredit'];
    }
    public function getIsTotal()
    {
        return $this->lineItem['isTotal'];
    }

}