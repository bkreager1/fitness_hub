<?php // app/views/auth/register.php ?>
<?php
    $errName    = field_error('name');
    $errEmail   = field_error('email');
    $errPass    = field_error('password');
    $errConfirm = field_error('password_confirm');
?>
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
                       maxlength="100" autocomplete="name"
                       <?= $errName ? 'aria-invalid="true"' : '' ?>
                       required>
                <?php if ($errName): ?>
                    <p class="field-error"><?= e($errName) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e(old('email')) ?>"
                       autocomplete="email"
                       <?= $errEmail ? 'aria-invalid="true"' : '' ?>
                       required>
                <?php if ($errEmail): ?>
                    <p class="field-error"><?= e($errEmail) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password"
                           autocomplete="new-password"
                           <?= $errPass ? 'aria-invalid="true"' : '' ?>
                           required>
                    <?= password_toggle_button('password') ?>
                </div>
                <?php if ($errPass): ?>
                    <p class="field-error"><?= e($errPass) ?></p>
                <?php else: ?>
                    <small class="field-hint"><?= e(PASSWORD_HINT) ?></small>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <div class="password-wrap">
                    <input type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password"
                           <?= $errConfirm ? 'aria-invalid="true"' : '' ?>
                           required>
                    <?= password_toggle_button('password_confirm') ?>
                </div>
                <?php if ($errConfirm): ?>
                    <p class="field-error"><?= e($errConfirm) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn">Create account</button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="<?= url('login') ?>">Log in</a>
        </p>
    </div>
</div>
