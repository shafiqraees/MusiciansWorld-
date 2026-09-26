(function () {
  const base = (window.MW && window.MW.base) || "";
  function api(path) {
    return base + path;
  }

  function phoenixToday() {
    return new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Phoenix",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());
  }

  function formatClock(minutes) {
    const wrapped = minutes % (24 * 60);
    const h = Math.floor(wrapped / 60);
    const m = wrapped % 60;
    const ampm = h >= 12 ? "PM" : "AM";
    const hr = h % 12 || 12;
    return hr + ":" + String(m).padStart(2, "0") + " " + ampm;
  }

  const root = document.getElementById("booking-root");
  if (!root) return;

  const DURATIONS = [2, 3, 4, 6, 8];
  const today = phoenixToday();
  const params = new URLSearchParams(window.location.search);
  const qDate = params.get("date") || "";
  const qDur = Number(params.get("duration"));
  const startDate = /^\d{4}-\d{2}-\d{2}$/.test(qDate) && qDate >= today ? qDate : today;
  const startDur = DURATIONS.includes(qDur) ? qDur : 2;
  const [ty, tm] = startDate.split("-").map(Number);
  const state = {
    y: ty,
    m: tm,
    days: {},
    date: startDate,
    duration: startDur,
    start: null,
    slots: [],
    price: 80,
    unavailable: false,
    alts: [],
    step: "pick",
    name: "",
    email: "",
    phone: "",
    notes: "",
    error: "",
    busy: false,
  };

  function monthLabel(y, m) {
    return new Date(Date.UTC(y, m - 1, 1)).toLocaleString("en-US", {
      month: "long",
      year: "numeric",
      timeZone: "UTC",
    });
  }

  async function loadMonth() {
    const res = await fetch(api("/api/availability.php?resource=studio-a&year=" + state.y + "&month=" + state.m));
    const data = await res.json();
    state.days = data.days || {};
    render();
  }

  async function loadDay() {
    const q = new URLSearchParams({ resource: "studio-a", date: state.date, duration: String(state.duration) });
    if (state.start != null) q.set("start", String(state.start));
    const res = await fetch(api("/api/availability.php?" + q.toString()));
    const data = await res.json();
    state.slots = data.slots || [];
    state.price = data.price;
    if (data.requested) {
      state.unavailable = !data.requested.available;
      state.alts = data.requested.alternatives || [];
    } else {
      state.unavailable = false;
      state.alts = [];
    }
    render();
  }

  function syncDetailsFromDom() {
    const form = root.querySelector("#booking-details");
    if (!form) return;
    const fd = new FormData(form);
    state.name = String(fd.get("name") || "").trim();
    state.email = String(fd.get("email") || "").trim().replace(/\s+/g, "");
    state.phone = String(fd.get("phone") || "").trim();
    state.notes = String(fd.get("notes") || "").trim();
  }

  async function confirm() {
    syncDetailsFromDom();

    if (state.name.length < 2) {
      state.error = "Name is required.";
      state.step = "details";
      render();
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email)) {
      state.error = "Enter a valid email address.";
      state.step = "details";
      render();
      return;
    }

    state.busy = true;
    state.error = "";
    render();
    const res = await fetch(api("/api/bookings.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        resourceId: "studio-a",
        date: state.date,
        start: state.start,
        durationHours: state.duration,
        addonIds: [],
        name: state.name,
        email: state.email,
        phone: state.phone,
        notes: state.notes,
      }),
    });
    const data = await res.json();
    state.busy = false;
    if (!res.ok) {
      state.error = data.error || "Could not book that time.";
      state.step = "details";
      render();
      return;
    }
    if (data.url) {
      window.location.href = data.url;
      return;
    }
    window.location.href = api("/book/confirm/" + encodeURIComponent(data.id));
  }

  function dayClass(iso, st, selected) {
    if (selected) return "day-selected";
    if (st === "unavailable") return "day-unavailable";
    if (st === "full") return "day-full";
    if (st === "partial") return "day-partial";
    return "day-available";
  }

  function renderCalendar() {
    const first = new Date(Date.UTC(state.y, state.m - 1, 1)).getUTCDay();
    const count = new Date(Date.UTC(state.y, state.m, 0)).getUTCDate();
    const cells = [];
    for (let i = 0; i < first; i++) cells.push(null);
    for (let d = 1; d <= count; d++) cells.push(d);
    const short = ["S", "M", "T", "W", "T", "F", "S"];
    const long = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    return `
      <div>
        <div class="mb-4 flex items-center justify-between">
          <button type="button" class="px-2 text-sm text-dim" data-cal="prev" aria-label="Previous month">←</button>
          <p class="display text-base sm:text-xl">${monthLabel(state.y, state.m)}</p>
          <button type="button" class="px-2 text-sm text-dim" data-cal="next" aria-label="Next month">→</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-[11px] tracking-wide text-dim uppercase">
          ${short.map((d, i) => `<div class="py-2 sm:hidden">${d}</div>`).join("")}
          ${long.map((d) => `<div class="hidden py-2 sm:block">${d}</div>`).join("")}
          ${cells
            .map((d, i) => {
              if (!d) return `<div></div>`;
              const iso = `${state.y}-${String(state.m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
              const st = iso < today ? "unavailable" : state.days[iso] || "available";
              const selected = iso === state.date;
              const blocked = st === "unavailable";
              return `<button type="button" ${blocked ? "disabled" : ""} data-iso="${iso}" aria-pressed="${selected}" aria-label="${iso}, ${st}" class="aspect-square text-sm ${dayClass(iso, st, selected)}">${d}</button>`;
            })
            .join("")}
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-[11px] tracking-wide text-dim uppercase">
          <span class="flex items-center gap-2"><i class="day-available inline-block h-3 w-3"></i> Available</span>
          <span class="flex items-center gap-2"><i class="day-partial inline-block h-3 w-3"></i> Partially booked</span>
          <span class="flex items-center gap-2"><i class="day-full inline-block h-3 w-3"></i> Fully booked</span>
          <span class="flex items-center gap-2"><i class="day-selected inline-block h-3 w-3"></i> Selected</span>
        </div>
      </div>`;
  }

  function slotClass(s) {
    if (state.start === s.start) return "min-h-11 px-1 py-2 text-xs bg-mark text-mark-ink";
    if (s.available) return "min-h-11 px-1 py-2 text-xs border border-line hover:border-text";
    return "min-h-11 px-1 py-2 text-xs border border-line/50 text-dim/50 line-through";
  }

  function renderPanel() {
    if (state.step === "details") {
    return `
      <div class="book-sheet">
        <p class="kicker">Review</p>
          <h2 class="mt-2 text-lg font-semibold">${state.date} · ${state.start != null ? formatClock(state.start) : ""} · ${state.duration} hours</h2>
          <p class="mt-1 text-2xl">$${state.price}</p>
          <p class="text-xs text-dim">You’ll pay securely with Stripe. After payment you’ll return here with a success or cancelled page.</p>
          <form id="booking-details" class="mt-4 space-y-3" novalidate>
            <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Name</span>
              <input name="name" required autocomplete="name" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.name)}" /></label>
            <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email</span>
              <input name="email" type="email" required autocomplete="email" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.email)}" /></label>
            <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Phone</span>
              <input name="phone" type="tel" autocomplete="tel" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.phone)}" /></label>
            <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Notes</span>
              <textarea name="notes" class="w-full border border-line bg-bg px-3 py-2" rows="3">${escapeHtml(state.notes)}</textarea></label>
            ${state.error ? `<p class="text-sm text-danger">${escapeHtml(state.error)}</p>` : ""}
            <div class="flex gap-2 pt-1">
              <button class="btn btn-ghost flex-1" data-act="back" type="button">Back</button>
              <button class="btn btn-mark flex-1" type="submit" ${state.busy ? "disabled" : ""}>${state.busy ? "Opening checkout…" : "Confirm booking"}</button>
            </div>
          </form>
        </div>`;
    }
    return `
      <div class="book-sheet">
        <p class="kicker">Book Studio A</p>
        <p class="book-where">Where · ${(window.MW && window.MW.locationLabel) || ""}</p>
        <h2 class="mt-2 text-lg font-semibold">${state.date}</h2>
        <p class="mt-4 text-xs tracking-[0.14em] text-dim uppercase">Duration</p>
        <div class="mt-2 grid grid-cols-3 gap-1 sm:grid-cols-5">
          ${DURATIONS.map(
            (h) =>
              `<button type="button" data-duration="${h}" class="min-h-11 text-sm ${
                state.duration === h ? "bg-mark text-mark-ink" : "border border-line"
              }">${h}h</button>`
          ).join("")}
        </div>
        <p class="mt-4 text-3xl font-semibold">$${state.price}</p>
        <p class="text-sm text-dim">${state.duration} hours · price updates with duration</p>
        <p class="mt-5 text-xs tracking-[0.14em] text-dim uppercase">Start time</p>
        <div class="mt-2 grid max-h-56 grid-cols-2 gap-1 overflow-auto sm:grid-cols-3">
          ${state.slots
            .map(
              (s) =>
                `<button type="button" data-start="${s.start}" aria-pressed="${state.start === s.start}" aria-disabled="${!s.available}" class="${slotClass(s)}">${s.label}</button>`
            )
            .join("")}
        </div>
        ${
          state.unavailable
            ? `<div class="mt-4 border border-danger/40 bg-danger/10 p-3 text-sm">
                <p>This time is unavailable.</p>
                ${
                  state.alts.length
                    ? `<div class="mt-2"><p class="text-dim">Available nearby times</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                      ${state.alts.map((a) => `<button type="button" class="border border-line px-2 py-1 text-xs" data-start="${a.start}">${a.label}</button>`).join("")}
                    </div></div>`
                    : ""
                }
                <p class="mt-2 text-dim">Or choose another date.</p>
              </div>`
            : ""
        }
        <button class="btn btn-mark mt-6 w-full" type="button" data-act="continue" ${state.start == null || state.unavailable ? "disabled" : ""}>Continue</button>
      </div>`;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
  function escapeAttr(s) {
    return escapeHtml(s).replace(/"/g, "&quot;");
  }

  function render() {
    root.innerHTML = renderCalendar() + renderPanel();
  }

  root.addEventListener("click", (e) => {
    const t = e.target.closest("button");
    if (!t || !root.contains(t)) return;
    if (t.dataset.cal === "prev") {
      state.m === 1 ? ((state.y -= 1), (state.m = 12)) : (state.m -= 1);
      loadMonth();
      return;
    }
    if (t.dataset.cal === "next") {
      state.m === 12 ? ((state.y += 1), (state.m = 1)) : (state.m += 1);
      loadMonth();
      return;
    }
    if (t.dataset.iso) {
      state.date = t.dataset.iso;
      state.start = null;
      loadDay();
      return;
    }
    if (t.dataset.duration) {
      state.duration = Number(t.dataset.duration);
      loadDay();
      return;
    }
    if (t.dataset.start) {
      state.start = Number(t.dataset.start);
      loadDay();
      return;
    }
    if (t.dataset.act === "continue") {
      state.step = "details";
      render();
      return;
    }
    if (t.dataset.act === "back") {
      state.step = "pick";
      render();
      return;
    }
  });

  root.addEventListener("submit", (e) => {
    if (e.target && e.target.id === "booking-details") {
      e.preventDefault();
      confirm();
    }
  });

  function onFieldEdit(e) {
    const el = e.target;
    if (!el || !el.name) return;
    if (el.name === "name" || el.name === "email" || el.name === "phone" || el.name === "notes") {
      state[el.name] = el.value;
    }
  }
  root.addEventListener("input", onFieldEdit);
  root.addEventListener("change", onFieldEdit);

  loadMonth();
  loadDay();
})();
