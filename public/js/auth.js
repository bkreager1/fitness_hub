// ============================================================
// public/js/auth.js
// Tiny show/hide password toggle. Event-delegated so it works for
// any element with class .password-toggle and a data-target attribute
// that points at a password input's id.
// ============================================================
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.password-toggle');
        if (!btn) return;

        const input = document.getElementById(btn.dataset.target);
        if (!input) return;

        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        btn.textContent = hidden ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    });
})();
