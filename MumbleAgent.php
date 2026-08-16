<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – MumbleAgent
 * File: MumbleAgent.php
 *
 * HTTPS-Client für den Python-Agent (mumble-agent) auf jedem Mumble-Host.
 * Liefert normalisiertes Ergebnis:
 *   ['ok' => bool, 'data' => mixed|null, 'error' => ?string, 'http' => int]
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

namespace EsseMumble;

class MumbleAgent
{
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    public function __construct(string $agentUrl, string $token, int $timeout = 30)
    {
        $this->baseUrl = rtrim($agentUrl, '/');
        $this->token   = $token;
        $this->timeout = $timeout;
    }

    public function ping(): array { return $this->request('GET', '/v1/ping'); }
    public function listServers(): array { return $this->request('GET', '/v1/servers', null, 15); }
    public function createServer(array $cfg): array { return $this->request('POST', '/v1/servers', $cfg, 300); }
    public function deleteServer(string $cid): array { return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid), null, 30); }
    public function startServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/start'); }
    public function stopServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/stop'); }
    public function restartServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/restart'); }
    public function getStats(string $cid): array { return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/stats'); }
    public function getLogs(string $cid, int $tail = 200): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/logs?tail='.$tail);
    }
    public function updateConfig(string $cid, array $cfg): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid), $cfg, 120);
    }
    public function getSuperUser(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/superuser');
    }
    public function resetSuperUser(string $cid, string $pw = ''): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/superuser/reset',
            ['password' => $pw], 30);
    }
    public function getViewer(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/viewer', null, 12);
    }
    public function getSettings(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/settings');
    }
    public function saveSettings(string $cid, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid), $data, 120);
    }
    public function setCertificate(string $cid, string $cert, string $key): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/certificate',
            ['cert' => $cert, 'key' => $key], 60);
    }
    public function removeCertificate(string $cid): array {
        return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid).'/certificate', null, 60);
    }
    public function getChannelAcl(string $cid, int $channelId): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/acl?channel_id='.$channelId, null, 15);
    }
    public function setChannelAcl(string $cid, array $data): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/acl', $data, 15);
    }
    // Live-User (ICE)
    public function getLiveUsers(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/users', null, 10);
    }
    public function kickUser(string $cid, int $session, string $reason = ''): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/users/'.rawurlencode((string)$session).'/kick', ['reason' => $reason], 10);
    }
    public function updateUser(string $cid, int $session, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/users/'.rawurlencode((string)$session), $data, 10);
    }
    // Channels (ICE)
    public function getChannels(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/channels', null, 10);
    }
    public function addChannel(string $cid, string $name, int $parent = 0): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/channels', ['name' => $name, 'parent' => $parent], 10);
    }
    public function updateChannel(string $cid, int $channelId, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/channels/'.rawurlencode((string)$channelId), $data, 10);
    }
    public function removeChannel(string $cid, int $channelId): array {
        return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid).'/channels/'.rawurlencode((string)$channelId), null, 10);
    }
    // Bans (ICE)
    public function getBans(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/bans', null, 10);
    }
    public function setBans(string $cid, array $bans): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/bans', ['bans' => $bans], 10);
    }
    public function updateImage(string $image): array {
        return $this->request('POST', '/v1/image', ['image' => $image], 15);
    }
    public function upgradeServer(string $cid, ?string $image = null): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/upgrade',
            $image !== null ? ['image' => $image] : null, 300);
    }
    public function listImages(): array {
        return $this->request('GET', '/v1/images', null, 15);
    }
    public function setChannel(string $channel): array {
        return $this->request('POST', '/v1/channel', ['channel' => $channel], 10);
    }
    public function updateAgent(?string $version = null): array {
        return $this->request('POST', '/v1/agent/update', $version !== null ? ['version' => $version] : null, 30);
    }
    public function updateSettingsLive(string $cid, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/live', $data, 15);
    }
    // ICE aktivieren
    public function enableIce(string $cid): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/ice/enable', null, 30);
    }
    // Dashboard — live (schnell, kein CPU/RAM); mit $resources=true für Cron (langsamer)
    public function getDashboard(string $cid, bool $resources = false): array {
        $q = $resources ? '?resources=true' : '';
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/dashboard'.$q, null, $resources ? 30 : 15);
    }

    /**
     * Mehrere Dashboard-Requests parallel via curl_multi abfeuern.
     * $requests = [['url' => $agentUrl, 'token' => $token, 'cid' => $cid], ...]
     * Gibt Array mit denselben Keys zurück, Wert = ['ok'=>bool,'data'=>mixed]
     */
    public static function multiDashboard(array $requests, int $timeout = 15, bool $resources = false): array
    {
        $q       = $resources ? '?resources=true' : '';
        $mh      = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => $req) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => rtrim($req['url'], '/').'/v1/servers/'.rawurlencode($req['cid']).'/dashboard'.$q,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer '.$req['token'],
                    'Accept: application/json',
                ],
            ]);
            if (str_starts_with($req['url'], 'https://')) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }
        $active = null;
        do { curl_multi_exec($mh, $active); curl_multi_select($mh); } while ($active > 0);

        $results = [];
        foreach ($handles as $key => $ch) {
            $raw  = curl_multi_getcontent($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            $decoded = $raw ? json_decode($raw, true) : null;
            $results[$key] = [
                'ok'   => ($http >= 200 && $http < 300),
                'data' => $decoded,
            ];
        }
        curl_multi_close($mh);
        return $results;
    }

    private function request(string $method, string $path, mixed $body = null, ?int $timeoutOverride = null): array
    {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer '.$this->token,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_URL            => $this->baseUrl.$path,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutOverride ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
        ];

        if (str_starts_with($this->baseUrl, 'https://')) {
            $opts[CURLOPT_SSL_VERIFYPEER] = true;
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_POSTFIELDS] = $json;
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: '.strlen((string)$json);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw  = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);

        if ($raw === false) {
            return ['ok' => false, 'data' => null, 'error' => $err, 'http' => 0];
        }
        $decoded = json_decode((string)$raw, true);
        $ok = ($http >= 200 && $http < 300);
        return [
            'ok'    => $ok,
            'data'  => $decoded,
            'error' => $ok ? null : ($decoded['error'] ?? ('HTTP '.$http)),
            'http'  => $http,
        ];
    }
}
