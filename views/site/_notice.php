<?php /** Hinweise aus der Sitzung. @var array $notice [[typ, text], …] */ ?>
<?php foreach ($notice as [$type, $msg]): ?>
<p class="aw-note<?= $type === 'error' ? ' aw-note--error' : '' ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></p>
<?php endforeach; ?>
