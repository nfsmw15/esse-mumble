<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – MumbleRepository
 * File: MumbleRepository.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

namespace EsseMumble;

use Esse\Auth;
use Esse\DB;
use Esse\Crypto;

class MumbleRepository
{
    /* ========== Migration ========== */

    public static function migrate(): void
    {
        $th  = DB::table('mumble_host');
        $ts  = DB::table('mumble_server');
        $tsm = DB::table('mumble_server_members');
        $tl  = DB::table('mumble_log');
        $tq  = DB::table('mumble_quota');
        $tst = DB::table('mumble_stats');
        $tse = DB::table('mumble_settings');
        $tha = DB::table('mumble_host_admin');

        DB::query("CREATE TABLE IF NOT EXISTS `{$th}` (
            `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`          VARCHAR(64)   NOT NULL,
            `hostname`      VARCHAR(255)  NOT NULL,
            `agent_url`     VARCHAR(255)  NOT NULL,
            `agent_token`   VARCHAR(255)  NOT NULL,
            `port_min`      INT UNSIGNED  NOT NULL DEFAULT 64738,
            `port_max`      INT UNSIGNED  NOT NULL DEFAULT 64838,
            `max_servers`   INT UNSIGNED  NOT NULL DEFAULT 20,
            `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
            `last_seen`     DATETIME      DEFAULT NULL,
            `latest_image`  VARCHAR(128)  DEFAULT NULL,
            `note`          TEXT          DEFAULT NULL,
            `created_at`    DATETIME      NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$ts}` (
            `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `host_id`            INT UNSIGNED  NOT NULL,
            `owner_user_id`      INT UNSIGNED  NOT NULL,
            `container_id`       VARCHAR(64)   DEFAULT NULL,
            `name`               VARCHAR(64)   NOT NULL,
            `port`               INT UNSIGNED  NOT NULL,
            `password`           VARCHAR(255)  DEFAULT NULL,
            `max_users`          INT UNSIGNED  NOT NULL DEFAULT 10,
            `welcome_text`       TEXT          DEFAULT NULL,
            `status`             ENUM('stopped','running','error','creating') NOT NULL DEFAULT 'creating',
            `last_status`        DATETIME      DEFAULT NULL,
            `stats_online`       INT UNSIGNED  NOT NULL DEFAULT 0,
            `stats_uptime`       INT UNSIGNED  NOT NULL DEFAULT 0,
            `superuser_password` VARCHAR(255)  DEFAULT NULL,
            `widget_token`       VARCHAR(64)   DEFAULT NULL,
            `widget_public`      TINYINT(1)    NOT NULL DEFAULT 0,
            `widget_refresh`     SMALLINT      NOT NULL DEFAULT 30,
            `created_at`         DATETIME      NOT NULL,
            `updated_at`         DATETIME      DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_host`  (`host_id`),
            KEY `idx_owner` (`owner_user_id`),
            UNIQUE KEY `uniq_host_port` (`host_id`,`port`),
            UNIQUE KEY `uniq_widget_token` (`widget_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tsm}` (
            `server_id` INT UNSIGNED NOT NULL,
            `user_id`   INT UNSIGNED NOT NULL,
            `added_at`  DATETIME     NOT NULL DEFAULT NOW(),
            PRIMARY KEY (`server_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tl}` (
            `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `server_id`   INT UNSIGNED  DEFAULT NULL,
            `user_id`     INT UNSIGNED  NOT NULL,
            `action`      VARCHAR(32)   NOT NULL,
            `details`     TEXT          DEFAULT NULL,
            `success`     TINYINT(1)    NOT NULL DEFAULT 1,
            `created_at`  DATETIME      NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_server` (`server_id`),
            KEY `idx_user`   (`user_id`),
            KEY `idx_date`   (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tq}` (
            `role`          VARCHAR(32)   NOT NULL,
            `max_servers`   INT UNSIGNED  NOT NULL DEFAULT 1,
            `max_users_cap` INT UNSIGNED  NOT NULL DEFAULT 25,
            `can_create`    TINYINT(1)    NOT NULL DEFAULT 1,
            PRIMARY KEY (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        // Standard-Quotas
        DB::query("INSERT INTO `{$tq}` (role, max_servers, max_users_cap, can_create) VALUES
            ('member', 1, 25, 1),
            ('author', 2, 50, 1),
            ('editor', 5, 100, 1)
            ON DUPLICATE KEY UPDATE role = role", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tst}` (
            `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `server_id`    INT UNSIGNED     NOT NULL,
            `ts`           INT UNSIGNED     NOT NULL,
            `users_online` SMALLINT         NOT NULL DEFAULT 0,
            `max_users`    SMALLINT         NOT NULL DEFAULT 0,
            `bandwidth`    INT              NOT NULL DEFAULT 0,
            `cpu`          FLOAT            NOT NULL DEFAULT 0,
            `ram_mb`       INT              NOT NULL DEFAULT 0,
            `ping_avg`     FLOAT            NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_srv_ts` (`server_id`, `ts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tse}` (
            `key`   VARCHAR(64)  NOT NULL,
            `value` TEXT         NOT NULL DEFAULT '',
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        DB::query("CREATE TABLE IF NOT EXISTS `{$tha}` (
            `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `host_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_host_user` (`host_id`, `user_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
    }

    public static function registerPermissions(): void
    {
        $tp = DB::table('permissions');
        $perms = [
            ['mumble_view',       'Mumble: Ansehen',               'Darf die Mumble-Serverübersicht sehen'],
            ['mumble_admin',      'Mumble: Fremdserver verwalten', 'Darf alle Mumble-Server verwalten'],
            ['mumble_hosts',      'Mumble: Hosts verwalten',       'Darf Mumble-Hosts anlegen/bearbeiten'],
            ['mumble_quota',      'Mumble: Quotas verwalten',      'Darf Quota-Regeln pro Rolle bearbeiten'],
            ['mumble_host_admin', 'Mumble: Host-Admin',            'Darf als Host-Admin einen Mumble-Host verwalten'],
        ];
        foreach ($perms as [$slug, $label, $desc]) {
            DB::query(
                "INSERT INTO `{$tp}` (slug, label, description) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description)",
                [$slug, $label, $desc]
            );
        }
    }

    public static function drop(): void
    {
        $tables = [
            'mumble_host_admin', 'mumble_stats', 'mumble_settings',
            'mumble_log', 'mumble_quota', 'mumble_server_members',
            'mumble_server', 'mumble_host',
        ];
        foreach ($tables as $t) {
            DB::query("DROP TABLE IF EXISTS `".DB::table($t)."`", []);
        }
    }

    /* ========== Berechtigungen ========== */

    public function canView(): bool         { return Auth::check(); }
    public function canAdminAll(): bool     { return Auth::meetsRole('admin') || Auth::can('mumble_admin'); }
    public function canManageHosts(): bool  { return Auth::meetsRole('admin') || Auth::can('mumble_hosts'); }
    public function canManageQuotas(): bool { return Auth::meetsRole('admin') || Auth::can('mumble_quota'); }
    public function isHostAdmin(): bool     { return !$this->canAdminAll() && Auth::can('mumble_host_admin'); }

    public static function setUserPermission(int $userId, string $perm, bool $granted): void
    {
        $tup = DB::table('user_permissions');
        if ($granted) {
            DB::query(
                "INSERT INTO `{$tup}` (user_id, permission_slug, granted) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE granted = 1",
                [$userId, $perm]
            );
        } else {
            DB::query(
                "DELETE FROM `{$tup}` WHERE user_id = ? AND permission_slug = ?",
                [$userId, $perm]
            );
        }
    }

    public static function getHostAdminMap(): array
    {
        $tha = DB::table('mumble_host_admin');
        $tu  = DB::table('users');
        $rows = DB::fetchAll(
            "SELECT ha.host_id, ha.user_id, u.display_name
               FROM `{$tha}` ha
               JOIN `{$tu}` u ON u.id = ha.user_id
              ORDER BY ha.host_id, u.display_name",
            []
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['host_id']][] = ['user_id' => (int)$r['user_id'], 'display_name' => $r['display_name']];
        }
        return $map;
    }

    public function canCreate(): bool
    {
        if (!Auth::check()) return false;
        if ($this->canAdminAll()) return true;
        if ($this->isHostAdmin()) return !empty($this->getAdminHostIds());
        return false;
    }

    public function getAdminHostIds(): array
    {
        $uid  = (int)Auth::id();
        $tha  = DB::table('mumble_host_admin');
        $rows = DB::fetchAll("SELECT host_id FROM `{$tha}` WHERE user_id = ?", [$uid]) ?: [];
        return array_map('intval', array_column($rows, 'host_id'));
    }

    public function canSeeHost(int $hostId): bool
    {
        if ($this->canAdminAll()) return true;
        if ($this->isHostAdmin()) return in_array($hostId, $this->getAdminHostIds());
        return false;
    }

    public function canManageServer(int $serverId): bool
    {
        if ($this->canAdminAll()) return true;
        $uid = (int)Auth::id();
        $srv = $this->getServer($serverId);
        if (!$srv) return false;
        if ($this->isHostAdmin() && in_array((int)$srv['host_id'], $this->getAdminHostIds())) return true;
        if ((int)$srv['owner_user_id'] === $uid) return true;
        return $this->isMember($serverId, $uid);
    }

    public function isOwner(int $serverId): bool
    {
        if ($this->canAdminAll()) return true;
        $uid = (int)Auth::id();
        $srv = $this->getServer($serverId);
        if (!$srv) return false;
        if ($this->isHostAdmin() && in_array((int)$srv['host_id'], $this->getAdminHostIds())) return true;
        return (int)$srv['owner_user_id'] === $uid;
    }

    /* ========== Quota ========== */

    public function getQuotaForRole(string $role): array
    {
        $tq = DB::table('mumble_quota');
        $row = DB::fetch("SELECT * FROM `{$tq}` WHERE role = ? LIMIT 1", [$role]);
        if (!$row) {
            return ['role' => $role, 'max_servers' => 0, 'max_users_cap' => 25, 'can_create' => 0];
        }
        return $row;
    }

    public function saveQuota(string $role, int $maxServers, int $maxUsersCap, bool $canCreate): void
    {
        if (!$this->canManageQuotas()) throw new \RuntimeException('Keine Berechtigung');
        $tq = DB::table('mumble_quota');
        DB::query(
            "INSERT INTO `{$tq}` (role, max_servers, max_users_cap, can_create)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE max_servers=VALUES(max_servers),
             max_users_cap=VALUES(max_users_cap), can_create=VALUES(can_create)",
            [$role, $maxServers, $maxUsersCap, $canCreate ? 1 : 0]
        );
    }

    public function getAllRanks(): array
    {
        return [
            ['id' => 'member', 'name' => 'Member'],
            ['id' => 'author', 'name' => 'Author'],
            ['id' => 'editor', 'name' => 'Editor'],
        ];
    }

    /* ========== Hosts ========== */

    public function listHosts(bool $activeOnly = false): array
    {
        $th  = DB::table('mumble_host');
        $sql = "SELECT * FROM `{$th}`";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        $rows = DB::fetchAll($sql, []) ?: [];
        return array_map(fn($row) => $this->decryptRow($row, ['agent_token']), $rows);
    }

    public function getHost(int $id): ?array
    {
        $th  = DB::table('mumble_host');
        $row = DB::fetch("SELECT * FROM `{$th}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->decryptRow($row, ['agent_token']) : null;
    }

    public function saveHost(array $data, ?int $id = null): int
    {
        if (!$this->canManageHosts()) {
            throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        }
        $th = DB::table('mumble_host');
        $params = [
            substr((string)($data['name'] ?? ''), 0, 64),
            substr((string)($data['hostname'] ?? ''), 0, 255),
            substr((string)($data['agent_url'] ?? ''), 0, 255),
            Crypto::encrypt(substr((string)($data['agent_token'] ?? ''), 0, 128)),
            (int)($data['port_min'] ?? 64738),
            (int)($data['port_max'] ?? 64838),
            (int)($data['max_servers'] ?? 20),
            !empty($data['is_active']) ? 1 : 0,
            (string)($data['note'] ?? ''),
        ];

        if ($id === null) {
            DB::query(
                "INSERT INTO `{$th}` (name, hostname, agent_url, agent_token, port_min, port_max,
                 max_servers, is_active, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                $params
            );
            return (int)DB::connection()->lastInsertId();
        }

        $params[] = $id;
        DB::query(
            "UPDATE `{$th}` SET name=?, hostname=?, agent_url=?, agent_token=?,
             port_min=?, port_max=?, max_servers=?, is_active=?, note=? WHERE id=?",
            $params
        );
        return $id;
    }

    public function deleteHost(int $id): bool
    {
        if (!$this->canManageHosts()) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        $th = DB::table('mumble_host');
        $ts = DB::table('mumble_server');
        $cnt = (int)DB::value("SELECT COUNT(*) FROM `{$ts}` WHERE host_id = ?", [$id]);
        if ($cnt > 0) throw new \RuntimeException('Host hat noch aktive Server und kann nicht gelöscht werden.');
        DB::query("DELETE FROM `{$th}` WHERE id = ?", [$id]);
        return true;
    }

    public function touchHostLastSeen(int $hostId): void
    {
        $th = DB::table('mumble_host');
        DB::query("UPDATE `{$th}` SET last_seen = NOW() WHERE id = ?", [$hostId]);
    }

    public function updateHostLatestImage(int $hostId, string $latestImage): void
    {
        $th = DB::table('mumble_host');
        DB::query("UPDATE `{$th}` SET latest_image = ? WHERE id = ?", [$latestImage, $hostId]);
    }

    public function updateHostImage(int $hostId, string $image): array
    {
        $allowed = $this->canManageHosts() ||
                   ($this->isHostAdmin() && in_array($hostId, $this->getAdminHostIds()));
        if (!$allowed) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $host = $this->getHost($hostId);
        if (!$host) return ['ok' => false, 'error' => 'Host nicht gefunden'];
        $agent = new MumbleAgent((string)$host['agent_url'], (string)$host['agent_token'], 15);
        return $agent->updateImage($image);
    }

    public function getHostAdminUsers(int $hostId): array
    {
        $tha = DB::table('mumble_host_admin');
        $tu  = DB::table('users');
        return DB::fetchAll(
            "SELECT u.id, u.display_name FROM `{$tha}` ha
              JOIN `{$tu}` u ON u.id = ha.user_id
             WHERE ha.host_id = ? ORDER BY u.display_name",
            [$hostId]
        ) ?: [];
    }

    public function addHostAdmin(int $hostId, int $userId): void
    {
        if (!$this->canManageHosts()) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        $tha = DB::table('mumble_host_admin');
        DB::query("INSERT IGNORE INTO `{$tha}` (host_id, user_id) VALUES (?, ?)", [$hostId, $userId]);
    }

    public function removeHostAdmin(int $hostId, int $userId): void
    {
        if (!$this->canManageHosts()) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        $tha = DB::table('mumble_host_admin');
        DB::query("DELETE FROM `{$tha}` WHERE host_id = ? AND user_id = ?", [$hostId, $userId]);
    }

    public function importServersFromAgent(int $hostId, int $ownerUserId): array
    {
        if (!$this->canManageHosts() && !($this->isHostAdmin() && in_array($hostId, $this->getAdminHostIds()))) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }
        $host = $this->getHost($hostId);
        if (!$host) return ['ok' => false, 'error' => 'Host nicht gefunden'];

        $agent = new MumbleAgent((string)$host['agent_url'], (string)$host['agent_token'], 15);
        $res   = $agent->listServers();
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Agent nicht erreichbar'];
        }

        $agentServers = (array)($res['data']['servers'] ?? []);
        if (empty($agentServers)) {
            return ['ok' => true, 'imported' => 0, 'skipped' => 0];
        }

        $ts = DB::table('mumble_server');
        $existingCids  = array_flip(array_column(
            DB::fetchAll("SELECT container_id FROM `{$ts}` WHERE host_id = ? AND container_id IS NOT NULL", [$hostId]) ?: [],
            'container_id'
        ));
        $existingPorts = array_flip(array_map('intval', array_column(
            DB::fetchAll("SELECT port FROM `{$ts}` WHERE host_id = ?", [$hostId]) ?: [],
            'port'
        )));

        $imported = 0; $skipped = 0;
        foreach ($agentServers as $srv) {
            $cid  = (string)($srv['container_id'] ?? '');
            $port = (int)($srv['port'] ?? 0);
            if ($cid === '' || $port === 0) { $skipped++; continue; }
            if (isset($existingCids[$cid]) || isset($existingPorts[$port])) { $skipped++; continue; }

            $name = trim((string)preg_replace('/[^A-Za-z0-9 _\-.]/u', '', (string)($srv['name'] ?? '')));
            if ($name === '') $name = 'Importiert-'.$port;

            $status = in_array((string)($srv['status'] ?? ''), ['running','stopped','error','creating'], true)
                ? (string)$srv['status'] : 'stopped';

            DB::query(
                "INSERT INTO `{$ts}` (host_id, owner_user_id, container_id, name, port, password,
                 max_users, welcome_text, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$hostId, $ownerUserId, $cid, substr($name, 0, 64), $port,
                 (string)($srv['password'] ?? ''), max(1, (int)($srv['max_users'] ?? 10)),
                 (string)($srv['welcome_text'] ?? ''), $status]
            );
            $existingCids[$cid]   = true;
            $existingPorts[$port] = true;
            $imported++;
        }

        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    /* ========== Server ========== */

    public function listServersByOwner(int $userId): array
    {
        $ts  = DB::table('mumble_server');
        $th  = DB::table('mumble_host');
        $tu  = DB::table('users');
        $tsm = DB::table('mumble_server_members');
        return DB::fetchAll(
            "SELECT s.*, h.name AS host_name, h.hostname, h.latest_image AS host_latest_image,
                    u.display_name AS owner_name,
                    GROUP_CONCAT(mu.display_name ORDER BY mu.display_name SEPARATOR ', ') AS member_names
               FROM `{$ts}` s
               JOIN `{$th}` h ON h.id = s.host_id
          LEFT JOIN `{$tu}` u ON u.id = s.owner_user_id
          LEFT JOIN `{$tsm}` sm ON sm.server_id = s.id
          LEFT JOIN `{$tu}` mu ON mu.id = sm.user_id
              WHERE s.owner_user_id = ?
                 OR s.id IN (SELECT server_id FROM `{$tsm}` WHERE user_id = ?)
              GROUP BY s.id
              ORDER BY s.owner_user_id = ? DESC, s.created_at DESC",
            [$userId, $userId, $userId]
        ) ?: [];
    }

    public function listServersByHostAdmin(int $userId): array
    {
        $hostIds = $this->getAdminHostIds();
        if (empty($hostIds)) return [];
        $in  = implode(',', $hostIds);
        $ts  = DB::table('mumble_server');
        $th  = DB::table('mumble_host');
        $tu  = DB::table('users');
        $tsm = DB::table('mumble_server_members');
        return DB::fetchAll(
            "SELECT s.*, h.name AS host_name, h.hostname, h.latest_image AS host_latest_image,
                    u.display_name AS owner_name,
                    GROUP_CONCAT(mu.display_name ORDER BY mu.display_name SEPARATOR ', ') AS member_names
               FROM `{$ts}` s
               JOIN `{$th}` h ON h.id = s.host_id
          LEFT JOIN `{$tu}` u ON u.id = s.owner_user_id
          LEFT JOIN `{$tsm}` sm ON sm.server_id = s.id
          LEFT JOIN `{$tu}` mu ON mu.id = sm.user_id
              WHERE s.host_id IN ($in)
              GROUP BY s.id
              ORDER BY s.created_at DESC",
            []
        ) ?: [];
    }

    public function listAllServers(): array
    {
        $ts  = DB::table('mumble_server');
        $th  = DB::table('mumble_host');
        $tu  = DB::table('users');
        $tsm = DB::table('mumble_server_members');
        return DB::fetchAll(
            "SELECT s.*, h.name AS host_name, h.hostname, h.latest_image AS host_latest_image,
                    u.display_name AS owner_name,
                    GROUP_CONCAT(mu.display_name ORDER BY mu.display_name SEPARATOR ', ') AS member_names
               FROM `{$ts}` s
               JOIN `{$th}` h ON h.id = s.host_id
          LEFT JOIN `{$tu}` u ON u.id = s.owner_user_id
          LEFT JOIN `{$tsm}` sm ON sm.server_id = s.id
          LEFT JOIN `{$tu}` mu ON mu.id = sm.user_id
              GROUP BY s.id
              ORDER BY s.created_at DESC",
            []
        ) ?: [];
    }

    public function getServer(int $id): ?array
    {
        $ts = DB::table('mumble_server');
        $th = DB::table('mumble_host');
        $row = DB::fetch(
            "SELECT s.*, h.agent_url, h.agent_token, h.name AS host_name, h.hostname
               FROM `{$ts}` s
               JOIN `{$th}` h ON h.id = s.host_id
              WHERE s.id = ? LIMIT 1",
            [$id]
        );
        return $row ? $this->decryptRow($row, ['agent_token', 'superuser_password']) : null;
    }

    public function getServersForHost(int $hostId): array
    {
        $ts  = DB::table('mumble_server');
        $th  = DB::table('mumble_host');
        $tu  = DB::table('users');
        $tsm = DB::table('mumble_server_members');
        return DB::fetchAll(
            "SELECT s.*, h.name AS host_name, h.hostname, h.latest_image AS host_latest_image,
                    u.display_name AS owner_name,
                    GROUP_CONCAT(mu.display_name ORDER BY mu.display_name SEPARATOR ', ') AS member_names
               FROM `{$ts}` s
               JOIN `{$th}` h ON h.id = s.host_id
          LEFT JOIN `{$tu}` u ON u.id = s.owner_user_id
          LEFT JOIN `{$tsm}` sm ON sm.server_id = s.id
          LEFT JOIN `{$tu}` mu ON mu.id = sm.user_id
              WHERE s.host_id = ?
              GROUP BY s.id
              ORDER BY s.created_at DESC",
            [$hostId]
        ) ?: [];
    }

    public function countServersByOwner(int $userId): int
    {
        $ts = DB::table('mumble_server');
        return (int)DB::value("SELECT COUNT(*) FROM `{$ts}` WHERE owner_user_id = ?", [$userId]);
    }

    public function findFreePort(int $hostId, int $min, int $max): ?int
    {
        $ts   = DB::table('mumble_server');
        $used = array_flip(array_map('intval', array_column(
            DB::fetchAll("SELECT port FROM `{$ts}` WHERE host_id = ? AND port BETWEEN ? AND ?", [$hostId, $min, $max]) ?: [],
            'port'
        )));
        for ($p = $min; $p <= $max; $p++) {
            if (!isset($used[$p])) return $p;
        }
        return null;
    }

    public function createServer(array $data): int
    {
        if (!$this->canCreate()) {
            throw new \RuntimeException('Keine Berechtigung zum Erstellen von Servern');
        }

        $uid   = (int)Auth::id();
        $quota = $this->getQuotaForRole(Auth::role());

        if (!$this->canAdminAll()) {
            if ($this->countServersByOwner($uid) >= (int)$quota['max_servers']) {
                throw new \RuntimeException('Server-Kontingent für deine Rolle erreicht.');
            }
        }

        $hostId = (int)($data['host_id'] ?? 0);
        $host   = $this->getHost($hostId);
        if (!$host || !$host['is_active']) {
            throw new \RuntimeException('Host ist nicht verfügbar.');
        }

        $rawMax   = (int)($data['max_users'] ?? 10);
        $maxUsers = $this->canAdminAll()
            ? max(1, $rawMax)
            : max(1, min($rawMax, (int)$quota['max_users_cap']));
        $name = trim((string)preg_replace('/[^A-Za-z0-9 _\-.]/u', '', (string)($data['name'] ?? '')));
        if ($name === '') throw new \RuntimeException('Ungültiger Server-Name.');

        $port = $this->findFreePort($hostId, (int)$host['port_min'], (int)$host['port_max']);
        if ($port === null) throw new \RuntimeException('Kein freier Port auf diesem Host verfügbar.');

        $ts = DB::table('mumble_server');
        DB::query(
            "INSERT INTO `{$ts}` (host_id, owner_user_id, name, port, password, max_users,
             welcome_text, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'creating', NOW())",
            [$hostId, $uid, $name, $port, (string)($data['password'] ?? ''),
             $maxUsers, (string)($data['welcome_text'] ?? '')]
        );
        $serverId = (int)DB::connection()->lastInsertId();

        // Verbindung testen (wait_timeout)
        try { DB::connection()->query('SELECT 1'); } catch (\PDOException $e) { DB::connect(); }

        $agent = new MumbleAgent($host['agent_url'], $host['agent_token']);
        $res = $agent->createServer([
            'name'         => $name,
            'port'         => $port,
            'password'     => (string)($data['password'] ?? ''),
            'max_users'    => $maxUsers,
            'welcome_text' => (string)($data['welcome_text'] ?? ''),
            'external_id'  => $serverId,
        ]);

        try { DB::connection()->query('SELECT 1'); } catch (\PDOException $e) { DB::connect(); }

        if (!$res['ok']) {
            $this->setStatus($serverId, 'error');
            $this->log($serverId, $uid, 'create', 'agent: '.(string)$res['error'], false);
            throw new \RuntimeException('Agent-Fehler: '.$res['error']);
        }

        $cid  = (string)($res['data']['container_id'] ?? '');
        $supw = (string)($res['data']['superuser_password'] ?? '');
        DB::query(
            "UPDATE `{$ts}` SET container_id = ?, status = 'running',
             superuser_password = ?, updated_at = NOW(), last_status = NOW() WHERE id = ?",
            [$cid, $supw !== '' ? Crypto::encrypt($supw) : null, $serverId]
        );

        $this->touchHostLastSeen($hostId);
        $this->log($serverId, $uid, 'create', 'port='.$port);
        return $serverId;
    }

    public function setStatus(int $serverId, string $status): void
    {
        $ts = DB::table('mumble_server');
        DB::query(
            "UPDATE `{$ts}` SET status = ?, last_status = NOW(), updated_at = NOW() WHERE id = ?",
            [$status, $serverId]
        );
    }

    public function updateStats(int $serverId, int $online, int $uptime): void
    {
        $ts = DB::table('mumble_server');
        DB::query(
            "UPDATE `{$ts}` SET stats_online = ?, stats_uptime = ?, last_status = NOW() WHERE id = ?",
            [$online, $uptime, $serverId]
        );
    }

    public function performAction(int $serverId, string $action): array
    {
        if (!in_array($action, ['start','stop','restart','delete'], true)) {
            return ['ok' => false, 'error' => 'Ungültige Aktion'];
        }
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        if ($action === 'delete') {
            $canDelete = $this->canAdminAll() ||
                         ($this->isHostAdmin() && in_array((int)$srv['host_id'], $this->getAdminHostIds()));
            if (!$canDelete) return ['ok' => false, 'error' => 'Keine Berechtigung zum Löschen'];
        } elseif (!$this->canManageServer($serverId)) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $cid   = (string)$srv['container_id'];

        $res = match ($action) {
            'start'   => $agent->startServer($cid),
            'stop'    => $agent->stopServer($cid),
            'restart' => $agent->restartServer($cid),
            'delete'  => $agent->deleteServer($cid),
        };

        if ($res['ok']) {
            match ($action) {
                'start'   => $this->setStatus($serverId, 'running'),
                'stop'    => $this->setStatus($serverId, 'stopped'),
                'restart' => $this->setStatus($serverId, 'running'),
                'delete'  => $this->deleteServerRow($serverId),
            };
        }

        $uid = (int)Auth::id();
        $this->log($serverId, $uid, $action,
            $res['ok'] ? 'ok' : (string)$res['error'], (bool)$res['ok']);
        return $res;
    }

    public function performUpgrade(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];

        try { DB::connection()->query('SELECT 1'); } catch (\PDOException $e) { DB::connect(); }
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token'], 300);
        $res   = $agent->upgradeServer((string)$srv['container_id']);

        if ($res['ok'] ?? false) {
            $newCid = (string)($res['data']['container_id'] ?? $srv['container_id']);
            try { DB::connection()->query('SELECT 1'); } catch (\PDOException $e) { DB::connect(); }
            $this->updateContainerId($serverId, $newCid);
            $uid = (int)Auth::id();
            $this->log($serverId, $uid, 'upgrade',
                'Image aktualisiert auf '.($res['data']['image'] ?? 'unbekannt'), true);
        }
        return $res;
    }

    private function deleteServerRow(int $serverId): void
    {
        $ts = DB::table('mumble_server');
        DB::query("DELETE FROM `{$ts}` WHERE id = ?", [$serverId]);
    }

    public function refreshStats(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];

        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->getStats((string)$srv['container_id']);

        if ($res['ok'] && isset($res['data']['online'], $res['data']['uptime'])) {
            $this->updateStats($serverId, (int)$res['data']['online'], (int)$res['data']['uptime']);
            if (in_array($srv['status'], ['creating', 'error'], true)) {
                $this->setStatus($serverId, 'running');
            }
        }
        return $res;
    }

    public function fetchLogs(int $serverId, int $tail = 300): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->getLogs((string)$srv['container_id'], $tail);
    }

    /* ========== Settings ========== */

    public function getSetting(string $key): string
    {
        $tse = DB::table('mumble_settings');
        return (string)(DB::value("SELECT `value` FROM `{$tse}` WHERE `key` = ?", [$key]) ?: '');
    }

    private function setSetting(string $key, string $value): void
    {
        $tse = DB::table('mumble_settings');
        DB::query(
            "INSERT INTO `{$tse}` (`key`,`value`) VALUES (?,?)
             ON DUPLICATE KEY UPDATE `value`=?",
            [$key, $value, $value]
        );
    }

    public function getCronKey(): string { return $this->getSetting('cron_key'); }
    public function setCronKey(string $key): void
    {
        if (!$this->canManageHosts()) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        $this->setSetting('cron_key', $key);
    }

    /* ========== Statistik-Cron ========== */

    public function collectAllStats(): void
    {
        $ts = DB::table('mumble_server');
        $rows = DB::fetchAll("SELECT id FROM `{$ts}` WHERE status = 'running'", []) ?: [];
        $epoch = time();

        // Alle Agent-Anfragen parallel abfeuern statt nacheinander — sonst läuft der
        // Cron-Aufruf bei vielen Servern in den Apache-Proxy-Timeout (504).
        $servers  = [];
        $requests = [];
        foreach ($rows as $row) {
            $fullSrv = $this->getServer((int)$row['id']);
            if (!$fullSrv || $fullSrv['status'] !== 'running') continue;
            $sid = (int)$fullSrv['id'];
            $servers[$sid]  = $fullSrv;
            $requests[$sid] = [
                'url'   => $fullSrv['agent_url'],
                'token' => $fullSrv['agent_token'],
                'cid'   => (string)$fullSrv['container_id'],
            ];
        }

        $results = $requests ? MumbleAgent::multiDashboard($requests, 20, true) : [];

        foreach ($results as $sid => $dash) {
            $fullSrv   = $servers[$sid];
            $agentResp = $dash['data'] ?? [];
            $data      = (($agentResp['ok'] ?? false) && isset($agentResp['data'])) ? $agentResp['data'] : [];
            if (empty($data)) continue;
            $users   = $data['users'] ?? [];
            $pings   = array_filter(array_map(function($u) {
                $p = ($u['udp_ping'] ?? 0) > 0 ? $u['udp_ping'] : ($u['tcp_ping'] ?? 0);
                return $p > 0 ? $p : null;
            }, $users));
            $pingAvg = count($pings) > 0 ? array_sum($pings) / count($pings) : 0;
            $this->recordStats($sid, $epoch, [
                'users_online' => (int)($data['user_count'] ?? 0),
                'max_users'    => (int)$fullSrv['max_users'],
                'bandwidth'    => (int)array_sum(array_column($users, 'bytespersec')),
                'cpu'          => (float)($data['cpu_percent'] ?? 0),
                'ram_mb'       => (int)($data['mem_mb'] ?? 0),
                'ping_avg'     => round($pingAvg, 1),
            ]);
        }
        $this->cleanOldStats();
        $this->setSetting('cron_last_run', (string)$epoch);
    }

    private function recordStats(int $serverId, int $ts, array $d): void
    {
        $tst = DB::table('mumble_stats');
        DB::query(
            "INSERT INTO `{$tst}` (server_id, ts, users_online, max_users, bandwidth, cpu, ram_mb, ping_avg)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$serverId, $ts, $d['users_online'], $d['max_users'], $d['bandwidth'],
             $d['cpu'], $d['ram_mb'], $d['ping_avg']]
        );
    }

    private function cleanOldStats(): void
    {
        $cutoff = time() - 31 * 86400;
        $tst = DB::table('mumble_stats');
        DB::query("DELETE FROM `{$tst}` WHERE ts < ?", [$cutoff]);
    }

    public function getStatsHistory(int $serverId, string $range): array
    {
        $now = time();
        [$since, $interval] = match ($range) {
            '7d'    => [$now - 7 * 86400,   3600],
            '30d'   => [$now - 30 * 86400, 21600],
            default => [$now - 86400,         300],
        };
        $tst = DB::table('mumble_stats');
        if ($interval > 300) {
            return DB::fetchAll(
                "SELECT FLOOR(ts / ?) * ? AS bucket,
                        ROUND(AVG(users_online),1) AS users_online,
                        ROUND(AVG(max_users))      AS max_users,
                        ROUND(AVG(bandwidth))      AS bandwidth,
                        ROUND(AVG(cpu),1)          AS cpu,
                        ROUND(AVG(ram_mb))         AS ram_mb,
                        ROUND(AVG(ping_avg),1)     AS ping_avg
                 FROM `{$tst}`
                 WHERE server_id = ? AND ts >= ?
                 GROUP BY bucket ORDER BY bucket ASC",
                [$interval, $interval, $serverId, $since]
            ) ?: [];
        } else {
            return DB::fetchAll(
                "SELECT ts AS bucket, users_online, max_users, bandwidth, cpu, ram_mb, ping_avg
                 FROM `{$tst}` WHERE server_id = ? AND ts >= ? ORDER BY ts ASC",
                [$serverId, $since]
            ) ?: [];
        }
    }

    public function getStatsHistoryAll(string $range, array $serverIds = []): array
    {
        $now = time();
        [$since, $interval] = match ($range) {
            '7d'    => [$now - 7 * 86400,   3600],
            '30d'   => [$now - 30 * 86400, 21600],
            default => [$now - 86400,         300],
        };
        $tst      = DB::table('mumble_stats');
        $idFilter = '';
        if (!empty($serverIds)) {
            $idFilter = ' AND server_id IN (' . implode(',', array_map('intval', $serverIds)) . ')';
        }
        if ($interval > 300) {
            return DB::fetchAll(
                "SELECT FLOOR(ts / ?) * ? AS bucket,
                        SUM(users_online) AS users_online, SUM(bandwidth) AS bandwidth,
                        ROUND(AVG(cpu),1) AS cpu, SUM(ram_mb) AS ram_mb,
                        ROUND(AVG(NULLIF(ping_avg,0)),1) AS ping_avg
                 FROM `{$tst}` WHERE ts >= ?{$idFilter} GROUP BY bucket ORDER BY bucket ASC",
                [$interval, $interval, $since]
            ) ?: [];
        } else {
            return DB::fetchAll(
                "SELECT FLOOR(ts / 60) * 60 AS bucket,
                        SUM(users_online) AS users_online, SUM(bandwidth) AS bandwidth,
                        ROUND(AVG(cpu),1) AS cpu, SUM(ram_mb) AS ram_mb,
                        ROUND(AVG(NULLIF(ping_avg,0)),1) AS ping_avg
                 FROM `{$tst}` WHERE ts >= ?{$idFilter} GROUP BY bucket ORDER BY bucket ASC",
                [$since]
            ) ?: [];
        }
    }

    public function getStatsHistoryForHost(int $hostId, string $range): array
    {
        $now = time();
        [$since, $interval] = match ($range) {
            '7d'    => [$now - 7 * 86400,   3600],
            '30d'   => [$now - 30 * 86400, 21600],
            default => [$now - 86400,         300],
        };
        $ts  = DB::table('mumble_server');
        $tst = DB::table('mumble_stats');
        $ids = array_map('intval', array_column(
            DB::fetchAll("SELECT id FROM `{$ts}` WHERE host_id = ?", [$hostId]) ?: [],
            'id'
        ));
        if (empty($ids)) return [];
        $in = implode(',', $ids);

        if ($interval > 300) {
            return DB::fetchAll(
                "SELECT FLOOR(ts / ?) * ? AS bucket,
                        SUM(users_online) AS users_online, SUM(bandwidth) AS bandwidth,
                        ROUND(AVG(cpu),1) AS cpu, SUM(ram_mb) AS ram_mb,
                        ROUND(AVG(NULLIF(ping_avg,0)),1) AS ping_avg
                 FROM `{$tst}` WHERE server_id IN ($in) AND ts >= ?
                 GROUP BY bucket ORDER BY bucket ASC",
                [$interval, $interval, $since]
            ) ?: [];
        } else {
            return DB::fetchAll(
                "SELECT FLOOR(ts / 60) * 60 AS bucket,
                        SUM(users_online) AS users_online, SUM(bandwidth) AS bandwidth,
                        ROUND(AVG(cpu),1) AS cpu, SUM(ram_mb) AS ram_mb,
                        ROUND(AVG(NULLIF(ping_avg,0)),1) AS ping_avg
                 FROM `{$tst}` WHERE server_id IN ($in) AND ts >= ?
                 GROUP BY bucket ORDER BY bucket ASC",
                [$since]
            ) ?: [];
        }
    }

    /* ========== Dashboard ========== */

    public function getServers(): array
    {
        if ($this->canAdminAll()) return $this->listAllServers();
        return $this->listServersByOwner((int)Auth::id());
    }

    public function getDashboardData(): array
    {
        $servers = $this->getServers();
        $result  = [];
        foreach ($servers as $srv) {
            $fullSrv = $this->getServer((int)$srv['id']);
            if (!$fullSrv) continue;
            $agent = new MumbleAgent($fullSrv['agent_url'], $fullSrv['agent_token']);
            $dash  = $agent->getDashboard((string)$fullSrv['container_id']);
            $agentResp = $dash['data'] ?? [];
            $data  = (($agentResp['ok'] ?? false) && isset($agentResp['data'])) ? $agentResp['data'] : [];
            $result[] = [
                'id'             => (int)$fullSrv['id'],
                'name'           => $fullSrv['name'],
                'port'           => (int)$fullSrv['port'],
                'max_users'      => (int)$fullSrv['max_users'],
                'status'         => $data['status'] ?? $fullSrv['status'],
                'uptime_secs'    => $data['uptime_secs'] ?? 0,
                'users'          => $data['users'] ?? [],
                'user_count'     => $data['user_count'] ?? 0,
                'channel_count'  => $data['channel_count'] ?? 0,
                'ban_count'      => $data['ban_count'] ?? 0,
                'cpu_percent'    => $data['cpu_percent'] ?? 0,
                'mem_mb'         => $data['mem_mb'] ?? 0,
                'net_rx_mb'      => $data['net_rx_mb'] ?? 0,
                'net_tx_mb'      => $data['net_tx_mb'] ?? 0,
                'bandwidth_total'=> array_sum(array_column($data['users'] ?? [], 'bytespersec')),
            ];
        }
        return ['ok' => true, 'servers' => $result];
    }

    public function getDashboardDataGrouped(): array
    {
        $isAdmin   = $this->canAdminAll();
        $isHostAdm = $this->isHostAdmin();
        $adminHosts = $isHostAdm ? $this->getAdminHostIds() : [];
        $hosts  = $this->listHosts(false);
        $result = [];

        // Live-Dashboard-Requests fuer alle laufenden Server parallel vorbereiten
        $multiReqs = [];
        $srvIndex  = [];

        foreach ($hosts as $host) {
            $hid = (int)$host['id'];
            if (!$isAdmin && !in_array($hid, $adminHosts)) continue;
            $servers = $this->getServersForHost($hid);
            $running = 0; $users = 0;
            foreach ($servers as &$srv) {
                if ($srv['status'] === 'running') $running++;
                $users += (int)$srv['stats_online'];
            }
            unset($srv);

            $hi = count($result);
            foreach ($servers as $si => $srv) {
                if ($srv['status'] !== 'running' || empty($srv['container_id'])) continue;
                $key = $hi.'_'.$si;
                $multiReqs[$key] = [
                    'url'   => (string)$host['agent_url'],
                    'token' => (string)$host['agent_token'],
                    'cid'   => (string)$srv['container_id'],
                ];
                $srvIndex[$key] = [$hi, $si];
            }

            unset($host['agent_token']);
            $result[] = [
                'host'         => $host,
                'servers'      => $servers,
                'server_count' => count($servers),
                'running'      => $running,
                'users_total'  => $users,
            ];
        }

        // Alle Agent-Requests gleichzeitig abfeuern und Live-Daten je Server einmischen
        if (!empty($multiReqs)) {
            $dashResults = MumbleAgent::multiDashboard($multiReqs);
            foreach ($dashResults as $key => $dash) {
                [$hi, $si] = $srvIndex[$key];
                $resp = $dash['data'] ?? [];
                $data = (($resp['ok'] ?? false) && isset($resp['data'])) ? $resp['data'] : [];
                $result[$hi]['servers'][$si]['users']           = $data['users'] ?? [];
                $result[$hi]['servers'][$si]['user_count']      = $data['user_count'] ?? 0;
                $result[$hi]['servers'][$si]['bandwidth_total'] = array_sum(array_column($data['users'] ?? [], 'bytespersec'));
                $result[$hi]['servers'][$si]['uptime_secs']     = $data['uptime_secs'] ?? 0;
                $result[$hi]['servers'][$si]['cpu_percent']     = $data['cpu_percent'] ?? 0;
                $result[$hi]['servers'][$si]['mem_mb']          = $data['mem_mb'] ?? 0;
                $result[$hi]['servers'][$si]['channel_count']   = $data['channel_count'] ?? 0;
            }
            foreach ($result as &$hostData) {
                $hostData['users_total'] = array_sum(array_column($hostData['servers'], 'user_count'));
            }
            unset($hostData);
        }

        return ['ok' => true, 'hosts' => $result, 'is_admin' => $isAdmin, 'is_host_admin' => $isHostAdm];
    }

    public function getLiveHostData(int $hostId): array
    {
        $servers = $this->getServersForHost($hostId);
        $result  = ['users_total' => 0, 'running' => 0, 'stopped' => 0, 'bandwidth' => 0, 'cpu_avg' => 0, 'ram_total' => 0, 'ping_avg' => 0, 'servers' => []];
        $cpuVals = []; $pingVals = [];
        foreach ($servers as $srv) {
            $fullSrv = $this->getServer((int)$srv['id']);
            if (!$fullSrv) continue;
            if ($srv['status'] !== 'running') { $result['stopped']++; $result['servers'][] = $srv; continue; }
            $result['running']++;
            $agent = new MumbleAgent($fullSrv['agent_url'], $fullSrv['agent_token']);
            $dash  = $agent->getDashboard((string)$fullSrv['container_id']);
            $resp  = $dash['data'] ?? [];
            $data  = (($resp['ok'] ?? false) && isset($resp['data'])) ? $resp['data'] : [];
            $users = $data['users'] ?? [];
            $bw    = array_sum(array_column($users, 'bytespersec'));
            $pings = array_filter(array_map(fn($u) => ($u['udp_ping'] ?? 0) > 0 ? $u['udp_ping'] : ($u['tcp_ping'] ?? 0), $users), fn($p) => $p > 0);
            $result['users_total'] += (int)($data['user_count'] ?? 0);
            $result['bandwidth']   += $bw;
            $result['ram_total']   += (int)($data['mem_mb'] ?? 0);
            if (isset($data['cpu_percent'])) $cpuVals[] = (float)$data['cpu_percent'];
            if (!empty($pings)) foreach ($pings as $p) $pingVals[] = $p;
            $srv['live'] = ['user_count' => $data['user_count'] ?? 0, 'bandwidth' => $bw, 'uptime_secs' => $data['uptime_secs'] ?? 0, 'cpu_percent' => $data['cpu_percent'] ?? 0, 'mem_mb' => $data['mem_mb'] ?? 0, 'users' => $users];
            $result['servers'][] = $srv;
        }
        $result['cpu_avg']  = count($cpuVals)  > 0 ? round(array_sum($cpuVals)  / count($cpuVals),  1) : 0;
        $result['ping_avg'] = count($pingVals) > 0 ? round(array_sum($pingVals) / count($pingVals), 1) : 0;
        return $result;
    }

    /* ========== Widget-Summary ========== */

    public function getWidgetSummary(): array
    {
        $uid = (int)Auth::id();
        $ts  = DB::table('mumble_server');
        $own = DB::fetch(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) AS running,
                    SUM(stats_online) AS online FROM `{$ts}` WHERE owner_user_id = ?",
            [$uid]
        ) ?: ['total' => 0, 'running' => 0, 'online' => 0];

        $all = null;
        if ($this->canAdminAll()) {
            $all = DB::fetch(
                "SELECT COUNT(*) AS total, SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) AS running,
                        SUM(stats_online) AS online FROM `{$ts}`",
                []
            ) ?: null;
        }
        return [
            'own' => ['total' => (int)$own['total'], 'running' => (int)$own['running'], 'online' => (int)$own['online']],
            'all' => $all ? ['total' => (int)$all['total'], 'running' => (int)$all['running'], 'online' => (int)$all['online']] : null,
        ];
    }

    /* ========== SuperUser ========== */

    public function getSuperUserPassword(int $serverId): ?string
    {
        $srv = $this->getServer($serverId);
        if (!$srv || !$this->canManageServer($serverId)) return null;
        if (!empty($srv['superuser_password'])) return (string)$srv['superuser_password'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->getSuperUser((string)$srv['container_id']);
        if ($res['ok'] && !empty($res['data']['superuser_password'])) {
            $pw = (string)$res['data']['superuser_password'];
            $this->saveSuperUserPassword($serverId, $pw);
            return $pw;
        }
        return null;
    }

    public function saveSuperUserPassword(int $serverId, string $pw): void
    {
        $ts = DB::table('mumble_server');
        DB::query(
            "UPDATE `{$ts}` SET superuser_password = ? WHERE id = ?",
            [Crypto::encrypt($pw), $serverId]
        );
    }

    public function resetSuperUserPassword(int $serverId, string $newPw = ''): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->resetSuperUser((string)$srv['container_id'], $newPw);
        if ($res['ok'] && !empty($res['data']['superuser_password'])) {
            $pw = (string)$res['data']['superuser_password'];
            $this->saveSuperUserPassword($serverId, $pw);
            $this->log($serverId, $uid, 'superuser_reset', 'ok');
            return ['ok' => true, 'password' => $pw];
        }
        return ['ok' => false, 'error' => $res['error'] ?? 'Agent-Fehler'];
    }

    /* ========== Server-Einstellungen live ========== */

    public function updateMumbleSettingsLive(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];

        $agent   = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $payload = [];
        if (isset($data['name']))         $payload['name']         = substr((string)$data['name'], 0, 64);
        if (isset($data['password']))     $payload['password']     = (string)$data['password'];
        if (isset($data['max_users']) && $this->isOwner($serverId)) {
            $maxUsers = max(1, (int)$data['max_users']);
            if (!$this->canAdminAll() && !$this->isHostAdmin()) {
                $quota = $this->getQuotaForRole(Auth::role());
                $maxUsers = min($maxUsers, (int)$quota['max_users_cap']);
            }
            $payload['max_users'] = $maxUsers;
        }
        if (isset($data['welcome_text'])) $payload['welcome_text'] = (string)$data['welcome_text'];

        $res = $agent->updateSettingsLive((string)$srv['container_id'], $payload);
        if (!$res['ok']) return $res;

        $ts     = DB::table('mumble_server');
        $fields = ['updated_at = NOW()'];
        $params = [];
        if (isset($payload['name']))         { $fields[] = 'name = ?';         $params[] = $payload['name']; }
        if (isset($payload['password']))     { $fields[] = 'password = ?';     $params[] = $payload['password']; }
        if (isset($payload['max_users']))    { $fields[] = 'max_users = ?';    $params[] = $payload['max_users']; }
        if (isset($payload['welcome_text'])) { $fields[] = 'welcome_text = ?'; $params[] = $payload['welcome_text']; }
        $params[] = $serverId;
        DB::query("UPDATE `{$ts}` SET ".implode(', ', $fields)." WHERE id = ?", $params);
        return ['ok' => true];
    }

    public function updateServerSettings(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        if (isset($data['max_users'])) {
            if (!$this->isOwner($serverId)) {
                unset($data['max_users']);
            } elseif (!$this->canAdminAll() && !$this->isHostAdmin()) {
                $quota = $this->getQuotaForRole(Auth::role());
                $data['max_users'] = min(max(1, (int)$data['max_users']), (int)$quota['max_users_cap']);
            }
        }

        $uid    = (int)Auth::id();
        $ts     = DB::table('mumble_server');
        $fields = [];
        $params = [];
        if (isset($data['name']))         { $fields[] = 'name = ?';         $params[] = substr((string)$data['name'], 0, 64); }
        if (isset($data['welcome_text'])) { $fields[] = 'welcome_text = ?'; $params[] = (string)$data['welcome_text']; }
        if (isset($data['max_users']))    { $fields[] = 'max_users = ?';    $params[] = max(1, (int)$data['max_users']); }
        if (array_key_exists('password', $data)) { $fields[] = 'password = ?'; $params[] = (string)$data['password']; }
        if (!empty($fields)) {
            $fields[]  = 'updated_at = NOW()';
            $params[]  = $serverId;
            DB::query("UPDATE `{$ts}` SET ".implode(', ', $fields)." WHERE id = ?", $params);
        }

        $agent     = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $agentData = [];
        if (isset($data['name']))         $agentData['name']         = $data['name'];
        if (isset($data['welcome_text'])) $agentData['welcome_text'] = $data['welcome_text'];
        if (isset($data['max_users']))    $agentData['max_users']    = (int)$data['max_users'];
        if (array_key_exists('password', $data)) $agentData['password'] = $data['password'];

        $res = $agent->updateConfig((string)$srv['container_id'], $agentData);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            try { DB::connection()->query('SELECT 1'); } catch (\PDOException $e) { DB::connect(); }
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'settings_update',
            $res['ok'] ? 'ok' : (string)($res['error'] ?? 'unknown'), (bool)$res['ok']);
        return $res;
    }

