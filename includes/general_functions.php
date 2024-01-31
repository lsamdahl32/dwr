<?php
/**
 * general_functions
 * @author Lee Samdahl, Gleesoft, LLC
 * @copyright 2023, Gleesoft, LLC
 *
 */

 require_once($_SERVER['DOCUMENT_ROOT'] . "/dwr/vendor/autoload.php");

 Dotenv\Dotenv::createUnsafeImmutable(__DIR__)->load();

require_once($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/plm_config.php');
require_once(PLMPATH . 'classes/DBEngine.php');

const CURRENCY_SYMBOL = "$";
const BI_CARET_RIGHT = '<i class="bi-caret-right" style="font-size: 16px;"></i>';

error_reporting(E_ALL);
// create session
$previousSessionName = session_name("PLMan"); //Sets session_name to "PLMan" and returns the old name (see php.net documentation for more info)
session_start();

// get the contents of the configuration table into constants
$dbe = new DBEngine('plm');
$rows = $dbe->getRowsWhere('configuration');
foreach ($rows as $row) {
    define($row['key'], $row['value']);
}
$dbe->close();

// todo temp line
$_SESSION['userID'] = 1;

function clean_param ($param, $type, $sql=false, &$dblink=null, $stripHTML = false, $regexp = "") {
    // C Martin 9/2011
    //This function cleans parameters whether passed via URL (GET) or form (POST)
    //$param = parameter value to be cleaned
    //$type = this is the data type that is expected and to be checked against
    //		Possible values for $type:  s = string | i = integer  (TODO:  add support for boolean, double, array, and others as needed)
    //$sql = for string types only, this boolean value indicates whether we also
    //  	need to check and safeguard against SQL injection
    //$dblink = a valid mysqli connection link resource (the db connection) ... mandatory if $sql=true
    //$stripHTML = include the strip_tags() function to the output if true // added by Lee 10/12/2017
    //$regexp = for string types only, can optionally specify a regular expression to
    //		compare to or modify string (THIS OPTION NOT YET IMPLEMENTED)

    //For general information:
    //mysql_real_escape_string($string) should be used when dealing with a database table
    //htmlentities($string, ENT_QUOTES) should be used when outputting data to a webpage


    switch ($type) {

        case 'i':
            return intval($param);

        case 'd': // added by Lee - 7/30/2019
            return (float)$param;

        case 's':	//Strings
            if ($sql) {
                // a database link is required to use the msqli functions, so if one was not passed to the function,

                //strip off slashes, then escape the string to prevent SQL injection
                //should also be using parameterized queries in addition to this!!
                $cleanstring = mysqli_real_escape_string($dblink, stripslashes(trim($param)));

            }
            else {
                //strip off slashes, then encode html entities to prevent cross-site-scripting
                $cleanstring = htmlentities(stripslashes(trim($param)));
            }

            if ($stripHTML) {
                $cleanstring = strip_tags($cleanstring);
            }

            return $cleanstring;

        default:
            return false;
    }
}

/**
 * Escape HTML output
 * Modified from Wordpress by Lee 8/17/2020
 * Use this function whenever untrusted data is echoed directly to an HTML page
 *
 * @param $text
 * @return string
 */
function gls_esc_html($text )
{
    $safe_text = gls_check_invalid_utf8( $text, true );
    $safe_text = stripslashes(htmlspecialchars( $safe_text ));
    return $safe_text;
}

/**
 * Escape URL output
 * Added by Lee 8/17/2020 * wrapper for PHP rawurlencode
 * Use this function whenever untrusted data is echoed directly inta a URL query string
 * This should not be used to escape
 * an entire URI - only a subcomponent being inserted.
 *
 * @param $url
 * @return false|mixed
 */
function gls_esc_url( $url)
{
    // encode all illegal characters from a url
    $url = rawurlencode ($url);

    return $url;
}

/**
 * Escape Javascript output
 * Modified from Wordpress by Lee 8/17/2020
 * Filters a string cleaned and escaped for output in JavaScript.
 * Use this in DOM event definitions and JS blocks
 *
 * Text passed to esc_js() is stripped of invalid or special characters,
 * and properly slashed for output.
 * Changed to json_encode (from AI - 1/31/24)
 *
 * @param string $text      The text prior to being escaped.
 * @return string
 */
function gls_esc_js( $text )
{
    $safe_text = gls_check_invalid_utf8( $text, true );
    // $safe_text = htmlspecialchars( $safe_text, ENT_COMPAT );
    // $safe_text = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes( $safe_text ) );
    // $safe_text = str_replace( "\r", '', $safe_text );
    // $safe_text = str_replace( "\n", '\\n', addslashes( $safe_text ) );
    $safe_text = json_encode($safe_text, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); // note: this will add quotes!
    return $safe_text;
}

/**
 * Escape HTML Atrribute
 * Modified from Wordpress by Lee 8/17/2020
 * Filters a string cleaned and escaped for output in an HTML attribute.
 * Use this when untrusted data is put in attributes such as "value=''"
 *
 * Text passed to gls_esc_attr() is stripped of invalid or special characters and have quotes escaped
 * before output.
 *
 * @param string $text      The text prior to being escaped.
 * @return string
 */
function gls_esc_attr( $text ) {
    $safe_text = gls_check_invalid_utf8( $text, true );
    $safe_text = htmlspecialchars( $safe_text, ENT_QUOTES, 'UTF-8', false);
    return $safe_text;
}

/**
 * Modified from Wordpress by Lee 8/17/2020
 *
 * @param $string
 * @param false $strip
 * @return false|string
 */
function gls_check_invalid_utf8( $string, $strip = false )
{
    $string = (string) $string;

    if ( 0 === strlen( $string ) ) {
        return '';
    }

    // Check for support for utf8 in the installed PCRE library once and store the result in a static.
    static $utf8_pcre = null;
    if ( ! isset( $utf8_pcre ) ) {
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $utf8_pcre = @preg_match( '/^./u', 'a' );
    }
    // We can't demand utf8 in the PCRE installation, so just return the string in those cases.
    if ( ! $utf8_pcre ) {
        return $string;
    }

    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- preg_match fails when it encounters invalid UTF8 in $string.
    if ( 1 === @preg_match( '/^./us', $string ) ) {
        return $string;
    }

    // Attempt to strip the bad chars if requested (not recommended).
    if ( $strip && function_exists( 'iconv' ) ) {
        return iconv( 'utf-8', 'utf-8//IGNORE', $string );
    }

    return '';
}

function gls_encrypt( $plain ) {
    // Change to OpenSSL encryption for PHP 7.2 - Lee 10/22/2018

    $output = false;
    $encrypt_method = "AES-256-CBC";
    $secret_key = ' #Harm0ny,&&,m3lodY';
    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = openssl_random_pseudo_bytes(16);
    // hash the secret key
    $key = hash('sha256', $secret_key, true);

    $output = openssl_encrypt($plain, $encrypt_method, $key, 0, $iv);
    // iv is prepended to output
    return $iv.$output;
}

function gls_decrypt( $encrypted ) {
    if(!$encrypted){return false;}
    // Change to OpenSSL encryption for PHP 7.2 - Lee 10/22/2018

    $output = false;
    $encrypt_method = "AES-256-CBC";
    $secret_key = ' #Harm0ny,&&,m3lodY';
    // extract iv from encrypted input
    $iv = substr($encrypted, 0, 16);
    $encrypted = substr($encrypted, 16);

    // hash the secret key
    $key = hash('sha256', $secret_key, true);

    $output = openssl_decrypt($encrypted, $encrypt_method, $key, 0, $iv);
    return $output;
}

function send_email ($recipients, $subject="", $messageBody="", $from=null, $cc=null, $bcc=null, $isHTML=true) {

    // Created 4/4/2015 (CM)
    //
    // Purpose:  Sends an email
    //
    // Parameters:
    //
    //      - $recipients:  comma separated list of recipients
    //      - $subject:     email subject
    //      - $messageBody: email message
    //      - $from:        optionally allows us to specify the from email.  NOTE:  This MUST be one of the Appriver emails configured in the sender_relay file, otherwise the message will NOT BE SENT!
    //                      If $from is NOT specified, the default from address is info@spectrasonics.net

    $headers = "MIME-Version: 1.0\r\n";

    if(!is_null($cc)) {
        $headers .= "CC: " . $cc . "\r\n";
    }

    if(!is_null($bcc)) {
        $headers .= "BCC: " . $bcc . "\r\n";
    }

    if ($isHTML) {
        $headers .= "Content-Type: text/html; charset=utf-8" . "\r\n";
    }
    else {
        $headers .= "Content-Type: text/plain; charset=utf-8" . "\r\n";
    }

    if ($from == "") {
        mail($recipients, $subject, $messageBody, $headers);
    }
    else {
        mail($recipients, $subject, $messageBody, $headers, '-f' . $from);  // the headers parameter can just be null
    }

}

/**
 * New array sorting routine
 * Will sort an array of arrays (database) or an array of objects
 * 
 * @param array $array - the input array
 * @param array $keys - the keys (fields) to sort by
 * @param array $orders - an array of sort orders corresponding to $keys - use SORT_ASC or SORT_DESC
 * @return array - if sort fails, original array is returned
 */
function sortArray(array $array, array $keys, ?array $orders = [null]) {
    $arr = $array; // make a copy of the array before sorting
    uasort($arr, function ($a, $b) use ($keys, $orders) { // sort with a callback
        foreach ($keys as $index => $key) {
            $order = $orders[$index] ?? SORT_ASC;

            if (is_object($a)) { // if this is an array of objects
                if ($a->$key != $b->$key) {
                    return ($order == SORT_ASC)
                        ? strnatcmp($a->$key, $b->$key)
                        : strnatcmp($b->$key, $a->$key);
                }

            } else { // if this is a standard array of arrays
                if ($a[$key] != $b[$key]) {
                    return ($order == SORT_ASC)
                        ? strnatcmp($a[$key], $b[$key])
                        : strnatcmp($b[$key], $a[$key]);
                }
            }
        }
        return 0;
    });
    return $arr;
}

/**
 * niceDate
 *
 * @param string|null $date - the date to be formatted
 * @param bool $includeTime - include the time portion
 * @param string|null $format - optional - date format string
 * @return string
 */
function niceDate(?string $date, bool $includeTime = true, ?string $format = null):string
{
    // Early exit if date validation fails
    if (is_null($date) 
        or ($date == '') 
        or (date('Y-m-d H:i:s',strtotime($date)) == '1969-12-31 00:00:00') 
        or ($date == '0000-00-00 00:00:00') 
        or ($date == '0000-00-00')){
        return '';
    }

    $timestamp = strtotime($date);

    // Handle invalid dates
    if ($timestamp === false) {
        return '';
    }

    // Default format selection
    if (!$format) {
        $format = $includeTime ? 'm/d/Y H:i' : 'm/d/Y'; 
    }

    return date($format, $timestamp);
}

/**
 * savePreferences
 * 3/12/21 added ability to set userID. For global settings, set it to my ID - 28
 *
 * @param string $sourceFile
 * @param array $valueArray
 * @param int|null $userID
 */
function savePreferences(string $sourceFile, array $valueArray, ?int $userID = 0)
{
    $dbe = new DBEngine('plm');
    if ($userID == 0) $userID = $_SESSION['userID'];
    // find existing pref record for this userID/sourceFile
    $pq_query = 'SELECT * FROM `preferences` WHERE `sourceFile` = ? AND `userID` = ? ';
    $dbe->setBindtypes("si");
    $dbe->setBindvalues(array($sourceFile, $userID));
    $rows = $dbe->execute_query($pq_query);
    if (count($rows) > 0) {
        // replace record with new $valueArray
        $data = array(
            'valueArray'    => serialize($valueArray),
        );
        $dbe->updateRow('preferences', $data, 'preferenceID', $rows[0]['preferenceID'], 's'); // no logging needed 10/21/2020
    } else {
        // insert new record
        $data = array(
            'sourceFile'    => $sourceFile,
            'userID'        => $userID,
            'valueArray'    => serialize($valueArray),
        );
        $dbe->insertRow('preferences', $data);
    }

    $dbe->close();
}

/**
 * getPreferences
 * 3/12/21 added ability to set userID. For global settings, set it to my ID - 28
 *
 * @param string $sourceFile
 * @param int|null $userID
 * @return false|mixed
 */
function getPreferences(string $sourceFile, ?int $userID = 0)
{
    $dbe = new DBEngine('plm');
    if ($userID == 0) $userID = $_SESSION['userID'];
    // find existing pref record for this userID/sourceFile
    $pq_query = 'SELECT * FROM `preferences` WHERE `sourceFile` = ? AND `userID` = ? ';
    $dbe->setBindtypes("si");
    $dbe->setBindvalues(array($sourceFile, $userID));
    $rows = $dbe->execute_query($pq_query);  // execute query
    $dbe->close();
    if (count($rows) > 0) {
        return unserialize($rows[0]['valueArray']);
    } else {
        return false;
    }
}

/**
 * erasePreferences
 *
 * @param string $sourceFile
 */
function erasePreferences(string $sourceFile)
{
    $dbe = new DBEngine('plm');
    // find existing pref record for this userID/sourceFile
    $pq_query = 'DELETE FROM `preferences` WHERE `sourceFile` = ? AND `userID` = ? ';
    $dbe->setBindtypes("si");
    $dbe->setBindvalues(array($sourceFile, $_SESSION['userID']));
    $result = $dbe->execute_query($pq_query);  // execute query
    $dbe->close();
}

/**
 * mergeColumnArray - part of the Preferences system
 * This function will merge the user-changeable elements of the column array with the default values from the parent file.
 *
 * @param array $column_array
 * @param array $column_array_items
 * @return array
 */
function mergeColumnArray(array $column_array, array $col2):array
{
    for ($i=0; $i < count($column_array); $i++) { // loop the default array
//        if ($column_array[$i]['field'] == $col2[$i]['field'] and $column_array[$i]['heading'] == $col2[$i]['heading']) { // if found, replace the values
            $column_array[$i]['width'] = $col2[$i]['width'];
            $column_array[$i]['search'] = $col2[$i]['search'];
            $column_array[$i]['show'] = $col2[$i]['show'];
            $column_array[$i]['order'] = $col2[$i]['order'];
//        }
    }
    return $column_array;
}

/**
 * lookup
 * Lookup a field in a different table or database, also
 * handles lookups using a linking table and many to many relationships
 *
 * @param array $lookup
 * @param int|string $key
 * @param int|string $key2
 * @return string - contents of field or empty string | CSV
 */
function lookup(array $lookup, $key, $key2 = null):string
{
    if (isset($lookup['table'])) {
        if (empty($key)) {
            return '';
        }
        $dbe = new DBEngine($lookup['db'], false);
        $lookuprow = $dbe->getRowWhere($lookup['table'], $lookup['keyfield'], $key);
        $dbe->close();
        if ($lookuprow) {
            return $lookuprow[$lookup['field']];
        } else {
            return '';
        }
    } elseif (isset($lookup['linkTable'])) {
        if (is_null($key2) or empty($key2)) {
            return '';
        }
        // many to many relationship with a linking table, return all as comma separated string
        $dbe = new DBEngine($lookup['db']);
        $where = array();
        if (isset($lookup['sourceCriteria'])) {
            $sourceCriteria = ' AND b.'.$lookup['sourceCriteria'];
        } else {
            $sourceCriteria = '';
        }
        $pq_query = 'SELECT b.`'.$lookup['displayField'].'` FROM `'. $lookup['linkTable'].'` a INNER JOIN `'. $lookup['sourceTable'].'` b ON b.`'.$lookup['sourceField'].'` = a.`'.$lookup['linkField'].'` WHERE a.`'.$lookup['keyName'].'` = ? '.$sourceCriteria . ' ORDER BY  b.`'.$lookup['displayField'].'`';
        $dbe->setBindtypes("i");
        $dbe->setBindvalues(array($key2));
        $lookuprows = $dbe->execute_query($pq_query);  // execute query
        $dbe->close();
        if ($lookuprows) {
            $output = '';
            foreach ($lookuprows as $lookuprow) {
                $output .= gls_esc_html($lookuprow[$lookup['displayField']]) . ', ';
            }
            return substr($output, 0,-2);
        } else {
            return '';
        }

    }
    return '';
}

/**
 * colLookup
 * handle retrieving values from a lookup column where col['lookup'] contains the sql
 * 6/13/19 Added editable for in column editing
 *
 * @param string $db
 * @param string $sql
 * @param $id
 * @param bool $editable
 * @param bool $allowNone
 * @param string|null $altAllowNoneLabel
 * @param bool $debug
 * @return string
 */
function colLookup(string $db, string $sql, $id, bool $editable = false, bool $allowNone = false, ?string $altAllowNoneLabel = 'None', bool $debug = false):string
{
    $dbe = new DBEngine($db, $debug);
    $lookupRows = $dbe->execute_query($sql);
    $dbe->close();
    $output = '';
    if ($allowNone) {
        if ($editable) {
            $output .= '<option value="0">None</option>';
        } elseif ($id == 0 or $id=='') {
            return $altAllowNoneLabel;
        }
    }
    foreach ($lookupRows as $item) {
        if ($editable) {
            $output .= '<option value="'.$item['id'].'"';
            if ($item['id'] == $id) {
                $output .= ' selected ';
            }
            $output .= '>'.$item['item'].'</option>';
        } else {
            if ($item['id'] == $id) {
                $output .= $item['item'];
            }
        }
    }
    return $output;
}

/**
 * formatSizeUnits
 * Snippet from PHP Share: http://www.phpshare.org
 *
 * @param $bytes
 * @param int|null $decimals
 * @return string
 */
function formatSizeUnits($bytes, ?int $decimals = 1):string
{
    $units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    if ($factor > 0) { 
        $size = $bytes / pow(1024, $factor);
    } else {
        if ($bytes > 1) {
            $size = $bytes;
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            $size = 0;
        }
    }

    return round($size, $decimals) . ' ' . $units[$factor];
}

/**
 * Divs for standard confirmation and alert popups
 *
 */
function jqModalDivs()
{
    ?>
    <!-- hidden divs for the confirmation and alert dialogs -->
    <div class="jqmWindow" id="jqConfDialog">
        <div class="jqPopupHeader">
            <div>
                <span id="confirmImgErase" class="bad_color" style="display: none;">
                    <i class="bi-exclamation-octagon"></i>
                </span>
                <span id="confirmImgWarning" class="pending_color" style="display: none;">
                    <i class="bi-exclamation-triangle"></i>
                </span>
                <span id="confirmImgExclamation" style="display: none;">
                    <i class="bi-exclamation-diamond"></i>
                </span>
            </div>
            <span id="confirmText1">Reset Serials</span>
        </div>
        <div class="jqPopupBody">
            <div class="jqPopupContent">
                <span id="delSerialsText">Are you sure you want to <span id="confirmText2">reset</span> the selected serials?</span>
            </div>
            <div class="jqPopupFooter">
                <input type="button" class="jqmClose buttonBar" name="doConfirmAction" id="doConfirmAction" value="Reset" />
                <input id="doCancelConfirmAction" type="button" class="jqmClose buttonBar" value="Cancel" />
            </div>
        </div>
    </div>
    <div class="jqmWindow" id="jqAlertDialog">
        <div class="jqPopupHeader">
            <i class="bi-exclamation-diamond"></i>
        </div>
        <div class="jqPopupBody">
            <div class="jqPopupContent" id="alertText">
            </div>
            <div class="jqPopupFooter">
                <input type="button" class="jqmClose buttonBar" name="alertOk" id="alertOk" value="Ok" />
            </div>
        </div>
    </div>
    <?php
}

/**
 * Output the standard Export/ Print Options
 *
 * @param bool $formBased - if true use submit button, if false use button type
 * @param int $totPages - set to <= 1 to hide
 * @param bool | float $scaleFactor
 * @param bool $includeScheduleBtn
 */
function showExportOptions(bool $formBased = true, int $totPages = 1, $scaleFactor = false, bool $includeScheduleBtn = false)
{
    // Export/Print options now always starts out as hidden 4/9/2020
    ?>
    <button id="openExportPrintOptions" type="button" class="buttonBar" >
        <i class="bi-box-arrow-right"></i>&nbsp;
        <span>Export/ Print Options</span>
    </button>
    <fieldset id="exportPrintOptions" class="report_fieldset" style="display: none;">
        <legend><b>Export/ Print Options:</b></legend>
        <div class="ar-searchContents">
            <div class="form_rows">
                <div class="form_label">
                    <label for="exporttype1">
                        <input type="radio" name="exporttype" id="exporttype1" value="pdf" checked="checked" />
                        Print to PDF
                    </label>
                </div>
                <div id="ExportPrint_pdf_options">
                    <?php if($scaleFactor) { ?>
                        <label for="scaleFactorInput">
                            Scale Factor
                            <input type="number" size="3" maxlength="3" min=50 max=200 name="scaleFactorInput"
                                   id="scaleFactorInput"
                                   value="<?php echo gls_esc_attr($scaleFactor) * 100; ?>"/>%
                        </label>&nbsp;&nbsp;
                    <?php }
                    if ($totPages > 1) { ?>
                        <label for="currentpage">
                            <input type="checkbox" name="currentpageInputPDF" id="currentpageInputPDF" checked="checked"  />&nbsp;Current Page Only
                        </label>
                    <?php } ?>
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label">
                    <label for="exporttype3">
                        <input type="radio" name="exporttype" id="exporttype3" value="csv" />
                        CSV
                    </label>
                </div>
                <div id="ExportPrint_csv_options" style="display: none;">
                    <?php if ($totPages > 1) { ?>
                        <label for="currentpage">
                            <input type="checkbox" name="currentpageInputCSV" id="currentpageInputCSV" checked="checked"  />&nbsp;Current Page Only
                        </label>
                    <?php } ?>
                </div>
            </div>
            <div id="exportButtons">
                <?php if ($formBased) { ?>
                    <button type="submit" class="buttonBar">
                        <i class="bi-download"></i>&nbsp;
                        Export
                    </button>&nbsp;&nbsp;
                <?php } else { ?>
                    <button type="button" class="buttonBar" id="exportBtn" >
                        <i class="bi-download"></i>&nbsp;
                        Export
                    </button>&nbsp;&nbsp;
                <?php } ?>
                <button type="button" class="buttonBar" id="cancelExportBtn" >
                    <i class="bi-arrow-return-left"></i>&nbsp;
                    Cancel
                </button>
            </div>
        </div>
    </fieldset>
    <?php
}

/**
 * isValidEmail()
 * copied from zen cart function, zen_validate_email() in functions_email.php by Lee - 05/25/2016
 *
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email):bool
{
    // original function:
    //return preg_match("/^[a-z0-9._\-]+[@]([a-z0-9\-]+[.])+([a-z]{2,4})\$/i", $email);
    // copied from zen cart function, zen_validate_email() in functions_email.php by Lee - 05/25/2016
    $valid_address = TRUE;

    // fail if contains no @ symbol or more than one @ symbol
    if (substr_count($email,'@') != 1) return false;

    // split the email address into user and domain parts
    // this method will most likely break in that case
    list( $user, $domain ) = explode( "@", $email );
    $valid_ip_form = '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}';
    $valid_email_pattern = '^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$';
    $space_check = '[ ]';

    // strip beginning and ending quotes, if and only if both present
    if( (preg_match('/^["]/', $user) && preg_match('/["]$/', $user)) ){
        $user = preg_replace ( '/^["]/', '', $user );
        $user = preg_replace ( '/["]$/', '', $user );
        $user = preg_replace ( '/'.$space_check.'/', '', $user ); //spaces in quoted addresses OK per RFC (?)
        $email = $user."@".$domain; // contine with stripped quotes for remainder
    }

    // fail if contains spaces in domain name
    if (strstr($domain,' ')) return false;

    // if email domain part is an IP address, check each part for a value under 256
    if (preg_match('/'.$valid_ip_form.'/', $domain)) {
        $digit = explode( ".", $domain );
        for($i=0; $i<4; $i++) {
            if ($digit[$i] > 255) {
                $valid_address = false;
                return $valid_address;
            }
            // stop crafty people from using internal IP addresses
            if (($digit[0] == 192) || ($digit[0] == 10)) {
                $valid_address = false;
                return $valid_address;
            }
        }
    }

    if (!preg_match('/'.$valid_email_pattern.'/i', $email)) { // validate against valid email patterns
        $valid_address = false;
        return $valid_address;
    }

    return $valid_address;

}


/**
 * exportToPDF
 * Uses TFPDF to create download PDF documents for reports
 *
 * @param string $reportTitle
 * @param string $subtitle
 * @param array $column_array - array of column information - see calling programs for format
 * @param array $rows - the db rows for output
 * @param float $scaleFactor - shrink or expand output
 * @param string|null $sendTo - if email address present - direct output to email instead of screen
 * @param string|null $subject - if email, this will override the standard subject line of $reportTitle
 * @param string|null $body - if email, this will override the standard body text
 * @param boolean $largeFile - set true for multi-page documents, pdf will be constructed on disk
 */
function exportToPDF(string $reportTitle, string $subtitle, array $column_array, array $rows, float $scaleFactor, ?string $sendTo = '', ?string $subject = '', ?string $body = '', bool $largeFile = false)
{
    ////////////////////////// Export to PDF /////////////////////////////
    // Include Reports_class and FPDF
    // This routine allows for a report where columns span pages horizontally
    // Pages are output down vertically then horizontally
    //
    if ($largeFile) {
        require_once PLMPATH . 'classes/tfpdf/MyPDF_lf.php';
        $myPDF = new MyPDF_lf($reportTitle, COMPANY_NAME, 1);
    } else {
        require_once PLMPATH . 'classes/tfpdf/MyPDF.php';
        $myPDF = new MyPDF($reportTitle, COMPANY_NAME, 1);
    }

    global $db;
    // set $clm array for report
    $repoFontSize = 8 * $scaleFactor; // points
    $fromLeft = 0;
    $h_page = 0;
    $clm = array();
    $width = 7.5; // start with letter/ portrait 1/2" margins
    $height = 10;
    $orientation = 'P';

    foreach ($column_array as $col){
        if (isset($col['altHeading'])) {
            $heading = $col['altHeading'];
        } else {
            $heading = $col['heading'];
        }
        if ($col['show'] and !isset($col['sectionheading']) and ($col['field'] != 'multi_checkbox') and ($col['field'] != 'multi_select')) {
            if (substr($col['type'], 0, 5) == 'link:' or $col['forceShow']) {
                // don't include links
            } else {
                if (!isset($col['width'])) $col['width'] = $width - $fromLeft;
                if ($orientation == 'P' and ($fromLeft + ($col['width'] * $scaleFactor) > $width)) {
                    // too wide for portrait, switch to landscape
                    $orientation = 'L';
                    $width = 10;
                    $height = 7.5;
                }
                if ($fromLeft + ($col['width'] * $scaleFactor) > $width) { // too wide for one page - new page of columns
                    $h_page++;
                    $fromLeft = 0;
                }
                if (($col['format'] == 'currency' or $col['format'] == 'accounting' or $col['format'] == 'numeric') and !isset($col['align'])) {
                    $myPDF->column_array($fromLeft + (($col['width'] - .2) * $scaleFactor), $heading, true, false, $h_page, $col);
                } elseif ($col['type'] == 't') {
                    // text type - word wrap it
                    $myPDF->column_array($fromLeft, $heading, false, true, $h_page, $col);
                } else {
                    $myPDF->column_array($fromLeft, $heading, false, false, $h_page, $col);
                }
                $fromLeft += ($col['width'] * $scaleFactor);
            }
        } else {
            // include show=false columns also in clm array
            $myPDF->column_array(0, $heading, false, false, 0, $col);
        }
    }
    // output the report
    $lineheight = (.2 * $scaleFactor);
    // loop the report rows
    if ($rows) {
        // if not empty recordset
        $myPDF->setSelectionCriteria($subtitle);
        $myPDF->init($orientation, 'letter', .5, false, false, false);
        $myPDF->SetFillColor(255);
        $myPDF->SetTextColor(0);
        $clm = $myPDF->getColumns();
        // loop horizontal pages
        for ($h=0; $h<=$h_page; $h++){
            if ($h > 0){
                $myPDF->AddPage('', '', 0,false, $h);
            }
            // loop the rows
            foreach ($rows as $row) {
                $myPDF->SetFontSize($repoFontSize);
                // get the starting Y position
                $y = $myPDF->GetY();
                // get the next row's height
                $rowheight = $myPDF->getRowHeight($row, $lineheight);
                if ($y + $rowheight > $height) {
                    $myPDF->AddPage('', '', 0,false, $h);
                    $y = $myPDF->GetY();
                }
                if (isset($row['hiLiteRow']) or isset($row['subtotalRow'])) {
                    // show row in Bold
                    if (!is_bool($row['hiLiteRow'])) { // used in autoSerialsReport.php
                        if (strpos($row['hiLiteRow'], '-color: yellow') > 0) {
                            $myPDF->SetFillColor(255,255,0);
                            $myPDF->SetTextColor(0);
                        } elseif (strpos($row['hiLiteRow'], '-color: maroon') > 0) {
                            $myPDF->SetFillColor(128,0,0);
                            $myPDF->SetTextColor(255,255,0);
                            $myPDF->SetFont('DejaVu','B',($repoFontSize));
                        } else {
                            $myPDF->SetFillColor(230);
                            $myPDF->SetFont('DejaVu','B',($repoFontSize));
                        }
                    } else {
                        $myPDF->SetFillColor(230);
                        $myPDF->SetFont('DejaVu','B',($repoFontSize));
                    }
                    // draw a background on the hilite row - 4/3/19
                    $myPDF->cell($myPDF->getWidth(), $lineheight * $scaleFactor, '', 0, 1, 'L', true);
                    $myPDF->SetY($y);
                    if (isset($row['subtotalRow'])) $myPDF->hLine();
                } else {
                    $myPDF->SetFont('DejaVu','',($repoFontSize));
                    $myPDF->SetFillColor(255);
                    $myPDF->SetTextColor(0);
                }
                // loop the columns on the page
                $LnCnt = 0;
                for ($i = 0; $i < count($clm); $i++) {
                    if ($clm[$i]['hpage'] == $h) {
                        if (substr($clm[$i]['type'], 0, 5) == 'link:' or $clm[$i]['forceShow']) {
                            // don't include links
                        } else {
                            if ($clm[$i]['show']) {
                                $fld = removePeriodsInFieldName($clm[$i]['field']);
                                if (substr($clm[$i]['type'], 0, 9) == 'function:' or isset($clm[$i]['showFunction'])) {
                                    if (isset($clm[$i]['showFunction'])) {
                                        $fn = $clm[$i]['showFunction'];
                                    } else {
                                        $fn = substr($clm[$i]['type'], 9);
                                    }
                                    if (is_callable($fn)) {
                                        $row[$fld] = call_user_func($fn, $row[$column_array[0]['field']], $row[$clm[$i]['field']], $clm[$i], false, $row); // note expects first field in column array to be the keyfield
                                    }
                                }
                                if ($clm[$i]['format'] == 'accounting') {
                                    if (strpos($row[$fld], '(') > 0) {
                                        $myPDF->SetTextColor(255,0,0);
                                        $myPDF->PrintColumns(strip_tags($row[$fld]), $i, $LnCnt);
                                        $myPDF->SetTextColor(0);
                                    } else {
                                        $myPDF->SetTextColor(0,100,0);
                                        $myPDF->PrintColumns(strip_tags($row[$fld]), $i, $LnCnt);
                                        $myPDF->SetTextColor(0);
                                    }
                                } else {
                                    $myPDF->PrintColumns(strip_tags($row[$fld]), $i, $LnCnt);
                                }
                            }
                        }
                    }
                }
                $myPDF->SetY($y + ($lineheight * $LnCnt));
                if (isset($row['subtotalRow'])) $myPDF->SetY($y + $lineheight * 1.5); // add extra line after subtotals
            }
        }
        // output pdf
        if ($sendTo == '') {
            $myPDF->Output();
        } else { // send to email
//            $content = $myPDF->Output('S'); // Lee changed for new version of tfpdf
//            $attachName = strtolower(str_replace(' ','_',$reportTitle)).'_'.date('Ymd').'.pdf';
//            $doc = array('content' => $content, 'filename' => $attachName);
//            if ($subject == '') {
//                $subject = $reportTitle;
//            }
//            if ($body == '') {
//                $body = 'Attached is the scheduled report, "'.$reportTitle.'".';
//            }
//            echo sendEmailWithAttachments($sendTo, $body, $subject, array($doc));
        }
    }

}

/**
 * exportToCSV
 * Output data to CSV download file
 *
 * @param string $filename
 * @param array $column_array - array of column information - see calling programs for format
 * @param array $rows - the db rows for output
 * @param int $count_rows - for very large files - only query this many rows at a time
 * @param array $filters - from processSearchRequests in ATReports, using 'where', 'pq_bindtypes', and 'pq_bindvalues'
 * @param string $table
 * @param array $orderby - SQL ORDER BY in an array of [fieldname ASC]...
 * @param DBEngine|null $dbe - DBEngine instance
 * @param bool $currentpage - only output items displayed on current report page - false means all records included
 * @param string $reportTitle
 * @param string $subTitle
 * @param string|null $sendTo - if email address present - direct output to email instead of screen
 */
function exportToCSV(string $filename, array $column_array, array $rows, int $count_rows, array $filters, string $table, array $orderby, ?DBEngine $dbe = null, bool $currentpage = true, string $reportTitle = '', string $subTitle = '', ?string $sendTo = '')
{
    ////////////////////////// Export to CSV /////////////////////////////
    global $db;
    // filename for download
    $filename .= date('Ymd') . ".csv";
    if ($sendTo == '') {
        // output headers so that the file is downloaded rather than displayed
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '');
    } else {
        @ob_start();
    }

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    if ($reportTitle != '' or $subTitle != '') {
        // output the heading
        fputcsv($output, array($reportTitle));
        fputcsv($output, array($subTitle));
    }
    // output the column headings
    $head = array();
    foreach ($column_array as $col){
        if ($col['show']) {
            if (substr($col['type'], 0,5) == 'link:') {
                // don't include checkboxes or links
            } else {
                $head[] = $col['heading'];
            }
        }
    }
    fputcsv($output, $head);

    // loop over the rows, outputting them
    if ($currentpage) {
        foreach ($rows as $row) {
            $row_array = array();
            foreach ($column_array as $col){
                if (substr($col['type'], 0,5) == 'link:') {
                    // don't include checkboxes or links
                } else {
                    $fld = removePeriodsInFieldName($col['field']);
                    if ($col['show']) {
                        $row_array[] = $row[$fld];
                    }
                }
            }
            fputcsv($output, $row_array);
        }
    } else {
        // output as array 100 rows at a time
        if (is_null($dbe)) {
            $dbe = new DBEngine($db);
        }
        if ($filters == array()) { // if the filters array is empty, set the default values so the below will work with non-ATReports exports
            $filters['where'] = array();
            $filters['pq_bindtypes'] = '';
            $filters['pq_bindvalues'] = array();
        }
        for ($i = 0; $i < ($count_rows/100); $i++) {
            if (count($filters['where']) == 0) {
                $pq_query = 'SELECT * FROM '.$table.'
                     Where 1
                     ORDER BY ' . implode(',', $orderby);
            } else {
                $pq_query = 'SELECT * FROM '.$table.'
                      Where ' . implode(' AND ', $filters['where']) . '
                         ORDER BY ' . implode(',', $orderby);
            }
            $pq_query .= ' LIMIT ' . ($i*100) . ', 100';
            $dbe->setBindtypes($filters['pq_bindtypes']);
            $dbe->setBindvalues($filters['pq_bindvalues']);
            $rows2 = $dbe->execute_query($pq_query);  // execute query
            foreach ($rows2 as $row) {
                $row_array = array();
                foreach ($column_array as $col) {
                    if (substr($col['type'], 0,5) == 'link:') {
                        // don't include checkboxes or links
                    } else {
                        $fld = removePeriodsInFieldName($col['field']);
                        if ($col['show']) {
                            if ($col['type'] == 'lookup') {
                                // lookup in other db table - 'format' element is array
                                $row_array[] = lookup($col['format'], $row[$col['field']]);
                            } elseif (substr($col['type'], 0, 9) == 'function:' or isset($col['showFunction'])) {
                                if (isset($col['showFunction'])) {
                                    $fn = $col['showFunction'];
                                } else {
                                    $fn = substr($col['type'], 9);
                                }
                                if (is_callable($fn)) {
                                    $row_array[] = call_user_func($fn, $row[$column_array[0]['field']], $row[$col['field']], $col, false, $row); // note expects first field in column array to be the keyfield
                                }
                            } else {
                                $row_array[] = $row[$fld];
                            }
                        }
                    }
                }
                fputcsv($output, $row_array);
            }
        }
    }
}

/**
 * removePeriodsInFieldName
 *
 * @param string $field - the field name
 * @return bool|string - the field name with any periods removed
 */
function removePeriodsInFieldName(string $field)
{
    // remove any periods in field name
    if (strpos($field, '.') > 0) {
        return substr($field, strpos($field, '.') + 1);
    } else {
        return $field;
    }
}
