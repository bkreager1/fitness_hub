<?php
// ============================================================
// app/controllers/WeightController.php
// Weight tracker — straightforward CRUD with a chart.
// Each row = one weigh-in (date + weight + unit + optional notes).
// One row per (user, date) — re-saving the same date upserts.
//
// Routes:
//   GET  /weight         → index()   (form + history table + chart)
//   POST /weight         → save()    (create new entry)
//   GET  /weight/edit    → edit()    (dedicated edit page per row, ?id=N)
//   POST /weight/update  → update()  (persist edit)
//   POST /weight/delete  → delete()
// ============================================================

class WeightController extends Controller {

    // Conversion + validation bounds. Mirrors what the calorie
    // tracker uses for its weight inputs so the two stay consistent.
    private const LB_PER_KG = 2.2046226218;
    private const KG_MIN    = 30.0;
    private const KG_MAX    = 300.0;
    private const LB_MIN    = 66.0;
    private const LB_MAX    = 660.0;

    // Allowed values for the ?range= history filter. Strings (not ints)
    // because 'all' is also valid. Default lives in index().
    private const ALLOWED_RANGES = ['7', '30', '90', 'all'];
    private const DEFAULT_RANGE  = '30';

    // GET /weight -------------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId  = current_user_id();
        $today   = date('Y-m-d');
        $latest  = WeightLog::latestForUser($userId);

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

        $history = WeightLog::forUser($userId, $sinceDate);

        // Distinguishes "no logs at all" (hide the range picker, show
        // the original empty state) from "no logs in this range"
        // (show the picker so the user can widen it). One row per day
        // for weight, so the row-count == the distinct-day count.
        $totalLoggedDays = WeightLog::countForUser($userId);

        // Chart wants oldest-first for left-to-right time progression.
        // Pass only the date + canonical kg + the row's unit so the JS
        // can let users toggle the chart's display unit independently.
        $chartData = array_map(static fn(array $r): array => [
            'date'      => $r['logged_date'],
            'weight_kg' => (float) $r['weight_kg'],
        ], array_reverse($history));

        // Form defaults: prefer last entry's unit so returning users
        // don't have to flip the toggle every time.
        $defaultUnit = $latest['unit'] ?? 'lbs';

