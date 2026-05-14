<?php
// ============================================================
// app/controllers/CardioController.php
// Cardio tracker — sessions logged with type + duration, plus
// optional intensity and distance. One row per session.
//
// Routes:
//   GET  /cardio         → index()  (form + history + chart)
//   POST /cardio         → save()
//   GET  /cardio/edit    → edit()   (?id=N)
//   POST /cardio/update  → update()
//   POST /cardio/delete  → delete()
// ============================================================

class CardioController extends Controller {

    // Generous bounds — 24h covers any imaginable single session.
    private const DURATION_MIN = 1;
    private const DURATION_MAX = 1440;

    // Distance: optional, but if present, capped at a sane upper bound.
    // 999.99 covers an ultra-marathon (≈100 mi) with room to spare.
    private const DISTANCE_MIN = 0.01;
    private const DISTANCE_MAX = 999.99;

    // Allowed values for the ?range= history filter.
    private const ALLOWED_RANGES = ['7', '30', '90', 'all'];
    private const DEFAULT_RANGE  = '30';

    // GET /cardio -------------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $today  = date('Y-m-d');
        $latest = CardioLog::latestForUser($userId);

        // History range filter: ?range=7|30|90|all. Same shape as the
        // other trackers.
        $range = (string) ($_GET['range'] ?? self::DEFAULT_RANGE);
        if (!in_array($range, self::ALLOWED_RANGES, true)) {
            $range = self::DEFAULT_RANGE;
        }
        $sinceDate = $range === 'all'
            ? null
            : date('Y-m-d', strtotime('-' . $range . ' days'));

        $history = CardioLog::forUser($userId, $sinceDate);

        $totalLoggedDays = CardioLog::countLoggedDaysForUser($userId);

        // Chart wants only the fields it needs. Oldest-first so left-to-
        // right is time-progressing. JS rolls these up into daily totals.
        $chartRows = array_map(
            static fn(array $r): array => [
                'date'         => $r['logged_date'],
                'cardio_type'  => $r['cardio_type'],
                'duration_min' => (int) $r['duration_min'],
            ],
            array_reverse($history)
        );

        // Form defaults: prefer the last entry's distance unit so a
        // returning user doesn't have to keep flipping mi ↔ km.
        $defaultDistanceUnit = $latest['distance_unit'] ?? 'mi';

