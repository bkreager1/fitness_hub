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


    /* ---------------------------------------------------------
       4. Calorie tracker — calculate-then-save flow
          + US/metric unit toggle (mirrors the BMI calc).
       --------------------------------------------------------- */
    (function initCalorieCalc () {
        const form = document.getElementById('calorieForm');
        if (!form) return;

        const els = {
            unitSystem: document.getElementById('calUnitSystem'),
            toggle:     document.getElementById('calUnitToggle'),
            paneUs:     form.querySelector('[data-cal-pane="us"]'),
            paneMetric: form.querySelector('[data-cal-pane="metric"]'),
            // Inputs we read
            age:      document.getElementById('calAge'),
            activity: document.getElementById('calActivity'),
            ft:       document.getElementById('calHeightFt'),
            inch:     document.getElementById('calHeightIn'),
            lb:       document.getElementById('calWeightLb'),
            cm:       document.getElementById('calHeightCm'),
            kg:       document.getElementById('calWeightKg'),
            // Output
            preview:  document.getElementById('calPreview'),
            cut:      document.getElementById('calPrevCut'),
            maintain: document.getElementById('calPrevMaintenance'),
            bulk:     document.getElementById('calPrevBulk'),
            calcBtn:  document.getElementById('calCalculate'),
            saveBtn:  document.getElementById('calSave'),
        };

        // These mirror the server's CalorieController constants. If the
        // server-side numbers ever change, update them here too.
        const MULTIPLIERS = {
            sedentary:         1.2,
            lightly_active:    1.375,
            moderately_active: 1.55,
            very_active:       1.725,
            extra_active:      1.9,
        };
        const LB_PER_KG = 2.2046226218;
        const CM_PER_IN = 2.54;

        function unit () { return els.unitSystem.value; }

        function getGender () {
            const r = form.querySelector('input[name="gender"]:checked');
            return r ? r.value : '';
        }

        // Validate every input and return either {data} (canonical kg/cm)
        // or {errors} (per-field message list) so we can render inline
        // error text + aria-invalid the same way the server does.
        function validate () {
            const errors = [];
            const push = (field, msg) => errors.push({ field, msg });

            const age = parseInt(els.age.value, 10);
            if (!Number.isFinite(age) || age < 13 || age > 100) {
                push(els.age, 'Age must be between 13 and 100.');
            }

            const gender = getGender();
            if (gender !== 'male' && gender !== 'female') {
                // Attach to one of the radios — pushError() walks up to .field.
                push(form.querySelector('input[name="gender"]'), 'Please select a sex.');
            }

            const activity = els.activity.value;
            if (!MULTIPLIERS[activity]) {
                push(els.activity, 'Please choose an activity level.');
            }

            let kg = NaN, cm = NaN;
            if (unit() === 'us') {
                const ft   = parseInt(els.ft.value, 10);
                const inch = parseInt(els.inch.value || '0', 10);
                const lb   = parseFloat(els.lb.value);

                const ftOk = Number.isFinite(ft)   && ft   >= 3  && ft   <= 8;
                const inOk = Number.isFinite(inch) && inch >= 0  && inch <= 11;

                if (!ftOk || !inOk) {
                    // Visually mark ft as invalid too, but put the message
                    // under the inch field (mirrors the server-side layout).
                    push(els.ft, '');
                    push(els.inch, 'Enter a valid height (e.g. 5 ft 10 in).');
                } else {
                    cm = (ft * 12 + inch) * CM_PER_IN;
                }

                if (!Number.isFinite(lb) || lb < 66 || lb > 660) {
                    push(els.lb, 'Weight must be between 66 and 660 lbs.');
                } else {
                    kg = lb / LB_PER_KG;
                }
            } else {
                const cmI = parseFloat(els.cm.value);
                const kgI = parseFloat(els.kg.value);

                if (!Number.isFinite(cmI) || cmI < 100 || cmI > 250) {
                    push(els.cm, 'Height must be between 100 and 250 cm.');
                } else {
                    cm = cmI;
                }

                if (!Number.isFinite(kgI) || kgI < 30 || kgI > 300) {
                    push(els.kg, 'Weight must be between 30 and 300 kg.');
                } else {
                    kg = kgI;
                }
            }

            if (errors.length) return { errors };
            return { data: { age, gender, activity, kg, cm } };
        }

        // Drop a <p class="field-error"> under the field's container and
        // mark every input/select/textarea inside as aria-invalid. If msg
        // is empty, only the visual aria-invalid mark is added (used for
        // the ft+in pair where the message lives under inch).
        function pushFieldError (field, msg) {
            if (!field) return;
            const container = field.closest('.field');
            if (!container) return;

            if (msg && !container.querySelector('p[data-js-error]')) {
                const p = document.createElement('p');
                p.className = 'field-error';
                p.dataset.jsError = '1';
                p.textContent = msg;
                container.appendChild(p);
            }
            container.querySelectorAll('input, select, textarea').forEach((el) => {
                if (!el.hasAttribute('aria-invalid')) {
                    el.setAttribute('aria-invalid', 'true');
                    el.dataset.jsInvalid = '1';
                }
            });
        }

        // Remove every JS-injected error + clear JS-injected aria-invalids.
        // Server-rendered errors (no data-js-error flag) are left alone.
        function clearJsErrors () {
            form.querySelectorAll('p[data-js-error]').forEach((el) => el.remove());
            form.querySelectorAll('[data-js-invalid]').forEach((el) => {
                el.removeAttribute('aria-invalid');
                delete el.dataset.jsInvalid;
            });
        }

        // Mifflin-St Jeor + activity multiplier → three integer targets.
        function compute (d) {
            const bmr = 10 * d.kg + 6.25 * d.cm - 5 * d.age
                      + (d.gender === 'male' ? 5 : -161);
            const maintenance = Math.round(bmr * MULTIPLIERS[d.activity]);
            const cutting     = Math.max(1200, maintenance - 500);
            const bulking     = maintenance + 500;
            return { cutting, maintenance, bulking };
        }

        const fmtCals = (n) => n.toLocaleString('en-US');

        function showPreview (r) {
            els.cut.textContent      = fmtCals(r.cutting);
            els.maintain.textContent = fmtCals(r.maintenance);
            els.bulk.textContent     = fmtCals(r.bulking);
            els.preview.hidden       = false;
            els.saveBtn.hidden       = false;
            els.calcBtn.hidden       = true;   // Save replaces Calculate
        }

        function hidePreview () {
            els.preview.hidden = true;
            els.saveBtn.hidden = true;
            els.calcBtn.hidden = false;        // Calculate returns
        }

        // ----- Calculate button --------------------------------------
        els.calcBtn.addEventListener('click', () => {
            clearJsErrors();
            const result = validate();
            if (result.errors) {
                result.errors.forEach((e) => pushFieldError(e.field, e.msg));
                els.calcBtn.classList.add('is-shake');
                setTimeout(() => els.calcBtn.classList.remove('is-shake'), 400);
                hidePreview();
                return;
            }
            showPreview(compute(result.data));
        });

        // ----- Edits invalidate the preview + clear stale JS errors --
        // Once the user starts editing, any inline error from the last
        // Calculate attempt is no longer accurate, so wipe them.
        form.addEventListener('input',  () => {
            clearJsErrors();
            if (!els.saveBtn.hidden) hidePreview();
        });
        form.addEventListener('change', () => {
            clearJsErrors();
            if (!els.saveBtn.hidden) hidePreview();
        });

        // ----- Unit toggle -------------------------------------------
        function convertUnits (from, to) {
            if (from === to) return;
            if (from === 'us' && to === 'metric') {
                const ft   = parseFloat(els.ft.value);
                const inch = parseFloat(els.inch.value);
                const lb   = parseFloat(els.lb.value);
                if (!isNaN(ft) || !isNaN(inch)) {
                    const totalIn = (isNaN(ft) ? 0 : ft) * 12
                                  + (isNaN(inch) ? 0 : inch);
                    if (totalIn > 0) els.cm.value = (totalIn * CM_PER_IN).toFixed(1);
                }
                if (!isNaN(lb) && lb > 0) {
                    els.kg.value = (lb / LB_PER_KG).toFixed(1);
                }
            } else {
                const cm = parseFloat(els.cm.value);
                const kg = parseFloat(els.kg.value);
                if (!isNaN(cm) && cm > 0) {
                    const totalIn = cm / CM_PER_IN;
                    let ft   = Math.floor(totalIn / 12);
                    let inch = Math.round(totalIn - ft * 12);
                    if (inch === 12) { ft += 1; inch = 0; }
                    els.ft.value   = String(ft);
                    els.inch.value = String(inch);
                }
                if (!isNaN(kg) && kg > 0) {
                    els.lb.value = (kg * LB_PER_KG).toFixed(1);
                }
            }
        }

        if (els.toggle) {
            els.toggle.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-unit]');
                if (!btn) return;
                const newUnit = btn.dataset.unit;
                if (newUnit === unit()) return;

                convertUnits(unit(), newUnit);
                els.unitSystem.value = newUnit;

                els.toggle.querySelectorAll('button').forEach((b) => {
                    const on = (b === btn);
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                els.paneUs.hidden     = (newUnit !== 'us');
                els.paneMetric.hidden = (newUnit !== 'metric');
                clearJsErrors();   // Errors from old pane don't apply.
                hidePreview();
            });
        }
    })();


    /* ---------------------------------------------------------
       5. Targets card — show/hide stats form toggle.
          Lets the user re-open the calc form after the targets
          card is showing read-only numbers.
       --------------------------------------------------------- */
    (function initTargetsToggle () {
        const btn  = document.getElementById('targetsToggle');
        const wrap = document.getElementById('statsFormWrap');
        if (!btn || !wrap) return;

        btn.addEventListener('click', () => {
            const willOpen = wrap.hidden;   // currently closed → opening
            wrap.hidden = !willOpen;
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            btn.textContent = willOpen
                ? (btn.dataset.labelOpen   || 'Cancel')
                : (btn.dataset.labelClosed || 'Update my stats');

            if (willOpen) {
                // Scroll the form into view so the user sees what just unfolded.
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                // Focus the first non-readonly input for keyboard users.
                const firstInput = wrap.querySelector('input:not([type="hidden"]), select');
                if (firstInput) firstInput.focus({ preventScroll: true });
            }
        });
    })();


    /* ---------------------------------------------------------
       6. Intake chart (Chart.js).
          Bars for daily calorie intake, with horizontal lines
          overlaid for cut / maintenance / bulk targets when the
          user has them. Reads JSON from the canvas data attrs.
       --------------------------------------------------------- */
    (function initIntakeChart () {
        const canvas = document.getElementById('intakeChart');
        if (!canvas) return;
        if (typeof Chart === 'undefined') return;   // CDN failed to load

        let rows, targets;
        try {
            rows    = JSON.parse(canvas.dataset.rows    || '[]');
            targets = JSON.parse(canvas.dataset.targets || 'null');
        } catch (e) {
            return;
        }
        if (!Array.isArray(rows) || rows.length === 0) return;

        // Active goal: 'cut' | 'maintain' | 'bulk' | (default 'maintain').
        // The matching line gets bold + solid; the other two stay dashed
        // and thin so they read as background context.
        const activeGoal = canvas.dataset.activeGoal || 'maintain';
        // Lines are keyed by 'cut'|'maintenance'|'bulk' (the column-style
        // names) but the goal uses 'maintain'. Normalize:
        const activeKey = activeGoal === 'maintain' ? 'maintenance' : activeGoal;

        const css  = getComputedStyle(document.documentElement);
        const text = (css.getPropertyValue('--text-dim') || '#a4a8b5').trim();
        const grid = 'rgba(148, 163, 184, 0.10)';

        const labels = rows.map((r) => {
            const d = new Date(r.date + 'T00:00:00');
            return isNaN(d) ? r.date
                : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const datasets = [
            {
                type: 'bar',
                label: 'Intake',
                data: rows.map((r) => r.calories),
                backgroundColor: 'rgba(255, 122, 26, 0.55)',
                borderColor: '#ff7a1a',
                borderWidth: 1,
                borderRadius: 6,
                order: 2,
            },
        ];

        // Draw target lines on top of the bars when we know them. Each is
        // a constant-y line across all labels — Chart.js renders that
        // cleanly and the legend lets users toggle individual lines.
        // The active-goal line gets a thick solid stroke; the others
        // are thinner + dashed so they read as background context.
        if (targets) {
            const flatLine = (key, label, value, color) => {
                const isActive = key === activeKey;
                return {
                    type: 'line',
                    label: `${label} (${value.toLocaleString()})`,
                    data: labels.map(() => value),
                    borderColor: color,
                    backgroundColor: 'transparent',
                    borderWidth: isActive ? 3 : 1.5,
                    borderDash: isActive ? [] : [4, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0,
                    fill: false,
                    order: 1,
                };
            };
            datasets.push(flatLine('cut',         'Cut',         targets.cut,         '#60a5fa'));
            datasets.push(flatLine('maintenance', 'Maintenance', targets.maintenance, '#fb923c'));
            datasets.push(flatLine('bulk',        'Bulk',        targets.bulk,        '#34d399'));
        }

        new Chart(canvas, {
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: text } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()} cal`,
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: text }, grid: { color: grid } },
                    y: {
                        ticks: { color: text, callback: (v) => v.toLocaleString() },
                        grid:  { color: grid },
                        beginAtZero: true,
                    },
                },
            },
        });
    })();
})();
