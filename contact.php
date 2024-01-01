<?php
/**
 * Contact Us page
 * @author Lee Samdahl
 * @author Gleesoft, LLC
 *
 * @created 3/29/2023
 */

//require_once $_SERVER['DOCUMENT_ROOT'] .'/include/recaptcha/src/autoload.php';
//$siteKeyv3 = '6LcfAeokAAAAAGJ9cS_B2Q0Bc2V5Snjt_Mqu54ou'; // v3
//$secretv3 = '6LcfAeokAAAAAPGRs4433arw66iKFEr1b8WPSTW9';

$msg = '';
//if (isset($_POST['g-recaptcha-response'])) {
if (isset($_POST['name'])) {

//    $recaptcha = new \ReCaptcha\ReCaptcha($secretv3, new \ReCaptcha\RequestMethod\CurlPost());
    // Make the call to verify the response and also pass the user's IP address
//    $resp = $recaptcha->setExpectedHostname($_SERVER["HTTP_HOST"]) // v3 (Lee) 11/9/22
//    ->setExpectedAction('contact')
//        ->setScoreThreshold(0.5)
//        ->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
//
//    if ($resp->getScore() > .5 and $resp->getAction() == 'contact') {
        // send an email to gleesoft@gleesoft.com containing the data
        $sendTo = 'gleesoft@gleesoft.com';
        $subject = 'Information Request from ' . $_POST['name'];
        $body = 'DWR Contact Page Request<br><br>';
        $body .= "From: " . $_POST['name'] . "<br>";
        $body .= "Email: " . $_POST['email'] . "<br>";
        $body .= "Question:<div>" . stripcslashes($_POST['description']) . "</div>";


        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        if (mail($sendTo, $subject, $body, $headers)) {
            $msg = 'Thank you for sending your message to Desert Willow Ranch.';
        }
//    } else {
//        // an error has occurred
//        echo 'A Recaptcha error has occurred. ' . $resp->getScore();
//    }
}

$additionalHeaders = '';
require_once('./page_header.php');
require_once('./page_nav.php');
?>
<main>
    <div class="titlebar">
        <h1>The Desert Willow Ranch B&B</h1>
        <h2>Contact Us</h2>
    </div>
    <address id="contact_page">
        Desert Willow Ranch B&B, LLC<br>
        381 S. Pinto Place<br>
        Tucson, Arizona, 85748<br>
        (520) 296-4500
    </address>
    <?php if ($msg == '') { ?>
        <p>To ask a question, please enter your information and question below.</p>
        <form action="<?=$_SERVER['PHP_SELF']?>" id="contactForm" method="post" accept-charset="UTF-8">
            <div class="form_rows">
                <label for="name" class="form_label">Name</label>
                <div class="form_cell">
                    <input type="text" name="name" id="name" required />
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <label for="email" class="form_label">Email</label>
                <div class="form_cell">
                    <input type="email" name="email" id="email" required />
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <label for="description" class="form_label" style="align-self: flex-start;">Question</label>
                <div class="form_cell">
                    <textarea name="description" id="description" style="width: 90%; max-width: 600px; height: 200px;" required></textarea>
                    <span class="form_error">*</span>
                </div>
            </div>
            <div class="form_rows">
                <div class="form_label"></div>
                <div class="form_cell">
<!--                    <button name="submitForm" class="buttonBar g-recaptcha"-->
<!--                            data-sitekey="--><?//=$siteKeyv3?><!--"-->
<!--                            data-action="contact"-->
<!--                            data-callback="onSubmit"-->
<!--                            data-theme="dark"  style="padding: 4px 14px;">-->
<!--                        <i class="bi-box-arrow-up"></i>&nbsp;Submit-->
<!--                    </button>-->
                    <button name="submitForm" type="submit">
                        Submit
                    </button>
                </div>
            </div>
            <span class="form_error">* = Required</span>

        </form>
    <?php } else {
        echo '<div class="form_error" style="margin: 2rem auto; font-size: 14px;">' . $msg . '</div>';
    } ?>

</main>

<?php

require_once('./page_footer.php');

?>

<script>

    function onSubmit(token) {
        console.log('Submitted');
        document.getElementById("contactForm").submit();
    }

</script>