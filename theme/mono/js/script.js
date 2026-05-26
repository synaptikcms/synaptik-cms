/**
 * Mono Theme — mono.js
 * Burger menu, nav active state, sidebar centering animation, reading progress bar.
 */

(function() {
    'use strict';

    const burger = document.getElementById('mono-burger');
    const sidebar = document.getElementById('mono-sidebar');
    const overlay = document.getElementById('mono-overlay');
    const sidebarInner = document.querySelector('.sidebar-inner');

    // ── Burger menu ────────────────────────────────────────────

    if (burger && sidebar && overlay) {

        function openMenu() {
            sidebar.classList.add('is-open');
            overlay.classList.add('is-visible');
            burger.classList.add('is-open');
            burger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-visible');
            burger.classList.remove('is-open');
            burger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', function() {
            sidebar.classList.contains('is-open') ? closeMenu() : openMenu();
        });

        overlay.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeMenu();
                burger.focus();
            }
        });
    }

    // ── Active nav link ────────────────────────────────────────

    if (sidebar) {
        const currentPath = window.location.pathname;
        sidebar.querySelectorAll('.site-nav a').forEach(function(link) {
            const linkPath = new URL(link.href, window.location.origin).pathname;
            if (linkPath !== '/' && currentPath.startsWith(linkPath)) {
                link.classList.add('is-active');
            } else if (linkPath === '/' && currentPath === '/') {
                link.classList.add('is-active');
            }
        });
    }

    // ── Sidebar centering animation ────────────────────────────
    //
    // At scroll = 0: sidebar-inner is vertically centered in the 100vh sidebar.
    // As the user scrolls, the inner travels up toward its natural top position.
    // Once fully scrolled past the centering distance it stays put — no bounce.
    //
    // No CSS transition on this element: scroll-driven animations must be
    // frame-perfect or they feel laggy. We write the transform directly inside rAF.

    var centerOffset = 0; // px the inner needs to shift down from natural pos to appear centered

    // ── Sidebar centering animation ────────────────────────────
    
    var centerOffset  = 0;
    var currentShift  = 0; // the value actually applied to the transform
    var animFrameId   = null;
    
    function computeCenterOffset() {
        if (!sidebarInner || window.innerWidth <= 768) return 0;
        var naturalTop = sidebarInner.getBoundingClientRect().top;
        var innerH     = sidebarInner.offsetHeight;
        var viewH      = window.innerHeight;
        return Math.max(0, (viewH - innerH) / 2.5 - naturalTop);
    }
    
    function getTargetShift() {
        var scrollRange = (document.documentElement.scrollHeight - window.innerHeight) * 0.4;
        var progress    = scrollRange > 0 ? Math.min(1, window.scrollY / scrollRange) : 0;
        var eased       = 1 - Math.pow(1 - progress, 25);
        return eased * centerOffset;
    }
    
    function animateSidebar() {
        var target  = getTargetShift();
        // Lerp: current creeps toward target at 8% per frame — adjust for more/less inertia
        currentShift += (target - currentShift) * 0.08;
    
        // Snap to target when close enough to avoid infinite micro-updates
        if (Math.abs(target - currentShift) < 0.1) {
            currentShift = target;
            animFrameId  = null;
        } else {
            animFrameId = requestAnimationFrame(animateSidebar);
        }
    
        sidebarInner.style.transform = currentShift > 0.1
            ? 'translateY(' + currentShift + 'px)'
            : '';
    }
    
    function applySidebarShift() {
        if (!sidebarInner) return;
        if (window.innerWidth <= 768) {
            sidebarInner.style.transform = '';
            return;
        }
        // Kick off the animation loop if not already running
        if (!animFrameId) {
            animFrameId = requestAnimationFrame(animateSidebar);
        }
    }
    
    if (sidebarInner) {
        sidebarInner.style.willChange = 'transform';
        centerOffset = computeCenterOffset();
        applySidebarShift();
    
        window.addEventListener('resize', function () {
            centerOffset = computeCenterOffset();
            applySidebarShift();
        }, { passive: true });
    }

    // ── Reading progress bar ───────────────────────────────────
    //
    // Thin 5px line fixed at the bottom of the viewport.
    // A rounded bubble floats just above the current progress point, showing %.
    // Invisible at scroll = 0, fades in as soon as the user moves.
    // Only inject progress bar on single content pages
    if (!document.body.classList.contains('is-list') &&
        !document.body.classList.contains('is-home')) {
        // ── Reading progress bar ───────────────────────────────────
        var progressWrap = document.createElement('div');
        progressWrap.className = 'mono-progress';
        progressWrap.setAttribute('aria-hidden', 'true');
        progressWrap.innerHTML =
            '<div class="mono-progress-fill" id="mono-pf"></div>' +
            '<div class="mono-progress-thumb" id="mono-pt">' +
            '<span class="mono-progress-bubble" id="mono-pl">0%</span>' +
            '</div>';
        document.body.appendChild(progressWrap);

        var progressFill = document.getElementById('mono-pf');
        var progressThumb = document.getElementById('mono-pt');
        var progressLabel = document.getElementById('mono-pl');

        function updateProgress() {
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            var pct = docHeight > 0 ? Math.min(100, (window.scrollY / docHeight) * 100) : 0;

            progressFill.style.width = pct + '%';
            progressThumb.style.left = pct + '%';
            progressLabel.textContent = Math.round(pct) + '%';

            progressWrap.classList.toggle('is-visible', pct > 0);
            progressWrap.classList.toggle('mono-progress-completed', pct >= 98);
        }

        // ── Unified rAF-throttled scroll handler ───────────────────

        var ticking = false;

        window.addEventListener('scroll', function() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function() {
                applySidebarShift();
                updateProgress();
                ticking = false;
            });
        }, { passive: true });

        // Init — run once so the initial state is correct without waiting for a scroll event
        updateProgress();
    }
})();