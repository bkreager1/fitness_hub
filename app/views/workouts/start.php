<?php
// ============================================================
// app/views/workouts/start.php
// Log a session from a saved workout template.
//
// Variables from WorkoutController::start():
//   $workout        the workouts row being performed
//   $rows           ordered exercise rows, each ['lift_type',
//                   'weight', 'sets', 'reps'] as strings — sets/reps
//                   pre-filled from the template targets, weight blank
//   $today          ISO date for "today"
//   $defaultUnit    'lbs'|'kg' from the user's last strength log
//   $latestPerLift  [key => last strength_logs row|null] for the
//                   per-lift "Last: …" hints
//
// Rows post as parallel arrays ex_lift[] (hidden) / ex_weight[] /
// ex_sets[] / ex_reps[]. A row left fully blank is skipped; every
// filled row becomes one strength_logs row tied to a new
// workout_sessions row, so charts/PRs/history update automatically.
// ============================================================

$errName = field_error('name');
$errDate = field_error('logged_date');

$nameVal = old('name')        ?: (string) $workout['name'];
$dateVal = old('logged_date') ?: $today;
$unit    = old('unit')        ?: $defaultUnit;
if (!in_array($unit, StrengthLog::ALLOWED_UNITS, true)) {
    $unit = 'lbs';
}

$placeholderWeight = $unit === 'lbs' ? '225' : '102';
$weightMax         = $unit === 'lbs' ? '1500' : '700';

// "Last: 225 lbs · 3 × 5" hint from the user's most recent log of a
// lift; null when they've never logged it.
$fmtLast = static function (?array $r): ?string {
    if (!$r) return null;
    $load = $r['weight'] === null
        ? 'BW'
        : rtrim(rtrim(number_format((float) $r['weight'], 2, '.', ''), '0'), '.') . ' ' . $r['unit'];
    $sets = (int) ($r['sets'] ?? 1);
    $sr   = $sets > 1 ? $sets . ' × ' . (int) $r['reps'] : '× ' . (int) $r['reps'];
    return 'Last: ' . $load . ' · ' . $sr;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Log a session</span>
        <h1><?= e($workout['name']) ?></h1>
        <p class="hero-lede">
            Fill in what you actually lifted — sets and reps start at your
            template targets. Leave a row completely blank to skip that
            exercise.
        </p>
    </div>
</section>


<!-- ===================== Session form ===================== -->
<section class="section">
    <div class="container">
        <a class="back-link" href="<?= url('workouts') ?>">
            <span class="back-link__arrow" aria-hidden="true">&larr;</span>
            Back to workouts
        </a>

        <?php if ($errs = flash('errors')): ?>
            <div class="error-box" role="alert">
                <ul>
                    <?php foreach (explode("\n", $errs) as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <article class="tracker-card">

            <header class="tracker-card__head">
                <div>
                    <h2>Log this session</h2>
                    <span class="field-hint">
                        Every filled-in exercise is saved to your strength
                        history as one entry.
                    </span>
                </div>
                <div class="unit-toggle" id="sessionUnitToggle"
                     role="tablist" aria-label="Units">
                    <button type="button" data-unit="lbs"
                            class="<?= $unit === 'lbs' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $unit === 'lbs' ? 'true' : 'false' ?>">
                        lbs
                    </button>
                    <button type="button" data-unit="kg"
                            class="<?= $unit === 'kg' ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $unit === 'kg' ? 'true' : 'false' ?>">
                        kg
                    </button>
                </div>
            </header>

            <form method="post" action="<?= url('workouts/start') ?>"
                  id="sessionForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $workout['id']) ?>">
                <input type="hidden" name="unit" id="sessionUnit" value="<?= e($unit) ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="sessionName">Session name</label>
                        <input type="text" id="sessionName" name="name"
                               value="<?= e($nameVal) ?>"
                               maxlength="80"
                               <?= $errName ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
                               required>
                        <?php if ($errName): ?>
                            <p id="name-error" class="field-error"><?= e($errName) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="sessionDate">Date</label>
                        <input type="date" id="sessionDate" name="logged_date"
                               value="<?= e($dateVal) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <ol class="session-rows">
                    <?php foreach ($rows as $i => $row):
                        $key   = (string) $row['lift_type'];
                        $isBw  = StrengthLog::isBodyweight($key);
                        $label = StrengthLog::label($key);
                        $last  = $fmtLast($latestPerLift[$key] ?? null);
                    ?>
                        <li class="session-row">
                            <span class="exercise-row__pos" aria-hidden="true"><?= $i + 1 ?></span>
                            <div class="session-row__info">
                                <span class="session-row__lift"><?= e($label) ?></span>
                                <?php if ($last): ?>
                                    <span class="session-row__last"><?= e($last) ?></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="ex_lift[]" value="<?= e($key) ?>">
                            <div class="session-row__fields">
                                <div class="field">
                                    <span class="exercise-row__label" aria-hidden="true">
                                        <?= $isBw ? 'Added' : 'Weight' ?>
                                        (<span class="session-unit"><?= e($unit) ?></span>)
                                    </span>
                                    <input type="number" name="ex_weight[]"
                                           inputmode="decimal" step="0.5"
                                           min="1" max="<?= e($weightMax) ?>"
                                           value="<?= e((string) $row['weight']) ?>"
                                           placeholder="<?= $isBw ? '' : e($placeholderWeight) ?>"
                                           <?= $isBw ? 'data-bw="1"' : '' ?>
                                           aria-label="<?= e($label) ?> weight<?= $isBw ? ' (added load, optional)' : '' ?>">
                                </div>
                                <div class="field">
                                    <span class="exercise-row__label" aria-hidden="true">Sets</span>
                                    <input type="number" name="ex_sets[]"
                                           inputmode="numeric" step="1" min="1" max="20"
                                           value="<?= e((string) $row['sets']) ?>"
                                           placeholder="1"
                                           aria-label="<?= e($label) ?> sets">
                                </div>
                                <div class="field">
                                    <span class="exercise-row__label" aria-hidden="true">Reps</span>
                                    <input type="number" name="ex_reps[]"
                                           inputmode="numeric" step="1" min="1" max="30"
                                           value="<?= e((string) $row['reps']) ?>"
                                           placeholder="5"
                                           aria-label="<?= e($label) ?> reps">
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline"
                            data-loading-text="Logging…">
                        Log session
                    </button>
                    <a href="<?= url('workouts') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>

        </article>
    </div>
</section>
