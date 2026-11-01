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
  const rangeFields = [...document.querySelectorAll(".home-range-field")];
  const base = (window.MW && window.MW.base) || "";
  function toggleDuration() {
    if (!space) return;
    const studio = space.value === "studio-a";
    rangeFields.forEach((field) => {
      field.style.display = studio ? "" : "none";
      field.querySelector("input")?.toggleAttribute("required", studio);
    });
    form?.classList.toggle("is-short", !studio);
  }
  space?.addEventListener("change", toggleDuration);
  toggleDuration();
  function stamp(dateValue, timeValue) {
    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(dateValue || "").trim());
    const time = String(timeValue || "").slice(0, 5);
    if (!match || !/^\d{2}:\d{2}$/.test(time)) return "";
    return match[3] + "-" + match[1] + "-" + match[2] + "T" + time;
  }

  form?.addEventListener("submit", (e) => {
    const kind = space?.value || "studio-a";
    e.preventDefault();
    if (kind === "soundtunnel") {
      window.location.href = base + "/soundtunnel#request";
      return;
    }
    if (kind === "dj-school") {
      window.location.href = base + "/dj-school#register";
      return;
    }
    const data = new FormData(form);
    const from = stamp(data.get("from_date"), data.get("from_time"));
    const to = stamp(data.get("to_date"), data.get("to_time"));
    if (!from || !to) return;
    window.location.href = base + "/book?from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to);
  });
})();
