<?php // app/views/auth/reset.php ?>
<?php
    $errPass    = field_error('password');
    $errConfirm = field_error('password_confirm');
?>
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
                           autocomplete="new-password"
                           <?= $errPass
                              ? 'aria-invalid="true" aria-describedby="password-error"'
                              : 'aria-describedby="password-hint"' ?>
                           required>
                    <?= password_toggle_button('password') ?>
                </div>
                <?php if ($errPass): ?>
                    <p id="password-error" class="field-error"><?= e($errPass) ?></p>
                <?php else: ?>
                    <small id="password-hint" class="field-hint"><?= e(PASSWORD_HINT) ?></small>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm new password</label>
                <div class="password-wrap">
                    <input type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password"
                           <?= $errConfirm ? 'aria-invalid="true" aria-describedby="password_confirm-error"' : '' ?>
                           required>
                    <?= password_toggle_button('password_confirm') ?>
                </div>
                <?php if ($errConfirm): ?>
                    <p id="password_confirm-error" class="field-error"><?= e($errConfirm) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn">Reset password</button>
        </form>

        <p class="auth-footer">
            <a href="<?= url('login') ?>">Back to log in</a>
        </p>
    </div>
</div>
