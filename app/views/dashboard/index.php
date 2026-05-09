<?php
// ============================================================
// app/views/dashboard/index.php
// Phase 7 — Dashboard hub.
// Three "today at a glance" summary cards (calories / weight /
// lifts), then three quick-action cards that route into each
// tracker. The summary cards are also clickable as shortcuts.
//
// Variables expected from DashboardController::index():
//   $today         ISO date "today"
//   $displayName   user's name
//   $calorieCard   ['has_targets', 'has_today', 'today', 'target',
//                   'goal_label', 'diff'?]
//   $weightCard    ['has_logs', 'weight'?, 'unit'?, 'date'?,
//                   'trend_diff'?, 'trend_days'?]
//   $strengthCard  ['has_logs', 'lifts' (per lift_type row|null),
//                   'labels' (lift_type → friendly)]
// ============================================================

$todayPretty = date('l, F j', strtotime($today));   // "Tuesday, April 28"

// Inline helper: format a strength row's "weight × reps" line.
$liftLine = static function (?array $row): string {
    if (!$row) return '—';
    $w = (float) $row['weight'];
    // Strip trailing zeros: 225.00 → "225", 102.50 → "102.5"
    $weightStr = rtrim(rtrim((string) $w, '0'), '.');
    return sprintf('%s %s × %s', $weightStr, $row['unit'], $row['reps']);
};

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j', $ts) : $iso;
};
?>


<!-- ===================== Greeting hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow"><?= e($todayPretty) ?></span>
        <h1>Hi, <?= e($displayName) ?>.</h1>
        <p class="hero-lede">
            Here's where you're at today. Tap a card to log something
            new or dive into the full tracker.
        </p>
    </div>
</section>


<!-- ===================== Today at a glance ===================== -->
<section class="section">
    <div class="container">

        <div class="dash-summary">

            <!-- Calorie summary -->
            <a class="dash-card" href="<?= url('calorie') ?>">
                <span class="dash-card__eyebrow">Calories</span>

                <?php if ($calorieCard['has_targets'] && $calorieCard['has_today']): ?>
                    <div class="dash-card__value">
                        <?= e(number_format((int) $calorieCard['today'])) ?>
                        <span class="dash-card__unit">/ <?= e(number_format((int) $calorieCard['target'])) ?> cal</span>
                    </div>
                    <div class="dash-card__hint">
                        <?php $diff = (int) $calorieCard['diff']; ?>
                        <?php if ($diff < 0): ?>
                            <span class="text-good"><?= e(number_format(abs($diff))) ?> under</span>
                            your <?= e($calorieCard['goal_label']) ?> target
                        <?php elseif ($diff > 0): ?>
                            <span class="text-warn"><?= e(number_format($diff)) ?> over</span>
                            your <?= e($calorieCard['goal_label']) ?> target
                        <?php else: ?>
                            right on your <?= e($calorieCard['goal_label']) ?> target
                        <?php endif; ?>
                    </div>
                <?php elseif ($calorieCard['has_targets']): ?>
                    <div class="dash-card__value">
                        <?= e(number_format((int) $calorieCard['target'])) ?>
                        <span class="dash-card__unit">cal target</span>
                    </div>
                    <div class="dash-card__hint">
                        Today not logged yet · aim for your
                        <?= e($calorieCard['goal_label']) ?> target.
                    </div>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder">—</div>
                    <div class="dash-card__hint">
                        Set your calorie targets to start tracking.
                    </div>
                <?php endif; ?>
            </a>

            <!-- Weight summary -->
            <a class="dash-card" href="<?= url('weight') ?>">
                <span class="dash-card__eyebrow">Weight</span>

                <?php if ($weightCard['has_logs']): ?>
                    <div class="dash-card__value">
                        <?= e(number_format((float) $weightCard['weight'], 1)) ?>
                        <span class="dash-card__unit"><?= e($weightCard['unit']) ?></span>
                    </div>
                    <div class="dash-card__hint">
                        Last weigh-in <?= e($fmtDate($weightCard['date'])) ?>
                        <?php if (isset($weightCard['trend_diff'])):
                            $td = (float) $weightCard['trend_diff'];
                            $days = (int) ($weightCard['trend_days'] ?? 0);
                        ?>
                            ·
                            <?php if ($td < 0): ?>
                                <span class="text-good">↓ <?= e(number_format(abs($td), 1)) ?>
                                <?= e($weightCard['unit']) ?> over <?= e((string) $days) ?>d</span>
                            <?php elseif ($td > 0): ?>
                                <span class="text-warn">↑ <?= e(number_format($td, 1)) ?>
                                <?= e($weightCard['unit']) ?> over <?= e((string) $days) ?>d</span>
                            <?php else: ?>
                                <span>flat over <?= e((string) $days) ?>d</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder">—</div>
                    <div class="dash-card__hint">
                        Log your first weigh-in to start the trend.
                    </div>
                <?php endif; ?>
            </a>

            <!-- Strength summary -->
            <a class="dash-card" href="<?= url('strength') ?>">
                <span class="dash-card__eyebrow">Big three</span>

                <?php if ($strengthCard['has_logs']): ?>
                    <ul class="dash-card__list">
                        <?php foreach (['bench', 'squat', 'deadlift'] as $key): ?>
                            <li>
                                <span class="dash-card__list-label">
                                    <?= e($strengthCard['labels'][$key] ?? ucfirst($key)) ?>
                                </span>
                                <span class="dash-card__list-value">
                                    <?= e($liftLine($strengthCard['lifts'][$key] ?? null)) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder">—</div>
                    <div class="dash-card__hint">
                        Log your first big-three lift to start the chart.
                    </div>
                <?php endif; ?>
            </a>

        </div>

    </div>
