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

  function phoenixToday() {
    return new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Phoenix",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());
  }

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function clockLabel(value) {
    const [h, m] = String(value).split(":").map(Number);
    return pad(h) + ":" + pad(m);
  }

  function timeChoices() {
    const choices = [];
    for (let minutes = 9 * 60; minutes <= 26 * 60; minutes += 30) {
      const wrapped = minutes % (24 * 60);
      choices.push(pad(Math.floor(wrapped / 60)) + ":" + pad(wrapped % 60));
    }
    return choices;
  }

  const pop = document.createElement("div");
  pop.className = "home-cal";
  pop.hidden = true;
  document.body.appendChild(pop);
  let active = null;
  let viewYear = 2026;
  let viewMonth = 1;

  function selectedParts(field) {
    const date = field.querySelector("[name$=_date]").value;
    const [month, day, year] = date.split("/").map(Number);
    return { year, month, day, time: field.querySelector("[name$=_time]").value };
  }

  function paintLabel(field) {
    const parts = selectedParts(field);
    const button = field.querySelector(".home-picker-btn");
    button.querySelector("strong").textContent = pad(parts.month) + "/" + pad(parts.day) + "/" + parts.year;
    button.querySelector("small").textContent = clockLabel(parts.time);
  }

  function renderPicker() {
    if (!active) return;
    const parts = selectedParts(active);
    const today = phoenixToday();
    const first = new Date(Date.UTC(viewYear, viewMonth - 1, 1)).getUTCDay();
    const count = new Date(Date.UTC(viewYear, viewMonth, 0)).getUTCDate();
    const names = ["S", "M", "T", "W", "T", "F", "S"];
    const cells = names.map((name) => `<span>${name}</span>`).join("");
    let days = "";
    for (let i = 0; i < first; i++) days += "<span></span>";
    for (let day = 1; day <= count; day++) {
      const iso = viewYear + "-" + pad(viewMonth) + "-" + pad(day);
      const selected = day === parts.day && viewMonth === parts.month && viewYear === parts.year;
      days += `<button type="button" data-day="${day}" ${iso < today ? "disabled" : ""} class="${selected ? "is-on" : ""}">${day}</button>`;
    }
    const options = timeChoices()
      .map((value) => `<option value="${value}" ${value === parts.time ? "selected" : ""}>${clockLabel(value)}</option>`)
      .join("");
    const monthLabel = new Date(Date.UTC(viewYear, viewMonth - 1, 1)).toLocaleString("en-US", {
      month: "long",
      year: "numeric",
      timeZone: "UTC",
    });
    pop.innerHTML = `
      <div class="home-cal-head">
        <button type="button" data-cal="prev" aria-label="Previous month">←</button>
        <strong>${monthLabel}</strong>
        <button type="button" data-cal="next" aria-label="Next month">→</button>
      </div>
      <div class="home-cal-grid">${cells}${days}</div>
      <div class="home-cal-time">
        <select aria-label="Time">${options}</select>
        <button type="button" class="home-cal-done" data-cal="done">Done</button>
      </div>`;
  }

  function openPicker(field) {
    active = field;
    const parts = selectedParts(field);
    viewYear = parts.year;
    viewMonth = parts.month;
    pop.hidden = false;
    const rect = field.querySelector(".home-picker-btn").getBoundingClientRect();
    pop.style.top = Math.min(rect.bottom + 8, window.innerHeight - 380) + "px";
    pop.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - 332)) + "px";
    renderPicker();
  }

  function closePicker() {
    pop.hidden = true;
    active = null;
  }

  rangeFields.forEach((field) => {
    paintLabel(field);
    field.querySelector(".home-picker-btn").addEventListener("click", () => {
      if (active === field) closePicker();
      else openPicker(field);
    });
  });

  pop.addEventListener("click", (e) => {
    const button = e.target.closest("button");
    if (!active) return;
    if (button?.dataset.cal === "prev") {
      viewMonth === 1 ? ((viewYear -= 1), (viewMonth = 12)) : (viewMonth -= 1);
      renderPicker();
      return;
    }
    if (button?.dataset.cal === "next") {
      viewMonth === 12 ? ((viewYear += 1), (viewMonth = 1)) : (viewMonth += 1);
      renderPicker();
      return;
    }
    if (button?.dataset.cal === "done") {
      closePicker();
      return;
    }
    if (button?.dataset.day) {
      const dateInput = active.querySelector("[name$=_date]");
      dateInput.value = pad(viewMonth) + "/" + pad(Number(button.dataset.day)) + "/" + viewYear;
      paintLabel(active);
      renderPicker();
    }
  });
  pop.addEventListener("change", (e) => {
    if (!active || e.target.tagName !== "SELECT") return;
    active.querySelector("[name$=_time]").value = e.target.value;
    paintLabel(active);
  });
  document.addEventListener("click", (e) => {
    if (!active) return;
    if (pop.contains(e.target) || e.target.closest(".home-picker-btn")) return;
    closePicker();
  });

  function toggleDuration() {
    if (!space) return;
    const studio = space.value === "studio-a";
    rangeFields.forEach((field) => {
      field.style.display = studio ? "" : "none";
    });
    if (!studio) closePicker();
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
    let toDate = String(data.get("to_date") || "");
    const toTime = String(data.get("to_time") || "");
    const fromTime = String(data.get("from_time") || "");
    const toHour = Number(toTime.slice(0, 2));
    const fromHour = Number(fromTime.slice(0, 2));
    if (toDate === String(data.get("from_date") || "") && toHour < 9 && fromHour >= 9) {
      const [month, day, year] = toDate.split("/").map(Number);
      const next = new Date(year, month - 1, day + 1);
      toDate = pad(next.getMonth() + 1) + "/" + pad(next.getDate()) + "/" + next.getFullYear();
    }
    const from = stamp(data.get("from_date"), data.get("from_time"));
    const to = stamp(toDate, toTime);
    if (!from || !to) return;
    window.location.href = base + "/book?from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to);
  });
})();
