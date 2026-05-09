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
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
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
                               <?= $errWeight
                                  ? 'aria-invalid="true" aria-describedby="weight-error"'
                                  : 'aria-describedby="weight-hint"' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p id="weight-error" class="field-error"><?= e($errWeight) ?></p>
                        <?php else: ?>
                            <span id="weight-hint" class="field-hint">Re-saving the same date overwrites the previous weigh-in.</span>
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
                              <?= $errNotes ? 'aria-invalid="true" aria-describedby="notes-error"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p id="notes-error" class="field-error"><?= e($errNotes) ?></p>
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
<?php
    // Compact labels for the range picker. Keys must match what the
    // controller validates against (WeightController::ALLOWED_RANGES).
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
                <h2>No weigh-ins yet</h2>
                <p>Add your first weigh-in to start building your trend.</p>
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
                <form method="get" action="<?= url('weight') ?>"
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
                    <h2>No weigh-ins in this range</h2>
                    <p>
                        You've logged
                        <?= e((string) $totalLoggedDays) ?>
                        weigh-in<?= $totalLoggedDays === 1 ? '' : 's' ?> total
                        <?php if ($range !== 'all'): ?>
                            — try
                            <a class="link-inline" href="<?= url('weight?range=all') ?>">All time</a>
                            to see the full list.
                        <?php else: ?>.
                        <?php endif; ?>
                    </p>
                </article>

            <?php else: ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Weight over time</h2>
                        <span class="field-hint">
                            <?= e($range === 'all' ? 'All time' : 'Last ' . $rangeLabel) ?> ·
                            Trend across your logged weigh-ins.
                        </span>
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
                            role="img"
                            aria-label="Weight trend line chart, <?= count($chartData) ?> data point<?= count($chartData) === 1 ? '' : 's' ?> across <?= e($range === 'all' ? 'all time' : 'the last ' . $rangeLabel) ?>. Full data in the table below."
                            data-rows='<?= e(json_encode($chartData, JSON_THROW_ON_ERROR)) ?>'
                            data-default-unit="<?= e($defaultUnit) ?>">
                    </canvas>
                </div>

                <!-- Visually-hidden data table — screen readers get full
                     access to the trend the canvas paints. Display unit
                     is the row's logged unit so it matches the table. -->
                <table class="visually-hidden">
                    <caption>Weigh-in history, oldest first, <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Weight (<?= e($defaultUnit) ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chartData as $point): ?>
                            <tr>
                                <td><?= e($fmtDate($point['date'])) ?></td>
                                <td><?= e($displayWeight((float) $point['weight_kg'], $defaultUnit)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>History</h2>
                    <span class="field-hint">
                        <?= count($history) ?> log<?= count($history) === 1 ? '' : 's' ?>
                        · <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>
                        · newest first
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

            <?php endif; /* end of: $history empty within range */ ?>

        <?php endif; /* end of: $totalLoggedDays === 0 */ ?>

    </div>
</section>

<!-- Chart.js — only loaded on tracker pages that need it. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
