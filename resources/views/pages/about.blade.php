<?php
global $MEDIA, $SITE;
page_start(
    "About · Musicians World",
    "Musicians World is a creative environment for musicians, DJs, artists, performers, and the surrounding community."
);
page_hero(
    "About · Est. " . $SITE["established"],
    "More than<br />a lockout.",
    "Musicians World is a creative environment in " . $SITE["location"] . " designed for musicians, DJs, artists, performers, creators, and the community around them.",
    $MEDIA["about"],
    ["href" => "/membership", "label" => "Join the community"],
    ["href" => "/contact", "label" => "Contact"]
);
$beats = [
    ["Vision", "A serious creative space where people can create, perform, learn, and connect — not a generic room rental site with a logo on it."],
    ["Community", "The MusiciansWorld Creative Network is the through-line: musicians, DJs, artists, performers, photographers, videographers, and makers sharing the same house."],
    ["Creativity", "Studio A is for the work. SoundTunnel is for the night. DJ School is for the craft. Each has its own job."],
    ["Collaboration", "Membership, events, and the rooms are designed so people bump into each other — on purpose, not by accident."],
    ["Learning", "DJ School stays a separate education stream. It is not folded into membership, and it is not an LMS in this phase."],
    ["Growth", "Stay in the network after the session ends. Free Community keeps you informed. Founding Member is a limited circle — size pending confirmation."],
];
?>
<section class="section-block bg-mesh">
  <div class="wrap">
    <div class="reveal max-w-3xl">
      <p class="text-lg leading-8 text-dim md:text-xl">
        The work is simple: give people a serious place to create, a way to connect, a school for those who want to
        learn, a room to perform, and a network that still cares after the session ends.
      </p>
      <p class="display mt-10 text-3xl text-text">CREATE. CONNECT. LEARN. PERFORM. GROW.</p>
      <p class="mt-8 leading-8 text-dim">
        We don’t advertise programs that aren’t live. What’s on this site is what you can walk into: Studio A,
        SoundTunnel by request, DJ School enrollment, and two memberships.
      </p>
    </div>
  </div>
</section>
<section class="section-block bg-aurora">
  <div class="wrap grid gap-10 md:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($beats as $i => [$title, $body]): ?>
      <div class="reveal border-t border-line pt-5" style="transition-delay:<?= $i * 60 ?>ms">
        <p class="kicker">0<?= $i + 1 ?></p>
        <h2 class="display mt-3 text-3xl"><?= e($title) ?></h2>
        <p class="mt-4 text-sm leading-7 text-dim"><?= e($body) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<section class="section-block bg-mesh">
  <div class="wrap">
    <div class="reveal">
      <h2 class="display text-4xl md:text-5xl">Come through.</h2>
      <p class="mt-4 max-w-xl text-dim"><?= e($SITE["location"]) ?>. <?= e($SITE["hours"]) ?>.</p>
      <div class="mt-8"><?= cta("/book", "Book a space") ?></div>
    </div>
  </div>
</section>
<?php page_end();
