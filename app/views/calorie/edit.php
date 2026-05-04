<?php
// ============================================================
// app/views/calorie/edit.php
// Edit a single calorie intake entry. Date is fixed (the controller
// re-reads it from the row) — to "move" a meal to a different day,
// delete and re-add.
//
// Variables expected from CalorieController::editIntake():
//   $row   the calorie_intake_logs row being edited
// ============================================================

$errCal   = field_error('intake_calories');
$errLabel = field_error('intake_label');

// On a validation failure we round-trip via old(); on initial load we
// use the row's stored values.
$calVal   = old('intake_calories') !== '' ? old('intake_calories') : (string) (int) $row['calories'];
$labelVal = old('intake_label')    !== '' ? old('intake_label')    : ($row['label'] ?? '');

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Calorie tracker</span>
        <h1>Edit meal</h1>
        <p class="hero-lede">
            Logged on <?= e($fmtDate($row['logged_date'])) ?>.
            Update the label or calories, then save.
        </p>
    </div>
</section>


<!-- ===================== Edit form ===================== -->
<section class="section">
    <div class="container">
        <article class="tracker-card">

            <header class="tracker-card__head">
                <div>
                    <h2>Update this meal</h2>
                    <span class="field-hint">
                        Date is fixed — to move this entry to a different day, delete it and re-add.
                    </span>
                </div>
            </header>

            <form method="post" action="<?= url('calorie/intake/update') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label for="intakeLabel">Label <span class="field-optional">(optional)</span></label>
                        <input type="text" id="intakeLabel" name="label"
                               value="<?= e($labelVal) ?>"
                               maxlength="50"
                               placeholder="Lunch, Pizza, Snack…"
                               <?= $errLabel ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($errLabel): ?>
                            <p class="field-error"><?= e($errLabel) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="intakeCalories">Calories</label>
                        <input type="number" id="intakeCalories" name="calories"
                               inputmode="numeric" min="0" max="20000" step="1"
                               value="<?= e($calVal) ?>"
                               <?= $errCal ? 'aria-invalid="true"' : '' ?>
                               required>
                        <?php if ($errCal): ?>
                            <p class="field-error"><?= e($errCal) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Save changes</button>
                    <a href="<?= url('calorie') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>

        </article>
    </div>
</section>
