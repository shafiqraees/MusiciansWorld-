<?php
global $MEDIA, $SITE, $SOUNDTUNNEL, $SOUNDTUNNEL_USES, $SOUNDTUNNEL_FEATURES;
$GLOBALS["page_scripts"] = ["assets/js/forms.js", "assets/js/soundtunnel-video.js", "assets/js/soundtunnel-registration.js"];
$GLOBALS["page_styles"] = ["assets/css/soundtunnel-registration.css"];
page_start(
    "SoundTunnel · Musicians World",
    "Creative performance and event environment for DJ events, live shows, showcases, workshops, and private nights."
);
?>
<?php
page_hero(
    "SoundTunnel · Performance",
    "The night<br />room.",
    "A creative performance and event environment — DJ events, live performances, private events, artist showcases, workshops, and community nights.",
    $MEDIA["soundtunnelVenue"],
    ["href" => "#request", "label" => "Request pricing"]
);
?>

<section class="section-block bg-mesh">
  <div class="wrap grid items-center gap-10 lg:grid-cols-2">
    <div class="reveal">
      <p class="kicker">The booth</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">Lasers. Logo wall. Night mode.</h2>
      <p class="mt-5 leading-7 text-dim">
        The SoundTunnel booth in full wash — lasers, LED wall, and the room ready for the set.
      </p>
    </div>
    <div class="reveal st-hero-media" style="transition-delay:100ms">
      <div class="st-portrait-frame">
        <?= media_img($MEDIA["soundtunnelDj"], "", "(min-width: 768px) 420px, 90vw") ?>
        <div class="photo-shade photo-shade-soft"></div>
      </div>
    </div>
  </div>
</section>

<section class="section-block bg-aurora">
  <div class="wrap st-lights-grid">
    <div class="reveal st-lights-copy">
      <p class="kicker">Lights</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">Stage wash. Room ready.</h2>
      <p class="mt-5 leading-7 text-dim">
        Purple and green stage lighting across the hall — the same room shown empty in photos, lit for the night.
      </p>
    </div>
    <div class="reveal st-lights-media" style="transition-delay:100ms">
      <div class="st-video st-video-panel st-video-panel-start" data-st-video>
        <video
          class="st-video-el"
          poster="<?= e($SOUNDTUNNEL["poster"]) ?>"
          muted
          loop
          playsinline
          preload="metadata"
          hidden
        >
          <source src="<?= e($SOUNDTUNNEL["videoWebm"]) ?>" type="video/webm" />
          <source src="<?= e($SOUNDTUNNEL["video"]) ?>" type="video/mp4" />
        </video>
        <img src="<?= e($SOUNDTUNNEL["poster"]) ?>" alt="SoundTunnel stage lighting" class="st-video-el" data-st-poster />
        <div class="photo-shade photo-shade-soft"></div>
      </div>
    </div>
  </div>
</section>

