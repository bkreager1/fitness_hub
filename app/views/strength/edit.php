<?php
// ============================================================
// app/views/strength/edit.php
// Phase 7 — Edit a single strength log entry.
// Same shape as the index form but submits to /strength/update
// with the row's id as a hidden input.
//
// Variables expected from StrengthController::edit():
//   $today       ISO date for "today"
//   $row         the strength_logs row being edited
//   $liftLabels  ['bench' => 'Bench press', ...]
// ============================================================

$errLift   = field_error('lift_type');
$errUnit   = field_error('unit');
$errWeight = field_error('weight');
$errReps   = field_error('reps');
$errDate   = field_error('logged_date');
$errNotes  = field_error('notes');

// Round-trip via old() on validation failure; otherwise use the row.
$liftType   = old('lift_type')   ?: $row['lift_type'];
$unit       = old('unit')        ?: $row['unit'];
$loggedDate = old('logged_date') ?: $row['logged_date'];
$weightVal  = old('weight') !== '' ? old('weight') : rtrim(rtrim((string) $row['weight'], '0'), '.');
$repsVal    = old('reps')   !== '' ? old('reps')   : (string) $row['reps'];
$notesVal   = old('notes')  !== '' ? old('notes')  : ($row['notes'] ?? '');

$placeholderWeight = $unit === 'lbs' ? '225' : '102';

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Strength tracker</span>
        <h1>Edit lift log</h1>
        <p class="hero-lede">
            Originally logged on <?= e($fmtDate($row['logged_date'])) ?>.
            Update what you need, then save.
        </p>
    </div>
</section>


<!-- ===================== Edit form ===================== -->
<section class="section">
    <div class="container">
        <article class="tracker-card">

            <header class="tracker-card__head">
                <div>
                    <h2>Update this lift</h2>
                    <span class="field-hint">All fields are required except notes.</span>
                </div>
                <div class="unit-toggle" id="strengthUnitToggle"
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

            <form method="post" action="<?= url('strength/update') ?>"
                  id="strengthForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id"   value="<?= e((string) $row['id']) ?>">
                <input type="hidden" name="unit" id="strengthUnit" value="<?= e($unit) ?>">

                <div class="field field--wide">
                    <span class="field-label">Lift</span>
                    <div class="goal-picker__pills <?= $errLift ? 'is-invalid' : '' ?>"
                         role="radiogroup" aria-label="Lift type"
                         <?= $errLift ? 'aria-describedby="lift_type-error"' : '' ?>>
                        <?php foreach ($liftLabels as $key => $label): ?>
                            <label class="goal-pill goal-pill--radio">
                                <input type="radio" name="lift_type" value="<?= e($key) ?>"
                                       <?= $liftType === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($errLift): ?>
                        <p id="lift_type-error" class="field-error"><?= e($errLift) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-grid">

                    <div class="field">
                        <label for="strengthDate">Date</label>
                        <input type="date" id="strengthDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="strengthWeight">
                            Weight <span class="weight-unit-label" id="strengthUnitLabel">(<?= e($unit) ?>)</span>
                        </label>
                        <input type="number" id="strengthWeight" name="weight"
                               inputmode="decimal" step="0.5"
                               min="1"
                               max="<?= $unit === 'lbs' ? '1500' : '700' ?>"
                               value="<?= e($weightVal) ?>"
                               placeholder="<?= e($placeholderWeight) ?>"
                               <?= $errWeight ? 'aria-invalid="true" aria-describedby="weight-error"' : '' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p id="weight-error" class="field-error"><?= e($errWeight) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="strengthReps">Reps</label>
                        <input type="number" id="strengthReps" name="reps"
                               inputmode="numeric" step="1" min="1" max="30"
                               value="<?= e($repsVal) ?>"
                               placeholder="5"
                               <?= $errReps ? 'aria-invalid="true" aria-describedby="reps-error"' : '' ?>
                               required>
                        <?php if ($errReps): ?>
                            <p id="reps-error" class="field-error"><?= e($errReps) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="field field--wide">
                    <label for="strengthNotes">
                        Notes <span class="field-hint-inline">(optional)</span>
                    </label>
                    <textarea id="strengthNotes" name="notes"
                              rows="2" maxlength="300"
                              placeholder="Add any notes about this entry..."
                              <?= $errNotes ? 'aria-invalid="true" aria-describedby="notes-error"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p id="notes-error" class="field-error"><?= e($errNotes) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Save changes</button>
                    <a href="<?= url('strength') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>

        </article>
    </div>
</section>
