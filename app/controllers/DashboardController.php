<?php
// ============================================================
// app/controllers/DashboardController.php
// The main hub after login. Pulls "latest" data from each tracker,
// computes a small weight-trend over the last 7 days, and hands
// everything off to the view as plain arrays.
//
// Routes:
//   GET /dashboard → index()
// ============================================================

class DashboardController extends Controller {

    private const LB_PER_KG = 2.2046226218;

    public function index(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $today  = date('Y-m-d');

        $user = User::find($userId);

        // ----- Calorie summary -------------------------------------
        $latestTargets = CalorieLog::latestForUser($userId);
        $todayTotal    = CalorieIntake::totalForUserOnDate($userId, $today);

        // Active goal drives which target we compare today's intake against.
        $activeGoal = $user['current_goal'] ?? 'maintain';
        if (!in_array($activeGoal, User::ALLOWED_GOALS, true)) {
            $activeGoal = 'maintain';
        }
        $goalColumn = match ($activeGoal) {
            'cut'   => 'cutting_calories',
            'bulk'  => 'bulking_calories',
            default => 'maintenance_calories',
        };
        $goalLabel = match ($activeGoal) {
            'cut'  => 'cut',
            'bulk' => 'bulk',
            default => 'maintenance',
        };

        $calorieCard = [
            'has_targets' => (bool) $latestTargets,
            'has_today'   => $todayTotal > 0,
            'today'       => $todayTotal > 0 ? $todayTotal : null,
            'target'      => $latestTargets ? (int) $latestTargets[$goalColumn] : null,
            'goal_label'  => $goalLabel,
        ];
        if ($calorieCard['has_targets'] && $calorieCard['has_today']) {
            $calorieCard['diff'] = $calorieCard['today'] - $calorieCard['target'];
        }

        // ----- Today's macros (Tier 2) -----------------------------
        // Sum protein/carbs/fat across today's meals, plus auto-compute
        // a per-macro target from the active calorie target using
        // standard splits per goal (40/30/30 cut, 30/40/30 maintain,
        // 25/45/30 bulk). Bars only render when the user has actually
        // entered at least one macro on today's meals — we don't push
        // macro tracking onto people who don't care.
        $macroTotals = CalorieIntake::macroTotalsForUserOnDate($userId, $today);
        $macroSplits = [
            'cut'      => ['protein' => 0.40, 'carbs' => 0.30, 'fat' => 0.30],
            'maintain' => ['protein' => 0.30, 'carbs' => 0.40, 'fat' => 0.30],
            'bulk'     => ['protein' => 0.25, 'carbs' => 0.45, 'fat' => 0.30],
        ];
        $macroCalsPerG = ['protein' => 4, 'carbs' => 4, 'fat' => 9];
        $calorieCard['has_macros'] = $macroTotals['macro_rows'] > 0;
        if ($calorieCard['has_macros'] && $latestTargets) {
            $split = $macroSplits[$activeGoal];
            $targetCal = (int) $latestTargets[$goalColumn];
            $calorieCard['macros'] = [
                'protein' => [
                    'g'        => $macroTotals['protein_g'],
                    'target_g' => (int) round(($targetCal * $split['protein']) / $macroCalsPerG['protein']),
                ],
                'carbs' => [
                    'g'        => $macroTotals['carbs_g'],
                    'target_g' => (int) round(($targetCal * $split['carbs']) / $macroCalsPerG['carbs']),
                ],
                'fat' => [
                    'g'        => $macroTotals['fat_g'],
                    'target_g' => (int) round(($targetCal * $split['fat']) / $macroCalsPerG['fat']),
                ],
            ];
        }

        // ----- Weight summary --------------------------------------
        $weightHistory = WeightLog::forUser($userId);   // newest-first
        $latestWeight  = $weightHistory[0] ?? null;

        $weightCard = ['has_logs' => (bool) $latestWeight];
        if ($latestWeight) {
            $kg = (float) $latestWeight['weight_kg'];
            $weightCard['weight'] = $latestWeight['unit'] === 'kg'
                ? round($kg, 1)
                : round($kg * self::LB_PER_KG, 1);
            $weightCard['unit'] = $latestWeight['unit'];
            $weightCard['date'] = $latestWeight['logged_date'];

            // 7-day trend: walk the (newest-first) history and find the
            // oldest entry within the last 7 days. Compare its weight
            // (in the latest's unit) to the latest. Skip the trend if
            // there's nothing to compare.
            $sevenAgo  = (new DateTime('today'))->modify('-7 days')->format('Y-m-d');
            $weekStart = null;
            foreach ($weightHistory as $row) {
                if ($row['logged_date'] < $sevenAgo) break;
                $weekStart = $row;
            }
            if ($weekStart && (int) $weekStart['id'] !== (int) $latestWeight['id']) {
                $startKg = (float) $weekStart['weight_kg'];
                $startInLatestUnit = $latestWeight['unit'] === 'kg'
                    ? round($startKg, 1)
                    : round($startKg * self::LB_PER_KG, 1);
                $weightCard['trend_diff'] = round(
                    $weightCard['weight'] - $startInLatestUnit, 1
                );
                $weightCard['trend_days'] = (new DateTime($latestWeight['logged_date']))
                    ->diff(new DateTime($weekStart['logged_date']))->days;
            }

            // Weight goal progress (Tier 1 — uses user.target_weight_kg).
            // Progress is measured from the user's first-ever weigh-in
            // ("start") toward the target. Works for both cut (start >
            // target) and bulk (start < target).
            //
            // Edge cases:
            //   - No target set            → skip entirely.
            //   - Only one weigh-in        → no "start" yet, just show
            //                                target + remaining without %.
            //   - Already past the target  → pct clamps to 100, label
            //                                flips to "goal reached".
            $targetWeightKg = isset($user['target_weight_kg'])
                ? (float) $user['target_weight_kg']
                : null;
            if ($targetWeightKg !== null && $targetWeightKg > 0) {
                $unit         = $latestWeight['unit'];
                $latestKg     = (float) $latestWeight['weight_kg'];
                $targetDisp   = $unit === 'kg' ? $targetWeightKg : $targetWeightKg * self::LB_PER_KG;
                $remainingKg  = $latestKg - $targetWeightKg;   // signed
                $remainingDisp = $unit === 'kg'
                    ? round(abs($remainingKg), 1)
                    : round(abs($remainingKg) * self::LB_PER_KG, 1);

                $oldest       = end($weightHistory) ?: $latestWeight;
                $startKg      = (float) $oldest['weight_kg'];
                $rangeKg      = $startKg - $targetWeightKg;     // signed
                $progressKg   = $startKg - $latestKg;           // signed

                if (abs($remainingKg) < 0.05) {
                    $pct = 100; $direction = 'hit';
                } elseif (abs($rangeKg) < 0.05) {
                    // Start == target (already there at goal-set time).
                    $pct = 100; $direction = 'hit';
                } else {
                    $pct = (int) round(max(0, min(100, ($progressKg / $rangeKg) * 100)));
                    $direction = $rangeKg > 0 ? 'cut' : 'bulk';
                }

                $weightCard['goal'] = [
                    'pct'             => $pct,
                    'direction'       => $direction,        // cut | bulk | hit
                    'target_display'  => round($targetDisp, 1),
                    'remaining_display' => $remainingDisp,
                ];
            }

            // Sparkline data — last ~14 weigh-ins, oldest-first, in the
            // user's display unit. The view renders this as a small SVG
            // polyline so the empty space below the weight value shows
            // direction visually instead of just the textual "↓ 0.6 kg".
            // Only computed when there are ≥2 entries (a single point is
            // not a trend).
            if (count($weightHistory) >= 2) {
                $sparkSlice = array_slice($weightHistory, 0, 14);   // newest 14
                $sparkChrono = array_reverse($sparkSlice);           // oldest-first
                $unit = $latestWeight['unit'];
                $weightCard['sparkline'] = array_map(
                    static fn(array $r) => $unit === 'kg'
                        ? round((float) $r['weight_kg'], 1)
                        : round((float) $r['weight_kg'] * self::LB_PER_KG, 1),
                    $sparkChrono
                );
            }
        }

        // ----- Strength summary ------------------------------------
        $latestPerLift = StrengthLog::latestPerLiftForUser($userId);

        // PR flags: a "PR" badge appears next to a lift on the
        // strength card when its latest logged entry equals the best
        // estimated 1RM (Epley) we've ever seen for that lift type.
        // Computed in PHP over the full strength history (already
        // fetched below for the chart) so we don't issue an extra
        // query. Falls back to all-false when the user has no history.
        $strengthHistoryAll = StrengthLog::forUser($userId);
        $bestEst1rmKg = ['bench' => 0.0, 'squat' => 0.0, 'deadlift' => 0.0];
        foreach ($strengthHistoryAll as $row) {
            $kg     = StrengthLog::toKg((float) $row['weight'], $row['unit']);
            $reps   = (int) $row['reps'];
            $est1rm = $kg * (1.0 + $reps / 30.0);
            if ($est1rm > ($bestEst1rmKg[$row['lift_type']] ?? 0.0)) {
                $bestEst1rmKg[$row['lift_type']] = $est1rm;
            }
        }
        $isPr = ['bench' => false, 'squat' => false, 'deadlift' => false];
        foreach (['bench', 'squat', 'deadlift'] as $lift) {
            $latest = $latestPerLift[$lift] ?? null;
            if (!$latest) continue;
            $kg     = StrengthLog::toKg((float) $latest['weight'], $latest['unit']);
            $est1rm = $kg * (1.0 + ((int) $latest['reps']) / 30.0);
            // Tiny epsilon to absorb float-conversion noise.
            if (abs($est1rm - $bestEst1rmKg[$lift]) < 0.01 && $est1rm > 0) {
                $isPr[$lift] = true;
            }
        }

        // Current 1RM (latest entry's Epley est) vs all-time best 1RM,
        // both in the display unit of the latest row. Feeds the strength
        // card's "1RM | All-time" inset toggle — the bar's right-hand
        // number swaps between current and best, the goal bar's % stays
        // tied to all-time best (that's "where you are" toward target).
        $strengthDisplay = ['bench' => null, 'squat' => null, 'deadlift' => null];
        foreach (['bench', 'squat', 'deadlift'] as $lift) {
            $latest = $latestPerLift[$lift] ?? null;
            if (!$latest) continue;
            $unit       = $latest['unit'];
            $toDisp     = static fn(float $kg) => $unit === 'kg'
                ? round($kg, 1)
                : round($kg * self::LB_PER_KG, 1);
            $latestKg   = StrengthLog::toKg((float) $latest['weight'], $latest['unit']);
            $latest1rm  = $latestKg * (1.0 + ((int) $latest['reps']) / 30.0);
            $strengthDisplay[$lift] = [
                'unit'         => $unit,
                'current_orm'  => $toDisp($latest1rm),
                'alltime_orm'  => $toDisp($bestEst1rmKg[$lift] ?? 0.0),
            ];
        }

        // Strength goal progress per lift (Tier 1 — uses
        // user.target_{bench,squat,deadlift}_kg). For each lift we
        // compare the user's best-ever est-1RM (already computed
        // above) to their target. Bar fills as a % of target,
        // clamped to 100. Display unit = the unit the latest entry
        // for that lift was logged in (falls back to lbs).
        $strengthGoals = ['bench' => null, 'squat' => null, 'deadlift' => null];
        foreach (['bench', 'squat', 'deadlift'] as $lift) {
            $col = 'target_' . $lift . '_kg';
            $targetKg = isset($user[$col]) ? (float) $user[$col] : 0.0;
            if ($targetKg <= 0) continue;

            $currentKg = $bestEst1rmKg[$lift] ?? 0.0;
            // Display in the unit the latest entry for THIS lift was
            // logged in. Falls back to lbs if the user has never logged
            // this particular lift (target set but no entries yet).
            $unit = $latestPerLift[$lift]['unit'] ?? 'lbs';
            $toDisplay = static fn(float $kg) => $unit === 'kg'
                ? round($kg, 1)
                : round($kg * self::LB_PER_KG, 1);

            $strengthGoals[$lift] = [
                'pct'             => (int) round(max(0, min(100, ($currentKg / $targetKg) * 100))),
                'target_display'  => $toDisplay($targetKg),
                'current_display' => $toDisplay($currentKg),
                'unit'            => $unit,
            ];
        }

        $strengthCard = [
            'has_logs' => (bool) array_filter($latestPerLift),
            'lifts'    => $latestPerLift,   // ['bench' => row|null, ...]
            'labels'   => StrengthLog::LIFT_LABELS,
            'is_pr'    => $isPr,
            'goals'    => $strengthGoals,
            'display'  => $strengthDisplay, // current_orm + alltime_orm per lift
        ];

        // ----- Cardio summary --------------------------------------
        // Card surfaces this week's progress against the user's optional
        // weekly_cardio_target plus a "last session" preview line. The
        // window is the rolling 7 days (today inclusive) so a Monday
        // streak reads the same whether it's Tuesday or Sunday today.
        $sevenAgoDate = (new DateTime('today'))->modify('-6 days')->format('Y-m-d');

        $cardioWeekCount   = CardioLog::countForUserSince($userId, $sevenAgoDate);
        $cardioWeekMinutes = CardioLog::totalMinutesForUserSince($userId, $sevenAgoDate);
        $latestCardio      = CardioLog::latestForUser($userId);

        $cardioTarget = isset($user['weekly_cardio_target'])
            ? (int) $user['weekly_cardio_target']
            : 0;

        $cardioCard = [
            'has_logs'      => $latestCardio !== null,
            'sessions_week' => $cardioWeekCount,
            'minutes_week'  => $cardioWeekMinutes,
            'target'        => $cardioTarget > 0 ? $cardioTarget : null,
            'pct'           => $cardioTarget > 0
                ? (int) round(max(0, min(100, ($cardioWeekCount / $cardioTarget) * 100)))
                : null,
            'latest'        => $latestCardio,
            'type_labels'      => CardioLog::TYPE_LABELS,
            'intensity_labels' => CardioLog::INTENSITY_LABELS,
        ];

        // ----- "This week" card ------------------------------------
        // Two concerns living in one card:
        //   1. Workouts-this-week bar — distinct dates with ANY training
        //      activity (strength OR cardio) in the rolling 7-day window,
        //      compared against the user's optional weekly_workout_target.
        //   2. Recent activity feed — latest N entries across all four
        //      trackers, normalized into a uniform shape for the view.
        $strengthDates = StrengthLog::countLoggedDaysForUser($userId) > 0
            ? array_unique(array_map(
                static fn(array $r): string => $r['logged_date'],
                array_filter(
                    $strengthHistoryAll,
                    static fn(array $r): bool => $r['logged_date'] >= $sevenAgoDate
                )
            ))
            : [];
        $cardioDates = CardioLog::distinctDatesForUserSince($userId, $sevenAgoDate);
        $workoutDates = array_unique(array_merge($strengthDates, $cardioDates));
        $workoutsDone = count($workoutDates);

        $workoutTarget = isset($user['weekly_workout_target'])
            ? (int) $user['weekly_workout_target']
            : 0;

        $thisWeek = [
            'workouts_done'   => $workoutsDone,
            'workouts_target' => $workoutTarget > 0 ? $workoutTarget : null,
            'workouts_pct'    => $workoutTarget > 0
                ? (int) round(max(0, min(100, ($workoutsDone / $workoutTarget) * 100)))
                : null,
            'recent'          => $this->buildRecentActivity($userId, 5),
        ];

        // ----- Stat strip (under greeting) -------------------------
        // Four small "momentum" stats. Computed in one block so the
        // view just renders them. Each stat returns null when there's
        // nothing meaningful yet, and the view shows a muted dash.
        // $sevenAgoDate was set above next to the cardio block.
        $statStrip = [
            'streak'        => $this->computeLoggingStreak($userId),
            'meals_week'    => CalorieIntake::countForUserSince($userId, $sevenAgoDate),
            'lifts_week'    => StrengthLog::countForUserSince($userId, $sevenAgoDate),
            'cardio_week'   => $cardioWeekCount,
            'weight_delta'  => $weightCard['trend_diff'] ?? null,
            'weight_unit'   => $weightCard['unit'] ?? null,
            'weight_days'   => $weightCard['trend_days'] ?? null,
        ];

        // ----- Chart data (oldest-first, shaped for the existing
        // chart IIFEs in main.js — same canvas IDs and data attrs
        // as the tracker pages, so the JS just works).
        // Daily totals (summed across meals) drive the chart now that
        // intake is per-meal.
        $intakeChartData = array_map(
            static fn(array $r): array => [
                'date'     => $r['logged_date'],
                'calories' => (int) $r['calories'],
            ],
            array_reverse(CalorieIntake::dailyTotalsForUser($userId))
        );
        $intakeChartTargets = $latestTargets ? [
            'cut'         => (int) $latestTargets['cutting_calories'],
            'maintenance' => (int) $latestTargets['maintenance_calories'],
            'bulk'        => (int) $latestTargets['bulking_calories'],
        ] : null;

        $weightChartData = array_map(
            static fn(array $r): array => [
                'date'      => $r['logged_date'],
                'weight_kg' => (float) $r['weight_kg'],
            ],
            array_reverse($weightHistory)
        );
        $weightChartUnit = $latestWeight['unit'] ?? 'lbs';

        // Reuse $strengthHistoryAll (already fetched for PR computation).
        $strengthChartData = array_map(
            static fn(array $r): array => [
                'date'      => $r['logged_date'],
                'lift_type' => $r['lift_type'],
                'weight_kg' => StrengthLog::toKg((float) $r['weight'], $r['unit']),
                'reps'      => (int) $r['reps'],
            ],
            array_reverse($strengthHistoryAll)
        );
        $latestStrength = StrengthLog::latestForUser($userId);
        $strengthChartUnit = $latestStrength['unit'] ?? 'lbs';

        // Cardio trend chart — same daily-totals shape as the cardio
        // page's chart, lets the existing initCardioChart() IIFE in
        // main.js paint it identically here on the dashboard.
        $cardioHistoryAll  = CardioLog::forUser($userId);
        $cardioChartData   = array_map(
            static fn(array $r): array => [
                'date'         => $r['logged_date'],
                'cardio_type'  => $r['cardio_type'],
                'duration_min' => (int) $r['duration_min'],
            ],
            array_reverse($cardioHistoryAll)
        );

        $this->view('dashboard/index', [
            'title'              => 'Dashboard',
            'active'             => 'dashboard',
            'today'              => $today,
            'displayName'        => $user['name'] ?? 'there',
            'calorieCard'        => $calorieCard,
            'weightCard'         => $weightCard,
            'strengthCard'       => $strengthCard,
            'cardioCard'         => $cardioCard,
            'thisWeek'           => $thisWeek,
            'statStrip'          => $statStrip,
            'user'               => $user,

            // Chart payloads — empty arrays mean "don't render".
            'intakeChartData'    => $intakeChartData,
            'intakeChartTargets' => $intakeChartTargets,
            'activeGoal'         => $activeGoal,
            'weightChartData'    => $weightChartData,
            'weightChartUnit'    => $weightChartUnit,
            'strengthChartData'  => $strengthChartData,
            'strengthChartUnit'  => $strengthChartUnit,
            'cardioChartData'    => $cardioChartData,
        ]);
    }