</section>


<!-- ===================== Trend charts ===================== -->
<?php
$hasAnyChart =
    !empty($intakeChartData)
    || !empty($weightChartData)
    || !empty($strengthChartData);
?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Your trends</h2>
            <p>Quick look at how the numbers are moving. Click into a tracker for the full chart and controls.</p>
        </div>

        <div class="dash-charts">

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Calorie intake</h3>
                </header>
                <?php if (!empty($intakeChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact">
                        <!-- Canvas id matches the existing initIntakeChart()
                             IIFE in main.js, which already handles target
                             overlay lines + active-goal highlight. -->
                        <canvas id="intakeChart"
                                role="img"
                                aria-label="Recent daily calorie intake bar chart, <?= count($intakeChartData) ?> day<?= count($intakeChartData) === 1 ? '' : 's' ?>. Open the calorie tracker for full data."
                                data-rows='<?= e(json_encode($intakeChartData, JSON_THROW_ON_ERROR)) ?>'
                                data-targets='<?= e(json_encode($intakeChartTargets, JSON_THROW_ON_ERROR)) ?>'
                                data-active-goal="<?= e($activeGoal) ?>">
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <p>No intake logs yet.</p>
                        <a href="<?= url('calorie') ?>" class="btn btn-secondary btn-inline">Log calories</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Weight</h3>
                </header>
                <?php if (!empty($weightChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact">
                        <canvas id="weightChart"
                                role="img"
                                aria-label="Recent weight trend line chart, <?= count($weightChartData) ?> entr<?= count($weightChartData) === 1 ? 'y' : 'ies' ?>. Open the weight tracker for full data."
                                data-rows='<?= e(json_encode($weightChartData, JSON_THROW_ON_ERROR)) ?>'
                                data-default-unit="<?= e($weightChartUnit) ?>">
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <p>No weigh-ins yet.</p>
                        <a href="<?= url('weight') ?>" class="btn btn-secondary btn-inline">Log a weigh-in</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Big three (est. 1RM)</h3>
                </header>
                <?php if (!empty($strengthChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact">
                        <canvas id="strengthChart"
                                role="img"
                                aria-label="Recent estimated 1-rep max line chart for bench, squat, and deadlift. Open the strength tracker for full data."
                                data-rows='<?= e(json_encode($strengthChartData, JSON_THROW_ON_ERROR)) ?>'
                                data-default-unit="<?= e($strengthChartUnit) ?>">
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <p>No lifts logged yet.</p>
                        <a href="<?= url('strength') ?>" class="btn btn-secondary btn-inline">Log a lift</a>
                    </div>
                <?php endif; ?>
            </article>

        </div>
    </div>
</section>

<?php if ($hasAnyChart): ?>
<!-- Chart.js — only fetched when at least one trend chart will render. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
<?php endif; ?>


<!-- ===================== Quick actions ===================== -->
<section class="section section--alt">
    <div class="container">
        <div class="section-head">
            <h2>Log something new</h2>
            <p>Jump straight to whichever tracker you need.</p>
        </div>

        <div class="features dash-actions">

            <a class="feature-card" href="<?= url('calorie') ?>"
               aria-label="Log calorie intake">
                <img class="feature-image"
                     src="<?= asset('images/calorielogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Log calorie intake</h3>
                <p>Set your targets, then log how many calories you ate today against them.</p>
            </a>

            <a class="feature-card" href="<?= url('weight') ?>"
               aria-label="Log a weigh-in">
                <img class="feature-image"
                     src="<?= asset('images/weightlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Log a weigh-in</h3>
                <p>Add today's weight, watch the trend smooth out over the week.</p>
            </a>

            <a class="feature-card" href="<?= url('strength') ?>"
               aria-label="Log a lift">
                <img class="feature-image"
                     src="<?= asset('images/strengthlogo.png') ?>"
                     alt="" width="88" height="88">
                <h3>Log a lift</h3>
                <p>Bench, squat, or deadlift — weight, reps, done. The chart does the rest.</p>
            </a>

        </div>
    </div>
</section>
