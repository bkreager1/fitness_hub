<?php
// ============================================================
// app/views/calorie/index.php
// Phase 7 — Calorie tracker (redesigned).
//
// Three sections:
//   1. "Your daily targets" — current cut/maintain/bulk numbers
//      pulled from the latest calorie_logs row, with an
//      "Update my stats" button that reveals the calc form.
//   2. "Today's meals"        — list of today's entries + "Add meal" form
//   3. "Intake over time"     — chart of daily totals vs target + history
//
// Variables expected from CalorieController::index():
//   $today              ISO date for "today"
//   $latest             latest calorie_logs row (or null)
//   $intakeHistory      per-entry history rows newest-first (table)
//   $intakeChartData    per-day totals oldest-first (chart)
//   $todayMeals         today's individual entries (oldest-first)
//   $todayTotal         summed calories for today (int)
//   $levels             CalorieLog::ACTIVITY_LEVELS map
//   $prefill            stats form prefill (or [])
//   $statsFormOpen      bool — force the stats form open after a fail
// ============================================================

// ----- Errors per field (targets form) -------------------------------
$errAge      = field_error('age');
$errGender   = field_error('gender');
$errLevel    = field_error('activity_level');
$errDate     = field_error('logged_date');
$errHeightFt = field_error('height_ft');
$errWeightLb = field_error('weight_lb');
$errHeightCm = field_error('height_cm');
$errWeightKg = field_error('weight_kg');

// ----- Errors per field (intake form) --------------------------------
$errIntakeDate  = field_error('intake_logged_date');
$errIntakeCal   = field_error('intake_calories');
$errIntakeLabel = field_error('intake_label');

// ----- Targets form state --------------------------------------------
$unitSystem = old('unit_system', 'us');
$loggedDate = old('logged_date', $today);

$prefill ??= [];
$pre = static fn(string $key): string =>
    old($key) !== '' ? old($key) : ($prefill[$key] ?? '');

// ----- Intake form state ---------------------------------------------
$intakeDate = old('intake_logged_date') !== ''
    ? old('intake_logged_date')
    : $today;

// Calories field is empty by default — each meal is its own entry,
// so there's no "previous total" to seed it with.
$intakeCal   = old('intake_calories');
$intakeLabel = old('intake_label');

// ----- Goal lookups (drives the dynamic copy + highlight) ------------
// activeGoal is one of 'cut' | 'maintain' | 'bulk' from the controller.
$activeGoal ??= 'maintain';

// Maps the goal key to the calorie_logs column name + a friendly label.
// Used everywhere we need to talk about "your <goal> target".
$GOAL_TARGETS = [
    'cut'      => ['column' => 'cutting_calories',     'label' => 'cut',         'short' => 'cut'],
    'maintain' => ['column' => 'maintenance_calories', 'label' => 'maintenance', 'short' => 'maintenance'],
    'bulk'     => ['column' => 'bulking_calories',     'label' => 'bulk',        'short' => 'bulk'],
];
$activeMeta   = $GOAL_TARGETS[$activeGoal];
$activeTarget = $latest ? (int) $latest[$activeMeta['column']] : null;

// ----- Today vs target message ---------------------------------------
$todayVsTarget = null;
if ($latest && $todayTotal > 0) {
    $diff = $todayTotal - $activeTarget;
    $todayVsTarget = [
        'today'  => $todayTotal,
        'target' => $activeTarget,
        'diff'   => $diff,
        'label'  => $activeMeta['label'],
    ];
}

// ----- Inline date display helper ------------------------------------
$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Calorie tracker</span>
        <h1>Track calories — hit your targets.</h1>
        <p class="hero-lede">
            Calculate your calorie targets once, then log how many calories you
            eat each day. The chart below shows you whether you're staying in
            your cut, maintenance, or bulk range over time.
        </p>
    </div>
</section>


