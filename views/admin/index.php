<?php
/** Übersicht der Shootings. @var list<array> $rows  @var string $status  @var array $counts  @var bool $mailOk */
use Klxm\Auswahl\AdminController;
use Klxm\Auswahl\Auswahl;
use Klxm\Auswahl\Repo;
$labels = Auswahl::statusLabels();
include __DIR__ . '/_head.php';
?>
<?php if (!$mailOk): ?><p class="adm-flash adm-flash--error" role="status"><?= e(__('Der E-Mail-Versand ist nicht eingerichtet (System → E-Mail-Versand) – Links können Sie trotzdem kopieren und selbst weitergeben.')) ?></p><?php endif; ?>
<nav class="aw-adm-filter" aria-label="<?= e(__('Status')) ?>">
  <a href="<?= e(url(Auswahl::ADMIN)) ?>"<?= $status === '' ? ' aria-current="true"' : '' ?>><?= e(__('Alle')) ?> <span><?= array_sum($counts) ?></span></a>
  <?php foreach ($labels as $k => $l): ?><a href="<?= e(url(Auswahl::ADMIN) . '?status=' . $k) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?> <span><?= (int) $counts[$k] ?></span></a><?php endforeach; ?>
</nav>
<?php if (!$rows): ?>
<section class="adm-card aw-adm-empty">
  <h2><?= e(__('Noch keine Shootings')) ?></h2>
  <ol>
    <li><?= e(__('Bilder und Videos in der Mediathek in eine neue Sammlung laden (z. B. „Hochzeit Anna & Ben“).')) ?></li>
    <li><?= e(__('Hier ein Shooting anlegen: Sammlung wählen, Kunde, Paketgröße, Frist, optional eine PIN.')) ?></li>
    <li><?= e(__('Link per E-Mail senden oder kopieren. Ihre Kundin bzw. Ihr Kunde wählt aus und schickt die Auswahl ab – Sie bekommen eine E-Mail mit den Dateinamen.')) ?></li>
  </ol>
  <p><a class="adm-btn adm-btn--primary" href="<?= e(url(Auswahl::ADMIN . '/neu')) ?>"><?= e(__('Erstes Shooting anlegen')) ?></a></p>
</section>
<?php else: ?>
<ul class="aw-adm-cards" role="list">
  <?php foreach ($rows as $s): $cover = Repo::cover($s); ?>
  <li class="aw-adm-card">
    <a href="<?= e(url(Auswahl::ADMIN . '/' . (int) $s['id'])) ?>">
      <span class="aw-adm-card__img"><?= $cover ? AdminController::thumb($cover, '20rem') : '' ?></span>
      <span class="aw-adm-card__body">
        <span class="aw-adm-badge aw-adm-badge--<?= e($s['status']) ?>"><?= e($labels[$s['status']] ?? $s['status']) ?></span>
        <strong><?= e($s['title']) ?></strong>
        <span class="adm-muted"><?= e($s['client'] ?: '–') ?><?= $s['shoot_date'] ? ' · ' . e(fmt()->date((string) $s['shoot_date'])) : '' ?></span>
        <span class="aw-adm-card__n"><b><?= (int) $s['picked'] ?></b><?= (int) $s['max_picks'] ? ' / ' . (int) $s['max_picks'] : '' ?> <?= e(__('ausgewählt')) ?> · <?= e(__('{n} Dateien', ['n' => (int) $s['count']])) ?></span>
        <span class="adm-muted aw-adm-card__seen"><?= $s['last_seen_at'] ? e(__('Zuletzt angesehen {when}', ['when' => fmt()->relative((string) $s['last_seen_at'])])) : e(__('Noch nicht geöffnet')) ?><?= $s['deadline'] ? ' · ' . e(__('bis {date}', ['date' => fmt()->date((string) $s['deadline'])])) : '' ?></span>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
