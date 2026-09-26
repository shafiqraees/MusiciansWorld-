(function () {
  const hero = document.getElementById("home-hero");
  if (hero) {
    const slides = [...hero.querySelectorAll(".home-slide")];
    const dots = [...hero.querySelectorAll("[data-dot]")];
    let i = 0;
    let timer;
    function show(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach((el, idx) => el.classList.toggle("is-on", idx === i));
      dots.forEach((el, idx) => el.classList.toggle("is-on", idx === i));
    }
    function start() {
      stop();
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
      timer = window.setInterval(() => show(i + 1), 6500);
    }
    function stop() {
      if (timer) window.clearInterval(timer);
    }
    hero.querySelector("[data-slide=prev]")?.addEventListener("click", () => {
      show(i - 1);
      start();
    });
    hero.querySelector("[data-slide=next]")?.addEventListener("click", () => {
      show(i + 1);
      start();
    });
    dots.forEach((dot) =>
      dot.addEventListener("click", () => {
        show(Number(dot.getAttribute("data-dot")));
        start();
      })
    );
    hero.addEventListener("mouseenter", stop);
    hero.addEventListener("mouseleave", start);
    start();
  }

  const form = document.getElementById("home-search");
  const space = document.getElementById("home-space");
  const durationWrap = document.getElementById("home-duration-wrap");
  const base = (window.MW && window.MW.base) || "";
  function toggleDuration() {
    if (!durationWrap || !space) return;
    const studio = space.value === "studio-a";
    durationWrap.style.display = studio ? "" : "none";
    form?.classList.toggle("is-short", !studio);
  }
  space?.addEventListener("change", toggleDuration);
  toggleDuration();
  form?.addEventListener("submit", (e) => {
    const kind = space?.value || "studio-a";
    if (kind === "studio-a") return;
    e.preventDefault();
    if (kind === "soundtunnel") window.location.href = base + "/soundtunnel#request";
    else if (kind === "dj-school") window.location.href = base + "/dj-school#register";
  });
})();
