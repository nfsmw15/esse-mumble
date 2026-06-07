<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Zentraler AJAX-Handler
 * File: admin/ajax.php
 *
 * Wird von Plugin.php-Routen per require eingebunden.
 * $ajaxAction muss vor dem require gesetzt sein.
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

use Esse\Auth;

header('Content-Type: application/json; charset=utf-8');

// Hilfsfunktion: JSON ausgeben und beenden
function mbJson(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Hilfsfunktion: JSON-Body lesen
function mbJsonBody(): array
{
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// Hilfsfunktion: CSRF aus JSON-Body oder Header prüfen
function mbVerifyCsrf(array $body): bool
{
    // Token aus JSON-Body in $_POST injizieren damit Auth::verifyCsrf() greift
    $token = $body['csrf'] ?? $body['_csrf'] ?? null;
    if ($token !== null) {
        $_POST['_csrf'] = $token;
    }
    // Esse prüft $_POST['_csrf'] oder HTTP_X_CSRF_TOKEN Header
    return Auth::verifyCsrf();
}

$action = $ajaxAction ?? 'unknown';

// ── Widget-Embed (public) ──────────────────────────────────────────────────
if ($action === 'widget_embed') {
    $mumble = new \EsseMumble\MumbleRepository();
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') { mbJson(['ok' => false, 'error' => 'Kein Token']); }
    $srv = $mumble->getServerByWidget($token);
    if (!$srv) { mbJson(['ok' => false, 'error' => 'Widget nicht verfügbar']); }
    $agent = new \EsseMumble\MumbleAgent((string)$srv['agent_url'], (string)$srv['agent_token']);
    $res   = $agent->getViewer((string)$srv['container_id']);
    if (!$res['ok']) { mbJson(['ok' => false, 'error' => 'Server nicht erreichbar']); }
    $viewer = $res['data'];
    if (isset($viewer['channels'])) $viewer['channels']['name'] = (string)$srv['name'];
    mbJson(['ok' => true, 'channels' => $viewer['channels'] ?? null]);
}

if (!Auth::check()) {
    mbJson(['ok' => false, 'error' => 'Nicht eingeloggt']);
}

$mumble = new \EsseMumble\MumbleRepository();

// ── GET-Endpoints ──────────────────────────────────────────────────────────

if ($action === 'stats') {
    $sid = (int)($_GET['id'] ?? 0);
    mbJson($mumble->refreshStats($sid));
}

if ($action === 'dashboard_data') {
    if (!$mumble->canAdminAll() && !$mumble->isHostAdmin()) {
        mbJson(['ok' => false, 'error' => 'Keine Berechtigung']);
    }
    mbJson($mumble->getDashboardDataGrouped());
}

if ($action === 'dashboard_history') {
    if (!$mumble->canAdminAll() && !$mumble->isHostAdmin()) {
        mbJson(['ok' => false, 'error' => 'Keine Berechtigung']);
    }
    $range = (string)($_GET['range'] ?? '24h');
    $data  = $mumble->getStatsHistoryAll($range);
    mbJson(['ok' => true, 'data' => $data]);
}

if ($action === 'server_history') {
    $sid   = (int)($_GET['id'] ?? 0);
    $range = (string)($_GET['range'] ?? '24h');
    if (!$mumble->canManageServer($sid)) {
        mbJson(['ok' => false, 'error' => 'Keine Berechtigung']);
    }
    $data = $mumble->getStatsHistory($sid, $range);
    mbJson(['ok' => true, 'data' => $data]);
}

if ($action === 'live_users') {
    $sid = (int)($_GET['id'] ?? 0);
    mbJson($mumble->getLiveUsers($sid));
}

if ($action === 'viewer') {
    $sid    = (int)($_GET['id'] ?? 0);
    $viewer = $mumble->getViewer($sid);
    if ($viewer === null) {
        mbJson(['ok' => false, 'error' => 'Nicht verfügbar']);
    }
    mbJson(array_merge(['ok' => true], $viewer));
}

if ($action === 'user_search') {
    $term  = (string)($_GET['q'] ?? '');
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    if (strlen($term) < 2) { mbJson([]); }
    mbJson($mumble->searchUsers($term, $limit));
}

if ($action === 'host_user_search') {
    $term  = (string)($_GET['q'] ?? '');
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    if (strlen($term) < 2) { mbJson([]); }
    mbJson($mumble->searchUsers($term, $limit));
}

if ($action === 'channels') {
    $sid = (int)($_GET['id'] ?? 0);
    mbJson($mumble->getMumbleChannels($sid));
}

if ($action === 'bans') {
    $sid = (int)($_GET['id'] ?? 0);
    mbJson($mumble->getMumbleBans($sid));
}

if ($action === 'acl') {
    $sid       = (int)($_GET['id'] ?? 0);
    $channelId = (int)($_GET['channel_id'] ?? 0);
    mbJson($mumble->getChannelAcl($sid, $channelId));
}

if ($action === 'host_history') {
    $hid   = (int)($_GET['id'] ?? 0);
    $range = (string)($_GET['range'] ?? '24h');
    if (!$mumble->canSeeHost($hid)) {
        mbJson(['ok' => false, 'error' => 'Keine Berechtigung']);
    }
    $data = $mumble->getStatsHistoryForHost($hid, $range);
    mbJson(['ok' => true, 'data' => $data]);
}

// ── POST-Endpoints ─────────────────────────────────────────────────────────

if ($action === 'settings_save') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    unset($body['csrf'], $body['_csrf']);
    mbJson($mumble->updateMumbleSettingsLive($sid, $body));
}

if ($action === 'kick') {
    $sid     = (int)($_GET['id'] ?? 0);
    $body    = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $session = (int)($body['session'] ?? 0);
    $reason  = (string)($body['reason'] ?? '');
    mbJson($mumble->kickMumbleUser($sid, $session, $reason));
}

if ($action === 'update_user') {
    $sid     = (int)($_GET['id'] ?? 0);
    $body    = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $session = (int)($body['session'] ?? 0);

    $srv = $mumble->getServer($sid);
    if (!$srv || !$mumble->canManageServer($sid)) {
        mbJson(['ok' => false, 'error' => 'Keine Berechtigung']);
    }
    $agent = new \EsseMumble\MumbleAgent($srv['agent_url'], $srv['agent_token']);
    $data  = $body;
    unset($data['csrf'], $data['_csrf'], $data['session']);
    mbJson($agent->updateUser((string)$srv['container_id'], $session, $data));
}

if ($action === 'channel_add') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $name   = trim((string)($body['name'] ?? ''));
    $parent = (int)($body['parent'] ?? 0);
    mbJson($mumble->addMumbleChannel($sid, $name, $parent));
}

