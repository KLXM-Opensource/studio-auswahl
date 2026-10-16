<?php
/** Shooting anlegen/bearbeiten. @var ?array $shoot  @var array $values  @var array $errors  @var string $title */
use Core\Fields;
use Klxm\Auswahl\AdminController;
use Klxm\Auswahl\Auswahl;
$tab = 'edit';
include __DIR__ . '/_head.php';
$action = $shoot ? Auswahl::ADMIN . '/' . (int) $shoot['id'] . '/bearbeiten' : Auswahl::ADMIN;
unset($values['pin'], $values['pin_hash']);
?>
<p><a href="<?= e(url($shoot ? Auswahl::ADMIN . '/' . (int) $shoot['id'] : Auswahl::ADMIN)) ?>"><?= icon('arrow-left') ?> <?= e(__('Zurück')) ?></a></p>
<?php if ($shoot && $shoot['pin_hash'] !== ''): ?><p class="adm-muted"><?= icon('lock') ?> <?= e(__('Dieses Shooting ist mit einer PIN geschützt.')) ?></p><?php endif; ?>
<form method="post" action="<?= e(url($action)) ?>" novalidate>
  <?= csrf_field() ?>
  <section class="adm-card"><div class="adm-fields"><?= Fields::renderForm(AdminController::fields($shoot === null), $values, $errors, 'f') ?></div></section>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary"><?= e($shoot ? __('Speichern') : __('Shooting anlegen')) ?></button></div>
</form>
