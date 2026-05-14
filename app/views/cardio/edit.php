<?php
// ============================================================
// app/views/cardio/edit.php
// Edit a single cardio_logs entry. Same shape as the index form
// but submits to /cardio/update with the row's id as a hidden input.
//
// Variables expected from CardioController::edit():
//   $today           ISO date for "today"
//   $row             the cardio_logs row being edited
//   $typeLabels      ['walk' => 'Walk', ...]
//   $intensityLabels ['easy' => 'Easy', ...]
// ============================================================

$errType      = field_error('cardio_type');
$errDuration  = field_error('duration_min');
$errIntensity = field_error('intensity');
$errDistance  = field_error('distance');
$errDistUnit  = field_error('distance_unit');
$errDate      = field_error('logged_date');

// Round-trip via old() on validation failure; otherwise use the row.
// Intensity, distance, and distance_unit are nullable, so we can't use
// ?: (it'd swap a deliberately-cleared empty back to the row value).
// Instead: if there's no "old" snapshot at all (fresh load), use the row;
// once a failed submit has populated _old, trust whatever the user typed.
$isResubmit = !empty($_SESSION['_old']);

$cardioType  = old('cardio_type')   ?: $row['cardio_type'];
$loggedDate  = old('logged_date')   ?: $row['logged_date'];
$durationVal = $isResubmit ? old('duration_min') : (string) $row['duration_min'];
$intensity   = $isResubmit ? old('intensity')    : (string) ($row['intensity'] ?? '');
$distanceVal = $isResubmit
    ? old('distance')
    : ($row['distance'] !== null
        ? rtrim(rtrim((string) $row['distance'], '0'), '.')
        : '');
$distUnit = $isResubmit
    ? (old('distance_unit') ?: 'mi')
    : ($row['distance_unit'] ?: 'mi');

$fmtDate = static function (string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Cardio tracker</span>
        <div class="hero-heading-row">
            <h1>Edit cardio log</h1>
            <img class="hero-icon"
                 src="<?= asset('images/cardio.png') ?>"
                 alt="" width="96" height="96">
        </div>
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
                    <h2>Update this session</h2>
                    <span class="field-hint">
                        Type, date, and duration are required. Intensity and
                        distance are optional.
                    </span>
                </div>
            </header>

            <form method="post" action="<?= url('cardio/update') ?>"
                  id="cardioForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">

                <div class="form-grid">

                    <div class="field">
                        <label for="cardioType">Type</label>
                        <select id="cardioType" name="cardio_type"
                                <?= $errType ? 'aria-invalid="true" aria-describedby="cardio_type-error"' : '' ?>
                                required>
                            <option value="">Choose…</option>
                            <?php foreach ($typeLabels as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= $cardioType === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($errType): ?>
                            <p id="cardio_type-error" class="field-error"><?= e($errType) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="cardioDate">Date</label>
                        <input type="date" id="cardioDate" name="logged_date"
                               value="<?= e($loggedDate) ?>"
                               max="<?= e($today) ?>"
                               <?= $errDate ? 'aria-invalid="true" aria-describedby="logged_date-error"' : '' ?>
                               required>
                        <?php if ($errDate): ?>
                            <p id="logged_date-error" class="field-error"><?= e($errDate) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="cardioDuration">
                            Duration <span class="field-hint-inline">(min)</span>
                        </label>
                        <input type="number" id="cardioDuration" name="duration_min"
                               inputmode="numeric" step="1" min="1" max="1440"
                               value="<?= e($durationVal) ?>"
                               placeholder="30"
                               <?= $errDuration ? 'aria-invalid="true" aria-describedby="duration_min-error"' : '' ?>
                               required>
                        <?php if ($errDuration): ?>
                            <p id="duration_min-error" class="field-error"><?= e($errDuration) ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="field field--wide">
                    <span class="field-label">
                        Intensity <span class="field-hint-inline">(optional)</span>
                    </span>
                    <div class="goal-picker__pills <?= $errIntensity ? 'is-invalid' : '' ?>"
                         role="radiogroup" aria-label="Intensity"
                         <?= $errIntensity ? 'aria-describedby="intensity-error"' : '' ?>>
                        <label class="goal-pill goal-pill--radio">
                            <input type="radio" name="intensity" value=""
                                   <?= $intensity === '' ? 'checked' : '' ?>>
                            —
                        </label>
                        <?php foreach ($intensityLabels as $key => $label): ?>
                            <label class="goal-pill goal-pill--radio">
                                <input type="radio" name="intensity" value="<?= e($key) ?>"
                                       <?= $intensity === $key ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($errIntensity): ?>
                        <p id="intensity-error" class="field-error"><?= e($errIntensity) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field field--wide">
                    <span class="field-label">
                        Distance <span class="field-hint-inline">(optional)</span>
                    </span>
                    <div class="distance-row">
                        <input type="number" id="cardioDistance" name="distance"
                               class="distance-row__input"
                               inputmode="decimal" step="0.01" min="0.01" max="999.99"
                               value="<?= e($distanceVal) ?>"
                               placeholder="2.5"
                               aria-label="Distance"
                               <?= $errDistance ? 'aria-invalid="true" aria-describedby="distance-error"' : '' ?>>

                        <select name="distance_unit" class="distance-row__unit"
                                aria-label="Distance unit"
                                <?= $errDistUnit ? 'aria-invalid="true" aria-describedby="distance_unit-error"' : '' ?>>
                            <option value="mi" <?= $distUnit === 'mi' ? 'selected' : '' ?>>mi</option>
                            <option value="km" <?= $distUnit === 'km' ? 'selected' : '' ?>>km</option>
                        </select>
                    </div>
                    <?php if ($errDistance): ?>
                        <p id="distance-error" class="field-error"><?= e($errDistance) ?></p>
                    <?php endif; ?>
                    <?php if ($errDistUnit): ?>
                        <p id="distance_unit-error" class="field-error"><?= e($errDistUnit) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-inline">Save changes</button>
                    <a href="<?= url('cardio') ?>" class="btn btn-secondary btn-inline">Cancel</a>
                </div>
            </form>

        </article>
    </div>
</section>
