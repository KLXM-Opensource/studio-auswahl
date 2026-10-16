<?php
/** Kopf der Bildauswahl in der Verwaltung. @var string $tab  @var string $title */
use Klxm\Auswahl\Auswahl;
$tabs = ['index' => [Auswahl::ADMIN, __('Shootings')], 'settings' => [Auswahl::ADMIN . '/einstellungen', __('Einstellungen')]];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Erweiterung')) ?> · <?= e(__('Bildauswahl')) ?></p><h1><?= e($title) ?></h1></div>
  <?php if ($tab === 'index'): ?><a class="adm-btn adm-btn--primary" href="<?= e(url(Auswahl::ADMIN . '/neu')) ?>"><?= icon('plus') ?> <?= e(__('Neues Shooting')) ?></a><?php endif; ?>
</header>
<?php if (in_array($tab, ['index', 'settings'], true)): ?>
<nav class="aw-adm-tabs" aria-label="<?= e(__('Bildauswahl')) ?>">
  <?php foreach ($tabs as $k => [$href, $label]): ?><a href="<?= e(url($href)) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>
