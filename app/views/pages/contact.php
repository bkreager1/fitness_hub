<?php // app/views/pages/contact.php — Phase 6 ?>

<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Contact</span>
        <h1>Got a question or some feedback?</h1>
        <p class="hero-lede">
            Drop a note below — bug reports, feature ideas, or just saying
            hi. Every message lands in our inbox and we read them all.
        </p>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">

        <!-- ===== Form ===== -->
        <div class="contact-card">

            <?php if ($errs = flash('errors')): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach (explode("\n", $errs) as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('contact') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= e(old('name')) ?>"
                           autocomplete="name" maxlength="100" required>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= e(old('email')) ?>"
                           autocomplete="email" maxlength="150" required>
                </div>

                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message"
                              rows="6" maxlength="2000"
                              placeholder="What's on your mind?"
                              required><?= e(old('message')) ?></textarea>
                    <span class="field-hint">10–2000 characters.</span>
                </div>

                <button type="submit" class="btn btn-inline">Send message</button>
            </form>
        </div>

        <!-- ===== Info aside ===== -->
        <aside class="contact-info">
            <div class="info-card">
                <h3>What to expect</h3>
                <p>We're a small team — usually a reply within a couple of
                   business days. If your question is urgent, please mention
                   that in the subject of your message.</p>
            </div>

            <div class="info-card">
                <h3>Common questions</h3>
                <dl class="faq">
                    <dt>Is the app really free?</dt>
                    <dd>Yes. No paywalled features, no ads.</dd>

                    <dt>Where's my data stored?</dt>
                    <dd>On our server, encrypted in transit. We never sell or
                        share it.</dd>

                    <dt>I forgot my password.</dt>
                    <dd>Use <a href="<?= url('forgot-password') ?>">Forgot
                        password</a> on the login page.</dd>
                </dl>
            </div>
        </aside>

    </div>
</section>
