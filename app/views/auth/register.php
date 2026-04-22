<?php // app/views/auth/register.php ?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>Create your account</h1>
        <p class="lede">Track your weight, strength, and calories in one place.</p>

        <?php if ($errs = flash('errors')): ?>
            <div class="error-box">
                <ul>
                    <?php foreach (explode("\n", $errs) as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('register') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name"
                       value="<?= e(old('name')) ?>"
                       maxlength="100" autocomplete="name" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e(old('email')) ?>"
                       autocomplete="email" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" required>
                <small class="field-hint">
                    At least 8 characters, with one uppercase, one lowercase, and one number.
                </small>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn">Create account</button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="<?= url('login') ?>">Log in</a>
        </p>
    </div>
</div>
