(() => {
  const root = document.documentElement;
  const THEME_KEY = "mwcn-admin-theme";

  /* ---------------------------------------------------------------- Theme */
  const applyTheme = (theme) => {
    root.dataset.theme = theme;
    try { localStorage.setItem(THEME_KEY, theme); } catch (_) { /* private mode */ }
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.setAttribute("aria-label", theme === "dark" ? "Switch to light theme" : "Switch to dark theme");
      button.setAttribute("aria-pressed", theme === "light" ? "true" : "false");
    });
  };
  document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
    button.addEventListener("click", () => applyTheme(root.dataset.theme === "light" ? "dark" : "light"));
  });
  applyTheme(root.dataset.theme || "dark");

  /* ----------------------------------------------------------- Navigation */
  const shell = document.querySelector(".admin-shell");
  if (shell) {
    const close = () => shell.classList.remove("is-nav-open");
    document.querySelector("[data-admin-open]")?.addEventListener("click", () => shell.classList.add("is-nav-open"));
    document.querySelector("[data-admin-close]")?.addEventListener("click", close);
    document.querySelectorAll(".admin-nav-link").forEach((link) => link.addEventListener("click", close));
    window.addEventListener("keydown", (event) => { if (event.key === "Escape") close(); });
  }

  /* --------------------------------------------------------------- Modals */
  const openModal = (id) => {
    const dialog = document.getElementById(id);
    if (!dialog || typeof dialog.showModal !== "function" || dialog.open) return;
    dialog.showModal();
    const focusable = dialog.querySelector("input:not([type=hidden]):not([readonly]), select, textarea, button:not(.a-modal-close)");
    focusable?.focus({ preventScroll: true });
  };
  document.addEventListener("click", (event) => {
    const opener = event.target.closest("[data-modal-open]");
    if (opener) {
      event.preventDefault();
      openModal(opener.getAttribute("data-modal-open"));
      return;
    }
    const closer = event.target.closest("[data-modal-close]");
    if (closer) {
      event.preventDefault();
      closer.closest("dialog")?.close();
      return;
    }
    // Click on the backdrop (outside the dialog's box) closes it.
    const dialog = event.target.closest("dialog.a-modal");
    if (dialog && event.target === dialog) {
      const rect = dialog.getBoundingClientRect();
      const inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
      if (!inside) dialog.close();
    }
  });
  const autoOpen = document.body.getAttribute("data-open-modal");
  if (autoOpen) openModal(autoOpen);
  const autoPane = document.body.getAttribute("data-open-pane");
  if (autoOpen && autoPane) {
    document.getElementById(autoOpen)?.querySelector(`[data-modal-tabs] button[data-pane="${autoPane}"]`)?.click();
  }

  /* ----------------------------------------------------------------- Tabs */
  document.querySelectorAll("[data-tabs]").forEach((group) => {
    const name = group.getAttribute("data-tabs");
    const tabs = Array.from(group.querySelectorAll("[data-tab]"));
    const panels = Array.from(document.querySelectorAll(`[data-tab-panel][data-tab-group="${name}"]`));
    const activate = (id, push) => {
      tabs.forEach((tab) => tab.classList.toggle("is-active", tab.getAttribute("data-tab") === id));
      panels.forEach((panel) => { panel.hidden = panel.getAttribute("data-tab-panel") !== id; });
      if (push) {
        const url = new URL(window.location.href);
        url.searchParams.set("tab", id);
        history.replaceState(null, "", url);
      }
    };
    tabs.forEach((tab) => tab.addEventListener("click", (event) => { event.preventDefault(); activate(tab.getAttribute("data-tab"), true); }));
    const initial = new URL(window.location.href).searchParams.get("tab");
    const fallback = tabs.find((tab) => tab.classList.contains("is-active")) || tabs[0];
    activate(tabs.some((tab) => tab.getAttribute("data-tab") === initial) ? initial : fallback?.getAttribute("data-tab"), false);
  });

  /* Modal-internal tabs (not URL-bound) */
  document.querySelectorAll("[data-modal-tabs]").forEach((group) => {
    const buttons = Array.from(group.querySelectorAll("button[data-pane]"));
    const scope = group.closest("dialog") || document;
    const panes = Array.from(scope.querySelectorAll("[data-pane-panel]"));
    const activate = (id) => {
      buttons.forEach((button) => button.classList.toggle("is-active", button.getAttribute("data-pane") === id));
      panes.forEach((pane) => { pane.hidden = pane.getAttribute("data-pane-panel") !== id; });
    };
    buttons.forEach((button) => button.addEventListener("click", (event) => { event.preventDefault(); activate(button.getAttribute("data-pane")); }));
    activate((buttons.find((b) => b.classList.contains("is-active")) || buttons[0])?.getAttribute("data-pane"));
  });

  /* ------------------------------------------------------ Password helper */
  document.querySelectorAll("[data-generate-password]").forEach((button) => {
    button.addEventListener("click", () => {
      const scope = button.closest("form") || document;
      const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
      const bytes = new Uint32Array(14);
      crypto.getRandomValues(bytes);
      let password = Array.from(bytes, (n) => alphabet[n % alphabet.length]).join("");
      password = password.slice(0, 6) + "7" + password.slice(6, 12) + "k";
      scope.querySelectorAll("input[name=password], input[name=password_confirmation]").forEach((input) => {
        input.type = "text";
        input.value = password;
      });
    });
  });
  document.querySelectorAll("[data-toggle-password]").forEach((button) => {
    button.addEventListener("click", () => {
      const scope = button.closest("form") || document;
      scope.querySelectorAll("input[name=password], input[name=password_confirmation]").forEach((input) => {
        input.type = input.type === "password" ? "text" : "password";
      });
    });
  });

  /* ----------------------------------------------------- Confirm & misc */
  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!window.confirm(form.getAttribute("data-confirm"))) event.preventDefault();
    });
  });
  document.querySelectorAll("[data-select-all]").forEach((button) => {
    button.addEventListener("click", () => {
      const scope = document.getElementById(button.getAttribute("data-select-all"));
      const boxes = scope ? scope.querySelectorAll("input[type=checkbox]") : [];
      const allChecked = Array.from(boxes).every((box) => box.checked);
      boxes.forEach((box) => { box.checked = !allChecked; });
    });
  });
  document.querySelectorAll("[data-location-switch]").forEach((select) => {
    const update = () => {
      const scope = select.closest("form") || document;
      scope.querySelectorAll("[data-location-only]").forEach((node) => {
        node.hidden = node.getAttribute("data-location-only") !== select.value;
      });
    };
    select.addEventListener("change", update);
    update();
  });
  document.querySelectorAll(".a-alert.success[data-autodismiss]").forEach((alert) => {
    setTimeout(() => { alert.style.transition = "opacity .4s"; alert.style.opacity = "0"; setTimeout(() => alert.remove(), 450); }, 6000);
  });
})();
