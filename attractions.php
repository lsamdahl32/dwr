<?php
/**
 * Attractions page
 * @author Lee Samdahl
 * @author Gleesoft, LLC
 *
 * @created 3/29/2023
 */
require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');

$dbe = new DBEngine('plm');

$pq_query = 'SELECT * FROM `attractions` WHERE `isAvailable` = ? ORDER BY `sortOrder`, `createdOn` ';
$dbe->setBindtypes('i');
$dbe->setBindvalues(array(1));
$rows = $dbe->execute_query($pq_query);
$dbe->close();
$additionalHeaders = '';
require_once('./page_header.php');
require_once('./page_nav.php');
?>
<main>
    <div class="titlebar">
        <h1>The Desert Willow Ranch B&B</h1>
        <h2>Tucson Attractions</h2>
    </div>
    <div id="results">
        <?php if ($rows) {
            foreach ($rows as $row) {
            ?>
        <section class="attractions">
            <div style="flex-basis: 48%;">
                <h2><?=gls_esc_html($row['title']) ?></h2>
                <p><?=gls_esc_html($row['description'])?></p>
                <a href="<?=gls_esc_html($row['url'])?>" target="_blank"><?=gls_esc_html($row['url'])?></a>
            </div>
            <div class="attraction_image" style="flex-basis: 48%;">
                <img src="./images/<?=$row['image']?>" alt="<?=gls_esc_html($row['title']) ?>" />
            </div>
        </section>
        <?php }
        } ?>
    </div>
</main>


<?php

require_once('./page_footer.php');
