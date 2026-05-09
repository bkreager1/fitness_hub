<?php // app/views/pages/about.php — Phase 6 ?>

<!-- ===================== Intro ===================== -->
<section class="hero hero--compact hero--photo"
         style="--hero-image: url('<?= asset('images/about.jpg') ?>');">
    <div class="container">
        <span class="eyebrow">About</span>
        <h1>Fitness, without the noise.</h1>
        <p class="hero-lede">
            Rock County Fitness Hub is a simple, no-fluff app for tracking
            the three things that actually move the needle: what you eat,
            what you weigh, and what you can lift. It was built for
            beginners, but it is useful for anyone who wants a clean place
            to stay consistent without the noise, pressure, or confusing
            fitness advice.
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
                <p>Enter your stats and goal, and the app calculates daily
                   calorie targets for cutting, maintaining, or bulking.
                   Then you can log meals and compare your intake against
                   your goal.</p>
            </article>

            <article class="feature-card">
                <img class="feature-image"
                     src="<?= asset('images/weightlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Body weight</h3>
                <p>Log weigh-ins over time and view your progress on a simple
                   chart. The trend helps you focus on the bigger picture
                   instead of one random scale reading.</p>
            </article>

            <article class="feature-card">
                <img class="feature-image"
                     src="<?= asset('images/strengthlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>The big three lifts</h3>
                <p>Track bench press, squat, and deadlift with weight and
                   reps. The chart estimates your one-rep max so progress
                   is easier to compare across different rep ranges.</p>
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
                You do not need a complicated program to start tracking
                progress. Create an account, log a few numbers, and come
                back when you have new ones. Consistency beats complexity.
            </p>
        </div>

        <ol class="steps">
            <li>
                <span class="steps-num">1</span>
                <div>
                    <h3>Make an account</h3>
                    <p>Create a free account with your name, email, and
                       password. Your dashboard keeps your progress organized
                       in one place.</p>
                </div>
            </li>
            <li>
                <span class="steps-num">2</span>
                <div>
                    <h3>Log your numbers</h3>
                    <p>Log your calories, weigh-ins, and lifts as you go.
                       Small entries build a clearer picture over time.</p>
                </div>
            </li>
            <li>
                <span class="steps-num">3</span>
                <div>
                    <h3>Watch your trends</h3>
                    <p>Use the charts to spot patterns over time. Day-to-day
                       numbers can jump around, but trends show what is
                       really happening.</p>
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
                If you are new to lifting, trying to lose weight, or just
                tired of guessing whether you are eating enough, this app
                gives you a simple place to track the basics. It is not a
                replacement for a doctor or coach. It is a tool to keep your
                numbers organized so you can stop relying on memory and start
                seeing patterns.
            </p>
        </div>
        <aside class="cta-card cta-card--photo"
               style="--cta-image: url('<?= asset('images/register.jpg') ?>');">
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
