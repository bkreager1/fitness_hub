<?php
// ============================================================
// app/views/dashboard/index.php
// Dashboard hub — redesigned for the cardio + weekly-cadence pass.
//
// Layout (desktop ≥1100px):
//   ┌─ Hero greeting ───────────────────────────────────────────┐
//   ┌─ Stat strip (streak / meals / lifts / weight Δ) ──────────┐
//   ┌─ Today's snapshot grid ───────────────────────────────────┐
//   │  Calories | Weight  | Strength                            │
//   │  ──────── This week ──── | Cardio                         │
//   │  ───── Goals & progress (spans all 3 cols) ─────────────  │
//   ┌─ Progress over time (3 trend charts + cardio) ────────────┐
//   ┌─ Quick-action strip ──────────────────────────────────────┐
//
// Variables expected from DashboardController::index():
//   $today, $displayName, $user
//   $calorieCard, $weightCard, $strengthCard, $cardioCard
//   $thisWeek                 ← workouts_done/target/pct + recent[]
//   $statStrip
//   $intakeChartData / Targets, $weightChartData / Unit,
//   $strengthChartData / Unit, $cardioChartData, $activeGoal
// ============================================================

$todayPretty = date('l, F j', strtotime($today));

$liftLine = static function (?array $row): string {
    if (!$row) return '—';
    $w = (float) $row['weight'];
    $weightStr = rtrim(rtrim((string) $w, '0'), '.');
    return sprintf('%s %s × %s', $weightStr, $row['unit'], $row['reps']);
};

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j', $ts) : $iso;
};

// "2h ago" / "today" / "yesterday" / "Mon" for the recent-activity feed.
$relativeDay = static function (string $iso) use ($today): string {
    if ($iso === $today) return 'today';
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($iso === $yesterday) return 'yesterday';
    $ts = strtotime($iso);
    return $ts ? date('M j', $ts) : $iso;
};

// Goals & progress — flat list of progress bars built from data the
// controller already computed. Built up here so the card can sit
// inside the dash-grid alongside the snapshot cards. Hidden entirely
// when nothing has been set.
$goalRows = [];

if (!empty($weightCard['goal'])) {
    $wg = $weightCard['goal'];
    $goalRows[] = [
        'icon'   => '⚖',
        'label'  => 'Reach '
                  . number_format($wg['target_display'], 1) . ' '
                  . $weightCard['unit'],
        'pct'    => (int) $wg['pct'],
        'suffix' => $wg['direction'] === 'hit'
            ? 'goal reached'
            : number_format($wg['remaining_display'], 1) . ' '
              . $weightCard['unit'] . ' to go',
    ];
}
foreach (['bench', 'squat', 'deadlift'] as $lift) {
    $sg = $strengthCard['goals'][$lift] ?? null;
    if (!$sg) continue;
    $label = $strengthCard['labels'][$lift] ?? ucfirst($lift);
    $remaining = max(0, $sg['target_display'] - $sg['current_display']);
    $goalRows[] = [
        'icon'   => '🏋',
        'label'  => $label . ' to '
                  . number_format($sg['target_display'], 0) . ' '
                  . $sg['unit'],
        'pct'    => (int) $sg['pct'],
        'suffix' => $remaining > 0
            ? number_format($remaining, 0) . ' ' . $sg['unit'] . ' to go'
            : 'goal reached',
    ];
}
if (!empty($thisWeek['workouts_target'])) {
    $done = (int) $thisWeek['workouts_done'];
    $tgt  = (int) $thisWeek['workouts_target'];
    $goalRows[] = [
        'icon'   => '📅',
        'label'  => $tgt . ' workouts / week',
        'pct'    => (int) $thisWeek['workouts_pct'],
        'suffix' => $done . ' of ' . $tgt . ' this week',
    ];
}
if (!empty($cardioCard['target'])) {
    $goalRows[] = [
        'icon'   => '🏃',
        'label'  => $cardioCard['target'] . ' cardio / week',
        'pct'    => (int) $cardioCard['pct'],
        'suffix' => $cardioCard['sessions_week'] . ' of ' . $cardioCard['target'] . ' this week',
    ];
}
?>


<!-- ===================== Greeting hero ===================== -->
<section class="hero hero--compact hero--photo"
         style="--hero-image: url('<?= asset('images/dashboard.jpg') ?>');">
    <div class="container container--wide">
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
// Workouts/week reuses $thisWeek (computed for the This-week card) so
// the strip and the bar inside the card never disagree about the count.
$workoutsDone   = (int) ($thisWeek['workouts_done']   ?? 0);
$workoutsTarget = isset($thisWeek['workouts_target']) ? (int) $thisWeek['workouts_target'] : 0;

