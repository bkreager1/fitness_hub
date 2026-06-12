<?php
// ============================================================
// app/controllers/StrengthController.php
// Strength tracker — bench / squat / deadlift logged with
// weight + reps. Each row is one lift attempt.
//
// Chart Y-axis = estimated 1RM (Epley: weight * (1 + reps/30))
// so 225x5 and 225x1 don't look equally heavy. Computed in JS
// from canonical kg + reps.
//
// Routes:
//   GET  /strength         → index()  (form + history table + chart)
//   POST /strength         → save()
//   GET  /strength/edit    → edit()   (?id=N)
//   POST /strength/update  → update()
//   POST /strength/delete  → delete()
// ============================================================

class StrengthController extends Controller {

    // Validation bounds — the canonical values live in StrengthLog
    // (shared with the workout session logger in WorkoutController).
    // Aliased here so the validator below keeps its self:: references.
    private const KG_MIN   = StrengthLog::KG_MIN;
    private const KG_MAX   = StrengthLog::KG_MAX;
    private const LB_MIN   = StrengthLog::LB_MIN;
    private const LB_MAX   = StrengthLog::LB_MAX;
    private const REPS_MIN = StrengthLog::REPS_MIN;
    private const REPS_MAX = StrengthLog::REPS_MAX;
    private const SETS_MIN = StrengthLog::SETS_MIN;
    private const SETS_MAX = StrengthLog::SETS_MAX;

    // Allowed values for the ?range= history filter. Strings (not ints)
    // because 'all' is also valid. Default lives in index().
    private const ALLOWED_RANGES = ['7', '30', '90', 'all'];
    private const DEFAULT_RANGE  = '30';

    // GET /strength -----------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId  = current_user_id();
        $today   = date('Y-m-d');
        $latest  = StrengthLog::latestForUser($userId);

        // History range filter: ?range=7|30|90|all. Default 30 days
        // keeps the chart readable + history scannable as logs grow.
        // 'all' bypasses the date filter entirely.
        $range = (string) ($_GET['range'] ?? self::DEFAULT_RANGE);
        if (!in_array($range, self::ALLOWED_RANGES, true)) {
            $range = self::DEFAULT_RANGE;
        }
        $sinceDate = $range === 'all'
            ? null
            : date('Y-m-d', strtotime('-' . $range . ' days'));

        $history = StrengthLog::forUser($userId, $sinceDate);

        // Distinguishes "no logs at all" (hide the range picker, show
        // the original empty state) from "no logs in this range"
        // (show the picker so the user can widen it).
        $totalLoggedDays = StrengthLog::countLoggedDaysForUser($userId);

        // Chart wants only the fields it needs, in canonical kg so JS
        // can convert + compute 1RM in any display unit. Oldest-first
        // so left-to-right is time-progressing. Same range filter as
        // the history table so chart and table stay in sync.
        $chartRows = array_map(
            static fn(array $r): array => [
                'date'      => $r['logged_date'],
                'lift_type' => $r['lift_type'],
                'weight_kg' => StrengthLog::toKg((float) $r['weight'], $r['unit']),
                'reps'      => (int) $r['reps'],
            ],
            array_reverse($history)
        );

        // Form defaults: prefer last entry's unit so returning users
        // don't have to flip the toggle every time.
        $defaultUnit = $latest['unit'] ?? 'lbs';