if ($action === 'channel_update') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $channelId = (int)($body['channel_id'] ?? 0);
    $data = $body;
    unset($data['csrf'], $data['_csrf'], $data['channel_id']);
    mbJson($mumble->updateMumbleChannel($sid, $channelId, $data));
}

if ($action === 'channel_delete') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $channelId = (int)($body['channel_id'] ?? 0);
    mbJson($mumble->removeMumbleChannel($sid, $channelId));
}

if ($action === 'bans_save') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $bans = (array)($body['bans'] ?? []);
    mbJson($mumble->setMumbleBans($sid, $bans));
}

if ($action === 'acl_save') {
    $sid  = (int)($_GET['id'] ?? 0);
    $body = mbJsonBody();
    if (!mbVerifyCsrf($body)) { mbJson(['ok' => false, 'error' => 'CSRF-Fehler']); }
    $channelId  = (int)($body['channel_id'] ?? 0);
    $inheritAcl = (bool)($body['inherit_acl'] ?? true);
    $aclEntries = (array)($body['acl'] ?? []);
    $groups     = (array)($body['groups'] ?? []);
    mbJson($mumble->setChannelAcl($sid, $channelId, $inheritAcl, $aclEntries, $groups));
}

// Fallback
mbJson(['ok' => false, 'error' => 'Unbekannte Aktion: '.$action]);
