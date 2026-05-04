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

                <!-- Goal picker: 3 pills posting to /calorie/goal. -->
                <form method="post" action="<?= url('calorie/goal') ?>" class="goal-picker">
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
                                   <?= $errDate ? 'aria-invalid="true"' : '' ?>
                                   required>
                            <?php if ($errDate): ?>
                                <p class="field-error"><?= e($errDate) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="calAge">Age</label>
                            <input type="number" id="calAge" name="age"
                                   inputmode="numeric" min="13" max="100" step="1"
                                   value="<?= e($pre('age')) ?>"
                                   placeholder="30"
                                   <?= $errAge ? 'aria-invalid="true"' : '' ?>
                                   required>
                            <?php if ($errAge): ?>
                                <p class="field-error"><?= e($errAge) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <span class="field-label">Sex</span>
                            <div class="radio-row" <?= $errGender ? 'aria-invalid="true"' : '' ?>>
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
                                <p class="field-error"><?= e($errGender) ?></p>
                            <?php else: ?>
                                <span class="field-hint">Used only for the calorie formula.</span>
                            <?php endif; ?>
                        </div>

                        <div class="field field--wide">
                            <label for="calActivity">Activity level</label>
                            <select id="calActivity" name="activity_level"
                                    <?= $errLevel ? 'aria-invalid="true"' : '' ?>
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
                                <p class="field-error"><?= e($errLevel) ?></p>
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
                                   <?= $errHeightFt ? 'aria-invalid="true"' : '' ?>>
                        </div>
                        <div class="field">
                            <label for="calHeightIn">Height (in)</label>
                            <input type="number" id="calHeightIn" name="height_in"
                                   inputmode="numeric" min="0" max="11" step="1"
                                   value="<?= e($pre('height_in')) ?>"
                                   placeholder="10"
                                   <?= $errHeightFt ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($errHeightFt): ?>
                                <p class="field-error"><?= e($errHeightFt) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="calWeightLb">Weight (lbs)</label>
                            <input type="number" id="calWeightLb" name="weight_lb"
                                   inputmode="decimal" min="66" max="660" step="0.1"
                                   value="<?= e($pre('weight_lb')) ?>"
                                   placeholder="170"
                                   <?= $errWeightLb ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($errWeightLb): ?>
                                <p class="field-error"><?= e($errWeightLb) ?></p>
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
                                   <?= $errHeightCm ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($errHeightCm): ?>
                                <p class="field-error"><?= e($errHeightCm) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="calWeightKg">Weight (kg)</label>
                            <input type="number" id="calWeightKg" name="weight_kg"
                                   inputmode="decimal" min="30" max="300" step="0.1"
                                   value="<?= e($pre('weight_kg')) ?>"
                                   placeholder="77"
                                   <?= $errWeightKg ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($errWeightKg): ?>
                                <p class="field-error"><?= e($errWeightKg) ?></p>
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
                    <?php foreach ($todayMeals as $meal): ?>
                        <li class="meals-list__item">
                            <span class="meals-list__label">
                                <?= e(($meal['label'] ?? '') !== '' ? $meal['label'] : 'Meal') ?>
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
                               <?= $errIntakeDate ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errIntakeDate): ?>
                            <p class="field-error"><?= e($errIntakeDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="intakeLabel">Label <span class="field-optional">(optional)</span></label>
                        <input type="text" id="intakeLabel" name="label"
                               value="<?= e($intakeLabel) ?>"
                               maxlength="50"
                               placeholder="Lunch, Pizza, Snack…"
                               <?= $errIntakeLabel ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($errIntakeLabel): ?>
                            <p class="field-error"><?= e($errIntakeLabel) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="intakeCalories">Calories</label>
                        <input type="number" id="intakeCalories" name="calories"
                               inputmode="numeric" min="0" max="20000" step="1"
                               value="<?= e($intakeCal) ?>"
                               placeholder="650"
                               <?= $errIntakeCal ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errIntakeCal): ?>
                            <p class="field-error"><?= e($errIntakeCal) ?></p>
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
<section class="section">
    <div class="container">

        <?php if (empty($intakeHistory)): ?>

            <article class="tracker-card empty-state">
                <h2>No meals logged yet</h2>
                <p>Add your first meal above to start seeing how your day stacks up.</p>
            </article>

        <?php else: ?>

            <?php
                // Per-day totals + entry counts so the table can show one
                // "Day total / vs target" cell per group via rowspan.
                $dailyTotals      = [];
                $dailyEntryCounts = [];
                foreach ($intakeHistory as $r) {
                    $d = $r['logged_date'];
                    $dailyTotals[$d]      = ($dailyTotals[$d]      ?? 0) + (int) $r['calories'];
                    $dailyEntryCounts[$d] = ($dailyEntryCounts[$d] ?? 0) + 1;
                }
                $prevDate = null;
            ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>Intake over time</h2>
                    <span class="field-hint">
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
                        · newest first
                    </span>
                </header>

                <div class="history-scroll">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Meal</th>
                                <th scope="col" class="num">Calories</th>
                                <th scope="col" class="num">Day total</th>
                                <?php if ($latest): ?>
                                    <th scope="col">vs <?= e($activeMeta['short']) ?></th>
                                <?php endif; ?>
                                <th scope="col" class="actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($intakeHistory as $row):
                                $d              = $row['logged_date'];
                                $isFirstOfGroup = ($d !== $prevDate);
                                $rowspan        = $isFirstOfGroup ? $dailyEntryCounts[$d] : 0;
                                $dayTotal       = $dailyTotals[$d];
                                $diff           = $latest ? $dayTotal - $activeTarget : null;
                                $label          = ($row['label'] ?? '') !== '' ? $row['label'] : 'Meal';
                            ?>
                                <tr<?= $isFirstOfGroup ? ' class="row-group-start"' : '' ?>>
                                    <?php if ($isFirstOfGroup): ?>
                                        <td rowspan="<?= e((string) $rowspan) ?>"
                                            class="cell-date">
                                            <?= e($fmtDate($d)) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= e($label) ?></td>
                                    <td class="num"><?= e(number_format((int) $row['calories'])) ?></td>
                                    <?php if ($isFirstOfGroup): ?>
                                        <td rowspan="<?= e((string) $rowspan) ?>"
                                            class="num strong">
                                            <?= e(number_format($dayTotal)) ?>
                                        </td>
                                        <?php if ($latest): ?>
                                            <td rowspan="<?= e((string) $rowspan) ?>">
                                                <?php if ($diff < 0): ?>
                                                    <span class="text-good"><?= e(number_format(abs($diff))) ?> under</span>
                                                <?php elseif ($diff > 0): ?>
                                                    <span class="text-warn"><?= e(number_format($diff)) ?> over</span>
                                                <?php else: ?>
                                                    <span>at target</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
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
                                <?php $prevDate = $d; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

        <?php endif; ?>

    </div>
</section>

<!-- Chart.js — only loaded on tracker pages that need it. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
