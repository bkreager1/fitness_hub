<?php // app/views/pages/privacy.php ?>

<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Privacy</span>
        <h1>Plain-language privacy.</h1>
        <p class="hero-lede">
            Short version: your data is yours. We use it to render your
            dashboard. We don't sell it, share it, or feed it into ad
            networks. You can export everything or delete your account
            at any time.
        </p>
        <p class="hero-lede" style="font-size:.9rem; color: var(--text-faint);">
            Last updated: <?= e(date('F j, Y')) ?>
            &middot; <em>[TODO: confirm wording with a human before public launch]</em>
        </p>
    </div>
</section>

<section class="section">
    <div class="container legal-prose">

        <h2>What we store</h2>
        <ul>
            <li><strong>Account info</strong> — your name, email, and a hashed password.</li>
            <li><strong>Fitness logs</strong> — the weigh-ins, meals, lifts, and cardio sessions you enter.</li>
            <li><strong>Profile photo</strong> — only if you upload one. Stored in <code>/public/uploads</code> on our server.</li>
            <li><strong>Goals + preferences</strong> — your active calorie goal, target weight, lift PR targets, and weekly cadence goals.</li>
            <li><strong>Login attempts</strong> — IP + timestamp for the last 15 minutes of failed logins, used only to slow down brute-force attempts.</li>
        </ul>

        <h2>What we don't store</h2>
        <ul>
            <li>Tracking cookies, analytics IDs, or third-party advertising pixels.</li>
            <li>Any payment information &mdash; the app is free.</li>
            <li>Anything you didn't explicitly type into a form or upload.</li>
        </ul>

        <h2>How we use it</h2>
        <p>
            Only to render your dashboard and the four trackers. Nothing
            is shared with third parties. Nothing is used to train
            machine-learning models. Email goes out only when you trigger
            it (password reset, email verification).
        </p>

        <h2>Cookies</h2>
        <p>
            One cookie: your session ID, set when you log in. It's
            <code>HttpOnly</code>, <code>SameSite=Lax</code>, and
            <code>Secure</code> when served over HTTPS. There is no
            analytics, advertising, or cross-site tracking cookie of any
            kind.
        </p>

        <h2>Where it lives</h2>
        <p>
            Data is stored in a MySQL database hosted on
            <em>[TODO: name the prod host, e.g. Hostinger]</em>.
            We don't operate edge caches or CDNs that would replicate
            your data elsewhere.
        </p>

        <h2>Your controls</h2>
        <ul>
            <li>
                <strong>Export</strong> — every tracker has a
                "Download CSV" link in its history section.
            </li>
            <li>
                <strong>Delete</strong> — the
                <a href="<?= url('profile') ?>">profile page</a> has a
                Danger Zone that permanently removes your account and
                every log we have for you. Foreign-key cascades take
                care of the rest.
            </li>
            <li>
                <strong>Edit</strong> — every weigh-in, meal, lift, and
                cardio session can be edited or deleted individually
                from its tracker.
            </li>
        </ul>

        <h2>Children</h2>
        <p>
            This site is intended for users aged 13 and over. If you
            believe a child has registered, contact us via the
            <a href="<?= url('contact') ?>">contact page</a> and we will
            remove the account.
        </p>

        <h2>Changes</h2>
        <p>
            If we make material changes to this policy, we'll update the
            "Last updated" date at the top and surface a banner on the
            dashboard. Continued use after a change means you accept the
            new policy.
        </p>

        <h2>Contact</h2>
        <p>
            Questions or concerns? Use the
            <a href="<?= url('contact') ?>">contact form</a> — every
            message lands in our inbox.
        </p>

    </div>
</section>
