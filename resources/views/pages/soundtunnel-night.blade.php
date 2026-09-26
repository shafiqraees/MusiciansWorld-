<?php
global $SITE, $SOUNDTUNNEL_FEATURES;
$GLOBALS["page_scripts"] = ["assets/js/forms.js"];
$GLOBALS["page_styles"] = ["assets/css/st-night.css"];
page_start(
    "SoundTunnel Night · MusiciansWorld",
    "The night room at MusiciansWorld — DJ events, live performances, and private nights in a full performance environment."
);
$img = static function (string $file): string {
    return url("assets/soundtunnel-night/" . $file);
};
?>
<div class="stn">
  <section class="stn-hero">
    <div class="stn-hero-media" aria-hidden="true">
      <img src="<?= e($img("hero.png")) ?>" alt="" />
    </div>
    <div class="stn-hero-shade"></div>
    <div class="wrap stn-hero-copy">
      <p class="stn-kicker">Perform | Connect | Create | Belong</p>
      <h1>SoundTunnel</h1>
      <p class="stn-lead">The night room. Real stage. Real sound. Real crowd energy.</p>
      <p class="stn-body">
        SoundTunnel is the performance environment inside MusiciansWorld — DJ events, live shows, artist showcases,
        workshops, and private nights. Built for the night you actually throw, not a rehearsal lockout.
      </p>
    </div>
  </section>

  <section class="stn-section" style="padding-top:0">
    <div class="wrap">
      <div class="stn-cards">
        <a class="stn-card" href="#dj-events">
          <div class="stn-card-photo"><img src="<?= e($img("card-events.png")) ?>" alt="SoundTunnel DJ booth" /></div>
          <div class="stn-card-body">
            <h3>DJ Events</h3>
            <p class="stn-card-meta">Booth · Lasers · LED wall</p>
            <p>The booth in full wash — mixers, lighting, and the room ready for the set.</p>
            <span class="stn-card-cta stn-cta-cyan">View the booth →</span>
          </div>
        </a>
        <a class="stn-card" href="#live">
          <div class="stn-card-photo"><img src="<?= e($img("card-live.png")) ?>" alt="SoundTunnel live performance deck" /></div>
          <div class="stn-card-body">
            <h3>Live Performances</h3>
            <p class="stn-card-meta">Stage wash · Room ready</p>
            <p>Artist showcases, live sets, and community nights with professional audio and lighting.</p>
            <span class="stn-card-cta stn-cta-purple">View the stage →</span>
          </div>
        </a>
        <a class="stn-card" href="#private">
          <div class="stn-card-photo"><img src="<?= e($img("card-private.png")) ?>" alt="Private night in SoundTunnel" /></div>
          <div class="stn-card-body">
            <h3>Private Nights</h3>
            <p class="stn-card-meta">Quoted from the desk</p>
            <p>Birthdays, brand nights, and closed events. Tell us the date, headcount, and the show.</p>
            <span class="stn-card-cta stn-cta-ghost">Request pricing →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <section id="dj-events" class="stn-section">
    <div class="wrap stn-split">
      <div class="stn-split-media">
        <img src="<?= e($img("performer.png")) ?>" alt="DJ performing in SoundTunnel" />
        <p class="stn-quote">“Good music changes lives.”</p>
      </div>
      <div>
        <p class="stn-kicker">DJ Events</p>
        <h2 class="display mt-3 text-4xl md:text-6xl" style="text-transform:uppercase">The booth is the room.</h2>
        <p class="stn-body">
          Lasers, logo wall, night mode. SoundTunnel is built so a DJ set feels like a night — not a practice lockout.
          House equipment is ready. Bring a USB or play the house decks.
        </p>
        <div class="stn-stats">
          <span><?= icon("bars") ?> Professional audio</span>
          <span><?= icon("trending") ?> Lighting</span>
          <span><?= icon("users") ?> LED video wall</span>
          <span><?= icon("gear") ?> DJ capabilities</span>
        </div>
        <p class="stn-kicker" style="margin-top:1.6rem">In the room</p>
        <ul class="stn-list">
          <?php foreach ($SOUNDTUNNEL_FEATURES as $f): ?>
            <li><?= e($f) ?></li>
          <?php endforeach; ?>
          <li>Quoted from the desk</li>
        </ul>
        <a href="#request" class="btn btn-mark mt-8">Request pricing →</a>
      </div>
    </div>
  </section>

  <section id="live" class="stn-section" style="padding-top:0">
    <div class="wrap stn-split is-flip">
      <div class="stn-split-media">
        <img src="<?= e($img("host.png")) ?>" alt="Host preparing a SoundTunnel night" />
        <p class="stn-side-mark">Skills. Beats. Opportunity.</p>
      </div>
      <div>
        <p class="stn-kicker">Private nights</p>
        <h2 class="display mt-3 text-4xl md:text-6xl" style="text-transform:uppercase">Throw the night you came to throw.</h2>
        <p class="stn-body">
          Whether you are booking a first showcase or a private party, SoundTunnel is quoted from the desk.
          <?= e($SITE["hours"]) ?>. <?= e(location_for("soundtunnel")) ?>.
        </p>
        <div class="stn-features">
          <div class="stn-feature"><?= icon("target") ?><p>Personalized setup</p></div>
          <div class="stn-feature"><?= icon("users") ?><p>Any event size</p></div>
          <div class="stn-feature"><?= icon("gear") ?><p>House gear ready</p></div>
          <div class="stn-feature"><?= icon("calendar") ?><p>Flexible dates</p></div>
        </div>
        <a href="#request" class="btn stn-cta-purple mt-8">View night packages →</a>
      </div>
    </div>
  </section>

  <section id="private" class="stn-section" style="padding-top:0">
    <div class="wrap grid gap-10 lg:grid-cols-[0.9fr_1.1fr]">
      <div>
        <p class="stn-kicker">Rental</p>
        <h2 class="display mt-3 text-4xl md:text-5xl">Request pricing</h2>
        <p class="stn-body">
          Pricing is not listed until it is current. Tell us the date, the headcount, and what you are putting on.
        </p>
      </div>
      <div id="request" class="stn-form">
        <form data-form="soundtunnel" class="grid gap-3 md:grid-cols-2">
          <label class="block">
            <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Name</span>
            <input name="name" required class="w-full border border-line bg-bg px-3 py-3" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email</span>
            <input name="email" type="email" required class="w-full border border-line bg-bg px-3 py-3" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Preferred date</span>
            <input name="date" type="date" class="w-full border border-line bg-bg px-3 py-3" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Estimated headcount</span>
            <input name="headcount" class="w-full border border-line bg-bg px-3 py-3" />
          </label>
          <div class="md:col-span-2">
            <label class="block">
              <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Event type</span>
              <select name="eventType" required class="w-full border border-line bg-bg px-3 py-3">
                <option value="">Select one…</option>
                <?php foreach ([
                    "Private Party", "Birthday", "Corporate Event", "Concert/Live Performance", "Artist Showcase",
                    "DJ Event", "Church/Faith Event", "Wedding/Reception", "Community Event", "Content/Production", "Other",
                ] as $et): ?>
                  <option value="<?= e($et) ?>"><?= e($et) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="md:col-span-2">
            <label class="block">
              <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">What are you putting on?</span>
              <textarea name="message" required rows="4" class="w-full border border-line bg-bg px-3 py-3"></textarea>
            </label>
          </div>
          <label class="hp-field md:col-span-2" aria-hidden="true">
            Leave blank
            <input name="website" type="text" tabindex="-1" autocomplete="off" />
          </label>
          <div class="md:col-span-2">
            <button class="btn btn-mark" type="submit">Request pricing</button>
            <p class="form-msg mt-3 text-sm text-dim" hidden></p>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
<?php page_end();
