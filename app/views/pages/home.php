<?php // app/views/pages/home.php — Phase 5 landing page ?>

<!-- ===================== Hero + BMI ===================== -->
<section class="hero hero--photo"
         style="--hero-image: url('<?= asset('images/Landinghero.jpg') ?>');">
    <div class="container hero-grid">

        <div class="hero-copy">
            <span class="eyebrow">Built for Rock County</span>
            <h1>
                Build the body you want.
                <span class="accent">One day at a time.</span>
            </h1>
            <p class="hero-lede">
                Track your calories, weight, lifts, and cardio in one
                simple dashboard. No gimmicks, no confusing spreadsheets —
                just the numbers that help you stay consistent and see
                real progress.
            </p>
            <div class="hero-cta">
                <?php if (is_logged_in()): ?>
                    <a class="btn" href="<?= url('dashboard') ?>">Open dashboard</a>
                    <a class="btn btn-secondary btn-inline" href="<?= url('dashboard') ?>">Log today's progress</a>
                <?php else: ?>
                    <a class="btn" href="<?= url('register') ?>">Get started</a>
                    <a class="btn btn-secondary btn-inline" href="<?= url('about') ?>">Learn more</a>
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
            <h2>Four trackers. One simple goal.</h2>
            <p>Whether you&rsquo;re losing weight, building muscle, or staying consistent,
               each tracker helps you measure progress without overcomplicating it.</p>
        </div>

        <?php
            // Logged-in visitors jump straight to the matching tracker;
            // logged-out visitors all land on /register since they need
            // an account before they can log anything.
            $loggedIn = is_logged_in();
            $href = static fn(string $tracker): string =>
                $loggedIn ? url($tracker) : url('register');
            // Short accessible name on each card link so screen readers
            // announce a concise label instead of reading the whole
            // heading + paragraph in one breath.
            $cardLabel = static fn(string $name): string =>
                ($loggedIn ? 'Open ' : 'Sign up to use the ') . $name;
        ?>
        <div class="features">

            <a class="feature-card" href="<?= e($href('calorie')) ?>"
               aria-label="<?= e($cardLabel('Calorie tracker')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/calorielogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Calorie tracker</h3>
                <p>Calculate your maintenance, cut, and bulk targets,
                   then log your daily intake against your goal.</p>
            </a>

            <a class="feature-card" href="<?= e($href('weight')) ?>"
               aria-label="<?= e($cardLabel('Weight tracker')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/weightlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Weight tracker</h3>
                <p>Log your weigh-ins, watch your trend over time,
                   and switch between lbs and kg whenever you need.</p>
            </a>

            <a class="feature-card" href="<?= e($href('strength')) ?>"
               aria-label="<?= e($cardLabel('Strength tracker')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/strengthlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Strength tracker</h3>
                <p>Track your workouts in one place so you can see your
                   strength progress over time.</p>
            </a>

            <a class="feature-card" href="<?= e($href('cardio')) ?>"
               aria-label="<?= e($cardLabel('Cardio tracker')) ?>">
                <img class="feature-image"
                     src="<?= asset('images/cardio.png') ?>"
                     alt="" width="88" height="88">
                <h3>Cardio tracker</h3>
                <p>Log walks, runs, rides, and more — duration, optional
                   intensity, and distance roll up into a weekly goal.</p>
            </a>

        </div>
    </div>
</section>


<!-- ===================== Quote ===================== -->
<section class="container quote">
    <blockquote>
        We are what we repeatedly do. Excellence, then, is not an act but a habit.
    </blockquote>
    <cite>— Aristotle</cite>
</section>
