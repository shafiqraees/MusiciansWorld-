<?php
global $MEDIA;
$GLOBALS["page_scripts"] = ["assets/js/booking.js"];
page_start("Book Studio A · MusiciansWorld", "Choose a date, pick a start time and duration, then pay securely with Stripe to confirm Studio A.");
page_hero(
    "Booking · Studio A",
    "Book<br />Studio A.",
    "Select a date, then a start time and duration. Confirm booking to pay with Stripe. You’ll return here after a successful or cancelled payment.",
    $MEDIA["studioA"][0]
);
?>
<section class="section-block bg-mesh">
  <div class="wrap">
    <div id="booking-root" class="grid gap-10 lg:grid-cols-[1.15fr_0.85fr]"></div>
  </div>
</section>
<?php page_end();
