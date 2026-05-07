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

        <?php
            // Logged-in visitors jump straight to the matching tracker;
            // logged-out visitors all land on /register since they need
            // an account before they can log anything.
            $loggedIn = is_logged_in();
            $href = static fn(string $tracker): string =>
                $loggedIn ? url($tracker) : url('register');
        ?>
        <div class="features">

            <a class="feature-card" href="<?= e($href('calorie')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/calorielogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Calorie tracker</h3>
                <p>Get a maintenance, cut, and bulk target from the
                   Mifflin-St Jeor formula, then log daily intake against your goal.</p>
            </a>

            <a class="feature-card" href="<?= e($href('weight')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/weightlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Weight tracker</h3>
                <p>Log a weight, see your trend on a clean chart,
                   and switch between lbs and kg whenever you want.</p>
            </a>

            <a class="feature-card" href="<?= e($href('strength')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/strengthlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Strength tracker</h3>
                <p>Bench, squat, and deadlift on one chart so you can
                   actually see your big three moving together.</p>
            </a>

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
