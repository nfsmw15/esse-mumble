<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Statistik-Cron-Endpoint
 * Route: /mumble/cron/collect?key=XYZ
 *********************************************/

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$key = trim((string)($_GET['key'] ?? ''));
if ($key === '') { http_response_code(403); exit('Forbidden'); }

$mumble = new \EsseMumble\MumbleRepository();

if ($key !== $mumble->getCronKey()) {
    http_response_code(403);
    exit('Forbidden');
}

// Überlappungsschutz: Läuft noch ein vorheriger Aufruf (z.B. weil ein Mumble-Host
// hängt), sofort beenden statt einen weiteren parallelen Lauf zu starten — sonst
// stapeln sich bei jedem 5-Minuten-Cron-Tick weitere hängende Requests auf.
$mb_lockFile = sys_get_temp_dir().'/esse-mumble-cron.lock';
$mb_lockFp   = fopen($mb_lockFile, 'c');
if (!$mb_lockFp || !flock($mb_lockFp, LOCK_EX | LOCK_NB)) {
    header('Content-Type: text/plain');
    echo 'skipped (previous run still active)';
    exit;
}

$mumble->collectAllStats();

flock($mb_lockFp, LOCK_UN);
fclose($mb_lockFp);

header('Content-Type: text/plain');
echo 'ok '.date('c');