        $this->view('strength/index', [
            'title'           => 'Strength tracker',
            'active'          => 'dashboard',
            'today'           => $today,
            'history'         => $history,
            'latest'          => $latest,
            'chartRows'       => $chartRows,
            'defaultUnit'     => $defaultUnit,
            'liftLabels'      => StrengthLog::labels(),
            'flashInline'     => true,
            'range'           => $range,
            'totalLoggedDays' => $totalLoggedDays,
        ]);
    }

    // POST /strength ----------------------------------------------
    public function save(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('strength');
        }

        StrengthLog::create($userId, $data['fields']);

        flash('success', 'Lift logged.');
        $this->redirect('strength');
    }

    // GET /strength/edit?id=N -------------------------------------
    public function edit(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $id     = (int) ($_GET['id'] ?? 0);

        $row = $id > 0 ? StrengthLog::find($id, $userId) : null;
        if (!$row) {
            flash('error', 'That lift log was not found.');
            $this->redirect('strength');
        }

        $this->view('strength/edit', [
            'title'      => 'Edit lift log',
            'active'     => 'dashboard',
            'today'      => date('Y-m-d'),
            'row'        => $row,
            'liftLabels' => StrengthLog::labels(),
        ]);
    }

    // POST /strength/update ---------------------------------------
    public function update(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id <= 0 || !StrengthLog::find($id, $userId)) {
            flash('error', 'That lift log was not found.');
            $this->redirect('strength');
        }

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('strength/edit?id=' . $id);
        }

        StrengthLog::update($id, $userId, $data['fields']);

        flash('success', 'Lift log updated.');
        $this->redirect('strength');
    }

    // POST /strength/delete ---------------------------------------
    public function delete(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && StrengthLog::find($id, $userId)) {
            StrengthLog::delete($id, $userId);
            flash('success', 'Lift log deleted.');
        }

        $this->redirect('strength');
    }

    // ---------------------------------------------------------------
    // Shared validator — see WeightController for the same pattern.
    // 'old' is always present in the return so the catch path (if we
    // ever add one here) can re-render without an undefined-key error.
    // ---------------------------------------------------------------
    private function validateInput(array $input): array {
        $liftType   = $input['lift_type']   ?? '';
        $unit       = $input['unit']        ?? '';
        $weightRaw  = $input['weight']      ?? '';
        $repsRaw    = $input['reps']        ?? '';
        $setsRaw    = $input['sets']        ?? '';
        $loggedDate = $input['logged_date'] ?? '';
        $notes      = trim((string) ($input['notes'] ?? ''));

        $errors = [];

        // Lift type
        if (!in_array($liftType, StrengthLog::allowedKeys(), true)) {
            $errors['lift_type'] = 'Please choose a lift.';
        }

        // Bodyweight lifts (pull-up, dips, …) carry no external load, so
        // weight is optional for them (blank = bodyweight). Every other
        // lift still requires a weight.
        $isBodyweight = in_array($liftType, StrengthLog::allowedKeys(), true)
            && StrengthLog::isBodyweight($liftType);

        // Unit
        if (!in_array($unit, StrengthLog::ALLOWED_UNITS, true)) {
            $errors['unit'] = 'Please choose lbs or kg.';
            $unit = 'lbs';
        }

        // Weight — required for loaded lifts, optional added load for
        // bodyweight lifts. A NULL weight means "bodyweight, no added
        // load"; the bounds only apply when a number was entered.
        $weight = null;
        if ($weightRaw === '') {
            if (!$isBodyweight) {
                $errors['weight'] = 'Weight is required.';
            }
        } elseif (!is_numeric($weightRaw)) {
            $errors['weight'] = 'Weight must be a number.';
        } else {
            $w = (float) $weightRaw;
            if ($unit === 'lbs') {
                if ($w < self::LB_MIN || $w > self::LB_MAX) {
                    $errors['weight'] = 'Weight must be between '
                        . self::LB_MIN . ' and ' . self::LB_MAX . ' lbs.';
                } else {
                    $weight = round($w, 2);
                }
            } else {
                if ($w < self::KG_MIN || $w > self::KG_MAX) {
                    $errors['weight'] = 'Weight must be between '
                        . self::KG_MIN . ' and ' . self::KG_MAX . ' kg.';
                } else {
                    $weight = round($w, 2);
                }
            }
        }

        // Reps — whole number, sane range.
        $reps = null;
        if ($repsRaw === '' || !ctype_digit((string) $repsRaw)) {
            $errors['reps'] = 'Reps must be a whole number.';
        } else {
            $r = (int) $repsRaw;
            if ($r < self::REPS_MIN || $r > self::REPS_MAX) {
                $errors['reps'] = 'Reps must be between '
                    . self::REPS_MIN . ' and ' . self::REPS_MAX . '.';
            } else {
                $reps = $r;
            }
        }

        // Sets — optional whole number; blank defaults to 1 so the
        // quick top-set flow needs no extra input.
        $sets = 1;
        if ($setsRaw !== '') {
            if (!ctype_digit((string) $setsRaw)) {
                $errors['sets'] = 'Sets must be a whole number.';
            } else {
                $s = (int) $setsRaw;
                if ($s < self::SETS_MIN || $s > self::SETS_MAX) {
                    $errors['sets'] = 'Sets must be between '
                        . self::SETS_MIN . ' and ' . self::SETS_MAX . '.';
                } else {
                    $sets = $s;
                }
            }
        }

        // Date — required, valid, not in the future.
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

        // Notes — optional, schema cap at 300 chars.
        if (mb_strlen($notes) > 300) {
            $errors['notes'] = 'Notes must be 300 characters or fewer.';
        }

        $old = [
            'lift_type'   => $liftType,
            'unit'        => $unit,
            'weight'      => $weightRaw,
            'reps'        => $repsRaw,
            'sets'        => $setsRaw,
            'logged_date' => $loggedDate,
            'notes'       => $notes,
        ];

        if ($errors) {
            return ['errors' => $errors, 'old' => $old];
        }

        return [
            'fields' => [
                'lift_type'   => $liftType,
                'weight'      => $weight,
                'reps'        => $reps,
                'sets'        => $sets,
                'unit'        => $unit,
                'logged_date' => $loggedDate,
                'notes'       => $notes !== '' ? $notes : null,
            ],
            'old' => $old,
        ];
    }

    // ============ EXPORT (CSV download) ============
    public function exportCsv(): void {
        $this->requireLogin();
        $userId = (int) current_user_id();
        $rows   = StrengthLog::forUser($userId);

        $filename = 'fitness-hub-strength-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['date', 'lift', 'weight', 'unit', 'sets', 'reps', 'est_1rm_kg', 'notes']);
        foreach ($rows as $r) {
            $reps = (int) $r['reps'];
            if ($r['weight'] === null) {
                // Bodyweight entry — no load, no meaningful 1RM.
                $weightCol = 'BW';
                $oneRmCol  = '';
            } else {
                $w   = (float) $r['weight'];
                $kg  = StrengthLog::toKg($w, $r['unit']);
                // Epley est. 1RM in canonical kg so it's comparable across rows.
                $oneRm = $reps > 0 ? $kg * (1 + $reps / 30.0) : $kg;
                $weightCol = number_format($w, 2, '.', '');
                $oneRmCol  = number_format($oneRm, 2, '.', '');
            }
            fputcsv($out, [
                $r['logged_date'],
                $r['lift_type'],
                $weightCol,
                $r['unit'],
                (int) $r['sets'],
                $reps,
                $oneRmCol,
                $r['notes'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
