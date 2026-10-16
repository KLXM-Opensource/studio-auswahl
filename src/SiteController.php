<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Csrf;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\RateLimiter;

/**
 * Website: /auswahl/{token} – das Shooting für Kundinnen und Kunden, ohne Konto. Optional mit PIN (Sitzung, Begrenzung je
 * Anschluss). Auswählen und Notizen per fetch (JSON, CSRF-Token der Sitzung), Abschicken als Formular. Seiten im Layout des
 * Kits, nie im Seiten-Cache, nicht in Suche und Sitemap (noindex).
 */
final class SiteController
{
    // ------------------------------------------------------------------ Darstellung

    private function page(string $view, array $vars, string $title, int $status = 200): Response
    {
        $theme = app()->theme;
        $page = ['id' => 0, 'title' => $title, 'slug' => ltrim(Auswahl::BASE, '/'), 'is_home' => 0, 'meta_description' => '', 'noindex' => 1, 'status' => 'published'];
        app()->currentPage = $page;
        $vars += ['title' => $title, 'notice' => app()->session->started() ? app()->session->takeFlash() : []];
        $content = '<link rel="stylesheet" href="' . e(Auswahl::asset('css/auswahl.css')) . '">'
            . \Core\Theme::capture(dirname(__DIR__) . '/views/site/' . $view . '.php', $vars);
        $seo = \Core\Seo::forError(200);
        $seo['title'] = $title;
        $html = $theme->render('layout', ['page' => $page, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null,
            'extraCss' => $theme->conditionalCss(['form'])]);
        return (new \Core\Http\Controllers\SiteController())->respond($html, false, $status, true)
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    private function message(string $title, string $text, int $status = 200): Response
    {
        return $this->page('message', ['text' => $text], $title, $status);
    }

    /** Shooting zum Link oder 404 (unbekannt, Erweiterung bzw. Funktion aus) */
    private function shoot(Request $r, string $token): array
    {
        if (!\Core\Extensions::isActive(Auswahl::NAME) || !\Core\Features::on(Auswahl::FEATURE)) throw new HttpException(404);
        (new \Core\Http\Controllers\FormController())->useLang($r);
        Auswahl::startSession($r);
        return Repo::byToken($token) ?? throw new HttpException(404);
    }

    private function limited(string $key, int $max, int $window): bool
    {
        $l = new RateLimiter(app()->db);
        $k = 'auswahl:' . hash_hmac('sha256', $key, app()->key());
        if ($l->tooMany($k, $max, $window)) return true;
        $l->hit($k);
        return false;
    }

    // ------------------------------------------------------------------ Seiten

    public function show(Request $r, string $token): Response
    {
        $s = $this->shoot($r, $token);
        if (!Auswahl::viewable($s)) return $this->message(lt('Auswahl abgeschlossen'), lt('Dieses Shooting ist abgeschlossen. Bei Fragen wenden Sie sich bitte an {name}.', ['name' => site_name()]), 410);
        if (!Auswahl::unlocked($s)) return $this->page('pin', ['shoot' => $s, 'error' => ''], (string) $s['title']);
        // Besuch zählen (höchstens einmal je Sitzung)
        $seen = app()->session->get('auswahl_seen');
        $seen = is_array($seen) ? $seen : [];
        if (empty($seen[(int) $s['id']])) {
            $seen[(int) $s['id']] = 1;
            app()->session->set('auswahl_seen', $seen);
            app()->db->query('UPDATE auswahl_shoots SET views = views + 1, last_seen_at = ? WHERE id = ?', [now(), (int) $s['id']]);
        }
        return $this->page('gallery', ['shoot' => $s, 'items' => Repo::items($s), 'picks' => Repo::picks((int) $s['id']),
            'editable' => Auswahl::editable($s), 'expired' => Auswahl::expired($s)], (string) $s['title']);
    }

    public function pin(Request $r, string $token): Response
    {
        $s = $this->shoot($r, $token);
        if (!Auswahl::viewable($s)) throw new HttpException(410);
        if (!Csrf::valid($r)) return $this->page('pin', ['shoot' => $s, 'error' => lt('Die Sitzung ist abgelaufen. Bitte erneut versuchen.')], (string) $s['title'], 419);
        if ($this->limited('pin|' . $r->ip() . '|' . $s['id'], Auswahl::PIN_TRIES, 900)) {
            return $this->page('pin', ['shoot' => $s, 'error' => lt('Zu viele Versuche. Bitte warten Sie 15 Minuten.')], (string) $s['title'], 429);
        }
        $pin = trim((string) ($r->post['pin'] ?? ''));
        if ($pin === '' || !password_verify($pin, (string) $s['pin_hash'])) {
            return $this->page('pin', ['shoot' => $s, 'error' => lt('Die PIN stimmt nicht.')], (string) $s['title'], 422);
        }
        app()->session->regenerate();
        Auswahl::unlock($s);
        return Response::redirect(Auswahl::link($s), 303);
    }

    /** POST …/wahl (media, picked=0|1) → JSON {ok, picked, count} */
    public function pick(Request $r, string $token): Response
    {
        [$s, $mid, $err] = $this->apiGuard($r, $token);
        if ($err) return $err;
        $want = ($r->post['picked'] ?? '') === '1';
        $count = Repo::pickedCount((int) $s['id']);
        $was = (Repo::picks((int) $s['id'])[$mid]['picked'] ?? false);
        if ($want && !$was && (int) $s['max_picks'] > 0 && $count >= (int) $s['max_picks']) {
            return Response::fail(lt('Sie haben bereits {n} Dateien ausgewählt – mehr sind in Ihrem Paket nicht enthalten. Nehmen Sie zuerst eine andere heraus.', ['n' => (int) $s['max_picks']]), 409, ['count' => $count]);
        }
        Repo::setPick((int) $s['id'], $mid, $want);
        return Response::json(['ok' => true, 'picked' => $want, 'count' => Repo::pickedCount((int) $s['id'])]);
    }

    /** POST …/notiz (media, note) → JSON {ok, note} */
    public function note(Request $r, string $token): Response
    {
        [$s, $mid, $err] = $this->apiGuard($r, $token);
        if ($err) return $err;
        if (!(int) $s['allow_comments']) return Response::fail(lt('Notizen sind für dieses Shooting nicht vorgesehen.'), 403);
        $note = mb_substr(trim(strip_tags((string) ($r->post['note'] ?? ''))), 0, Auswahl::NOTE_MAX);
        Repo::setPick((int) $s['id'], $mid, null, $note);
        return Response::json(['ok' => true, 'note' => $note]);
    }

    /** @return array{0: array, 1: int, 2: ?Response} */
    private function apiGuard(Request $r, string $token): array
    {
        $s = $this->shoot($r, $token);
        if (!Csrf::valid($r)) return [$s, 0, Response::fail(lt('Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.'), 419)];
        if (!Auswahl::unlocked($s)) return [$s, 0, Response::fail(lt('Bitte geben Sie zuerst die PIN ein.'), 403)];
        if (!Auswahl::editable($s)) return [$s, 0, Response::fail(lt('Die Auswahl ist abgeschlossen und kann nicht mehr geändert werden.'), 409)];
        if ($this->limited('api|' . $r->ip(), 600, 600)) return [$s, 0, Response::fail(lt('Zu viele Änderungen in kurzer Zeit. Bitte kurz warten.'), 429)];
        $mid = (int) ($r->post['media'] ?? 0);
        if (!$mid || !Repo::hasItem($s, $mid)) return [$s, 0, Response::fail(lt('Diese Datei gehört nicht zum Shooting.'), 404)];
        return [$s, $mid, null];
    }

    /** POST …/abschicken (message) – Auswahl festhalten, Hinweis an das Studio, Bestätigung an die Kundin bzw. den Kunden */
    public function submit(Request $r, string $token): Response
    {
        $s = $this->shoot($r, $token);
        if (!Csrf::valid($r) || !Auswahl::unlocked($s)) return Response::redirect(Auswahl::link($s), 303);
        if (!Auswahl::editable($s)) return Response::redirect(Auswahl::link($s), 303);
        $count = Repo::pickedCount((int) $s['id']);
        if ($count === 0) {
            app()->session->flash('error', lt('Bitte wählen Sie mindestens eine Datei aus.'));
            return Response::redirect(Auswahl::link($s), 303);
        }
        $msg = mb_substr(trim(strip_tags((string) ($r->post['message'] ?? ''))), 0, 2000);
        Repo::update((int) $s['id'], ['status' => 'submitted', 'submitted_at' => now(), 'message' => $msg]);
        $s = Repo::find((int) $s['id']);
        try { Mail::submitted($s); } catch (\Throwable $e) { error_log('[auswahl] Mail: ' . $e->getMessage()); }
        if (class_exists(\Core\Push\Push::class)) {
            try {
                \Core\Push\Push::notifyUsers(Auswahl::PERM, fn(): array => ['title' => __('Auswahl eingegangen'),
                    'body' => __('{title}: {n} Dateien ausgewählt', ['title' => $s['title'], 'n' => $count]), 'url' => Auswahl::ADMIN . '/' . (int) $s['id']], 'auswahl.submitted');
            } catch (\Throwable) {}
        }
        app()->session->flash('success', lt('Vielen Dank – Ihre Auswahl ist eingegangen.'));
        return Response::redirect(Auswahl::link($s), 303);
    }

    /** GET …/datei/{id} – Originaldatei herunterladen (nur wenn das Studio es erlaubt) */
    public function download(Request $r, string $token, string $id): Response
    {
        $s = $this->shoot($r, $token);
        if (!(int) $s['allow_download'] || !Auswahl::viewable($s) || !Auswahl::unlocked($s)) throw new HttpException(404);
        $mid = (int) $id;
        if (!Repo::hasItem($s, $mid) || !($m = Media::find($mid))) throw new HttpException(404);
        $file = Media::localFile($m);
        if (!is_file($file)) throw new HttpException(404);
        $name = (string) preg_replace('~[^A-Za-z0-9._-]+~', '-', (string) ($m['original_name'] ?: basename($file))) ?: 'datei';
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . ((string) $m['mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        readfile($file);
        return Response::alreadySent();
    }
}
