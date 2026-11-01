<?php
/** Индикатор этапов проверки. Нужны: $stepStatus, $stepFee, $stepVisit (строки). */
$labels = inspectionStepLabels();
$flags = inspectionStepFlags($stepStatus, $stepFee, $stepVisit);
$cancelled = $stepStatus === 'cancelled';
$current = array_search(false, $flags, true);
?>
<ol class="insp-steps<?= $cancelled ? ' is-cancelled' : '' ?>" aria-label="Этапы проверки">
    <?php foreach ($labels as $n => $label):
        $state = $cancelled ? 'off' : ($flags[$n] ? 'done' : ($n === $current ? 'now' : 'todo'));
    ?>
        <li class="insp-step is-<?= e($state) ?>">
            <span class="insp-dot"><?= $state === 'done' ? '<i class="bi bi-check-lg"></i>' : ($n + 1) ?></span>
            <span class="insp-label"><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
