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
       1b. Submit-in-flight feedback for every POST form.
       Disables the submit button + swaps its label so the user
       knows the click registered while the round-trip happens.
       Customize the label per-button with data-loading-text.
       --------------------------------------------------------- */
    (function initFormLoading () {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if ((form.method || '').toLowerCase() !== 'post') return;

            // The button that actually triggered the submit. submitter
            // is null for forms submitted via Enter on a single-field
            // form — fall back to the form's first submit button.
            const btn = e.submitter
                || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!btn || btn.disabled) return;
            // Opt-out for instant-feeling toggles (e.g. the calorie
            // goal picker pills) where the loading state adds friction
            // without value. Set data-no-loading on the button OR on
            // the form to skip the spinner + label swap.
            if (btn.dataset.noLoading != null
                || form.dataset.noLoading != null) return;

            const original = btn.innerHTML;
            const label    = btn.dataset.loadingText || 'Working…';

            // Defer the disable + label swap to the next tick. Disabling
            // the submit button synchronously inside the submit event
            // can cause some browsers to omit the button's name=value
            // pair from the POST body — which broke the calorie goal
            // picker (3 submit buttons in one form, distinguished by
            // value="cut" / "maintain" / "bulk"). The form has already
            // started serializing by the time setTimeout's callback
            // fires, so the original submitter's data is on its way.
            setTimeout(() => {
                btn.disabled = true;
                btn.classList.add('is-loading');
                btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> '
                              + label.replace(/[<>&]/g, (c) =>
                                  ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
            }, 0);

            // Safety net: if the page hasn't navigated within 10s
            // (network error, blocked navigation, etc.), restore the
            // button so the user can retry instead of being stuck.
            setTimeout(() => {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.innerHTML = original;
            }, 10000);
        });
    })();


    /* ---------------------------------------------------------
       1c. Auto-dismiss flash banners.
       Success messages stick around for ~4s, then slide out and
       remove themselves from the DOM. CSS handles the animation;
       on prefers-reduced-motion the global animation-duration
       override makes the dismissal effectively instant.
       --------------------------------------------------------- */
    (function initFlashDismiss () {
        const DISMISS_AFTER = 4000;

        document.querySelectorAll('.flash').forEach((el) => {
            setTimeout(() => {
                el.classList.add('is-dismissing');
                el.addEventListener('animationend', (e) => {
                    if (e.animationName === 'flashSlideOut') el.remove();
                }, { once: true });
            }, DISMISS_AFTER);
        });
    })();


    /* ---------------------------------------------------------
       2. Sticky-header scroll shadow + back-to-top button.
       Both reads of window.scrollY share a single rAF-throttled
       handler so we don't run two scroll listeners side-by-side.
       --------------------------------------------------------- */
    (function initScrollHandlers () {
        const header  = document.getElementById('siteHeader');
        const backBtn = document.getElementById('backToTop');

        // Show the back-to-top button after scrolling roughly one
        // viewport height — by then there's a real "way back up" to
        // offer, and on shorter pages the button never appears.
        const BACK_TO_TOP_THRESHOLD = 600;

        let queued = false;
        const update = () => {
            queued = false;
            const y = window.scrollY;
            if (header)  header.classList.toggle('is-scrolled', y > 4);
            if (backBtn) backBtn.classList.toggle('is-visible',
                                                  y > BACK_TO_TOP_THRESHOLD);
        };
        const onScroll = () => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(update);
        };
        update();
        window.addEventListener('scroll', onScroll, { passive: true });

        if (backBtn) {
            const reduceMotion = window.matchMedia(
                '(prefers-reduced-motion: reduce)').matches;
            backBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: reduceMotion ? 'auto' : 'smooth',
                });
            });
        }
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

        // Sign-up CTA only renders for logged-out users (the PHP guards
        // it). When the BMI is valid, slide it in; when there's no
        // result yet, hide it.
        const ctaEl = document.getElementById('bmiCta');

        // ----- Repaint the result card --------------------------
        function render () {
            const bmi = computeBmi();
            const { cat, label } = classify(bmi);

            valueEl.textContent = (cat === 'none') ? '—' : bmi.toFixed(1);
            chipEl.textContent  = label;
            chipEl.dataset.cat  = cat;

            if (ctaEl) ctaEl.hidden = (cat === 'none');
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


    /* ---------------------------------------------------------
       6b. Tracker history — collapsible day rows.
          PHP renders the table fully expanded so no-JS users
          still see all their entries. On load we hide the
          per-entry rows and inject a chevron-toggle button
          into each day-row's date cell so users can drill into
          a day when they want to edit or delete an individual
          entry.
          Used by both /calorie and /strength — selector is
          generic over any .history-table--days table, and any
          tr[data-day] that isn't itself a .day-row counts as
          a sub-row regardless of its class (.meal-row,
          .lift-row, etc.). The data-day-noun on the table
          tunes the aria-label wording.
       --------------------------------------------------------- */
    (function initHistoryDayToggles () {
        const tables = document.querySelectorAll('.history-table--days');
        tables.forEach((table) => {
            const dayRows = table.querySelectorAll('.day-row');
            if (dayRows.length === 0) return;

            const noun = table.dataset.dayNoun || 'entries';

            const setOpen = (day, open) => {
                table.querySelectorAll(`tr[data-day="${day}"]:not(.day-row)`)
                     .forEach((r) => { r.hidden = !open; });
            };

            dayRows.forEach((row) => {
                const day  = row.dataset.day;
                const cell = row.querySelector('.cell-date');
                if (!cell || !day) return;

                const dateText = cell.textContent.trim();

                const btn = document.createElement('button');
                btn.type           = 'button';
                btn.className      = 'day-toggle';
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-label', `Show ${noun} for ${dateText}`);
                btn.innerHTML =
                    '<span class="day-toggle__chevron" aria-hidden="true"></span>' +
                    '<span class="day-toggle__date"></span>';
                btn.querySelector('.day-toggle__date').textContent = dateText;

                cell.textContent = '';
                cell.appendChild(btn);

                // Start collapsed.
                setOpen(day, false);

                btn.addEventListener('click', () => {
                    const open = btn.getAttribute('aria-expanded') !== 'true';
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    btn.setAttribute('aria-label',
                        `${open ? 'Hide' : 'Show'} ${noun} for ${dateText}`);
                    setOpen(day, open);
                });
            });
        });
    })();


    /* ---------------------------------------------------------
       7. Weight tracker — form unit toggle (lbs ↔ kg).
          Used on /weight (new entry) and /weight/edit. Single
          weight input, toggle swaps unit + converts typed value.
       --------------------------------------------------------- */
    (function initWeightForm () {
        const form = document.getElementById('weightForm');
        if (!form) return;

        const toggle      = document.getElementById('weightUnitToggle');
        const unitInput   = document.getElementById('weightUnit');
        const weightInput = document.getElementById('weightInput');
        const unitLabel   = document.getElementById('weightUnitLabel');
        if (!toggle || !unitInput || !weightInput) return;

        const LB_PER_KG = 2.2046226218;
        const PLACEHOLDERS = { lbs: '175', kg: '79' };
        const BOUNDS = {
            lbs: { min: 66, max: 660 },
            kg:  { min: 30, max: 300 },
        };

        function applyUnitUI (unit) {
            unitInput.value           = unit;
            weightInput.placeholder   = PLACEHOLDERS[unit];
            weightInput.min           = BOUNDS[unit].min;
            weightInput.max           = BOUNDS[unit].max;
            if (unitLabel) unitLabel.textContent = `(${unit})`;

            toggle.querySelectorAll('button').forEach((b) => {
                const on = b.dataset.unit === unit;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }

        toggle.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-unit]');
            if (!btn) return;
            const newUnit = btn.dataset.unit;
            const oldUnit = unitInput.value;
            if (newUnit === oldUnit) return;

            // Convert the typed weight if there is one. Empty stays empty.
            const v = parseFloat(weightInput.value);
            if (!isNaN(v) && v > 0) {
                const converted = (oldUnit === 'lbs' && newUnit === 'kg')
                    ? v / LB_PER_KG
                    : v * LB_PER_KG;
                weightInput.value = converted.toFixed(1);
            }

            applyUnitUI(newUnit);
        });
    })();


    /* ---------------------------------------------------------
       8. Weight chart (Chart.js).
          Single-line trend chart. lbs/kg toggle re-renders the
          Y values from canonical kg without round-tripping the
          server.
       --------------------------------------------------------- */
    (function initWeightChart () {
        const canvas = document.getElementById('weightChart');
        if (!canvas) return;
        if (typeof Chart === 'undefined') return;

        let rows;
        try {
            rows = JSON.parse(canvas.dataset.rows || '[]');
        } catch (e) {
            return;
        }
        if (!Array.isArray(rows) || rows.length === 0) return;

        const LB_PER_KG = 2.2046226218;
        let displayUnit = canvas.dataset.defaultUnit || 'lbs';

        const css  = getComputedStyle(document.documentElement);
        const text = (css.getPropertyValue('--text-dim') || '#a4a8b5').trim();
        const grid = 'rgba(148, 163, 184, 0.10)';

        const labels = rows.map((r) => {
            const d = new Date(r.date + 'T00:00:00');
            return isNaN(d) ? r.date
                : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const valuesIn = (unit) => rows.map(
            (r) => unit === 'lbs' ? r.weight_kg * LB_PER_KG : r.weight_kg
        );

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: `Weight (${displayUnit})`,
                    data: valuesIn(displayUnit),
                    borderColor: '#ff7a1a',
                    backgroundColor: 'rgba(255, 122, 26, 0.18)',
                    borderWidth: 3,
                    tension: 0.25,
                    pointRadius: 4,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: text } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y.toFixed(1)} ${displayUnit}`,
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: text }, grid: { color: grid } },
                    y: {
                        ticks: { color: text, callback: (v) => v.toFixed(1) },
                        grid:  { color: grid },
                        beginAtZero: false,
                    },
                },
            },
        });

        const chartToggle = document.getElementById('weightChartUnitToggle');
        if (chartToggle) {
            chartToggle.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-unit]');
                if (!btn) return;
                const newUnit = btn.dataset.unit;
                if (newUnit === displayUnit) return;

                displayUnit = newUnit;
                chart.data.datasets[0].label = `Weight (${displayUnit})`;
                chart.data.datasets[0].data  = valuesIn(displayUnit);
                chart.update();

                chartToggle.querySelectorAll('button').forEach((b) => {
                    const on = b.dataset.unit === displayUnit;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            });
        }
    })();


    /* ---------------------------------------------------------
       9. Strength tracker — form unit toggle (lbs ↔ kg).
          Same shape as the weight form's toggle.
       --------------------------------------------------------- */
    (function initStrengthForm () {
        const form = document.getElementById('strengthForm');
        if (!form) return;

        const toggle      = document.getElementById('strengthUnitToggle');
        const unitInput   = document.getElementById('strengthUnit');
        const weightInput = document.getElementById('strengthWeight');
        const unitLabel   = document.getElementById('strengthUnitLabel');
        if (!toggle || !unitInput || !weightInput) return;

        const LB_PER_KG = 2.2046226218;
        const PLACEHOLDERS = { lbs: '225', kg: '102' };
        const BOUNDS = {
            lbs: { min: 1, max: 1500 },
            kg:  { min: 1, max: 700 },
        };

        function applyUnitUI (unit) {
            unitInput.value         = unit;
            weightInput.placeholder = PLACEHOLDERS[unit];
            weightInput.min         = BOUNDS[unit].min;
            weightInput.max         = BOUNDS[unit].max;
            if (unitLabel) unitLabel.textContent = `(${unit})`;

            toggle.querySelectorAll('button').forEach((b) => {
                const on = b.dataset.unit === unit;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }

        toggle.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-unit]');
            if (!btn) return;
            const newUnit = btn.dataset.unit;
            const oldUnit = unitInput.value;
            if (newUnit === oldUnit) return;

            const v = parseFloat(weightInput.value);
            if (!isNaN(v) && v > 0) {
                const converted = (oldUnit === 'lbs' && newUnit === 'kg')
                    ? v / LB_PER_KG
                    : v * LB_PER_KG;
                weightInput.value = converted.toFixed(1);
            }

            applyUnitUI(newUnit);
        });
    })();


    /* ---------------------------------------------------------
       10. Strength chart (Chart.js).
           Three lines (bench/squat/deadlift). Y = estimated 1RM
           via Epley: weight * (1 + reps/30). Display unit is
           independent of the form's unit and toggleable.
       --------------------------------------------------------- */
    (function initStrengthChart () {
        const canvas = document.getElementById('strengthChart');
        if (!canvas) return;
        if (typeof Chart === 'undefined') return;

        let rows;
        try {
            rows = JSON.parse(canvas.dataset.rows || '[]');
        } catch (e) {
            return;
        }
        if (!Array.isArray(rows) || rows.length === 0) return;

        const LB_PER_KG = 2.2046226218;
        let displayUnit = canvas.dataset.defaultUnit || 'lbs';

        const css  = getComputedStyle(document.documentElement);
        const text = (css.getPropertyValue('--text-dim') || '#a4a8b5').trim();
        const grid = 'rgba(148, 163, 184, 0.10)';

        // Unique sorted dates across all lifts → shared X-axis labels.
        const allDates = [...new Set(rows.map((r) => r.date))].sort();
        const labels   = allDates.map((d) => {
            const dt = new Date(d + 'T00:00:00');
            return isNaN(dt) ? d
                : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        // Convert canonical kg → display unit, then apply Epley 1RM.
        function ormFor (row, unit) {
            const w = unit === 'lbs' ? row.weight_kg * LB_PER_KG : row.weight_kg;
            return w * (1 + row.reps / 30);
        }

        // Per-lift array of 1RM values aligned to allDates (null where
        // the user didn't log that lift on that date). If there are
        // multiple entries on the same date for a lift, keep the
        // highest 1RM — that's the most representative for "progress".
        function dataFor (lift, unit) {
            const byDate = {};
            rows.forEach((r) => {
                if (r.lift_type !== lift) return;
                const v = ormFor(r, unit);
                if (!(r.date in byDate) || v > byDate[r.date]) {
                    byDate[r.date] = v;
                }
            });
            return allDates.map((d) => d in byDate ? byDate[d] : null);
        }

        const COLORS = {
            bench:    '#60a5fa',   // sky
            squat:    '#fb923c',   // orange (matches brand)
            deadlift: '#34d399',   // mint
        };
        const TITLE = {
            bench:    'Bench',
            squat:    'Squat',
            deadlift: 'Deadlift',
        };

        function makeDataset (lift) {
            return {
                label: TITLE[lift],
                data: dataFor(lift, displayUnit),
                borderColor: COLORS[lift],
                backgroundColor: 'transparent',
                borderWidth: 2.5,
                tension: 0.25,
                pointRadius: 4,
                spanGaps: true,
                fill: false,
            };
        }

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    makeDataset('bench'),
                    makeDataset('squat'),
                    makeDataset('deadlift'),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: text } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                if (ctx.parsed.y === null || ctx.parsed.y === undefined) return null;
                                return `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)} ${displayUnit} (1RM)`;
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: text }, grid: { color: grid } },
                    y: {
                        ticks: { color: text, callback: (v) => v.toFixed(0) },
                        grid:  { color: grid },
                        beginAtZero: false,
                    },
                },
            },
        });

        const chartToggle = document.getElementById('strengthChartUnitToggle');
        if (chartToggle) {
            chartToggle.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-unit]');
                if (!btn) return;
                const newUnit = btn.dataset.unit;
                if (newUnit === displayUnit) return;

                displayUnit = newUnit;
                ['bench', 'squat', 'deadlift'].forEach((lift, i) => {
                    chart.data.datasets[i].data = dataFor(lift, displayUnit);
                });
                chart.update();

                chartToggle.querySelectorAll('button').forEach((b) => {
                    const on = b.dataset.unit === displayUnit;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            });
        }
    })();
})();