    // Latest N entries across all four trackers, normalized into a
    // uniform { type, date, summary, href } shape for the dashboard's
    // recent-activity feed. Pulls 5×$limit from each table (because we
    // merge then truncate, and a single tracker could dominate) so the
    // final list still reflects mixed activity even with bursts in one.
    private function buildRecentActivity(int $userId, int $limit): array {
        $perTracker = max(1, $limit);

        $items = [];

        // Strength — newest-first via the existing forUser() (no since
        // limit), then slice. forUser sorts DESC so the slice is
        // already the most recent.
        foreach (array_slice(StrengthLog::forUser($userId), 0, $perTracker) as $r) {
            $w = rtrim(rtrim((string) $r['weight'], '0'), '.');
            $items[] = [
                'type'    => 'strength',
                'date'    => $r['logged_date'],
                'id'      => (int) $r['id'],
                'summary' => sprintf(
                    '%s %s %s × %s',
                    StrengthLog::LIFT_LABELS[$r['lift_type']] ?? $r['lift_type'],
                    $w,
                    $r['unit'],
                    $r['reps']
                ),
                'href' => url('strength'),
            ];
        }

        // Cardio
        foreach (array_slice(CardioLog::forUser($userId), 0, $perTracker) as $r) {
            $label = CardioLog::TYPE_LABELS[$r['cardio_type']] ?? ucfirst($r['cardio_type']);
            $summary = $label . ' · ' . ((int) $r['duration_min']) . ' min';
            if (!empty($r['intensity'])) {
                $summary .= ' · ' . (CardioLog::INTENSITY_LABELS[$r['intensity']] ?? $r['intensity']);
            }
            $items[] = [
                'type'    => 'cardio',
                'date'    => $r['logged_date'],
                'id'      => (int) $r['id'],
                'summary' => $summary,
                'href'    => url('cardio'),
            ];
        }

        // Weight
        foreach (array_slice(WeightLog::forUser($userId), 0, $perTracker) as $r) {
            $w = rtrim(rtrim((string) round((float) $r['weight_kg'] * (
                $r['unit'] === 'kg' ? 1 : self::LB_PER_KG
            ), 1), '0'), '.');
            $items[] = [
                'type'    => 'weight',
                'date'    => $r['logged_date'],
                'id'      => (int) $r['id'],
                'summary' => 'Weigh-in ' . $w . ' ' . $r['unit'],
                'href'    => url('weight'),
            ];
        }

        // Calorie meals — pull recent across all dates via a quick
        // direct query (the existing per-day-totals API doesn't return
        // individual meal rows in date order).
        $stmt = db()->prepare(
            'SELECT id, label, calories, logged_date
               FROM calorie_intake_logs
              WHERE user_id = ?
              ORDER BY logged_date DESC, id DESC
              LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $perTracker, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $name = $r['label'] !== null && $r['label'] !== ''
                ? $r['label']
                : 'Meal';
            $items[] = [
                'type'    => 'calorie',
                'date'    => $r['logged_date'],
                'id'      => (int) $r['id'],
                'summary' => $name . ' ' . number_format((int) $r['calories']) . ' cal',
                'href'    => url('calorie'),
            ];
        }

        // Sort newest-first; tie-break on id within the same date so
        // two same-day entries from different trackers stay grouped.
        usort($items, static function (array $a, array $b): int {
            $c = strcmp($b['date'], $a['date']);
            if ($c !== 0) return $c;
            return $b['id'] <=> $a['id'];
        });

        return array_slice($items, 0, $limit);
    }

