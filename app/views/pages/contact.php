<?php // app/views/pages/contact.php — Phase 6 ?>
<?php
    // Pull each field's error once at the top — keeps the markup
    // below readable and avoids calling field_error() twice per field.
    $errName    = field_error('name');
    $errEmail   = field_error('email');
    $errMessage = field_error('message');
?>

<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Contact</span>
        <h1>Got a question or some feedback?</h1>
        <p class="hero-lede">
            Have a question, found a bug, or have an idea for the site?
            Send a message below. I read every submission and use the
            feedback to improve the app.
        </p>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">

        <!-- ===== Form ===== -->
        <div class="contact-card">

            <?php if ($success = flash('contact_success')): ?>
                <div class="flash flash-success flash--inline" role="status">
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('contact') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= e(old('name')) ?>"
                           autocomplete="name" maxlength="100"
                           <?= $errName ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
                           required>
                    <?php if ($errName): ?>
                        <p id="name-error" class="field-error"><?= e($errName) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= e(old('email')) ?>"
                           autocomplete="email" maxlength="150"
                           <?= $errEmail ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
                           required>
                    <?php if ($errEmail): ?>
                        <p id="email-error" class="field-error"><?= e($errEmail) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message"
                              rows="6" maxlength="2000"
                              placeholder="What's on your mind?"
                              <?= $errMessage
                                 ? 'aria-invalid="true" aria-describedby="message-error"'
                                 : 'aria-describedby="message-hint"' ?>
                              required><?= e(old('message')) ?></textarea>
                    <?php if ($errMessage): ?>
                        <p id="message-error" class="field-error"><?= e($errMessage) ?></p>
                    <?php else: ?>
                        <span id="message-hint" class="field-hint">10–2000 characters.</span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-inline">Send message</button>
            </form>
        </div>

        <!-- ===== Info aside ===== -->
        <aside class="contact-info">
            <div class="info-card info-card--photo">
                <img class="info-card__image"
                     src="<?= asset('images/contact.jpg') ?>"
                     alt="" width="800" height="450">
                <div class="info-card__body">
                    <h2>What to expect</h2>
                    <p>Messages are reviewed regularly. If your question is
                       urgent, include that in your message so it is easier
                       to spot.</p>
                </div>
            </div>

            <div class="info-card">
                <h2>Common questions</h2>
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
