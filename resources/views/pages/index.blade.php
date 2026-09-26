<?php
global $SITE, $MEDIA, $STUDIO_A_PRICES, $STUDIO_A_DURATIONS, $EVENT_CATEGORIES, $MEMBERSHIPS, $STORIES, $show_phase_2;
$GLOBALS["page_scripts"] = ["assets/js/home.js"];
page_start(
    "Musicians World — Arizona’s creator hub",
    "A creative environment for musicians, DJs, artists, and creators. Book Studio A, explore SoundTunnel, join DJ School, or become a member."
);
$today = phoenix_date_iso();
$slides = [
    ["asset" => $MEDIA["hero"], "label" => "Musicians World"],
    ["asset" => $MEDIA["studioA"][0], "label" => "Studio A"],
    ["asset" => $MEDIA["soundtunnel"][0], "label" => "SoundTunnel"],
    ["asset" => $MEDIA["djPerform"], "label" => "DJ School"],
];
?>

<section class="home-hero" id="home-hero">
  <div class="home-slides" aria-hidden="true">
    <?php foreach ($slides as $i => $slide): ?>
      <div class="home-slide <?= $i === 0 ? "is-on" : "" ?>">
        <?= media_img($slide["asset"], "home-slide-img", "100vw", $i === 0) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="photo-shade home-hero-shade"></div>
  <div class="home-hero-nav" aria-label="Banner slides">
    <button type="button" data-slide="prev" aria-label="Previous slide">←</button>
    <button type="button" data-slide="next" aria-label="Next slide">→</button>
  </div>
  <div class="wrap home-hero-copy">
    <p class="kicker fade-up">Arizona · Est. <?= (int) $SITE["established"] ?></p>
    <h1 class="display fade-up fade-up-d1 mt-4 max-w-5xl text-[2.55rem] leading-[0.9] sm:mt-5 sm:text-6xl md:text-8xl lg:text-[7.5rem]">
      Find a space.<br />Book a session.
    </h1>
    <p class="fade-up fade-up-d2 mt-5 max-w-2xl text-sm leading-6 text-white/90 sm:mt-7 sm:text-xl sm:leading-7">
      Studio A, SoundTunnel, DJ School, and membership in <?= e($SITE["location"]) ?>. Open <?= e($SITE["hours"]) ?>.
    </p>

    <form class="home-search fade-up fade-up-d3" id="home-search" action="<?= e(url("/book")) ?>" method="get">
      <label class="home-search-field">
        <span>Where</span>
        <input value="<?= e($SITE["location"]) ?>" readonly />
      </label>
      <label class="home-search-field">
        <span>When</span>
        <input type="date" name="date" min="<?= e($today) ?>" value="<?= e($today) ?>" />
      </label>
      <label class="home-search-field">
        <span>Space</span>
        <select name="space" id="home-space">
          <option value="studio-a">Studio A</option>
          <option value="soundtunnel">SoundTunnel</option>
          <option value="dj-school">DJ School</option>
        </select>
      </label>
      <label class="home-search-field" id="home-duration-wrap">
        <span>Duration</span>
        <select name="duration">
          <?php foreach ($STUDIO_A_DURATIONS as $h): ?>
            <option value="<?= (int) $h ?>"><?= (int) $h ?> hours · $<?= (int) $STUDIO_A_PRICES[$h] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="home-search-go">
        <button type="submit">Search</button>
      </div>
    </form>

    <div class="home-chips fade-up fade-up-d3">
      <a class="home-chip" href="<?= e(url("/spaces/studio-a")) ?>">Studio A</a>
      <a class="home-chip" href="<?= e(url("/soundtunnel")) ?>">SoundTunnel</a>
      <a class="home-chip" href="<?= e(url("/dj-school")) ?>">DJ School</a>
      <a class="home-chip" href="<?= e(url("/membership")) ?>">Membership</a>
      <a class="home-chip" href="<?= e(url("/events")) ?>">Events</a>
    </div>
  </div>
  <div class="home-dots" id="home-dots">
    <?php foreach ($slides as $i => $slide): ?>
      <button type="button" class="<?= $i === 0 ? "is-on" : "" ?>" data-dot="<?= $i ?>" aria-label="<?= e($slide["label"]) ?>"></button>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap grid gap-8 py-14 sm:gap-12 sm:py-20 lg:grid-cols-[1.15fr_0.85fr] lg:items-end lg:py-24">
    <div class="reveal">
      <p class="kicker">What this is</p>
      <h2 class="display mt-4 max-w-3xl text-4xl leading-[0.95] sm:text-5xl md:text-7xl">Not another lockout. A creative house.</h2>
    </div>
    <div class="reveal" style="transition-delay:120ms">
      <p class="story-body text-base leading-8 md:text-lg text-dim">
        Musicians World is more than a room to rent. It is a creative environment designed for musicians, DJs,
        artists, performers, photographers, videographers, and the community around them. Walk in to create. Stay
        to connect, learn, perform, and grow.
      </p>
    </div>
