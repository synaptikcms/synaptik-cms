/**
 * Admin Sidebar — Collapsible + Flyout Submenu System
 *
 * Features:
 * - Collapsible sidebar with smooth animations
 * - Icon-only mode on desktop when collapsed
 * - Flyout panels for Articles and Settings on hover in BOTH expanded
 *   and collapsed/icon-only modes (panel moves to <body> to escape overflow clip)
 * - Inline accordion in expanded mode when section is active (CSS-driven)
 * - Full-screen overlay on mobile
 * - LocalStorage state persistence
 * - Keyboard: Escape closes open flyout
 */

(function () {
  'use strict';

  const CONFIG = {
    storageKey: 'synaptik_sidebar_state',
    mobileBreakpoint: 768,
    flyoutDelay: 120   // ms before hiding flyout after mouseleave (prevents flicker)
  };

  let state = {
    isExpanded: true,
    isMobile: false,
    isMobileMenuOpen: false,
    activeFlyout: null
  };

  let elements = {
    body: null,
    sidebar: null,
    adminContainer: null,
    hamburgerBtn: null,
    overlay: null
  };

  // Each flyout entry: { section, panel, trigger, hideTimer }
  const flyouts = [];

  // ─── Collapsible <details> persistence ───────────────────────────────────

  function initCollapsibles() {
    const KEY = 'synaptik_sidebar_sections';

    function getStored() {
      try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; }
    }
    function setStored(key, val) {
      try { var s = getStored(); s[key] = val; localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {}
    }

    var details = document.querySelectorAll('.sidebar-collapsible[data-key]');
    var restoring = true;
    var saved = getStored();

    details.forEach(function (el) {
      var key = el.getAttribute('data-key');
      if (Object.prototype.hasOwnProperty.call(saved, key) && saved[key] === false) {
        el.removeAttribute('open');
      }
    });
    restoring = false;

    details.forEach(function (el) {
      var key = el.getAttribute('data-key');
      el.addEventListener('toggle', function () {
        if (restoring) return;
        setStored(key, el.open);
      });
    });
  }

  // ─── Flyout system ───────────────────────────────────────────────────────

  /**
   * Move each .sidebar-flyout-panel to <body> (escapes sidebar overflow:hidden clip),
   * then wire hover events on both the trigger row and the panel itself.
   * Works in both expanded and collapsed sidebar states.
   */
  function initFlyouts() {
    var sections = elements.sidebar.querySelectorAll('.sidebar-has-flyout');

    sections.forEach(function (section) {
      var panel   = section.querySelector('[data-flyout-panel]');
      var trigger = section.querySelector('.sidebar-parent-link');

      if (!panel || !trigger) return;

      // Move panel out of sidebar so it is never clipped
      document.body.appendChild(panel);

      var entry = { section: section, panel: panel, trigger: trigger, hideTimer: null };
      flyouts.push(entry);

      // Show on mouseenter of the parent row
      section.addEventListener('mouseenter', function () {
        showFlyout(entry);
      });

      // Keep open while hovering the panel itself
      panel.addEventListener('mouseenter', function () {
        clearTimeout(entry.hideTimer);
      });

      // Schedule hide on mouseleave of the section row
      section.addEventListener('mouseleave', function () {
        scheduleHide(entry);
      });

      // Schedule hide when leaving the panel
      panel.addEventListener('mouseleave', function () {
        scheduleHide(entry);
      });

      // Escape key closes the active flyout
      panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hideFlyout(entry); trigger.focus(); }
      });
    });
  }

  /**
   * Position and make a flyout panel visible, anchored to its trigger row.
   * Called on mouseenter — works regardless of sidebar expanded/collapsed state.
   */
  function showFlyout(entry) {
    // In expanded sidebar, don't show flyout if section accordion is already open
    if (state.isExpanded && entry.section.classList.contains('is-open')) return;

    // Close any other open flyout first
    flyouts.forEach(function (other) {
      if (other !== entry) hideFlyout(other);
    });

    clearTimeout(entry.hideTimer);

    var sidebarRect = elements.sidebar.getBoundingClientRect();
    var triggerRect = entry.trigger.getBoundingClientRect();

    entry.panel.style.left = Math.round(sidebarRect.right + 4) + 'px';
    entry.panel.style.top  = Math.round(triggerRect.top) + 'px';

    entry.panel.classList.add('is-visible');
    entry.panel.setAttribute('aria-hidden', 'false');
    state.activeFlyout = entry.section.getAttribute('data-flyout');
  }

  function hideFlyout(entry) {
    clearTimeout(entry.hideTimer);
    entry.panel.classList.remove('is-visible');
    entry.panel.setAttribute('aria-hidden', 'true');
    if (state.activeFlyout === entry.section.getAttribute('data-flyout')) {
      state.activeFlyout = null;
    }
  }

  function scheduleHide(entry) {
    clearTimeout(entry.hideTimer);
    entry.hideTimer = setTimeout(function () { hideFlyout(entry); }, CONFIG.flyoutDelay);
  }

  function closeAllFlyouts() {
    flyouts.forEach(function (entry) { hideFlyout(entry); });
  }

  // ─── Core sidebar setup ──────────────────────────────────────────────────

  function init() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setup);
    } else {
      setup();
    }
  }

  function setup() {
    elements.body           = document.body;
    elements.sidebar        = document.querySelector('.sidebar');
    elements.adminContainer = document.querySelector('.admin-container');

    if (!elements.sidebar) {
      console.warn('Sidebar not found');
      return;
    }

    createHamburgerButton();
    createOverlay();
    // addTooltips(); // commented out — replaced by flyout panels

    loadState();
    checkViewport();
    applyState();
    setupEventListeners();

    elements.body.classList.add('sidebar-initialized');
    initCollapsibles();
    initFlyouts();
  }

  function createHamburgerButton() {
    const btn = document.createElement('button');
    btn.className = 'hamburger-btn';
    btn.setAttribute('aria-label', 'Toggle navigation menu');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('type', 'button');
    btn.innerHTML =
      '<span class="hamburger-line"></span>' +
      '<span class="hamburger-line"></span>' +
      '<span class="hamburger-line"></span>';
    const container = elements.adminContainer || elements.body;
    container.insertBefore(btn, container.firstChild);
    elements.hamburgerBtn = btn;
  }

  function createOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    const container = elements.adminContainer || elements.body;
    container.insertBefore(overlay, container.firstChild);
    elements.overlay = overlay;
  }

  // ─── State ───────────────────────────────────────────────────────────────

  function loadState() {
    try {
      const saved = localStorage.getItem(CONFIG.storageKey);
      if (saved) {
        const parsed = JSON.parse(saved);
        state.isExpanded = parsed.isExpanded !== undefined ? parsed.isExpanded : true;
      }
    } catch (e) {}
  }

  function saveState() {
    try {
      localStorage.setItem(CONFIG.storageKey, JSON.stringify({ isExpanded: state.isExpanded }));
    } catch (e) {}
  }

  function checkViewport() {
    const wasMobile = state.isMobile;
    state.isMobile = window.innerWidth <= CONFIG.mobileBreakpoint;
    if (wasMobile && !state.isMobile && state.isMobileMenuOpen) {
      state.isMobileMenuOpen = false;
    }
  }

  function applyState() {
    elements.body.classList.remove('sidebar-expanded', 'sidebar-collapsed', 'mobile-menu-open');

    if (elements.hamburgerBtn) {
      elements.hamburgerBtn.setAttribute(
        'aria-expanded',
        String(state.isMobile ? state.isMobileMenuOpen : state.isExpanded)
      );
    }

    if (state.isMobile) {
      if (state.isMobileMenuOpen) {
        elements.body.classList.add('mobile-menu-open');
        elements.overlay.classList.add('active');
        elements.body.style.overflow = 'hidden';
      } else {
        elements.overlay.classList.remove('active');
        elements.body.style.overflow = '';
      }
      closeAllFlyouts();
    } else {
      elements.body.classList.add(state.isExpanded ? 'sidebar-expanded' : 'sidebar-collapsed');
    }
  }

  function toggle() {
    elements.body.classList.add('sidebar-ready');
    if (state.isMobile) {
      state.isMobileMenuOpen = !state.isMobileMenuOpen;
    } else {
      state.isExpanded = !state.isExpanded;
      saveState();
    }
    applyState();
  }

  function closeMobileMenu() {
    if (state.isMobile && state.isMobileMenuOpen) {
      state.isMobileMenuOpen = false;
      applyState();
    }
  }

  function setupEventListeners() {
    elements.hamburgerBtn.addEventListener('click', function (e) {
      e.preventDefault();
      toggle();
    });

    elements.overlay.addEventListener('click', closeMobileMenu);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (state.activeFlyout) {
          closeAllFlyouts();
        } else if (state.isMobileMenuOpen) {
          closeMobileMenu();
        }
      }
    });

    let resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { checkViewport(); applyState(); }, 100);
    });

    elements.sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setTimeout(closeMobileMenu, 100); });
    });
  }

  // ─── Public API ──────────────────────────────────────────────────────────

  window.SynaptikSidebar = {
    toggle: toggle,
    expand:   function () { if (!state.isMobile) { state.isExpanded = true;  saveState(); applyState(); } },
    collapse: function () { if (!state.isMobile) { state.isExpanded = false; saveState(); applyState(); } },
    openMobileMenu:  function () { if (state.isMobile) { state.isMobileMenuOpen = true; applyState(); } },
    closeMobileMenu: closeMobileMenu,
    isExpanded:       function () { return state.isExpanded; },
    isMobile:         function () { return state.isMobile; },
    isMobileMenuOpen: function () { return state.isMobileMenuOpen; }
  };

  init();

})();
