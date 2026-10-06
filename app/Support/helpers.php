<?php

use Illuminate\Support\Facades\View;

const OPEN_MIN = 9 * 60;
const CLOSE_MIN = 26 * 60;
const SLOT_STEP = 30;
const MIN_DURATION = 2;

function site_base(): string
{
    return '';
}

function current_path(): string
{
    return '/'.ltrim(request()->path() === '/' ? '' : request()->path(), '/');
}

function path_active(string $href): bool
{
    $path = rtrim(current_path(), '/') ?: '/';
    $target = rtrim($href, '/') ?: '/';
    return $target === '/' ? $path === '/' : ($path === $target || str_starts_with($path, $target.'/'));
}

function page_start(string $title, string $desc = ''): void
{
    $GLOBALS['page_title'] = $title;
    $GLOBALS['page_desc'] = $desc;
    $adminShell = request()->is('admin', 'admin/*')
        && auth()->check()
        && auth()->user()->isStaffAccount();
    $GLOBALS['admin_shell'] = $adminShell;
    echo View::make($adminShell ? 'layouts.admin-header' : 'layouts.header', $GLOBALS)->render();
}

function page_end(): void
{
    echo View::make(!empty($GLOBALS['admin_shell']) ? 'layouts.admin-footer' : 'layouts.footer', $GLOBALS)->render();
    $GLOBALS['admin_shell'] = false;
}

/**
 * Map a domain status string to an admin badge variant (success / warn / danger / info / neutral).
 */
function a_status(?string $status): string
{
    return match ((string) $status) {
        'active', 'paid', 'confirmed', 'published', 'approved', 'completed', 'succeeded', 'valid', 'checked_in', 'connected', 'healthy', 'synced', 'ready', 'transferred', 'yes', 'on', 'true' => 'success',
        'pending', 'pending_payment', 'trialing', 'grace', 'past_due', 'submitted', 'changes_requested', 'admin_hold', 'pending_closeout', 'hold', 'new', 'attention needed', 'not configured', 'received', 'maintenance' => 'warn',
        'failed', 'cancelled', 'expired', 'refunded', 'rejected', 'suspended', 'inactive', 'void', 'not_eligible', 'no', 'off', 'false' => 'danger',
        'draft', 'closeout', 'one_time', 'archived' => 'neutral',
        default => 'info',
    };
}

function a_label(?string $value): string
{
    return ucfirst(str_replace('_', ' ', (string) $value));
}

function a_money(int|float|null $cents): string
{
    $cents = (int) $cents;

    return ($cents < 0 ? '−$' : '$').number_format(abs($cents) / 100, 2);
}

/** Status pill for the customer portal, reusing the admin status vocabulary. */
function acct_badge(?string $status, ?string $label = null): string
{
    $tone = match (a_status($status)) {
        'success' => 'is-ok',
        'warn' => 'is-warn',
        'danger' => 'is-bad',
        default => '',
    };

    return '<span class="acct-status '.$tone.'">'.e($label ?? a_label($status)).'</span>';
}

function cta(string $href, string $label, bool $ghost = false): string
{
    $class = $ghost ? 'btn btn-ghost max-sm:w-full' : 'btn btn-mark max-sm:w-full';
    $absolute = str_starts_with($href, '#') || str_starts_with($href, 'http') ? $href : url($href);
    return '<a href="'.e($absolute).'" class="'.$class.'">'.e($label).'</a>';
}

function phoenix_date_iso(?int $timestamp = null): string
{
    $date = new DateTime('now', new DateTimeZone(config('services.mwcn.timezone', 'America/Phoenix')));
    if ($timestamp !== null) {
        $date->setTimestamp($timestamp);
    }
    return $date->format('Y-m-d');
}

function format_clock(int $minutes): string
{
    $wrapped = $minutes % (24 * 60);
    $hour = intdiv($wrapped, 60);
    $minute = $wrapped % 60;
    return ($hour % 12 ?: 12).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).' '.($hour >= 12 ? 'PM' : 'AM');
}

function ranges_overlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
{
    return $aStart < $bEnd && $bStart < $aEnd;
}

function slot_fits(int $start, int $durationHours): bool
{
    return $start >= OPEN_MIN && $start + $durationHours * 60 <= CLOSE_MIN;
}

function is_available(array $busy, int $start, int $durationHours): bool
{
    if (!slot_fits($start, $durationHours)) {
        return false;
    }
    foreach ($busy as $block) {
        if (ranges_overlap($start, $start + $durationHours * 60, (int) $block['start'], (int) $block['end'])) {
            return false;
        }
    }
    return true;
}

function iter_starts(int $durationHours): array
{
    $starts = [];
    for ($time = OPEN_MIN; $time + $durationHours * 60 <= CLOSE_MIN; $time += SLOT_STEP) {
        $starts[] = $time;
    }
    return $starts;
}

function day_status(array $busy, string $date, string $today): string
{
    if ($date < $today) {
        return 'unavailable';
    }
    $free = array_filter(iter_starts(MIN_DURATION), fn ($start) => is_available($busy, $start, MIN_DURATION));
    return count($free) === 0 ? 'full' : (count($busy) === 0 ? 'available' : 'partial');
}

