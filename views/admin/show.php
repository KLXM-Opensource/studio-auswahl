<?php
/**
 * Ein Shooting: Link teilen, Stand, Auswahl mit Notizen, Dateinamen für Lightroom & Co., Aktionen.
 * @var array $shoot  @var list<array> $items  @var list<array> $picked  @var list<array> $notes  @var list<string> $names  @var ?array $cover  @var bool $mailOk  @var string $title
 */
use Klxm\Auswahl\AdminController;
use Klxm\Auswahl\Auswahl;
$tab = 'show';
$s = $shoot;
$id = (int) $s['id'];
$labels = Auswahl::statusLabels();
$link = Auswahl::link($s, '', true);
include __DIR__ . '/_head.php';
?>
<p><a href="<?= e(url(Auswahl::ADMIN)) ?>"><?= icon('arrow-left') ?> <?= e(__('Alle Shootings')) ?></a></p>

<div class="aw-adm-top">
  <section class="adm-card aw-adm-share" aria-labelledby="aw-share-h">
    <h2 id="aw-share-h"><?= e(__('Link für die Kundin bzw. den Kunden')) ?></h2>
    <div class="aw-adm-link"><code id="aw-link"><?= e($link) ?></code>
      <button type="button" class="adm-btn adm-btn--small" data-copy="#aw-link"><?= icon('copy') ?> <?= e(__('Kopieren')) ?></button>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('arrow-square-out') ?> <?= e(__('Öffnen')) ?></a></div>
    <p class="adm-muted"><?= $s['pin_hash'] !== '' ? icon('lock') . ' ' . e(__('Mit PIN geschützt – die PIN geben Sie getrennt weiter.')) : e(__('Ohne PIN: Wer den Link kennt, sieht die Bilder.')) ?></p>
    <?php if ($s['email'] !== '' && $mailOk): ?>
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/senden')) ?>" class="aw-adm-send">
      <?= csrf_field() ?>
      <label for="aw-note"><?= e(__('Persönliche Zeile in der E-Mail (optional)')) ?></label>
      <textarea id="aw-note" name="note" rows="2" maxlength="1000"></textarea>
      <button class="adm-btn adm-btn--primary"><?= icon('paper-plane-tilt') ?> <?= e($s['invited_at'] ? __('Erneut an {email} senden', ['email' => $s['email']]) : __('Einladung an {email} senden', ['email' => $s['email']])) ?></button>
      <?php if ($s['invited_at']): ?><span class="adm-muted"><?= e(__('Zuletzt gesendet {when}', ['when' => fmt()->relative((string) $s['invited_at'])])) ?></span><?php endif; ?>
    </form>
    <?php endif; ?>
  </section>

  <section class="adm-card aw-adm-facts" aria-label="<?= e(__('Stand')) ?>">
    <?php if ($cover): ?><div class="aw-adm-facts__img"><?= AdminController::thumb($cover, '16rem') ?></div><?php endif; ?>
    <dl>
      <div><dt><?= e(__('Status')) ?></dt><dd><span class="aw-adm-badge aw-adm-badge--<?= e($s['status']) ?>"><?= e($labels[$s['status']]) ?></span></dd></div>
      <div><dt><?= e(__('Ausgewählt')) ?></dt><dd><b><?= count($picked) ?></b><?= (int) $s['max_picks'] ? ' / ' . (int) $s['max_picks'] : '' ?> · <?= e(__('{n} Dateien', ['n' => count($items)])) ?></dd></div>
      <div><dt><?= e(__('Kundin bzw. Kunde')) ?></dt><dd><?= e($s['client'] ?: '–') ?><?= $s['email'] !== '' ? ' · ' . e($s['email']) : '' ?></dd></div>
      <div><dt><?= e(__('Aufrufe')) ?></dt><dd><?= (int) $s['views'] ?><?= $s['last_seen_at'] ? ' · ' . e(__('zuletzt {when}', ['when' => fmt()->relative((string) $s['last_seen_at'])])) : '' ?></dd></div>
      <?php if ($s['deadline']): ?><div><dt><?= e(__('Auswahl bis')) ?></dt><dd><?= e(fmt()->date((string) $s['deadline'], 'long')) ?><?= Auswahl::expired($s) ? ' · ' . e(__('abgelaufen')) : '' ?></dd></div><?php endif; ?>
      <?php if ($s['submitted_at']): ?><div><dt><?= e(__('Abgeschickt')) ?></dt><dd><?= e(fmt()->datetime((string) $s['submitted_at'])) ?></dd></div><?php endif; ?>
    </dl>
    <div class="aw-adm-actions">
      <a class="adm-btn adm-btn--small" href="<?= e(url(Auswahl::ADMIN . '/' . $id . '/bearbeiten')) ?>"><?= icon('pencil-simple') ?> <?= e(__('Bearbeiten')) ?></a>
      <?php foreach (['open' => __('Wieder öffnen'), 'closed' => __('Abschließen')] as $to => $label): if ($s['status'] === $to) continue; ?>
      <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= e($to) ?>"><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e($label) ?></button></form>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php if ($s['pool'] !== ''): ?>
