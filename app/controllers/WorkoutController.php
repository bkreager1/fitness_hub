<?php
// ============================================================
// app/controllers/WorkoutController.php
// Workout builder — saved templates: a named, ordered list of
// exercises with optional target sets × reps. This controller is
// template CRUD only; the "Start workout" session logger (which
// turns a template into logged strength_logs rows) lands in the
// next drop.
//
// Routes:
//   GET  /workouts         → index()   (list of templates)
//   GET  /workouts/new     → create()  (empty builder form)
//   POST /workouts         → store()
//   GET  /workouts/edit    → edit()    (?id=N, pre-filled builder)
//   POST /workouts/update  → update()
//   POST /workouts/delete  → delete()
//
// Exercise rows arrive as three parallel arrays — ex_lift[],
// ex_sets[], ex_reps[] — so the browser's add/remove/reorder maps
// straight to submission order (DOM order == array order). See
// parseExercises().
// ============================================================

class WorkoutController extends Controller {

    private const NAME_MAX      = 80;   // matches workouts.name VARCHAR(80)
    private const NOTES_MAX     = 300;  // matches workouts.notes VARCHAR(300)
    private const MAX_EXERCISES = 30;   // generous ceiling for one template
    private const SETS_MIN = 1;
    private const SETS_MAX = 20;
    private const REPS_MIN = 1;
    private const REPS_MAX = 50;        // template target only; accessories run high

    // Session slot for re-rendering the submitted exercise rows after a
    // failed save. old() is string-typed, so the parallel arrays can't
    // ride along in it — stash them separately and reclaim in the form.
    private const OLD_EX_KEY = '_old_workout_exercises';

    // GET /workouts -----------------------------------------------
    public function index(): void {
        $this->requireLogin();

        $userId = current_user_id();

        $this->view('workouts/index', [
            'title'       => 'Workouts',
            'active'      => 'workouts',
            'workouts'    => Workout::forUser($userId),
            'exercisesBy' => Workout::exercisesForUser($userId),
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
}
