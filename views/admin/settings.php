<?php
/** Einstellungen. @var array $values  @var array $errors */
use Core\Fields;
use Klxm\Auswahl\Auswahl;
$tab = 'settings';
include __DIR__ . '/_head.php';
?>
<form method="post" action="<?= e(url(Auswahl::ADMIN . '/einstellungen')) ?>" novalidate>
  <?= csrf_field() ?>
  <section class="adm-card"><div class="adm-fields"><?= Fields::renderForm([
      ['name' => 'notify', 'label' => __('Hinweis auf eingegangene Auswahl an'), 'type' => 'text', 'max' => 400,
       'help' => __('E-Mail-Adressen, durch Komma getrennt. Leer = Empfänger der Website (System → E-Mail-Versand).')],
      ['name' => 'watermark', 'label' => __('Text des Wasserzeichens'), 'type' => 'text', 'max' => 60, 'placeholder' => '© {site} · ' . __('Vorschau'),
       'help' => __('Erscheint schräg über allen Vorschauen, bis Sie ein Shooting freigeben. {site} = Name der Website. Leer = Standard.')],
  ], $values, $errors, 'f') ?></div></section>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button></div>
</form>
