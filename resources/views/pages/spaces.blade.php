<?php
global $MEDIA, $SITE, $RESOURCES;
page_start("Spaces · Musicians World", "Studio A rehearsal lockouts and SoundTunnel, the performance room. " . $SITE["location"] . ".");
page_hero(
    "Spaces · " . $SITE["location"],
    "Rooms built<br />to work in.",
    "Phase one booking is Studio A. SoundTunnel is available by request. " . $SITE["location"] . ", open 9AM–2AM.",
    $MEDIA["studioA"][0],
    ["href" => "/book", "label" => "Book Studio A"],
    ["href" => "/soundtunnel", "label" => "Explore SoundTunnel"]
);
?>
<section class="section-block bg-mesh">
  <div class="wrap">
    <div class="reveal max-w-2xl">
      <p class="kicker">The floor plan</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">One house. Two rooms with different jobs.</h2>
      <p class="mt-5 text-dim leading-7">
        Studio A is the lockout — rehearsal, writing, and creative sessions you can hold on the calendar. SoundTunnel
        is the night room, quoted from the desk for the event you actually want to throw.
      </p>
    </div>
  </div>
</section>
<section class="wrap grid gap-px bg-line md:grid-cols-2">
  <?php
  space_card(
      $RESOURCES["studio-a"]["image"],
      "Studio",
      "Studio A",
      "Professional rehearsal and creative studio rental. Drum kit, PA, up to four microphones. Book 2 to 8 hours online.",
      ["href" => "/spaces/studio-a", "label" => "View Studio A"],
      ["href" => "/book", "label" => "Check availability"]
  );
  space_card(
      $MEDIA["soundtunnelDj"],
      "Venue",
      "SoundTunnel",
      "Creative performance and event environment. DJ events, live shows, private nights, showcases, and workshops.",
      ["href" => "/soundtunnel", "label" => "Explore SoundTunnel"]
  );
  ?>
</section>
<section class="section-block bg-aurora">
  <div class="wrap grid gap-8 md:grid-cols-3">
  <?php
  $bits = [["Hours", $SITE["hours"]], ["Location", $SITE["location"]], ["Desk", $SITE["phone"]]];
  foreach ($bits as $i => [$t, $b]):
  ?>
    <div class="reveal border-t border-line pt-5" style="transition-delay:<?= $i * 80 ?>ms">
      <h3 class="text-sm tracking-[0.16em] text-dim uppercase"><?= e($t) ?></h3>
      <p class="mt-3 text-lg"><?= e($b) ?></p>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php page_end();
