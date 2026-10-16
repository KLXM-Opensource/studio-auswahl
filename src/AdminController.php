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

    public static function fields(bool $new): array
    {
        $langs = [];
        foreach (\Core\Lang::all() as $code => $l) $langs[$code] = is_array($l) ? (string) ($l['label'] ?? $code) : (string) $l;
        $f = [
            ['type' => 'heading', 'label' => __('Shooting')],
            ['name' => 'title', 'label' => __('Titel'), 'type' => 'text', 'required' => true, 'max' => 160, 'placeholder' => __('z. B. Hochzeit Anna & Ben')],
            ['name' => 'collection_id', 'label' => __('Bilder und Videos (Sammlung der Mediathek)'), 'type' => 'collection', 'required' => true, 'width' => 'half',
                'help' => __('Laden Sie die Dateien in der Mediathek in eine eigene Sammlung – Reihenfolge und Dateinamen übernimmt die Auswahl.')],
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
        return $this->page('edit', ['title' => __('Shooting bearbeiten'), 'shoot' => $s, 'values' => $s + ['allow_comments' => (bool) $s['allow_comments'], 'allow_download' => (bool) $s['allow_download']]]);
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
        [$v, $errors] = Fields::sanitize(self::fields($s === null), (array) ($r->post['f'] ?? []));
        $pin = trim((string) ($v['pin'] ?? ''));
        if (!Auswahl::validPin($pin)) $errors['pin'] = __('Die PIN braucht 4–12 Ziffern oder Buchstaben.');
        if (!$errors && !(int) ($v['collection_id'] ?? 0)) $errors['collection_id'] = __('Bitte eine Sammlung wählen.');
        if ((int) ($v['max_picks'] ?? 0) < 0) $errors['max_picks'] = __('Bitte 0 oder mehr eintragen.');
        if ($errors) return $this->page('edit', ['title' => $s ? __('Shooting bearbeiten') : __('Neues Shooting'), 'shoot' => $s, 'values' => $v, 'errors' => $errors], 422);
        $data = [
            'title' => (string) $v['title'], 'client' => (string) ($v['client'] ?? ''), 'email' => mb_strtolower((string) ($v['email'] ?? '')),
            'intro' => (string) ($v['intro'] ?? ''), 'collection_id' => (int) $v['collection_id'], 'shoot_date' => (string) ($v['shoot_date'] ?? ''),
            'max_picks' => max(0, (int) ($v['max_picks'] ?? 0)), 'deadline' => (string) ($v['deadline'] ?? ''), 'lang' => (string) ($v['lang'] ?? ''),
            'allow_comments' => empty($v['allow_comments']) ? 0 : 1, 'allow_download' => empty($v['allow_download']) ? 0 : 1,
        ];
        if ($pin !== '') $data['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
        elseif ($s && !empty($v['remove_pin'])) $data['pin_hash'] = '';
        if ($s) {
            Repo::update((int) $s['id'], $data);
            return $this->to(Auswahl::ADMIN . '/' . (int) $s['id'], 'success', __('Gespeichert.'));
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
        Repo::delete((int) $s['id']);
        return $this->to(Auswahl::ADMIN, 'success', __('Shooting „{title}“ gelöscht. Die Dateien bleiben in der Mediathek.', ['title' => $s['title']]));
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
