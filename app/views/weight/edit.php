<?php
// ============================================================
// app/views/weight/edit.php
// Phase 7 — Edit a single weight log entry.
// Same shape as the index form but submits to /weight/update with
// the row's id as a hidden input.
//
// Variables expected from WeightController::edit():
//   $today         ISO date for "today"
//   $row           the weight_logs row being edited
//   $weightInUnit  weight already converted to $row['unit'] for display
// ============================================================

$errUnit   = field_error('unit');
$errWeight = field_error('weight');
$errDate   = field_error('logged_date');
$errNotes  = field_error('notes');

// On a validation failure we round-trip via old(); on initial load we
// use the row's stored values.
$unit       = old('unit')        ?: $row['unit'];
$loggedDate = old('logged_date') ?: $row['logged_date'];
$weightVal  = old('weight') !== '' ? old('weight') : (string) $weightInUnit;
$notesVal   = old('notes') !== '' ? old('notes')   : ($row['notes'] ?? '');

$placeholderWeight = $unit === 'lbs' ? '175' : '79';

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Weight tracker</span>
        <h1>Edit weigh-in</h1>
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
                    <h2>Update this weigh-in</h2>
                    <span class="field-hint">All fields are required except notes.</span>
                </div>
                <div class="unit-toggle" id="weightUnitToggle"
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

            <form method="post" action="<?= url('weight/update') ?>"
                  id="weightForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                <input type="hidden" name="unit" id="weightUnit" value="<?= e($unit) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label for="weightDate">Date</label>
                        <input type="date" id="weightDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="weightInput">
                            Weight <span class="weight-unit-label" id="weightUnitLabel">(<?= e($unit) ?>)</span>
                        </label>
                        <input type="number" id="weightInput" name="weight"
                               inputmode="decimal" step="0.1"
                               min="<?= $unit === 'lbs' ? '66' : '30' ?>"
                               max="<?= $unit === 'lbs' ? '660' : '300' ?>"
                               value="<?= e($weightVal) ?>"
                               placeholder="<?= e($placeholderWeight) ?>"
                               <?= $errWeight ? 'aria-invalid="true" aria-describedby="weight-error"' : '' ?>
                               required>
                        <?php if ($errWeight): ?>
                            <p id="weight-error" class="field-error"><?= e($errWeight) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="field field--wide">
                    <label for="weightNotes">
                        Notes <span class="field-hint-inline">(optional)</span>
                    </label>
                    <textarea id="weightNotes" name="notes"
                              rows="2" maxlength="300"
                              placeholder="Add any notes about this entry..."
                              <?= $errNotes ? 'aria-invalid="true" aria-describedby="notes-error"' : '' ?>><?= e($notesVal) ?></textarea>
                    <?php if ($errNotes): ?>
                        <p id="notes-error" class="field-error"><?= e($errNotes) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Save changes</button>
                    <a href="<?= url('weight') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>

        </article>
    </div>
</section>