</section>

<section class="border-y border-line bg-aurora">
  <div class="wrap grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-4">
    <?php
    $stats = [
        ["Est. 2018", "Arizona’s creator hub"],
        ["Location", $SITE["location"]],
        [$SITE["hours"], "Every day"],
        ["Studio A from $" . $STUDIO_A_PRICES[2], "Two-hour lockout"],
    ];
    foreach ($stats as $i => [$k, $v]):
    ?>
      <div class="reveal stat-cell bg-raised px-6 py-10" style="transition-delay:<?= $i * 80 ?>ms">
        <p class="display text-2xl md:text-3xl"><?= e($k) ?></p>
        <p class="mt-2 text-sm text-dim"><?= e($v) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-block sa-showcase">
  <div class="wrap">
    <div class="grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
      <div class="reveal">
        <p class="kicker">Studio A</p>
        <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl">Musicians in the room.</h2>
      </div>
      <div class="reveal" style="transition-delay:80ms">
        <p class="max-w-xl text-sm leading-7 text-dim sm:text-base sm:leading-8">
          Rehearsal, practice, and sessions that actually start. Studio A is built for artists using the kit, the
          PA, and the mics — not an empty lockout on a listing photo.
        </p>
      </div>
    </div>
    <div class="reveal mt-10" style="transition-delay:120ms">
      <?php gallery($MEDIA["studioA"], "Studio A rehearsal room at Musicians World"); ?>
    </div>
    <div class="reveal mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap" style="transition-delay:180ms">
      <?= cta("/book", "Book Studio A") ?>
      <?= cta("/spaces/studio-a", "View Studio A", true) ?>
    </div>
  </div>
</section>

<section class="wrap py-14 md:py-24">
  <div class="reveal">
    <p class="kicker">Primary rooms</p>
    <h2 class="display mt-3 max-w-3xl text-4xl sm:text-5xl md:text-6xl">Book. Perform. Learn.</h2>
    <p class="mt-5 max-w-2xl text-dim">
      Three live revenue areas sit at the center of the house. Studio A is bookable now. SoundTunnel and DJ School
      run by request and enrollment.
    </p>
  </div>
</section>

<section class="wrap grid gap-px bg-line md:grid-cols-3">
  <?php
  space_card(
      $MEDIA["studioA"][0],
      "Studio",
      "Studio A",
      "Professional rehearsal and creative studio rental. Full drum kit, professional PA, up to four microphones. Open 9AM–2AM.",
      ["href" => "/book", "label" => "Book Studio A"],
      ["href" => "/spaces/studio-a", "label" => "View the room"]
  );
  space_card(
      $MEDIA["soundtunnelDj"],
      "Venue",
      "SoundTunnel",
      "Creative performance and event environment for DJ nights, live shows, showcases, workshops, private events, and community nights.",
      ["href" => "/soundtunnel", "label" => "Explore SoundTunnel"]
  );
  space_card(
      $MEDIA["dj"],
      "Education",
      "DJ School",
      "DJ education for beginners and developing DJs. Fundamentals, beginner classes, and private lessons — in a booth, not a slideshow.",
      ["href" => "/dj-school", "label" => "Explore DJ School"]
  );
  ?>
