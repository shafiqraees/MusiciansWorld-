<?php
global $MEDIA;
$GLOBALS["page_scripts"] = ["assets/js/booking.js"];
page_start("Book Studio A · MusiciansWorld", "Choose a from time and a to time, review the range, then pay securely with Stripe to confirm Studio A.");
page_hero(
    "Booking · Studio A",
    "Book<br />Studio A.",
    "Select a start slot and an end slot. Review the from and to times, then continue to payment.",
    $MEDIA["studioA"][0]
);
?>
<section class="section-block bg-mesh">
  <div class="wrap">
    <div
      id="booking-root"
      class="grid gap-10 lg:grid-cols-[1.15fr_0.85fr]"
      data-availability-url="<?= e(route('booking.availability')) ?>"
      data-booking-url="<?= e(route('booking.store')) ?>"
    ></div>
  </div>
</section>
<?php page_end();
