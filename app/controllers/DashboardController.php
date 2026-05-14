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
        ];

        // ----- Stat strip (under greeting) -------------------------
        // Four small "momentum" stats. Computed in one block so the
        // view just renders them. Each stat returns null when there's
        // nothing meaningful yet, and the view shows a muted dash.
        $sevenAgoDate = (new DateTime('today'))->modify('-6 days')->format('Y-m-d');
        $statStrip = [
            'streak'        => $this->computeLoggingStreak($userId),
            'meals_week'    => CalorieIntake::countForUserSince($userId, $sevenAgoDate),
            'lifts_week'    => StrengthLog::countForUserSince($userId, $sevenAgoDate),
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

        $this->view('dashboard/index', [
            'title'              => 'Dashboard',
            'active'             => 'dashboard',
            'today'              => $today,
            'displayName'        => $user['name'] ?? 'there',
            'calorieCard'        => $calorieCard,
            'weightCard'         => $weightCard,
            'strengthCard'       => $strengthCard,
            'statStrip'          => $statStrip,

            // Chart payloads — empty arrays mean "don't render".
            'intakeChartData'    => $intakeChartData,
            'intakeChartTargets' => $intakeChartTargets,
            'activeGoal'         => $activeGoal,
            'weightChartData'    => $weightChartData,
            'weightChartUnit'    => $weightChartUnit,
            'strengthChartData'  => $strengthChartData,
            'strengthChartUnit'  => $strengthChartUnit,
        ]);
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
             ) d
             ORDER BY logged_date DESC
             LIMIT 366'
        );
        $stmt->execute([$userId, $userId, $userId]);
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