        $this->view('weight/index', [
            'title'           => 'Weight tracker',
            'active'          => 'dashboard',
            'today'           => $today,
            'history'         => $history,
            'latest'          => $latest,
            'chartData'       => $chartData,
            'defaultUnit'     => $defaultUnit,
            'flashInline'     => true,
            'range'           => $range,
            'totalLoggedDays' => $totalLoggedDays,
        ]);
    }

    // POST /weight ------------------------------------------------
    public function save(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('weight');
        }

        WeightLog::upsert($userId, $data['fields']);

        flash('success', 'Weight logged.');
        $this->redirect('weight');
    }

    // GET /weight/edit?id=N ---------------------------------------
    public function edit(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $id     = (int) ($_GET['id'] ?? 0);

        $row = $id > 0 ? WeightLog::find($id, $userId) : null;
        if (!$row) {
            flash('error', 'That weight log was not found.');
            $this->redirect('weight');
        }

        // Pre-fill the form with the row's stored value, in the
        // unit it was originally entered.
        $weightKg     = (float) $row['weight_kg'];
        $weightInUnit = $row['unit'] === 'kg'
            ? round($weightKg, 1)
            : round($weightKg * self::LB_PER_KG, 1);

        $this->view('weight/edit', [
            'title'        => 'Edit weight log',
            'active'       => 'dashboard',
            'today'        => date('Y-m-d'),
            'row'          => $row,
            'weightInUnit' => $weightInUnit,
        ]);
    }

    // POST /weight/update -----------------------------------------
    public function update(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        // Confirm ownership before touching anything.
        if ($id <= 0 || !WeightLog::find($id, $userId)) {
            flash('error', 'That weight log was not found.');
            $this->redirect('weight');
        }

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('weight/edit?id=' . $id);
        }

        // The UNIQUE(user_id, logged_date) constraint can reject the
        // update if the user changes this row's date to a date that
        // already has a different row. PDO surfaces that as SQLSTATE
        // 23000 — turn it into a friendly inline error.
        try {
            WeightLog::update($id, $userId, $data['fields']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                save_old($data['old']);
                set_errors(['logged_date' =>
                    'You already have a weigh-in on that date. Edit or delete that one instead.']);
                $this->redirect('weight/edit?id=' . $id);
            }
            throw $e;
        }

        flash('success', 'Weight log updated.');
        $this->redirect('weight');
    }

    // POST /weight/delete -----------------------------------------
    public function delete(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && WeightLog::find($id, $userId)) {
            WeightLog::delete($id, $userId);
            flash('success', 'Weight log deleted.');
        }

        $this->redirect('weight');
    }

    // ---------------------------------------------------------------
    // Shared validator for save() and update(). Reads $_POST-shaped
    // input and returns:
    //   on success: ['fields' => [...], 'old' => [...]]
    //   on failure: ['errors' => [...], 'old' => [...]]
    //
    // 'old' is always present so the update() catch block can re-render
    // the edit form when MySQL rejects the UPDATE on a UNIQUE conflict.
    // ---------------------------------------------------------------
    private function validateInput(array $input): array {
        $unit       = $input['unit']        ?? '';
        $weightRaw  = $input['weight']      ?? '';
        $loggedDate = $input['logged_date'] ?? '';
        $notes      = trim((string) ($input['notes'] ?? ''));

        $errors = [];

        // Unit
        if (!in_array($unit, WeightLog::ALLOWED_UNITS, true)) {
            $errors['unit'] = 'Please choose lbs or kg.';
            $unit = 'lbs';
        }

        // Weight — sanity bounds depend on unit.
        $weightKg = null;
        if ($weightRaw === '' || !is_numeric($weightRaw)) {
            $errors['weight'] = 'Weight is required.';
        } else {
            $w = (float) $weightRaw;
            if ($unit === 'lbs') {
                if ($w < self::LB_MIN || $w > self::LB_MAX) {
                    $errors['weight'] = 'Weight must be between '
                        . self::LB_MIN . ' and ' . self::LB_MAX . ' lbs.';
                } else {
                    $weightKg = round($w / self::LB_PER_KG, 2);
                }
            } else {
                if ($w < self::KG_MIN || $w > self::KG_MAX) {
                    $errors['weight'] = 'Weight must be between '
                        . self::KG_MIN . ' and ' . self::KG_MAX . ' kg.';
                } else {
                    $weightKg = round($w, 2);
                }
            }
        }

        // Date — required, valid, not in the future. The "!" zeroes
        // the time so today's date isn't read as a future timestamp.
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
            'unit'        => $unit,
            'weight'      => $weightRaw,
            'logged_date' => $loggedDate,
            'notes'       => $notes,
        ];

        if ($errors) {
            return ['errors' => $errors, 'old' => $old];
        }

        return [
            'fields' => [
                'weight_kg'   => $weightKg,
                'unit'        => $unit,
                'logged_date' => $loggedDate,
                'notes'       => $notes !== '' ? $notes : null,
            ],
            'old' => $old,
        ];
    }

    // ============ EXPORT (CSV download) ============
    // Streams every weigh-in for the logged-in user as CSV. We emit both
    // canonical kg and the user's originally-typed value so the file is
    // useful regardless of which unit they think in. UTF-8 BOM up front
    // makes Excel open it without mangling characters.
    public function exportCsv(): void {
        $this->requireLogin();
        $userId = (int) current_user_id();
        $rows   = WeightLog::forUser($userId);

        $filename = 'fitness-hub-weights-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['date', 'weight_kg', 'weight_display', 'unit', 'notes']);
        foreach ($rows as $r) {
            $kg   = (float) $r['weight_kg'];
            $disp = $r['unit'] === 'lbs' ? $kg * 2.2046226218 : $kg;
            fputcsv($out, [
                $r['logged_date'],
                number_format($kg,   2, '.', ''),
                number_format($disp, 2, '.', ''),
                $r['unit'],
                $r['notes'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
