<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Theme;

/**
 * Verwaltung /admin/auswahl: Shootings anlegen (Sammlung der Mediathek, Kunde, PIN, Paketgröße, Frist), Link teilen und per
 * E-Mail senden, Auswahl ansehen (Dateinamen kopieren, CSV), Status ändern. Recht und CSRF prüft der Router.
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!\Core\Extensions::isActive(Auswahl::NAME) || !\Core\Features::on(Auswahl::FEATURE)) throw new HttpException(404);
        return $this->auth($r, Auswahl::PERM);
    }

    private function page(string $view, array $vars = [], int $status = 200): Response
    {
        $vars += ['errors' => [], 'tab' => $view];
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] = [];
        $vars['user'] = app()->auth->user();
        $content = Theme::capture(dirname(__DIR__) . '/views/admin/' . $view . '.php', $vars);
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'auswahl', 'title' => $vars['title'] ?? __('Bildauswahl')]);
        return self::secure(new Response($html, $status));
    }

    private function to(string $path, string $type, string $msg): Response
    {
        app()->session->flash($type, $msg);
        return Response::redirect(url($path), 303);
    }

    private function shoot(string $id): array
    {
        return Repo::find((int) $id) ?? throw new HttpException(404);
    }

    public static function fields(bool $new, bool $pool = false): array
    {
        $langs = [];
        foreach (\Core\Lang::all() as $code => $l) $langs[$code] = is_array($l) ? (string) ($l['label'] ?? $code) : (string) $l;
        $f = [
            ['type' => 'heading', 'label' => __('Shooting')],
            ['name' => 'title', 'label' => __('Titel'), 'type' => 'text', 'required' => true, 'max' => 160, 'placeholder' => __('z. B. Hochzeit Anna & Ben')],
            ['name' => 'collection_id', 'label' => __('Vorhandene Sammlung der Mediathek (optional)'), 'type' => 'collection', 'width' => 'half',
                'help' => __('Leer lassen (empfohlen): Das Shooting bekommt einen eigenen, geschützten Ordner – Sie laden die Dateien danach direkt im Shooting hoch, die Mediathek der Website bleibt frei.')],
            ['name' => 'shoot_date', 'label' => __('Datum des Shootings'), 'type' => 'date', 'width' => 'half'],
            ['name' => 'intro', 'label' => __('Begrüßung'), 'type' => 'textarea', 'max' => 1500, 'rows' => 4,
                'help' => __('Erscheint über den Bildern, z. B. „Schön war’s! Wählen Sie bitte Ihre 20 Favoriten.“')],
            ['type' => 'heading', 'label' => __('Kundin bzw. Kunde')],
            ['name' => 'client', 'label' => __('Name'), 'type' => 'text', 'max' => 160, 'width' => 'half'],
            ['name' => 'email', 'label' => __('E-Mail-Adresse'), 'type' => 'email', 'width' => 'half', 'help' => __('Für Einladung und Bestätigung; Antworten auf den Hinweis gehen hierhin.')],
            ['type' => 'heading', 'label' => __('Regeln')],
            ['name' => 'max_picks', 'label' => __('Dateien im Paket'), 'type' => 'number', 'default' => 0, 'width' => 'half', 'help' => __('Höchstens so viele dürfen ausgewählt werden. 0 = beliebig viele.')],
            ['name' => 'deadline', 'label' => __('Auswahl bis'), 'type' => 'date', 'width' => 'half', 'help' => __('Danach ist nur noch Ansehen möglich. Leer = ohne Frist.')],
            ['name' => 'pin', 'label' => $new ? __('PIN (optional)') : __('Neue PIN (optional)'), 'type' => 'text', 'max' => 12, 'width' => 'half',
                'help' => $new ? __('4–12 Ziffern oder Buchstaben. Schicken Sie sie getrennt vom Link, z. B. per SMS.') : __('Leer lassen = bisherige PIN behalten. Eine neue PIN sperrt geöffnete Sitzungen.')],
            ['name' => 'lang', 'label' => __('Sprache für Kundin bzw. Kunde'), 'type' => 'select', 'width' => 'half', 'options' => ['' => __('Standardsprache der Website')] + $langs],
            ['name' => 'allow_comments', 'label' => __('Notizen zu einzelnen Bildern erlauben'), 'type' => 'bool', 'default' => true, 'width' => 'half'],
            ['name' => 'allow_download', 'label' => __('Herunterladen der Originale erlauben'), 'type' => 'bool', 'default' => false, 'width' => 'half',
                'help' => __('Zum Beispiel für die Abgabe der fertigen Bilder über denselben Link.')],
        ];
        if ($pool) $f = array_values(array_filter($f, fn($x) => ($x['name'] ?? '') !== 'collection_id'));
        if (!$new) {
            $f[] = ['name' => 'remove_pin', 'label' => __('PIN entfernen (Link genügt)'), 'type' => 'bool', 'default' => false, 'width' => 'half'];
        }
        return $f;
    }

    // ------------------------------------------------------------------ Übersicht

    public function index(Request $r): Response
    {
        $this->guard($r);
        $status = (string) ($r->query['status'] ?? '');
        return $this->page('index', ['title' => __('Bildauswahl'), 'rows' => Repo::shoots($status), 'status' => $status, 'counts' => Repo::counts(), 'mailOk' => \Core\Mailer::ready()]);
    }

    public function create(Request $r): Response
    {
        $this->guard($r);
        return $this->page('edit', ['title' => __('Neues Shooting'), 'shoot' => null, 'values' => ['allow_comments' => true, 'max_picks' => 0]]);
    }

    public function edit(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        return $this->page('edit', ['title' => __('Shooting bearbeiten'), 'shoot' => $s, 'pool' => (string) $s['pool'] !== '', 'values' => $s + ['allow_comments' => (bool) $s['allow_comments'], 'allow_download' => (bool) $s['allow_download']]]);
    }

    public function store(Request $r): Response
    {
        $this->guard($r);
        return $this->save($r, null);
    }

    public function update(Request $r, string $id): Response
    {
        $this->guard($r);
        return $this->save($r, $this->shoot($id));
    }

    private function save(Request $r, ?array $s): Response
    {
        $inPool = $s !== null && (string) $s['pool'] !== '';
        [$v, $errors] = Fields::sanitize(self::fields($s === null, $inPool), (array) ($r->post['f'] ?? []));
        $pin = trim((string) ($v['pin'] ?? ''));
        if (!Auswahl::validPin($pin)) $errors['pin'] = __('Die PIN braucht 4–12 Ziffern oder Buchstaben.');
        if ($s && !$inPool && !(int) ($v['collection_id'] ?? 0)) $errors['collection_id'] = __('Bitte eine Sammlung wählen.');
        if ((int) ($v['max_picks'] ?? 0) < 0) $errors['max_picks'] = __('Bitte 0 oder mehr eintragen.');
        if ($errors) return $this->page('edit', ['title' => $s ? __('Shooting bearbeiten') : __('Neues Shooting'), 'shoot' => $s, 'pool' => $inPool, 'values' => $v, 'errors' => $errors], 422);
        $data = [
            'title' => (string) $v['title'], 'client' => (string) ($v['client'] ?? ''), 'email' => mb_strtolower((string) ($v['email'] ?? '')),
            'intro' => (string) ($v['intro'] ?? ''), 'shoot_date' => (string) ($v['shoot_date'] ?? ''),
            'max_picks' => max(0, (int) ($v['max_picks'] ?? 0)), 'deadline' => (string) ($v['deadline'] ?? ''), 'lang' => (string) ($v['lang'] ?? ''),
            'allow_comments' => empty($v['allow_comments']) ? 0 : 1, 'allow_download' => empty($v['allow_download']) ? 0 : 1,
        ];
        if (!$inPool && (int) ($v['collection_id'] ?? 0)) $data['collection_id'] = (int) $v['collection_id'];
        if ($pin !== '') $data['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
        elseif ($s && !empty($v['remove_pin'])) $data['pin_hash'] = '';
        if ($s) {
            Repo::update((int) $s['id'], $data);
            return $this->to(Auswahl::ADMIN . '/' . (int) $s['id'], 'success', __('Gespeichert.'));
        }
        if (empty($data['collection_id'])) {
            // Eigener Ordner: Sammlung im geschützten Pool „Shootings“ dieser Website
            $data['pool'] = Auswahl::ensurePool();
            $data['collection_id'] = Repo::inPool($data, fn() => Media::createCollection((string) $data['title'], __('Shooting der Bildauswahl')));
            $id = Repo::create($data);
            return $this->to(Auswahl::ADMIN . '/' . $id, 'success', __('Shooting angelegt. Laden Sie jetzt die Bilder und Videos hoch.'));
        }
        $id = Repo::create($data);
        return $this->to(Auswahl::ADMIN . '/' . $id, 'success', __('Shooting angelegt. Teilen Sie den Link mit Ihrer Kundin bzw. Ihrem Kunden.'));
    }

    // ------------------------------------------------------------------ Ein Shooting

    public function show(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $items = Repo::items($s);
        $sel = Repo::selection($s);
        $picked = array_values(array_filter($sel, fn($m) => $m['_picked']));
        return $this->page('show', ['title' => (string) $s['title'], 'shoot' => $s, 'items' => $items, 'picked' => $picked,
            'notes' => array_values(array_filter($sel, fn($m) => $m['_note'] !== '')), 'names' => Repo::fileNames($picked),
            'cover' => Repo::cover($s), 'mailOk' => \Core\Mailer::ready()]);
    }

    public function send(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $err = Mail::invite($s, mb_substr(trim((string) ($r->post['note'] ?? '')), 0, 1000));
        return $err ? $this->to(Auswahl::ADMIN . '/' . $id, 'error', __('Die E-Mail konnte nicht gesendet werden: {err}', ['err' => $err]))
            : $this->to(Auswahl::ADMIN . '/' . $id, 'success', __('Einladung an {email} gesendet.', ['email' => $s['email']]));
    }

    public function status(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $to = (string) ($r->post['status'] ?? '');
        if (!in_array($to, Repo::STATUS, true)) throw new HttpException(422);
        Repo::update((int) $s['id'], ['status' => $to] + ($to === 'open' ? ['submitted_at' => null] : []));
        $msg = ['open' => __('Wieder geöffnet – die Auswahl kann geändert und erneut abgeschickt werden.'), 'submitted' => __('Als eingegangen markiert.'),
            'closed' => __('Abgeschlossen – der Link zeigt nur noch einen Hinweis.')][$to];
        return $this->to(Auswahl::ADMIN . '/' . $id, 'success', $msg);
    }

    public function renew(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        Repo::update((int) $s['id'], ['token' => Repo::newToken()]);
        return $this->to(Auswahl::ADMIN . '/' . $id, 'success', __('Neuer Link erzeugt – der alte funktioniert nicht mehr.'));
    }

    public function reset(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        app()->db->query('DELETE FROM auswahl_picks WHERE shoot_id = ?', [(int) $s['id']]);
        Repo::update((int) $s['id'], ['status' => 'open', 'submitted_at' => null, 'message' => '']);
        return $this->to(Auswahl::ADMIN . '/' . $id, 'success', __('Auswahl und Notizen gelöscht.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $files = (string) $s['pool'] !== '';
        Repo::delete((int) $s['id'], $files);
        return $this->to(Auswahl::ADMIN, 'success', $files ? __('Shooting „{title}“ samt Dateien gelöscht.', ['title' => $s['title']])
            : __('Shooting „{title}“ gelöscht. Die Dateien bleiben in der Mediathek.', ['title' => $s['title']]));
    }

    /** CSV: Nummer, Dateiname, ausgewählt, Notiz – alle Dateien des Shootings */
    public function csv(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $picks = Repo::picks((int) $s['id']);
        $rows = [];
        foreach (Repo::items($s) as $k => $m) {
            $p = $picks[(int) $m['id']] ?? ['picked' => false, 'note' => ''];
            $rows[] = [$k + 1, (string) ($m['original_name'] ?: basename((string) $m['file'])), $p['picked'] ? __('ja') : '', $p['note']];
        }
        $name = 'auswahl-' . (preg_replace('~[^a-z0-9]+~', '-', mb_strtolower((string) $s['title'])) ?: (string) $s['id']) . '.csv';
        return Response::csv($name, [__('Nr.'), __('Datei'), __('Ausgewählt'), __('Notiz')], $rows);
    }

    // ------------------------------------------------------------------ Dateien im eigenen Ordner (geschützter Pool)

    private const CHUNK = 2 * 1024 * 1024;

    private function poolShoot(string $id): array
    {
        $s = $this->shoot($id);
        if ((string) $s['pool'] === '' || !\Core\MediaPools::available((string) $s['pool'])) throw new HttpException(404);
        return $s;
    }

    private function tmp(array $user, string $uploadId): string
    {
        if (!preg_match('~^[a-zA-Z0-9-]{8,64}$~', $uploadId)) throw new HttpException(422);
        $dir = ROOT . '/storage/tmp/auswahl';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        return $dir . '/' . (int) $user['id'] . '-' . $uploadId;
    }

    /** POST …/upload/teil (chunk, upload_id, index, total, size) – ein Stück einer Datei */
    public function chunk(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $this->poolShoot($id);
        $path = $this->tmp($u, (string) ($r->post['upload_id'] ?? ''));
        $index = (int) ($r->post['index'] ?? -1);
        $total = (int) ($r->post['total'] ?? 0);
        $size = (int) ($r->post['size'] ?? 0);
        $file = $r->files['chunk'] ?? null;
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > self::CHUNK) return Response::fail(__('Teil-Upload fehlgeschlagen.'));
        if ($size > Media::maxBytes() || $total < 1 || $total > 2000) return Response::fail(__('Die Datei ist zu groß (höchstens {n} MB).', ['n' => (int) round(Media::maxBytes() / 1048576)]), 413);
        $meta = is_file("$path.json") ? (json_decode((string) file_get_contents("$path.json"), true) ?: []) : ['next' => 0];
        if ($index !== (int) $meta['next']) return Response::json(['ok' => false, 'error' => __('Reihenfolge der Teile stimmt nicht.'), 'expected' => $meta['next']], 409);
        file_put_contents("$path.part", (string) file_get_contents($file['tmp_name']), $index === 0 ? LOCK_EX : FILE_APPEND | LOCK_EX);
        if (filesize("$path.part") > Media::maxBytes()) { @unlink("$path.part"); @unlink("$path.json"); return Response::fail(__('Die Datei ist zu groß.'), 413); }
        file_put_contents("$path.json", json_encode(['next' => $index + 1, 'total' => $total, 'size' => $size]));
        return Response::json(['ok' => true]);
    }

    /** POST …/upload/fertig (upload_id, name) – Datei prüfen (nur Bilder und Videos) und in die Sammlung des Shootings übernehmen */
    public function finish(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $s = $this->poolShoot($id);
        $path = $this->tmp($u, (string) ($r->post['upload_id'] ?? ''));
        $meta = is_file("$path.json") ? (json_decode((string) file_get_contents("$path.json"), true) ?: []) : [];
        try {
            if (!is_file("$path.part") || ($meta['next'] ?? 0) !== ($meta['total'] ?? -1) || filesize("$path.part") !== (int) ($meta['size'] ?? -1)) {
                return Response::fail(__('Die Datei kam unvollständig an – bitte erneut hochladen.'));
            }
            $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file("$path.part") ?: '');
            if (!preg_match('~^(image/(jpeg|png|webp|avif|gif|heic|heif)|video/(mp4|webm|quicktime))$~', $mime)) {
                return Response::fail(__('Nur Fotos und Videos (JPEG, PNG, WebP, AVIF, HEIC, MP4, WebM, MOV).'));
            }
            $name = basename(str_replace('\\', '/', (string) ($r->post['name'] ?? 'datei')));
            [$m, $err] = Repo::inPool($s, fn() => Media::import("$path.part", $name, __('Foto {name} aus „{title}“', ['name' => pathinfo($name, PATHINFO_FILENAME), 'title' => $s['title']]), ['collection' => (int) $s['collection_id'], 'title' => pathinfo($name, PATHINFO_FILENAME)]));
        } finally {
            @unlink("$path.part");
            @unlink("$path.json");
        }
        if (!$m) return Response::fail((string) $err);
        Repo::update((int) $s['id'], []);
        return Response::json(['ok' => true, 'id' => (int) $m['id'], 'name' => $name, 'count' => count(Repo::items($s))]);
    }

    /** POST …/datei/{mid}/entfernen – Datei aus dem Ordner des Shootings löschen (samt Auswahl dazu) */
    public function removeFile(Request $r, string $id, string $mid): Response
    {
        $this->guard($r);
        $s = $this->poolShoot($id);
        if (!Repo::hasItem($s, (int) $mid)) throw new HttpException(404);
        Repo::inPool($s, fn() => Media::delete((int) $mid, true));
        app()->db->query('DELETE FROM auswahl_picks WHERE shoot_id = ? AND media_id = ?', [(int) $s['id'], (int) $mid]);
        return $this->to(Auswahl::ADMIN . '/' . $id . '#dateien', 'success', __('Datei entfernt.'));
    }

    /** POST …/sortieren – Reihenfolge nach Dateinamen (natürlich: DSC_2 vor DSC_10) */
    public function sort(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = $this->shoot($id);
        $items = Repo::items($s);
        usort($items, fn($a, $b) => strnatcasecmp((string) ($a['original_name'] ?: $a['file']), (string) ($b['original_name'] ?: $b['file'])));
        Repo::inPool($s, function () use ($s, $items): void {
            foreach ($items as $k => $m) Media::db()->query('UPDATE media_collection_items SET sort = ? WHERE collection_id = ? AND media_id = ?', [$k + 1, (int) $s['collection_id'], (int) $m['id']]);
        });
        return $this->to(Auswahl::ADMIN . '/' . $id . '#dateien', 'success', __('Nach Dateinamen sortiert.'));
    }

    // ------------------------------------------------------------------ Einstellungen

    public function settings(Request $r): Response
    {
        $this->guard($r);
        return $this->page('settings', ['title' => __('Einstellungen'), 'values' => ['notify' => (string) app()->settings->get('auswahl.notify', '')]]);
    }

    public function saveSettings(Request $r): Response
    {
        $this->guard($r);
        $raw = trim((string) ($r->post['f']['notify'] ?? ''));
        $bad = array_filter(array_map('trim', explode(',', $raw)), fn($e) => $e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($bad) return $this->page('settings', ['title' => __('Einstellungen'), 'values' => ['notify' => $raw], 'errors' => ['notify' => __('Ungültige Adresse: {list}', ['list' => implode(', ', $bad)])]], 422);
        app()->settings->set('auswahl.notify', $raw);
        return $this->to(Auswahl::ADMIN . '/einstellungen', 'success', __('Einstellungen gespeichert.'));
    }

    /** Vorschaubild für die Verwaltung (Bild oder Poster eines Videos) */
    public static function thumb(array $m, string $sizes = '12rem'): string
    {
        $p = str_starts_with((string) $m['mime'], 'video/') ? Media::posterFor($m) : $m;
        return $p ? Media::pictureOf($p, $sizes, ['alt' => '', 'fit' => false]) : '';
    }
}
