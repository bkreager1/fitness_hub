<?php // app/views/auth/login.php ?>
<?php
    $errEmail = field_error('email');
    $errPass  = field_error('password');
?>
<div class="auth-shell auth-shell--photo auth-shell--login">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="lede">Log in to continue your fitness journey.</p>

        <?php if ($errs = flash('errors')): ?>
            <div class="error-box" role="alert">
                <ul>
                    <?php foreach (explode("\n", $errs) as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('login') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e(old('email')) ?>"
                       autocomplete="email"
                       <?= $errEmail ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
                       required>
                <?php if ($errEmail): ?>
                    <p id="email-error" class="field-error"><?= e($errEmail) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password"
                           autocomplete="current-password"
                           <?= $errPass ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
                           required>
                    <?= password_toggle_button('password') ?>
                </div>
                <?php if ($errPass): ?>
                    <p id="password-error" class="field-error"><?= e($errPass) ?></p>
                <?php endif; ?>
            </div>

            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="remember" value="1"
                           <?= old('remember') === '1' ? 'checked' : '' ?>>
                    Remember me
                </label>
                <a href="<?= url('forgot-password') ?>">Forgot password?</a>
            </div>

            <button type="submit" class="btn">Log in</button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="<?= url('register') ?>">Sign up</a>
        </p>
    </div>
</div>
