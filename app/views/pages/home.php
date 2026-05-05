<?php // app/views/pages/home.php — Phase 5 landing page ?>

<!-- ===================== Hero + BMI ===================== -->
<section class="hero">
    <div class="container hero-grid">

        <div class="hero-copy">
            <span class="eyebrow">Built for Rock County</span>
            <h1>
                Build the body you want.
                <span class="accent">One day at a time.</span>
            </h1>
            <p class="hero-lede">
                Track your calories, weight, and lifts in one clean dashboard.
                No gimmicks, no sketchy supplements — just the numbers that
                actually move the needle, and a little encouragement along the way.
            </p>
            <div class="hero-cta">
                <?php if (is_logged_in()): ?>
                    <a class="btn" href="<?= url('dashboard') ?>">Open dashboard</a>
                    <a class="btn btn-secondary btn-inline" href="<?= url('about') ?>">Learn more</a>
                <?php else: ?>
                    <a class="btn" href="<?= url('register') ?>">Get started — it's free</a>
                    <a class="btn btn-secondary btn-inline" href="<?= url('login') ?>">Log in</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- BMI calculator (public, instant, no DB) -->
        <aside class="bmi-card" aria-labelledby="bmiHeading">
            <div class="bmi-head">
                <div>
                    <h2 id="bmiHeading">Quick BMI check</h2>
                    <span class="field-hint">No sign-up needed — just a starting reference.</span>
                </div>
                <div class="unit-toggle" role="tablist" aria-label="Units">
                    <button type="button" class="is-active" data-unit="imperial" role="tab" aria-selected="true">US</button>
                    <button type="button" data-unit="metric" role="tab" aria-selected="false">Metric</button>
                </div>
            </div>

            <form id="bmiForm" novalidate>

                <!-- Imperial inputs (ft + in + lbs) -->
                <div class="bmi-grid bmi-grid--imperial" data-bmi-pane="imperial">
                    <div class="field">
                        <label for="bmiFt">Height (ft)</label>
                        <input type="number" id="bmiFt" inputmode="numeric"
                               min="3" max="8" step="1" placeholder="5">
                    </div>
                    <div class="field">
                        <label for="bmiIn">Height (in)</label>
                        <input type="number" id="bmiIn" inputmode="numeric"
                               min="0" max="11" step="1" placeholder="10">
                    </div>
                    <div class="field">
                        <label for="bmiLbs">Weight (lbs)</label>
                        <input type="number" id="bmiLbs" inputmode="decimal"
                               min="50" max="800" step="0.1" placeholder="170">
                    </div>
                </div>

                <!-- Metric inputs (cm + kg) -->
                <div class="bmi-grid" data-bmi-pane="metric" hidden>
                    <div class="field">
                        <label for="bmiCm">Height (cm)</label>
                        <input type="number" id="bmiCm" inputmode="decimal"
                               min="100" max="250" step="0.1" placeholder="178">
                    </div>
                    <div class="field">
                        <label for="bmiKg">Weight (kg)</label>
                        <input type="number" id="bmiKg" inputmode="decimal"
                               min="25" max="350" step="0.1" placeholder="77">
                    </div>
                </div>

                <div class="bmi-result" aria-live="polite">
                    <div>
                        <div class="bmi-result__value" id="bmiValue">—</div>
                        <div class="bmi-result__label">Your BMI</div>
                    </div>
                    <span class="bmi-result__chip" id="bmiChip" data-cat="none">
                        Enter your numbers
                    </span>
                </div>

                <?php if (!is_logged_in()): ?>
                    <!-- Sign-up CTA — JS toggles [hidden] in main.js's BMI
                         render() based on whether there's a valid result.
                         Hidden for logged-in users (already have an account). -->
                    <div class="bmi-cta" id="bmiCta" hidden>
                        <p class="bmi-cta__copy">Want to track your BMI over time?</p>
                        <a class="btn btn-inline" href="<?= url('register') ?>">
                            Create a free account
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </aside>

    </div>
</section>


<!-- ===================== Features ===================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Three trackers. One simple goal.</h2>
            <p>Whether you're cutting, bulking, or just trying to stay consistent,
               every tool here is built around the basics that actually work.</p>
        </div>

        <div class="features">

            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2s4 4 4 8a4 4 0 1 1-8 0c0-4 4-8 4-8z"/>
                        <path d="M8 14a4 4 0 0 0 8 0"/>
                    </svg>
                </span>
                <h3>Calorie tracker</h3>
                <p>Get a maintenance, cut, and bulk target from the
                   Mifflin-St Jeor formula, then log daily intake against your goal.</p>
            </article>

            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 17l5-5 4 4 8-8"/>
                        <path d="M14 8h6v6"/>
                    </svg>
                </span>
                <h3>Weight tracker</h3>
                <p>Log a weight, see your trend on a clean chart,
                   and switch between lbs and kg whenever you want.</p>
            </article>

            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12h2"/>
                        <path d="M20 12h2"/>
                        <rect x="4"  y="9"  width="3" height="6" rx="1"/>
                        <rect x="17" y="9"  width="3" height="6" rx="1"/>
                        <rect x="7"  y="11" width="10" height="2"/>
                    </svg>
                </span>
                <h3>Strength tracker</h3>
                <p>Bench, squat, and deadlift on one chart so you can
                   actually see your big three moving together.</p>
            </article>

        </div>
    </div>
</section>


<!-- ===================== Quote ===================== -->
<section class="container quote">
    <blockquote>
        The pain you feel today will be the strength you feel tomorrow.
    </blockquote>
    <cite>— Gym wisdom</cite>
</section>
