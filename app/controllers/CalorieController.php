<?php
// ============================================================
// app/controllers/CalorieController.php
// Calorie tracker — split into two concerns:
//
//   1. Targets — calorie_logs rows. Calculated occasionally from
//      the user's stats (age/sex/height/weight/activity) using the
//      Mifflin-St Jeor formula. Latest row = current targets.
//
//   2. Daily intake — calorie_intake_logs rows. One row per
//      (user, date), upserted when re-saved. The data points the
//      user logs every day, plotted against their target as a line.
//
// Plus: the user's "current goal" (cut / maintain / bulk) lives on
// users.current_goal and drives which target the intake hint, the
// history table column, and the chart highlight key off.
//
// Routes:
//   GET  /calorie                → index()
//   POST /calorie/targets        → saveTargets()    (snapshot of stats + targets)
//   POST /calorie/intake         → saveIntake()     (add one meal/entry)
//   GET  /calorie/intake/edit    → editIntake()     (render edit form)
//   POST /calorie/intake/update  → updateIntake()   (save edits)
//   POST /calorie/intake/delete  → deleteIntake()
//   POST /calorie/goal           → setGoal()        (cut / maintain / bulk)
// ============================================================

class CalorieController extends Controller {

    // Mifflin-St Jeor activity multipliers. Keys must match the
    // calorie_logs.activity_level ENUM exactly.
    private const ACTIVITY_MULTIPLIERS = [
        'sedentary'         => 1.2,
        'lightly_active'    => 1.375,
        'moderately_active' => 1.55,
        'very_active'       => 1.725,
        'extra_active'      => 1.9,
    ];

    private const LB_PER_KG = 2.2046226218;
    private const CM_PER_IN = 2.54;

    // GET /calorie ------------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $today  = date('Y-m-d');

        // Active goal — drives intake hint copy, history column, and
        // chart line highlight. Falls back to 'maintain' if the column
        // is somehow missing (older users / pre-migration).
        $user       = User::find($userId);
        $activeGoal = $user['current_goal'] ?? 'maintain';
        if (!in_array($activeGoal, User::ALLOWED_GOALS, true)) {
            $activeGoal = 'maintain';
        }

        // Targets: most recent stat-snapshot row drives the targets
        // card and pre-fills the (collapsed) stats form.
        $latest = CalorieLog::latestForUser($userId);

        // Intake: full per-entry history (newest-first) for the table,
        // today's individual entries for the "Today's meals" list,
        // plus today's running total for the headline.
        $intakeHistory = CalorieIntake::forUser($userId);
        $todayMeals    = CalorieIntake::forUserOnDate($userId, $today);
        $todayTotal    = CalorieIntake::totalForUserOnDate($userId, $today);

        // Chart shows daily totals (summed across meals) — oldest-first
        // for left-to-right time progression.
        $intakeChartData = array_map(static fn(array $r): array => [
            'date'     => $r['logged_date'],
            'calories' => (int) $r['calories'],
        ], array_reverse(CalorieIntake::dailyTotalsForUser($userId)));

        // Stats prefill (mirrors what the form needs in either unit
        // pane). Empty array for first-time users → blank form.
        $prefill = [];
        if ($latest) {
            $heightCm = (float) $latest['height_cm'];
            $weightKg = (float) $latest['weight_kg'];

            $totalIn = $heightCm / self::CM_PER_IN;
            $ft      = (int) floor($totalIn / 12);
            $in      = (int) round($totalIn - $ft * 12);
            if ($in === 12) { $ft++; $in = 0; }

            $prefill = [
                'age'            => (string) (int) $latest['age'],
                'gender'         => $latest['gender'],
                'activity_level' => $latest['activity_level'],
                'height_cm'      => (string) (float) round($heightCm, 1),
                'weight_kg'      => (string) (float) round($weightKg, 1),
                'height_ft'      => (string) $ft,
                'height_in'      => (string) $in,
                'weight_lb'      => (string) (float) round($weightKg * self::LB_PER_KG, 1),
            ];
        }

