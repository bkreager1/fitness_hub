<?php // app/views/profile/index.php ?>
<?php
    // Field-level errors. Each form's keys are namespaced so a failed
    // submission only lights up its own card.
    $errName        = field_error('name');
    $errEmail       = field_error('email');
    $errCurrentPw   = field_error('current_password');
    $errNewPw       = field_error('new_password');
    $errConfirmPw   = field_error('new_password_confirm');
    $errAvatar      = field_error('avatar');

    $hasAvatar = !empty($user['profile_image_path']);
    $avatarSrc = $hasAvatar
        ? asset('uploads/' . $user['profile_image_path'])
        : null;

    // First letter of the name for the initials fallback.
    $initial = mb_strtoupper(mb_substr(trim($user['name']), 0, 1)) ?: '?';
?>
<section class="section">
    <div class="container">

        <header class="profile-head">
            <h1>Profile</h1>
            <p class="lede">Update your account info, change your password, or set a profile photo.</p>
        </header>

        <!-- ============ PROFILE PHOTO ============ -->
        <div class="tracker-card">
            <div class="tracker-card__head">
                <h2>Profile photo</h2>
            </div>

            <div class="profile-photo-row">
                <?php if ($hasAvatar): ?>
                    <img class="avatar avatar-lg"
                         src="<?= e($avatarSrc) ?>"
                         alt="Your profile photo">
                <?php else: ?>
                    <span class="avatar avatar-lg avatar-initials" aria-hidden="true">
                        <?= e($initial) ?>
                    </span>
                <?php endif; ?>

                <div class="profile-photo-actions">
                    <form method="post"
                          action="<?= url('profile/image') ?>"
                          enctype="multipart/form-data"
                          class="profile-upload-form">
                        <?= csrf_field() ?>

                        <div class="field">
                            <label for="avatar">Upload a new photo</label>
                            <input type="file" id="avatar" name="avatar"
                                   accept="image/jpeg,image/png,image/webp"
                                   <?= $errAvatar ? 'aria-invalid="true"' : '' ?>
                                   required>
                            <?php if ($errAvatar): ?>
                                <p class="field-error"><?= e($errAvatar) ?></p>
                            <?php else: ?>
                                <small class="field-hint">JPG, PNG, or WebP. Max 2&nbsp;MB.</small>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-inline">Upload photo</button>
                    </form>

                    <?php if ($hasAvatar): ?>
                        <form method="post"
                              action="<?= url('profile/image/delete') ?>"
                              class="profile-remove-form"
                              onsubmit="return confirm('Remove your profile photo?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link-danger">Remove photo</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ ACCOUNT INFO ============ -->
        <div class="tracker-card">
            <div class="tracker-card__head">
                <h2>Account info</h2>
            </div>

            <form method="post" action="<?= url('profile') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= e(old('name', $user['name'])) ?>"
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
                           value="<?= e(old('email', $user['email'])) ?>"
                           autocomplete="email"
                           <?= $errEmail ? 'aria-invalid="true"' : '' ?>
                           required>
                    <?php if ($errEmail): ?>
                        <p class="field-error"><?= e($errEmail) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-inline">Save changes</button>
            </form>
        </div>

        <!-- ============ CHANGE PASSWORD ============ -->
        <div class="tracker-card">
            <div class="tracker-card__head">
                <h2>Change password</h2>
            </div>

            <form method="post" action="<?= url('profile/password') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="current_password">Current password</label>
                    <div class="password-wrap">
                        <input type="password" id="current_password" name="current_password"
                               autocomplete="current-password"
                               <?= $errCurrentPw ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?= password_toggle_button('current_password') ?>
                    </div>
                    <?php if ($errCurrentPw): ?>
                        <p class="field-error"><?= e($errCurrentPw) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="new_password">New password</label>
                    <div class="password-wrap">
                        <input type="password" id="new_password" name="new_password"
                               autocomplete="new-password"
                               <?= $errNewPw ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?= password_toggle_button('new_password') ?>
                    </div>
                    <?php if ($errNewPw): ?>
                        <p class="field-error"><?= e($errNewPw) ?></p>
                    <?php else: ?>
                        <small class="field-hint"><?= e(PASSWORD_HINT) ?></small>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="new_password_confirm">Confirm new password</label>
                    <div class="password-wrap">
                        <input type="password" id="new_password_confirm" name="new_password_confirm"
                               autocomplete="new-password"
                               <?= $errConfirmPw ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?= password_toggle_button('new_password_confirm') ?>
                    </div>
                    <?php if ($errConfirmPw): ?>
                        <p class="field-error"><?= e($errConfirmPw) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-inline">Change password</button>
            </form>
        </div>

    </div>
</section>
