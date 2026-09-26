<?php
global $MEDIA, $EVENT_CATEGORIES;
page_start("Events · Musicians World", "DJ events, live performances, workshops, showcases, community nights, and open houses at Musicians World.");
page_hero(
    "Events",
    "What’s<br />on.",
    "DJ events, live performances, workshops, artist showcases, community events, and open houses. Nothing is posted on this calendar yet.",
    $MEDIA["events"],
    ["href" => "/membership", "label" => "Join the community — free"],
    ["href" => "/contact", "label" => "Propose a night"]
);
?>
<section class="section-block bg-aurora">
  <div class="wrap">
    <div class="reveal">
      <p class="kicker">Categories</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">The kinds of nights this house holds.</h2>
      <p class="mt-4 max-w-2xl text-dim">
        When a date is confirmed it lands here. We don’t invent a calendar. Join the free community list to hear
        first.
      </p>
    </div>
    <div class="mt-10 flex flex-wrap gap-2">
      <?php foreach ($EVENT_CATEGORIES as $c): ?>
        <span class="inner-chip"><?= e($c) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="mt-14">
      <div class="border border-dashed border-line px-6 py-20 text-center">
        <p class="display text-4xl md:text-5xl">No public events listed.</p>
        <p class="mt-3 text-sm text-dim">When a night is confirmed it will land here. Join the free community list to hear first.</p>
      </div>
    </div>
    <div class="mt-10"><?= cta("/membership", "Get event updates") ?></div>
  </div>
</section>
<?php page_end();