<!-- ===================== Section 1 — Targets card ===================== -->
<section class="section">
    <div class="container">
        <a class="back-link" href="<?= url('dashboard') ?>">
            <span class="back-link__arrow" aria-hidden="true">&larr;</span>
            Back to dashboard
        </a>

        <?php if ($flashMsg = flash('targets_success')): ?>
            <div class="flash flash-success flash--centered" role="status">
                <?= e($flashMsg) ?>
            </div>
        <?php endif; ?>

        <article class="tracker-card">

            <header class="tracker-card__head">
                <?php if ($latest): ?>
                    <div>
                        <h2>Your daily targets</h2>
                        <span class="field-hint">
                            Last calculated <?= e($fmtDate($latest['logged_date'])) ?>.
                        </span>
                    </div>
                    <button type="button" class="btn btn-secondary btn-inline"
                            id="targetsToggle"
                            aria-expanded="<?= $statsFormOpen ? 'true' : 'false' ?>"
                            aria-controls="statsFormWrap"
                            data-label-open="Cancel"
                            data-label-closed="Update my stats">
                        <?= $statsFormOpen ? 'Cancel' : 'Update my stats' ?>
                    </button>
                <?php else: ?>
                    <div>
                        <h2>Set your daily targets</h2>
                        <span class="field-hint">
                            Enter your stats once to calculate your cut, maintenance, and bulk targets.
                        </span>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($latest): ?>
                <!-- Read-only target display. The active goal's cell gets
                     the orange accent so the user always sees their
                     "current target" highlighted. -->
                <div class="cal-preview cal-preview--inline">
                    <div class="cal-preview__grid">
                        <div class="cal-preview__cell <?= $activeGoal === 'cut' ? 'cal-preview__cell--accent' : '' ?>"
                             data-goal="cut">
                            <span class="cal-preview__label">Cut</span>
                            <span class="cal-preview__value"><?= e(number_format((int) $latest['cutting_calories'])) ?></span>
                            <span class="cal-preview__unit">cal / day</span>
                        </div>
                        <div class="cal-preview__cell <?= $activeGoal === 'maintain' ? 'cal-preview__cell--accent' : '' ?>"
                             data-goal="maintenance">
                            <span class="cal-preview__label">Maintenance</span>
                            <span class="cal-preview__value"><?= e(number_format((int) $latest['maintenance_calories'])) ?></span>
                            <span class="cal-preview__unit">cal / day</span>
                        </div>
                        <div class="cal-preview__cell <?= $activeGoal === 'bulk' ? 'cal-preview__cell--accent' : '' ?>"
                             data-goal="bulk">
                            <span class="cal-preview__label">Bulk</span>
                            <span class="cal-preview__value"><?= e(number_format((int) $latest['bulking_calories'])) ?></span>
                            <span class="cal-preview__unit">cal / day</span>
                        </div>
                    </div>
                </div>

                <!-- Goal picker: 3 pills posting to /calorie/goal.
                     data-no-loading opts the form out of the global
                     submit-spinner — the page reload is fast and a
                     loading state on a toggle just adds friction. -->
                <form method="post" action="<?= url('calorie/goal') ?>"
                      class="goal-picker" data-no-loading>
                    <?= csrf_field() ?>
                    <span class="field-label goal-picker__label">I'm currently:</span>
                    <div class="goal-picker__pills">
                        <button type="submit" name="goal" value="cut"
                                class="goal-pill <?= $activeGoal === 'cut' ? 'is-active' : '' ?>"
                                aria-pressed="<?= $activeGoal === 'cut' ? 'true' : 'false' ?>">
                            Cutting
                        </button>
                        <button type="submit" name="goal" value="maintain"
                                class="goal-pill <?= $activeGoal === 'maintain' ? 'is-active' : '' ?>"
                                aria-pressed="<?= $activeGoal === 'maintain' ? 'true' : 'false' ?>">
                            Maintaining
                        </button>
                        <button type="submit" name="goal" value="bulk"
                                class="goal-pill <?= $activeGoal === 'bulk' ? 'is-active' : '' ?>"
                                aria-pressed="<?= $activeGoal === 'bulk' ? 'true' : 'false' ?>">
                            Bulking
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Stats form: collapsed when targets exist, open on first visit
                 or after a validation error sent the user back. -->
            <div id="statsFormWrap" class="targets-form-wrap"
                <?= ($latest && !$statsFormOpen) ? 'hidden' : '' ?>>

                <?php if ($latest): ?>
                    <div class="targets-form-divider">
                        <span>Update your stats</span>
                    </div>
                <?php endif; ?>

                <div class="unit-toggle-row">
                    <span class="field-label">Units</span>
                    <div class="unit-toggle" id="calUnitToggle"
                         role="tablist" aria-label="Units">
                        <button type="button" data-unit="us"
                                class="<?= $unitSystem === 'us' ? 'is-active' : '' ?>"
                                role="tab"
                                aria-selected="<?= $unitSystem === 'us' ? 'true' : 'false' ?>">
                            US
                        </button>
                        <button type="button" data-unit="metric"
                                class="<?= $unitSystem === 'metric' ? 'is-active' : '' ?>"
                                role="tab"
                                aria-selected="<?= $unitSystem === 'metric' ? 'true' : 'false' ?>">
                            Metric
                        </button>
                    </div>
                </div>

                <form method="post" action="<?= url('calorie/targets') ?>"
                      id="calorieForm" novalidate>
                    <?= csrf_field() ?>

                    <input type="hidden" name="unit_system"
                           id="calUnitSystem" value="<?= e($unitSystem) ?>">

                    <div class="form-grid">

                        <div class="field">
                            <label for="calDate">Date</label>
                            <input type="date" id="calDate" name="logged_date"
                                   value="<?= e($loggedDate) ?>"
                                   max="<?= e($today) ?>"
                                   <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                                   required>
                            <?php if ($errDate): ?>
                                <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="calAge">Age</label>
                            <input type="number" id="calAge" name="age"
                                   inputmode="numeric" min="13" max="100" step="1"
                                   value="<?= e($pre('age')) ?>"
                                   placeholder="30"
                                   <?= $errAge ? 'aria-invalid="true" aria-describedby="age-error"' : '' ?>
                                   required>
                            <?php if ($errAge): ?>
                                <p id="age-error" class="field-error"><?= e($errAge) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <span class="field-label">Sex</span>
                            <div class="radio-row"
                                 <?= $errGender
                                    ? 'aria-invalid="true" aria-describedby="gender-error"'
                                    : 'aria-describedby="gender-hint"' ?>>
                                <label class="radio-pill">
                                    <input type="radio" name="gender" value="male"
                                           <?= $pre('gender') === 'male' ? 'checked' : '' ?>>
                                    <span>Male</span>
                                </label>
                                <label class="radio-pill">
                                    <input type="radio" name="gender" value="female"
                                           <?= $pre('gender') === 'female' ? 'checked' : '' ?>>
                                    <span>Female</span>
                                </label>
                            </div>
                            <?php if ($errGender): ?>
                                <p id="gender-error" class="field-error"><?= e($errGender) ?></p>
                            <?php else: ?>
                                <span id="gender-hint" class="field-hint">Used only for the calorie formula.</span>
                            <?php endif; ?>
                        </div>

                        <div class="field field--wide">
                            <label for="calActivity">Activity level</label>
                            <select id="calActivity" name="activity_level"
                                    <?= $errLevel ? 'aria-invalid="true" aria-describedby="activity_level-error"' : '' ?>
                                    required>
                                <option value="">Choose an activity level…</option>
                                <?php foreach ($levels as $key => $label): ?>
                                    <option value="<?= e($key) ?>"
                                        <?= $pre('activity_level') === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($errLevel): ?>
                                <p id="activity_level-error" class="field-error"><?= e($errLevel) ?></p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- US pane -->
                    <div class="form-grid" data-cal-pane="us"
                         <?= $unitSystem === 'us' ? '' : 'hidden' ?>>
                        <div class="field">
                            <label for="calHeightFt">Height (ft)</label>
                            <input type="number" id="calHeightFt" name="height_ft"
                                   inputmode="numeric" min="3" max="8" step="1"
                                   value="<?= e($pre('height_ft')) ?>"
                                   placeholder="5"
                                   <?= $errHeightFt ? 'aria-invalid="true" aria-describedby="height_ft-error"' : '' ?>>
                        </div>
                        <div class="field">
                            <label for="calHeightIn">Height (in)</label>
                            <input type="number" id="calHeightIn" name="height_in"
                                   inputmode="numeric" min="0" max="11" step="1"
                                   value="<?= e($pre('height_in')) ?>"
                                   placeholder="10"
                                   <?= $errHeightFt ? 'aria-invalid="true" aria-describedby="height_ft-error"' : '' ?>>
                            <?php if ($errHeightFt): ?>
                                <p id="height_ft-error" class="field-error"><?= e($errHeightFt) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="calWeightLb">Weight (lbs)</label>
                            <input type="number" id="calWeightLb" name="weight_lb"
                                   inputmode="decimal" min="66" max="660" step="0.1"
                                   value="<?= e($pre('weight_lb')) ?>"
                                   placeholder="170"
                                   <?= $errWeightLb ? 'aria-invalid="true" aria-describedby="weight_lb-error"' : '' ?>>
                            <?php if ($errWeightLb): ?>
                                <p id="weight_lb-error" class="field-error"><?= e($errWeightLb) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Metric pane -->
                    <div class="form-grid" data-cal-pane="metric"
                         <?= $unitSystem === 'metric' ? '' : 'hidden' ?>>
                        <div class="field">
                            <label for="calHeightCm">Height (cm)</label>
                            <input type="number" id="calHeightCm" name="height_cm"
                                   inputmode="decimal" min="100" max="250" step="0.1"
                                   value="<?= e($pre('height_cm')) ?>"
                                   placeholder="178"
                                   <?= $errHeightCm ? 'aria-invalid="true" aria-describedby="height_cm-error"' : '' ?>>
                            <?php if ($errHeightCm): ?>
                                <p id="height_cm-error" class="field-error"><?= e($errHeightCm) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="calWeightKg">Weight (kg)</label>
                            <input type="number" id="calWeightKg" name="weight_kg"
                                   inputmode="decimal" min="30" max="300" step="0.1"
                                   value="<?= e($pre('weight_kg')) ?>"
                                   placeholder="77"
                                   <?= $errWeightKg ? 'aria-invalid="true" aria-describedby="weight_kg-error"' : '' ?>>
                            <?php if ($errWeightKg): ?>
                                <p id="weight_kg-error" class="field-error"><?= e($errWeightKg) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- JS-driven preview, revealed by Calculate -->
                    <div class="cal-preview" id="calPreview" hidden aria-live="polite">
                        <div class="cal-preview__head">
                            <h3>New targets preview</h3>
                            <span class="field-hint">Save to keep these as your current targets.</span>
                        </div>
                        <div class="cal-preview__grid">
                            <div class="cal-preview__cell" data-goal="cut">
                                <span class="cal-preview__label">Cut</span>
                                <span class="cal-preview__value" id="calPrevCut">—</span>
                                <span class="cal-preview__unit">cal / day</span>
                            </div>
                            <div class="cal-preview__cell cal-preview__cell--accent" data-goal="maintenance">
                                <span class="cal-preview__label">Maintenance</span>
                                <span class="cal-preview__value" id="calPrevMaintenance">—</span>
                                <span class="cal-preview__unit">cal / day</span>
                            </div>
                            <div class="cal-preview__cell" data-goal="bulk">
                                <span class="cal-preview__label">Bulk</span>
                                <span class="cal-preview__value" id="calPrevBulk">—</span>
                                <span class="cal-preview__unit">cal / day</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary btn-inline"
                                id="calCalculate">
                            Calculate
                        </button>
                        <button type="submit" class="btn btn-inline"
                                id="calSave" hidden>
                            <?= $latest ? 'Save updated targets' : 'Save my targets' ?>
                        </button>
                    </div>

                </form>
            </div>

        </article>
    </div>
