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

<?php if ($s['message'] !== ''): ?>
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
    <form method="post" action="<?= e(url(Auswahl::ADMIN . '/' . $id . '/loeschen')) ?>" data-confirm="<?= e(__('Shooting „{title}“ löschen? Die Dateien bleiben in der Mediathek.', ['title' => $s['title']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger"><?= e(__('Shooting löschen')) ?></button></form>
  </div>
</section>