    /* ========== Server-Konfiguration (Agent-Settings) ========== */

    public function getServerSettings(int $serverId): ?array
    {
        $srv = $this->getServer($serverId);
        if (!$srv || !$this->canManageServer($serverId)) return null;
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->getSettings((string)$srv['container_id']);
        return $res['ok'] ? ($res['data']['settings'] ?? []) : [];
    }

    public function saveServerSettings(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid = (int)Auth::id();
        if (isset($data['max_users'])) {
            if (!$this->isOwner($serverId)) {
                unset($data['max_users']);
            } elseif (!$this->canAdminAll() && !$this->isHostAdmin()) {
                $quota = $this->getQuotaForRole(Auth::role());
                $data['max_users'] = min(max(1, (int)$data['max_users']), (int)$quota['max_users_cap']);
            }
        }
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->saveSettings((string)$srv['container_id'], $data);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'config_update', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    /* ========== Zertifikat ========== */

    public function setCertificate(int $serverId, string $cert, string $key): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->setCertificate((string)$srv['container_id'], $cert, $key);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'cert_upload', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    public function removeCertificate(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->removeCertificate((string)$srv['container_id']);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'cert_remove', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    private function updateContainerId(int $serverId, string $newCid): void
    {
        try {
            $ts = DB::table('mumble_server');
            DB::query("UPDATE `{$ts}` SET container_id = ?, updated_at = NOW() WHERE id = ?", [$newCid, $serverId]);
        } catch (\Throwable) {}
    }

    /* ========== Live-User-Verwaltung ========== */

    public function getLiveUsers(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->getLiveUsers((string)$srv['container_id']);
    }

    public function kickMumbleUser(int $serverId, int $session, string $reason = ''): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->kickUser((string)$srv['container_id'], $session, $reason);
        if ($res['ok']) $this->log($serverId, $uid, 'kick', "session={$session} reason={$reason}");
        return $res;
    }

    public function muteMumbleUser(int $serverId, int $session, bool $mute): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->updateUser((string)$srv['container_id'], $session, ['mute' => $mute]);
    }

