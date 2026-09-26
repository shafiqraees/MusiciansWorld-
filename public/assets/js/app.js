(function () {
  const header = document.getElementById("site-header");
  const openBtn = document.getElementById("menu-open");
  const closeBtn = document.getElementById("menu-close");
  const overlay = document.getElementById("menu-overlay");
  const sidebar = document.getElementById("mobile-sidebar");
  const bars = [
    document.querySelector(".menu-bar-1"),
    document.querySelector(".menu-bar-2"),
    document.querySelector(".menu-bar-3"),
  ];
  let open = false;

  function setOpen(next) {
    open = next;
    document.body.style.overflow = open ? "hidden" : "";
    if (openBtn) {
      openBtn.setAttribute("aria-expanded", open ? "true" : "false");
      openBtn.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    }
    if (overlay) {
      overlay.classList.toggle("opacity-100", open);
      overlay.classList.toggle("opacity-0", !open);
      overlay.classList.toggle("pointer-events-none", !open);
      overlay.tabIndex = open ? 0 : -1;
    }
    if (sidebar) {
      sidebar.classList.toggle("translate-x-0", open);
      sidebar.classList.toggle("translate-x-full", !open);
      sidebar.classList.toggle("pointer-events-none", !open);
      sidebar.setAttribute("aria-hidden", open ? "false" : "true");
    }
    if (bars[0]) bars[0].classList.toggle("translate-y-[7px]", open);
    if (bars[0]) bars[0].classList.toggle("rotate-45", open);
    if (bars[1]) bars[1].classList.toggle("opacity-0", open);
    if (bars[2]) bars[2].classList.toggle("-translate-y-[7px]", open);
    if (bars[2]) bars[2].classList.toggle("-rotate-45", open);
    paintHeader();
  }

  function paintHeader() {
    if (!header) return;
    const scrolled = window.scrollY > 12;
    const on = scrolled || open;
    header.classList.toggle("border-b", on);
    header.classList.toggle("border-line", on);
    header.classList.toggle("bg-bg/90", on);
    header.classList.toggle("shadow-[0_10px_40px_rgba(0,0,0,0.45)]", on);
    header.classList.toggle("backdrop-blur-md", on);
    header.classList.toggle("bg-transparent", !on);
  }

  window.addEventListener("scroll", paintHeader, { passive: true });
  paintHeader();
  openBtn?.addEventListener("click", () => setOpen(!open));
  closeBtn?.addEventListener("click", () => setOpen(false));
  overlay?.addEventListener("click", () => setOpen(false));
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setOpen(false);
  });

  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  document.querySelectorAll(".reveal").forEach((el) => {
    if (reduced) {
      el.classList.add("is-in");
      return;
    }
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) el.classList.add("is-in");
      },
      { threshold: 0.08, rootMargin: "0px 0px -10% 0px" }
    );
    io.observe(el);
    if (el.getBoundingClientRect().top < window.innerHeight * 0.92) el.classList.add("is-in");
    window.setTimeout(() => el.classList.add("is-in"), 1200);
  });
})();
