<?php
// ============================================================
// app/views/cardio/index.php
// Cardio tracker — sessions logged with type + duration, plus
// optional intensity + distance.
//
// Sections:
//   1. New session form (type select, date, duration, intensity
//      pills, distance + unit)
//   2. History (chart of daily minutes + table grouped by day,
//      range-filtered)
//
// Variables expected from CardioController::index():
//   $today               ISO date for "today"
//   $history             rows newest-first (for the table)
//   $latest              most recent row, or null
//   $chartRows           rows oldest-first: { date, cardio_type, duration_min }
//   $defaultDistanceUnit 'mi' or 'km' — pre-selected on the form
//   $typeLabels          ['walk' => 'Walk', ...]
//   $intensityLabels     ['easy' => 'Easy', ...]
//   $range               '7' | '30' | '90' | 'all'
//   $totalLoggedDays     int — used to tell "no logs ever" from "none in range"
// ============================================================

$errType      = field_error('cardio_type');
$errDuration  = field_error('duration_min');
$errIntensity = field_error('intensity');
$errDistance  = field_error('distance');
$errDistUnit  = field_error('distance_unit');
$errDate      = field_error('logged_date');

$cardioType  = old('cardio_type');
$durationVal = old('duration_min');
$intensity   = old('intensity');
$distanceVal = old('distance');
$distUnit    = old('distance_unit') ?: $defaultDistanceUnit;
$loggedDate  = old('logged_date')   ?: $today;

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};

// Tiny inline formatter — "Walk · 30 min · moderate · 2.1 mi".
$summarize = static function (array $r) use ($typeLabels, $intensityLabels): string {
    $parts = [
        $typeLabels[$r['cardio_type']] ?? ucfirst($r['cardio_type']),
        ((int) $r['duration_min']) . ' min',
    ];
    if (!empty($r['intensity'])) {
        $parts[] = $intensityLabels[$r['intensity']] ?? $r['intensity'];
    }
    if (!empty($r['distance']) && !empty($r['distance_unit'])) {
        $d = rtrim(rtrim((string) $r['distance'], '0'), '.');
        $parts[] = $d . ' ' . $r['distance_unit'];
    }
    return implode(' · ', $parts);
};

