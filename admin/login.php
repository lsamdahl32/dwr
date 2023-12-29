<?php
/**
 * Login Form
 * This module expects HTTP Basic Auth to be set for the entire admin directory
 * A OTC is then required to proceed
 * @author Lee Samdahl
 * @company Gleesoft.com
 * @created 6/8/23
 */

ini_set('session.gc_maxlifetime', 3600 * 24);
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');

// TODO temporary lines - skip code entry
$_SESSION['is_logged_in'] = true;
header('location: index.php');
exit;
// END temporary lines

// todo count number of attempts and block if > allowed
if (isset($_POST['form_submit'])) {
    if (isset($_POST['submitCode'])) {
        $otc = $_POST['plm_otc_code'];
        if (strlen($otc) != 6) {
            // todo error
        }
        if ($otc == $_SESSION['plm_otc_code']) {
            $_SESSION['is_logged_in'] = true;
            header('location: index.php');
        }
    } elseif (isset($_POST['resendCode'])) {
        // create new OTC code
        $_SESSION['plm_otc_code'] = randomCode();
        // send the OTC code to the email address in the settings
        sendCode($_SESSION['plm_otc_code'], ADMIN_EMAIL);
    }
} else {
    // create OTC code
    $_SESSION['plm_otc_code'] = randomCode();
    // send the OTC code to the email address in the settings
    sendCode($_SESSION['plm_otc_code'], ADMIN_EMAIL);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title><?=COMPANY_NAME?> Admin</title>
    <meta http-equiv="Content-Language" content="en-us">

    <link rel="stylesheet" href="<?=PLMSite?>css/normalize.css" type="text/css">
    <link href="<?=PLMSite?>admin/css/side_menu_home.css" rel="stylesheet" type="text/css"/>
    <link href="<?=PLMSite?>admin/css/plm_admin.css" rel="stylesheet" type="text/css"/>

    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

    <script src="./js/plm_admin.js"></script>
    <style>
        body {
            height: 100vh;
            padding: 0;
            font-family: 'Lucida Grande', Arial, Verdana, sans-serif;
            font-size: 12px;
            color: var(--color);
            background-color: var(--background-color);
            margin: 0;
        }

        .pos {
            width: 40%;
            max-width: 380px;
            margin: 15% auto 0 auto;
        }

        .out {
            border: solid 10px #cccccc;
            background-color: var(--area-background);
            height: 80%;
            padding: 24px;
            display: flex;
            flex-flow: column nowrap;
            justify-content: center;
            align-items: center;
            gap: 1em;
        }

        .error {
            color: maroon;
        }

        .username-container {
            text-align: right;
            margin-bottom: 1rem;
        }

        .username {
            text-align: left;
        }


    </style>
</head>
<body>
<?php
?>

    <div class="pos">
        <form action="login.php" target="_self" method="post" name="loginform" ID="loginform" accept-charset="utf-8">
            <input type="hidden" name="form_submit" value="yes">
            <div class="out">
                <h1>Login</h1>
                <?php
                // 2fa code entry
                if ($errorMsg != ''){
                    echo '<div id="error" class="error">'.$errorMsg.'</div>';
                }
                ?>
                <div>
                    <div class="username-container">
                        <div class="username">
                            <label for="at_2fa_code">Enter Code from Email<br>
                                <input type="text" class="inputtext" name="plm_otc_code" value="" maxlength=6 id="plm_otc_code">
                            </label>
                        </div>
                    </div>
                </div>
                <button class="buttonBar" id="submitCode" name="submitCode">
                    <i class="bi-unlock"></i>&nbsp;
                    Submit
                </button>
                <button type="button" class="buttonBar" id="resendCode" name="resendCode">
                    <i class="bi-send"></i>&nbsp;
                    Resend Code
                </button>

            </div>
        </form>
    </div>
</body>
</html>

<?php

/**
 * Returns a 6 digit random number as a string
 * @return string
 */
function randomCode():string
{
    $pass = '';
    $lchar = 0;
    $char = 0;
    for($i = 0; $i < 6; $i++)
    {
        while($char == $lchar)
        {
            $char = rand(48, 57);
        }
        $pass .= chr($char);
        $lchar = $char;
    }
    return $pass;
}

/**
 * @param string $code
 * @param string $name
 * @param string $phone
 * @param string $email
 * @param int $carrier
 * @param int $method
 * @return bool
 */
function sendCode(string $code, string $email):bool
{
    $subject = COMPANY_NAME . ' - One Time Login Code';
    $headers = "MIME-Version: 1.0\r\n";
//    $from = 'server-mail@spectrasonics.net';

    $recipients = $email;     // Add a recipient
    $body = 'Your code is <b style="font-size: larger;">' . $code . '</b>';
    $headers .= "Content-Type: text/html; charset=utf-8" . "\r\n";
    $_SESSION['otcSentMessage'] = 'A code was sent to your email - ' . $email;

//    mail($recipients, $subject, $body, $headers, '-f' . $from);
    mail($recipients, $subject, $body, $headers);

    return true;
}