<?php
/** PIN eingeben. @var array $shoot  @var string $error  @var array $notice */
use Klxm\Auswahl\Auswahl;
$i = require __DIR__ . '/_icons.php';
?>
<div class="wrap aw-wrap"><div class="aw-card aw-card--pin">
  <p class="aw-eyebrow"><?= $i('lock') ?> <?= e(lt('Geschützte Auswahl')) ?></p>
  <h1 class="aw-title"><?= e($shoot['title']) ?></h1>
  <p class="aw-lead"><?= e(lt('Bitte geben Sie die PIN ein, die Sie von {name} erhalten haben.', ['name' => site_name()])) ?></p>
  <?php include __DIR__ . '/_notice.php'; ?>
  <form method="post" action="<?= e(Auswahl::link($shoot, 'pin')) ?>" class="aw-pin">
    <?= csrf_field() ?>
    <label for="aw-pin"><?= e(lt('PIN')) ?></label>
    <input id="aw-pin" name="pin" type="password" inputmode="text" autocomplete="one-time-code" required minlength="4" maxlength="12"
      <?= $error !== '' ? 'aria-invalid="true" aria-describedby="aw-pin-err"' : '' ?> autofocus>
    <?php if ($error !== ''): ?><p class="aw-err" id="aw-pin-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <button class="btn btn--primary"><?= e(lt('Bilder ansehen')) ?></button>
  </form>
</div></div>
