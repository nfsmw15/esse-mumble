<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Plugin Bootstrap
 * File: Plugin.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

namespace EsseMumble;

use Esse\Router;
use Esse\Hooks;

require_once __DIR__ . '/MumbleAgent.php';
require_once __DIR__ . '/MumbleRepository.php';

class Plugin extends \Esse\Plugin
{
    public function boot(): void
    {
        MumbleRepository::migrate();
        MumbleRepository::registerPermissions();

        $this->addAdminNav('Mumble Rechte', '/admin/mumble-permissions', 'headphones', 'admin/mumble-permissions');

        $this->registerPage('/mumble/servers',    'Mumble: Server',           'headphones', 'member');
        $this->registerPage('/mumble/new',        'Mumble: Neuer Server',     'headphones', 'admin|mumble_host_admin');
        $this->registerPage('/mumble/dashboard',  'Mumble: Dashboard',        'headphones', 'admin|mumble_host_admin');
        $this->registerPage('/mumble/hosts',      'Mumble: Hosts verwalten',  'headphones', 'admin');
        $this->registerPage('/mumble/quota',      'Mumble: Quotas verwalten', 'headphones', 'admin');

        $base = $this->basePath();

        // Helper: renders a template file wrapped in the active frontend theme
        $render = static function(string $file, string $title, array $vars = []): void {
            extract($vars, EXTR_SKIP);
            $esse_user = \Esse\Auth::user();
            $fakePage  = [
                'slug'       => ltrim((string)\parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'),
                'title'      => $title,
                'icon'       => 'headphones',
                'content'    => '',
                'type'       => 'standard',
                'visibility' => 'public',
                'status'     => 'published',
            ];
            ob_start();
            require $file;
            $content = ob_get_clean();
            if (Hooks::has('page.render')) {
                Hooks::fire('page.render', $fakePage, $content);
                return;
            }
            echo $content;
        };

        // ── Asset-Serving ────────────────────────────────────────────────────
        Router::get('/plugins/esse-mumble/assets/{file}', function(string $file) use ($base) {
            $path = $base . '/assets/' . basename($file);
            if (!file_exists($path)) { http_response_code(404); exit; }
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            header("Content-Type: {$mime}");
            readfile($path);
            exit;
        }, ['name' => 'esse-mumble.assets', 'auth' => 'public']);

        // ── Mumble-Seiten (Frontend) ─────────────────────────────────────────
        Router::get('/mumble/servers', fn() => $render($base . '/templates/servers.php', 'Mumble Server', ['viewAll' => true]),
            ['name' => 'mumble.servers', 'auth' => 'member']);

        Router::get('/mumble/new', fn() => $render($base . '/templates/server-new.php', 'Neuer Mumble-Server'),
            ['name' => 'mumble.new', 'auth' => 'member']);
        Router::post('/mumble/new', fn() => $render($base . '/templates/server-new.php', 'Neuer Mumble-Server'),
            ['name' => 'mumble.new.post', 'auth' => 'member']);

        Router::get('/mumble/edit/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-edit.php', 'Server bearbeiten', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.edit', 'auth' => 'member']);
        Router::post('/mumble/edit/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-edit.php', 'Server bearbeiten', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.edit.post', 'auth' => 'member']);

        Router::get('/mumble/logs/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-logs.php', 'Server Logs', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.logs', 'auth' => 'member']);

        Router::get('/mumble/config/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-config.php', 'Server Konfiguration', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.config', 'auth' => 'member']);
        Router::post('/mumble/config/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-config.php', 'Server Konfiguration', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.config.post', 'auth' => 'member']);

        Router::get('/mumble/channels/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-channels.php', 'Channels', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.channels', 'auth' => 'member']);

        Router::get('/mumble/bans/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-bans.php', 'Bans', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.bans', 'auth' => 'member']);

        Router::get('/mumble/acl/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-acl.php', 'ACL', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.acl', 'auth' => 'member']);

        Router::get('/mumble/server-stats/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-stats.php', 'Server Statistiken', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.server-stats', 'auth' => 'member']);

        Router::get('/mumble/widget-settings/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-widget.php', 'Widget Einstellungen', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.widget-settings', 'auth' => 'member']);
        Router::post('/mumble/widget-settings/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/server-widget.php', 'Widget Einstellungen', ['serverId' => (int)$id]);
        }, ['name' => 'mumble.widget-settings.post', 'auth' => 'member']);

        Router::get('/mumble/hosts', fn() => $render($base . '/templates/hosts.php', 'Mumble Hosts'),
            ['name' => 'mumble.hosts', 'auth' => 'member']);
        Router::post('/mumble/hosts', fn() => $render($base . '/templates/hosts.php', 'Mumble Hosts'),
            ['name' => 'mumble.hosts.post', 'auth' => 'member']);

        Router::get('/mumble/quota', fn() => $render($base . '/templates/quota.php', 'Mumble Quotas'),
            ['name' => 'mumble.quota', 'auth' => 'member']);
        Router::post('/mumble/quota', fn() => $render($base . '/templates/quota.php', 'Mumble Quotas'),
            ['name' => 'mumble.quota.post', 'auth' => 'member']);

        Router::get('/mumble/dashboard', fn() => $render($base . '/templates/dashboard.php', 'Mumble Dashboard'),
            ['name' => 'mumble.dashboard', 'auth' => 'member']);

        Router::get('/mumble/host-stats/{id}', function(string $id) use ($base, $render) {
            $render($base . '/templates/host-stats.php', 'Host Statistiken', ['hostId' => (int)$id]);
        }, ['name' => 'mumble.host-stats', 'auth' => 'member']);

        Router::get('/mumble/host-detail/{id}', function(string $id) use ($base, $render) {
            $hid    = (int)$id;
            $mumble = new \EsseMumble\MumbleRepository();
            $host   = $mumble->getHost($hid);
            $title  = $host ? 'Server: '.$host['name'] : 'Host-Server';
            $render($base . '/templates/servers.php', $title, ['hostId' => $hid]);
        }, ['name' => 'mumble.host-detail', 'auth' => 'member']);

        // ── AJAX-Endpoints ────────────────────────────────────────────────────
        $ajax = $base . '/templates/ajax.php';

        Router::get('/mumble/api/stats', function() use ($ajax) {
            $ajaxAction = 'stats'; require $ajax;
        }, ['name' => 'mumble.api.stats', 'auth' => 'public']);

        Router::get('/mumble/api/dashboard-data', function() use ($ajax) {
            $ajaxAction = 'dashboard_data'; require $ajax;
        }, ['name' => 'mumble.api.dashboard-data', 'auth' => 'public']);

        Router::get('/mumble/api/dashboard-history', function() use ($ajax) {
            $ajaxAction = 'dashboard_history'; require $ajax;
        }, ['name' => 'mumble.api.dashboard-history', 'auth' => 'public']);

        Router::get('/mumble/api/server-history', function() use ($ajax) {
            $ajaxAction = 'server_history'; require $ajax;
        }, ['name' => 'mumble.api.server-history', 'auth' => 'public']);

        Router::get('/mumble/api/live-users', function() use ($ajax) {
            $ajaxAction = 'live_users'; require $ajax;
        }, ['name' => 'mumble.api.live-users', 'auth' => 'public']);

        Router::get('/mumble/api/viewer', function() use ($ajax) {
            $ajaxAction = 'viewer'; require $ajax;
        }, ['name' => 'mumble.api.viewer', 'auth' => 'public']);

        Router::get('/mumble/api/user-search', function() use ($ajax) {
            $ajaxAction = 'user_search'; require $ajax;
        }, ['name' => 'mumble.api.user-search', 'auth' => 'public']);

        Router::get('/mumble/api/channels', function() use ($ajax) {
            $ajaxAction = 'channels'; require $ajax;
        }, ['name' => 'mumble.api.channels', 'auth' => 'public']);

        Router::get('/mumble/api/bans', function() use ($ajax) {
            $ajaxAction = 'bans'; require $ajax;
        }, ['name' => 'mumble.api.bans', 'auth' => 'public']);

        Router::get('/mumble/api/acl', function() use ($ajax) {
            $ajaxAction = 'acl'; require $ajax;
        }, ['name' => 'mumble.api.acl', 'auth' => 'public']);

        Router::get('/mumble/api/host-history', function() use ($ajax) {
            $ajaxAction = 'host_history'; require $ajax;
        }, ['name' => 'mumble.api.host-history', 'auth' => 'public']);

        Router::get('/mumble/api/host-user-search', function() use ($ajax) {
            $ajaxAction = 'host_user_search'; require $ajax;
        }, ['name' => 'mumble.api.host-user-search', 'auth' => 'public']);

        Router::post('/mumble/api/settings-save', function() use ($ajax) {
            $ajaxAction = 'settings_save'; require $ajax;
        }, ['name' => 'mumble.api.settings-save', 'auth' => 'public']);

        Router::post('/mumble/api/kick', function() use ($ajax) {
            $ajaxAction = 'kick'; require $ajax;
        }, ['name' => 'mumble.api.kick', 'auth' => 'public']);

        Router::post('/mumble/api/update-user', function() use ($ajax) {
            $ajaxAction = 'update_user'; require $ajax;
        }, ['name' => 'mumble.api.update-user', 'auth' => 'public']);

        Router::post('/mumble/api/channel-add', function() use ($ajax) {
            $ajaxAction = 'channel_add'; require $ajax;
        }, ['name' => 'mumble.api.channel-add', 'auth' => 'public']);

        Router::post('/mumble/api/channel-update', function() use ($ajax) {
            $ajaxAction = 'channel_update'; require $ajax;
        }, ['name' => 'mumble.api.channel-update', 'auth' => 'public']);

        Router::post('/mumble/api/channel-delete', function() use ($ajax) {
            $ajaxAction = 'channel_delete'; require $ajax;
        }, ['name' => 'mumble.api.channel-delete', 'auth' => 'public']);

        Router::post('/mumble/api/bans-save', function() use ($ajax) {
            $ajaxAction = 'bans_save'; require $ajax;
        }, ['name' => 'mumble.api.bans-save', 'auth' => 'public']);

        Router::post('/mumble/api/acl-save', function() use ($ajax) {
            $ajaxAction = 'acl_save'; require $ajax;
        }, ['name' => 'mumble.api.acl-save', 'auth' => 'public']);

        // ── Öffentliche Frontend-Seiten ───────────────────────────────────────
        Router::get('/mumble/widget', fn() => require $base . '/frontend/widget.php',
            ['name' => 'mumble.widget', 'auth' => 'public']);

        Router::get('/mumble/widget/embed', function() use ($ajax) {
            $ajaxAction = 'widget_embed'; require $ajax;
        }, ['name' => 'mumble.widget.embed', 'auth' => 'public']);

        Router::get('/mumble/banner', fn() => require $base . '/frontend/banner.php',
            ['name' => 'mumble.banner', 'auth' => 'public']);

        Router::get('/mumble/cron/collect', fn() => require $base . '/frontend/cron.php',
            ['name' => 'mumble.cron', 'auth' => 'public']);

        // ── Admin: Rechte-Verwaltung ──────────────────────────────────────────
        Router::get('/admin/mumble-permissions', fn() => require $base . '/admin/permissions.php',
            ['name' => 'mumble.admin.permissions', 'auth' => 'admin']);
        Router::post('/admin/mumble-permissions', fn() => require $base . '/admin/permissions.php',
            ['name' => 'mumble.admin.permissions.post', 'auth' => 'admin']);
    }

    public function install(): void {}

    public function uninstall(): void
    {
        MumbleRepository::drop();
    }
}
