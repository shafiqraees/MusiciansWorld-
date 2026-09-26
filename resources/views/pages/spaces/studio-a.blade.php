<?php
global $MEDIA, $SITE, $STUDIO_A_EQUIPMENT, $STUDIO_A_PRICES;
page_start("Studio A · Musicians World", "Professional rehearsal studio in " . location_for("studio-a") . ". Drum kit, PA, up to four microphones. Book 2 to 8 hours.");
page_hero(
    "Studio A · " . location_for("studio-a"),
    "The loud<br />room.",
    "Professional rehearsal and creative studio rental. Treat it like a lockout: kit, PA, mics, and a clock that runs from 9AM to 2AM.",
    $MEDIA["studioA"][0],
    ["href" => "/book", "label" => "Check availability"],
    ["href" => "/contact", "label" => "Ask the desk"]
);
$rows = [[2, $STUDIO_A_PRICES[2]], [3, $STUDIO_A_PRICES[3]], [4, $STUDIO_A_PRICES[4]], [6, $STUDIO_A_PRICES[6]], [8, $STUDIO_A_PRICES[8]]];
?>
<section class="section-block bg-aurora">
  <div class="wrap">
    <?php gallery($MEDIA["studioA"], "Studio A rehearsal room"); ?>
  </div>
</section>
<section class="section-block bg-mesh">
  <div class="wrap grid gap-14 lg:grid-cols-2">
    <div class="reveal">
      <p class="kicker">The room</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">Built for sessions that actually start.</h2>
      <p class="mt-5 leading-8 text-dim">
        Studio A is the working studio at Musicians World — rehearsal, writing, and creative lockouts in
        <?= e(location_for("studio-a")) ?>. You pick a date, a start time, and a duration. The calendar shows when the
        room is open, partial, or fully booked.
      </p>
      <h3 class="display mt-10 text-2xl">Included equipment</h3>
      <ul class="mt-4 space-y-3 text-dim">
        <?php foreach ($STUDIO_A_EQUIPMENT as $item): ?>
          <li class="border-l-2 border-mark pl-4"><?= e($item) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="mt-8 text-sm text-dim">Hours: <?= e($SITE["hours"]) ?>. Call <?= e($SITE["phone"]) ?> if you need the desk.</p>
    </div>
    <div class="reveal surface p-8" style="transition-delay:100ms">
      <h2 class="display text-3xl">Rental durations</h2>
      <p class="mt-3 text-sm text-dim">Price updates with duration. Server-side pricing — not a number you can edit in the browser.</p>
      <div class="mt-6">
        <table class="w-full text-left">
          <thead>
            <tr class="text-xs tracking-[0.14em] text-dim uppercase">
              <th class="py-2 font-medium">Duration</th>
              <th class="py-2 font-medium">Price</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as [$hours, $price]): ?>
              <tr class="border-t border-line">
                <td class="py-3"><?= (int) $hours ?> hours</td>
                <td class="py-3">$<?= (int) $price ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-8"><?= cta("/book", "Book Studio A") ?></div>
    </div>
  </div>
</section>
<section class="section-block bg-aurora">
  <div class="wrap grid gap-8 md:grid-cols-3">
    <?php
    $steps = [
        ["01", "Pick a date", "The calendar marks available, partial, full, and unavailable days."],
        ["02", "Pick a time", "If a start time overlaps an existing hold, we show nearby open slots."],
        ["03", "Hold the room", "Duration sets the price. Overlaps are blocked on the server."],
    ];
    foreach ($steps as [$n, $t, $b]):
    ?>
      <div class="reveal">
        <p class="kicker"><?= e($n) ?></p>
        <h3 class="display mt-3 text-3xl"><?= e($t) ?></h3>
        <p class="mt-3 text-sm leading-6 text-dim"><?= e($b) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php page_end();
