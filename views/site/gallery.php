<?php
/**
 * Das Shooting für Kundinnen und Kunden: Kopf mit Fortschritt, Filter, Raster (Originalformate), Lightbox, Abschicken.
 * Ohne JavaScript: Bilder ansehen (Links auf die große Fassung), Auswahl braucht JavaScript (Hinweis).
 * @var array $shoot  @var list<array> $items  @var array<int, array{picked: bool, note: string}> $picks  @var bool $editable  @var bool $expired  @var array $notice
 */
use Core\Media;
use Klxm\Auswahl\Auswahl;

$i = require __DIR__ . '/_icons.php';
$max = (int) $shoot['max_picks'];
$count = count(array_filter($picks, fn($p) => $p['picked']));
$notes = count(array_filter($picks, fn($p) => $p['note'] !== ''));
$comments = (bool) $shoot['allow_comments'];
$download = (bool) $shoot['allow_download'];
$name = fn(array $m): string => (string) pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME);
$state = $shoot['status'] === 'submitted' ? 'submitted' : ($expired ? 'expired' : 'open');
?>
<div class="aw" data-aw
  data-csrf="<?= e(\Core\Csrf::token()) ?>" data-pick="<?= e(Auswahl::link($shoot, 'wahl')) ?>" data-note="<?= e(Auswahl::link($shoot, 'notiz')) ?>"
  data-max="<?= $max ?>" data-editable="<?= $editable ? '1' : '0' ?>" data-comments="<?= $comments ? '1' : '0' ?>"
  data-t-pick="<?= e(lt('Auswählen')) ?>" data-t-picked="<?= e(lt('Ausgewählt')) ?>" data-t-saved="<?= e(lt('Gespeichert')) ?>"
  data-t-error="<?= e(lt('Das hat nicht geklappt. Bitte erneut versuchen.')) ?>" data-t-of="<?= e(lt('{i} von {n}')) ?>"
  data-t-count="<?= e($max ? lt('{n} von {max} ausgewählt') : lt('{n} ausgewählt')) ?>">

  <header class="aw-head wrap">
    <div class="aw-head__text">
      <p class="aw-eyebrow"><?= e(lt('Ihre Bildauswahl')) ?><?= $shoot['client'] !== '' ? ' · ' . e($shoot['client']) : '' ?></p>
      <h1 class="aw-title"><?= e($shoot['title']) ?></h1>
      <p class="aw-meta">
        <?php if ($shoot['shoot_date'] !== ''): ?><span><?= e(fmt()->date((string) $shoot['shoot_date'], 'long')) ?></span><?php endif; ?>
        <span><?= e(lt('{n} Dateien', ['n' => count($items)])) ?></span>
        <?php if ($shoot['deadline'] !== '' && $state === 'open'): ?><span><?= e(lt('Auswahl bis {date}', ['date' => fmt()->date((string) $shoot['deadline'], 'long')])) ?></span><?php endif; ?>
      </p>
      <?php if (trim((string) $shoot['intro']) !== ''): ?><p class="aw-lead"><?= nl2br(e($shoot['intro']), false) ?></p><?php endif; ?>
    </div>
    <div class="aw-progress" aria-live="polite">
      <p class="aw-progress__n"><strong data-aw-count><?= $count ?></strong><?php if ($max): ?><span> / <?= $max ?></span><?php endif; ?></p>
      <p class="aw-progress__l"><?= e($max ? lt('ausgewählt – in Ihrem Paket: {n}', ['n' => $max]) : lt('ausgewählt')) ?></p>
      <?php if ($max): ?><div class="aw-bar" role="presentation"><span data-aw-bar data-p="<?= min(100, (int) round($count / $max * 100)) ?>"></span></div><?php endif; ?>
    </div>
  </header>

  <div class="wrap">
    <?php include __DIR__ . '/_notice.php'; ?>
    <?php if ($state === 'submitted'): ?>
      <p class="aw-note"><?= $i('check') ?> <?= e(lt('Ihre Auswahl ist am {date} eingegangen. Änderungen sind nur noch über {name} möglich.', ['date' => fmt()->datetime((string) $shoot['submitted_at']), 'name' => site_name()])) ?></p>
    <?php elseif ($state === 'expired'): ?>
      <p class="aw-note aw-note--error"><?= e(lt('Die Frist für die Auswahl ist abgelaufen. Bitte wenden Sie sich an {name}.', ['name' => site_name()])) ?></p>
    <?php else: ?>
      <noscript><p class="aw-note aw-note--error"><?= e(lt('Zum Auswählen bitte JavaScript einschalten.')) ?></p></noscript>
      <p class="aw-how"><?= $i('heart') ?> <?= e(lt('Tippen Sie auf das Herz, um ein Bild auszuwählen. Groß ansehen mit einem Klick aufs Bild – dort geht es mit den Pfeiltasten weiter, „P“ wählt aus.')) ?></p>
    <?php endif; ?>
  </div>

  <div class="aw-bar-sticky">
    <div class="wrap aw-tools">
      <div class="aw-filter" role="group" aria-label="<?= e(lt('Anzeigen')) ?>">
        <button type="button" class="aw-chip" data-aw-filter="all" aria-pressed="true"><?= e(lt('Alle')) ?> <span><?= count($items) ?></span></button>
        <button type="button" class="aw-chip" data-aw-filter="picked" aria-pressed="false"><?= $i('heart') ?> <?= e(lt('Ausgewählt')) ?> <span data-aw-n-picked><?= $count ?></span></button>
        <?php if ($comments): ?><button type="button" class="aw-chip" data-aw-filter="note" aria-pressed="false"><?= $i('note') ?> <?= e(lt('Mit Notiz')) ?> <span data-aw-n-note><?= $notes ?></span></button><?php endif; ?>
      </div>
      <?php if ($editable): ?>
      <button type="button" class="btn btn--primary aw-send-btn" data-aw-send><?= $i('send') ?> <?= e(lt('Auswahl abschicken')) ?></button>
      <?php endif; ?>
    </div>
  </div>

  <ul class="aw-grid wrap" role="list" data-aw-grid>
    <?php foreach ($items as $k => $m):
        $id = (int) $m['id'];
        $p = $picks[$id] ?? ['picked' => false, 'note' => ''];
        $video = str_starts_with((string) $m['mime'], 'video/');
        $poster = $video ? Media::posterFor($m) : $m;
        $n = $name($m);
        $full = $video ? Media::url($m) : Media::url($m, 2000);
        $w = (int) ($poster['width'] ?? $m['width'] ?? 0) ?: 4; $h = (int) ($poster['height'] ?? $m['height'] ?? 0) ?: 3;
    ?>
    <li class="aw-item<?= $p['picked'] ? ' is-picked' : '' ?><?= $p['note'] !== '' ? ' has-note' : '' ?>" data-aw-item data-id="<?= $id ?>"
      data-kind="<?= $video ? 'video' : 'image' ?>" data-full="<?= e($full) ?>" data-name="<?= e($n) ?>" data-alt="<?= e(Media::alt($m)) ?>"
      data-w="<?= $w ?>" data-h="<?= $h ?>" data-note="<?= e($p['note']) ?>"<?= $video && $poster ? ' data-poster="' . e(Media::url($poster, 1600)) . '"' : '' ?>
      <?= $download ? ' data-download="' . e(Auswahl::link($shoot, 'datei/' . $id)) . '"' : '' ?>>
      <figure class="aw-fig">
        <a class="aw-open" href="<?= e($full) ?>" data-aw-open aria-label="<?= e(lt('Groß ansehen: {name}', ['name' => $n])) ?>">
          <?php if ($poster): ?><?= Media::pictureOf($poster, '(min-width: 72rem) 24rem, (min-width: 40rem) 33vw, 50vw', ['alt' => '', 'fit' => false]) ?>
          <?php else: ?><span class="aw-ph" data-w="<?= $w ?>" data-h="<?= $h ?>"></span><?php endif; ?>
          <?php if ($video): ?><span class="aw-play"><?= $i('play') ?></span><?php endif; ?>
        </a>
        <button type="button" class="aw-pick" data-aw-pick aria-pressed="<?= $p['picked'] ? 'true' : 'false' ?>"<?= $editable ? '' : ' disabled' ?>
          aria-label="<?= e(lt('Auswählen: {name}', ['name' => $n])) ?>"><?= $i('heart') ?></button>
        <figcaption class="aw-cap"><span class="aw-cap__n"><?= str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT) ?></span> <span class="aw-cap__f"><?= e($n) ?></span>
          <span class="aw-cap__note" title="<?= e(lt('Mit Notiz')) ?>"><?= $i('note') ?><span class="aw-sr"><?= e(lt('Mit Notiz')) ?></span></span></figcaption>
      </figure>
    </li>
    <?php endforeach; ?>
  </ul>
  <p class="wrap aw-empty" data-aw-empty hidden><?= e(lt('Keine Dateien in dieser Ansicht.')) ?></p>

  <dialog class="aw-lb" data-aw-lb aria-label="<?= e(lt('Großansicht')) ?>">
    <div class="aw-lb__top">
      <p class="aw-lb__pos" data-aw-pos></p>
      <p class="aw-lb__name" data-aw-name></p>
      <button type="button" class="aw-lb__btn" data-aw-close aria-label="<?= e(lt('Schließen')) ?>"><?= $i('close') ?></button>
    </div>
    <div class="aw-lb__stage" data-aw-stage></div>
    <button type="button" class="aw-lb__nav aw-lb__nav--prev" data-aw-prev aria-label="<?= e(lt('Vorheriges')) ?>"><?= $i('prev') ?></button>
    <button type="button" class="aw-lb__nav aw-lb__nav--next" data-aw-next aria-label="<?= e(lt('Nächstes')) ?>"><?= $i('next') ?></button>
    <div class="aw-lb__bar">
      <button type="button" class="aw-lb__pick" data-aw-lbpick aria-pressed="false"<?= $editable ? '' : ' disabled' ?>><?= $i('heart') ?> <span data-aw-lbpick-l><?= e(lt('Auswählen')) ?></span></button>
      <?php if ($comments): ?>
      <form class="aw-lb__note" data-aw-noteform>
        <label class="aw-sr" for="aw-note"><?= e(lt('Notiz zu diesem Bild')) ?></label>
        <input id="aw-note" type="text" maxlength="<?= Auswahl::NOTE_MAX ?>" placeholder="<?= e(lt('Notiz, z. B. „bitte etwas heller“')) ?>"<?= $editable ? '' : ' readonly' ?>>
        <?php if ($editable): ?><button class="aw-lb__btn aw-lb__btn--text"><?= e(lt('Speichern')) ?></button><?php endif; ?>
      </form>
      <?php endif; ?>
      <a class="aw-lb__btn aw-lb__btn--text" data-aw-dl hidden download><?= $i('download') ?> <?= e(lt('Herunterladen')) ?></a>
      <p class="aw-lb__status" data-aw-status role="status"></p>
    </div>
  </dialog>

  <?php if ($editable): ?>
  <dialog class="aw-send" data-aw-senddlg aria-labelledby="aw-send-h">
    <form method="post" action="<?= e(Auswahl::link($shoot, 'abschicken')) ?>" class="aw-send__form">
      <?= csrf_field() ?>
      <h2 id="aw-send-h"><?= e(lt('Auswahl abschicken')) ?></h2>
      <p data-aw-sendsum><?= e(lt('Sie haben {n} Dateien ausgewählt.', ['n' => $count])) ?></p>
      <p class="aw-hint"><?= e(lt('Danach ist die Auswahl verbindlich. Änderungen sind nur noch über {name} möglich.', ['name' => site_name()])) ?></p>
      <label for="aw-msg"><?= e(lt('Nachricht (optional)')) ?></label>
      <textarea id="aw-msg" name="message" rows="4" maxlength="2000"></textarea>
      <div class="aw-send__actions">
        <button type="button" class="btn btn--secondary" data-aw-sendclose><?= e(lt('Weiter auswählen')) ?></button>
        <button class="btn btn--primary"><?= e(lt('Verbindlich abschicken')) ?></button>
      </div>
    </form>
  </dialog>
  <?php endif; ?>
</div>
<script src="<?= e(Auswahl::asset('js/auswahl.js')) ?>" defer></script>
