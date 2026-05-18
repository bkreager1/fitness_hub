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
       Shared chart helpers
       Consumed by the three Chart.js init blocks (intake, weight,
       strength). Keeps the visual language consistent across the
       full tracker pages and the dashboard mini-charts which reuse
       the same canvas IDs.

       makeChartGradient — returns a top-to-bottom CanvasGradient
       suitable for a line/bar dataset's backgroundColor. The color
       arg should contain the literal string "ALPHA" where the
       gradient alpha varies (e.g. 'rgba(255, 122, 26, ALPHA)').
       Falls back to the input color string before first layout,
       which Chart.js will replace on the next paint.

       brandedTooltip — Chart.js tooltip options keyed off the
       --bg-elev / --text / --primary CSS variables. Drop in via
       options.plugins.tooltip = { ...brandedTooltip(), ...overrides }.
       --------------------------------------------------------- */
    function makeChartGradient (chart, color) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return color.replace('ALPHA', '0.16');
        const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        g.addColorStop(0,    color.replace('ALPHA', '0.35'));
        g.addColorStop(0.55, color.replace('ALPHA', '0.10'));
        g.addColorStop(1,    color.replace('ALPHA', '0'));
        return g;
    }

    function brandedTooltip () {
        const css = getComputedStyle(document.documentElement);
        const bg     = (css.getPropertyValue('--bg-elev')  || '#161a24').trim();
        const txt    = (css.getPropertyValue('--text')     || '#ecedf2').trim();
        const dim    = (css.getPropertyValue('--text-dim') || '#a4a8b5').trim();
        const accent = (css.getPropertyValue('--primary')  || '#ff7a1a').trim();
        return {
            backgroundColor: bg,
            titleColor: accent,
            titleFont:  { size: 12, weight: '600' },
            bodyColor:  txt,
            bodyFont:   { size: 13, weight: '500' },
            footerColor: dim,
            borderColor: 'rgba(255, 255, 255, 0.08)',
            borderWidth: 1,
            cornerRadius: 10,
            padding: 10,
            boxPadding: 6,
            displayColors: true,
            usePointStyle: true,
        };
    }


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

        const dismiss = (el) => {
            // Guard so the timer + click paths can't double-fire and
            // orphan the animationend listener.
            if (el.classList.contains('is-dismissing')) return;
            el.classList.add('is-dismissing');
            el.addEventListener('animationend', (e) => {
                if (e.animationName === 'flashSlideOut') el.remove();
            }, { once: true });
        };

        document.querySelectorAll('.flash').forEach((el) => {
            setTimeout(() => dismiss(el), DISMISS_AFTER);
            // Click anywhere on the banner dismisses it early. Flash
            // banners are static text (no interactive children) so a
            // single listener on the element is enough.
            el.addEventListener('click', () => dismiss(el));
        });
    })();


    /* ---------------------------------------------------------
       1d. Open the native date picker on any click inside
       input[type="date"]. Chrome/Edge already do this natively;
       Firefox + Safari require explicit showPicker(). We painted
       a custom calendar icon via CSS background-image and hid the
       webkit picker indicator — calling showPicker() on click
       restores cross-browser parity. Wrapped in try/catch because
       showPicker() throws if invoked outside a user gesture.
       --------------------------------------------------------- */
    (function initDatePickerOpen () {
        document.addEventListener('click', (e) => {
            const input = e.target.closest('input[type="date"]');
            if (!input || input.disabled || input.readOnly) return;
            if (typeof input.showPicker !== 'function') return;
            try { input.showPicker(); } catch (_) { /* ignore */ }
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
        // Tear down the loading skeleton on the parent .chart-wrap. Done
        // up front so even early bails (no Chart, bad JSON) reveal the
        // canvas underneath instead of leaving the shimmer running.
        canvas.closest('.chart-wrap--loading')?.classList.remove('chart-wrap--loading');
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
                // Scriptable backgroundColor so the gradient rebuilds
                // on every paint — Chart.js re-invokes this after each
                // resize/layout. Top of the chart area gets the most
                // saturated orange and fades toward the baseline, so
                // tall bars read more confidently than short ones.
                backgroundColor: (ctx) =>
                    makeChartGradient(ctx.chart, 'rgba(255, 122, 26, ALPHA)'),
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
                    legend: { labels: { color: text, usePointStyle: true } },
                    tooltip: {
                        ...brandedTooltip(),
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
       6a. Tracker history — day-row striping.
          For .history-table--days tables, plain CSS :nth-child
          would stripe individual rows (mixing day-rows and the
          meal/lift-rows nested under them). We want every-other
          DAY to read as a stripe so the eye can track day groups
          at a glance — so JS walks day-rows, picks every odd index,
          and adds .is-stripe to every row sharing the same data-day
          (the day-row itself plus its hidden meal/lift-rows).
          Plain .history-table (weight) gets pure CSS striping.
       --------------------------------------------------------- */
    (function initHistoryStriping () {
        document.querySelectorAll('.history-table--days').forEach((table) => {
            table.querySelectorAll('.day-row').forEach((dayRow, idx) => {
                if (idx % 2 === 0) return;
                const day = dayRow.dataset.day;
                if (!day) return;
                table.querySelectorAll(`tr[data-day="${day}"]`)
                     .forEach((r) => r.classList.add('is-stripe'));
            });
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
       7b. Profile → Goals form unit toggle (lbs ↔ kg).
           Mirrors the weight-form toggle but converts four inputs
           (target weight + bench / squat / deadlift) at once, and
           updates the per-field "(unit)" label spans.
       --------------------------------------------------------- */
    (function initGoalsForm () {
        const toggle    = document.getElementById('goalsUnitToggle');
        const unitInput = document.getElementById('goalsUnit');
        if (!toggle || !unitInput) return;

        const LB_PER_KG = 2.2046226218;
        const FIELDS = ['target_weight', 'target_bench', 'target_squat', 'target_deadlift'];
        const PLACEHOLDERS = {
            target_weight:   { lbs: '165', kg: '75'  },
            target_bench:    { lbs: '225', kg: '102' },
            target_squat:    { lbs: '315', kg: '142' },
            target_deadlift: { lbs: '405', kg: '184' },
        };

        function applyUnitUI (unit) {
            unitInput.value = unit;

            FIELDS.forEach((name) => {
                const input = document.getElementById(name);
                if (input) input.placeholder = PLACEHOLDERS[name][unit];
            });

            document.querySelectorAll('.goals-unit-label').forEach((el) => {
                el.textContent = `(${unit})`;
            });

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

            FIELDS.forEach((name) => {
                const input = document.getElementById(name);
                if (!input) return;
                const v = parseFloat(input.value);
                if (!isNaN(v) && v > 0) {
                    const converted = (oldUnit === 'lbs' && newUnit === 'kg')
                        ? v / LB_PER_KG
                        : v * LB_PER_KG;
                    input.value = converted.toFixed(1);
                }
            });

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
        canvas.closest('.chart-wrap--loading')?.classList.remove('chart-wrap--loading');
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
                    backgroundColor: (ctx) =>
                        makeChartGradient(ctx.chart, 'rgba(255, 122, 26, ALPHA)'),
                    borderWidth: 2.5,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ff7a1a',
                    pointBorderColor: 'rgba(15, 18, 24, 0.95)',
                    pointBorderWidth: 2,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: text, usePointStyle: true } },
                    tooltip: {
                        ...brandedTooltip(),
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
        canvas.closest('.chart-wrap--loading')?.classList.remove('chart-wrap--loading');
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
                tension: 0.35,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: COLORS[lift],
                pointBorderColor: 'rgba(15, 18, 24, 0.95)',
                pointBorderWidth: 2,
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
                    legend: { labels: { color: text, usePointStyle: true } },
                    tooltip: {
                        ...brandedTooltip(),
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


    /* ---------------------------------------------------------
       Dashboard strength card — 1RM ↔ All-time inset toggle.

       Each lift row's right-hand number lives in a span with
       data-current + data-alltime attributes (raw decimals). The
       toggle's two buttons have data-mode="current"|"alltime".
       Click swaps the rendered text, formatted with thousands
       separators to match the server-rendered initial value.
       --------------------------------------------------------- */
    (function initStrengthDashToggle () {
        const card = document.getElementById('dashStrengthCard');
        if (!card) return;
        const toggle = card.querySelector('.strength-toggle');
        if (!toggle) return;

        const fmt = (v) => Number(v).toLocaleString('en-US', {
            maximumFractionDigits: 0,
        });

        toggle.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-mode]');
            if (!btn || btn.classList.contains('is-active')) return;
            const mode = btn.dataset.mode;

            toggle.querySelectorAll('button').forEach((b) => {
                const on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            card.querySelectorAll('.strength-list__num').forEach((n) => {
                const raw = mode === 'alltime'
                    ? n.dataset.alltime
                    : n.dataset.current;
                if (raw !== undefined) n.textContent = fmt(raw);
            });
        });
    })();


    /* ---------------------------------------------------------
       Cardio tracker — daily minutes bar chart.

       Rolls up rows into per-day totals (a morning walk + evening
       run on the same date stack into one bar), then renders a
       single orange-gradient bar series. Same visual language as
       the calorie intake chart so the dashboard reads consistently.
       --------------------------------------------------------- */
    (function initCardioChart () {
        const canvas = document.getElementById('cardioChart');
        if (!canvas) return;
        canvas.closest('.chart-wrap--loading')?.classList.remove('chart-wrap--loading');
        if (typeof Chart === 'undefined') return;

        let rows;
        try {
            rows = JSON.parse(canvas.dataset.rows || '[]');
        } catch (e) {
            return;
        }
        if (!Array.isArray(rows) || rows.length === 0) return;

        // Roll up to one bar per date (multiple sessions stack).
        const byDate = {};
        rows.forEach((r) => {
            byDate[r.date] = (byDate[r.date] || 0) + (r.duration_min || 0);
        });
        const dates  = Object.keys(byDate).sort();
        const labels = dates.map((d) => {
            const dt = new Date(d + 'T00:00:00');
            return isNaN(dt) ? d
                : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const data = dates.map((d) => byDate[d]);

        const css  = getComputedStyle(document.documentElement);
        const text = (css.getPropertyValue('--text-dim') || '#a4a8b5').trim();
        const grid = 'rgba(148, 163, 184, 0.10)';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Minutes',
                    data,
                    backgroundColor: (ctx) =>
                        makeChartGradient(ctx.chart, 'rgba(255, 122, 26, ALPHA)'),
                    borderColor: '#ff7a1a',
                    borderWidth: 1,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...brandedTooltip(),
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y.toLocaleString()} min`,
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
       Count-up animation on dashboard summary numbers.

       Targets the leading numeric text node inside each
       .dash-card__value (the unit span next to it is left alone),
       parses the formatted value, then animates from 0 → value
       over ~900ms with an ease-out cubic when the card scrolls
       into view. Number formatting (thousands separators, decimal
       places) is preserved by detecting it from the original
       string and re-applying it via toLocaleString.

       Skipped under reduced-motion and on browsers without
       IntersectionObserver. Placeholder cards rendering "—" stay
       untouched since they don't parse as a number.
       --------------------------------------------------------- */
    (function initDashCountUp () {
        if (!('IntersectionObserver' in window)) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const valueNodes = document.querySelectorAll('.dash-card__value');
        if (valueNodes.length === 0) return;

        const DURATION = 900;
        const targets  = new Map();   // valueNode → { textNode, end, decimals, final }

        valueNodes.forEach((valueNode) => {
            // First non-empty text node is the number; the unit span
            // (and any other markup) is left as-is.
            const textNode = [...valueNode.childNodes].find(
                (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim()
            );
            if (!textNode) return;

            // Split the text node into leading whitespace + value +
            // trailing whitespace so we can preserve the spacing
            // between the number and the unit span when we rewrite
            // the value during the animation.
            const full = textNode.textContent;
            const lead = (full.match(/^\s*/) || [''])[0];
            const trail = (full.match(/\s*$/) || [''])[0];
            const valueStr = full.slice(lead.length, full.length - trail.length);

            const num = parseFloat(valueStr.replace(/,/g, ''));
            if (!isFinite(num)) return;   // "—" placeholders fall out here

            const decimals = valueStr.includes('.')
                ? (valueStr.split('.')[1] || '').length
                : 0;

            const fmt = (v) => v.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });

            // Start at zero so the very first paint already shows the
            // animation's starting state — no flash of the final value.
            textNode.textContent = lead + fmt(0) + trail;

            targets.set(valueNode, {
                textNode, end: num, fmt, lead, trail, originalValueStr: valueStr,
            });
        });

        if (targets.size === 0) return;

        const easeOut = (x) => 1 - Math.pow(1 - x, 3);

        const animate = (t) => {
            const start = performance.now();
            const step  = (now) => {
                const p   = Math.min((now - start) / DURATION, 1);
                const val = t.end * easeOut(p);
                t.textNode.textContent = t.lead + t.fmt(val) + t.trail;
                if (p < 1) {
                    requestAnimationFrame(step);
                } else {
                    // Snap back to the exact PHP-rendered string so
                    // toLocaleString rounding can't drift away from
                    // what number_format produced server-side.
                    t.textNode.textContent = t.lead + t.originalValueStr + t.trail;
                }
            };
            requestAnimationFrame(step);
        };

        const io = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const t = targets.get(entry.target);
                if (!t) continue;
                targets.delete(entry.target);  // run once
                io.unobserve(entry.target);
                animate(t);
            }
        }, { threshold: 0.35 });

        targets.forEach((_, valueNode) => io.observe(valueNode));
    })();


    /* ---------------------------------------------------------
       Reveal-on-scroll for cards/sections below the fold.

       Pairs with the .reveal / .is-visible CSS pair at the bottom
       of style.css. IntersectionObserver flips items to visible
       as they enter the viewport; a per-group index seeds a tiny
       stagger so siblings cascade rather than popping in unison.

       Two early bails: no IntersectionObserver (very old browsers)
       and prefers-reduced-motion (no animation should play at all).
       We also skip items already above the fold at script time —
       those are already being animated by .site-main's page-entry
       rule, so layering a second fade on top would feel doubled.
       --------------------------------------------------------- */
    (function initRevealOnScroll () {
        if (!('IntersectionObserver' in window)) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        // [selector, per-index stagger in ms]. Order matters: a WeakSet
        // dedupes overlapping selectors so an element only gets one
        // reveal-group's stagger applied.
        const groups = [
            ['.features > .feature-card',     70],
            ['.dash-summary > .dash-card',    60],
            ['.dash-charts > .tracker-card',  90],
            ['.section-head',                  0],
            ['.how-grid > *',                 80],
            ['.contact-grid > *',             80],
            ['.quote',                         0],
        ];

        const io = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            }
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });

        // Anything sitting above this point at page load is already
        // visible to the user — page-entry animates it. Reveal only
        // earns its keep for things the user has to scroll to find.
        const vh = window.innerHeight || document.documentElement.clientHeight;
        const aboveFoldCutoff = vh * 0.92;

        const seen = new WeakSet();
        groups.forEach(([sel, stagger]) => {
            document.querySelectorAll(sel).forEach((el, idx) => {
                if (seen.has(el)) return;
                seen.add(el);

                if (el.getBoundingClientRect().top < aboveFoldCutoff) return;

                el.classList.add('reveal');
                if (stagger) {
                    const delay = Math.min(idx * stagger, 280);
                    el.style.setProperty('--reveal-delay', delay + 'ms');
                }
                io.observe(el);
            });
        });
    })();


    /* ---------------------------------------------------------
       Sitewide ARIA announcer
       Writes one-liners into #ariaStatus (live="polite") so
       silent UI updates — chart unit toggles, etc. — get spoken
       by assistive tech. Empty-then-set avoids identical-text
       deduping by screen readers.
       --------------------------------------------------------- */
    const ariaStatus = document.getElementById('ariaStatus');
    function announce(message) {
        if (!ariaStatus || typeof message !== 'string' || !message) return;
        ariaStatus.textContent = '';
        setTimeout(() => { ariaStatus.textContent = message; }, 20);
    }

    // Chart unit-toggle announcer. The per-tracker handlers (above)
    // own the actual unit swap + chart redraw; this is purely a
    // bubble-phase listener that announces the new state. Multiple
    // clicks announce each time so the user always hears feedback.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.unit-toggle button[data-unit]');
        if (!btn) return;
        const labels = { kg: 'kilograms', lbs: 'pounds', mi: 'miles', km: 'kilometers' };
        const unit = btn.dataset.unit;
        announce('Now showing data in ' + (labels[unit] || unit) + '.');
    });


    /* ---------------------------------------------------------
       Confirmation dialog
       Replaces inline `onsubmit="return confirm(...)"` with the
       sitewide <dialog> in layouts/footer.php. Forms opt in via:
           <form data-confirm="Are you sure?"
                 data-confirm-ok="Yes, delete">…</form>
       Falls back to native confirm() on browsers without
       HTMLDialogElement support.
       --------------------------------------------------------- */
    (function initConfirmDialog() {
        const dialog = document.getElementById('confirmDialog');
        if (!dialog) return;

        const msgEl    = document.getElementById('confirmDialogMsg');
        const okBtn    = dialog.querySelector('[data-confirm-ok]');
        const cancelBtn = dialog.querySelector('[data-confirm-cancel]');
        const hasNative = typeof dialog.showModal === 'function';
        let pending = null;

        const finalizeSubmit = (form) => {
            form.dataset.confirmed = 'yes';
            // requestSubmit() (vs submit()) DOES dispatch a submit event,
            // so the loading-state handler still triggers and the form's
            // submit handlers run. Our own handler short-circuits on the
            // confirmed flag.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        document.addEventListener('submit', (e) => {
            const form = e.target.closest('form[data-confirm]');
            if (!form) return;
            if (form.dataset.confirmed === 'yes') return; // user already confirmed
            e.preventDefault();

            if (!hasNative) {
                if (window.confirm(form.dataset.confirm || 'Are you sure?')) {
                    finalizeSubmit(form);
                }
                return;
            }

            pending = form;
            msgEl.textContent = form.dataset.confirm || 'Are you sure?';
            okBtn.textContent = form.dataset.confirmOk || 'Confirm';
            dialog.showModal();
        });

        cancelBtn.addEventListener('click', () => {
            pending = null;
            dialog.close('cancel');
        });
        okBtn.addEventListener('click', () => {
            const form = pending;
            pending = null;
            dialog.close('confirm');
            if (form) finalizeSubmit(form);
        });
        // Click on the backdrop (the dialog itself outside the inner card) closes it.
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                pending = null;
                dialog.close('backdrop');
            }
        });
        // ESC: native dialog fires 'cancel' then 'close' — clear pending so
        // a subsequent OK click on a different form doesn't submit the wrong one.
        dialog.addEventListener('cancel', () => { pending = null; });
    })();
})();
