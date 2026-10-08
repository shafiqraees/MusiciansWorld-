<?php
global $SITE, $MEDIA;
?>
  </main>
  <footer class="mt-auto border-t border-line">
    <div class="wrap grid gap-12 py-16 md:grid-cols-4">
      <div>
        <img src="<?= e(url($MEDIA["logo"])) ?>" alt="Musicians World" class="h-16 w-auto mix-blend-screen sm:h-20" />
        <p class="mt-5 max-w-xs text-sm leading-6 text-dim">
          Arizona’s creator hub since <?= (int) $SITE["established"] ?>. Studio A, SoundTunnel, DJ School, and a membership community
          in <?= e($SITE["location"]) ?>.
        </p>
      </div>
      <div class="text-sm">
        <p class="mb-3 text-xs tracking-[0.16em] text-dim uppercase">Visit</p>
        <p><?= e($SITE["location"]) ?></p>
        <p class="mt-2"><?= e($SITE["hours"]) ?></p>
        <p class="mt-2"><?= e($SITE["phone"]) ?></p>
        <p><?= e($SITE["email"]) ?></p>
      </div>
      <div class="text-sm">
        <p class="mb-3 text-xs tracking-[0.16em] text-dim uppercase">Explore</p>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/spaces")) ?>">Spaces</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/soundtunnel")) ?>">SoundTunnel</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/dj-school")) ?>">DJ School</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/membership")) ?>">Membership</a>
      </div>
      <div class="text-sm">
        <p class="mb-3 text-xs tracking-[0.16em] text-dim uppercase">Desk</p>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/events")) ?>">Events</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/about")) ?>">About</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/contact")) ?>">Contact</a>
        <a class="mb-2 block text-dim transition hover:text-mark" href="<?= e(url("/book")) ?>">Book Studio A</a>
      </div>
    </div>
    <div class="grad-line"></div>
    <div class="wrap py-5 text-xs text-dim">
      <span>© 2026 <?= e($SITE["network"]) ?></span>
    </div>
  </footer>
  <script src="<?= e(url("assets/js/app.js")) ?>?v=20260924b" defer></script>
  <?php if (!empty($page_scripts)): foreach ($page_scripts as $src): ?>
  <script src="<?= e(url($src)) ?>?v=20261008a" defer></script>
  <?php endforeach; endif; ?>
</body>
</html>
