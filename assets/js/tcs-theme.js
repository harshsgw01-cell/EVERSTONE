/* ════════════════════════════════════════
   EVERSTONE SHARED THEME MANAGER
   Include on every page before page scripts
════════════════════════════════════════ */
(function () {
  const STORAGE_KEY = "tcs-theme";
  const DEFAULT = "dark";
  const html = document.documentElement;

  function apply(theme) {
    html.setAttribute("data-theme", theme);
    localStorage.setItem(STORAGE_KEY, theme);

    // Sync every .tcs-theme-toggle icon on the page
    document
      .querySelectorAll(
        ".tcs-theme-toggle [data-theme-icon], .tcs-theme-toggle i",
      )
      .forEach((icon) => {
        icon.className =
          theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
      });
  }

  function toggle() {
    apply(html.getAttribute("data-theme") === "dark" ? "light" : "dark");
  }

  function init() {
    apply(localStorage.getItem(STORAGE_KEY) || DEFAULT);

    // Wire every .tcs-theme-toggle button automatically
    document.querySelectorAll(".tcs-theme-toggle").forEach((btn) => {
      btn.addEventListener("click", toggle);
    });
  }

  // Expose globally for page-specific scripts
  window.EverstoneTheme = { apply, toggle };

  // Run on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