</section>

<?php story_band($STORIES[1]); ?>

<section class="section-block">
  <div class="wrap">
    <div class="reveal">
      <p class="kicker">The house</p>
      <h2 class="display mt-3 max-w-3xl text-4xl sm:text-5xl md:text-6xl">Five moves. One ecosystem.</h2>
    </div>
    <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-5">
      <?php
      $pillars = [
          ["Create", "Lock out Studio A and get to work. A professional rehearsal room with a drum kit, PA, and mics — built for sessions that actually move."],
          ["Connect", "Join the MusiciansWorld Creative Network. Stay in the loop for events, open houses, workshops, and the people using the same rooms."],
          ["Learn", "DJ School is a separate education stream — fundamentals, beginner classes, and private lessons in a real playing environment."],
          ["Perform", "SoundTunnel is the night room: DJ events, live sets, showcases, workshops, and private events with audio, lighting, and an LED wall."],
          ["Grow", "Membership keeps you in the network after the session ends — from a free community seat to a limited Founding Member circle."],
      ];
      foreach ($pillars as $i => [$title, $body]):
      ?>
        <div class="reveal pillar-card border-t border-line pt-5" style="transition-delay:<?= $i * 70 ?>ms">
          <p class="text-xs tracking-[0.18em] text-mark uppercase">0<?= $i + 1 ?></p>
          <h3 class="display mt-3 text-3xl"><?= e($title) ?></h3>
          <p class="mt-3 text-sm leading-6 text-dim"><?= e($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
story_band($STORIES[2]);
story_band($STORIES[3]);
?>

<section class="section-block" id="spaces">
  <div class="wrap">
    <div class="reveal">
      <p class="kicker">Spaces</p>
      <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl">Rooms built to work in.</h2>
      <p class="mt-5 max-w-2xl text-dim">Phase one booking is Studio A. SoundTunnel is quoted from the desk.</p>
    </div>
    <div class="listing-grid">
      <article class="listing-card reveal">
        <div class="img-frame aspect-[4/3]">
          <?= media_img($MEDIA["studioA"][0], "", "(min-width: 768px) 50vw, 100vw") ?>
          <div class="photo-shade"></div>
        </div>
        <div class="listing-body">
          <p class="kicker">Studio · Bookable now</p>
          <h3 class="display mt-2 text-3xl">Studio A</h3>
          <p class="mt-3 text-sm leading-6 text-dim">Professional rehearsal lockout. Full drum kit, professional PA, up to 4 microphones.</p>
          <p class="listing-meta"><span><?= e(location_for("studio-a")) ?></span><span><?= e($SITE["hours"]) ?></span></p>
          <p class="listing-price">From $<?= (int) $STUDIO_A_PRICES[2] ?> · 2 hours</p>
          <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <?= cta("/book", "Check availability") ?>
            <?= cta("/spaces/studio-a", "View the room", true) ?>
          </div>
        </div>
      </article>
      <article class="listing-card reveal">
        <div class="img-frame aspect-[4/3]">
          <?= media_img($MEDIA["soundtunnelDj"], "", "(min-width: 768px) 50vw, 100vw") ?>
          <div class="photo-shade photo-shade-soft"></div>
        </div>
        <div class="listing-body">
          <p class="kicker">Venue · By request</p>
          <h3 class="display mt-2 text-3xl">SoundTunnel</h3>
          <p class="mt-3 text-sm leading-6 text-dim">Performance and event environment for DJ nights, live sets, showcases, and private events.</p>
          <p class="listing-meta"><span><?= e(location_for("soundtunnel")) ?></span><span>Quoted from the desk</span></p>
          <p class="listing-price">Request pricing</p>
          <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <?= cta("/soundtunnel", "Explore SoundTunnel") ?>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>

<?php
story_band($STORIES[4]);
story_band($STORIES[5]);
?>

<section class="section-block" id="events">
  <div class="wrap">
    <div class="reveal">
      <p class="kicker">Events</p>
      <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl">What’s on.</h2>
      <p class="mt-5 max-w-2xl text-dim">DJ events, live performances, workshops, showcases, community nights, and open houses. Nothing is posted on this calendar yet.</p>
    </div>
    <div class="mt-8 flex flex-wrap gap-2">
      <?php foreach ($EVENT_CATEGORIES as $c): ?>
        <span class="border border-line px-4 py-2 text-xs tracking-wide uppercase text-dim"><?= e($c) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="mt-10 border border-dashed border-line px-6 py-16 text-center">
      <p class="display text-4xl">No public events listed.</p>
      <p class="mt-3 text-sm text-dim">When a night is confirmed it will land here. Join the free community list to hear first.</p>
      <div class="mt-8 flex justify-center"><?= cta("/membership", "Get event updates") ?></div>
    </div>
  </div>
</section>

<section class="section-block bg-aurora" id="membership">
  <div class="wrap">
    <div class="reveal">
      <p class="kicker">Membership</p>
      <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl">Get in the network.</h2>
      <p class="mt-5 max-w-2xl text-dim">Two public memberships at launch. Free Community keeps you in the loop. Founding Member is $150 / month.</p>
    </div>
    <div class="listing-grid">
      <?php foreach ($MEMBERSHIPS as $m):
          if (!$show_phase_2 && $m["phase"] !== "phase1") continue;
          $priceKicker = trim($m["priceValue"] . " " . $m["pricePeriod"]);
      ?>
        <article class="listing-card reveal <?= !empty($m["featured"]) ? "border-mark" : "" ?>">
          <div class="listing-body">
            <p class="kicker"><?= e($priceKicker) ?></p>
            <?php if ($m["priceNote"] !== ""): ?>
              <p class="mt-1 text-xs tracking-[0.14em] text-mark uppercase"><?= e($m["priceNote"]) ?></p>
            <?php endif; ?>
            <h3 class="display mt-2 text-3xl"><?= e($m["name"]) ?></h3>
            <?php if (!empty($m["headline"])): ?><p class="mt-3"><?= e($m["headline"]) ?></p><?php endif; ?>
            <ul class="mt-5 space-y-2 text-sm text-dim">
              <?php foreach (array_slice($m["benefits"], 0, 4) as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
            </ul>
            <div class="mt-7"><?= cta("/membership", $m["cta"]) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="mt-6 max-w-2xl text-xs text-dim"><?= e($MEMBERSHIPS[1]["disclaimer"]) ?></p>
  </div>
</section>

<?php story_band($STORIES[6]); ?>

<section class="section-block">
  <div class="wrap">
    <div class="reveal max-w-3xl">
    <p class="kicker">Visit</p>
    <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl"><?= e($SITE["location"]) ?>. Open when the work is.</h2>
    <p class="mt-5 text-base leading-7 text-dim sm:text-lg sm:leading-8">
      <?= e($SITE["hours"]) ?>. Call <?= e($SITE["phone"]) ?> or write <?= e($SITE["email"]) ?>. Studio A books online. SoundTunnel and DJ School go through the desk.
    </p>
    <div class="mt-8 flex flex-col gap-3 sm:mt-10 sm:flex-row sm:flex-wrap">
      <?= cta("/book", "Book Studio A") ?>
      <?= cta("/contact", "Talk to the desk", true) ?>
    </div>
    </div>
  </div>
</section>

<?php page_end();