$latestLine = $latest
    ? 'Last session: ' . $summarize($latest) . ' on ' . $fmtDate($latest['logged_date'])
    : null;
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Cardio tracker</span>
        <div class="hero-heading-row">
            <h1>Walks, runs, rides — log every session.</h1>
            <img class="hero-icon"
                 src="<?= asset('images/cardio.png') ?>"
                 alt="" width="96" height="96">
        </div>
        <p class="hero-lede">
            Pick a type, drop in your duration, and (optionally) note
            intensity or distance. The dashboard rolls these up into your
            weekly cardio target.
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
                    <h2>Log a session</h2>
                    <?php if ($latestLine): ?>
                        <span class="field-hint"><?= e($latestLine) ?>.</span>
                    <?php else: ?>
                        <span class="field-hint">
                            Your first cardio session goes here. After that we'll show your latest right alongside the form.
                        </span>
                    <?php endif; ?>
                    <span class="field-hint">
                        Intensity and distance are optional — leave them blank
                        if you don't track those.
                    </span>
                </div>
            </header>

            <form method="post" action="<?= url('cardio') ?>"
                  id="cardioForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">

                    <div class="field">
                        <label for="cardioType">Type</label>
                        <select id="cardioType" name="cardio_type"
                                <?= $errType ? 'aria-invalid="true" aria-describedby="cardio_type-error"' : '' ?>
                                required>
                            <option value="">Choose…</option>
                            <?php foreach ($typeLabels as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= $cardioType === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($errType): ?>
                            <p id="cardio_type-error" class="field-error"><?= e($errType) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="cardioDate">Date</label>
                        <input type="date" id="cardioDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="cardioDuration">
                            Duration <span class="field-hint-inline">(min)</span>
                        </label>
                        <input type="number" id="cardioDuration" name="duration_min"
                               inputmode="numeric" step="1" min="1" max="1440"
                               value="<?= e($durationVal) ?>"
                               placeholder="30"
                               <?= $errDuration ? 'aria-invalid="true" aria-describedby="duration_min-error"' : '' ?>
                               required>
                        <?php if ($errDuration): ?>
                            <p id="duration_min-error" class="field-error"><?= e($errDuration) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Intensity (optional) — 4 pills: a leading "—" means
                     "not set" (empty value submits as null). -->
                <div class="field field--wide">
                    <span class="field-label">
                        Intensity <span class="field-hint-inline">(optional)</span>
                    </span>
                    <div class="goal-picker__pills <?= $errIntensity ? 'is-invalid' : '' ?>"
                         role="radiogroup" aria-label="Intensity"
                         <?= $errIntensity ? 'aria-describedby="intensity-error"' : '' ?>>
                        <label class="goal-pill goal-pill--radio">
                            <input type="radio" name="intensity" value=""
                                   <?= $intensity === '' ? 'checked' : '' ?>>
                            —
                        </label>
                        <?php foreach ($intensityLabels as $key => $label): ?>
                            <label class="goal-pill goal-pill--radio">
                                <input type="radio" name="intensity" value="<?= e($key) ?>"
                                       <?= $intensity === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($errIntensity): ?>
                        <p id="intensity-error" class="field-error"><?= e($errIntensity) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Distance + unit — optional pair. Either both blank or
                     both filled; the validator enforces it. -->
                <div class="field field--wide">
                    <span class="field-label">
                        Distance <span class="field-hint-inline">(optional)</span>
                    </span>
                    <div class="distance-row">
                        <input type="number" id="cardioDistance" name="distance"
                               class="distance-row__input"
                               inputmode="decimal" step="0.01" min="0.01" max="999.99"
                               value="<?= e($distanceVal) ?>"
                               placeholder="2.5"
                               aria-label="Distance"
                               <?= $errDistance ? 'aria-invalid="true" aria-describedby="distance-error"' : '' ?>>

                        <select name="distance_unit" class="distance-row__unit"
                                aria-label="Distance unit"
                                <?= $errDistUnit ? 'aria-invalid="true" aria-describedby="distance_unit-error"' : '' ?>>
                            <option value="mi" <?= $distUnit === 'mi' ? 'selected' : '' ?>>mi</option>
                            <option value="km" <?= $distUnit === 'km' ? 'selected' : '' ?>>km</option>
                        </select>
                    </div>
                    <?php if ($errDistance): ?>
                        <p id="distance-error" class="field-error"><?= e($errDistance) ?></p>
                    <?php endif; ?>
                    <?php if ($errDistUnit): ?>
                        <p id="distance_unit-error" class="field-error"><?= e($errDistUnit) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Log session</button>
                </div>
            </form>

        </article>
    </div>
</section>


<!-- ===================== History (chart + table) ===================== -->
<?php
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
                <h2>No cardio logged yet</h2>
                <p>Add your first walk, run, or ride above to start tracking.</p>
            </article>

        <?php else: ?>

            <div class="section-toolbar">
                <div class="section-toolbar__heading">
                    <span class="section-toolbar__title">Timeline</span>
                    <span class="section-toolbar__hint">
                        Range applies to both the chart and history.
                    </span>
                </div>
                <form method="get" action="<?= url('cardio') ?>"
                      class="range-picker-row" data-no-loading>
                    <span class="range-picker-row__label">Showing:</span>
                    <div class="unit-toggle">
                        <?php foreach ($RANGE_OPTIONS as $key => $label):
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
                    <h2>No cardio in this range</h2>
                    <p>
                        You've logged cardio on
                        <?= e((string) $totalLoggedDays) ?>
                        day<?= $totalLoggedDays === 1 ? '' : 's' ?> total
                        <?php if ($range !== 'all'): ?>
                            — try
                            <a class="link-inline" href="<?= url('cardio?range=all') ?>">All time</a>
                            to see the full list.
                        <?php else: ?>.
                        <?php endif; ?>
                    </p>
                </article>

            <?php else: ?>

            <?php
                // Group by date for the history table (one summary row
                // per day + per-session rows beneath it, collapsible).
                $byDate = [];
                foreach ($history as $r) {
                    $byDate[$r['logged_date']][] = $r;
                }
            ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Daily cardio minutes</h2>
                        <span class="field-hint">
                            <?= e($range === 'all' ? 'All time' : 'Last ' . $rangeLabel) ?> ·
                            Totals duration across all sessions on each day.
                        </span>
                    </div>
                </header>
                <div class="chart-wrap chart-wrap--loading">
                    <canvas id="cardioChart"
                            role="img"
                            aria-label="Daily cardio minutes bar chart over <?= e($range === 'all' ? 'all time' : 'the last ' . $rangeLabel) ?>. Full data in the table below."
                            data-rows='<?= e(json_encode($chartRows, JSON_THROW_ON_ERROR)) ?>'>
                    </canvas>
                </div>

                <!-- Visually-hidden data table for screen readers. -->
                <?php
                    $dailyTotals = [];
                    foreach ($chartRows as $r) {
                        $dailyTotals[$r['date']] = ($dailyTotals[$r['date']] ?? 0) + $r['duration_min'];
                    }
                ?>
                <table class="visually-hidden">
                    <caption>Cardio totals by day, oldest first, <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Total minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyTotals as $d => $mins): ?>
                            <tr>
                                <td><?= e($fmtDate($d)) ?></td>
                                <td><?= e((string) $mins) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h2>History</h2>
                    <span class="field-hint">
                        <?= count($history) ?> session<?= count($history) === 1 ? '' : 's' ?>
                        across <?= count($byDate) ?> day<?= count($byDate) === 1 ? '' : 's' ?>
                        · <?= e($range === 'all' ? 'all time' : 'last ' . $rangeLabel) ?>
                        · newest first
                    </span>
                </header>

                <div class="history-scroll">
                    <table class="history-table history-table--days" data-day-noun="sessions">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Type</th>
                                <th scope="col" class="num">Duration</th>
                                <th scope="col">Intensity</th>
                                <th scope="col" class="num">Distance</th>
                                <th scope="col" class="actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byDate as $d => $rows):
                                $count   = count($rows);
                                $totalMin = 0;
                                foreach ($rows as $r) { $totalMin += (int) $r['duration_min']; }
                            ?>
                                <tr class="day-row" data-day="<?= e($d) ?>">
                                    <td class="cell-date cell-date--day">
                                        <?= e($fmtDate($d)) ?>
                                    </td>
                                    <td class="cell-meal-count">
                                        <?= $count ?> session<?= $count === 1 ? '' : 's' ?>
                                    </td>
                                    <td class="num strong">
                                        <?= e((string) $totalMin) ?>
                                        <span class="weight-unit-suffix">min</span>
                                    </td>
                                    <td></td>
                                    <td class="num"></td>
                                    <td class="actions"></td>
                                </tr>
                                <?php foreach ($rows as $row):
                                    $dist = null;
                                    if (!empty($row['distance']) && !empty($row['distance_unit'])) {
                                        $dist = rtrim(rtrim((string) $row['distance'], '0'), '.')
                                              . ' ' . $row['distance_unit'];
                                    }
                                ?>
                                    <tr class="lift-row" data-day="<?= e($d) ?>">
                                        <td class="cell-date cell-date--indent"></td>
                                        <td>
                                            <?= e($typeLabels[$row['cardio_type']] ?? $row['cardio_type']) ?>
                                        </td>
                                        <td class="num strong">
                                            <?= e((string) $row['duration_min']) ?>
                                            <span class="weight-unit-suffix">min</span>
                                        </td>
                                        <td>
                                            <?= !empty($row['intensity'])
                                                ? e($intensityLabels[$row['intensity']] ?? $row['intensity'])
                                                : '<span class="text-faint">—</span>' ?>
                                        </td>
                                        <td class="num">
                                            <?= $dist !== null ? e($dist) : '<span class="text-faint">—</span>' ?>
                                        </td>
                                        <td class="actions">
                                            <a href="<?= url('cardio/edit?id=' . (int) $row['id']) ?>"
                                               class="btn-link"
                                               aria-label="Edit cardio from <?= e($fmtDate($row['logged_date'])) ?>">
                                                Edit
                                            </a>
                                            <form method="post" action="<?= url('cardio/delete') ?>"
                                                  onsubmit="return confirm('Delete this cardio log?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                                <button type="submit" class="btn-link-danger"
                                                        aria-label="Delete cardio from <?= e($fmtDate($row['logged_date'])) ?>">
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

<!-- Chart.js — only loaded when the cardio chart will actually render. -->
<?php if (!empty($chartRows)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
<?php endif; ?>
