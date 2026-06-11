<?php
// ============================================================
// app/views/workouts/index.php
// Workout builder — list of a user's saved templates.
//
// Variables from WorkoutController::index():
//   $workouts  rows: id, name, notes, exercise_count, created_at
// ============================================================

$fmtDate = static function (?string $iso): string {
    if (!$iso) return '';
    $ts = strtotime($iso);
    return $ts ? date('M j, Y', $ts) : $iso;
};

// Compact target label for a template exercise: "3 × 5", "5 reps",
// "3 sets", or null when no targets were set on that line.
$fmtTarget = static function ($sets, $reps): ?string {
    $s = ($sets === null || $sets === '') ? null : (int) $sets;
    $r = ($reps === null || $reps === '') ? null : (int) $reps;
    if ($s !== null && $r !== null) return $s . ' × ' . $r;
    if ($s !== null) return $s . ' set' . ($s === 1 ? '' : 's');
    if ($r !== null) return $r . ' rep' . ($r === 1 ? '' : 's');
    return null;
};
?>


<!-- ===================== Hero ===================== -->
<section class="hero hero--compact">
    <div class="container">
        <span class="eyebrow">Workout builder</span>
        <div class="hero-heading-row">
            <h1>Your workout templates.</h1>
            <img class="hero-icon"
                 src="<?= asset('images/strengthlogo.png') ?>"
                 alt="" width="96" height="96">
        </div>
        <p class="hero-lede">
            Save a workout once — a named, ordered list of lifts with
            optional target sets and reps — then reuse it whenever you
            train. Building the template is step one; logging a session
            straight from it arrives in the next update.
        </p>
    </div>
</section>


<!-- ===================== List ===================== -->
<section class="section">
    <div class="container">

        <div class="page-toolbar">
            <a class="back-link" href="<?= url('dashboard') ?>">
                <span class="back-link__arrow" aria-hidden="true">&larr;</span>
                Back to dashboard
            </a>
            <a class="btn btn-inline" href="<?= url('workouts/new') ?>">+ New workout</a>
        </div>

        <?php if ($flashMsg = flash('success')): ?>
            <div class="flash flash-success flash--centered" role="status">
                <?= e($flashMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashErr = flash('error')): ?>
            <div class="flash flash-error flash--centered" role="alert">
                <?= e($flashErr) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($workouts)): ?>

            <article class="tracker-card empty-state">
                <?= empty_state_icon() ?>
                <h2>No workouts yet</h2>
                <p>
                    Create your first template to line up the lifts you do
                    together — push day, leg day, whatever your split looks like.
                </p>
                <div class="empty-state__cta">
                    <a class="btn btn-inline" href="<?= url('workouts/new') ?>">Build a workout</a>
                </div>
            </article>

        <?php else: ?>

            <ul class="workout-list">
                <?php foreach ($workouts as $w):
                    $count = (int) $w['exercise_count'];
                ?>
                    <li class="workout-card">
                        <div class="workout-card__top">
                            <div class="workout-card__heading">
                                <h2 class="workout-card__name"><?= e($w['name']) ?></h2>
                                <p class="workout-card__meta">
                                    <?= $count ?> exercise<?= $count === 1 ? '' : 's' ?>
                                    <?php if (!empty($w['created_at'])): ?>
                                        · added <?= e($fmtDate($w['created_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="workout-card__actions">
                                <a class="btn-link"
                                   href="<?= url('workouts/edit?id=' . (int) $w['id']) ?>">
                                    Edit
                                </a>
                                <form method="post" action="<?= url('workouts/delete') ?>"
                                      data-confirm="Delete &ldquo;<?= e($w['name']) ?>&rdquo;? This can't be undone."
                                      data-confirm-ok="Delete">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $w['id']) ?>">
                                    <button type="submit" class="btn-link-danger"
                                            aria-label="Delete <?= e($w['name']) ?>">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php $exs = $exercisesBy[(int) $w['id']] ?? []; ?>
                        <?php if (!empty($exs)): ?>
                            <ol class="workout-card__exercises">
                                <?php foreach ($exs as $ex):
                                    $target = $fmtTarget($ex['target_sets'] ?? null, $ex['target_reps'] ?? null);
                                ?>
                                    <li class="workout-exercise">
                                        <span class="workout-exercise__name"><?= e(StrengthLog::label($ex['lift_type'])) ?></span>
                                        <span class="workout-exercise__target<?= $target === null ? ' text-faint' : '' ?>"><?= $target === null ? '&mdash;' : e($target) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if (!empty($w['notes'])): ?>
                            <p class="workout-card__notes"><?= e($w['notes']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php endif; ?>

    </div>
</section>
