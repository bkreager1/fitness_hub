<?php
// ============================================================
// app/views/strength/index.php
// Phase 7 — Strength tracker.
//
// Sections:
//   1. New lift form (date + lift + weight + reps + unit + notes)
//   2. Chart (3 lines: bench/squat/deadlift, Y = est. 1RM) + history
//
// Variables expected from StrengthController::index():
//   $today        ISO date for "today"
//   $history      rows newest-first (for the table)
//   $latest       most recent row, or null
//   $chartRows    rows oldest-first, shaped { date, lift_type, weight_kg, reps }
//   $defaultUnit  'lbs' or 'kg' — pre-selected unit on the form + chart
//   $liftLabels   ['bench' => 'Bench press', ...]
// ============================================================

$errLift   = field_error('lift_type');
$errUnit   = field_error('unit');
$errWeight = field_error('weight');
$errReps   = field_error('reps');
$errDate   = field_error('logged_date');
$errNotes  = field_error('notes');

$liftType   = old('lift_type');
$unit       = old('unit')        ?: $defaultUnit;
$loggedDate = old('logged_date') ?: $today;
$weightVal  = old('weight');
$repsVal    = old('reps');
$notesVal   = old('notes');

$placeholderWeight = $unit === 'lbs' ? '225' : '102';

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};

// Latest summary line — show in form header if there's a recent lift.
$latestLine = null;
if ($latest) {
    $latestLine = sprintf(
        'Last lift: %s %s × %s %s on %s',
        $liftLabels[$latest['lift_type']] ?? $latest['lift_type'],
        rtrim(rtrim((string) $latest['weight'], '0'), '.'),
        $latest['reps'],
        $latest['unit'],
        $fmtDate($latest['logged_date'])
    );
}
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Strength tracker</span>
        <div class="hero-heading-row">
            <h1>Bench, squat, deadlift — chart your big three.</h1>
            <img class="hero-icon"
                 src="<?= asset('images/strengthlogo.png') ?>"
                 alt="" width="96" height="96">
        </div>
        <p class="hero-lede">
            Log each lift with weight and reps. The chart estimates your
            one-rep max over time, so you can compare progress even when
            your rep ranges change.
        </p>
    </div>
</section>


