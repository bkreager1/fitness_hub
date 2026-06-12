<?php
// ============================================================
// app/controllers/WorkoutController.php
// Workout builder — saved templates (a named, ordered list of
// exercises with optional target sets × reps) plus the session
// logger: "Start workout" opens a pre-filled form and saves each
// performed exercise as a strength_logs row grouped under one
// workout_sessions row, so charts/PRs/history pick them up
// automatically.
//
// Routes:
//   GET  /workouts                → index()   (templates + recent sessions)
//   GET  /workouts/new            → create()  (empty builder form)
//   POST /workouts                → store()
//   GET  /workouts/edit           → edit()    (?id=N, pre-filled builder)
//   POST /workouts/update         → update()
//   POST /workouts/delete         → delete()
//   GET  /workouts/start          → start()   (?id=N, pre-filled session form)
//   POST /workouts/start          → logSession()
//   POST /workouts/session/delete → deleteSession()
//
// Exercise rows arrive as parallel arrays (ex_lift[]/ex_sets[]/
// ex_reps[], plus ex_weight[] on the session form) so the browser's
// add/remove/reorder maps straight to submission order (DOM order
// == array order). See parseExercises() / parseSessionRows().
// ============================================================

class WorkoutController extends Controller {

    private const NAME_MAX      = 80;   // workouts.name + workout_sessions.name VARCHAR(80)
    private const NOTES_MAX     = 300;  // matches workouts.notes VARCHAR(300)
    private const MAX_EXERCISES = 30;   // generous ceiling for one template

    // Target bounds mirror what a strength_logs row can actually hold
    // (StrengthLog is the single source of truth) — a template target
    // you could never log would just be a trap at session time.
    private const SETS_MIN = StrengthLog::SETS_MIN;
    private const SETS_MAX = StrengthLog::SETS_MAX;
    private const REPS_MIN = StrengthLog::REPS_MIN;
    private const REPS_MAX = StrengthLog::REPS_MAX;

    // Session slots for re-rendering submitted exercise rows after a
    // failed save. old() is string-typed, so the parallel arrays can't
    // ride along in it — stash them separately and reclaim in the form.
    private const OLD_EX_KEY      = '_old_workout_exercises';
    private const OLD_SESSION_KEY = '_old_session_rows';

    // GET /workouts -----------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId   = current_user_id();
        $sessions = WorkoutSession::forUser($userId);