function nearby_times(array $busy, int $preferred, int $durationHours, int $limit = 3): array
{
    $times = [];
    foreach (iter_starts($durationHours) as $time) {
        if ($time !== $preferred && is_available($busy, $time, $durationHours)) {
            $times[] = ['time' => $time, 'distance' => abs($time - $preferred)];
        }
    }
    usort($times, fn ($a, $b) => $a['distance'] <=> $b['distance']);
    return array_column(array_slice($times, 0, $limit), 'time');
}

function founding_status(): array
{
    $limit = 50;
    try {
        $planId = \App\Models\MembershipPlan::where('slug', 'founder')->value('id');
        $count = $planId
            ? \App\Models\CustomerMembership::where('membership_plan_id', $planId)->whereIn('status', ['active', 'trialing', 'grace'])->count()
            : 0;
    } catch (Throwable) {
        $count = 0;
    }
    return ['limit' => $limit, 'count' => $count, 'remaining' => max(0, $limit - $count)];
}

function page_hero(string $kicker, string $titleHtml, string $body, $image, ?array $primary = null, ?array $secondary = null, string $ambient = ""): void
{
    ?>
    <section class="page-hero relative overflow-hidden">
      <?= media_img($image, "hero-media", "100vw", true) ?>
      <div class="page-hero-shade"></div>
      <?php if ($ambient === "orbs"): ?>
      <div class="bg-fx bg-fx-orbs is-live" aria-hidden="true">
        <span class="o1"></span>
        <span class="o2"></span>
      </div>
      <?php endif; ?>
      <div class="wrap relative z-[2] flex min-h-[52vh] flex-col justify-end pb-10 pt-24 sm:min-h-[56vh] sm:pb-12 sm:pt-28 md:min-h-[64vh] md:pb-16 md:pt-32 lg:pt-36">
        <p class="kicker fade-up"><?= e($kicker) ?></p>
        <h1 class="display fade-up fade-up-d1 mt-3 max-w-4xl" style="font-size:clamp(2.75rem,8vw,6.5rem);line-height:0.9;max-width:min(16ch,100%)"><?= $titleHtml ?></h1>
        <p class="fade-up fade-up-d2 mt-4 max-w-2xl text-sm text-white/90 sm:mt-5 sm:text-base sm:text-lg"><?= e($body) ?></p>
        <?php if ($primary || $secondary): ?>
        <div class="fade-up fade-up-d3 mt-6 flex w-full flex-col gap-3 sm:mt-8 sm:w-auto sm:flex-row sm:flex-wrap">
          <?php if ($primary) echo cta($primary["href"], $primary["label"]); ?>
          <?php if ($secondary) echo cta($secondary["href"], $secondary["label"], true); ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php
}

function home_hero(string $kicker, string $titleHtml, string $body, string $image, array $primary, array $secondary): void
{
    ?>
    <section class="relative min-h-[85vh] overflow-hidden md:min-h-screen">
      <img src="<?= e(url($image)) ?>" alt="" class="hero-media absolute inset-0 h-full w-full object-cover" fetchpriority="high" />
      <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_0%,rgba(4,4,10,0.35)_45%,#04040a_100%)]"></div>
      <div class="absolute inset-0 bg-gradient-to-t from-bg via-bg/28 to-transparent"></div>
      <div class="wrap relative flex min-h-[85vh] flex-col justify-end pb-10 pt-24 sm:pb-16 sm:pt-32 md:min-h-screen md:pb-24 lg:pt-40">
        <p class="kicker fade-up"><?= e($kicker) ?></p>
        <h1 class="display fade-up fade-up-d1 mt-4 max-w-5xl text-[2.55rem] leading-[0.9] sm:mt-5 sm:text-6xl md:text-8xl lg:text-[7.5rem]"><?= $titleHtml ?></h1>
        <p class="fade-up fade-up-d2 mt-5 max-w-2xl text-sm leading-6 text-dim sm:mt-7 sm:text-xl sm:leading-7"><?= e($body) ?></p>
        <div class="fade-up fade-up-d3 mt-7 flex w-full flex-col gap-3 sm:mt-10 sm:w-auto sm:flex-row sm:flex-wrap">
          <?= cta($primary["href"], $primary["label"]) ?>
          <?= cta($secondary["href"], $secondary["label"], true) ?>
        </div>
      </div>
    </section>
    <?php
}

function space_card($image, string $kicker, string $title, string $body, array $primary, ?array $secondary = null): void
{
    $soft = is_array($image) && !empty($image["softOverlay"]);
    ?>
    <article class="group space-card bg-bg p-5 sm:p-6 md:p-8">
      <div class="img-frame mb-6 aspect-[4/3]" style="aspect-ratio:4/3;position:relative;overflow:hidden;">
        <?= media_img($image, "mw-cover", "(min-width: 768px) 33vw, 100vw", false, $title) ?>
        <div class="<?= $soft ? "photo-shade photo-shade-soft" : "photo-shade" ?>"></div>
      </div>
      <p class="kicker"><?= e($kicker) ?></p>
      <h2 class="display mt-3 text-4xl md:text-5xl"><?= e($title) ?></h2>
      <p class="mt-4 text-sm leading-7 text-dim md:text-base"><?= e($body) ?></p>
      <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <?= cta($primary["href"], $primary["label"]) ?>
        <?php if ($secondary) echo cta($secondary["href"], $secondary["label"], true); ?>
      </div>
    </article>
    <?php
}