<!-- ===================== Form card ===================== -->
<section class="section">
    <div class="container">
        <a class="back-link" href="<?= url('dashboard') ?>">
            <span class="back-link__arrow" aria-hidden="true">&larr;</span>
            Back to dashboard
        </a>

        <?php if ($flashMsg = flash('success')): ?>
            <div class="flash flash-success flash--centered" role="status">
                <?= e($flashMsg) ?>
            </div>
        <?php endif; ?>

        <article class="tracker-card">

            <header class="tracker-card__head">
                <div>
                    <h2>Log a lift</h2>
                    <?php if ($latestLine): ?>
                        <span class="field-hint"><?= e($latestLine) ?>.</span>
                    <?php else: ?>
                        <span class="field-hint">
                            Your first lift goes here. After that we'll show your latest right alongside the form.
                        </span>
                    <?php endif; ?>
                    <span class="field-hint">
                        Tip: log your top working set for each lift. Weekly
                        entries usually give the cleanest progress trend.
                    </span>
                </div>
                <div class="unit-toggle" id="strengthUnitToggle"
                     role="tablist" aria-label="Units">
                    <button type="button" data-unit="lbs"
                            class="<?= $unit === 'lbs' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $unit === 'lbs' ? 'true' : 'false' ?>">
                        lbs
                    </button>
                    <button type="button" data-unit="kg"
                            class="<?= $unit === 'kg' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $unit === 'kg' ? 'true' : 'false' ?>">
                        kg
                    </button>
                </div>
            </header>

            <form method="post" action="<?= url('strength') ?>"
                  id="strengthForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="unit" id="strengthUnit" value="<?= e($unit) ?>">

                <!-- Lift picker (3 pills, same look as the goal picker) -->
                <div class="field field--wide">
                    <span class="field-label">Lift</span>
                    <div class="goal-picker__pills <?= $errLift ? 'is-invalid' : '' ?>"
                         role="radiogroup" aria-label="Lift type"
                         <?= $errLift ? 'aria-describedby="lift_type-error"' : '' ?>>
                        <?php foreach ($liftLabels as $key => $label): ?>
                            <label class="goal-pill goal-pill--radio">
                                <input type="radio" name="lift_type" value="<?= e($key) ?>"
                                       <?= $liftType === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($errLift): ?>
                        <p id="lift_type-error" class="field-error"><?= e($errLift) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-grid">

                    <div class="field">
                        <label for="strengthDate">Date</label>
                        <input type="date" id="strengthDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="strengthWeight">
                            Weight <span class="weight-unit-label" id="strengthUnitLabel">(<?= e($unit) ?>)</span>
                        </label>
                        <input type="number" id="strengthWeight" name="weight"
                               inputmode="decimal" step="0.5"
                               min="<?= $unit === 'lbs' ? '1' : '1' ?>"
                               max="<?= $unit === 'lbs' ? '1500' : '700' ?>"
                               value="<?= e($weightVal) ?>"
                               placeholder="<?= e($placeholderWeight) ?>"
                               <?= $errWeight ? 'aria-invalid="true" aria-describedby="weight-error"' : '' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p id="weight-error" class="field-error"><?= e($errWeight) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="strengthReps">Reps</label>
                        <input type="number" id="strengthReps" name="reps"
                               inputmode="numeric" step="1" min="1" max="30"
                               value="<?= e($repsVal) ?>"
                               placeholder="5"
                               <?= $errReps ? 'aria-invalid="true" aria-describedby="reps-error"' : '' ?>
                               required>
                        <?php if ($errReps): ?>
                            <p id="reps-error" class="field-error"><?= e($errReps) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="field field--wide">
                    <label for="strengthNotes">
                        Notes <span class="field-hint-inline">(optional)</span>
                    </label>
                    <textarea id="strengthNotes" name="notes"
                              rows="2" maxlength="300"
                              placeholder="Add any notes about this entry..."
                              <?= $errNotes ? 'aria-invalid="true" aria-describedby="notes-error"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p id="notes-error" class="field-error"><?= e($errNotes) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Log lift</button>
                </div>
            </form>

        </article>
    </div>
</section>


<!-- ===================== History (chart + table) ===================== -->
<?php
    // Compact labels for the range picker. Keys must match what the
    // controller validates against (StrengthController::ALLOWED_RANGES).
    $RANGE_OPTIONS = [
        '7'   => '7 days',
        '30'  => '30 days',
        '90'  => '90 days',
        'all' => 'All time',
    ];
    $rangeLabel = $RANGE_OPTIONS[$range] ?? '30 days';
