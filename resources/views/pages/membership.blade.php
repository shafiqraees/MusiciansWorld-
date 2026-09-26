<?php
global $MEDIA, $MEMBERSHIPS, $show_phase_2;
$GLOBALS["page_scripts"] = ["assets/js/forms.js"];
$founding = founding_status();
page_start("Membership · Musicians World", "Free Community and Founding Member are live now. Creative Network and Pro Membership are coming in Phase 2.");
page_hero(
    "Create · Connect · Collaborate · Grow",
    "More than a<br />network.",
    "A movement — music, creators, community, opportunity. Free Community is open now. Founding Member is $150 / month via secure Stripe checkout.",
    $MEDIA["membership"],
    ["href" => "#join", "label" => "Join now"]
);
$visibleTiers = array_values(array_filter($MEMBERSHIPS, fn($m) => $show_phase_2 || $m["phase"] === "phase1"));
?>
<section class="section-block bg-mesh">
  <div class="wrap">
  <div class="reveal max-w-2xl">
    <p class="kicker">Choose a seat</p>
    <h2 class="display mt-3 text-4xl md:text-5xl">Stay in the loop — or be part of the beginning.</h2>
    <p class="mt-5 leading-7 text-dim">
      Free Community Membership does not include studio discounts, included studio hours, or Founding Member
      benefits. Founding Member pricing and benefits are not final.
    </p>
  </div>
  <div class="tier-grid mt-14">
    <?php foreach ($visibleTiers as $m): ?>
      <article class="tier-card<?= !empty($m["featured"]) ? " is-featured" : "" ?>" data-tier="<?= e($m["id"]) ?>">
        <span class="pill"><span class="pill-dot"></span><?= e($m["pill"]) ?></span>
        <span class="icon-badge"><?= icon($m["icon"]) ?></span>
        <h3 class="display tier-name"><?= e($m["name"]) ?></h3>
        <p class="tier-subtitle"><?= e($m["subtitle"]) ?></p>
        <p class="tier-headline"><?= e($m["headline"]) ?></p>
        <div class="tier-price-row">
          <span class="tier-price-value"><?= e($m["priceValue"]) ?></span>
          <?php if ($m["pricePeriod"] !== ""): ?><span class="tier-price-period"><?= e($m["pricePeriod"]) ?></span><?php endif; ?>
        </div>
        <p class="tier-price-note"><?= e($m["priceNote"]) ?></p>
        <?php if ($m["id"] === "founding"): ?>
          <p class="mt-2 text-xs text-dim" data-founding-remaining><?= (int) $founding["remaining"] ?> of 50 Founding spots remaining</p>
        <?php endif; ?>

        <?php if (!empty($m["waitlist"])): ?>
          <button type="button" class="tier-cta is-ghost" data-plan="<?= e($m["id"]) ?>" data-plan-label="Joining the <?= e($m["name"]) ?> waitlist"><?= e($m["cta"]) ?></button>
        <?php else: ?>
          <button type="button" class="tier-cta" data-plan="<?= e($m["id"]) ?>" data-plan-label="Joining as <?= e($m["name"]) ?>"><?= e($m["cta"]) ?></button>
        <?php endif; ?>

        <?php if ($m["includesLabel"] !== ""): ?><p class="tier-includes"><?= e($m["includesLabel"]) ?></p><?php endif; ?>
        <ul class="check-list">
          <?php foreach ($m["benefits"] as $b): ?><li><?= icon("check") ?><span><?= e($b) ?></span></li><?php endforeach; ?>
        </ul>
        <p class="tier-disclaimer"><?= e($m["disclaimer"]) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  </div>
</section>
<section class="section-block bg-aurora">
  <div class="wrap">
  <form id="join" data-form="membership" class="surface max-w-lg space-y-3 p-8">
    <p class="kicker">Join</p>
    <p class="mt-2 text-sm font-semibold" id="join-plan-label">Joining as Free Community</p>
    <input type="hidden" name="plan" id="join-plan" value="community" />
    <label class="block">
      <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Name</span>
      <input name="name" required class="w-full border border-line bg-bg px-3 py-3" />
    </label>
    <label class="block">
      <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email</span>
      <input name="email" type="email" required class="w-full border border-line bg-bg px-3 py-3" />
    </label>
    <label class="block">
      <span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Phone (optional)</span>
      <input name="phone" type="tel" class="w-full border border-line bg-bg px-3 py-3" />
    </label>
    <label class="hp-field" aria-hidden="true">
      Leave blank
      <input name="website" type="text" tabindex="-1" autocomplete="off" />
    </label>
    <button class="btn btn-mark" type="submit">Submit</button>
    <p class="form-msg text-sm text-dim" hidden></p>
    <p class="text-xs text-dim">Founding Member checkout is secured by Stripe. Free Community does not require a card.</p>
  </form>
  </div>
</section>
<?php page_end();