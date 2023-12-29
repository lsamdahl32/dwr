<?php
/**
 * Standard Page Header
 * @author Lee Samdahl
 * @company Gleesoft, LLC
 * @created 3/29/2023
 *
 * @var $additionalHeaders
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Desert Willow Ranch B&B</title>
    <meta name="keywords" content="lodging horses tucson arizona desert willow bed breakfast">
    <meta http-equiv="Content-Language" content="en-us">

    <meta name="description" content="Desert Willow Ranch B&B an authentic horse ranch in Tucson, Arizona.">

    <link rel="stylesheet" href="/dwr/css/normalize.css" type="text/css">

    <link href="/dwr/css/dwr.css?version=0_1" rel="stylesheet" type="text/css"/>

    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

    <script src="/dwr/js/dwrShared.js"></script>
    <?php
    if (isset($additionalHeaders) and $additionalHeaders !== '') {
        echo $additionalHeaders;
    }
    ?>
</head>

<body>
    <div class="container">