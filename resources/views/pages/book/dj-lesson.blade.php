<?php
$GLOBALS["page_scripts"] = ["assets/js/forms.js"];
page_start("Private DJ Lesson Inquiry · Musicians World", "Tell us about your goals so we can match you with the right instructor and lesson option.");
?>
<div class="wrap max-w-3xl py-20 sm:py-24">
  <p class="kicker">Private DJ Lessons</p>
  <h1 class="display mt-4 text-4xl sm:text-5xl md:text-6xl">Private DJ Lesson Inquiry</h1>
  <p class="mt-4 max-w-xl text-dim">Tell us about your goals so we can match you with the right instructor and lesson option.</p>

  <form data-form="dj-lesson" class="surface mt-12 p-6 sm:p-9">

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">Student information</h2>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Student full name</span>
          <input name="studentName" required class="w-full border border-line bg-bg px-3 py-3" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Age</span>
          <input name="age" class="w-full border border-line bg-bg px-3 py-3" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email address</span>
          <input name="email" type="email" required class="w-full border border-line bg-bg px-3 py-3" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Phone number</span>
          <input name="phone" type="tel" class="w-full border border-line bg-bg px-3 py-3" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">If under 18: parent/guardian name</span>
          <input name="parentName" class="w-full border border-line bg-bg px-3 py-3" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Parent/guardian phone or email</span>
          <input name="parentContact" class="w-full border border-line bg-bg px-3 py-3" />
        </label>
      </div>
    </div>

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">DJ experience</h2>
      <p class="mt-4 text-xs tracking-[0.1em] text-dim uppercase">Current experience level</p>
      <div class="choice-grid cols-4">
        <?php foreach (["beginner" => "Beginner", "some" => "Some experience", "intermediate" => "Intermediate", "advanced" => "Advanced"] as $val => $label): ?>
          <label class="choice-item"><input type="radio" name="experienceLevel" value="<?= e($val) ?>" required /><?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="mt-6 text-xs tracking-[0.1em] text-dim uppercase">DJ software used (check all that apply)</p>
      <div class="choice-grid cols-4">
        <?php foreach (["Serato", "Rekordbox", "VirtualDJ", "Engine DJ", "None yet"] as $sw): ?>
          <label class="choice-item"><input type="checkbox" name="software[]" value="<?= e($sw) ?>" /><?= e($sw) ?></label>
        <?php endforeach; ?>
      </div>
      <label class="mt-6 block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Equipment you currently own or use</span>
        <textarea name="equipment" rows="2" class="w-full border border-line bg-bg px-3 py-3"></textarea>
      </label>
    </div>

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">Goals & interests</h2>
      <p class="mt-4 text-xs tracking-[0.1em] text-dim uppercase">What would you like to learn or improve? (check all that apply)</p>
      <div class="choice-grid">
        <?php foreach ([
            "Beatmatching", "Mixing/transitions", "Scratching", "Music organization",
            "CDJs/controllers", "Set preparation", "Crowd reading", "Performance skills",
        ] as $g): ?>
          <label class="choice-item"><input type="checkbox" name="learnGoals[]" value="<?= e($g) ?>" /><?= e($g) ?></label>
        <?php endforeach; ?>
      </div>
      <label class="mt-6 block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Primary music styles / genres</span>
        <input name="genres" class="w-full border border-line bg-bg px-3 py-3" />
      </label>

      <p class="mt-6 text-xs tracking-[0.1em] text-dim uppercase">Your main DJ goal (check all that apply)</p>
      <div class="choice-grid">
        <?php foreach ([
            "Learn as a hobby", "Learn the basics", "Become a professional DJ",
            "Perform at parties/events", "Club/nightlife DJing", "Improve existing skills",
        ] as $g): ?>
          <label class="choice-item"><input type="checkbox" name="mainGoals[]" value="<?= e($g) ?>" /><?= e($g) ?></label>
        <?php endforeach; ?>
      </div>
      <label class="mt-6 block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Other goal or details</span>
        <textarea name="otherGoal" rows="2" class="w-full border border-line bg-bg px-3 py-3"></textarea>
      </label>
    </div>

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">Lesson interest</h2>
      <div class="price-ref">
        <div class="price-ref-card">
          <h4>1-hour private lessons</h4>
          <ul>
            <li>Single lesson — $85</li>
            <li>4-lesson package — $300</li>
            <li>8-lesson package — $560</li>
          </ul>
        </div>
        <div class="price-ref-card">
          <h4>2-hour Pro DJ sessions</h4>
          <ul>
            <li>Single session — $175</li>
            <li>2-session package — $340</li>
            <li>4-session — $640 · 8-session — $1,200</li>
          </ul>
        </div>
      </div>
      <p class="mt-6 text-xs tracking-[0.1em] text-dim uppercase">Which option interests you?</p>
      <div class="choice-grid cols-4">
        <?php foreach (["1-hour" => "1-hour lessons", "2-hour-pro" => "2-hour Pro sessions", "not-sure" => "Not sure — help me choose"] as $val => $label): ?>
          <label class="choice-item"><input type="radio" name="lessonInterest" value="<?= e($val) ?>" required /><?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">Scheduling</h2>
      <p class="mt-4 text-xs tracking-[0.1em] text-dim uppercase">Preferred days (check all that apply)</p>
      <div class="choice-grid cols-7">
        <?php foreach (["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"] as $d): ?>
          <label class="choice-item"><input type="checkbox" name="preferredDays[]" value="<?= e($d) ?>" /><?= e($d) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="mt-6 text-xs tracking-[0.1em] text-dim uppercase">Preferred time</p>
      <div class="choice-grid cols-4">
        <?php foreach (["Morning", "Afternoon", "Evening"] as $t): ?>
          <label class="choice-item"><input type="radio" name="preferredTime" value="<?= e($t) ?>" /><?= e($t) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section">
      <h2 class="text-sm tracking-[0.14em] text-dim uppercase">Upcoming performance or event</h2>
      <div class="choice-grid cols-4">
        <label class="choice-item"><input type="radio" name="hasEvent" value="yes" />Yes</label>
        <label class="choice-item"><input type="radio" name="hasEvent" value="no" />No</label>
      </div>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Event/performance date</span>
          <input name="eventDate" type="date" class="w-full border border-line bg-bg px-3 py-3" />
        </label>
      </div>
      <label class="mt-4 block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Tell us what you are preparing for</span>
        <textarea name="eventDetails" rows="2" class="w-full border border-line bg-bg px-3 py-3"></textarea>
      </label>
    </div>

    <label class="hp-field" aria-hidden="true">
      Leave blank
      <input name="website" type="text" tabindex="-1" autocomplete="off" />
    </label>

    <div class="form-section">
      <button class="btn btn-mark" type="submit">Send inquiry</button>
      <p class="form-msg mt-3 text-sm text-dim" hidden></p>
      <p class="mt-4 text-xs text-dim">Inquiry only. Lesson placement and instructor availability are confirmed separately.</p>
    </div>
  </form>
</div>
<?php page_end();