        $this->view('cardio/index', [
            'title'               => 'Cardio tracker',
            'active'              => 'dashboard',
            'today'               => $today,
            'history'             => $history,
            'latest'              => $latest,
            'chartRows'           => $chartRows,
            'defaultDistanceUnit' => $defaultDistanceUnit,
            'typeLabels'          => CardioLog::TYPE_LABELS,
            'intensityLabels'     => CardioLog::INTENSITY_LABELS,
            'flashInline'         => true,
            'range'               => $range,
            'totalLoggedDays'     => $totalLoggedDays,
        ]);
    }

    // POST /cardio ------------------------------------------------
    public function save(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('cardio');
        }

        CardioLog::create($userId, $data['fields']);

        flash('success', 'Cardio session logged.');
        $this->redirect('cardio');
    }

    // GET /cardio/edit?id=N ---------------------------------------
    public function edit(): void {
        $this->requireLogin();

        $userId = current_user_id();
        $id     = (int) ($_GET['id'] ?? 0);

        $row = $id > 0 ? CardioLog::find($id, $userId) : null;
        if (!$row) {
            flash('error', 'That cardio log was not found.');
            $this->redirect('cardio');
        }

        $this->view('cardio/edit', [
            'title'           => 'Edit cardio log',
            'active'          => 'dashboard',
            'today'           => date('Y-m-d'),
            'row'             => $row,
            'typeLabels'      => CardioLog::TYPE_LABELS,
            'intensityLabels' => CardioLog::INTENSITY_LABELS,
        ]);
    }

    // POST /cardio/update -----------------------------------------
    public function update(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id <= 0 || !CardioLog::find($id, $userId)) {
            flash('error', 'That cardio log was not found.');
            $this->redirect('cardio');
        }

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            save_old($data['old']);
            set_errors($data['errors']);
            $this->redirect('cardio/edit?id=' . $id);
        }

        CardioLog::update($id, $userId, $data['fields']);

        flash('success', 'Cardio log updated.');
        $this->redirect('cardio');
    }

    // POST /cardio/delete -----------------------------------------
    public function delete(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && CardioLog::find($id, $userId)) {
            CardioLog::delete($id, $userId);
            flash('success', 'Cardio log deleted.');
        }

        $this->redirect('cardio');
    }

    // ---------------------------------------------------------------
    // Validator — distance + distance_unit move together. Either
    // both are blank, or both are filled. Half-filled is a UX error
    // rather than a quietly-dropped field.
    // ---------------------------------------------------------------
    private function validateInput(array $input): array {
        $cardioType  = $input['cardio_type']  ?? '';
        $durationRaw = $input['duration_min'] ?? '';
        $intensity   = $input['intensity']    ?? '';
        $distanceRaw = trim((string) ($input['distance']      ?? ''));
        $distUnit    = $input['distance_unit'] ?? '';
        $loggedDate  = $input['logged_date']  ?? '';

        $errors = [];

        // Cardio type
        if (!in_array($cardioType, CardioLog::ALLOWED_TYPES, true)) {
            $errors['cardio_type'] = 'Please choose a cardio type.';
        }

        // Duration — required whole-number minutes.
        $duration = null;
        if ($durationRaw === '' || !ctype_digit((string) $durationRaw)) {
            $errors['duration_min'] = 'Duration is required (whole minutes).';
        } else {
            $d = (int) $durationRaw;
            if ($d < self::DURATION_MIN || $d > self::DURATION_MAX) {
                $errors['duration_min'] = 'Duration must be between '
                    . self::DURATION_MIN . ' and ' . self::DURATION_MAX . ' minutes.';
            } else {
                $duration = $d;
            }
        }

        // Intensity — optional. Treat empty string as "not set".
        $intensityClean = null;
        if ($intensity !== '') {
            if (!in_array($intensity, CardioLog::ALLOWED_INTENSITIES, true)) {
                $errors['intensity'] = 'Please choose easy, moderate, or hard.';
            } else {
                $intensityClean = $intensity;
            }
        }

        // Distance + distance_unit — optional, but paired. Both empty
        // is fine; both filled is fine; one without the other is an error.
        $distance     = null;
        $distUnitClean = null;
        $distHasValue = $distanceRaw !== '';
        $unitHasValue = $distUnit !== '';

        if ($distHasValue || $unitHasValue) {
            if (!$distHasValue) {
                $errors['distance'] = 'Add a distance, or clear the unit.';
            } elseif (!is_numeric($distanceRaw)) {
                $errors['distance'] = 'Distance must be a number.';
            } else {
                $dv = (float) $distanceRaw;
                if ($dv < self::DISTANCE_MIN || $dv > self::DISTANCE_MAX) {
                    $errors['distance'] = 'Distance must be between '
                        . self::DISTANCE_MIN . ' and ' . self::DISTANCE_MAX . '.';
                } else {
                    $distance = round($dv, 2);
                }
            }

            if (!$unitHasValue) {
                $errors['distance_unit'] = 'Pick miles or km, or clear the distance.';
            } elseif (!in_array($distUnit, CardioLog::ALLOWED_DISTANCE_UNITS, true)) {
                $errors['distance_unit'] = 'Pick miles or km.';
            } else {
                $distUnitClean = $distUnit;
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

        $old = [
            'cardio_type'   => $cardioType,
            'duration_min'  => $durationRaw,
            'intensity'     => $intensity,
            'distance'      => $distanceRaw,
            'distance_unit' => $distUnit,
            'logged_date'   => $loggedDate,
        ];

        if ($errors) {
            return ['errors' => $errors, 'old' => $old];
        }

        return [
            'fields' => [
                'cardio_type'   => $cardioType,
                'duration_min'  => $duration,
                'intensity'     => $intensityClean,
                'distance'      => $distance,
                'distance_unit' => $distUnitClean,
                'logged_date'   => $loggedDate,
            ],
            'old' => $old,
        ];
    }
}