?>
<section class="section section--alt">
    <div class="container">

        <?php if ($totalLoggedDays === 0): ?>

            <article class="tracker-card empty-state">
                <?= empty_state_icon() ?>
                <h2>No lifts logged yet</h2>
                <p>Add your first bench, squat, or deadlift entry above to start tracking progress.</p>
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
                <form method="get" action="<?= url('strength') ?>"
                      class="range-picker-row" data-no-loading>
                    <span class="range-picker-row__label">Showing:</span>
                    <div class="unit-toggle">
                        <?php foreach ($RANGE_OPTIONS as $key => $label):
                            // PHP coerces numeric string array keys to int
                            // on foreach, so cast to string before strict
                            // comparison against $range.
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

            <?php if (empty($history)): ?>

                <article class="tracker-card empty-state">
                    <?= empty_state_icon() ?>
                    <h2>No lifts in this range</h2>
                    <p>
                        You've logged lifts on
                        <?= e((string) $totalLoggedDays) ?>
                        day<?= $totalLoggedDays === 1 ? '' : 's' ?> total
                        <?php if ($range !== 'all'): ?>
                            — try
                            <a class="link-inline" href="<?= url('strength?range=all') ?>">All time</a>
                            to see the full list.
                        <?php else: ?>.
                        <?php endif; ?>
                    </p>
                </article>

            <?php else: ?>

            <?php
                // Group rows by day so the history can render one
                // summary row per day (collapsed by default) plus the
                // individual lift rows beneath it. Multiple lift
                // attempts are common per session, so this keeps the
                // table scannable as logs grow.
                $byDate = [];
                foreach ($history as $r) {
                    $byDate[$r['logged_date']][] = $r;
                }
            ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Estimated 1RM over time</h2>
                        <span class="field-hint">
                            <?= e($range === 'all' ? 'All time' : 'Last ' . $rangeLabel) ?> ·
                            Computed from each entry as
                            <em>weight &times; (1 + reps/30)</em> (Epley formula).
                        </span>
                    </div>
                    <div class="unit-toggle" id="strengthChartUnitToggle"
                         role="tablist" aria-label="Chart units">
                        <button type="button" data-unit="lbs"
                                class="<?= $defaultUnit === 'lbs' ? 'is-active' : '' ?>"
                                role="tab"
                                aria-selected="<?= $defaultUnit === 'lbs' ? 'true' : 'false' ?>">
                            lbs
                        </button>
                        <button type="button" data-unit="kg"
                                class="<?= $defaultUnit === 'kg' ? 'is-active' : '' ?>"
                                role="tab"
                                aria-selected="<?= $defaultUnit === 'kg' ? 'true' : 'false' ?>">
                            kg
                        </button>
                    </div>
                </header>
                <div class="chart-wrap chart-wrap--loading">
                    <canvas id="strengthChart"
                            role="img"
                            aria-label="Estimated 1-rep max line chart for bench, squat, and deadlift over <?= e($range === 'all' ? 'all time' : 'the last ' . $rangeLabel) ?>. Full data in the table below."
                            data-rows='<?= e(json_encode($chartRows, JSON_THROW_ON_ERROR)) ?>'
                            data-default-unit="<?= e($defaultUnit) ?>">
                    </canvas>
                </div>

                <!-- Visually-hidden data table — same data the canvas
                     paints, listed per session with computed 1RM in
                     the user's default display unit. -->
                <?php
                    // Convert kg → display unit, then apply Epley 1RM
                    // (weight × (1 + reps/30)) — same math the JS does.
                    $LB_PER_KG = 2.2046226218;
                    $orm = static function (array $r, string $unit) use ($LB_PER_KG): float {
                        $w = $unit === 'lbs' ? $r['weight_kg'] * $LB_PER_KG : $r['weight_kg'];
                        return $w * (1 + $r['reps'] / 30);
                    };
                ?>
                <table class="visually-hidden">
                    <caption>Strength log, oldest first. Estimated 1-rep max in <?= e($defaultUnit) ?>, <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Lift</th>
                            <th scope="col">Weight × Reps</th>
                            <th scope="col">Est. 1RM (<?= e($defaultUnit) ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chartRows as $row):
                            $w = $defaultUnit === 'lbs'
                                ? $row['weight_kg'] * $LB_PER_KG
                                : $row['weight_kg'];
                        ?>
                            <tr>
                                <td><?= e($fmtDate($row['date'])) ?></td>
                                <td><?= e($liftLabels[$row['lift_type']] ?? $row['lift_type']) ?></td>
                                <td><?= e(number_format($w, 1)) ?> × <?= e((string) $row['reps']) ?></td>
                                <td><?= e(number_format($orm($row, $defaultUnit), 1)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>History</h2>
                        <span class="field-hint">
                            <?= count($history) ?> lift<?= count($history) === 1 ? '' : 's' ?>
                            across <?= count($byDate) ?> day<?= count($byDate) === 1 ? '' : 's' ?>
                            · <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>
                            · newest first
                        </span>
                    </div>
                    <a class="btn-link" href="<?= url('strength/export') ?>" download
                       aria-label="Download all lift logs as a CSV file">
                        Download CSV
                    </a>
                </header>

                <div class="history-scroll">
                    <table class="history-table history-table--days" data-day-noun="lifts">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Lift</th>
                                <th scope="col" class="num">Weight × Reps</th>
                                <th scope="col" class="num">Est. 1RM</th>
                                <th scope="col">Notes</th>
                                <th scope="col" class="actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byDate as $d => $rows):
                                $count = count($rows);
                                // Distinct lift types performed that day,
                                // in canonical bench → squat → deadlift
                                // order so the summary reads consistently
                                // regardless of insertion order.
                                $typesThatDay = [];
                                foreach ($rows as $r) {
                                    $typesThatDay[$r['lift_type']] = true;
                                }
                                $typeSummary = [];
                                foreach (StrengthLog::ALLOWED_LIFTS as $key) {
                                    if (isset($typesThatDay[$key])) {
                                        $typeSummary[] = $liftLabels[$key] ?? $key;
                                    }
                                }
                            ?>
                                <!-- Day summary row (always visible). JS
                                     injects a chevron toggle into the date
                                     cell on load and hides the lift-rows
                                     below until clicked. -->
                                <tr class="day-row" data-day="<?= e($d) ?>">
                                    <td class="cell-date cell-date--day">
                                        <?= e($fmtDate($d)) ?>
                                    </td>
                                    <td class="cell-meal-count">
                                        <?= $count ?> lift<?= $count === 1 ? '' : 's' ?>
                                        <?php if (!empty($typeSummary)): ?>
                                            · <?= e(implode(', ', $typeSummary)) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"></td>
                                    <td class="num"></td>
                                    <td></td>
                                    <td class="actions"></td>
                                </tr>
                                <?php foreach ($rows as $row):
                                    $w   = (float) $row['weight'];
                                    $r   = (int) $row['reps'];
                                    // Epley 1RM in the row's own unit.
                                    $orm1 = $w * (1 + $r / 30);
                                ?>
                                    <tr class="lift-row" data-day="<?= e($d) ?>">
                                        <td class="cell-date cell-date--indent"></td>
                                        <td><?= e($liftLabels[$row['lift_type']] ?? $row['lift_type']) ?></td>
                                        <td class="num strong">
                                            <?= e(rtrim(rtrim((string) $w, '0'), '.')) ?>
                                            <span class="weight-unit-suffix"><?= e($row['unit']) ?></span>
                                            <span class="reps-suffix">× <?= e((string) $r) ?></span>
                                        </td>
                                        <td class="num">
                                            <?= e(number_format($orm1, 1)) ?>
                                            <span class="weight-unit-suffix"><?= e($row['unit']) ?></span>
                                        </td>
                                        <td class="notes-cell"><?= e($row['notes'] ?? '') ?></td>
                                        <td class="actions">
                                            <a href="<?= url('strength/edit?id=' . (int) $row['id']) ?>"
                                               class="btn-link"
                                               aria-label="Edit lift from <?= e($fmtDate($row['logged_date'])) ?>">
                                                Edit
                                            </a>
                                            <form method="post" action="<?= url('strength/delete') ?>"
                                                  data-confirm="Delete this lift log? This can't be undone."
                                                  data-confirm-ok="Delete">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                                <button type="submit" class="btn-link-danger"
                                                        aria-label="Delete lift from <?= e($fmtDate($row['logged_date'])) ?>">
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

            <?php endif; /* end of: $history empty within range */ ?>

        <?php endif; /* end of: $totalLoggedDays === 0 */ ?>

    </div>
</section>

<!-- Chart.js — only loaded on tracker pages that need it. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
