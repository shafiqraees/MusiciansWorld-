(function () {
  var roots = document.querySelectorAll("[data-st-video]");
  if (!roots.length) return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  var slow =
    conn && (conn.saveData || conn.effectiveType === "2g" || conn.effectiveType === "3g" || conn.effectiveType === "slow-2g");

  if (reduced || slow) return;

  roots.forEach(function (root) {
    var video = root.querySelector("video");
    var poster = root.querySelector("[data-st-poster]");
    if (!video) return;

    video.hidden = false;
    if (poster) poster.hidden = true;

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            video.play().catch(function () {});
          } else {
            video.pause();
          }
        });
      },
      { threshold: 0.25 }
    );
    io.observe(video);
  });
})();
