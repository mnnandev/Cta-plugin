/**
 * CTA Design System — Main JavaScript
 * Clinical Training and Supervision Academy
 */

(function () {
  "use strict";

  /**
   * Mobile menu toggle
   * Toggles .site-header__nav visibility and hamburger animation
   */
  function initMobileMenu() {
    const toggle = document.querySelector(".mobile-menu-toggle");
    const nav = document.querySelector(".site-header__nav");

    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      const isOpen = nav.classList.toggle("site-header__nav--open");
      toggle.classList.toggle("mobile-menu-toggle--active", isOpen);
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      document.body.style.overflow = isOpen ? "hidden" : "";
    });

    nav.querySelectorAll(".site-header__nav-link").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("site-header__nav--open");
        toggle.classList.remove("mobile-menu-toggle--active");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && nav.classList.contains("site-header__nav--open")) {
        nav.classList.remove("site-header__nav--open");
        toggle.classList.remove("mobile-menu-toggle--active");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      }
    });
  }

  /**
   * Generic accordion toggle
   * Works with any .accordion-item inside an .accordion container
   * Set data-accordion="single" on the container to allow only one open item
   */
  function initAccordion() {
    document.querySelectorAll(".accordion").forEach(function (accordion) {
      const isSingle = accordion.dataset.accordion === "single";

      accordion.querySelectorAll(".accordion-item").forEach(function (item) {
        const header = item.querySelector(".accordion-item__header");
        if (!header) return;

        header.setAttribute("aria-expanded", "false");

        header.addEventListener("click", function () {
          const isActive = item.classList.contains("accordion-item--active");

          if (isSingle) {
            accordion.querySelectorAll(".accordion-item--active").forEach(function (openItem) {
              if (openItem !== item) {
                openItem.classList.remove("accordion-item--active");
                const openHeader = openItem.querySelector(".accordion-item__header");
                if (openHeader) openHeader.setAttribute("aria-expanded", "false");
              }
            });
          }

          item.classList.toggle("accordion-item--active", !isActive);
          header.setAttribute("aria-expanded", !isActive ? "true" : "false");
        });
      });
    });
  }

  /**
   * Generic tab switcher
   * Expects structure:
   *   .tabs
   *     .tabs__list > .tabs__tab[data-tab="id"]
   *     .tabs__panel[data-tab-panel="id"]
   */
  function initTabs() {
    document.querySelectorAll(".tabs").forEach(function (tabsContainer) {
      const tabButtons = tabsContainer.querySelectorAll(".tabs__tab");
      const tabPanels = tabsContainer.querySelectorAll(".tabs__panel");

      if (!tabButtons.length || !tabPanels.length) return;

      tabButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          const targetId = button.dataset.tab;
          if (!targetId) return;

          tabButtons.forEach(function (btn) {
            btn.classList.remove("tabs__tab--active");
            btn.setAttribute("aria-selected", "false");
          });

          tabPanels.forEach(function (panel) {
            panel.classList.remove("tabs__panel--active");
            panel.setAttribute("hidden", "");
          });

          button.classList.add("tabs__tab--active");
          button.setAttribute("aria-selected", "true");

          const targetPanel = tabsContainer.querySelector(
            '[data-tab-panel="' + targetId + '"]'
          );

          if (targetPanel) {
            targetPanel.classList.add("tabs__panel--active");
            targetPanel.removeAttribute("hidden");
          }
        });
      });
    });
  }

  function init() {
    initMobileMenu();
    initAccordion();
    initTabs();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
