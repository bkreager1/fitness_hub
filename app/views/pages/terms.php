<?php // app/views/pages/terms.php ?>

<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Terms of use</span>
        <h1>The ground rules.</h1>
        <p class="hero-lede">
            Short version: use the app for personal fitness tracking,
            don't abuse it, and know that nothing here is medical
            advice. By creating an account you agree to the rest below.
        </p>
        <p class="hero-lede" style="font-size:.9rem; color: var(--text-faint);">
            Last updated: <?= e(date('F j, Y')) ?>
            &middot; <em>[TODO: confirm wording with a human before public launch]</em>
        </p>
    </div>
</section>

<section class="section">
    <div class="container legal-prose">

        <h2>What this app is (and isn't)</h2>
        <p>
            Rock County Fitness Hub is a personal fitness-tracking tool.
            It calculates calorie targets, tracks body weight and
            workouts, and helps you stay consistent. It is
            <strong>not</strong> a medical device and the numbers it
            displays are <strong>not</strong> medical, nutritional, or
            therapeutic advice. Talk to a qualified professional before
            making major changes to your diet or exercise routine.
        </p>

        <h2>Your account</h2>
        <ul>
            <li>You're responsible for keeping your password secret.</li>
            <li>You're responsible for the accuracy of the data you log.</li>
            <li>You agree not to share an account with another person.</li>
            <li>You can delete your account from the
                <a href="<?= url('profile') ?>">profile page</a> at any time.</li>
        </ul>

        <h2>Acceptable use</h2>
        <p>You agree not to:</p>
        <ul>
            <li>Attempt to access another user's account or data.</li>
            <li>Scrape, harvest, or bulk-extract data from the site.</li>
            <li>Submit content that's unlawful, defamatory, or abusive
                through the contact form.</li>
            <li>Probe, test, or attempt to break the site's security
                without explicit prior permission.</li>
        </ul>

        <h2>Service availability</h2>
        <p>
            The app is offered as-is. We try to keep it online and
            working, but we don't guarantee uptime, response times, or
            that every feature will keep working indefinitely.
        </p>

        <h2>Limitation of liability</h2>
        <p>
            To the maximum extent allowed by law, we are not liable for
            any indirect, incidental, or consequential damages arising
            from your use of the site &mdash; including but not limited
            to lost data, lost progress, or health outcomes.
            <em>[TODO: ask a lawyer to harden this section before launch
            if usage grows beyond personal/small-group.]</em>
        </p>

        <h2>Changes to these terms</h2>
        <p>
            If we make material changes, the "Last updated" date above
            will change and a banner will appear on the dashboard for
            existing users. Continued use after a change constitutes
            acceptance of the new terms.
        </p>

        <h2>Termination</h2>
        <p>
            We may suspend or remove an account that's clearly being
            used to abuse the system. Otherwise, we'll only delete an
            account at your explicit request via the profile page.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about these terms?
            <a href="<?= url('contact') ?>">Send us a note</a>.
        </p>

    </div>
</section>