function gallery(array $images, string $alt): void
{
    if (count($images) >= 5) {
        echo '<div class="sa-gallery">';
        foreach ($images as $i => $image) {
            $soft = is_array($image) && !empty($image["softOverlay"]);
            $shade = $soft ? "photo-shade photo-shade-soft" : "photo-shade";
            $n = $i + 1;
            echo '<div class="img-frame sa-gallery-cell sa-gallery-cell-' . $n . '" style="position:relative;overflow:hidden;">'
                . media_img($image, "mw-cover", $i === 0 ? "60vw" : "30vw", $i === 0, $i === 0 ? $alt : "")
                . '<div class="' . $shade . '"></div></div>';
        }
        echo "</div>";
        return;
    }

    echo '<div class="grid gap-2 md:grid-cols-4 md:grid-rows-2">';
    foreach ($images as $i => $image) {
        $soft = is_array($image) && !empty($image["softOverlay"]);
        $portrait = is_array($image) && !empty($image["portrait"]);
        if ($i === 0) {
            $frame = 'img-frame aspect-[16/9] min-h-[280px] md:col-span-2 md:row-span-2 md:min-h-[460px]" style="aspect-ratio:16/9;position:relative;overflow:hidden;min-height:280px';
        } else {
            $ratio = $portrait ? "2/3" : "4/3";
            $hide = $portrait
                ? "img-frame hidden aspect-[2/3] min-h-[210px] md:block"
                : "img-frame hidden aspect-[4/3] min-h-[210px] md:block";
            $frame = $hide . '" style="aspect-ratio:' . $ratio . ';position:relative;overflow:hidden;min-height:210px';
        }
        $shade = $soft ? "photo-shade photo-shade-soft" : "photo-shade";
        echo '<div class="' . $frame . '">' . media_img($image, "mw-cover", $i === 0 ? "60vw" : "25vw", $i === 0, $i === 0 ? $alt : "") . '<div class="' . $shade . '"></div></div>';
    }
    echo "</div>";
}

function story_band(array $s): void
{
    $line = !empty($s["line"]) ? '<p class="story-line mt-4">' . e($s["line"]) . "</p>" : "";
    $ctaHtml = cta($s["href"], $s["cta"]);
    $soft = is_array($s["image"] ?? null) && !empty($s["image"]["softOverlay"]);
    $shade = $soft ? "photo-shade photo-shade-soft" : "photo-shade";
    if (($s["tone"] ?? "") === "photo") {
        ?>
        <section class="story-photo">
          <?= media_img($s["image"], "story-photo-img", "100vw") ?>
          <div class="<?= $shade ?>"></div>
          <div class="wrap story-photo-copy">
            <div class="reveal">
              <p class="kicker"><?= e($s["kicker"]) ?></p>
              <h2 class="display mt-3 max-w-3xl text-4xl sm:text-5xl md:text-6xl"><?= e($s["title"]) ?></h2>
              <?= $line ?>
              <p class="mt-5 max-w-2xl text-sm leading-7 text-white/90 sm:text-base sm:leading-8"><?= e($s["body"]) ?></p>
              <div class="mt-8"><?= $ctaHtml ?></div>
            </div>
          </div>
        </section>
        <?php
        return;
    }
    $toneRaw = (string) ($s["tone"] ?? "dark");
    $tone = $toneRaw === "light" || $toneRaw === "paper" ? "surface" : $toneRaw;
    $flip = !empty($s["flip"]) ? " is-flip" : "";
    $portrait = !empty($s["portrait"]) ? " is-portrait" : "";
    ?>
    <section class="story-band tone-<?= e($tone) ?>">
      <div class="wrap story-grid<?= $flip ?><?= $portrait ?>">
        <div class="reveal story-media<?= $portrait ?>" style="position:relative;overflow:hidden;border-radius:4px;">
          <?= media_img($s["image"], "mw-cover", !empty($s["portrait"]) ? "(min-width: 768px) 420px, 90vw" : "(min-width: 768px) 60vw, 100vw", false, media_alt($s["image"], (string) ($s["alt"] ?? ""))) ?>
          <div class="<?= $shade ?>"></div>
        </div>
        <div class="reveal story-copy">
          <p class="kicker"><?= e($s["kicker"]) ?></p>
          <h2 class="display mt-3 text-4xl sm:text-5xl md:text-6xl"><?= e($s["title"]) ?></h2>
          <?= $line ?>
          <p class="story-body mt-5 max-w-xl text-sm leading-7 sm:text-base sm:leading-8"><?= e($s["body"]) ?></p>
          <div class="mt-8"><?= $ctaHtml ?></div>
        </div>
      </div>
    </section>
    <?php
}
