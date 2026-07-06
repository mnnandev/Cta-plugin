/**
 * CTA Design System — Main JavaScript
 * Clinical Training and Supervision Academy
 */

(function () {
  "use strict";

  var USERS_KEY = "cta_users";
  var SESSION_KEY = "cta_session";

  function loadUsers() {
    try {
      var raw = localStorage.getItem(USERS_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (err) {
      return [];
    }
  }

  function saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
  }

  function setSession(email) {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify({ email: email.toLowerCase() }));
  }

  function clearSession() {
    sessionStorage.removeItem(SESSION_KEY);
  }

  function getCurrentUser() {
    try {
      var raw = sessionStorage.getItem(SESSION_KEY);
      if (!raw) return null;
      var session = JSON.parse(raw);
      var users = loadUsers();
      return users.find(function (user) {
        return user.email === session.email;
      }) || null;
    } catch (err) {
      return null;
    }
  }

  function getInitials(fullName) {
    var parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function getFirstName(fullName) {
    return fullName.trim().split(/\s+/)[0] || "there";
  }

  function getUserLicenseLabel(user) {
    if (user.licenseNumber) return user.licenseNumber;
    return user.userType === "associate" ? "Registered Associate" : "Licensed Professional";
  }

  function findUserByLogin(identifier) {
    var value = identifier.trim().toLowerCase();
    var users = loadUsers();
    return users.find(function (user) {
      return user.email === value || user.email.split("@")[0] === value;
    }) || null;
  }

  function registerUser(profile) {
    var users = loadUsers();
    var email = profile.email.trim().toLowerCase();

    if (users.some(function (user) { return user.email === email; })) {
      return { ok: false, message: "An account with this email already exists. Please log in instead." };
    }

    var user = {
      email: email,
      fullName: profile.fullName.trim(),
      password: profile.password,
      userType: profile.userType || "associate",
      licenseNumber: profile.userType === "associate" ? "Registered Associate" : "Licensed Professional"
    };

    users.push(user);
    saveUsers(users);
    setSession(email);
    return { ok: true, user: user };
  }

  function loginUser(identifier, password) {
    var user = findUserByLogin(identifier);
    if (!user || user.password !== password) {
      return { ok: false, message: "Invalid user name or password. Please try again or sign up." };
    }
    setSession(user.email);
    return { ok: true, user: user };
  }

  function updateUserProfile(email, updates) {
    var users = loadUsers();
    var index = users.findIndex(function (user) { return user.email === email; });
    if (index === -1) return null;

    users[index] = Object.assign({}, users[index], updates, { email: email });
    saveUsers(users);
    return users[index];
  }

  function applyUserToDashboard(user) {
    var firstName = getFirstName(user.fullName);
    var initials = getInitials(user.fullName);
    var licenseLabel = getUserLicenseLabel(user);

    document.querySelectorAll("[data-user-avatar]").forEach(function (el) {
      if (user.avatarUrl) {
        el.textContent = "";
        el.style.backgroundImage = "url(\"" + user.avatarUrl + "\")";
        el.classList.add("dashboard-sidebar__avatar--photo");
      } else {
        el.textContent = initials;
        el.style.backgroundImage = "";
        el.classList.remove("dashboard-sidebar__avatar--photo");
      }
    });
    document.querySelectorAll("[data-user-name]").forEach(function (el) {
      el.textContent = user.fullName;
    });
    document.querySelectorAll("[data-user-license]").forEach(function (el) {
      el.textContent = licenseLabel;
    });
    document.querySelectorAll("[data-user-greeting]").forEach(function (el) {
      el.textContent = "Welcome back, " + firstName;
    });

    var nameInput = document.getElementById("settings-name");
    var emailInput = document.getElementById("settings-email");
    var licenseInput = document.getElementById("settings-license");
    var typeInput = document.getElementById("settings-type");
    var photoPreview = document.querySelector("[data-profile-photo-preview]");
    var photoImage = document.querySelector("[data-profile-photo-image]");

    if (nameInput) nameInput.value = user.fullName;
    if (emailInput) emailInput.value = user.email;
    if (licenseInput) licenseInput.value = user.licenseNumber || licenseLabel;
    if (typeInput && user.userType === "licensed") {
      typeInput.value = "lmft";
    }

    if (photoPreview && photoImage) {
      if (user.avatarUrl) {
        photoPreview.textContent = initials;
        photoPreview.hidden = true;
        photoImage.src = user.avatarUrl;
        photoImage.hidden = false;
      } else {
        photoPreview.textContent = initials;
        photoPreview.hidden = false;
        photoImage.src = "";
        photoImage.hidden = true;
      }
    }
  }

  function initProfilePhotoUpload() {
    var fileInput = document.getElementById("settings-photo");
    if (!fileInput) return;

    fileInput.addEventListener("change", function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      var maxSize = 10 * 1024 * 1024;
      if (file.size > maxSize) {
        fileInput.value = "";
        return;
      }

      var reader = new FileReader();
      reader.onload = function () {
        var user = getCurrentUser();
        if (!user) return;

        var updated = updateUserProfile(user.email, { avatarUrl: reader.result });
        if (updated) applyUserToDashboard(updated);
        fileInput.value = "";
      };
      reader.onerror = function () {
        fileInput.value = "";
      };
      reader.readAsDataURL(file);
    });
  }

  function initDashboardUser() {
    if (!document.body.classList.contains("dashboard-page")) return;

    var user = getCurrentUser();
    if (!user) {
      window.location.href = "login.html";
      return;
    }

    applyUserToDashboard(user);
    initProfilePhotoUpload();

    document.querySelectorAll("[data-auth-logout]").forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        clearSession();
        window.location.href = "login.html";
      });
    });
  }

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

  /**
   * Dashboard sidebar panel switcher
   * Container: [data-dashboard]
   * Nav links: [data-dashboard-nav="panel-id"]
   * Panels: [data-dashboard-panel="panel-id"]
   */
  function initDashboardNav() {
    const layout = document.querySelector("[data-dashboard]");
    if (!layout) return;

    const links = layout.querySelectorAll("[data-dashboard-nav]");
    const panels = layout.querySelectorAll("[data-dashboard-panel]");
    if (!links.length || !panels.length) return;

    function showPanel(panelId) {
      links.forEach(function (link) {
        const isActive = link.dataset.dashboardNav === panelId;
        link.classList.toggle("dashboard-sidebar__link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });

      panels.forEach(function (panel) {
        const isActive = panel.dataset.dashboardPanel === panelId;
        panel.hidden = !isActive;
        panel.classList.toggle("dashboard-panel--active", isActive);
      });

      if (panelId && panelId !== "courses" && panelId !== "sessions") {
        window.location.hash = panelId;
      } else {
        history.replaceState(null, "", window.location.pathname + window.location.search);
      }
    }

    links.forEach(function (link) {
      link.addEventListener("click", function (e) {
        const panelId = link.dataset.dashboardNav;
        if (!panelId) return;

        const href = link.getAttribute("href") || "";
        const isSamePage =
          href === "#" ||
          href.startsWith("#") ||
          (href.indexOf("dashboard-ce.html") !== -1 && !href.includes("course-player")) ||
          href.indexOf("dashboard-supervision.html") !== -1;

        if (isSamePage && layout.contains(link)) {
          e.preventDefault();
          showPanel(panelId);
        }
      });
    });

    const hash = window.location.hash.replace("#", "");
    if (hash && layout.querySelector('[data-dashboard-panel="' + hash + '"]')) {
      showPanel(hash);
    }
  }

  /**
   * Dashboard settings save (mock — no backend yet)
   */
  function initDashboardSettings() {
    document.querySelectorAll(".dashboard-settings-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        handleSettingsSave(form);
      });
    });
  }

  function handleSettingsSave(form) {
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    var user = getCurrentUser();
    if (user) {
      var fullNameInput = form.querySelector('[name="full_name"]');
      var licenseInput = form.querySelector('[name="license_number"]');
      var updated = updateUserProfile(user.email, {
        fullName: fullNameInput ? fullNameInput.value : user.fullName,
        licenseNumber: licenseInput ? licenseInput.value : user.licenseNumber
      });
      if (updated) applyUserToDashboard(updated);
    }

    var existing = form.querySelector(".dashboard-settings__notice");
    if (existing) existing.remove();

    var notice = document.createElement("p");
    notice.className = "dashboard-settings__notice dashboard-settings__notice--success";
    notice.setAttribute("role", "status");
    notice.textContent = "Your changes have been saved successfully.";
    form.insertBefore(notice, form.firstChild);

    var btn = form.querySelector('[type="submit"]');
    if (btn) {
      var originalText = btn.textContent;
      btn.textContent = "Saved!";
      btn.disabled = true;
      setTimeout(function () {
        btn.textContent = originalText;
        btn.disabled = false;
      }, 2000);
    }
  }

  function initCoursePlayer() {
    var layout = document.querySelector("[data-course-player]");
    if (!layout) return;

    var video = layout.querySelector(".course-player__video");
    var markBtn = layout.querySelector("[data-mark-complete]");
    var quizBtn = layout.querySelector("[data-take-quiz]");
    var modal = document.getElementById("course-quiz-modal");
    var quizForm = document.getElementById("course-quiz-form");
    var quizResult = document.getElementById("course-quiz-result");

    var correctAnswers = { q1: "a", q2: "b", q3: "b" };

    function showPlayerNotice(message, type) {
      var existing = layout.querySelector(".course-player__notice");
      if (existing) existing.remove();

      var notice = document.createElement("p");
      notice.className = "course-player__notice" + (type ? " course-player__notice--" + type : "");
      notice.setAttribute("role", "status");
      notice.textContent = message;

      var actions = layout.querySelector("[data-course-player-actions]");
      if (actions) {
        actions.insertAdjacentElement("afterend", notice);
      }
    }

    function enableQuiz() {
      if (!quizBtn) return;
      quizBtn.disabled = false;
      quizBtn.removeAttribute("aria-disabled");
      quizBtn.classList.remove("btn-outline");
      quizBtn.classList.add("btn-primary");
    }

    function markLessonComplete() {
      if (video) {
        video.classList.add("course-player__video--watched");
        video.classList.remove("course-player__video--playing");
      }
      if (markBtn && !markBtn.disabled) {
        markBtn.textContent = "Completed";
        markBtn.disabled = true;
        markBtn.setAttribute("aria-disabled", "true");
      }
      enableQuiz();
      showPlayerNotice("Lesson marked complete. You can now take the quiz.", "success");
    }

    function startVideoPlayback() {
      if (!video || video.classList.contains("course-player__video--watched")) return;
      if (video.classList.contains("course-player__video--playing")) return;

      video.classList.add("course-player__video--playing");
      showPlayerNotice("Video playing… (demo preview)", "info");

      setTimeout(function () {
        video.classList.remove("course-player__video--playing");
        video.classList.add("course-player__video--watched");
        enableQuiz();
        showPlayerNotice("Video complete. Mark as complete or take the quiz.", "success");
      }, 2500);
    }

    if (video) {
      video.addEventListener("click", startVideoPlayback);
      video.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          startVideoPlayback();
        }
      });
    }

    if (markBtn) {
      markBtn.addEventListener("click", markLessonComplete);
    }

    function openQuizModal() {
      if (!modal || quizBtn.disabled) return;

      modal.hidden = false;
      modal.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden";

      if (quizForm) quizForm.hidden = false;
      if (quizResult) {
        quizResult.hidden = true;
        quizResult.textContent = "";
        quizResult.className = "course-quiz-result";
      }

      var closeBtn = modal.querySelector(".course-quiz-modal__close");
      if (closeBtn) closeBtn.focus();
    }

    function closeQuizModal() {
      if (!modal) return;

      modal.hidden = true;
      modal.setAttribute("aria-hidden", "true");
      document.body.style.overflow = "";

      if (quizBtn && !quizBtn.disabled) quizBtn.focus();
    }

    if (quizBtn && modal) {
      quizBtn.addEventListener("click", openQuizModal);

      modal.querySelectorAll("[data-quiz-close]").forEach(function (el) {
        el.addEventListener("click", closeQuizModal);
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !modal.hidden) {
          closeQuizModal();
        }
      });
    }

    if (quizForm && quizResult) {
      quizForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!quizForm.checkValidity()) {
          quizForm.reportValidity();
          return;
        }

        var score = 0;
        var total = Object.keys(correctAnswers).length;

        Object.keys(correctAnswers).forEach(function (key) {
          var selected = quizForm.querySelector('input[name="' + key + '"]:checked');
          if (selected && selected.value === correctAnswers[key]) {
            score += 1;
          }
        });

        var passed = score === total;

        quizForm.hidden = true;
        quizResult.hidden = false;
        quizResult.classList.add(passed ? "course-quiz-result--pass" : "course-quiz-result--fail");

        if (passed) {
          quizResult.innerHTML =
            "<strong>Quiz passed!</strong> You scored " +
            score +
            " of " +
            total +
            ". Module 3 is complete.";
          quizBtn.textContent = "Quiz Passed";
          quizBtn.disabled = true;
          quizBtn.setAttribute("aria-disabled", "true");
        } else {
          quizResult.innerHTML =
            "<strong>Not quite.</strong> You scored " +
            score +
            " of " +
            total +
            '. Review the lesson and try again. <button type="button" class="btn btn-primary course-quiz-result__retry">Retry Quiz</button>';
          quizResult.querySelector(".course-quiz-result__retry").addEventListener("click", function () {
            quizForm.reset();
            quizForm.hidden = false;
            quizResult.hidden = true;
          });
        }
      });
    }
  }

  /**
   * Course catalog — category filters, sort, search
   */
  function initCatalogFilters() {
    var catalog = document.querySelector("[data-course-catalog]");
    if (!catalog) return;

    var cards = Array.from(catalog.querySelectorAll(".course-card--catalog"));
    var filterGroup = document.querySelector("[data-catalog-filter]");
    var pills = filterGroup ? filterGroup.querySelectorAll(".catalog-filter__pill") : [];
    var sortSelect = document.getElementById("course-sort");
    var searchForm = document.querySelector(".course-banner__search-form");
    var searchInput = document.querySelector(".course-banner__search-input");
    var emptyEl = document.querySelector("[data-course-catalog-empty]");
    var pagination = document.querySelector("[data-course-pagination]");

    var currentFilter = "all";
    var currentSearch = "";
    var currentPage = 1;
    var perPage = 2;

    cards.forEach(function (card, index) {
      card.dataset.popular = String(index + 1);

      if (!card.dataset.price) {
        var priceEl = card.querySelector(".course-card__price");
        if (priceEl) {
          card.dataset.price = priceEl.textContent.replace(/[^0-9.]/g, "") || "0";
        }
      }

      if (!card.dataset.ceHours) {
        var badgeEl = card.querySelector(".course-card__badge");
        if (badgeEl) {
          card.dataset.ceHours = badgeEl.textContent.replace(/[^0-9.]/g, "") || "0";
        }
      }
    });

    function cardMatches(card) {
      var category = card.dataset.category || "";
      var matchCategory = currentFilter === "all" || category === currentFilter;

      if (!currentSearch) return matchCategory;

      var titleEl = card.querySelector(".course-card__title");
      var textEl = card.querySelector(".card__text");
      var title = titleEl ? titleEl.textContent.toLowerCase() : "";
      var text = textEl ? textEl.textContent.toLowerCase() : "";
      var matchSearch = title.indexOf(currentSearch) !== -1 || text.indexOf(currentSearch) !== -1;

      return matchCategory && matchSearch;
    }

    function sortCards(list) {
      var sortValue = sortSelect ? sortSelect.value : "popular";
      var sorted = list.slice();

      sorted.sort(function (a, b) {
        if (sortValue === "price-asc") {
          return parseFloat(a.dataset.price || 0) - parseFloat(b.dataset.price || 0);
        }

        if (sortValue === "ce-hours") {
          return parseFloat(b.dataset.ceHours || 0) - parseFloat(a.dataset.ceHours || 0);
        }

        return parseFloat(a.dataset.popular || 0) - parseFloat(b.dataset.popular || 0);
      });

      return sorted;
    }

    function getPageNumbers(totalPages, page) {
      if (totalPages <= 5) {
        var all = [];
        for (var i = 1; i <= totalPages; i += 1) {
          all.push(i);
        }
        return all;
      }

      var items = [1];

      if (page > 3) {
        items.push("...");
      }

      var start = Math.max(2, page - 1);
      var end = Math.min(totalPages - 1, page + 1);

      for (var p = start; p <= end; p += 1) {
        items.push(p);
      }

      if (page < totalPages - 2) {
        items.push("...");
      }

      items.push(totalPages);
      return items;
    }

    function renderPagination(totalItems) {
      if (!pagination) return;

      var totalPages = Math.max(1, Math.ceil(totalItems / perPage));

      if (currentPage > totalPages) {
        currentPage = totalPages;
      }

      pagination.innerHTML = "";

      if (totalItems === 0 || totalPages <= 1) {
        pagination.hidden = true;
        return;
      }

      pagination.hidden = false;

      var prevBtn = document.createElement("button");
      prevBtn.type = "button";
      prevBtn.className = "pagination__item";
      prevBtn.innerHTML = "&laquo;";
      prevBtn.setAttribute("aria-label", "Previous page");

      if (currentPage <= 1) {
        prevBtn.classList.add("pagination__item--disabled");
        prevBtn.disabled = true;
      } else {
        prevBtn.addEventListener("click", function () {
          currentPage -= 1;
          renderCatalog(false);
        });
      }

      pagination.appendChild(prevBtn);

      getPageNumbers(totalPages, currentPage).forEach(function (item) {
        if (item === "...") {
          var ellipsis = document.createElement("span");
          ellipsis.className = "pagination__item pagination__item--disabled";
          ellipsis.setAttribute("aria-hidden", "true");
          ellipsis.textContent = "...";
          pagination.appendChild(ellipsis);
          return;
        }

        var pageBtn = document.createElement("button");
        pageBtn.type = "button";
        pageBtn.className = "pagination__item";
        pageBtn.textContent = String(item);

        if (item === currentPage) {
          pageBtn.classList.add("pagination__item--active");
          pageBtn.setAttribute("aria-current", "page");
        } else {
          pageBtn.addEventListener("click", function () {
            currentPage = item;
            renderCatalog(false);
          });
        }

        pagination.appendChild(pageBtn);
      });

      var nextBtn = document.createElement("button");
      nextBtn.type = "button";
      nextBtn.className = "pagination__item";
      nextBtn.innerHTML = "&raquo;";
      nextBtn.setAttribute("aria-label", "Next page");

      if (currentPage >= totalPages) {
        nextBtn.classList.add("pagination__item--disabled");
        nextBtn.disabled = true;
      } else {
        nextBtn.addEventListener("click", function () {
          currentPage += 1;
          renderCatalog(false);
        });
      }

      pagination.appendChild(nextBtn);
    }

    function renderCatalog(resetPage) {
      if (resetPage !== false) {
        currentPage = 1;
      }

      var matched = sortCards(cards.filter(cardMatches));
      var start = (currentPage - 1) * perPage;
      var pageItems = matched.slice(start, start + perPage);

      cards.forEach(function (card) {
        card.hidden = true;
      });

      pageItems.forEach(function (card) {
        card.hidden = false;
        catalog.appendChild(card);
      });

      if (emptyEl) {
        emptyEl.hidden = matched.length > 0;
      }

      renderPagination(matched.length);
    }

    pills.forEach(function (pill) {
      pill.addEventListener("click", function () {
        var filterValue = pill.dataset.filter || "all";

        pills.forEach(function (btn) {
          btn.classList.remove("catalog-filter__pill--active");
        });

        pill.classList.add("catalog-filter__pill--active");
        currentFilter = filterValue;
        renderCatalog();
      });
    });

    if (sortSelect) {
      sortSelect.addEventListener("change", renderCatalog);
    }

    if (searchForm && searchInput) {
      searchForm.addEventListener("submit", function (e) {
        e.preventDefault();
        currentSearch = searchInput.value.trim().toLowerCase();
        renderCatalog();
      });

      searchInput.addEventListener("input", function () {
        if (!searchInput.value.trim()) {
          currentSearch = "";
          renderCatalog();
        }
      });
    }

    renderCatalog();
  }

  /**
   * Admin mockup sidebar panel switcher
   * Container: [data-admin-mockup]
   * Nav links: [data-admin-nav="panel-id"]
   * Panels: [data-admin-panel="panel-id"]
   */
  function initAdminMockup() {
    var layout = document.querySelector("[data-admin-mockup]");
    if (!layout) return;

    var links = layout.querySelectorAll("[data-admin-nav]");
    var panels = layout.querySelectorAll("[data-admin-panel]");
    if (!links.length || !panels.length) return;

    function showPanel(panelId) {
      links.forEach(function (link) {
        var isActive = link.dataset.adminNav === panelId;
        link.classList.toggle("admin-mockup__nav-link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });

      panels.forEach(function (panel) {
        var isActive = panel.dataset.adminPanel === panelId;
        panel.hidden = !isActive;
      });
    }

    links.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var panelId = link.dataset.adminNav;
        if (panelId) {
          showPanel(panelId);
        }
      });
    });
  }

  /**
   * Admin mockup shortcode copy buttons
   */
  function initAdminShortcodeCopy() {
    document.querySelectorAll(".shortcode-card__copy").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var targetId = btn.getAttribute("data-copy");
        var codeEl = document.getElementById(targetId);
        if (!codeEl) return;

        var text = codeEl.textContent;

        function showCopied() {
          var original = btn.textContent;
          btn.textContent = "Copied!";
          btn.classList.add("shortcode-card__copy--copied");
          setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove("shortcode-card__copy--copied");
          }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(showCopied);
        } else {
          var range = document.createRange();
          range.selectNodeContents(codeEl);
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
          showCopied();
        }
      });
    });
  }

  /**
   * Admin mockup settings save (mock)
   */
  function initAdminSettings() {
    document.querySelectorAll(".admin-settings-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type="submit"]');
        if (btn) {
          var originalText = btn.textContent;
          btn.textContent = "Saved!";
          btn.disabled = true;
          setTimeout(function () {
            btn.textContent = originalText;
            btn.disabled = false;
          }, 2000);
        }
      });
    });
  }

  function initCourseReviewForm() {
    document.querySelectorAll(".course-review-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        var btn = form.querySelector('[type="submit"]');
        if (btn) {
          var originalText = btn.textContent;
          btn.textContent = "Submitted!";
          btn.disabled = true;
          setTimeout(function () {
            btn.textContent = originalText;
            btn.disabled = false;
            form.reset();
          }, 2000);
        }
      });
    });
  }

  function initContactForm() {
    var form = document.getElementById("contact-form");
    var notice = document.getElementById("contact-form-notice");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      if (notice) {
        notice.hidden = false;
        notice.textContent = "Thank you! Your message has been sent. We'll respond within 1 business day.";
      }

      var btn = form.querySelector('[type="submit"]');
      if (btn) {
        var originalText = btn.textContent;
        btn.textContent = "Message Sent!";
        btn.disabled = true;
        setTimeout(function () {
          btn.textContent = originalText;
          btn.disabled = false;
          form.reset();
        }, 2500);
      }
    });
  }

  /**
   * FAQ page category tab filter
   * Container: [data-faq-page]
   * Tabs: [data-faq-filter="all" | category id]
   * Groups: [data-category="category-id"]
   */
  function initFaqFilters() {
    const page = document.querySelector("[data-faq-page]");
    if (!page) return;

    const tabButtons = page.querySelectorAll("[data-faq-filter]");
    const groups = page.querySelectorAll("[data-category]");

    if (!tabButtons.length || !groups.length) return;

    function applyFilter(filter) {
      groups.forEach(function (group) {
        const show = filter === "all" || group.dataset.category === filter;
        group.hidden = !show;
      });
    }

    tabButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        const filter = button.dataset.faqFilter;
        if (!filter) return;

        tabButtons.forEach(function (btn) {
          btn.classList.remove("tabs__tab--active");
          btn.setAttribute("aria-selected", "false");
        });

        button.classList.add("tabs__tab--active");
        button.setAttribute("aria-selected", "true");
        applyFilter(filter);
      });
    });
  }

  /**
   * Policies page sticky sidebar — active section highlight
   * Container: [data-policies-page]
   * Nav links: [data-policies-nav]
   * Sections: .legal-section[id]
   */
  function initPoliciesNav() {
    const page = document.querySelector("[data-policies-page]");
    if (!page) return;

    const navLinks = page.querySelectorAll("[data-policies-nav]");
    const sections = page.querySelectorAll(".legal-section[id]");

    if (!navLinks.length || !sections.length) return;

    const visibility = {};

    function setActive(id) {
      navLinks.forEach(function (link) {
        const isActive = link.getAttribute("href") === "#" + id;
        link.classList.toggle("policies-sidebar__link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "true");
        } else {
          link.removeAttribute("aria-current");
        }
      });
    }

    sections.forEach(function (section) {
      visibility[section.id] = false;
    });

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          visibility[entry.target.id] = entry.isIntersecting;
        });

        for (var i = 0; i < sections.length; i++) {
          if (visibility[sections[i].id]) {
            setActive(sections[i].id);
            return;
          }
        }
      },
      {
        rootMargin: "-15% 0px -60% 0px",
        threshold: 0
      }
    );

    sections.forEach(function (section) {
      observer.observe(section);
    });

    navLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        const id = link.getAttribute("href").slice(1);
        if (id) setActive(id);
      });
    });

    if (window.location.hash) {
      const hashId = window.location.hash.slice(1);
      const match = page.querySelector("#" + hashId);
      if (match) setActive(hashId);
    }
  }

  /**
   * CE certificate download (mock PDF for static prototype)
   */
  function initCertificateDownload() {
    document.querySelectorAll("[data-certificate-download]").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();

        var user = getCurrentUser();
        var course = btn.getAttribute("data-certificate-course") || "CE Course";
        var certId = btn.getAttribute("data-certificate-id") || "CTA-CERT";
        var hours = btn.getAttribute("data-certificate-hours") || "0";
        var date = btn.getAttribute("data-certificate-date") || new Date().toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric"
        });
        var recipient = user ? user.fullName : "Certificate Recipient";
        var license = user ? getUserLicenseLabel(user) : "Licensed Professional";

        var html = [
          "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">",
          "<title>CE Certificate " + certId + "</title>",
          "<style>",
          "body{font-family:Georgia,serif;max-width:720px;margin:48px auto;padding:40px;border:3px solid #122B51;color:#122B51;}",
          "h1{font-size:28px;text-align:center;margin:0 0 8px;}",
          "p{text-align:center;margin:8px 0;}",
          ".meta{margin-top:32px;font-size:14px;line-height:1.7;}",
          ".footer{margin-top:40px;font-size:12px;color:#666;text-align:center;}",
          "</style></head><body>",
          "<h1>Certificate of Completion</h1>",
          "<p>Clinical Training &amp; Supervision Academy</p>",
          "<p><strong>" + recipient + "</strong></p>",
          "<p>has successfully completed</p>",
          "<p><strong>" + course + "</strong></p>",
          "<div class=\"meta\">",
          "<p>Certificate ID: " + certId + "</p>",
          "<p>Issue Date: " + date + "</p>",
          "<p>CE Hours: " + hours + "</p>",
          "<p>Credential: " + license + "</p>",
          "<p>California BBS Approved Provider</p>",
          "</div>",
          "<p class=\"footer\">This is a prototype certificate for demonstration purposes.</p>",
          "</body></html>"
        ].join("");

        var blob = new Blob([html], { type: "text/html;charset=utf-8" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = certId + "-Certificate.html";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        var originalHtml = btn.innerHTML;
        btn.innerHTML = "Downloaded!";
        btn.classList.add("btn--downloaded");
        setTimeout(function () {
          btn.innerHTML = originalHtml;
          btn.classList.remove("btn--downloaded");
        }, 2000);
      });
    });
  }

  /**
   * Password show/hide toggle (eye icon)
   * Wrapper: [data-password-field]
   */
  function initPasswordToggle() {
    document.querySelectorAll("[data-password-field]").forEach(function (wrap) {
      var input = wrap.querySelector(".form-password__input");
      var btn = wrap.querySelector(".form-password__toggle");
      if (!input || !btn) return;

      btn.addEventListener("click", function () {
        var isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        btn.classList.toggle("form-password__toggle--visible", isHidden);
        btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
        btn.setAttribute("aria-pressed", isHidden ? "true" : "false");
      });
    });
  }

  /**
   * Login / Register page (mock auth for static prototype)
   */
  function initAuthForms() {
    var loginForm = document.getElementById("login-form");
    var registerForm = document.getElementById("register-form");
    var toggleButtons = document.querySelectorAll("[data-auth-toggle]");
    var loginError = document.getElementById("login-form-error");
    var registerError = document.getElementById("register-form-error");

    if (!loginForm && !registerForm) return;

    function showAuthError(el, message) {
      if (!el) return;
      if (!message) {
        el.hidden = true;
        el.textContent = "";
        return;
      }
      el.textContent = message;
      el.hidden = false;
    }

    function showLogin() {
      if (!loginForm || !registerForm) return;
      loginForm.classList.remove("form-hidden");
      loginForm.removeAttribute("hidden");
      registerForm.classList.add("form-hidden");
      registerForm.setAttribute("hidden", "");
      document.title = "Log In | Clinical Training and Supervision Academy";
      showAuthError(registerError, "");
    }

    function showRegister() {
      if (!loginForm || !registerForm) return;
      registerForm.classList.remove("form-hidden");
      registerForm.removeAttribute("hidden");
      loginForm.classList.add("form-hidden");
      loginForm.setAttribute("hidden", "");
      document.title = "Create Account | Clinical Training and Supervision Academy";
      showAuthError(loginError, "");
    }

    toggleButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var target = button.getAttribute("data-auth-toggle");
        if (target === "register") {
          showRegister();
        } else {
          showLogin();
        }
      });
    });

    if (loginForm) {
      loginForm.addEventListener("submit", function (e) {
        e.preventDefault();
        showAuthError(loginError, "");

        if (!loginForm.checkValidity()) {
          loginForm.reportValidity();
          return;
        }

        var identifierField = loginForm.querySelector('[name="email"], [name="username"]');
        var identifier = identifierField ? identifierField.value : "";
        var password = loginForm.querySelector('[name="password"]').value;
        var result = loginUser(identifier, password);

        if (!result.ok) {
          showAuthError(loginError, result.message);
          return;
        }

        var btn = loginForm.querySelector('[type="submit"]');
        if (btn) {
          btn.textContent = "Logging in...";
          btn.disabled = true;
        }

        setTimeout(function () {
          window.location.href = "dashboard-ce.html";
        }, 400);
      });
    }

    if (registerForm) {
      registerForm.addEventListener("submit", function (e) {
        e.preventDefault();
        showAuthError(registerError, "");

        if (!registerForm.checkValidity()) {
          registerForm.reportValidity();
          return;
        }

        var password = registerForm.querySelector('[name="password"]').value;
        var confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;

        if (password !== confirmPassword) {
          showAuthError(registerError, "Passwords do not match. Please try again.");
          return;
        }

        var result = registerUser({
          fullName: registerForm.querySelector('[name="full_name"]').value,
          email: registerForm.querySelector('[name="email"]').value,
          password: password,
          userType: registerForm.querySelector('[name="user_type"]').value
        });

        if (!result.ok) {
          showAuthError(registerError, result.message);
          return;
        }

        var btn = registerForm.querySelector('[type="submit"]');
        if (btn) {
          btn.textContent = "Creating account...";
          btn.disabled = true;
        }

        setTimeout(function () {
          window.location.href = "dashboard-ce.html";
        }, 600);
      });
    }
  }

  function init() {
    initMobileMenu();
    initAccordion();
    initTabs();
    initFaqFilters();
    initPoliciesNav();
    initPasswordToggle();
    initAuthForms();
    initDashboardUser();
    initCertificateDownload();
    initDashboardNav();
    initDashboardSettings();
    initCoursePlayer();
    initCatalogFilters();
    initAdminMockup();
    initAdminShortcodeCopy();
    initAdminSettings();
    initCourseReviewForm();
    initContactForm();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