        $this->view('workouts/index', [
            'title'        => 'Workouts',
            'active'       => 'workouts',
            'workouts'     => Workout::forUser($userId),
            'exercisesBy'  => Workout::exercisesForUser($userId),
            'sessions'     => $sessions,
            'sessionLifts' => WorkoutSession::liftsForSessions(array_column($sessions, 'id')),
            'flashInline'  => true,
        ]);
    }

    // GET /workouts/new -------------------------------------------
    public function create(): void {
        $this->requireLogin();

        $this->view('workouts/form', [
            'title'     => 'Build a workout',
            'active'    => 'workouts',
            'mode'      => 'create',
            'action'    => url('workouts'),
            'workout'   => null,
            'exercises' => $this->takeOldExercises(),  // [] on a fresh visit
        ]);
    }

    // POST /workouts ----------------------------------------------
    public function store(): void {
        $this->requireLogin();
        csrf_verify();

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            $this->stashFailure($data);
            $this->redirect('workouts/new');
        }

        Workout::create(
            current_user_id(),
            $data['name'],
            $data['notes'],
            $data['exercises']
        );

        flash('success', 'Workout saved.');
        $this->redirect('workouts');
    }

    // GET /workouts/edit?id=N -------------------------------------
    public function edit(): void {
        $this->requireLogin();

        $userId  = current_user_id();
        $id      = (int) ($_GET['id'] ?? 0);
        $workout = $id > 0 ? Workout::find($id, $userId) : null;

        if (!$workout) {
            flash('error', 'That workout was not found.');
            $this->redirect('workouts');
        }

        // Prefer the just-submitted rows (failed-update re-render); fall
        // back to the saved exercises, normalized to the string shape the
        // form renders.
        $exercises = $this->takeOldExercises();
        if (!$exercises) {
            $exercises = array_map(
                static fn(array $e): array => [
                    'lift_type'   => $e['lift_type'],
                    'target_sets' => $e['target_sets'] !== null ? (string) $e['target_sets'] : '',
                    'target_reps' => $e['target_reps'] !== null ? (string) $e['target_reps'] : '',
                ],
                Workout::exercisesFor((int) $workout['id'])
            );
        }

        $this->view('workouts/form', [
            'title'     => 'Edit workout',
            'active'    => 'workouts',
            'mode'      => 'edit',
            'action'    => url('workouts/update'),
            'workout'   => $workout,
            'exercises' => $exercises,
        ]);
    }

    // POST /workouts/update ---------------------------------------
    public function update(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id <= 0 || !Workout::find($id, $userId)) {
            flash('error', 'That workout was not found.');
            $this->redirect('workouts');
        }

        $data = $this->validateInput($_POST);
        if (isset($data['errors'])) {
            $this->stashFailure($data);
            $this->redirect('workouts/edit?id=' . $id);
        }

        Workout::update($id, $userId, $data['name'], $data['notes'], $data['exercises']);

        flash('success', 'Workout updated.');
        $this->redirect('workouts');
    }

    // POST /workouts/delete ---------------------------------------
    public function delete(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && Workout::find($id, $userId)) {
            Workout::delete($id, $userId);
            flash('success', 'Workout deleted.');
        }

        $this->redirect('workouts');
    }

    // GET /workouts/start?id=N --------------------------------------
    // Pre-filled session form: one row per template exercise with
    // sets/reps targets filled in. Weight is left blank (that's what
    // the user is here to enter) with a "Last: …" hint per lift.
    public function start(): void {
        $this->requireLogin();

        $userId  = current_user_id();
        $id      = (int) ($_GET['id'] ?? 0);
        $workout = $id > 0 ? Workout::find($id, $userId) : null;

        if (!$workout) {
            flash('error', 'That workout was not found.');
            $this->redirect('workouts');
        }

        // Prefer the just-submitted rows after a failed save — but only
        // if they belong to THIS workout (the stash is one global slot).
        $stash = $this->takeOldSessionRows();
        $rows  = ($stash && (int) $stash['workout_id'] === $id) ? $stash['rows'] : null;

        if ($rows === null) {
            $rows = array_map(
                static fn(array $e): array => [
                    'lift_type' => $e['lift_type'],
                    'weight'    => '',
                    'sets'      => $e['target_sets'] !== null ? (string) $e['target_sets'] : '',
                    'reps'      => $e['target_reps'] !== null ? (string) $e['target_reps'] : '',
                ],
                Workout::exercisesFor((int) $workout['id'])
            );
        }

        if (!$rows) {
            flash('error', 'That workout has no exercises to log — add some first.');
            $this->redirect('workouts/edit?id=' . $id);
        }

        $latest = StrengthLog::latestForUser($userId);

        $this->view('workouts/start', [
            'title'         => 'Log session',
            'active'        => 'workouts',
            'workout'       => $workout,
            'rows'          => $rows,
            'today'         => date('Y-m-d'),
            'defaultUnit'   => $latest['unit'] ?? 'lbs',
            'latestPerLift' => StrengthLog::latestPerLiftForUser($userId),
        ]);
    }

    // POST /workouts/start ------------------------------------------
    public function logSession(): void {
        $this->requireLogin();
        csrf_verify();

        $userId  = current_user_id();
        $id      = (int) ($_POST['id'] ?? 0);
        $workout = $id > 0 ? Workout::find($id, $userId) : null;

        if (!$workout) {
            flash('error', 'That workout was not found.');
            $this->redirect('workouts');
        }

        $data = $this->validateSession($_POST);
        if (isset($data['errors'])) {
            if (!empty($data['fieldErrors'])) {
                set_errors($data['fieldErrors']);
            }
            if (!empty($data['errors'])) {
                flash('errors', implode("\n", $data['errors']));
            }
            save_old([
                'name'        => $data['name'],
                'logged_date' => $data['logged_date'],
                'unit'        => $data['unit'],
            ]);
            $_SESSION[self::OLD_SESSION_KEY] = [
                'workout_id' => $id,
                'rows'       => $data['raw'],
            ];
            $this->redirect('workouts/start?id=' . $id);
        }

        WorkoutSession::createWithLifts(
            $userId,
            $id,
            $data['name'],
            $data['logged_date'],
            $data['unit'],
            $data['lifts']
        );

        $n = count($data['lifts']);
        flash('success', 'Session logged — ' . $n . ' lift' . ($n === 1 ? '' : 's')
            . ' added to your strength history.');
        $this->redirect('workouts');
    }

    // POST /workouts/session/delete ---------------------------------
    // Removes the session AND its logged lifts (see WorkoutSession::delete).
    public function deleteSession(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = current_user_id();
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && WorkoutSession::find($id, $userId)) {
            WorkoutSession::delete($id, $userId);
            flash('success', 'Session deleted.');
        }

        $this->redirect('workouts');
    }

    // ---------------------------------------------------------------
    // Validation. Returns one of:
    //   failure → ['errors' => [...msgs], 'nameError' => ?str,
    //              'name' => raw, 'notes' => raw, 'raw' => rawRows]
    //   success → ['name' => str, 'notes' => ?str, 'exercises' => rows]
    // 'errors' (the list) feeds the top error-box; 'nameError' feeds the
    // inline error under the name field.
    // ---------------------------------------------------------------
    private function validateInput(array $input): array {
        $name  = trim((string) ($input['name']  ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        $errors    = [];
        $nameError = null;

        if ($name === '') {
            $nameError = 'Give your workout a name.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $nameError = 'Name must be ' . self::NAME_MAX . ' characters or fewer.';
        }

        if (mb_strlen($notes) > self::NOTES_MAX) {
            $errors[] = 'Notes must be ' . self::NOTES_MAX . ' characters or fewer.';
        }

        [$rawRows, $cleanRows, $exErrors] = $this->parseExercises($input);
        $errors = array_merge($errors, $exErrors);

        // Only nudge "add an exercise" when there's nothing usable AND no
        // per-row error already explaining what's wrong.
        if (!$cleanRows && !$exErrors) {
            $errors[] = 'Add at least one exercise to your workout.';
        }

        if ($nameError || $errors) {
            return [
                'errors'    => $errors,
                'nameError' => $nameError,
                'name'      => $name,
                'notes'     => $notes,
                'raw'       => $rawRows,
            ];
        }

        return [
            'name'      => $name,
            'notes'     => $notes !== '' ? $notes : null,
            'exercises' => $cleanRows,
        ];
    }

    // Turn ex_lift[]/ex_sets[]/ex_reps[] into [$rawRows, $cleanRows, $errors].
    //   $rawRows   — non-blank rows as submitted (strings), for re-render
    //   $cleanRows — validated rows for the model (null targets allowed)
    //   $errors    — de-duplicated, human-facing messages (list)
    private function parseExercises(array $input): array {
        $lifts = is_array($input['ex_lift'] ?? null) ? $input['ex_lift'] : [];
        $sets  = is_array($input['ex_sets'] ?? null) ? $input['ex_sets'] : [];
        $reps  = is_array($input['ex_reps'] ?? null) ? $input['ex_reps'] : [];

        $rawRows   = [];
        $cleanRows = [];
        $errors    = [];   // string-keyed so repeated problems collapse to one
        $allowed   = StrengthLog::allowedKeys();

        $count = count($lifts);
        for ($i = 0; $i < $count; $i++) {
            $lift    = trim((string) ($lifts[$i] ?? ''));
            $setsRaw = trim((string) ($sets[$i]  ?? ''));
            $repsRaw = trim((string) ($reps[$i]  ?? ''));

            // Skip a fully-blank row — an added-but-never-filled line.
            if ($lift === '' && $setsRaw === '' && $repsRaw === '') {
                continue;
            }

            $rawRows[] = [
                'lift_type'   => $lift,
                'target_sets' => $setsRaw,
                'target_reps' => $repsRaw,
            ];

            $rowError = false;

            if (!in_array($lift, $allowed, true)) {
                $errors['lift'] = 'Pick a lift for every exercise row.';
                $rowError = true;
            }

            $targetSets = null;
            if ($setsRaw !== '') {
                if (ctype_digit($setsRaw)
                    && (int) $setsRaw >= self::SETS_MIN
                    && (int) $setsRaw <= self::SETS_MAX) {
                    $targetSets = (int) $setsRaw;
                } else {
                    $errors['sets'] = 'Target sets must be a whole number from '
                        . self::SETS_MIN . ' to ' . self::SETS_MAX . '.';
                    $rowError = true;
                }
            }

            $targetReps = null;
            if ($repsRaw !== '') {
                if (ctype_digit($repsRaw)
                    && (int) $repsRaw >= self::REPS_MIN
                    && (int) $repsRaw <= self::REPS_MAX) {
                    $targetReps = (int) $repsRaw;
                } else {
                    $errors['reps'] = 'Target reps must be a whole number from '
                        . self::REPS_MIN . ' to ' . self::REPS_MAX . '.';
                    $rowError = true;
                }
            }

            if ($rowError) {
                continue;
            }

            if (count($cleanRows) >= self::MAX_EXERCISES) {
                $errors['max'] = 'A workout can have up to ' . self::MAX_EXERCISES . ' exercises.';
                continue;
            }

            $cleanRows[] = [
                'lift_type'   => $lift,
                'target_sets' => $targetSets,
                'target_reps' => $targetReps,
            ];
        }

        return [$rawRows, $cleanRows, array_values($errors)];
    }

    // ---------------------------------------------------------------
    // Session validation. Returns one of:
    //   failure → ['errors' => [...msgs], 'fieldErrors' => assoc,
    //              'name' => raw, 'logged_date' => raw, 'unit' => str,
    //              'raw' => rawRows]
    //   success → ['name', 'logged_date', 'unit', 'lifts' => rows]
    // fieldErrors render inline under name/date; the list feeds the
    // top error-box.
    // ---------------------------------------------------------------
    private function validateSession(array $input): array {
        $name = trim((string) ($input['name']        ?? ''));
        $date = (string) ($input['logged_date'] ?? '');
        $unit = (string) ($input['unit']        ?? '');

        $fieldErrors = [];
        $listErrors  = [];

        if ($name === '') {
            $fieldErrors['name'] = 'Give this session a name.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $fieldErrors['name'] = 'Name must be ' . self::NAME_MAX . ' characters or fewer.';
        }

        // Date — same rules as direct strength logging.
        if ($date === '') {
            $fieldErrors['logged_date'] = 'Date is required.';
        } else {
            $d = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                $fieldErrors['logged_date'] = 'Please enter a valid date.';
            } elseif ($d > new DateTime('today')) {
                $fieldErrors['logged_date'] = 'Date cannot be in the future.';
            }
        }

        if (!in_array($unit, StrengthLog::ALLOWED_UNITS, true)) {
            $listErrors[] = 'Please choose lbs or kg.';
            $unit = 'lbs';
        }

        [$rawRows, $cleanRows, $rowErrors] = $this->parseSessionRows($input, $unit);
        $listErrors = array_merge($listErrors, $rowErrors);

        if (!$cleanRows && !$rowErrors) {
            $listErrors[] = 'Fill in at least one exercise — leave rows blank to skip them.';
        }

        if ($fieldErrors || $listErrors) {
            return [
                'errors'      => $listErrors,
                'fieldErrors' => $fieldErrors,
                'name'        => $name,
                'logged_date' => $date,
                'unit'        => $unit,
                'raw'         => $rawRows,
            ];
        }

        return [
            'name'        => $name,
            'logged_date' => $date,
            'unit'        => $unit,
            'lifts'       => $cleanRows,
        ];
    }

    // Turn ex_lift[]/ex_weight[]/ex_sets[]/ex_reps[] into
    // [$rawRows, $cleanRows, $errors]. Unlike the builder, EVERY
    // submitted row is kept in $rawRows (blank ones included) so a
    // failed save re-renders the full template structure. A row with
    // weight + sets + reps all blank is a skipped exercise. Errors
    // are keyed per lift + field so duplicate problems collapse but
    // each message still names the exercise it belongs to.
    private function parseSessionRows(array $input, string $unit): array {
        $lifts   = is_array($input['ex_lift']   ?? null) ? $input['ex_lift']   : [];
        $weights = is_array($input['ex_weight'] ?? null) ? $input['ex_weight'] : [];
        $sets    = is_array($input['ex_sets']   ?? null) ? $input['ex_sets']   : [];
        $reps    = is_array($input['ex_reps']   ?? null) ? $input['ex_reps']   : [];

        $rawRows   = [];
        $cleanRows = [];
        $errors    = [];
        $allowed   = StrengthLog::allowedKeys();

        [$wMin, $wMax] = $unit === 'kg'
            ? [StrengthLog::KG_MIN, StrengthLog::KG_MAX]
            : [StrengthLog::LB_MIN, StrengthLog::LB_MAX];

        $count = count($lifts);
        for ($i = 0; $i < $count; $i++) {
            $lift = trim((string) ($lifts[$i]   ?? ''));
            $wRaw = trim((string) ($weights[$i] ?? ''));
            $sRaw = trim((string) ($sets[$i]    ?? ''));
            $rRaw = trim((string) ($reps[$i]    ?? ''));

            $rawRows[] = [
                'lift_type' => $lift,
                'weight'    => $wRaw,
                'sets'      => $sRaw,
                'reps'      => $rRaw,
            ];

            // Fully blank inputs = skipped exercise.
            if ($wRaw === '' && $sRaw === '' && $rRaw === '') {
                continue;
            }

            if (!in_array($lift, $allowed, true)) {
                $errors['lift'] = 'One of the exercises in this session isn\'t a valid lift. '
                    . 'Reload the page and try again.';
                continue;
            }

            $isBw  = StrengthLog::isBodyweight($lift);
            $label = StrengthLog::label($lift);
            $rowBad = false;

            // Weight — required for loaded lifts; optional added load
            // for bodyweight ones (blank = just bodyweight).
            $weight = null;
            if ($wRaw === '') {
                if (!$isBw) {
                    $errors['w_' . $lift] = $label . ': add a weight, or clear the whole row to skip it.';
                    $rowBad = true;
                }
            } elseif (!is_numeric($wRaw)) {
                $errors['w_' . $lift] = $label . ': weight must be a number.';
                $rowBad = true;
            } else {
                $w = (float) $wRaw;
                if ($w < $wMin || $w > $wMax) {
                    $errors['w_' . $lift] = $label . ': weight must be between '
                        . $wMin . ' and ' . $wMax . ' ' . $unit . '.';
                    $rowBad = true;
                } else {
                    $weight = round($w, 2);
                }
            }

            // Reps — required for any filled-in row.
            $repsVal = null;
            if ($rRaw === '' || !ctype_digit($rRaw)) {
                $errors['r_' . $lift] = $label . ': reps must be a whole number'
                    . ($rRaw === '' ? ' (or clear the whole row to skip it)' : '') . '.';
                $rowBad = true;
            } else {
                $r = (int) $rRaw;
                if ($r < StrengthLog::REPS_MIN || $r > StrengthLog::REPS_MAX) {
                    $errors['r_' . $lift] = $label . ': reps must be between '
                        . StrengthLog::REPS_MIN . ' and ' . StrengthLog::REPS_MAX . '.';
                    $rowBad = true;
                } else {
                    $repsVal = $r;
                }
            }

            // Sets — optional; blank means 1 (top-set flow).
            $setsVal = 1;
            if ($sRaw !== '') {
                if (!ctype_digit($sRaw)) {
                    $errors['s_' . $lift] = $label . ': sets must be a whole number.';
                    $rowBad = true;
                } elseif ((int) $sRaw < StrengthLog::SETS_MIN || (int) $sRaw > StrengthLog::SETS_MAX) {
                    $errors['s_' . $lift] = $label . ': sets must be between '
                        . StrengthLog::SETS_MIN . ' and ' . StrengthLog::SETS_MAX . '.';
                    $rowBad = true;
                } else {
                    $setsVal = (int) $sRaw;
                }
            }

            if ($rowBad) {
                continue;
            }

            if (count($cleanRows) >= self::MAX_EXERCISES) {
                $errors['max'] = 'A session can log up to ' . self::MAX_EXERCISES . ' exercises.';
                continue;
            }

            $cleanRows[] = [
                'lift_type' => $lift,
                'weight'    => $weight,
                'reps'      => $repsVal,
                'sets'      => $setsVal,
            ];
        }

        return [$rawRows, $cleanRows, array_values($errors)];
    }

    // Persist a failed submission so the builder re-renders intact across
    // the PRG redirect: name error inline, list errors in the box, scalar
    // fields via old(), and the raw exercise rows in their own slot.
    private function stashFailure(array $data): void {
        if (!empty($data['nameError'])) {
            set_errors(['name' => $data['nameError']]);
        }
        if (!empty($data['errors'])) {
            flash('errors', implode("\n", $data['errors']));
        }
        save_old(['name' => $data['name'], 'notes' => $data['notes']]);
        $_SESSION[self::OLD_EX_KEY] = $data['raw'] ?? [];
    }

    // Reclaim (and clear) the stashed exercise rows from a failed save.
    // Empty on a normal first visit.
    private function takeOldExercises(): array {
        $rows = $_SESSION[self::OLD_EX_KEY] ?? [];
        unset($_SESSION[self::OLD_EX_KEY]);
        return is_array($rows) ? $rows : [];
    }

    // Reclaim (and clear) the stashed session rows from a failed log.
    // Returns ['workout_id' => int, 'rows' => array] or null. The
    // caller checks workout_id so a stale stash from workout A never
    // leaks into workout B's form.
    private function takeOldSessionRows(): ?array {
        $stash = $_SESSION[self::OLD_SESSION_KEY] ?? null;
        unset($_SESSION[self::OLD_SESSION_KEY]);
        if (!is_array($stash)
            || !isset($stash['workout_id'], $stash['rows'])
            || !is_array($stash['rows'])) {
            return null;
        }
        return $stash;
    }
}
