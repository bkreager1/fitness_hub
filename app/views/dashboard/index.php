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
<section class="hero hero--compact hero--photo"
         style="--hero-image: url('<?= asset('images/dashboard.jpg') ?>');">
    <div class="container">
        <span class="eyebrow"><?= e($todayPretty) ?></span>
        <h1>Hi, <?= e($displayName) ?>.</h1>
        <p class="hero-lede">
            Here's where you're at today. Tap a card to log something
            new or dive into the full tracker.
        </p>
    </div>
</section>


<!-- ===================== Stat strip ===================== -->
<?php
// Hide the strip entirely on brand-new accounts (no logs anywhere) so
// it doesn't read as a wall of "0 / —" the moment they sign up.
$hasAnyActivity =
    $statStrip['streak'] > 0
    || $statStrip['meals_week'] > 0
    || $statStrip['lifts_week'] > 0
    || $statStrip['weight_delta'] !== null;
?>
<?php if ($hasAnyActivity): ?>
<section class="dash-stat-strip-wrap" aria-label="This week's momentum">
    <div class="container">
        <ul class="dash-stat-strip">

            <!-- Logging streak -->
            <li class="dash-stat <?= $statStrip['streak'] > 0 ? 'dash-stat--hot' : '' ?>">
                <span class="dash-stat__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2c1 4 4 5 4 9a4 4 0 0 1-8 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 0-8z"/>
                        <path d="M7 14c-1 1.5-1 3 0 4.5A5 5 0 0 0 12 22a5 5 0 0 0 5-3.5c1-1.5 1-3 0-4.5"/>
                    </svg>
                </span>
                <div class="dash-stat__body">
                    <span class="dash-stat__value">
                        <?php if ($statStrip['streak'] > 0): ?>
                            <?= e((string) $statStrip['streak']) ?>
                            <span class="dash-stat__unit">day<?= $statStrip['streak'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                    <span class="dash-stat__label">
                        <?= $statStrip['streak'] > 0 ? 'logging streak' : 'no streak yet' ?>
                    </span>
                </div>
            </li>

            <!-- Meals this week -->
            <li class="dash-stat">
                <span class="dash-stat__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4v8a4 4 0 0 0 8 0V4"/>
                        <line x1="8" y1="4" x2="8" y2="22"/>
                        <path d="M17 4v6h3v10"/>
                        <line x1="17" y1="14" x2="17" y2="22"/>
                    </svg>
                </span>
                <div class="dash-stat__body">
                    <span class="dash-stat__value">
                        <?php if ($statStrip['meals_week'] > 0): ?>
                            <?= e((string) $statStrip['meals_week']) ?>
                            <span class="dash-stat__unit">meal<?= $statStrip['meals_week'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                    <span class="dash-stat__label">logged this week</span>
                </div>
            </li>

            <!-- Weight delta -->
            <li class="dash-stat">
                <span class="dash-stat__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/>
                        <path d="M6 6l2 14h8l2-14"/>
                        <path d="M9 10v6M15 10v6M12 10v6"/>
                    </svg>
                </span>
                <div class="dash-stat__body">
                    <?php if ($statStrip['weight_delta'] !== null):
                        $wd    = (float) $statStrip['weight_delta'];
                        $wu    = $statStrip['weight_unit'] ?? '';
                        $wdays = (int) ($statStrip['weight_days'] ?? 0);
                    ?>
                        <span class="dash-stat__value">
                            <?php if ($wd < 0): ?>
                                <span class="text-good">↓ <?= e(number_format(abs($wd), 1)) ?></span>
                            <?php elseif ($wd > 0): ?>
                                <span class="text-warn">↑ <?= e(number_format($wd, 1)) ?></span>
                            <?php else: ?>
                                <span>0.0</span>
                            <?php endif; ?>
                            <span class="dash-stat__unit"><?= e($wu) ?></span>
                        </span>
                        <span class="dash-stat__label">
                            over the last <?= e((string) max(1, $wdays)) ?> day<?= $wdays === 1 ? '' : 's' ?>
                        </span>
                    <?php else: ?>
                        <span class="dash-stat__value">
                            <span class="dash-stat__placeholder">—</span>
                        </span>
                        <span class="dash-stat__label">log a weigh-in to track</span>
                    <?php endif; ?>
                </div>
            </li>

            <!-- Lifts this week -->
            <li class="dash-stat">
                <span class="dash-stat__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="9" width="3" height="6" rx="1"/>
                        <rect x="19" y="9" width="3" height="6" rx="1"/>
                        <rect x="6" y="6" width="2.5" height="12" rx="1"/>
                        <rect x="15.5" y="6" width="2.5" height="12" rx="1"/>
                        <line x1="8.5" y1="12" x2="15.5" y2="12"/>
                    </svg>
                </span>
                <div class="dash-stat__body">
                    <span class="dash-stat__value">
                        <?php if ($statStrip['lifts_week'] > 0): ?>
                            <?= e((string) $statStrip['lifts_week']) ?>
                            <span class="dash-stat__unit">lift<?= $statStrip['lifts_week'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                    <span class="dash-stat__label">logged this week</span>
                </div>
            </li>

        </ul>
    </div>
</section>
<?php endif; ?>


<!-- ===================== Today at a glance ===================== -->
<section class="section">
    <div class="container">

        <div class="dash-summary">

            <!-- Calorie summary -->
            <a class="dash-card" href="<?= url('calorie') ?>">
                <span class="dash-card__eyebrow">Calories</span>

                <?php if ($calorieCard['has_targets']):
                    // Ring fill — clamped to 0..100 even if the user blew past
                    // the target (overfill is communicated via the "X over"
                    // hint underneath, not by drawing more than a full ring).
                    $todayCal  = $calorieCard['has_today'] ? (int) $calorieCard['today'] : 0;
                    $targetCal = (int) $calorieCard['target'];
                    $pct       = $targetCal > 0
                        ? max(0, min(100, round(($todayCal / $targetCal) * 100)))
                        : 0;
                    // Stroke math: r=32, circumference ≈ 201.06. Offset is the
                    // un-filled remainder, so 0% → full offset (no fill), 100%
                    // → 0 offset (full ring). Drawn rotated -90deg so the
                    // start of the arc is at 12 o'clock.
                    $circ   = 201.06;
                    $offset = $circ * (1 - $pct / 100);
                ?>
                    <div class="dash-ring-row">
                        <div class="dash-ring" aria-hidden="true">
                            <svg viewBox="0 0 80 80" class="dash-ring__svg">
                                <circle class="dash-ring__track"
                                        cx="40" cy="40" r="32"
                                        fill="none" stroke-width="7"/>
                                <circle class="dash-ring__fill"
                                        cx="40" cy="40" r="32"
                                        fill="none" stroke-width="7"
                                        stroke-linecap="round"
                                        stroke-dasharray="<?= $circ ?>"
                                        stroke-dashoffset="<?= e((string) $offset) ?>"/>
                            </svg>
                            <span class="dash-ring__pct"><?= $pct ?>%</span>
                        </div>
                        <div class="dash-ring-text">
                            <div class="dash-card__value dash-card__value--ring">
                                <?= e(number_format($todayCal)) ?>
                                <span class="dash-card__unit">/ <?= e(number_format($targetCal)) ?> cal</span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card__hint">
                        <?php if ($calorieCard['has_today']):
                            $diff = (int) $calorieCard['diff']; ?>
                            <?php if ($diff < 0): ?>
                                <span class="text-good"><?= e(number_format(abs($diff))) ?> under</span>
                                your <?= e($calorieCard['goal_label']) ?> target
                            <?php elseif ($diff > 0): ?>
                                <span class="text-warn"><?= e(number_format($diff)) ?> over</span>
                                your <?= e($calorieCard['goal_label']) ?> target
                            <?php else: ?>
                                right on your <?= e($calorieCard['goal_label']) ?> target
                            <?php endif; ?>
                        <?php else: ?>
                            Today not logged yet · aim for your
                            <?= e($calorieCard['goal_label']) ?> target.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
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

                    <?php if (!empty($weightCard['goal'])):
                        $wg = $weightCard['goal']; ?>
                        <div class="goal-bar" aria-label="Weight goal progress">
                            <div class="goal-bar__meta">
                                <span class="goal-bar__label">
                                    <?php if ($wg['direction'] === 'hit'): ?>
                                        Goal reached!
                                    <?php else: ?>
                                        <?= e(number_format($wg['remaining_display'], 1)) ?>
                                        <?= e($weightCard['unit']) ?> to
                                        <?= e(number_format($wg['target_display'], 1)) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="goal-bar__pct"><?= e((string) $wg['pct']) ?>%</span>
                            </div>
                            <div class="goal-bar__track">
                                <div class="goal-bar__fill"
                                     style="width: <?= e((string) $wg['pct']) ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
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
                        <?php foreach (['bench', 'squat', 'deadlift'] as $key):
                            $sg = $strengthCard['goals'][$key] ?? null; ?>
                            <li class="<?= $sg ? 'has-goal' : '' ?>">
                                <div class="dash-card__list-row">
                                    <span class="dash-card__list-label">
                                        <?= e($strengthCard['labels'][$key] ?? ucfirst($key)) ?>
                                    </span>
                                    <span class="dash-card__list-value">
                                        <?php if (!empty($strengthCard['is_pr'][$key])): ?>
                                            <span class="dash-pr-badge"
                                                  title="Personal record (estimated 1-rep max)"
                                                  aria-label="Personal record">PR</span>
                                        <?php endif; ?>
                                        <?= e($liftLine($strengthCard['lifts'][$key] ?? null)) ?>
                                    </span>
                                </div>
                                <?php if ($sg): ?>
                                    <div class="goal-bar goal-bar--compact"
                                         aria-label="<?= e(ucfirst($key)) ?> goal progress">
                                        <div class="goal-bar__track">
                                            <div class="goal-bar__fill"
                                                 style="width: <?= e((string) $sg['pct']) ?>%"></div>
                                        </div>
                                        <span class="goal-bar__sub">
                                            <?= e(number_format($sg['current_display'], 0)) ?>
                                            /
                                            <?= e(number_format($sg['target_display'], 0)) ?>
                                            <?= e($sg['unit']) ?>
                                            <span class="goal-bar__pct goal-bar__pct--inline">·
                                                <?= e((string) $sg['pct']) ?>%</span>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
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
                    <div class="chart-wrap chart-wrap--compact chart-wrap--loading">
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
                        <?= empty_state_icon('sm') ?>
                        <p>No calorie entries yet. Log your first meal to start seeing your intake trend.</p>
                        <a href="<?= url('calorie') ?>" class="btn btn-secondary btn-inline">Log calories</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Weight</h3>
                </header>
                <?php if (!empty($weightChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact chart-wrap--loading">
                        <canvas id="weightChart"
                                role="img"
                                aria-label="Recent weight trend line chart, <?= count($weightChartData) ?> entr<?= count($weightChartData) === 1 ? 'y' : 'ies' ?>. Open the weight tracker for full data."
                                data-rows='<?= e(json_encode($weightChartData, JSON_THROW_ON_ERROR)) ?>'
                                data-default-unit="<?= e($weightChartUnit) ?>">
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <?= empty_state_icon('sm') ?>
                        <p>No weigh-ins yet. Add your first weigh-in to start building your trend.</p>
                        <a href="<?= url('weight') ?>" class="btn btn-secondary btn-inline">Log a weigh-in</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Big three (est. 1RM)</h3>
                </header>
                <?php if (!empty($strengthChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact chart-wrap--loading">
                        <canvas id="strengthChart"
                                role="img"
                                aria-label="Recent estimated 1-rep max line chart for bench, squat, and deadlift. Open the strength tracker for full data."
                                data-rows='<?= e(json_encode($strengthChartData, JSON_THROW_ON_ERROR)) ?>'
                                data-default-unit="<?= e($strengthChartUnit) ?>">
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <?= empty_state_icon('sm') ?>
                        <p>No lifts logged yet. Add your first bench, squat, or deadlift entry to start tracking progress.</p>
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
<section class="section section--alt section--tight">
    <div class="container">
        <div class="section-head section-head--inline">
            <h2>Log something new</h2>
            <p>Jump straight to whichever tracker you need.</p>
        </div>

        <ul class="action-strip" aria-label="Quick log actions">

            <li>
                <a class="action-strip__item" href="<?= url('calorie') ?>"
                   aria-label="Log calorie intake">
                    <img class="action-strip__icon"
                         src="<?= asset('images/calorielogo.png') ?>"
                         alt="" width="44" height="44">
                    <span class="action-strip__body">
                        <span class="action-strip__title">Log calorie intake</span>
                        <span class="action-strip__caption">Targets + today's meals</span>
                    </span>
                    <span class="action-strip__chev" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 6 15 12 9 18"/>
                        </svg>
                    </span>
                </a>
            </li>

            <li>
                <a class="action-strip__item" href="<?= url('weight') ?>"
                   aria-label="Log a weigh-in">
                    <img class="action-strip__icon"
                         src="<?= asset('images/weightlogo.png') ?>"
                         alt="" width="44" height="44">
                    <span class="action-strip__body">
                        <span class="action-strip__title">Log a weigh-in</span>
                        <span class="action-strip__caption">Today's weight + trend</span>
                    </span>
                    <span class="action-strip__chev" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 6 15 12 9 18"/>
                        </svg>
                    </span>
                </a>
            </li>

            <li>
                <a class="action-strip__item" href="<?= url('strength') ?>"
                   aria-label="Log a lift">
                    <img class="action-strip__icon"
                         src="<?= asset('images/strengthlogo.png') ?>"
                         alt="" width="44" height="44">
                    <span class="action-strip__body">
                        <span class="action-strip__title">Log a lift</span>
                        <span class="action-strip__caption">Bench, squat, or deadlift</span>
                    </span>
                    <span class="action-strip__chev" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 6 15 12 9 18"/>
                        </svg>
                    </span>
                </a>
            </li>

        </ul>
    </div>
</section>
