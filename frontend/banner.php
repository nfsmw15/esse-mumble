<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Server-Banner (PNG-Output)
 * Route: /mumble/banner?token=XYZ
 *********************************************/

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') { http_response_code(400); exit; }

$mumble = new \EsseMumble\MumbleRepository();
$srv    = $mumble->getServerByWidget($token);
if (!$srv) { http_response_code(404); exit; }

// Live-Daten (nur wenn Host aktiv — sonst blockiert ein hängender Host jeden
// Betrachter dieses öffentlich eingebetteten Banners)
$data = [];
if ((int)($srv['host_is_active'] ?? 1) === 1) {
    $agent     = new \EsseMumble\MumbleAgent((string)$srv['agent_url'], (string)$srv['agent_token'], 6);
    $dash      = $agent->getDashboard((string)$srv['container_id']);
    $agentResp = $dash['data'] ?? [];
    $data      = (($agentResp['ok'] ?? false) && isset($agentResp['data'])) ? $agentResp['data'] : [];
}

$online    = (int)($data['user_count'] ?? 0);
$maxUsers  = (int)$srv['max_users'];
$status    = (string)($data['status'] ?? $srv['status']);
$isRunning = $status === 'running';
$name      = (string)$srv['name'];
$host      = (string)$srv['hostname'];
$port      = (int)$srv['port'];
$address   = $host . ($port !== 64738 ? ':'.$port : '');

// Statistik-Sparkline
$history = $mumble->getStatsHistory((int)$srv['id'], '24h');

// Bildgröße
$W = 400;
$H = 80;

$img = imagecreatetruecolor($W, $H);
imagealphablending($img, true);
imagesavealpha($img, true);

// Farben
$cBg      = imagecolorallocate($img, 22,  22,  46);
$cBg2     = imagecolorallocate($img, 30,  30,  60);
$cBorder  = imagecolorallocate($img, 50,  50,  100);
$cGreen   = imagecolorallocate($img, 46,  213, 115);
$cRed     = imagecolorallocate($img, 220, 53,  69);
$cWhite   = imagecolorallocate($img, 255, 255, 255);
$cGray    = imagecolorallocate($img, 160, 160, 190);
$cAccent  = imagecolorallocate($img, 88,  166, 255);
$cSpark   = imagecolorallocate($img, 88,  166, 255);

// Hintergrund
imagefilledrectangle($img, 0,  0, $W-1, $H-1, $cBg);
imagefilledrectangle($img, 0, 55, $W-1, $H-1, $cBg2);
imagerectangle($img, 0, 0, $W-1, $H-1, $cBorder);

// Fonts
$fontBold   = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$fontNormal = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

// Status-Dot
$dotX = 14; $dotY = 20; $dotR = 5;
$cDot = $isRunning ? $cGreen : $cRed;
imagefilledellipse($img, $dotX, $dotY, $dotR * 2, $dotR * 2, $cDot);

// Server-Name (fallback wenn keine TTF-Fonts verfügbar)
if (file_exists($fontBold)) {
    imagettftext($img, 13, 0, 26, 26, $cWhite,  $fontBold,   $name);
    imagettftext($img, 8,  0, 27, 40, $cGray,   $fontNormal, $address);

    $userStr  = $online.' / '.$maxUsers;
    $labelStr = 'Nutzer';
    $bbox = imagettfbbox(18, 0, $fontBold, $userStr);
    $uw   = abs($bbox[2] - $bbox[0]);
    $ux   = $W - $uw - 14;
    imagettftext($img, 18, 0, $ux, 32, $isRunning ? $cAccent : $cGray, $fontBold, $userStr);
    $lbbox = imagettfbbox(8, 0, $fontNormal, $labelStr);
    $lw    = abs($lbbox[2] - $lbbox[0]);
    $lx    = $W - $lw - 14;
    imagettftext($img, 8, 0, $lx, 44, $cGray, $fontNormal, $labelStr);
    imagettftext($img, 7, 0, 4, $H-3, $cGray, $fontNormal, 'Mumble');
} else {
    // Fallback: GD-built-in-Font
    imagestring($img, 3, 26, 12, $name, $cWhite);
    imagestring($img, 1, 27, 28, $address, $cGray);
    imagestring($img, 4, $W-80, 12, $online.'/'.$maxUsers, $isRunning ? $cAccent : $cGray);
    imagestring($img, 1, 4, $H-12, 'Mumble', $cGray);
}

// Trennlinie
imageline($img, 0, 54, $W-1, 54, $cBorder);

// Sparkline
$sparkH   = $H - 57;
$sparkPad = 3;

if (count($history) >= 2) {
    $maxVal = max(1, max(array_column($history, 'users_online')));
    $n      = count($history);
    $step   = ($W - 2) / max(1, $n - 1);
    $points = [];
    foreach ($history as $i => $row) {
        $x = (int)round(1 + $i * $step);
        $y = (int)round(($H - $sparkPad) - ($row['users_online'] / $maxVal) * ($sparkH - $sparkPad * 2));
        $points[] = [$x, $y];
    }
    for ($i = 0; $i < count($points) - 1; $i++) {
        imageline($img, $points[$i][0], $points[$i][1], $points[$i+1][0], $points[$i+1][1], $cSpark);
    }
} else {
    $midY = (int)round($H - $sparkH / 2);
    for ($x = 4; $x < $W - 4; $x += 8) {
        imageline($img, $x, $midY, min($x + 4, $W - 4), $midY, $cGray);
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-cache, max-age=60');
imagepng($img);
imagedestroy($img);
