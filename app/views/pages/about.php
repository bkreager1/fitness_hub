<?php // app/views/pages/about.php — Phase 6 ?>

<!-- ===================== Intro ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">About</span>
        <h1>Fitness, without the noise.</h1>
        <p class="hero-lede">
            Rock County Fitness Hub is a free, no-fluff app for tracking the
            three things that actually move the needle: what you eat, what you
            weigh, and what you can lift. Built for beginners, useful for
            anyone — no DMs from a "coach," no $99 supplement stacks, no shame.
        </p>
    </div>
</section>


<!-- ===================== What it is ===================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>What's inside</h2>
            <p>Three simple trackers, one clean dashboard. That's it.</p>
        </div>

        <div class="features">

            <article class="feature-card">
                <img class="feature-image"
                     src="<?= asset('images/calorielogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Calories</h3>
                <p>Tell us your stats and goal — the app suggests a daily
                   calorie target using a well-known formula (Mifflin-St Jeor)
                   and lets you log what you ate to compare against it.</p>
            </article>

            <article class="feature-card">
                <img class="feature-image"
                     src="<?= asset('images/weightlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Body weight</h3>
                <p>Weigh in whenever you want. Your trend is plotted over time
                   so a single bad-scale-day doesn't psych you out. Switch
                   between lbs and kg without losing your data.</p>
            </article>

            <article class="feature-card">
                <img class="feature-image"
                     src="<?= asset('images/strengthlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>The big three lifts</h3>
                <p>Bench press, back squat, deadlift. The classic strength
                   markers. Log your top set and watch all three lines climb
                   on one chart over the months.</p>
            </article>

        </div>
    </div>
</section>


<!-- ===================== How it works ===================== -->
<section class="section section--alt">
    <div class="container how-grid">

        <div>
            <span class="eyebrow">How it works</span>
            <h2>Three steps. That's the whole pitch.</h2>
            <p class="prose">
                You don't need to read a 50-page program or watch hours of
                YouTube to start. Open an account, log a couple of numbers,
                come back when you have new ones. Consistency beats complexity.
            </p>
        </div>

        <ol class="steps">
            <li>
                <span class="steps-num">1</span>
                <div>
                    <h3>Make an account</h3>
                    <p>Free, takes about ten seconds. Email + password. Nothing
                       gets shared, sold, or emailed at you.</p>
                </div>
            </li>
            <li>
                <span class="steps-num">2</span>
                <div>
                    <h3>Log your numbers</h3>
                    <p>Today's weight. Yesterday's lifts. The calories you ate
                       at lunch. Whatever you've got — log it and forget it.</p>
                </div>
            </li>
            <li>
                <span class="steps-num">3</span>
                <div>
                    <h3>Watch your trends</h3>
                    <p>Charts make patterns obvious. The scale lies day to day,
                       but the line over a month tells the truth.</p>
                </div>
            </li>
        </ol>
    </div>
</section>


<!-- ===================== Who it's for ===================== -->
<section class="section">
    <div class="container who-grid">
        <div>
            <h2>Who it's for</h2>
            <p class="prose">
                If you're brand new to lifting, trying to lose your first 15 lbs,
                or just sick of guessing whether you're eating enough, this app
                is for you. It's not a replacement for a doctor, a coach, or
                your own common sense — it's a place to keep your numbers
                organized so you can stop relying on memory and start trusting
                the data.
            </p>
        </div>
        <aside class="cta-card">
            <h3>Ready to start?</h3>
            <p>Make a free account and log your first weigh-in in under a minute.</p>
            <?php if (is_logged_in()): ?>
                <a class="btn" href="<?= url('dashboard') ?>">Open dashboard</a>
            <?php else: ?>
                <a class="btn" href="<?= url('register') ?>">Create your account</a>
            <?php endif; ?>
        </aside>
    </div>
</section>
