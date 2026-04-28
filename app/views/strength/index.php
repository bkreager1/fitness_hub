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
        <h1>Bench, squat, deadlift — chart your big three.</h1>
        <p class="hero-lede">
            Log a lift with weight + reps. The chart below plots your
            estimated 1-rep max over time so you can see real progress
            even when you're working in different rep ranges.
        </p>
    </div>
</section>


<!-- ===================== Form card ===================== -->
<section class="section">
    <div class="container">
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
                        Tip: log your top working set per lift each session.
                        Weekly progress entries are the most reliable trend signal.
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
                         role="radiogroup" aria-label="Lift type">
                        <?php foreach ($liftLabels as $key => $label): ?>
                            <label class="goal-pill goal-pill--radio">
                                <input type="radio" name="lift_type" value="<?= e($key) ?>"
                                       <?= $liftType === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($errLift): ?>
                        <p class="field-error"><?= e($errLift) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-grid">

                    <div class="field">
                        <label for="strengthDate">Date</label>
                        <input type="date" id="strengthDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p class="field-error"><?= e($errDate) ?></p>
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
                               <?= $errWeight ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p class="field-error"><?= e($errWeight) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="strengthReps">Reps</label>
                        <input type="number" id="strengthReps" name="reps"
                               inputmode="numeric" step="1" min="1" max="30"
                               value="<?= e($repsVal) ?>"
                               placeholder="5"
                               <?= $errReps ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errReps): ?>
                            <p class="field-error"><?= e($errReps) ?></p>
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
                              <?= $errNotes ? 'aria-invalid="true"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p class="field-error"><?= e($errNotes) ?></p>
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
<section class="section section--alt">
    <div class="container">

        <?php if (empty($history)): ?>

            <article class="tracker-card empty-state">
                <h2>No lifts logged yet</h2>
                <p>Log your first big-three lift above and the chart will start tracking your estimated 1-rep max from there.</p>
            </article>

        <?php else: ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Estimated 1RM over time</h2>
                        <span class="field-hint">
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
                <div class="chart-wrap">
                    <canvas id="strengthChart"
                            data-rows='<?= e(json_encode($chartRows, JSON_THROW_ON_ERROR)) ?>'
                            data-default-unit="<?= e($defaultUnit) ?>">
                    </canvas>
                </div>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>History</h2>
                    <span class="field-hint">
                        <?= count($history) ?> log<?= count($history) === 1 ? '' : 's' ?> · newest first
                    </span>
                </header>

                <div class="history-scroll">
                    <table class="history-table">
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
                            <?php foreach ($history as $row):
                                $w   = (float) $row['weight'];
                                $r   = (int) $row['reps'];
                                // Epley 1RM in the row's own unit.
                                $orm = $w * (1 + $r / 30);
                            ?>
                                <tr>
                                    <td><?= e($fmtDate($row['logged_date'])) ?></td>
                                    <td><?= e($liftLabels[$row['lift_type']] ?? $row['lift_type']) ?></td>
                                    <td class="num strong">
                                        <?= e(rtrim(rtrim((string) $w, '0'), '.')) ?>
                                        <span class="weight-unit-suffix"><?= e($row['unit']) ?></span>
                                        <span class="reps-suffix">× <?= e((string) $r) ?></span>
                                    </td>
                                    <td class="num">
                                        <?= e(number_format($orm, 1)) ?>
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
                                              onsubmit="return confirm('Delete this lift log?');">
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
