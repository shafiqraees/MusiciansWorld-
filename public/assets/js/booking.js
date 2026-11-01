(function () {
  const mw = window.MW || {};

  function phoenixToday() {
    return new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Phoenix",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());
  }

  function formatClock(minutes) {
    const wrapped = ((minutes % (24 * 60)) + 24 * 60) % (24 * 60);
    const h = Math.floor(wrapped / 60);
    const m = wrapped % 60;
    const ampm = h >= 12 ? "PM" : "AM";
    const hr = h % 12 || 12;
    return hr + ":" + String(m).padStart(2, "0") + " " + ampm;
  }

  function addDays(iso, days) {
    const [y, m, d] = iso.split("-").map(Number);
    const dt = new Date(y, m - 1, d + days);
    return (
      dt.getFullYear() +
      "-" +
      String(dt.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(dt.getDate()).padStart(2, "0")
    );
  }

  function toStamp(dateIso, minutes) {
    const dayOffset = Math.floor(minutes / 1440);
    const mins = ((minutes % 1440) + 1440) % 1440;
    const date = addDays(dateIso, dayOffset);
    const hh = String(Math.floor(mins / 60)).padStart(2, "0");
    const mm = String(mins % 60).padStart(2, "0");
    return date + "T" + hh + ":" + mm;
  }

  function formatUs(dateIso, minutes) {
    const [date, time] = toStamp(dateIso, minutes).split("T");
    const [y, m, d] = date.split("-");
    return m + "/" + d + "/" + y + " " + formatClock(Number(time.slice(0, 2)) * 60 + Number(time.slice(3)));
  }

  function parseStamp(value) {
    const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/.exec(value || "");
    if (!match) return null;
    return { date: match[1], minutes: Number(match[2]) * 60 + Number(match[3]) };
  }

  const root = document.getElementById("booking-root");
  if (!root) return;
  const availabilityUrl = root.dataset.availabilityUrl;
  const bookingUrl = root.dataset.bookingUrl;
  const customer = mw.customer || {};
  const today = phoenixToday();
  const params = new URLSearchParams(window.location.search);
  const fromQuery = parseStamp(params.get("from"));
  const toQuery = parseStamp(params.get("to"));
  const qDate = params.get("date") || fromQuery?.date || "";
  const startDate = /^\d{4}-\d{2}-\d{2}$/.test(qDate) && qDate >= today ? qDate : today;
  const [ty, tm] = startDate.split("-").map(Number);

  function minutesOn(baseDate, parsed) {
    if (!parsed) return null;
    if (parsed.date === baseDate) return parsed.minutes < 9 * 60 ? parsed.minutes + 1440 : parsed.minutes;
    if (parsed.date === addDays(baseDate, 1) && parsed.minutes <= 2 * 60) return parsed.minutes + 1440;
    return null;
  }

  const state = {
    y: ty,
    m: tm,
    days: {},
    date: startDate,
    from: minutesOn(startDate, fromQuery),
    to: minutesOn(startDate, toQuery),
    fromIso: fromQuery ? params.get("from").slice(0, 16) : "",
    toIso: toQuery ? params.get("to").slice(0, 16) : "",
    slots: [],
    price: null,
    quote: null,
    step: "pick",
    name: customer.name || "",
    email: customer.email || "",
    phone: customer.phone || "",
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

  function syncRangeFromSlots() {
    if (state.from == null || state.to == null || state.to <= state.from) {
      state.fromIso = state.from == null ? "" : toStamp(state.date, state.from);
      state.toIso = "";
      return;
    }
    state.fromIso = toStamp(state.date, state.from);
    state.toIso = toStamp(state.date, state.to);
  }

  async function loadMonth() {
    const query = new URLSearchParams({ resource: "studio-a", year: String(state.y), month: String(state.m) });
    const res = await fetch(availabilityUrl + "?" + query.toString(), { headers: { Accept: "application/json" } });
    const data = await res.json();
    state.days = data.days || {};
    render();
  }

  async function loadDay() {
    syncRangeFromSlots();
    const q = new URLSearchParams({ resource: "studio-a", date: state.date });
    if (state.fromIso && state.toIso) {
      q.set("from", state.fromIso);
      q.set("to", state.toIso);
    }
    const res = await fetch(availabilityUrl + "?" + q.toString(), { headers: { Accept: "application/json" } });
    const data = await res.json();
    state.slots = data.slots || [];
    state.quote = data.quote || null;
    state.price = state.quote && state.quote.price != null ? state.quote.price : data.price;
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
    const res = await fetch(bookingUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": mw.csrfToken || "",
      },
      body: JSON.stringify({
        resourceId: "studio-a",
        from: state.fromIso,
        to: state.toIso,
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
    window.location.href = (mw.base || "") + "/book/confirm/" + encodeURIComponent(data.id);
  }

  function dayClass(st, selected) {
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
          ${short.map((d) => `<div class="py-2 sm:hidden">${d}</div>`).join("")}
          ${long.map((d) => `<div class="hidden py-2 sm:block">${d}</div>`).join("")}
          ${cells
            .map((d) => {
              if (!d) return `<div></div>`;
              const iso = `${state.y}-${String(state.m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
              const st = iso < today ? "unavailable" : state.days[iso] || "available";
              const selected = iso === state.date;
              const blocked = st === "unavailable";
              const [yy, mm, dd] = iso.split("-");
              return `<button type="button" ${blocked ? "disabled" : ""} data-iso="${iso}" aria-pressed="${selected}" aria-label="${mm}/${dd}/${yy}, ${st}" class="aspect-square text-sm ${dayClass(st, selected)}">${d}</button>`;
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

  function slotClass(minutes) {
    if (state.from === minutes || state.to === minutes) return "min-h-11 px-1 py-2 text-xs bg-mark text-mark-ink";
    if (state.from != null && state.to != null && minutes > state.from && minutes < state.to) {
      return "min-h-11 px-1 py-2 text-xs bg-mark/30";
    }
    return "min-h-11 px-1 py-2 text-xs border border-line hover:border-text";
  }

  function rangeLabel() {
    if (state.quote && state.quote.from && state.quote.to) {
      return `<p><strong>From</strong> ${escapeHtml(state.quote.from)}</p><p class="mt-1"><strong>To</strong> ${escapeHtml(state.quote.to)}</p>`;
    }
    if (state.from == null) return `<p class="text-dim">Select a start slot, then an end slot.</p>`;
    return `<p><strong>From</strong> ${formatUs(state.date, state.from)}</p><p class="mt-1"><strong>To</strong> ${state.to == null ? "Select an end slot" : formatUs(state.date, state.to)}</p>`;
  }

  function renderPanel() {
    const blocked = state.quote && state.quote.available === false;
    if (state.step === "details") {
      return `
      <div class="book-sheet">
        <p class="kicker">Review before payment</p>
        <h2 class="mt-2 text-lg font-semibold">Studio A</h2>
        <div class="mt-3 text-sm leading-6">${rangeLabel()}</div>
        <p class="mt-3 text-2xl">$${state.price}</p>
        <p class="text-xs text-dim">These are the times that will be charged. After you confirm, Stripe opens for payment.</p>
        <form id="booking-details" class="mt-4 space-y-3" novalidate>
          <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Name</span>
            <input name="name" required autocomplete="name" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.name)}" /></label>
          <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Email</span>
            <input name="email" type="email" required autocomplete="email" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.email)}" /></label>
          <p class="text-xs text-dim">A new account signs in with this email as both the username and the password.</p>
          <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Phone</span>
            <input name="phone" type="tel" autocomplete="tel" class="w-full border border-line bg-bg px-3 py-2" value="${escapeAttr(state.phone)}" /></label>
          <label class="block"><span class="mb-1 block text-xs tracking-[0.14em] text-dim uppercase">Notes</span>
            <textarea name="notes" class="w-full border border-line bg-bg px-3 py-2" rows="3">${escapeHtml(state.notes)}</textarea></label>
          ${state.error ? `<p class="text-sm text-danger">${escapeHtml(state.error)}</p>` : ""}
          <div class="flex gap-2 pt-1">
            <button class="btn btn-ghost flex-1" data-act="back" type="button">Back</button>
            <button class="btn btn-mark flex-1" type="submit" ${state.busy ? "disabled" : ""}>${state.busy ? "Opening checkout…" : "Continue to payment"}</button>
          </div>
        </form>
      </div>`;
    }
    const [yy, mm, dd] = state.date.split("-");
    return `
      <div class="book-sheet">
        <p class="kicker">Book Studio A</p>
        <p class="book-where">Where · ${(window.MW && window.MW.locationLabel) || ""}</p>
        <h2 class="mt-2 text-lg font-semibold">${mm}/${dd}/${yy}</h2>
        <div class="mt-4 text-sm leading-6">${rangeLabel()}</div>
        <p class="mt-4 text-3xl font-semibold">${state.price == null ? "" : "$" + state.price}</p>
        <p class="mt-5 text-xs tracking-[0.14em] text-dim uppercase">Select from slot, then to slot</p>
        <div class="mt-2 grid max-h-64 grid-cols-2 gap-1 overflow-auto sm:grid-cols-3">
          ${state.slots
            .map((s) => {
              const taken = !s.available && state.to !== s.start;
              return `<button type="button" data-start="${s.start}" data-open="${s.available ? "1" : "0"}" aria-pressed="${state.from === s.start || state.to === s.start}" class="${slotClass(s.start)} ${taken ? "text-dim/50 line-through" : ""}">${s.label}</button>`;
            })
            .join("")}
        </div>
        ${
          blocked
            ? `<p class="mt-4 border border-danger/40 bg-danger/10 p-3 text-sm">${escapeHtml(state.quote.status === "closed" ? "Studio A is closed between 2:00 AM and 9:00 AM." : "That range is unavailable. Choose two open slots.")}</p>`
            : ""
        }
        <button class="btn btn-mark mt-6 w-full" type="button" data-act="continue" ${state.from == null || state.to == null || blocked ? "disabled" : ""}>Review from and to</button>
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
      state.from = null;
      state.to = null;
      state.fromIso = "";
      state.toIso = "";
      state.quote = null;
      loadDay();
      return;
    }
    if (t.dataset.start) {
      const picked = Number(t.dataset.start);
      if (state.from == null || picked <= state.from) {
        if (t.dataset.open !== "1") return;
        state.from = picked;
        state.to = null;
      } else {
        state.to = picked;
      }
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
