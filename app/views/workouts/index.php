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

// What a logged lift reads as in the sessions list: "225 lbs · 3 × 5",
// "BW · × 8" for bodyweight, "+25 lbs · × 6" for weighted bodyweight.
$fmtLoad = static function (array $r): string {
    if ($r['weight'] === null) {
        $load = 'BW';
    } else {
        $prefix = StrengthLog::isBodyweight($r['lift_type']) ? '+' : '';
        $load = $prefix
            . rtrim(rtrim(number_format((float) $r['weight'], 2, '.', ''), '0'), '.')
            . ' ' . $r['unit'];
    }
    $sets = (int) ($r['sets'] ?? 1);
    $sr   = $sets > 1 ? $sets . ' × ' . (int) $r['reps'] : '× ' . (int) $r['reps'];
    return $load . ' · ' . $sr;
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
            optional target sets and reps — then hit Start on training
            day to log the whole session in one go. Every lift lands in
            your strength history automatically.
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
                                <a class="btn btn-inline btn--sm"
                                   href="<?= url('workouts/start?id=' . (int) $w['id']) ?>"
                                   aria-label="Start <?= e($w['name']) ?>">
                                    Start
                                </a>
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


<!-- ===================== Recent sessions ===================== -->
<?php if (!empty($sessions) || !empty($workouts)): ?>
<section class="section section--alt">
    <div class="container">

        <?php if (!empty($sessions)): ?>

            <article class="tracker-card">
                <header class="tracker-card__head">
                    <div>
                        <h2>Recent sessions</h2>
                        <span class="field-hint">
                            Workouts you've logged, newest first. Every lift
                            here also lives in your strength history.
                        </span>
                    </div>
                    <a class="btn-link" href="<?= url('strength') ?>">
                        Strength tracker <span aria-hidden="true">&rarr;</span>
                    </a>
                </header>

                <ul class="session-list">
                    <?php foreach ($sessions as $s):
                        $lifts = $sessionLifts[(int) $s['id']] ?? [];
                        $n = count($lifts);
                        $confirmMsg = $n > 0
                            ? 'Delete this session and its ' . $n . ' logged lift'
                              . ($n === 1 ? '' : 's')
                              . "? They'll be removed from your strength history too. This can't be undone."
                            : "Delete this session? This can't be undone.";
                    ?>
                        <li class="session-item">
                            <div class="session-item__top">
                                <div>
                                    <h3 class="session-item__name"><?= e($s['name']) ?></h3>
                                    <p class="session-item__meta">
                                        <?= e($fmtDate($s['logged_date'])) ?>
                                        · <?= $n ?> lift<?= $n === 1 ? '' : 's' ?>
                                    </p>
                                </div>
                                <form method="post" action="<?= url('workouts/session/delete') ?>"
                                      data-confirm="<?= e($confirmMsg) ?>"
                                      data-confirm-ok="Delete">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <button type="submit" class="btn-link-danger"
                                            aria-label="Delete session <?= e($s['name']) ?> from <?= e($fmtDate($s['logged_date'])) ?>">
                                        Delete
                                    </button>
                                </form>
                            </div>
                            <?php if (!empty($lifts)): ?>
                                <ol class="workout-card__exercises">
                                    <?php foreach ($lifts as $l): ?>
                                        <li class="workout-exercise">
                                            <span class="workout-exercise__name"><?= e(StrengthLog::label($l['lift_type'])) ?></span>
                                            <span class="workout-exercise__target"><?= e($fmtLoad($l)) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

        <?php else: ?>

            <article class="tracker-card empty-state">
                <?= empty_state_icon() ?>
                <h2>No sessions yet</h2>
                <p>
                    Hit Start on a workout above on training day — you'll get
                    a pre-filled form to log the whole session in one go.
                </p>
            </article>

        <?php endif; ?>

    </div>
</section>
<?php endif; ?>
