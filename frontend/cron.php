<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Statistik-Cron-Endpoint
 * Route: /mumble/cron/collect?key=XYZ
 *********************************************/

$key = trim((string)($_GET['key'] ?? ''));
if ($key === '') { http_response_code(403); exit('Forbidden'); }

$mumble = new \EsseMumble\MumbleRepository();

if ($key !== $mumble->getCronKey()) {
    http_response_code(403);
    exit('Forbidden');
}

$mumble->collectAllStats();
header('Content-Type: text/plain');
echo 'ok '.date('c');
