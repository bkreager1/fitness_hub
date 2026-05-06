<?php
// ============================================================
// app/views/weight/index.php
// Phase 7 — Weight tracker.
//
// Sections:
//   1. New weigh-in form (date + weight + unit toggle + notes)
//   2. Chart + history table (or empty state if no logs)
//
// Variables expected from WeightController::index():
//   $today        ISO date for "today"
//   $history      rows newest-first (for the table)
//   $latest       most recent row, or null
//   $chartData    rows oldest-first, shaped { date, weight_kg }
//   $defaultUnit  'lbs' or 'kg' — pre-selected unit on the form + chart
// ============================================================

$errUnit   = field_error('unit');
$errWeight = field_error('weight');
$errDate   = field_error('logged_date');
$errNotes  = field_error('notes');

$unit       = old('unit')        ?: $defaultUnit;
$loggedDate = old('logged_date') ?: $today;
$weightVal  = old('weight');
$notesVal   = old('notes');

$placeholderWeight = $unit === 'lbs' ? '175' : '79';

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};

// Canonical kg → display in either unit, rounded to 1 decimal.
$displayWeight = static function (float $kg, string $asUnit): string {
    $val = $asUnit === 'lbs' ? $kg * 2.2046226218 : $kg;
    return number_format($val, 1);
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Weight tracker</span>
        <h1>Log your weigh-ins, watch the trend.</h1>
        <p class="hero-lede">
            Add a weight any time you step on the scale — the chart below
            shows the trend over time so the day-to-day noise smooths out.
            Switch between lbs and kg whenever you want.
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
                    <h2>Log a weigh-in</h2>
                    <?php if ($latest): ?>
                        <span class="field-hint">
                            Last weigh-in:
                            <strong><?= e($displayWeight((float) $latest['weight_kg'], $latest['unit'])) ?>
                            <?= e($latest['unit']) ?></strong>
                            on <?= e($fmtDate($latest['logged_date'])) ?>.
                        </span>
                    <?php else: ?>
                        <span class="field-hint">
                            Your first weigh-in goes here. After that we'll show your latest right alongside the form.
                        </span>
                    <?php endif; ?>
                    <span class="field-hint">
                        Tip: weigh in first thing in the morning, after the
                        bathroom and before eating, for the most consistent trend.
                    </span>
                </div>
                <div class="unit-toggle" id="weightUnitToggle"
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

            <form method="post" action="<?= url('weight') ?>"
                  id="weightForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="unit" id="weightUnit" value="<?= e($unit) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label for="weightDate">Date</label>
                        <input type="date" id="weightDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="weightInput">
                            Weight <span class="weight-unit-label" id="weightUnitLabel">(<?= e($unit) ?>)</span>
                        </label>
                        <input type="number" id="weightInput" name="weight"
                               inputmode="decimal" step="0.1"
                               min="<?= $unit === 'lbs' ? '66' : '30' ?>"
                               max="<?= $unit === 'lbs' ? '660' : '300' ?>"
                               value="<?= e($weightVal) ?>"
                               placeholder="<?= e($placeholderWeight) ?>"
                               <?= $errWeight ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p class="field-error"><?= e($errWeight) ?></p>
                        <?php else: ?>
                            <span class="field-hint">Re-saving the same date overwrites the previous weigh-in.</span>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="field field--wide">
                    <label for="weightNotes">
                        Notes <span class="field-hint-inline">(optional)</span>
                    </label>
                    <textarea id="weightNotes" name="notes"
                              rows="2" maxlength="300"
                              placeholder="Add any notes about this entry..."
                              <?= $errNotes ? 'aria-invalid="true"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p class="field-error"><?= e($errNotes) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Log weigh-in</button>
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
                <h2>No weigh-ins yet</h2>
                <p>Log your first weigh-in above and the trend chart will start filling in from there.</p>
            </article>

        <?php else: ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Weight over time</h2>
                        <span class="field-hint">Trend across all your logged weigh-ins.</span>
                    </div>
                    <div class="unit-toggle" id="weightChartUnitToggle"
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
                    <canvas id="weightChart"
                            data-rows='<?= e(json_encode($chartData, JSON_THROW_ON_ERROR)) ?>'
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
                                <th scope="col" class="num">Weight</th>
                                <th scope="col">Notes</th>
                                <th scope="col" class="actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><?= e($fmtDate($row['logged_date'])) ?></td>
                                    <td class="num strong">
                                        <?= e($displayWeight((float) $row['weight_kg'], $row['unit'])) ?>
                                        <span class="weight-unit-suffix"><?= e($row['unit']) ?></span>
                                    </td>
                                    <td class="notes-cell"><?= e($row['notes'] ?? '') ?></td>
                                    <td class="actions">
                                        <a href="<?= url('weight/edit?id=' . (int) $row['id']) ?>"
                                           class="btn-link"
                                           aria-label="Edit weigh-in from <?= e($fmtDate($row['logged_date'])) ?>">
                                            Edit
                                        </a>
                                        <form method="post" action="<?= url('weight/delete') ?>"
                                              onsubmit="return confirm('Delete this weigh-in?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                            <button type="submit" class="btn-link-danger"
                                                    aria-label="Delete weigh-in from <?= e($fmtDate($row['logged_date'])) ?>">
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
