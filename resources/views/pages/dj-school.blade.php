<?php
global $SITE;
$GLOBALS["page_scripts"] = ["assets/js/forms.js", "assets/js/dj-school-registration.js"];
$GLOBALS["page_styles"] = ["assets/css/st-night.css", "assets/css/soundtunnel-registration.css"];
page_start(
    "DJ School · Musicians World",
    "Start your DJ journey at MusiciansWorld DJ School — fundamentals, performance lab, and private lessons."
);
$img = static function (string $file): string {
    return url("assets/soundtunnel-night/" . $file);
};
$choice = static function (string $legend, string $name, array $options, string $type = "radio", bool $required = true): void {
    $group = $type === "checkbox" ? ' data-require-group="'.e($name).'[]"' : "";
    echo '<fieldset class="st-choice-group"'.$group.'>';
    echo '<legend>'.e($legend).($required ? ' <b>*</b>' : ' <em>Optional</em>').'</legend>';
    echo '<div class="st-choice-row">';
    foreach ($options as $option) {
        $inputName = $type === "checkbox" ? $name."[]" : $name;
        $req = ($required && $type === "radio") ? " required" : "";
        echo '<label><input type="'.$type.'" name="'.e($inputName).'" value="'.e($option).'"'.$req.' /><span>'.e($option).'</span></label>';
    }
    echo '</div><small class="st-group-error"></small></fieldset>';
};
?>
<div class="stn">
  <section class="stn-hero">
    <div class="stn-hero-media" aria-hidden="true">
      <img src="<?= e($img("hero.png")) ?>" alt="" />
    </div>
    <div class="stn-hero-shade"></div>
    <div class="wrap stn-hero-copy">
      <p class="stn-kicker">Learn | Practice | Perform | Belong</p>
      <h1>DJ School</h1>
      <p class="stn-lead">Start your DJ journey. Real skills. Real instructors. Real opportunity.</p>
      <p class="stn-body">
        MWCN DJ School is hands-on training inside MusiciansWorld. Whether you are stepping behind the decks
        for the first time or sharpening a set you already play, tell us your goals and we will connect you
        with the program that fits.
      </p>
      <a href="#register" class="btn btn-mark mt-8">Start your inquiry →</a>
    </div>
  </section>

  <section class="stn-section" style="padding-top:0">
    <div class="wrap">
      <div class="stn-cards">
        <a class="stn-card" href="#programs">
          <div class="stn-card-photo"><img src="<?= e($img("card-events.png")) ?>" alt="DJ booth ready for a lesson" /></div>
          <div class="stn-card-body">
            <h3>DJ Fundamentals</h3>
            <p class="stn-card-meta">Beginner · First time on the decks</p>
            <p>Beatmatching, tempo, and the first mix — built for someone who has never used DJ equipment.</p>
            <span class="stn-card-cta stn-cta-cyan">See the beginner path →</span>
          </div>
        </a>
        <a class="stn-card" href="#programs">
          <div class="stn-card-photo"><img src="<?= e($img("card-live.png")) ?>" alt="Performance setup for developing DJs" /></div>
          <div class="stn-card-body">
            <h3>Performance Lab</h3>
            <p class="stn-card-meta">Intermediate · Real sets</p>
            <p>Mixing, phrasing, and playing for a room once the basics are already in your hands.</p>
            <span class="stn-card-cta stn-cta-purple">See the lab →</span>
          </div>
        </a>
        <a class="stn-card" href="#register">
          <div class="stn-card-photo"><img src="<?= e($img("card-private.png")) ?>" alt="Private DJ lesson environment" /></div>
          <div class="stn-card-body">
            <h3>Private Lessons</h3>
            <p class="stn-card-meta">1-on-1 · Your goals</p>
            <p>Personalized instruction for a skill, a set, or a specific event you are preparing for.</p>
            <span class="stn-card-cta stn-cta-ghost">Request a lesson →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <section id="programs" class="stn-section">
    <div class="wrap stn-split">
      <div class="stn-split-media">
        <img src="<?= e($img("performer.png")) ?>" alt="DJ performing during a school session" />
        <p class="stn-quote">“Good music changes lives.”</p>
      </div>
      <div>
        <p class="stn-kicker">The school</p>
        <h2 class="display mt-3 text-4xl md:text-6xl" style="text-transform:uppercase">Built for the way you actually learn.</h2>
        <p class="stn-body">
          DJ School is a separate education path — not a slideshow and not folded into membership.
          You learn on real equipment, in a real room, with an instructor who can hear what you are doing.
        </p>
        <div class="stn-stats">
          <span><?= icon("bars") ?> Beatmatching</span>
          <span><?= icon("trending") ?> Mixing</span>
          <span><?= icon("users") ?> Performance</span>
          <span><?= icon("gear") ?> House gear</span>
        </div>
        <p class="stn-kicker" style="margin-top:1.6rem">What students work on</p>
        <ul class="stn-list">
          <li>Beatmatching and tempo control</li>
          <li>Mixing, phrasing, and EQ</li>
          <li>Serato, Rekordbox, controllers, and CDJs</li>
          <li>Set building and crowd reading</li>
          <li>Preparing for a specific event</li>
        </ul>
        <a href="#register" class="btn btn-mark mt-8">Start your inquiry →</a>
      </div>
    </div>
  </section>

  <section class="stn-section" style="padding-top:0">
    <div class="wrap stn-split is-flip">
      <div class="stn-split-media">
        <img src="<?= e($img("host.png")) ?>" alt="Instructor preparing a DJ School session" />
        <p class="stn-side-mark">Skills. Beats. Opportunity.</p>
      </div>
      <div>
        <p class="stn-kicker">Every level</p>
        <h2 class="display mt-3 text-4xl md:text-6xl" style="text-transform:uppercase">From first mix to a real set.</h2>
        <p class="stn-body">
          Brand new, developing, or already playing out — the desk matches you to Fundamentals,
          Performance Lab, or a private lesson. <?= e($SITE["hours"]) ?>. <?= e(location_for("dj-school")) ?>.
        </p>
        <div class="stn-features">
          <div class="stn-feature"><?= icon("target") ?><p>Personalized instruction</p></div>
          <div class="stn-feature"><?= icon("users") ?><p>Any experience level</p></div>
          <div class="stn-feature"><?= icon("gear") ?><p>House gear ready</p></div>
          <div class="stn-feature"><?= icon("calendar") ?><p>Flexible schedule</p></div>
        </div>
        <a href="#register" class="btn stn-cta-purple mt-8">Request enrollment →</a>
      </div>
    </div>
  </section>
