<?php
/**
 * Desert Willow Ranch B&B
 * @author Lee Samdahl
 * @author Gleesoft, LLC
 *
 * @created 3/22/2023
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ($_SERVER['DOCUMENT_ROOT'] . '/dwr/includes/general_functions.php');

$additionalHeaders = '';
require_once('./page_header.php');
require_once('./page_nav.php');
?>

            <main>
                <div class="titlebar">
                    <h1>The Desert Willow Ranch B&B</h1>
                    <h2>An Authentic Horse Ranch in Tucson, Arizona.</h2>
                </div>
                <div id="home_page_photo_container">
                    <img src="images/barn_with_wagon.png" width="100%" />
                </div>

                <article>                    
                    <p>The Desert Willow Ranch is an authentic horse ranch located on the east side of Tucson, Arizona. It is nestled beneath the 
                        Rincón Mountains to the east and the Catalina Mountains to the north.</p>
                    <p>The original ranch house has been remodeled to include two deluxe rooms with private entrances.</p>
                    <p>A pad has been added for RV campers with full hookups.</p>
                </article>

                <article>
                    <h2>Bring Your Horse</h2>
                    <p>The picturesque barn has been updated with two high-quality horse stalls. Pens and an arena are also available.</p>
                    <p>Please contact us to enquire about adding your horse to your stay with us.</p>
                </article>
                
                <article>
                    <h2>History</h2>
                    <div style="display: flex; gap: 2rem; align-items: center;">
                        <img src="images/20161024_190620_IMG_8816.JPG" style="width: 300px;" />
                        <p>Desert Willow Ranch – what’s in a name?  The correlation began in the 1950s when Zip and Jinny Peterson
                            were newly married and moved to Tucson from back east.  Zip came from Massachusetts and Jinny came from Connecticut.
                            Zip and Jinny shared a deep passion for horses...
                            <a href="history.php" >See more</a>
                        </p>
                    </div>
                </article>
<!-- 
                <article>
                    <h2>Stuff!</h2>
                    <p>…</p>
                    <p>…</p>
                    <p>…</p>
                    <p>…</p>
                    <p>…</p>
                </article> -->


                <article>
                    <h2>Location</h2>
                    <p style="text-align: center;">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d8498.037157956343!2d-110.75130118613548!3d32.21240497660931!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x86d667c6c118a7ef%3A0x16544ab2d68aaf45!2sDesert%20Willow%20Ranch%20B%26B%2C%20LLC!5e0!3m2!1sen!2sus!4v1698862431629!5m2!1sen!2sus"
                               width="600" height="350" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </p>
                </article>

            </main>
<?php

require_once('./page_footer.php');