</section>


<!-- ===================== Section 2 — Daily intake ===================== -->
<section class="section section--alt">
    <div class="container">

        <?php if ($flashMsg = flash('intake_success')): ?>
            <div class="flash flash-success flash--centered" role="status">
                <?= e($flashMsg) ?>
            </div>
        <?php endif; ?>

        <article class="tracker-card">

            <header class="tracker-card__head">
                <div>
                    <h2>Today's meals</h2>
                    <?php if ($todayVsTarget): ?>
                        <span class="field-hint">
                            You're at <strong><?= e(number_format($todayVsTarget['today'])) ?></strong>
                            cal today —
                            <?php if ($todayVsTarget['diff'] < 0): ?>
                                <span class="text-good"><?= e(number_format(abs($todayVsTarget['diff']))) ?>
                                under</span> your <?= e($todayVsTarget['label']) ?> target.
                            <?php elseif ($todayVsTarget['diff'] > 0): ?>
                                <span class="text-warn"><?= e(number_format($todayVsTarget['diff'])) ?>
                                over</span> your <?= e($todayVsTarget['label']) ?> target.
                            <?php else: ?>
                                right on your <?= e($todayVsTarget['label']) ?> target.
                            <?php endif; ?>
                        </span>
                    <?php elseif ($latest): ?>
                        <span class="field-hint">
                            Aim for <strong><?= e(number_format($activeTarget)) ?></strong>
                            cal to hit your <?= e($activeMeta['label']) ?> target.
                        </span>
                    <?php else: ?>
                        <span class="field-hint">
                            Set your stats above first to see how today's intake stacks up vs your targets.
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (!empty($todayMeals)): ?>
                <ul class="meals-list">
                    <?php foreach ($todayMeals as $i => $meal): ?>
                        <li class="meals-list__item">
                            <span class="meals-list__label">
                                <?= e(($meal['label'] ?? '') !== '' ? $meal['label'] : 'Meal ' . ($i + 1)) ?>
                            </span>
                            <span class="meals-list__value">
                                <?= e(number_format((int) $meal['calories'])) ?> cal
                            </span>
                            <span class="meals-list__actions">
                                <a class="btn-link"
                                   href="<?= url('calorie/intake/edit?id=' . (int) $meal['id']) ?>">
                                    Edit
                                </a>
                                <form method="post" action="<?= url('calorie/intake/delete') ?>"
                                      onsubmit="return confirm('Remove this meal?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $meal['id']) ?>">
                                    <button type="submit" class="btn-link-danger"
                                            aria-label="Remove this meal">
                                        Delete
                                    </button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <li class="meals-list__total">
                        <span>Total today</span>
                        <strong><?= e(number_format($todayTotal)) ?> cal</strong>
                    </li>
                </ul>
            <?php endif; ?>

            <div class="meals-add-divider">
                <span>Add a meal</span>
            </div>

            <form method="post" action="<?= url('calorie/intake') ?>"
                  id="intakeForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">

                    <div class="field">
                        <label for="intakeDate">Date</label>
                        <input type="date" id="intakeDate" name="logged_date"
                               value="<?= e($intakeDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errIntakeDate ? 'aria-invalid="true" aria-describedby="intake_logged_date-error"' : '' ?>
                               required>
                        <?php if ($errIntakeDate): ?>
                            <p id="intake_logged_date-error" class="field-error"><?= e($errIntakeDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="intakeLabel">Label <span class="field-optional">(optional)</span></label>
                        <input type="text" id="intakeLabel" name="label"
                               value="<?= e($intakeLabel) ?>"
                               maxlength="50"
                               placeholder="Lunch, Pizza, Snack…"
                               <?= $errIntakeLabel ? 'aria-invalid="true" aria-describedby="intake_label-error"' : '' ?>>
                        <?php if ($errIntakeLabel): ?>
                            <p id="intake_label-error" class="field-error"><?= e($errIntakeLabel) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="intakeCalories">Calories</label>
                        <input type="number" id="intakeCalories" name="calories"
                               inputmode="numeric" min="0" max="20000" step="1"
                               value="<?= e($intakeCal) ?>"
                               placeholder="650"
                               <?= $errIntakeCal ? 'aria-invalid="true" aria-describedby="intake_calories-error"' : '' ?>
                               required>
                        <?php if ($errIntakeCal): ?>
                            <p id="intake_calories-error" class="field-error"><?= e($errIntakeCal) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">
                        Add meal
                    </button>
                </div>

            </form>

        </article>
    </div>
</section>


<!-- ===================== Section 3 — History ===================== -->
<?php
    // Compact labels for the range picker. Keys must match what the
    // controller validates against (CalorieController::ALLOWED_RANGES).
    $RANGE_OPTIONS = [
        '7'   => '7 days',
        '30'  => '30 days',
        '90'  => '90 days',
        'all' => 'All time',
    ];
    $rangeLabel = $RANGE_OPTIONS[$range] ?? '30 days';
?>
<section class="section">
    <div class="container">

        <?php if ($totalLoggedDays === 0): ?>

            <article class="tracker-card empty-state">
                <h2>No meals logged yet</h2>
                <p>Add your first meal above to start seeing how your day stacks up.</p>
            </article>

        <?php else: ?>

            <!-- Section toolbar — controls the timeline view (chart +
                 history together). Sits above both cards as a "this
                 scopes everything below" affordance, styled with a
                 subtle bottom divider so it reads as a section header
                 rather than a card or a per-card filter. -->
            <div class="section-toolbar">
                <div class="section-toolbar__heading">
                    <span class="section-toolbar__title">Timeline</span>
                    <span class="section-toolbar__hint">
                        Range applies to both the chart and history.
                    </span>
                </div>
                <!-- data-no-loading skips the submit-spinner since the
                     page reload is fast and a spinner on a filter pill
                     just adds friction. -->
                <form method="get" action="<?= url('calorie') ?>"
                      class="range-picker-row" data-no-loading>
                    <span class="range-picker-row__label">Showing:</span>
                    <div class="unit-toggle">
                        <?php foreach ($RANGE_OPTIONS as $key => $label):
                            // PHP coerces numeric string array keys to int on
                            // foreach, so $key is int(7)/int(30)/int(90) but
                            // 'all' stays a string. Cast to string here so
                            // the strict $range === $keyStr check actually
                            // matches the numeric ranges.
                            $keyStr = (string) $key;
                        ?>
                            <button type="submit" name="range" value="<?= e($keyStr) ?>"
                                    class="<?= $range === $keyStr ? 'is-active' : '' ?>"
                                    aria-pressed="<?= $range === $keyStr ? 'true' : 'false' ?>">
                                <?= e($label) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <?php if (empty($intakeHistory)): ?>

                <article class="tracker-card empty-state">
                    <h2>No meals in this range</h2>
                    <p>
                        You've logged meals on
                        <?= e((string) $totalLoggedDays) ?>
                        day<?= $totalLoggedDays === 1 ? '' : 's' ?> total
                        <?php if ($range !== 'all'): ?>
                            — try
                            <a class="link-inline" href="<?= url('calorie?range=all') ?>">All time</a>
                            to see the full list.
                        <?php else: ?>.
                        <?php endif; ?>
                    </p>
                </article>

            <?php else: ?>

            <?php
                // Per-day totals so the day-row can show one number per
                // group instead of a per-meal breakdown by default.
                $dailyTotals = [];
                $byDate      = [];
                foreach ($intakeHistory as $r) {
                    $d = $r['logged_date'];
                    $dailyTotals[$d] = ($dailyTotals[$d] ?? 0) + (int) $r['calories'];
                    $byDate[$d][]    = $r;
                }
                // Per-day chronological meal numbering: meal 1 = oldest of
                // that day. History is id DESC within day, so reverse to
                // count chronologically before stamping numbers.
                $mealNumbers = [];
                foreach ($byDate as $rows) {
                    $rows = array_reverse($rows);
                    foreach ($rows as $i => $r) {
                        $mealNumbers[(int) $r['id']] = $i + 1;
                    }
                }
            ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>Intake over time</h2>
                    <span class="field-hint">
                        <?= e($range === 'all' ? 'All time' : 'Last ' . $rangeLabel) ?> ·
                        <?= $latest
                            ? 'Bars are your daily intake. Lines are your cut, maintenance, and bulk targets.'
                            : 'Bars are your daily intake. Set targets above to overlay your goal lines.' ?>
                    </span>
                </header>
                <div class="chart-wrap">
                    <canvas id="intakeChart"
                            data-rows='<?= e(json_encode($intakeChartData, JSON_THROW_ON_ERROR)) ?>'
                            data-targets='<?= e(json_encode($latest ? [
                                'cut'         => (int) $latest['cutting_calories'],
                                'maintenance' => (int) $latest['maintenance_calories'],
                                'bulk'        => (int) $latest['bulking_calories'],
                            ] : null, JSON_THROW_ON_ERROR)) ?>'
                            data-active-goal="<?= e($activeGoal) ?>">
                    </canvas>
                </div>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>History</h2>
                    <span class="field-hint">
                        <?= count($intakeHistory) ?> meal<?= count($intakeHistory) === 1 ? '' : 's' ?>
                        across <?= count($dailyTotals) ?> day<?= count($dailyTotals) === 1 ? '' : 's' ?>
                        · <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>
                        · newest first
                    </span>
                </header>

                <div class="history-scroll">
                    <table class="history-table history-table--days">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Meal</th>
                                <th scope="col" class="num">Calories</th>
                                <?php if ($latest): ?>
                                    <th scope="col">vs <?= e($activeMeta['short']) ?></th>
                                <?php endif; ?>
                                <th scope="col" class="actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byDate as $d => $rows):
                                $count    = count($rows);
                                $dayTotal = $dailyTotals[$d];
                                $diff     = $latest ? $dayTotal - $activeTarget : null;
                            ?>
                                <!-- Day summary row (always visible). JS injects
                                     a chevron toggle into the date cell on load
                                     and hides the meal-rows below until clicked. -->
                                <tr class="day-row" data-day="<?= e($d) ?>">
                                    <td class="cell-date cell-date--day">
                                        <?= e($fmtDate($d)) ?>
                                    </td>
                                    <td class="cell-meal-count">
                                        <?= $count ?> meal<?= $count === 1 ? '' : 's' ?>
                                    </td>
                                    <td class="num strong">
                                        <?= e(number_format($dayTotal)) ?>
                                    </td>
                                    <?php if ($latest): ?>
                                        <td>
                                            <?php if ($diff < 0): ?>
                                                <span class="text-good"><?= e(number_format(abs($diff))) ?> under</span>
                                            <?php elseif ($diff > 0): ?>
                                                <span class="text-warn"><?= e(number_format($diff)) ?> over</span>
                                            <?php else: ?>
                                                <span>at target</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="actions"></td>
                                </tr>
                                <?php foreach ($rows as $row):
                                    $label = ($row['label'] ?? '') !== ''
                                        ? $row['label']
                                        : 'Meal ' . $mealNumbers[(int) $row['id']];
                                ?>
                                    <tr class="meal-row" data-day="<?= e($d) ?>">
                                        <td class="cell-date cell-date--indent"></td>
                                        <td><?= e($label) ?></td>
                                        <td class="num"><?= e(number_format((int) $row['calories'])) ?></td>
                                        <?php if ($latest): ?>
                                            <td></td>
                                        <?php endif; ?>
                                        <td class="actions">
                                            <a class="btn-link"
                                               href="<?= url('calorie/intake/edit?id=' . (int) $row['id']) ?>">
                                                Edit
                                            </a>
                                            <form method="post" action="<?= url('calorie/intake/delete') ?>"
                                                  onsubmit="return confirm('Delete this meal?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                                <button type="submit" class="btn-link-danger"
                                                        aria-label="Delete meal">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <?php endif; /* end of: $intakeHistory empty within range */ ?>

        <?php endif; /* end of: $totalLoggedDays === 0 */ ?>

    </div>
</section>

<!-- Chart.js — only loaded on tracker pages that need it. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
