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
        $todayIntake   = CalorieIntake::forUserOnDate($userId, $today);

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
            'has_today'   => (bool) $todayIntake,
            'today'       => $todayIntake ? (int) $todayIntake['calories'] : null,
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
        }

        // ----- Strength summary ------------------------------------
        $latestPerLift = StrengthLog::latestPerLiftForUser($userId);

        $strengthCard = [
            'has_logs' => (bool) array_filter($latestPerLift),
            'lifts'    => $latestPerLift,   // ['bench' => row|null, ...]
            'labels'   => StrengthLog::LIFT_LABELS,
        ];

        // ----- Chart data (oldest-first, shaped for the existing
        // chart IIFEs in main.js — same canvas IDs and data attrs
        // as the tracker pages, so the JS just works).
        $intakeHistory = CalorieIntake::forUser($userId);
        $intakeChartData = array_map(
            static fn(array $r): array => [
                'date'     => $r['logged_date'],
                'calories' => (int) $r['calories'],
            ],
            array_reverse($intakeHistory)
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

        $strengthHistory = StrengthLog::forUser($userId);
        $strengthChartData = array_map(
            static fn(array $r): array => [
                'date'      => $r['logged_date'],
                'lift_type' => $r['lift_type'],
                'weight_kg' => StrengthLog::toKg((float) $r['weight'], $r['unit']),
                'reps'      => (int) $r['reps'],
            ],
            array_reverse($strengthHistory)
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
}
