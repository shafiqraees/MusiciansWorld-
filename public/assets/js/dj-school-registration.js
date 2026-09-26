(function () {
  const wizard = document.querySelector("[data-dj-wizard]");
  const form = wizard?.querySelector("[data-dj-form]");
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
  const success = wizard.querySelector("[data-dj-success]");
  const titles = ["Your details", "Experience", "Schedule", "Review"];
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
    if (input.name === "acknowledgment") return form.querySelector(".st-consent-error");
    return null;
  }

  function skipped(input) {
    return input.disabled || Boolean(input.closest("[data-show-when]")?.hidden);
  }

  function clearError(input) {
    input.closest(".st-field")?.classList.remove("is-invalid");
    const error = errorElement(input);
    if (error) error.textContent = "";
    input.closest("[data-require-group]")?.querySelector(".st-group-error") && (input.closest("[data-require-group]").querySelector(".st-group-error").textContent = "");
  }

  function markInvalid(input) {
    input.closest(".st-field")?.classList.add("is-invalid");
    const error = errorElement(input);
    if (!error) return;
    if (input.validity.typeMismatch) error.textContent = "Enter a valid email address.";
    else if (input.validity.tooShort) error.textContent = `Please add at least ${input.minLength} characters.`;
    else error.textContent = `${fieldLabel(input)} is required.`;
  }

  function groupError(step) {
    let first = null;
    step.querySelectorAll("[data-require-group]").forEach((group) => {
      if (group.closest("[data-show-when]")?.hidden) return;
      const checked = group.querySelector("input:checked:not(:disabled)");
      const error = group.querySelector(".st-group-error");
      if (error) error.textContent = "";
      if (!checked) {
        if (error) error.textContent = "Select at least one option.";
        first ||= group.querySelector("input");
      }
    });
    return first;
  }

  function firstInvalidStep() {
    for (let index = 0; index < steps.length; index += 1) {
      const step = steps[index];
      const invalid = [...step.querySelectorAll("[required]")].some((input) => {
        if (skipped(input)) return false;
        if (input.type === "radio") {
          return !step.querySelector(`input[name="${CSS.escape(input.name)}"]:checked:not(:disabled)`);
        }
        if (input.type === "checkbox") return !input.checked;
        return !input.value.trim() || (input.type === "email" && !input.checkValidity()) || (input.minLength > 0 && input.value.trim().length < input.minLength);
      });
      if (invalid || groupError(step)) return index + 1;
    }
    return null;
  }

  function validateStep(number) {
    const step = steps[number - 1];
    let firstInvalid = null;
    const checkedGroups = new Set();

    [...step.querySelectorAll("[required]")].forEach((input) => {
      if (skipped(input)) return;
      clearError(input);
      if (input.type === "radio") {
        if (checkedGroups.has(input.name)) return;
        checkedGroups.add(input.name);
        const groupValid = Boolean(step.querySelector(`input[name="${CSS.escape(input.name)}"]:checked:not(:disabled)`));
        if (!groupValid) {
          markInvalid(input);
          firstInvalid ||= input;
        }
        return;
      }
      const empty = input.type === "checkbox" ? !input.checked : !input.checkValidity();
      if (empty) {
        markInvalid(input);
        firstInvalid ||= input;
      }
    });

    const missingGroup = groupError(step);
    firstInvalid ||= missingGroup;

    if (firstInvalid) {
      firstInvalid.focus({ preventScroll: true });
      firstInvalid.closest(".st-field, .st-choice-group, .st-consent, .st-conditional-block")?.scrollIntoView({ behavior: "smooth", block: "center" });
      return false;
    }
    return true;
  }

  function valueOf(name, fallback = "Not provided") {
    const input = form.elements[name];
    if (!input || input.disabled) return fallback;
    if (input instanceof RadioNodeList) {
      const selected = [...input].find((item) => item.checked && !item.disabled);
      return selected?.value || fallback;
    }
    return input.value?.trim() || fallback;
  }

  function checkedValues(name) {
    return [...form.querySelectorAll(`input[name="${name}"]:checked:not(:disabled)`)].map((item) => item.value);
  }

  function buildReview() {
    const rows = [
      ["Contact", `${valueOf("name")} · ${valueOf("email")} · ${valueOf("phone")}`],
      ["Location", `${valueOf("city")}, ${valueOf("state")} ${valueOf("zip")}`],
      ["Age", valueOf("age")],
    ];
    if (valueOf("age", "") === "Under 18") {
      rows.push(["Parent / guardian", `${valueOf("parentName")} · ${valueOf("parentEmail")}`]);
    }
    rows.push(
      ["Program", valueOf("program")],
      ["Experience", valueOf("experience")],
      ["Equipment", valueOf("hasEquipment") === "Yes" ? valueOf("equipment") : "No equipment yet"],
    );
    if (valueOf("hasEquipment", "") === "Yes") rows.push(["Software", valueOf("software")]);
    rows.push(
      ["Skills", checkedValues("skills[]").join(", ") || "Not provided"],
      ["Best times", checkedValues("bestTimes[]").join(", ") || "Not provided"],
      ["Best days", checkedValues("bestDays[]").join(", ") || "Not provided"],
      ["Specific event", valueOf("preparingEvent") === "Yes" ? valueOf("eventDetails") : "No"],
      ["Heard about us", valueOf("referral")],
    );
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

  function syncConditionals() {
    form.querySelectorAll("[data-show-when]").forEach((block) => {
      const [name, expected] = block.dataset.showWhen.split(":");
      const control = form.elements[name];
      const selected = control instanceof RadioNodeList ? control.value : (control?.value || "");
      const show = selected === expected;
      block.hidden = !show;
      block.querySelectorAll("input, textarea, select").forEach((input) => {
        input.disabled = !show;
        if (!show) {
          if (input.type === "checkbox" || input.type === "radio") input.checked = false;
          else input.value = "";
          clearError(input);
        }
      });
    });
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
    if (["age", "hasEquipment", "preparingEvent"].includes(event.target.name)) syncConditionals();
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
    if (target !== current) setStep(target, false);
    validateStep(target);
  });
  form.addEventListener("mw:form-error", () => {
    form.querySelector(".form-msg")?.scrollIntoView({ behavior: "smooth", block: "center" });
  });
  form.addEventListener("mw:form-success", () => {
    form.hidden = true;
    success.hidden = false;
    success.focus?.({ preventScroll: true });
    wizard.scrollIntoView({ behavior: "smooth", block: "center" });
  });

  syncConditionals();
  setStep(1, false);
})();
