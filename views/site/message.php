<?php /** Hinweisseite. @var string $title  @var string $text  @var array $notice */ ?>
<div class="wrap aw-wrap"><div class="aw-card">
  <h1 class="aw-title"><?= e($title) ?></h1>
  <?php include __DIR__ . '/_notice.php'; ?>
  <p class="aw-lead"><?= e($text) ?></p>
</div></div>