        // If the previous request was a failed intake save, the
        // "open the stats form?" question is no — keep targets card
        // collapsed. If it was a failed targets save, force-open the
        // form so the user sees their errors.
        $statsFormOpen = !empty(field_error('age'))
                      || !empty(field_error('gender'))
                      || !empty(field_error('activity_level'))
                      || !empty(field_error('height_ft'))
                      || !empty(field_error('height_cm'))
                      || !empty(field_error('weight_lb'))
                      || !empty(field_error('weight_kg'))
                      || !empty(field_error('logged_date'));

        $this->view('calorie/index', [
            'title'           => 'Calorie tracker',
            'active'          => 'dashboard',
            'today'           => $today,
            'latest'          => $latest,
            'intakeHistory'   => $intakeHistory,
            'intakeChartData' => $intakeChartData,
            'todayMeals'      => $todayMeals,
            'todayTotal'      => $todayTotal,
            'levels'          => CalorieLog::ACTIVITY_LEVELS,
            'prefill'         => $prefill,
            'statsFormOpen'   => $statsFormOpen,
            'activeGoal'      => $activeGoal,
            'flashInline'     => true,
        ]);
    }

    // POST /calorie/goal ------------------------------------------
    public function setGoal(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $goal   = $_POST['goal'] ?? '';

        if (in_array($goal, User::ALLOWED_GOALS, true)) {
            User::updateGoal($userId, $goal);
            // No flash banner — it'd feel noisy for a one-click pill change.
            // The reloaded page itself reflects the choice.
            // Tell the layout to skip the page-entry fade so the toggle
            // feels instant (read-and-cleared inside header.php).
            flash('skip_page_entry', '1');
        }

        $this->redirect('calorie');
    }

    // POST /calorie/targets ---------------------------------------
    // Save a new stats snapshot and (re)compute the targets.
    public function saveTargets(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();

        // ----- Read raw input ------------------------------------
        $unitSystem    = $_POST['unit_system']    ?? 'us';
        $age           = $_POST['age']            ?? '';
        $gender        = $_POST['gender']         ?? '';
        $activityLevel = $_POST['activity_level'] ?? '';
        $loggedDate    = $_POST['logged_date']    ?? '';

        $heightFt = $_POST['height_ft'] ?? '';
        $heightIn = $_POST['height_in'] ?? '';
        $weightLb = $_POST['weight_lb'] ?? '';
        $heightCm = $_POST['height_cm'] ?? '';
        $weightKg = $_POST['weight_kg'] ?? '';

        // ----- Validate ------------------------------------------
        $errors = [];

        $ageInt = null;
        if ($age === '' || !ctype_digit((string) $age)) {
            $errors['age'] = 'Age must be a whole number.';
        } else {
            $ageInt = (int) $age;
            if ($ageInt < 13 || $ageInt > 100) {
                $errors['age'] = 'Age must be between 13 and 100.';
            }
        }

        if (!in_array($gender, ['male', 'female'], true)) {
            $errors['gender'] = 'Please select a gender.';
        }

        if (!array_key_exists($activityLevel, self::ACTIVITY_MULTIPLIERS)) {
            $errors['activity_level'] = 'Please select an activity level.';
        }

        // Date — required, valid, not in the future. The "!" zeroes the
        // time so today's date isn't read as a future timestamp.
        if ($loggedDate === '') {
            $errors['logged_date'] = 'Date is required.';
        } else {
            $d = DateTime::createFromFormat('!Y-m-d', $loggedDate);
            if (!$d || $d->format('Y-m-d') !== $loggedDate) {
                $errors['logged_date'] = 'Please enter a valid date.';
            } elseif ($d > new DateTime('today')) {
                $errors['logged_date'] = 'Date cannot be in the future.';
            }
        }

        if (!in_array($unitSystem, ['us', 'metric'], true)) {
            $unitSystem = 'us';
        }

        $heightCmFinal = null;
        $weightKgFinal = null;

        if ($unitSystem === 'us') {
            $ftValid = ctype_digit((string) $heightFt)
                    && (int) $heightFt >= 3 && (int) $heightFt <= 8;
            $inRaw   = $heightIn === '' ? '0' : (string) $heightIn;
            $inValid = ctype_digit($inRaw)
                    && (int) $inRaw >= 0 && (int) $inRaw <= 11;

            if (!$ftValid || !$inValid) {
                $errors['height_ft'] = 'Enter a valid height (e.g. 5 ft 10 in).';
            } else {
                $totalIn       = (int) $heightFt * 12 + (int) $inRaw;
                $heightCmFinal = round($totalIn * self::CM_PER_IN, 2);
            }

            if ($weightLb === '' || !is_numeric($weightLb)) {
                $errors['weight_lb'] = 'Weight is required.';
            } else {
                $lb = (float) $weightLb;
                if ($lb < 66 || $lb > 660) {
                    $errors['weight_lb'] = 'Weight must be between 66 and 660 lbs.';
                } else {
                    $weightKgFinal = round($lb / self::LB_PER_KG, 2);
                }
            }
        } else {
            if ($heightCm === '' || !is_numeric($heightCm)) {
                $errors['height_cm'] = 'Height is required.';
            } else {
                $cm = (float) $heightCm;
                if ($cm < 100 || $cm > 250) {
                    $errors['height_cm'] = 'Height must be between 100 and 250 cm.';
                } else {
                    $heightCmFinal = round($cm, 2);
                }
            }

            if ($weightKg === '' || !is_numeric($weightKg)) {
                $errors['weight_kg'] = 'Weight is required.';
            } else {
                $kg = (float) $weightKg;
                if ($kg < 30 || $kg > 300) {
                    $errors['weight_kg'] = 'Weight must be between 30 and 300 kg.';
                } else {
                    $weightKgFinal = round($kg, 2);
                }
            }
        }

        // ----- Failure: re-render with errors --------------------
        if ($errors) {
            save_old([
                'unit_system'    => $unitSystem,
                'age'            => $age,
                'gender'         => $gender,
                'activity_level' => $activityLevel,
                'logged_date'    => $loggedDate,
                'height_ft'      => $heightFt,
                'height_in'      => $heightIn,
                'weight_lb'      => $weightLb,
                'height_cm'      => $heightCm,
                'weight_kg'      => $weightKg,
            ]);
            set_errors($errors);
            $this->redirect('calorie');
        }

        // ----- Mifflin-St Jeor + activity multiplier --------------
        $bmr = 10 * $weightKgFinal
             + 6.25 * $heightCmFinal
             - 5 * $ageInt
             + ($gender === 'male' ? 5 : -161);

        $maintenance = (int) round($bmr * self::ACTIVITY_MULTIPLIERS[$activityLevel]);
        $cutting     = max(1200, $maintenance - 500);   // safety floor
        $bulking     = $maintenance + 500;

        CalorieLog::create($userId, [
            'age'                  => $ageInt,
            'gender'               => $gender,
            'weight_kg'            => $weightKgFinal,
            'height_cm'            => $heightCmFinal,
            'activity_level'       => $activityLevel,
            'maintenance_calories' => $maintenance,
            'cutting_calories'     => $cutting,
            'bulking_calories'     => $bulking,
            'logged_date'          => $loggedDate,
        ]);

        flash('success', 'Targets updated.');
        $this->redirect('calorie');
    }

    // POST /calorie/intake ----------------------------------------
    // Add a single intake entry (one meal / one logged eating event).
    public function saveIntake(): void {
        $this->requireLogin();
        csrf_verify();

        $userId   = current_user_id();
        $date     = $_POST['logged_date'] ?? '';
        $calories = $_POST['calories']    ?? '';
        $label    = trim((string) ($_POST['label'] ?? ''));

        [$errors, $cleanLabel] = $this->validateIntake($date, $calories, $label);

        if ($errors) {
            save_old([
                'intake_logged_date' => $date,
                'intake_calories'    => $calories,
                'intake_label'       => $label,
            ]);
            set_errors($errors);
            $this->redirect('calorie');
        }

        CalorieIntake::create($userId, (int) $calories, $date, $cleanLabel);

        flash('success', 'Meal logged.');
        $this->redirect('calorie');
    }

    // GET /calorie/intake/edit?id=… --------------------------------
    public function editIntake(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $id     = (int) ($_GET['id'] ?? 0);
        $row    = $id > 0 ? CalorieIntake::find($id, $userId) : null;

        if (!$row) {
            flash('error', 'That intake entry could not be found.');
            $this->redirect('calorie');
            return;
        }

        $this->view('calorie/edit', [
            'title'  => 'Edit meal',
            'active' => 'dashboard',
            'row'    => $row,
        ]);
    }

    // POST /calorie/intake/update ---------------------------------
    public function updateIntake(): void {
        $this->requireLogin();
        csrf_verify();

        $userId   = current_user_id();
        $id       = (int) ($_POST['id']  ?? 0);
        $calories = $_POST['calories']   ?? '';
        $label    = trim((string) ($_POST['label'] ?? ''));

        $row = $id > 0 ? CalorieIntake::find($id, $userId) : null;
        if (!$row) {
            flash('error', 'That intake entry could not be found.');
            $this->redirect('calorie');
            return;
        }

        // Date is fixed for an edit — we re-validate it from the row so
        // a tampered POST can't sneak a different date through.
        [$errors, $cleanLabel] = $this->validateIntake($row['logged_date'], $calories, $label);

        if ($errors) {
            save_old([
                'intake_calories' => $calories,
                'intake_label'    => $label,
            ]);
            set_errors($errors);
            $this->redirect('calorie/intake/edit?id=' . $id);
        }

        CalorieIntake::update($id, $userId, (int) $calories, $cleanLabel);

        flash('success', 'Meal updated.');
        $this->redirect('calorie');
    }

    // POST /calorie/intake/delete ---------------------------------
    public function deleteIntake(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && CalorieIntake::find($id, $userId)) {
            CalorieIntake::delete($id, $userId);
            flash('success', 'Meal removed.');
        }

        $this->redirect('calorie');
    }

    // ----- Internal helpers --------------------------------------

    // Validate the intake fields and return [errors, cleanedLabel].
    // The "intake_" prefix on error keys keeps them from colliding
    // with the targets form (both forms have a logged_date field).
    private function validateIntake(string $date, $calories, string $label): array {
        $errors = [];

        if ($date === '') {
            $errors['intake_logged_date'] = 'Date is required.';
        } else {
            $d = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                $errors['intake_logged_date'] = 'Please enter a valid date.';
            } elseif ($d > new DateTime('today')) {
                $errors['intake_logged_date'] = 'Date cannot be in the future.';
            }
        }

        if ($calories === '' || !ctype_digit((string) $calories)) {
            $errors['intake_calories'] = 'Calories must be a whole number.';
        } else {
            $cal = (int) $calories;
            if ($cal < 0 || $cal > 20000) {
                $errors['intake_calories'] = 'Calories must be between 0 and 20,000.';
            }
        }

        // Label is optional. Trim and cap to 50 chars (matches column).
        // Empty string after trim → store NULL, not "".
        $cleanLabel = $label === '' ? null : mb_substr($label, 0, 50);
        if ($label !== '' && mb_strlen($label) > 50) {
            $errors['intake_label'] = 'Label must be 50 characters or fewer.';
        }

        return [$errors, $cleanLabel];
    }
}