<section class="adm-card" id="dateien" aria-labelledby="aw-files-h">
  <div class="aw-adm-row">
    <h2 id="aw-files-h"><?= e(__('Bilder und Videos')) ?> <span class="adm-count" data-aw-count><?= count($items) ?></span></h2>
    <?php if (count($items) > 1): ?>
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/sortieren')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= icon('sort-ascending') ?> <?= e(__('Nach Dateinamen sortieren')) ?></button></form>
    <?php endif; ?>
  </div>
  <div class="aw-adm-drop" data-aw-upload data-chunk="<?= e(url(Auswahl::ADMIN . '/' . $id . '/upload/teil')) ?>" data-finish="<?= e(url(Auswahl::ADMIN . '/' . $id . '/upload/fertig')) ?>"
    data-csrf="<?= e(\Core\Csrf::token()) ?>" data-limit="<?= (int) \Core\Media::maxBytes() ?>"
    data-t-done="<?= e(__('{n} Dateien hochgeladen.')) ?>" data-t-big="<?= e(__('Zu groß: {name}')) ?>" data-t-err="<?= e(__('Fehler bei {name}: {err}')) ?>">
    <?= icon('upload-simple') ?>
    <p><strong><?= e(__('Fotos und Videos hierher ziehen')) ?></strong><br><span class="adm-muted"><?= e(__('oder auswählen – JPEG, PNG, WebP, HEIC, MP4, MOV, bis {n} MB je Datei. Dateinamen bleiben erhalten.', ['n' => (int) round(\Core\Media::maxBytes() / 1048576)])) ?></span></p>
    <label class="adm-btn adm-btn--primary"><input type="file" multiple accept="image/*,video/*" data-aw-input hidden> <?= e(__('Dateien auswählen')) ?></label>
    <ul class="aw-adm-queue" data-aw-queue role="list" aria-live="polite"></ul>
  </div>
  <p class="adm-muted"><?= icon('lock') ?> <?= e(__('Geschützter Ordner: Die Dateien liegen außerhalb der Website und sind nur für Sie und für Kunden mit entsperrtem Link abrufbar – nicht in der Mediathek, nicht in der Suche.')) ?></p>
  <?php if ($items): ?>
  <ul class="aw-adm-grid aw-adm-grid--files" role="list">
    <?php foreach ($items as $m): ?>
    <li><figure><?= AdminController::thumb($m, '10rem') ?><figcaption><?= e(pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME)) ?></figcaption></figure>
      <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/datei/' . (int) $m['id'] . '/entfernen')) ?>" data-confirm="<?= e(__('„{name}“ endgültig löschen?', ['name' => (string) $m['original_name']])) ?>">
        <?= csrf_field() ?><button class="aw-adm-x" aria-label="<?= e(__('Entfernen: {name}', ['name' => (string) $m['original_name']])) ?>"><?= icon('trash') ?></button></form></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (trim((string) $s['message']) !== ''): ?>
<section class="adm-card"><h2><?= e(__('Nachricht')) ?></h2><p class="aw-adm-msg"><?= nl2br(e($s['message']), false) ?></p></section>
<?php endif; ?>

<section class="adm-card" aria-labelledby="aw-sel-h">
  <div class="aw-adm-row">
    <h2 id="aw-sel-h"><?= e(__('Auswahl')) ?> <span class="adm-count"><?= count($picked) ?></span></h2>
    <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url(Auswahl::ADMIN . '/' . $id . '/auswahl.csv')) ?>"><?= icon('download-simple') ?> <?= e(__('CSV herunterladen')) ?></a>
  </div>
  <?php if (!$picked): ?>
    <p class="adm-muted"><?= e(__('Noch nichts ausgewählt.')) ?></p>
  <?php else: ?>
    <div class="aw-adm-names">
      <label for="aw-names"><?= e(__('Dateinamen (z. B. für den Filter in Lightroom oder Capture One)')) ?></label>
      <div class="aw-adm-link"><code id="aw-names"><?= e(implode(', ', $names)) ?></code>
        <button type="button" class="adm-btn adm-btn--small" data-copy="#aw-names"><?= icon('copy') ?> <?= e(__('Kopieren')) ?></button></div>
    </div>
    <ul class="aw-adm-grid" role="list">
      <?php foreach ($picked as $m): ?>
      <li><figure><?= AdminController::thumb($m, '10rem') ?><figcaption><?= e(pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME)) ?><?= $m['_note'] !== '' ? '<span class="aw-adm-note">' . e($m['_note']) . '</span>' : '' ?></figcaption></figure></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php $loose = array_values(array_filter($notes, fn($m) => !$m['_picked'])); if ($loose): ?>
    <h3><?= e(__('Notizen zu nicht ausgewählten Dateien')) ?></h3>
    <ul class="aw-adm-grid" role="list">
      <?php foreach ($loose as $m): ?>
      <li><figure><?= AdminController::thumb($m, '10rem') ?><figcaption><?= e(pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME)) ?><span class="aw-adm-note"><?= e($m['_note']) ?></span></figcaption></figure></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="adm-card aw-adm-danger" aria-labelledby="aw-more-h">
  <h2 id="aw-more-h"><?= e(__('Weitere Aktionen')) ?></h2>
  <div class="aw-adm-actions">
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/neuer-link')) ?>" data-confirm="<?= e(__('Neuen Link erzeugen? Der bisherige Link funktioniert danach nicht mehr.')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Neuen Link erzeugen')) ?></button></form>
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/zuruecksetzen')) ?>" data-confirm="<?= e(__('Auswahl und Notizen dieses Shootings löschen?')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Auswahl zurücksetzen')) ?></button></form>
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/loeschen')) ?>" data-confirm="<?= e($s['pool'] !== '' ? __('Shooting „{title}“ samt aller Dateien endgültig löschen?', ['title' => $s['title']]) : __('Shooting „{title}“ löschen? Die Dateien bleiben in der Mediathek.', ['title' => $s['title']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger"><?= e(__('Shooting löschen')) ?></button></form>
  </div>
</section>
