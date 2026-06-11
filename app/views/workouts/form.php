<?php
// ============================================================
// app/views/workouts/form.php
// Workout builder — create + edit share this one form.
//
// Variables from WorkoutController::create() / edit():
//   $mode       'create' | 'edit'
//   $action     POST target (url('workouts') | url('workouts/update'))
//   $workout    the workouts row when editing, else null
//   $exercises  ordered rows to pre-render, each
//               ['lift_type', 'target_sets', 'target_reps'] (strings)
//
// Exercise rows post as parallel arrays ex_lift[]/ex_sets[]/ex_reps[];
// initWorkoutBuilder() in main.js drives add/remove/reorder. The page
// always renders at least one row (the no-JS / empty fallback), and a
// hidden <template> below holds a blank row for the JS to clone.
// ============================================================

$isEdit  = ($mode === 'edit');
$nameErr = field_error('name');

$nameVal  = old('name')  ?: (string) ($workout['name']  ?? '');
$notesVal = old('notes') ?: (string) ($workout['notes'] ?? '');

// Rows to render. Start with a single blank row when there are none so
// the builder is never empty on a fresh visit.
$rows = $exercises;
if (empty($rows)) {
    $rows = [['lift_type' => '', 'target_sets' => '', 'target_reps' => '']];
}

// Render one grouped lift <select> (optgroup per body part) with
// $selected chosen. Reused for every server-rendered row + the JS clone.
$renderLiftSelect = static function (string $selected): string {
    $html  = '<select name="ex_lift[]" class="exercise-row__lift" aria-label="Lift" required>';
    $html .= '<option value="">Choose a lift…</option>';
    foreach (StrengthLog::byCategory() as $category => $lifts) {
        $html .= '<optgroup label="' . e($category) . '">';
        foreach ($lifts as $key => $label) {
            $sel = ($key === $selected) ? ' selected' : '';
            $html .= '<option value="' . e($key) . '"' . $sel . '>' . e($label) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';
    return $html;
};

// Render a full exercise row. Field labels are decorative (aria-hidden)
// because each control already carries its own aria-label.
$renderRow = static function (array $row) use ($renderLiftSelect): string {
    $lift = (string) ($row['lift_type']   ?? '');
    $sets = (string) ($row['target_sets'] ?? '');
    $reps = (string) ($row['target_reps'] ?? '');
    ob_start(); ?>
    <li class="exercise-row" data-exercise-row>
        <span class="exercise-row__pos" aria-hidden="true"></span>
        <div class="exercise-row__fields">
            <div class="field exercise-row__field exercise-row__field--lift">
                <span class="exercise-row__label" aria-hidden="true">Lift</span>
                <?= $renderLiftSelect($lift) ?>
            </div>
            <div class="field exercise-row__field exercise-row__field--num">
                <span class="exercise-row__label" aria-hidden="true">Sets</span>
                <input type="number" name="ex_sets[]" class="exercise-row__num"
                       inputmode="numeric" min="1" max="20" step="1"
                       placeholder="3" aria-label="Target sets"
                       value="<?= e($sets) ?>">
            </div>
            <div class="field exercise-row__field exercise-row__field--num">
                <span class="exercise-row__label" aria-hidden="true">Reps</span>
                <input type="number" name="ex_reps[]" class="exercise-row__num"
                       inputmode="numeric" min="1" max="50" step="1"
                       placeholder="8" aria-label="Target reps"
                       value="<?= e($reps) ?>">
            </div>
        </div>
        <div class="exercise-row__controls">
            <button type="button" class="icon-btn" data-move-up aria-label="Move exercise up">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <polyline points="6 15 12 9 18 15"/>
                </svg>
            </button>
            <button type="button" class="icon-btn" data-move-down aria-label="Move exercise down">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <button type="button" class="icon-btn icon-btn--danger" data-remove-row
                    aria-label="Remove exercise">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <line x1="6" y1="6" x2="18" y2="18"/>
                    <line x1="18" y1="6" x2="6" y2="18"/>
                </svg>
            </button>
        </div>
    </li>
    <?php
    return ob_get_clean();
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Workout builder</span>
        <h1><?= $isEdit ? 'Edit workout' : 'Build a workout' ?></h1>
        <p class="hero-lede">
            Name it, then add the lifts you do in this session in order.
            Target sets and reps are optional — a reminder for when you train.
        </p>
    </div>
</section>


<!-- ===================== Builder form ===================== -->
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
            <form method="post" action="<?= e($action) ?>" id="workoutForm" novalidate>
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= e((string) $workout['id']) ?>">
                <?php endif; ?>

                <div class="field field--wide">
                    <label for="workoutName">Workout name</label>
                    <input type="text" id="workoutName" name="name"
                           value="<?= e($nameVal) ?>"
                           maxlength="80"
                           placeholder="e.g. Push day, Leg day, Full body A"
                           <?= $nameErr ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
                           required>
                    <?php if ($nameErr): ?>
                        <p id="name-error" class="field-error"><?= e($nameErr) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field field--wide">
                    <label for="workoutNotes">
                        Notes <span class="field-hint-inline">(optional)</span>
                    </label>
                    <textarea id="workoutNotes" name="notes" rows="2" maxlength="300"
                              placeholder="Any reminders for this workout…"><?= e($notesVal) ?></textarea>
                </div>

                <!-- ===== Exercises ===== -->
                <div class="exercise-builder">
                    <div class="exercise-builder__head">
                        <h2>Exercises</h2>
                        <span class="field-hint">
                            Add the lifts in the order you'll do them. Sets and
                            reps are optional targets; use the arrows to reorder.
                        </span>
                    </div>

                    <ol class="exercise-list" id="exerciseRows">
                        <?php foreach ($rows as $row): ?>
                            <?= $renderRow($row) ?>
                        <?php endforeach; ?>
                    </ol>

                    <button type="button" class="btn btn-secondary btn-inline"
                            id="addExerciseBtn">
                        + Add exercise
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline"
                            data-loading-text="Saving…">
                        <?= $isEdit ? 'Save changes' : 'Save workout' ?>
                    </button>
                    <a href="<?= url('workouts') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>
        </article>
    </div>
</section>

<!-- JS clone source for new rows. Sits outside the <form> so it never
     submits; initWorkoutBuilder() clones its content on "Add exercise". -->
<template id="exerciseRowTemplate"><?= $renderRow(['lift_type' => '', 'target_sets' => '', 'target_reps' => '']) ?></template>
