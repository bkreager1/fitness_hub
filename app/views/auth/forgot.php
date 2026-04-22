<?php // app/views/auth/forgot.php ?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>Forgot your password?</h1>
        <p class="lede">Enter the email on your account and we'll send you a reset link.</p>

        <?php if ($errs = flash('errors')): ?>
            <div class="error-box">
                <ul>
                    <?php foreach (explode("\n", $errs) as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('forgot-password') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e(old('email')) ?>"
                       autocomplete="email" required>
            </div>

            <button type="submit" class="btn">Send reset link</button>
        </form>

        <p class="auth-footer">
            Remembered it? <a href="<?= url('login') ?>">Back to log in</a>
        </p>
    </div>
</div>
