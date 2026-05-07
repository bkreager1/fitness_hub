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

    // Summary block — read-only "at a glance" info.
    $memberSinceTs = strtotime($user['created_at'] ?? '') ?: null;
    $memberSince   = $memberSinceTs ? date('F Y', $memberSinceTs) : '—';

    // Map the goal enum to a display label.
    $goalLabels = ['cut' => 'Cut', 'maintain' => 'Maintain', 'bulk' => 'Bulk'];
    $goalLabel  = $goalLabels[$user['current_goal'] ?? ''] ?? '—';

    // English-pluralize a count for the activity line.
    $countLabel = static fn(int $n, string $singular, string $plural): string =>
        number_format($n) . ' ' . ($n === 1 ? $singular : $plural);

    $logsLine = implode(' · ', [
        $countLabel((int) $summary['weight_logs'],   'weigh-in',     'weigh-ins'),
        $countLabel((int) $summary['calorie_days'],  'calorie day',  'calorie days'),
        $countLabel((int) $summary['strength_sets'], 'lift set',     'lift sets'),
    ]);
?>
<section class="section">
    <div class="container">

        <header class="profile-head">
            <h1>Profile</h1>
            <p class="lede">Update your account info, change your password, or set a profile photo.</p>
        </header>

        <!-- ============ AT-A-GLANCE SUMMARY ============ -->
        <div class="profile-summary">
            <div class="profile-summary__item">
                <span class="profile-summary__label">Member since</span>
                <span class="profile-summary__value"><?= e($memberSince) ?></span>
            </div>
            <div class="profile-summary__item">
                <span class="profile-summary__label">Current goal</span>
                <span class="profile-summary__value"><?= e($goalLabel) ?></span>
            </div>
            <div class="profile-summary__item profile-summary__item--wide">
                <span class="profile-summary__label">Logs</span>
                <span class="profile-summary__value"><?= e($logsLine) ?></span>
            </div>
        </div>

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
                                   <?= $errAvatar
                                      ? 'aria-invalid="true" aria-describedby="avatar-error"'
                                      : 'aria-describedby="avatar-hint"' ?>
                                   required>
                            <?php if ($errAvatar): ?>
                                <p id="avatar-error" class="field-error"><?= e($errAvatar) ?></p>
                            <?php else: ?>
                                <small id="avatar-hint" class="field-hint">JPG, PNG, or WebP. Max 2&nbsp;MB.</small>
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
                           <?= $errName ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
                           required>
                    <?php if ($errName): ?>
                        <p id="name-error" class="field-error"><?= e($errName) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= e(old('email', $user['email'])) ?>"
                           autocomplete="email"
                           <?= $errEmail ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
                           required>
                    <?php if ($errEmail): ?>
                        <p id="email-error" class="field-error"><?= e($errEmail) ?></p>
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
                               <?= $errCurrentPw ? 'aria-invalid="true" aria-describedby="current_password-error"' : '' ?>
                               required>
                        <?= password_toggle_button('current_password') ?>
                    </div>
                    <?php if ($errCurrentPw): ?>
                        <p id="current_password-error" class="field-error"><?= e($errCurrentPw) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="new_password">New password</label>
                    <div class="password-wrap">
                        <input type="password" id="new_password" name="new_password"
                               autocomplete="new-password"
                               <?= $errNewPw
                                  ? 'aria-invalid="true" aria-describedby="new_password-error"'
                                  : 'aria-describedby="new_password-hint"' ?>
                               required>
                        <?= password_toggle_button('new_password') ?>
                    </div>
                    <?php if ($errNewPw): ?>
                        <p id="new_password-error" class="field-error"><?= e($errNewPw) ?></p>
                    <?php else: ?>
                        <small id="new_password-hint" class="field-hint"><?= e(PASSWORD_HINT) ?></small>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="new_password_confirm">Confirm new password</label>
                    <div class="password-wrap">
                        <input type="password" id="new_password_confirm" name="new_password_confirm"
                               autocomplete="new-password"
                               <?= $errConfirmPw ? 'aria-invalid="true" aria-describedby="new_password_confirm-error"' : '' ?>
                               required>
                        <?= password_toggle_button('new_password_confirm') ?>
                    </div>
                    <?php if ($errConfirmPw): ?>
                        <p id="new_password_confirm-error" class="field-error"><?= e($errConfirmPw) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-inline">Change password</button>
            </form>
        </div>

    </div>
</section>