    public function moveMumbleUser(int $serverId, int $session, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->updateUser((string)$srv['container_id'], $session, ['channel' => $channelId]);
    }

    /* ========== Channel-Verwaltung ========== */

    public function getMumbleChannels(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->getChannels((string)$srv['container_id']);
    }

    public function addMumbleChannel(int $serverId, string $name, int $parent = 0): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->addChannel((string)$srv['container_id'], $name, $parent);
        if ($res['ok']) $this->log($serverId, $uid, 'channel_add', "name={$name} parent={$parent}");
        return $res;
    }

    public function updateMumbleChannel(int $serverId, int $channelId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->updateChannel((string)$srv['container_id'], $channelId, $data);
        if ($res['ok']) $this->log($serverId, $uid, 'channel_update', "id={$channelId}");
        return $res;
    }

    public function removeMumbleChannel(int $serverId, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->removeChannel((string)$srv['container_id'], $channelId);
        if ($res['ok']) $this->log($serverId, $uid, 'channel_remove', "id={$channelId}");
        return $res;
    }

    /* ========== Ban-Verwaltung ========== */

    public function getMumbleBans(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->getBans((string)$srv['container_id']);
    }

    public function setMumbleBans(int $serverId, array $bans): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->setBans((string)$srv['container_id'], $bans);
        if ($res['ok']) $this->log($serverId, $uid, 'bans_update', 'count='.count($bans));
        return $res;
    }

    /* ========== ACL-Verwaltung ========== */

    public function getChannelAcl(int $serverId, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        return $agent->getChannelAcl((string)$srv['container_id'], $channelId);
    }

    public function setChannelAcl(int $serverId, int $channelId, bool $inheritAcl, array $aclEntries, array $groups): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)Auth::id();
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->setChannelAcl((string)$srv['container_id'], [
            'channel_id'  => $channelId,
            'inherit_acl' => $inheritAcl,
            'acl'         => $aclEntries,
            'groups'      => $groups,
        ]);
        if ($res['ok']) $this->log($serverId, $uid, 'acl_update', "channel={$channelId}");
        return $res;
    }

    /* ========== Channel-Viewer & Widget ========== */

    public function getViewer(int $serverId): ?array
    {
        $srv = $this->getServer($serverId);
        if (!$srv || !$this->canManageServer($serverId)) return null;
        $agent = new MumbleAgent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->getViewer((string)$srv['container_id']);
        return $res['ok'] ? $res['data'] : null;
    }

    public function getWidgetSettings(int $serverId): ?array
    {
        $ts = DB::table('mumble_server');
        return DB::fetch(
            "SELECT id, name, widget_token, widget_public, widget_refresh FROM `{$ts}` WHERE id = ? LIMIT 1",
            [$serverId]
        ) ?: null;
    }

    public function saveWidgetSettings(int $serverId, bool $public, int $refresh): void
    {
        $ts = DB::table('mumble_server');
        DB::query(
            "UPDATE `{$ts}` SET widget_public = ?, widget_refresh = ?, updated_at = NOW() WHERE id = ?",
            [$public ? 1 : 0, max(0, $refresh), $serverId]
        );
    }

    public function generateWidgetToken(int $serverId): string
    {
        $token = bin2hex(random_bytes(24));
        $ts    = DB::table('mumble_server');
        DB::query("UPDATE `{$ts}` SET widget_token = ? WHERE id = ?", [$token, $serverId]);
        return $token;
    }

    public function disableWidget(int $serverId): void
    {
        $ts = DB::table('mumble_server');
        DB::query("UPDATE `{$ts}` SET widget_token = NULL WHERE id = ?", [$serverId]);
    }

    public function getServerByWidget(string $token): ?array
    {
        if ($token === '') return null;
        $ts = DB::table('mumble_server');
        $th = DB::table('mumble_host');
        $row = DB::fetch(
            "SELECT s.*, h.agent_url, h.agent_token, h.hostname
               FROM `{$ts}` s JOIN `{$th}` h ON h.id = s.host_id
              WHERE s.widget_token = ? AND s.status = 'running' LIMIT 1",
            [$token]
        );
        return $row ? $this->decryptRow($row, ['agent_token']) : null;
    }

    public function getPublicServer(int $id): ?array
    {
        $ts = DB::table('mumble_server');
        $th = DB::table('mumble_host');
        $row = DB::fetch(
            "SELECT s.*, h.agent_url, h.agent_token, h.hostname
               FROM `{$ts}` s JOIN `{$th}` h ON h.id = s.host_id
              WHERE s.id = ? AND s.widget_public = 1 AND s.status = 'running' LIMIT 1",
            [$id]
        );
        return $row ? $this->decryptRow($row, ['agent_token']) : null;
    }

    /* ========== Server-Mitglieder ========== */

    public function getMembers(int $serverId): array
    {
        $tsm = DB::table('mumble_server_members');
        $tu  = DB::table('users');
        return DB::fetchAll(
            "SELECT m.user_id, m.added_at, u.display_name
               FROM `{$tsm}` m JOIN `{$tu}` u ON u.id = m.user_id
              WHERE m.server_id = ? ORDER BY u.display_name",
            [$serverId]
        ) ?: [];
    }

    public function isMember(int $serverId, int $userId): bool
    {
        $tsm = DB::table('mumble_server_members');
        return (bool)DB::value(
            "SELECT 1 FROM `{$tsm}` WHERE server_id = ? AND user_id = ? LIMIT 1",
            [$serverId, $userId]
        );
    }

    public function addMember(int $serverId, int $userId): bool
    {
        if (!$this->isOwner($serverId)) return false;
        $tsm = DB::table('mumble_server_members');
        DB::query(
            "INSERT IGNORE INTO `{$tsm}` (server_id, user_id, added_at) VALUES (?, ?, NOW())",
            [$serverId, $userId]
        );
        return true;
    }

    public function removeMember(int $serverId, int $userId): bool
    {
        if (!$this->isOwner($serverId)) return false;
        $tsm = DB::table('mumble_server_members');
        DB::query("DELETE FROM `{$tsm}` WHERE server_id = ? AND user_id = ?", [$serverId, $userId]);
        return true;
    }

    public function searchUsers(string $term, int $limit = 10): array
    {
        $tu = DB::table('users');
        return DB::fetchAll(
            "SELECT id, display_name FROM `{$tu}`
              WHERE (display_name LIKE ? OR email LIKE ?) AND active = 1
              ORDER BY display_name LIMIT ?",
            ["%{$term}%", "%{$term}%", $limit]
        ) ?: [];
    }

    /* ========== Audit-Log ========== */

    public function log(?int $serverId, int $userId, string $action,
                        string $details = '', bool $success = true): void
    {
        try {
            $tl = DB::table('mumble_log');
            DB::query(
                "INSERT INTO `{$tl}` (server_id, user_id, action, details, success, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$serverId, $userId, $action, $details, $success ? 1 : 0]
            );
        } catch (\PDOException $e) {
            error_log('esse-mumble: log() fehlgeschlagen: '.$e->getMessage());
        }
    }

    /* ========== Hilfsfunktionen ========== */

    private function decryptRow(array $row, array $fields): array
    {
        foreach ($fields as $f) {
            if (isset($row[$f])) $row[$f] = Crypto::decrypt((string)$row[$f]);
        }
        return $row;
    }

    public function getOwnedServerIds(int $userId): array
    {
        $ts = DB::table('mumble_server');
        return array_column(
            DB::fetchAll("SELECT id FROM `{$ts}` WHERE owner_user_id = ?", [$userId]) ?: [],
            'id'
        );
    }
}
