/**
 * Synaptik Theme — script.js
 * Reading progress bar (single content pages only).
 */

(function () {
    'use strict';

    // Only inject on single content pages — not on list pages or homepage
    if (document.body.classList.contains('is-list') ||
        document.body.classList.contains('is-home')) {
        return;
    }

    var progressWrap = document.createElement('div');
    progressWrap.className = 'mono-progress';
    progressWrap.setAttribute('aria-hidden', 'true');
    progressWrap.innerHTML =
        '<div class="mono-progress-fill" id="mono-pf"></div>' +
        '<div class="mono-progress-thumb" id="mono-pt">' +
        '<span class="mono-progress-bubble" id="mono-pl">0%</span>' +
        '</div>';
    document.body.appendChild(progressWrap);

    var progressFill  = document.getElementById('mono-pf');
    var progressThumb = document.getElementById('mono-pt');
    var progressLabel = document.getElementById('mono-pl');
    var ticking       = false;

    function updateProgress() {
        var docHeight = document.documentElement.scrollHeight - window.innerHeight;
        var pct = docHeight > 0 ? Math.min(100, (window.scrollY / docHeight) * 100) : 0;

        progressFill.style.width  = pct + '%';
        progressThumb.style.left  = pct + '%';
        progressLabel.textContent = Math.round(pct) + '%';

        progressWrap.classList.toggle('is-visible', pct > 0);
        progressWrap.classList.toggle('mono-progress-completed', pct >= 98);
    }

    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            updateProgress();
            ticking = false;
        });
    }, { passive: true });

    // Initial state
    updateProgress();

}());