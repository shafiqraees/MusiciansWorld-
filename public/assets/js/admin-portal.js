(() => {
  const shell = document.querySelector(".admin-shell");
  if (!shell) return;
  const close = () => shell.classList.remove("is-nav-open");
  document.querySelector("[data-admin-open]")?.addEventListener("click", () => shell.classList.add("is-nav-open"));
  document.querySelector("[data-admin-close]")?.addEventListener("click", close);
  document.querySelectorAll(".admin-nav-link").forEach((link) => link.addEventListener("click", close));
  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") close();
  });
})();
