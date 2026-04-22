<?php // app/views/auth/reset.php ?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>Set a new password</h1>
        <p class="lede">Choose a strong password you haven't used before.</p>

        <?php if ($errs = flash('errors')): ?>
            <div class="error-box">
                <ul>
                    <?php foreach (explode("\n", $errs) as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('reset-password') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="field">
                <label for="password">New password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" required>
                    <?= password_toggle_button('password') ?>
                </div>
                <small class="field-hint">
                    At least 8 characters, with one uppercase, one lowercase, and one number.
                </small>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm new password</label>
                <div class="password-wrap">
                    <input type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password" required>
                    <?= password_toggle_button('password_confirm') ?>
                </div>
            </div>

            <button type="submit" class="btn">Reset password</button>
        </form>

        <p class="auth-footer">
            <a href="<?= url('login') ?>">Back to log in</a>
        </p>
    </div>
</div>