$hasAnyActivity =
    $statStrip['streak'] > 0
    || $statStrip['meals_week'] > 0
    || $workoutsDone > 0
    || $statStrip['weight_delta'] !== null;
?>
<?php if ($hasAnyActivity): ?>
<section class="dash-stat-strip-wrap" aria-label="This week's momentum">
    <div class="container container--wide">
        <ul class="dash-stat-strip">

            <li class="dash-stat <?= $statStrip['streak'] > 0 ? 'dash-stat--hot' : '' ?>">
                <span class="dash-stat__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2c1 4 4 5 4 9a4 4 0 0 1-8 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 0-8z"/>
                        <path d="M7 14c-1 1.5-1 3 0 4.5A5 5 0 0 0 12 22a5 5 0 0 0 5-3.5c1-1.5 1-3 0-4.5"/>
                    </svg>
                </span>
                <div class="dash-stat__body">
                    <span class="dash-stat__label">Streak</span>
                    <span class="dash-stat__value">
                        <?php if ($statStrip['streak'] > 0): ?>
                            <?= e((string) $statStrip['streak']) ?>
                            <span class="dash-stat__unit">day<?= $statStrip['streak'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                </div>
            </li>

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
                    <span class="dash-stat__label">Meals</span>
                    <span class="dash-stat__value">
                        <?php if ($statStrip['meals_week'] > 0): ?>
                            <?= e((string) $statStrip['meals_week']) ?>
                            <span class="dash-stat__unit">log<?= $statStrip['meals_week'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                </div>
            </li>

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
                    <span class="dash-stat__label">Workouts</span>
                    <span class="dash-stat__value">
                        <?php if ($workoutsDone > 0 || $workoutsTarget > 0): ?>
                            <?= e((string) $workoutsDone) ?>
                            <?php if ($workoutsTarget > 0): ?>
                                <span class="dash-stat__unit">/ <?= e((string) $workoutsTarget) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="dash-stat__placeholder">—</span>
                        <?php endif; ?>
                    </span>
                </div>
            </li>

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
                    <span class="dash-stat__label">Weight Δ</span>
                    <?php if ($statStrip['weight_delta'] !== null):
                        $wd = (float) $statStrip['weight_delta'];
                        $wu = $statStrip['weight_unit'] ?? '';
                    ?>
                        <span class="dash-stat__value">
                            <?php if ($wd < 0): ?>
                                <span class="text-good">−<?= e(number_format(abs($wd), 1)) ?></span>
                            <?php elseif ($wd > 0): ?>
                                <span class="text-warn">+<?= e(number_format($wd, 1)) ?></span>
                            <?php else: ?>
                                <span>0.0</span>
                            <?php endif; ?>
                            <span class="dash-stat__unit"><?= e($wu) ?></span>
                        </span>
                    <?php else: ?>
                        <span class="dash-stat__value">
                            <span class="dash-stat__placeholder">—</span>
                        </span>
                    <?php endif; ?>
                </div>
            </li>

        </ul>
    </div>
</section>
<?php endif; ?>


<!-- ===================== Today's snapshot grid ===================== -->
<section class="section">
    <div class="container container--wide">
        <div class="section-head">
            <h2>Today's snapshot</h2>
            <p>Quick read on where you're at right now. Click any card to dive in.</p>
        </div>

        <div class="dash-grid">

            <!-- ===== Calories ===== -->
            <a class="dash-card dash-grid__calories" href="<?= url('calorie') ?>">
                <span class="dash-card__eyebrow">Calories</span>

                <?php if ($calorieCard['has_targets']):
                    $todayCal  = $calorieCard['has_today'] ? (int) $calorieCard['today'] : 0;
                    $targetCal = (int) $calorieCard['target'];
                    $pct       = $targetCal > 0
                        ? max(0, min(100, round(($todayCal / $targetCal) * 100)))
                        : 0;
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

                    <?php if (($calorieCard['meals_today'] ?? 0) > 0 || isset($calorieCard['avg_7d'])): ?>
                        <div class="dash-card__stats">
                            <?php if (($calorieCard['meals_today'] ?? 0) > 0): ?>
                                <span>
                                    <strong><?= e((string) $calorieCard['meals_today']) ?></strong>
                                    meal<?= $calorieCard['meals_today'] === 1 ? '' : 's' ?> today
                                </span>
                            <?php endif; ?>
                            <?php if (isset($calorieCard['avg_7d'])): ?>
                                <span>
                                    <strong><?= e(number_format((int) $calorieCard['avg_7d'])) ?></strong>
                                    cal avg/day
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($calorieCard['has_macros']) && !empty($calorieCard['macros'])):
                        $macros = $calorieCard['macros']; ?>
                        <div class="macro-bars" aria-label="Today's macros vs target">
                            <?php foreach (['protein' => 'P', 'carbs' => 'C', 'fat' => 'F'] as $key => $letter):
                                $m   = $macros[$key];
                                $pct = $m['target_g'] > 0
                                    ? (int) round(max(0, min(100, ($m['g'] / $m['target_g']) * 100)))
                                    : 0;
                            ?>
                                <div class="macro-bar macro-bar--<?= $key ?>">
                                    <div class="macro-bar__meta">
                                        <span class="macro-bar__letter"><?= $letter ?></span>
                                        <span class="macro-bar__nums">
                                            <strong><?= (int) $m['g'] ?></strong> / <?= (int) $m['target_g'] ?> g
                                        </span>
                                    </div>
                                    <div class="macro-bar__track">
                                        <div class="macro-bar__fill"
                                             style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
                    <div class="dash-card__hint">
                        Set your calorie targets to start tracking.
                    </div>
                <?php endif; ?>
            </a>

            <!-- ===== Weight ===== -->
            <a class="dash-card dash-grid__weight" href="<?= url('weight') ?>">
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

                    <?php if (!empty($weightCard['sparkline']) && count($weightCard['sparkline']) >= 2):
                        // Tiny SVG sparkline — last ~14 weigh-ins, oldest →
                        // newest, in the user's display unit. Plot in a fixed
                        // viewBox (100w × 28h) and normalize the y-axis to the
                        // data range so even small wobbles read as a visible
                        // line. Direction class drives the stroke color
                        // (green = went down, warn = went up, dim = flat).
                        $pts = $weightCard['sparkline'];
                        $min = min($pts);
                        $max = max($pts);
                        $range = $max - $min;
                        $n = count($pts);

                        // Direction: latest vs oldest in this slice.
                        $sparkDelta = $pts[$n - 1] - $pts[0];
                        $sparkDir = abs($sparkDelta) < 0.05 ? 'flat'
                                  : ($sparkDelta < 0 ? 'down' : 'up');

                        $svgPoints = [];
                        foreach ($pts as $i => $v) {
                            $x = $n > 1 ? round(($i / ($n - 1)) * 100, 2) : 50;
                            // y is inverted (SVG 0 = top). Use a 3px top/bottom
                            // padding so the stroke doesn't clip at the edges.
                            $yNorm = $range > 0 ? ($v - $min) / $range : 0.5;
                            $y = round(3 + (1 - $yNorm) * 22, 2);  // 3..25
                            $svgPoints[] = $x . ',' . $y;
                        }
                        $lastIdx = $n - 1;
                        [$endX, $endY] = explode(',', $svgPoints[$lastIdx]);
                    ?>
                        <div class="weight-spark weight-spark--<?= e($sparkDir) ?>"
                             aria-hidden="true">
                            <svg viewBox="0 0 100 28" preserveAspectRatio="none"
                                 class="weight-spark__svg">
                                <polyline class="weight-spark__line"
                                          fill="none" stroke="currentColor"
                                          stroke-width="2"
                                          stroke-linecap="round"
                                          stroke-linejoin="round"
                                          vector-effect="non-scaling-stroke"
                                          points="<?= e(implode(' ', $svgPoints)) ?>"/>
                                <circle class="weight-spark__dot"
                                        cx="<?= e($endX) ?>" cy="<?= e($endY) ?>" r="2.4"
                                        fill="currentColor"/>
                            </svg>
                        </div>
                    <?php endif; ?>

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

                    <?php if (isset($weightCard['lifetime_diff'])):
                        $ld    = (float) $weightCard['lifetime_diff'];
                        $ldays = (int) ($weightCard['lifetime_days'] ?? 0);
                    ?>
                        <div class="dash-card__stats">
                            <span>
                                Lifetime
                                <?php if ($ld < 0): ?>
                                    <strong class="text-good">↓ <?= e(number_format(abs($ld), 1)) ?>
                                    <?= e($weightCard['unit']) ?></strong>
                                <?php elseif ($ld > 0): ?>
                                    <strong class="text-warn">↑ <?= e(number_format($ld, 1)) ?>
                                    <?= e($weightCard['unit']) ?></strong>
                                <?php else: ?>
                                    <strong>flat</strong>
                                <?php endif; ?>
                                over <?= e((string) $ldays) ?>d
                            </span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
                    <div class="dash-card__hint">
                        Log your first weigh-in to start the trend.
                    </div>
                <?php endif; ?>
            </a>

            <!-- ===== Strength (with 1RM ↔ All-time inset toggle) ===== -->
            <article class="dash-card dash-grid__strength" id="dashStrengthCard">
                <header class="dash-card__head">
                    <span class="dash-card__eyebrow">Strength</span>
                    <a class="dash-card__view-all" href="<?= url('strength') ?>">View all</a>
                </header>

                <?php if ($strengthCard['has_logs']): ?>
                    <!-- Inset toggle: swaps the right-hand number on each
                         lift row between "current 1RM" (today's latest
                         entry's est. 1RM) and "all-time best" 1RM. The
                         goal bar's % stays tied to all-time (that's the
                         honest answer to "how close am I to target"). -->
                    <div class="strength-toggle" role="tablist"
                         aria-label="Strength figure to display">
                        <button type="button" class="is-active"
                                role="tab" aria-selected="true"
                                data-mode="current">1RM</button>
                        <button type="button"
                                role="tab" aria-selected="false"
                                data-mode="alltime">All-time</button>
                    </div>

                    <ul class="strength-list">
                        <?php foreach (['bench', 'squat', 'deadlift'] as $key):
                            $sg     = $strengthCard['goals'][$key]   ?? null;
                            $disp   = $strengthCard['display'][$key] ?? null;
                            $isPr   = !empty($strengthCard['is_pr'][$key]);
                            $label  = $strengthCard['labels'][$key] ?? ucfirst($key);
                        ?>
                            <li class="strength-list__row">
                                <div class="strength-list__head">
                                    <span class="strength-list__lift">
                                        <?= e($label) ?>
                                        <?php if ($isPr): ?>
                                            <span class="dash-pr-badge"
                                                  title="Personal record (estimated 1-rep max)"
                                                  aria-label="Personal record">PR</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($disp): ?>
                                        <span class="strength-list__value">
                                            <span data-current="<?= e((string) $disp['current_orm']) ?>"
                                                  data-alltime="<?= e((string) $disp['alltime_orm']) ?>"
                                                  class="strength-list__num">
                                                <?= e(number_format($disp['current_orm'], 0)) ?>
                                            </span>
                                            <span class="strength-list__unit"><?= e($disp['unit']) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="strength-list__value text-faint">—</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($sg): ?>
                                    <div class="goal-bar goal-bar--compact"
                                         aria-label="<?= e(ucfirst($key)) ?> goal progress">
                                        <div class="goal-bar__track">
                                            <div class="goal-bar__fill"
                                                 style="width: <?= e((string) $sg['pct']) ?>%"></div>
                                        </div>
                                        <span class="goal-bar__sub">
                                            target <?= e(number_format($sg['target_display'], 0)) ?>
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
                        Log your first big-three lift to start tracking PRs.
                    </div>
                <?php endif; ?>
            </article>

            <!-- ===== This week (workouts bar + recent activity) ===== -->
            <article class="dash-card dash-grid__thisweek">
                <header class="dash-card__head">
                    <span class="dash-card__eyebrow">This week</span>
                </header>

                <?php if (!empty($thisWeek['workouts_target'])): ?>
                    <div class="weekly-bar" aria-label="Workouts this week vs target">
                        <div class="weekly-bar__meta">
                            <span class="weekly-bar__label">Workouts this week</span>
                            <span class="weekly-bar__value">
                                <strong><?= e((string) $thisWeek['workouts_done']) ?></strong>
                                <span>/ <?= e((string) $thisWeek['workouts_target']) ?> days</span>
                            </span>
                        </div>
                        <div class="weekly-bar__track">
                            <div class="weekly-bar__fill"
                                 style="width: <?= e((string) $thisWeek['workouts_pct']) ?>%"></div>
                        </div>
                    </div>
                <?php elseif ($thisWeek['workouts_done'] > 0): ?>
                    <div class="weekly-bar weekly-bar--no-target">
                        <div class="weekly-bar__meta">
                            <span class="weekly-bar__label">Workouts this week</span>
                            <span class="weekly-bar__value">
                                <strong><?= e((string) $thisWeek['workouts_done']) ?></strong>
                                <span>day<?= $thisWeek['workouts_done'] === 1 ? '' : 's' ?></span>
                            </span>
                        </div>
                        <p class="weekly-bar__hint">
                            Set a weekly workout target in
                            <a href="<?= url('profile') ?>">your profile</a>
                            to track it here.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="recent-activity">
                    <h3 class="recent-activity__title">Recent activity</h3>
                    <?php if (!empty($thisWeek['recent'])): ?>
                        <ul class="recent-activity__list">
                            <?php foreach ($thisWeek['recent'] as $item): ?>
                                <li class="recent-activity__item">
                                    <span class="recent-activity__dot recent-activity__dot--<?= e($item['type']) ?>"
                                          aria-hidden="true"></span>
                                    <a href="<?= e($item['href']) ?>"
                                       class="recent-activity__link">
                                        <?= e($item['summary']) ?>
                                    </a>
                                    <span class="recent-activity__when">
                                        <?= e($relativeDay($item['date'])) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="recent-activity__empty">
                            Log something to see your latest activity here.
                        </p>
                    <?php endif; ?>
                </div>
            </article>

            <!-- ===== Cardio ===== -->
            <a class="dash-card dash-grid__cardio" href="<?= url('cardio') ?>">
                <span class="dash-card__eyebrow">
                    <svg class="dash-card__eyebrow-icon" viewBox="0 0 24 24"
                         fill="currentColor" aria-hidden="true">
                        <path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
                    </svg>
                    Cardio
                </span>

                <?php if ($cardioCard['has_logs']): ?>
                    <?php if ($cardioCard['target'] !== null): ?>
                        <div class="dash-card__value">
                            <?= e((string) $cardioCard['sessions_week']) ?>
                            <span class="dash-card__unit">/ <?= e((string) $cardioCard['target']) ?> sessions</span>
                        </div>
                        <!-- Continuous progress bar, sized prominently so it
                             reads as the card's headline metric. Width caps
                             at 100% even if the user blew past their goal. -->
                        <div class="cardio-bar" aria-label="Cardio sessions this week vs target">
                            <div class="cardio-bar__fill"
                                 style="width: <?= e((string) $cardioCard['pct']) ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="dash-card__value">
                            <?= e((string) $cardioCard['minutes_week']) ?>
                            <span class="dash-card__unit">min this week</span>
                        </div>
                        <div class="dash-card__hint">
                            <?= e((string) $cardioCard['sessions_week']) ?> session<?= $cardioCard['sessions_week'] === 1 ? '' : 's' ?>
                            this week.
                        </div>
                    <?php endif; ?>

                    <?php if ($cardioCard['target'] !== null): ?>
                        <!-- Time / distance / progress stacked vertically so
                             the card body fills out without the metrics getting
                             crammed into a single ·-separated hint line. -->
                        <dl class="cardio-stats">
                            <div class="cardio-stats__row">
                                <dt>Time</dt>
                                <dd><?= e((string) $cardioCard['minutes_week']) ?>
                                    <span class="cardio-stats__unit">min</span></dd>
                            </div>
                            <?php if ($cardioCard['distance_week'] !== null): ?>
                                <div class="cardio-stats__row">
                                    <dt>Distance</dt>
                                    <dd><?= e(rtrim(rtrim(number_format($cardioCard['distance_week'], 1), '0'), '.')) ?>
                                        <span class="cardio-stats__unit"><?= e($cardioCard['distance_unit']) ?></span></dd>
                                </div>
                            <?php endif; ?>
                            <div class="cardio-stats__row">
                                <dt>Progress</dt>
                                <dd><?= e((string) $cardioCard['pct']) ?><span class="cardio-stats__unit">%</span></dd>
                            </div>
                        </dl>
                    <?php elseif ($cardioCard['distance_week'] !== null): ?>
                        <!-- No target set, but distance was recorded — still
                             surface it so the data the user logged shows up. -->
                        <dl class="cardio-stats">
                            <div class="cardio-stats__row">
                                <dt>Distance</dt>
                                <dd><?= e(rtrim(rtrim(number_format($cardioCard['distance_week'], 1), '0'), '.')) ?>
                                    <span class="cardio-stats__unit"><?= e($cardioCard['distance_unit']) ?></span></dd>
                            </div>
                        </dl>
                    <?php endif; ?>

                    <?php if (!empty($cardioCard['latest'])):
                        $l = $cardioCard['latest'];
                        $type = $cardioCard['type_labels'][$l['cardio_type']] ?? $l['cardio_type'];
                        $line = $type . ' · ' . ((int) $l['duration_min']) . ' min';
                        if (!empty($l['intensity'])) {
                            $line .= ' · ' . ($cardioCard['intensity_labels'][$l['intensity']] ?? $l['intensity']);
                        }
                    ?>
                        <div class="dash-card__hint dash-card__hint--last">
                            <span class="dash-card__hint-label">Last session</span>
                            <?= e($line) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dash-card__value dash-card__value--placeholder"><?= empty_state_icon('card') ?></div>
                    <div class="dash-card__hint">
                        Log your first walk, run, or ride to start tracking.
                    </div>
                <?php endif; ?>
            </a>

            <!-- ===== Goals & progress (full-width row, nested goals-strip) ===== -->
            <?php if (!empty($goalRows)): ?>
            <article class="dash-card dash-grid__goals">
                <header class="dash-card__head">
                    <span class="dash-card__eyebrow">Goals &amp; progress</span>
                    <a class="dash-card__view-all" href="<?= url('profile') ?>">Edit goals</a>
                </header>

                <ul class="goals-strip goals-strip--nested">
                    <?php foreach ($goalRows as $g): ?>
                        <li class="goals-strip__row">
                            <span class="goals-strip__icon" aria-hidden="true"><?= e($g['icon']) ?></span>
                            <span class="goals-strip__label"><?= e($g['label']) ?></span>
                            <div class="goals-strip__track">
                                <div class="goals-strip__fill"
                                     style="width: <?= e((string) $g['pct']) ?>%"></div>
                            </div>
                            <span class="goals-strip__pct"><?= e((string) $g['pct']) ?>%</span>
                            <span class="goals-strip__suffix"><?= e($g['suffix']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endif; ?>

        </div>
    </div>
</section>


<!-- ===================== Progress over time ===================== -->
<?php
$hasAnyChart =
    !empty($intakeChartData)
    || !empty($weightChartData)
    || !empty($strengthChartData)
    || !empty($cardioChartData);
?>
<section class="section section--alt">
    <div class="container container--wide">
        <div class="section-head">
            <h2>Progress over time</h2>
            <p>Trends across each tracker. Hover for details — or open the full tracker for range filters and the log form.</p>
        </div>

        <div class="dash-charts">

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Calorie intake</h3>
                    <a class="dash-card__view-all" href="<?= url('calorie') ?>">Open tracker →</a>
                </header>
                <?php if (!empty($intakeChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact chart-wrap--loading">
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
                    <a class="dash-card__view-all" href="<?= url('weight') ?>">Open tracker →</a>
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
                    <a class="dash-card__view-all" href="<?= url('strength') ?>">Open tracker →</a>
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

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <h3>Cardio (daily minutes)</h3>
                    <a class="dash-card__view-all" href="<?= url('cardio') ?>">Open tracker →</a>
                </header>
                <?php if (!empty($cardioChartData)): ?>
                    <div class="chart-wrap chart-wrap--compact chart-wrap--loading">
                        <canvas id="cardioChart"
                                role="img"
                                aria-label="Recent daily cardio minutes bar chart. Open the cardio tracker for full data."
                                data-rows='<?= e(json_encode($cardioChartData, JSON_THROW_ON_ERROR)) ?>'>
                        </canvas>
                    </div>
                <?php else: ?>
                    <div class="dash-chart-empty">
                        <?= empty_state_icon('sm') ?>
                        <p>No cardio logged yet. Add your first walk, run, or ride to start tracking minutes.</p>
                        <a href="<?= url('cardio') ?>" class="btn btn-secondary btn-inline">Log a session</a>
                    </div>
                <?php endif; ?>
            </article>

        </div>
    </div>
</section>

<?php if ($hasAnyChart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        defer></script>
<?php endif; ?>


<!-- ===================== Quick actions ===================== -->
<section class="section section--alt section--tight">
    <div class="container container--wide">
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

            <li>
                <a class="action-strip__item" href="<?= url('cardio') ?>"
                   aria-label="Log a cardio session">
                    <img class="action-strip__icon"
                         src="<?= asset('images/cardio.png') ?>"
                         alt="" width="44" height="44">
                    <span class="action-strip__body">
                        <span class="action-strip__title">Log cardio</span>
                        <span class="action-strip__caption">Walks, runs, rides, more</span>
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
