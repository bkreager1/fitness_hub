// ============================================================
// public/js/main.js
// Phase 5 — site-wide JavaScript.
//
// Three small, independent modules:
//   1. Mobile nav toggle (hamburger)
//   2. Sticky header scroll-shadow
//   3. BMI calculator (imperial / metric, instant, no submit)
//
// Loaded sitewide via layouts/footer.php with `defer`, so the DOM is
// already parsed by the time this runs.
// ============================================================
(function () {
    'use strict';

    /* ---------------------------------------------------------
       1. Mobile nav toggle
       --------------------------------------------------------- */
    (function initNavToggle () {
        const btn = document.getElementById('navToggle');
        const nav = document.getElementById('siteNav');
        if (!btn || !nav) return;

        btn.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Close the menu when a nav link is tapped (mobile).
        nav.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                nav.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    })();


    /* ---------------------------------------------------------
       2. Sticky-header scroll shadow
       --------------------------------------------------------- */
    (function initHeaderScroll () {
        const header = document.getElementById('siteHeader');
        if (!header) return;

        const update = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 4);
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
    })();


    /* ---------------------------------------------------------
       3. BMI calculator
       --------------------------------------------------------- */
    (function initBmi () {
        const form = document.getElementById('bmiForm');
        if (!form) return;   // Not on the home page — bail.

        const valueEl = document.getElementById('bmiValue');
        const chipEl  = document.getElementById('bmiChip');
        const toggle  = document.querySelector('.unit-toggle');

        const ft  = document.getElementById('bmiFt');
        const inn = document.getElementById('bmiIn');
        const lbs = document.getElementById('bmiLbs');
        const cm  = document.getElementById('bmiCm');
        const kg  = document.getElementById('bmiKg');

        const panes = {
            imperial: form.querySelector('[data-bmi-pane="imperial"]'),
            metric:   form.querySelector('[data-bmi-pane="metric"]'),
        };

        let unit = 'imperial';

        // ----- Compute BMI for the active unit system -----------
        function computeBmi () {
            let bmi = NaN;

            if (unit === 'imperial') {
                const feet    = parseFloat(ft.value)  || 0;
                const inches  = parseFloat(inn.value) || 0;
                const pounds  = parseFloat(lbs.value);
                const totalIn = feet * 12 + inches;

                if (totalIn > 0 && pounds > 0) {
                    // Imperial BMI = (lbs / in²) × 703
                    bmi = (pounds / (totalIn * totalIn)) * 703;
                }
            } else {
                const heightCm = parseFloat(cm.value);
                const weightKg = parseFloat(kg.value);

                if (heightCm > 0 && weightKg > 0) {
                    const m = heightCm / 100;
                    bmi = weightKg / (m * m);
                }
            }
            return bmi;
        }

        // ----- Map a BMI number → category key + label ----------
        function classify (bmi) {
            if (!isFinite(bmi) || bmi <= 0) return { cat: 'none',   label: 'Enter your numbers' };
            if (bmi < 18.5)                 return { cat: 'under',  label: 'Underweight' };
            if (bmi < 25)                   return { cat: 'normal', label: 'Healthy range' };
            if (bmi < 30)                   return { cat: 'over',   label: 'Overweight' };
            return                                  { cat: 'obese',  label: 'Obese' };
        }

        // ----- Repaint the result card --------------------------
        function render () {
            const bmi = computeBmi();
            const { cat, label } = classify(bmi);

            valueEl.textContent = (cat === 'none') ? '—' : bmi.toFixed(1);
            chipEl.textContent  = label;
            chipEl.dataset.cat  = cat;
        }

        // ----- Wire input listeners -----------------------------
        form.addEventListener('input', render);

        // ----- Convert typed values when the user switches units
        // so they don't have to retype. Each field is converted
        // independently — blank source fields leave the target blank.
        const LB_PER_KG = 2.2046226218;
        const IN_PER_CM = 0.3937007874;

        function convertUnits (from, to) {
            if (from === to) return;

            if (from === 'imperial' && to === 'metric') {
                const feet   = parseFloat(ft.value);
                const inches = parseFloat(inn.value);
                const pounds = parseFloat(lbs.value);

                // Height — only convert if at least one of ft/in was filled
                if (!isNaN(feet) || !isNaN(inches)) {
                    const totalIn = (isNaN(feet) ? 0 : feet) * 12
                                  + (isNaN(inches) ? 0 : inches);
                    if (totalIn > 0) {
                        cm.value = (totalIn / IN_PER_CM).toFixed(1);
                    }
                }
                if (!isNaN(pounds) && pounds > 0) {
                    kg.value = (pounds / LB_PER_KG).toFixed(1);
                }
            } else { // metric -> imperial
                const heightCm = parseFloat(cm.value);
                const weightKg = parseFloat(kg.value);

                if (!isNaN(heightCm) && heightCm > 0) {
                    const totalIn = heightCm * IN_PER_CM;
                    let feet      = Math.floor(totalIn / 12);
                    let inches    = Math.round(totalIn - feet * 12);
                    // Edge case: 11.6 in rounds to 12 → roll over to next foot
                    if (inches === 12) { feet += 1; inches = 0; }
                    ft.value  = String(feet);
                    inn.value = String(inches);
                }
                if (!isNaN(weightKg) && weightKg > 0) {
                    lbs.value = (weightKg * LB_PER_KG).toFixed(1);
                }
            }
        }

        // ----- Wire unit-system toggle --------------------------
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-unit]');
                if (!btn) return;

                const newUnit = btn.dataset.unit;
                if (newUnit === unit) return;

                // Convert any typed values BEFORE we swap units
                convertUnits(unit, newUnit);
                unit = newUnit;

                // Visually mark the active tab
                toggle.querySelectorAll('button').forEach((b) => {
                    const on = (b === btn);
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });

                // Show the matching pane, hide the other
                panes.imperial.hidden = (unit !== 'imperial');
                panes.metric.hidden   = (unit !== 'metric');

                render();
            });
        }

        // First paint (clears stale autofill state, sets "—")
        render();
    })();
})();