</div>

<section id="register" class="st-register-section">
  <div class="wrap">
    <div class="st-register-heading reveal">
      <div>
        <p class="kicker">MWCN DJ School</p>
        <h2 class="display mt-3 text-4xl md:text-6xl">Start your DJ journey.</h2>
      </div>
      <p>Tell us your experience, goals, and the program you want. This is an enrollment inquiry. A place is confirmed only after availability, agreements, and payment are complete.</p>
    </div>

    <div class="st-wizard reveal" data-dj-wizard style="transition-delay:80ms">
      <aside class="st-wizard-aside">
        <div class="st-wizard-brand">
          <span class="st-live-dot"></span>
          <span>DJ School</span>
          <small>Enrollment inquiry</small>
        </div>
        <ol class="st-steps" aria-label="Registration progress">
          <li class="is-active" data-step-indicator="1"><span>01</span><div><strong>Your details</strong><small>Who is enrolling</small></div></li>
          <li data-step-indicator="2"><span>02</span><div><strong>Experience</strong><small>Program and equipment</small></div></li>
          <li data-step-indicator="3"><span>03</span><div><strong>Schedule</strong><small>Skills, days, and goals</small></div></li>
          <li data-step-indicator="4"><span>04</span><div><strong>Review</strong><small>Confirm and submit</small></div></li>
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

        <form data-form="dj-school" data-dj-form novalidate>
          <section class="st-form-step is-active" data-step="1" aria-labelledby="dj-step-1-title">
            <div class="st-step-head">
              <span>01</span>
              <div>
                <p class="kicker">Start here</p>
                <h3 id="dj-step-1-title">Who should we contact?</h3>
                <p>We use these details only to discuss your DJ School inquiry. If the student is under 18, a parent or legal guardian is required.</p>
              </div>
            </div>
            <div class="st-field-grid">
              <label class="st-field st-span-2"><span>Full name <b>*</b></span><input name="name" autocomplete="name" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <label class="st-field"><span>Email address <b>*</b></span><input name="email" type="email" autocomplete="email" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <label class="st-field"><span>Phone number <b>*</b></span><input name="phone" type="tel" autocomplete="tel" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <label class="st-field"><span>City <b>*</b></span><input name="city" autocomplete="address-level2" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <label class="st-field"><span>State <b>*</b></span><input name="state" autocomplete="address-level1" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <label class="st-field st-span-2"><span>ZIP code <b>*</b></span><input name="zip" autocomplete="postal-code" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
            </div>
            <?php $choice("What is your age?", "age", ["Under 18", "18-24", "25-34", "35-44", "45-54", "55+"]); ?>
            <div class="st-conditional-block" data-show-when="age:Under 18" hidden>
              <div class="st-field-grid">
                <label class="st-field st-span-2"><span>Parent/Legal guardian full name <b>*</b></span><input name="parentName" autocomplete="name" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
                <label class="st-field"><span>Parent/Legal guardian email address <b>*</b></span><input name="parentEmail" type="email" autocomplete="email" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
                <label class="st-field"><span>Parent/Legal guardian phone number <b>*</b></span><input name="parentPhone" type="tel" autocomplete="tel" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              </div>
            </div>
          </section>

          <section class="st-form-step" data-step="2" aria-labelledby="dj-step-2-title" hidden>
            <div class="st-step-head">
              <span>02</span>
              <div>
                <p class="kicker">Your level</p>
                <h3 id="dj-step-2-title">Where are you starting?</h3>
                <p>Choose the closest program and experience level. Not sure is a valid answer — the desk will help you place.</p>
              </div>
            </div>
            <?php $choice("Which DJ School option are you interested in?", "program", ["DJ Fundamentals - Beginner", "DJ Performance Lab - Intermediate", "Private DJ Lessons - 1-on-1", "Not Sure"]); ?>
            <?php $choice("What is your current DJ experience level?", "experience", ["Brand New - I've never used DJ equipment", "Beginner - I've practiced a little", "Developing - I understand the basics", "Intermediate - I can already mix/play sets", "Experienced - I'm looking to sharpen specific skills"]); ?>
            <?php $choice("Do you currently own or have access to DJ equipment?", "hasEquipment", ["Yes", "No"]); ?>
            <div class="st-conditional-block" data-show-when="hasEquipment:Yes" hidden>
              <label class="st-field"><span>What DJ equipment do you currently use? <b>*</b></span><input name="equipment" required placeholder="Enter your answer" /><small class="st-field-error"></small></label>
              <?php $choice("What DJ software do you currently use?", "software", ["Serato", "Rekordbox", "VirtualDJ", "Engine DJ", "None yet", "Other"]); ?>
            </div>
          </section>

          <section class="st-form-step" data-step="3" aria-labelledby="dj-step-3-title" hidden>
            <div class="st-step-head">
              <span>03</span>
              <div>
                <p class="kicker">Schedule</p>
                <h3 id="dj-step-3-title">What do you want to work on?</h3>
                <p>Select every skill, time, and day that fits. We use this to match you with an instructor.</p>
              </div>
            </div>
            <?php $choice("What DJ skills are you most interested in developing?", "skills", ["Beatmatching & tempo control", "Mixing & transitions", "Phrasing", "EQ mixing", "Scratching & turntablism", "Serato", "Rekordbox", "Controllers", "CDJs", "Turntables", "Music library organization", "Set building & song selection", "Crowd reading", "Performance skills", "Preparing for a specific event", "Other"], "checkbox"); ?>
            <?php $choice("What time of day works best?", "bestTimes", ["Morning", "Afternoon", "Evening", "Weekends", "Flexible / Open Availability"], "checkbox"); ?>
            <?php $choice("Which days work best for you?", "bestDays", ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturdays", "Sunday", "Other"], "checkbox"); ?>
            <?php $choice("Are you preparing for a specific event or performance?", "preparingEvent", ["Yes", "No"]); ?>
            <div class="st-conditional-block" data-show-when="preparingEvent:Yes" hidden>
              <label class="st-field"><span>What are you preparing for and when is the event? <b>*</b></span><textarea name="eventDetails" required rows="4" placeholder="Enter your answer"></textarea><small class="st-field-error"></small></label>
            </div>
          </section>

          <section class="st-form-step" data-step="4" aria-labelledby="dj-step-4-title" hidden>
            <div class="st-step-head">
              <span>04</span>
              <div>
                <p class="kicker">Final review</p>
                <h3 id="dj-step-4-title">Almost ready.</h3>
                <p>Review the summary, tell us what you hope to accomplish, then send your inquiry.</p>
              </div>
            </div>
            <div class="st-review" data-review-summary aria-live="polite"></div>
            <div class="st-field-grid">
              <label class="st-field st-span-2"><span>What are you hoping to learn or accomplish? <b>*</b></span><textarea name="goals" required minlength="10" rows="4" placeholder="Enter your answer"></textarea><small class="st-field-error"></small></label>
            </div>
            <?php $choice("How did you hear about us?", "referral", ["Instagram", "Facebook", "Google Search", "Family/Friends", "Event", "DJ/Instructor Referral", "Our Website", "Other"]); ?>
            <label class="st-field" style="margin-top:22px"><span>Additional questions or information <em>Optional</em></span><textarea name="additional" rows="4" placeholder="Enter your answer"></textarea></label>
            <label class="st-consent">
              <input name="acknowledgment" type="checkbox" value="1" required />
              <span>
                <strong style="display:block;margin-bottom:6px;color:#d5dced;letter-spacing:.04em">DJ SCHOOL INQUIRY ACKNOWLEDGMENT</strong>
                I understand that submitting this form is an inquiry only and does not guarantee enrollment, class placement, instructor availability, or a specific schedule. Enrollment or lesson reservations are confirmed only after availability, pricing, required agreements, and payment have been completed. If the student is under 18, a parent or legal guardian must complete or approve any required enrollment documents.
                <br /><br />I have read and understand the acknowledgment above. <b>*</b>
              </span>
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

        <div class="st-success" data-dj-success hidden tabindex="-1">
          <span class="st-success-icon">✓</span>
          <p class="kicker">Inquiry received</p>
          <h3>Thank you for choosing DJ School.</h3>
          <p>Our team will review your request and contact you about availability and next steps. Please check your inbox for the registration email.</p>
          <a class="btn btn-mark" href="<?= e(url("/dj-school")) ?>">Back to DJ School</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_end();
