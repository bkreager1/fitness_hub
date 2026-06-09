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
    $errGWeight     = field_error('target_weight');
    $errGBench      = field_error('target_bench');
    $errGSquat      = field_error('target_squat');
    $errGDeadlift   = field_error('target_deadlift');
    $errGWorkouts   = field_error('weekly_workout_target');
    $errGCardio     = field_error('weekly_cardio_target');

    // Goals: target weight + 3 target lifts. Stored canonically in kg,
    // displayed in the unit the user picks (default lbs for now —
    // most of the strength UI uses lbs as the default too).
    $goalsUnit = old('goals_unit') !== '' ? old('goals_unit') : 'lbs';

    // Convert a canonical kg value to the display unit, rounded to 1
    // decimal. Returns "" so it slots into a value="" attribute cleanly
    // when the user hasn't set the goal yet.
    $kgToDisplay = static function (?float $kg, string $unit): string {
        if ($kg === null) return '';
        $val = $unit === 'kg' ? $kg : $kg * 2.2046226218;
        return rtrim(rtrim(number_format($val, 1, '.', ''), '0'), '.');
    };

    // "old('field')" wins on a validation-failure redraw, otherwise we
    // show the persisted goal converted into the current display unit.
    $gWeightVal   = old('target_weight',   $kgToDisplay($user['target_weight_kg']   ?? null, $goalsUnit));
    $gBenchVal    = old('target_bench',    $kgToDisplay($user['target_bench_kg']    ?? null, $goalsUnit));
    $gSquatVal    = old('target_squat',    $kgToDisplay($user['target_squat_kg']    ?? null, $goalsUnit));
    $gDeadliftVal = old('target_deadlift', $kgToDisplay($user['target_deadlift_kg'] ?? null, $goalsUnit));

    // Weekly cadence targets — plain integers, not converted.
    $weeklyWorkoutVal = old('weekly_workout_target',
        $user['weekly_workout_target'] !== null ? (string) $user['weekly_workout_target'] : '');
    $weeklyCardioVal  = old('weekly_cardio_target',
        $user['weekly_cardio_target']  !== null ? (string) $user['weekly_cardio_target']  : '');

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
                              data-confirm="Remove your profile photo? Your initials will replace it."
                              data-confirm-ok="Remove photo">
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

        <!-- ============ GOALS ============ -->
        <?php if ($flashMsg = flash('goals_success')): ?>
            <div class="flash flash-success flash--centered" role="status">
                <?= e($flashMsg) ?>
            </div>
        <?php endif; ?>
        <div class="tracker-card">
            <header class="tracker-card__head">
                <div>
                    <h2>Goals</h2>
                    <span class="field-hint">
                        Set a target weight and PR goals for each of your
                        main lifts. Leave any field blank to clear that goal —
                        the dashboard hides progress bars without a target.
                    </span>
                </div>
                <div class="unit-toggle" id="goalsUnitToggle"
                     role="tablist" aria-label="Units">
                    <button type="button" data-unit="lbs"
                            class="<?= $goalsUnit === 'lbs' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $goalsUnit === 'lbs' ? 'true' : 'false' ?>">
                        lbs
                    </button>
                    <button type="button" data-unit="kg"
                            class="<?= $goalsUnit === 'kg' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $goalsUnit === 'kg' ? 'true' : 'false' ?>">
                        kg
                    </button>
                </div>
            </header>

            <form method="post" action="<?= url('profile/goals') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="goals_unit" id="goalsUnit" value="<?= e($goalsUnit) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label for="target_weight">
                            Target weight <span class="goals-unit-label">(<?= e($goalsUnit) ?>)</span>
                        </label>
                        <input type="number" id="target_weight" name="target_weight"
                               inputmode="decimal" step="0.1" min="0"
                               value="<?= e($gWeightVal) ?>"
                               placeholder="<?= $goalsUnit === 'lbs' ? '165' : '75' ?>"
                               <?= $errGWeight ? 'aria-invalid="true" aria-describedby="target_weight-error"' : '' ?>>
                        <?php if ($errGWeight): ?>
                            <p id="target_weight-error" class="field-error"><?= e($errGWeight) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="target_bench">
                            Bench press <span class="goals-unit-label">(<?= e($goalsUnit) ?>)</span>
                        </label>
                        <input type="number" id="target_bench" name="target_bench"
                               inputmode="decimal" step="0.1" min="0"
                               value="<?= e($gBenchVal) ?>"
                               placeholder="<?= $goalsUnit === 'lbs' ? '225' : '102' ?>"
                               <?= $errGBench ? 'aria-invalid="true" aria-describedby="target_bench-error"' : '' ?>>
                        <?php if ($errGBench): ?>
                            <p id="target_bench-error" class="field-error"><?= e($errGBench) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="target_squat">
                            Squat <span class="goals-unit-label">(<?= e($goalsUnit) ?>)</span>
                        </label>
                        <input type="number" id="target_squat" name="target_squat"
                               inputmode="decimal" step="0.1" min="0"
                               value="<?= e($gSquatVal) ?>"
                               placeholder="<?= $goalsUnit === 'lbs' ? '315' : '142' ?>"
                               <?= $errGSquat ? 'aria-invalid="true" aria-describedby="target_squat-error"' : '' ?>>
                        <?php if ($errGSquat): ?>
                            <p id="target_squat-error" class="field-error"><?= e($errGSquat) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="target_deadlift">
                            Deadlift <span class="goals-unit-label">(<?= e($goalsUnit) ?>)</span>
                        </label>
                        <input type="number" id="target_deadlift" name="target_deadlift"
                               inputmode="decimal" step="0.1" min="0"
                               value="<?= e($gDeadliftVal) ?>"
                               placeholder="<?= $goalsUnit === 'lbs' ? '405' : '184' ?>"
                               <?= $errGDeadlift ? 'aria-invalid="true" aria-describedby="target_deadlift-error"' : '' ?>>
                        <?php if ($errGDeadlift): ?>
                            <p id="target_deadlift-error" class="field-error"><?= e($errGDeadlift) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <h3 class="goals-subhead">Weekly cadence</h3>
                <p class="field-hint goals-subhead__hint">
                    Set how many days per week you aim to train, and (separately)
                    how many cardio sessions. Leave blank to skip — the dashboard
                    hides the corresponding bar without a target.
                </p>

                <div class="form-grid">

                    <div class="field">
                        <label for="weekly_workout_target">
                            Workouts per week <span class="field-hint-inline">(1–7)</span>
                        </label>
                        <input type="number" id="weekly_workout_target" name="weekly_workout_target"
                               inputmode="numeric" step="1" min="1" max="7"
                               value="<?= e($weeklyWorkoutVal) ?>"
                               placeholder="4"
                               <?= $errGWorkouts ? 'aria-invalid="true" aria-describedby="weekly_workout_target-error"' : '' ?>>
                        <?php if ($errGWorkouts): ?>
                            <p id="weekly_workout_target-error" class="field-error"><?= e($errGWorkouts) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="weekly_cardio_target">
                            Cardio sessions per week <span class="field-hint-inline">(1–7)</span>
                        </label>
                        <input type="number" id="weekly_cardio_target" name="weekly_cardio_target"
                               inputmode="numeric" step="1" min="1" max="7"
                               value="<?= e($weeklyCardioVal) ?>"
                               placeholder="3"
                               <?= $errGCardio ? 'aria-invalid="true" aria-describedby="weekly_cardio_target-error"' : '' ?>>
                        <?php if ($errGCardio): ?>
                            <p id="weekly_cardio_target-error" class="field-error"><?= e($errGCardio) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <button type="submit" class="btn btn-inline">Save goals</button>
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

        <!-- ============ DANGER ZONE ============ -->
        <?php
            $errDelPw     = field_error('confirm_password');
            $errDelPhrase = field_error('confirm_phrase');
            // Re-open the disclosure if the user just bounced off a validation
            // error so they're not staring at a closed panel.
            $delOpen = (bool) flash('delete_open') || $errDelPw || $errDelPhrase;
        ?>
        <div class="tracker-card tracker-card--danger">
            <div class="tracker-card__head">
                <h2>Delete account</h2>
            </div>
            <p class="field-hint">
                Permanently deletes your account, profile photo, and every
                logged weigh-in, meal, lift, and cardio session. This can't
                be undone.
            </p>

            <details class="danger-zone" <?= $delOpen ? 'open' : '' ?>>
                <summary class="btn btn-secondary btn-inline">
                    I want to delete my account
                </summary>

                <form method="post" action="<?= url('profile/delete') ?>"
                      class="danger-zone__form" novalidate>
                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="confirm_password">Confirm with your password</label>
                        <div class="password-wrap">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   autocomplete="current-password"
                                   <?= $errDelPw ? 'aria-invalid="true" aria-describedby="confirm_password-error"' : '' ?>
                                   required>
                            <?= password_toggle_button('confirm_password') ?>
                        </div>
                        <?php if ($errDelPw): ?>
                            <p id="confirm_password-error" class="field-error"><?= e($errDelPw) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="confirm_phrase">Type <strong>DELETE</strong> to confirm</label>
                        <input type="text" id="confirm_phrase" name="confirm_phrase"
                               autocomplete="off" autocapitalize="characters"
                               <?= $errDelPhrase ? 'aria-invalid="true" aria-describedby="confirm_phrase-error"' : 'aria-describedby="confirm_phrase-hint"' ?>
                               required>
                        <?php if ($errDelPhrase): ?>
                            <p id="confirm_phrase-error" class="field-error"><?= e($errDelPhrase) ?></p>
                        <?php else: ?>
                            <small id="confirm_phrase-hint" class="field-hint">
                                Final guard against accidental clicks.
                            </small>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-danger btn-inline"
                            data-loading-text="Deleting…">
                        Permanently delete my account
                    </button>
                </form>
            </details>
        </div>

    </div>
</section>
