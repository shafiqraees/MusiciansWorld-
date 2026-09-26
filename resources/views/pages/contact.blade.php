<?php
global $MEDIA, $SITE;
$GLOBALS["page_scripts"] = ["assets/js/forms.js"];
page_start("Contact · Musicians World", "Talk to the desk at Musicians World in " . $SITE["location"] . ".");
page_hero(
    "Contact",
    "Talk to<br />the desk.",
    $SITE["location"] . ". " . $SITE["hours"] . ". Studio A books online — everything else can start here.",
    $MEDIA["contact"]
);
?>
<section class="section-block bg-aurora">
  <div class="wrap grid gap-14 lg:grid-cols-2">
    <div>
      <p class="kicker">Reach us</p>
      <h2 class="display mt-3 text-4xl">The house is open late.</h2>
      <ul class="mt-8 space-y-4 text-lg text-dim">
        <li><span class="block text-xs tracking-[0.16em] uppercase">Email</span><?= e($SITE["email"]) ?></li>
        <li><span class="block text-xs tracking-[0.16em] uppercase">Phone</span><?= e($SITE["phone"]) ?></li>
        <li><span class="block text-xs tracking-[0.16em] uppercase">Location</span><?= e($SITE["location"]) ?></li>
        <li><span class="block text-xs tracking-[0.16em] uppercase">Hours</span><?= e($SITE["hours"]) ?></li>
      </ul>
      <p class="mt-8 text-sm text-dim">Social links will be published here when the desk confirms them.</p>
    </div>
    <form data-form="contact" class="surface space-y-3 p-8">
      <label class="block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Name</span>
        <input name="name" required class="w-full border border-line bg-bg px-3 py-3" />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email</span>
        <input name="email" type="email" required class="w-full border border-line bg-bg px-3 py-3" />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Phone</span>
        <input name="phone" class="w-full border border-line bg-bg px-3 py-3" />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Topic</span>
        <select name="topic" class="w-full border border-line bg-bg px-3 py-3">
          <option>Studio A booking</option>
          <option>SoundTunnel rental</option>
          <option>DJ School</option>
          <option>Membership</option>
          <option>General</option>
        </select>
      </label>
      <label class="block">
        <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">What’s the project?</span>
        <textarea name="message" required rows="5" class="w-full border border-line bg-bg px-3 py-3"></textarea>
      </label>
      <button class="btn btn-mark" type="submit">Send</button>
      <p class="form-msg text-sm text-dim" hidden></p>
    </form>
  </div>
</section>
<?php page_end();