    // "Streak" = consecutive days, ending today or yesterday, on which
    // the user logged anything in any tracker. Yesterday qualifies as
    // the trailing edge so the streak doesn't drop to 0 before the
    // user has had a chance to log today.
    //
    // Single UNION query across all three log tables, then walk the
    // newest-first distinct-date list counting consecutive days.
    private function computeLoggingStreak(int $userId): int {
        $stmt = db()->prepare(
            'SELECT logged_date FROM (
                SELECT DISTINCT logged_date FROM calorie_intake_logs WHERE user_id = ?
                UNION
                SELECT DISTINCT logged_date FROM weight_logs         WHERE user_id = ?
                UNION
                SELECT DISTINCT logged_date FROM strength_logs       WHERE user_id = ?
                UNION
                SELECT DISTINCT logged_date FROM cardio_logs         WHERE user_id = ?
             ) d
             ORDER BY logged_date DESC
             LIMIT 366'
        );
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $dates = array_column($stmt->fetchAll(), 'logged_date');
        if (!$dates) return 0;

        $today     = new DateTime('today');
        $yesterday = (clone $today)->modify('-1 day');
        $first     = new DateTime($dates[0]);

        // Streak only counts if the most recent log is today or yesterday.
        if ($first->format('Y-m-d') !== $today->format('Y-m-d')
            && $first->format('Y-m-d') !== $yesterday->format('Y-m-d')) {
            return 0;
        }

        $streak = 1;
        $prev   = $first;
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $curr     = new DateTime($dates[$i]);
            $dayDiff  = (int) $prev->diff($curr)->days;
            if ($dayDiff === 1) {
                $streak++;
                $prev = $curr;
            } else {
                break;
            }
        }
        return $streak;
    }
}
