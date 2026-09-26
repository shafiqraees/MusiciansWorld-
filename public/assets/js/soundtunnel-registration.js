(function () {
  const wizard = document.querySelector("[data-st-wizard]");
  const form = wizard?.querySelector("[data-soundtunnel-form]");
  if (!wizard || !form) return;

  const steps = [...form.querySelectorAll("[data-step]")];
  const indicators = [...wizard.querySelectorAll("[data-step-indicator]")];
  const backButton = form.querySelector("[data-step-back]");
  const nextButton = form.querySelector("[data-step-next]");
  const submitButton = form.querySelector("[data-step-submit]");
  const stepLabel = wizard.querySelector("[data-step-label]");
  const stepTitle = wizard.querySelector("[data-step-title]");
  const progressBar = wizard.querySelector("[data-progress-bar]");
  const review = form.querySelector("[data-review-summary]");
  const ticketPrice = form.querySelector("[data-ticket-price]");
  const success = wizard.querySelector("[data-st-success]");
  const titles = ["Your details", "Event details", "Production", "Review"];
  let current = 1;

  function fieldLabel(input) {
    return input.closest(".st-field")?.querySelector(":scope > span")?.textContent.replace("*", "").replace("Optional", "").trim()
      || input.closest("fieldset")?.querySelector("legend")?.textContent.replace("*", "").trim()
      || "This field";
  }

  function errorElement(input) {
    const field = input.closest(".st-field");
    if (field) return field.querySelector(".st-field-error");
    const group = input.closest(".st-choice-group");
    if (group) return group.querySelector(".st-group-error");
    if (input.name === "terms") return form.querySelector(".st-consent-error");
    return null;
  }

  function clearError(input) {
    input.closest(".st-field")?.classList.remove("is-invalid");
    const error = errorElement(input);
    if (error) error.textContent = "";
  }

  function markInvalid(input) {
    input.closest(".st-field")?.classList.add("is-invalid");
    const error = errorElement(input);
    if (!error) return;
    if (input.validity.typeMismatch) error.textContent = "Enter a valid email address.";
    else if (input.validity.rangeOverflow) error.textContent = "SoundTunnel accommodates up to 250 guests.";
    else if (input.validity.rangeUnderflow) error.textContent = "Enter at least one guest.";
    else if (input.validity.tooShort) error.textContent = `Please add at least ${input.minLength} characters.`;
    else error.textContent = `${fieldLabel(input)} is required.`;
  }

  function firstInvalidStep() {
    for (let index = 0; index < steps.length; index += 1) {
      const step = steps[index];
      const invalid = [...step.querySelectorAll("[required]")].some((input) =>
        input.type === "radio"
          ? !step.querySelector(`input[name="${CSS.escape(input.name)}"]:checked`)
          : !input.checkValidity()
      );
      if (invalid) return index + 1;
    }
    return null;
  }

  function validateStep(number) {
    const step = steps[number - 1];
    const required = [...step.querySelectorAll("[required]")];
    let firstInvalid = null;
    const checkedGroups = new Set();

    required.forEach((input) => {
      clearError(input);
      if (input.type === "radio") {
        if (checkedGroups.has(input.name)) return;
        checkedGroups.add(input.name);
        const groupValid = Boolean(step.querySelector(`input[name="${CSS.escape(input.name)}"]:checked`));
        if (!groupValid) {
          markInvalid(input);
          firstInvalid ||= input;
        }
        return;
      }
      if (!input.checkValidity()) {
        markInvalid(input);
        firstInvalid ||= input;
      }
    });

    if (firstInvalid) {
      firstInvalid.focus({ preventScroll: true });
      firstInvalid.closest(".st-field, .st-choice-group, .st-consent")?.scrollIntoView({ behavior: "smooth", block: "center" });
      return false;
    }
    return true;
  }

  function valueOf(name, fallback = "Not provided") {
    const input = form.elements[name];
    if (!input) return fallback;
    if (input instanceof RadioNodeList) return input.value || fallback;
    return input.value?.trim() || fallback;
  }

  function buildReview() {
    const services = [...form.querySelectorAll('input[name="services[]"]:checked')].map((item) => item.value);
    const rows = [
      ["Contact", `${valueOf("name")} · ${valueOf("email")}`],
      ["Event", `${valueOf("eventName")} · ${valueOf("eventType")}`],
      ["Preferred date", `${valueOf("date")} at ${valueOf("startTime")}`],
      ["Attendance", `${valueOf("headcount")} guests · ${valueOf("audience")}`],
      ["Format", `${valueOf("duration")} · ${valueOf("ticketed") === "Yes" ? "Ticketed" : "Free / private entry"}`],
      ["Venue services", services.length ? services.join(", ") : "No additional services selected"],
      ["Alcohol", valueOf("alcohol")],
    ];
    review.replaceChildren(...rows.map(([label, value]) => {
      const row = document.createElement("div");
      const key = document.createElement("span");
      const content = document.createElement("strong");
      row.className = "st-review-row";
      key.textContent = label;
      content.textContent = value;
      row.append(key, content);
      return row;
    }));
  }

  function setStep(number, focusHeading = true) {
    current = Math.max(1, Math.min(steps.length, number));
    steps.forEach((step, index) => {
      const active = index + 1 === current;
      step.hidden = !active;
      step.classList.toggle("is-active", active);
    });
    indicators.forEach((indicator, index) => {
      indicator.classList.toggle("is-active", index + 1 === current);
      indicator.classList.toggle("is-complete", index + 1 < current);
      indicator.setAttribute("aria-current", index + 1 === current ? "step" : "false");
    });
    backButton.hidden = current === 1;
    nextButton.hidden = current === steps.length;
    submitButton.hidden = current !== steps.length;
    form.dataset.readyToSubmit = current === steps.length ? "true" : "false";
    stepLabel.textContent = `Step ${current} of ${steps.length}`;
    stepTitle.textContent = titles[current - 1];
    progressBar.style.width = `${(current / steps.length) * 100}%`;
    if (current === steps.length) buildReview();
    if (focusHeading) {
      steps[current - 1].querySelector("h3")?.focus({ preventScroll: true });
      wizard.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  form.addEventListener("input", (event) => clearError(event.target));
  form.addEventListener("change", (event) => {
    clearError(event.target);
    if (event.target.name === "ticketed") {
      ticketPrice.hidden = event.target.value !== "Yes";
      if (ticketPrice.hidden) form.elements.ticketPrice.value = "";
    }
  });

  nextButton.addEventListener("click", () => {
    if (validateStep(current)) setStep(current + 1);
  });
  backButton.addEventListener("click", () => setStep(current - 1));

  form.addEventListener("submit", (event) => {
    if (current < steps.length) {
      event.preventDefault();
      if (validateStep(current)) setStep(current + 1);
      return;
    }
    if (!validateStep(current)) event.preventDefault();
  });

  form.addEventListener("mw:form-invalid", () => {
    const target = firstInvalidStep() || current;
    if (target !== current) setStep(target);
    validateStep(target);
  });
  form.addEventListener("mw:form-error", () => {
    const message = form.querySelector(".form-msg");
    if (message) message.scrollIntoView({ behavior: "smooth", block: "center" });
  });
  form.addEventListener("mw:form-success", () => {
    form.hidden = true;
    success.hidden = false;
    success.focus?.({ preventScroll: true });
    wizard.scrollIntoView({ behavior: "smooth", block: "center" });
  });

  setStep(1, false);
})();
