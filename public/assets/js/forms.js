(function () {
  const mw = window.MW || {};

  function route(name) {
    return (mw.routes && mw.routes[name]) || "";
  }

  function requestHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": mw.csrfToken || "",
    };
  }

  function prefillCustomer(form) {
    if (!mw.customer) return;

    const values = {
      name: mw.customer.name,
      studentName: mw.customer.name,
      email: mw.customer.email,
      phone: mw.customer.phone,
      city: mw.customer.city,
      state: mw.customer.state,
      zip: mw.customer.zip,
    };

    Object.entries(values).forEach(([name, value]) => {
      const field = form.elements.namedItem(name);
      if (field && !field.value && value) field.value = value;
    });
  }

  function showMsg(form, text, state) {
    const el = form.querySelector(".form-msg");
    if (!el) return;
    el.hidden = false;
    el.textContent = text || "";
    el.classList.toggle("is-pending", state === "pending");
    el.classList.toggle("is-success", state === "success");
  }

  function formToBody(form) {
    const fd = new FormData(form);
    const body = {};
    for (const key of new Set(fd.keys())) {
      if (key.endsWith("[]")) {
        body[key.slice(0, -2)] = fd.getAll(key);
      } else {
        body[key] = fd.get(key);
      }
    }
    return body;
  }

  document.querySelectorAll("[data-form]").forEach((form) => {
    prefillCustomer(form);

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (form.matches("[data-soundtunnel-form], [data-dj-form]") && form.dataset.readyToSubmit !== "true") return;
      if (!form.checkValidity()) {
        form.dispatchEvent(new CustomEvent("mw:form-invalid"));
        return;
      }
      const kind = form.getAttribute("data-form");
      const body = formToBody(form);
      const submit = form.querySelector('[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.dataset.originalText = submit.innerHTML;
        submit.textContent = "Sending…";
      }
      showMsg(form, "Sending your request. This can take a few seconds…", "pending");

      try {
        if (kind === "membership" && body.plan === "founding") {
          showMsg(form, "Redirecting to secure checkout…");
          const res = await fetch(route("membership-checkout"), {
            method: "POST",
            headers: requestHeaders(),
            body: JSON.stringify(body),
          });
          const data = await res.json();
          if (data.url) {
            window.location.href = data.url;
          } else {
            showMsg(form, data.error || "Could not start checkout.");
          }
          return;
        }

        let url = route("contact");
        let okText = "Received. The desk will get back to you.";
        if (kind === "soundtunnel") {
          url = route("soundtunnel");
          okText = "Application received. Check your email for the next registration step.";
        } else if (kind === "dj-school") {
          url = route("dj-school");
          okText = "Request received. Check your email to complete DJ Registration.";
        } else if (kind === "enroll") {
          url = route("enroll");
        } else if (kind === "membership") {
          url = route("membership");
        } else if (kind === "dj-lesson") {
          url = route("dj-lesson");
          okText = "Inquiry received. The desk will follow up to confirm placement.";
        }
        const res = await fetch(url, {
          method: "POST",
          headers: requestHeaders(),
          body: JSON.stringify(body),
        });
        const data = await res.json();
        const succeeded = res.ok && data.ok !== false && !data.error;
        const state = succeeded ? "success" : "error";
        if (kind === "enroll" || kind === "dj-lesson" || kind === "soundtunnel" || kind === "dj-school") showMsg(form, data.note || data.error || okText, state);
        else if (kind === "membership") showMsg(form, data.note || data.error || "", state);
        else showMsg(form, succeeded ? okText : data.error, state);
        form.dispatchEvent(new CustomEvent(succeeded ? "mw:form-success" : "mw:form-error", { detail: data }));
      } catch (error) {
        showMsg(form, "We could not send your request. Please check your connection and try again.");
        form.dispatchEvent(new CustomEvent("mw:form-error", { detail: { error: error.message } }));
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.innerHTML = submit.dataset.originalText || "Submit";
        }
      }
    });
  });

  document.querySelectorAll("[data-plan]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const plan = btn.getAttribute("data-plan");
      const hidden = document.getElementById("join-plan");
      const label = document.getElementById("join-plan-label");
      if (hidden) hidden.value = plan;
      if (label) label.textContent = btn.getAttribute("data-plan-label") || "Joining as " + plan;
      document.getElementById("join")?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
})();