<section class="section-block bg-aurora st-gallery-section">
  <div class="wrap">
    <div class="st-gallery">
      <div class="img-frame st-gallery-venue">
        <?= media_img($MEDIA["soundtunnelVenue"], "", "(min-width: 768px) 60vw, 100vw", true, "SoundTunnel performance space") ?>
        <div class="photo-shade"></div>
      </div>
      <div class="img-frame st-gallery-dj">
        <?= media_img($MEDIA["soundtunnelDj"], "", "(min-width: 768px) 420px, 90vw") ?>
        <div class="photo-shade photo-shade-soft"></div>
      </div>
      <div class="st-gallery-video">
        <div class="st-video" data-st-video>
          <video
            class="st-video-el"
            poster="<?= e($SOUNDTUNNEL["poster"]) ?>"
            muted
            loop
            playsinline
            preload="metadata"
            hidden
          >
            <source src="<?= e($SOUNDTUNNEL["videoWebm"]) ?>" type="video/webm" />
            <source src="<?= e($SOUNDTUNNEL["video"]) ?>" type="video/mp4" />
          </video>
          <img src="<?= e($SOUNDTUNNEL["poster"]) ?>" alt="SoundTunnel stage lighting" class="st-video-el" data-st-poster />
          <div class="photo-shade photo-shade-soft"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block bg-mesh">
  <div class="wrap grid gap-14 lg:grid-cols-2">
    <div class="reveal">
      <p class="kicker">The room</p>
      <h2 class="display mt-3 text-4xl md:text-5xl">Built for the night you actually throw.</h2>
      <p class="mt-5 leading-8 text-dim">
        SoundTunnel sits inside Musicians World as the performance environment — not a rehearsal lockout. Rentals
        are quoted from the desk. Tell us the date, the headcount, and what you are putting on.
      </p>
      <div class="st-venue-frame mt-8">
        <?= media_img($MEDIA["soundtunnelVenue"], "", "(min-width: 768px) 50vw, 100vw") ?>
        <div class="st-venue-grade"></div>
      </div>
    </div>
    <div class="reveal grid gap-10 sm:grid-cols-2" style="transition-delay:100ms">
      <div>
        <h2 class="display text-3xl">Built for</h2>
        <ul class="mt-4 space-y-2 text-dim">
          <?php foreach ($SOUNDTUNNEL_USES as $u): ?><li><?= e($u) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h2 class="display text-3xl">In the room</h2>
        <ul class="mt-4 space-y-2 text-dim">
          <?php foreach ($SOUNDTUNNEL_FEATURES as $f): ?>
            <li class="border-l-2 border-mark pl-4"><?= e($f) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<section id="request" class="st-register-section">
  <div class="wrap">
    <div class="st-register-heading reveal">
      <div>
        <p class="kicker">SoundTunnel venue application</p>
        <h2 class="display mt-3 text-4xl md:text-6xl">Plan your night.</h2>
      </div>
      <p>Tell us what you are creating. The venue team reviews every application and follows up with availability, pricing, and next steps.</p>
    </div>

    <div class="st-wizard reveal" data-st-wizard style="transition-delay:80ms">
      <aside class="st-wizard-aside">
        <div class="st-wizard-brand">
          <span class="st-live-dot"></span>
          <span>SoundTunnel</span>
          <small>Event venue inquiry</small>
        </div>
        <ol class="st-steps" aria-label="Registration progress">
          <li class="is-active" data-step-indicator="1">
            <span>01</span><div><strong>Your details</strong><small>Who we should contact</small></div>
          </li>
          <li data-step-indicator="2">
            <span>02</span><div><strong>Event details</strong><small>Date, format, and guests</small></div>
          </li>
          <li data-step-indicator="3">
            <span>03</span><div><strong>Production</strong><small>Services and requirements</small></div>
          </li>
          <li data-step-indicator="4">
            <span>04</span><div><strong>Review</strong><small>Confirm and submit</small></div>
          </li>
        </ol>
        <div class="st-wizard-help">
          <strong>Need help first?</strong>
          <span><?= e($SITE["email"]) ?></span>
          <span><?= e($SITE["hours"]) ?></span>
        </div>
      </aside>

      <div class="st-wizard-main">
        <div class="st-mobile-progress">
          <div><span data-step-label>Step 1 of 4</span><strong data-step-title>Your details</strong></div>
          <div class="st-progress-track"><span data-progress-bar></span></div>
        </div>

        <form data-form="soundtunnel" data-soundtunnel-form novalidate>
          <section class="st-form-step is-active" data-step="1" aria-labelledby="st-step-1-title">
            <div class="st-step-head">
              <span>01</span>
              <div><p class="kicker">Start here</p><h3 id="st-step-1-title">Who should we contact?</h3><p>We will use these details only to discuss your SoundTunnel event.</p></div>
            </div>
            <div class="st-field-grid">
              <label class="st-field st-span-2">
                <span>Full name <b>*</b></span>
                <input name="name" autocomplete="name" required placeholder="Your full name" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Email address <b>*</b></span>
                <input name="email" type="email" autocomplete="email" required placeholder="you@example.com" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Phone number <b>*</b></span>
                <input name="phone" type="tel" autocomplete="tel" required placeholder="(602) 555-0123" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field st-span-2">
                <span>Organization or artist name <em>Optional</em></span>
                <input name="organization" autocomplete="organization" placeholder="Company, collective, or artist name" />
              </label>
            </div>
          </section>

          <section class="st-form-step" data-step="2" aria-labelledby="st-step-2-title" hidden>
            <div class="st-step-head">
              <span>02</span>
              <div><p class="kicker">The event</p><h3 id="st-step-2-title">Tell us about your night.</h3><p>Estimates are completely fine. Our team can help refine the details.</p></div>
            </div>
            <div class="st-field-grid">
              <label class="st-field st-span-2">
                <span>Event name <b>*</b></span>
                <input name="eventName" required placeholder="Example: Phoenix Producer Showcase" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field st-span-2">
                <span>Event type <b>*</b></span>
                <select name="eventType" required>
                  <option value="">Choose the closest match</option>
                  <?php foreach ([
                      "DJ Event / Club Night", "Concert / Live Performance", "Artist Showcase", "Private Party",
                      "Birthday Celebration", "Corporate Event", "Wedding / Reception", "Community Event",
                      "Workshop / Class", "Content / Production", "Church / Faith Event", "Other",
                  ] as $et): ?><option value="<?= e($et) ?>"><?= e($et) ?></option><?php endforeach; ?>
                </select>
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Preferred date <b>*</b></span>
                <input name="date" type="date" required min="<?= e(now()->addDay()->toDateString()) ?>" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Alternate date <em>Optional</em></span>
                <input name="alternateDate" type="date" min="<?= e(now()->addDay()->toDateString()) ?>" />
              </label>
              <label class="st-field">
                <span>Preferred start time <b>*</b></span>
                <input name="startTime" type="time" required />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Estimated duration <b>*</b></span>
                <select name="duration" required>
                  <option value="">Select duration</option>
                  <option>2–3 hours</option><option>4 hours</option><option>5–6 hours</option><option>7+ hours</option>
                </select>
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Estimated attendance <b>*</b></span>
                <input name="headcount" type="number" min="1" max="250" required placeholder="Up to 250 guests" />
                <small class="st-field-error"></small>
              </label>
              <label class="st-field">
                <span>Audience age range <b>*</b></span>
                <select name="audience" required>
                  <option value="">Select age range</option>
                  <option>All ages</option><option>16+</option><option>18+</option><option>21+</option><option>Private / invited guests</option>
                </select>
                <small class="st-field-error"></small>
              </label>
            </div>
            <fieldset class="st-choice-group">
              <legend>Will this be a ticketed event? <b>*</b></legend>
              <div class="st-choice-row">
                <label><input type="radio" name="ticketed" value="Yes" required /><span>Yes, tickets will be sold</span></label>
                <label><input type="radio" name="ticketed" value="No" required /><span>No, free or private entry</span></label>
              </div>
              <small class="st-group-error"></small>
            </fieldset>
            <label class="st-field st-conditional" data-ticket-price hidden>
              <span>Expected ticket price <em>Optional</em></span>
              <input name="ticketPrice" placeholder="Example: $20 advance / $25 door" />
            </label>
          </section>

          <section class="st-form-step" data-step="3" aria-labelledby="st-step-3-title" hidden>
            <div class="st-step-head">
              <span>03</span>
              <div><p class="kicker">Production plan</p><h3 id="st-step-3-title">What will your event need?</h3><p>Select everything you may need. Final services and pricing are confirmed with our team.</p></div>
            </div>
            <fieldset class="st-choice-group">
              <legend>Venue services <em>Select all that apply</em></legend>
              <div class="st-service-grid">
                <?php foreach ([
                    ["Sound engineer", "Technical support during your event"],
                    ["DJ services", "House DJ or performance support"],
                    ["Wireless microphones", "For hosts, speakers, or performers"],
                    ["Lighting upgrade", "Enhanced stage and room lighting"],
                    ["Video wall / projector", "Visuals, branding, or presentations"],
                    ["Haze / fog", "Atmospheric stage effects"],
                    ["Security staff", "Guest entry and floor support"],
                    ["Post-event cleanup", "Venue cleanup after your event"],
                ] as [$service, $detail]): ?>
                <label class="st-service-card">
                  <input type="checkbox" name="services[]" value="<?= e($service) ?>" />
                  <span class="st-service-check">✓</span>
                  <strong><?= e($service) ?></strong><small><?= e($detail) ?></small>
                </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <div class="st-field-grid st-production-fields">
              <fieldset class="st-choice-group">
                <legend>Will alcohol be served? <b>*</b></legend>
                <div class="st-choice-row">
                  <label><input type="radio" name="alcohol" value="Yes" required /><span>Yes</span></label>
                  <label><input type="radio" name="alcohol" value="No" required /><span>No</span></label>
                  <label><input type="radio" name="alcohol" value="Undecided" required /><span>Undecided</span></label>
                </div>
                <small class="st-group-error"></small>
              </fieldset>
              <label class="st-field">
                <span>Estimated event budget <em>Optional</em></span>
                <select name="budget">
                  <option value="">Prefer not to say</option>
                  <option>Under $1,000</option><option>$1,000–$2,500</option><option>$2,500–$5,000</option><option>$5,000+</option>
                </select>
              </label>
              <label class="st-field st-span-2">
                <span>Production notes <em>Optional</em></span>
                <textarea name="productionNotes" rows="4" placeholder="Tell us about performers, equipment, stage setup, load-in, vendors, or special requirements."></textarea>
              </label>
            </div>
          </section>

          <section class="st-form-step" data-step="4" aria-labelledby="st-step-4-title" hidden>
            <div class="st-step-head">
              <span>04</span>
              <div><p class="kicker">Final review</p><h3 id="st-step-4-title">Almost ready.</h3><p>Review the summary, add anything we should know, then send your application.</p></div>
            </div>
            <div class="st-review" data-review-summary aria-live="polite"></div>
            <div class="st-field-grid">
              <label class="st-field st-span-2">
                <span>Describe your event and vision <b>*</b></span>
                <textarea name="message" required minlength="20" rows="5" placeholder="What should the SoundTunnel team know about the experience you want to create?"></textarea>
                <small class="st-field-error"></small>
              </label>
              <label class="st-field st-span-2">
                <span>How did you hear about us? <em>Optional</em></span>
                <select name="referral">
                  <option value="">Select one</option><option>Friend or referral</option><option>Instagram / social media</option>
                  <option>Attended an event</option><option>Google search</option><option>MusiciansWorld member</option><option>Other</option>
                </select>
              </label>
            </div>
            <label class="st-consent">
              <input name="terms" type="checkbox" value="1" required />
              <span>I confirm these details are accurate and understand this inquiry does not reserve the venue. Dates and pricing are confirmed by the SoundTunnel team. <b>*</b></span>
            </label>
            <small class="st-consent-error"></small>
            <label class="hp-field" aria-hidden="true">Leave blank<input name="website" type="text" tabindex="-1" autocomplete="off" /></label>
          </section>

          <div class="st-form-actions">
            <button class="st-btn-secondary" type="button" data-step-back hidden>Back</button>
            <p class="st-save-note"><span>✓</span> Your answers stay here while you complete the form.</p>
            <button class="st-btn-primary" type="button" data-step-next>Continue <span>→</span></button>
            <button class="st-btn-primary" type="submit" data-step-submit hidden>Submit application <span>→</span></button>
          </div>
          <div class="st-submit-message form-msg" role="status" hidden></div>
        </form>

        <div class="st-success" data-st-success hidden>
          <span class="st-success-icon">✓</span>
          <p class="kicker">Application received</p>
          <h3>Thank you for choosing SoundTunnel.</h3>
          <p>Our venue team will review your request and contact you with availability and next steps. Please check your inbox for the registration email.</p>
          <a class="btn btn-mark" href="<?= e(url("/soundtunnel")) ?>">Back to SoundTunnel</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_end();
