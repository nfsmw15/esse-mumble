<?php
declare(strict_types=1);

/********************************************
 * esse-mumble – Channel-Viewer Widget (öffentliche Seite)
 * Route: /mumble/widget
 *
 * Zugriff: /mumble/widget?token=XYZ (token-geschützt)
 *           /mumble/widget?id=X     (öffentlich, wenn widget_public=1)
 *********************************************/

$mumble = new \EsseMumble\MumbleRepository();

$token = trim((string)($_GET['token'] ?? ''));
$sid   = (int)($_GET['id'] ?? 0);

if ($token !== '') {
    $srv = $mumble->getServerByWidget($token);
} elseif ($sid > 0) {
    $srv = $mumble->getPublicServer($sid);
} else {
    $srv = null;
}

if (!$srv) {
    http_response_code(404);
?>
<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">
<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
</head><body class="mb-widget-404">Widget nicht verfügbar.</body></html>
<?php
    exit;
}

$refresh = (int)$srv['widget_refresh'];
$data    = null;
if ((int)($srv['host_is_active'] ?? 1) === 1) {
    $agent = new \EsseMumble\MumbleAgent((string)$srv['agent_url'], (string)$srv['agent_token'], 6);
    $res   = $agent->getViewer((string)$srv['container_id']);
    $data  = ($res['ok'] && isset($res['data']['channels'])) ? $res['data'] : null;
}
if ($data && isset($data['channels'])) {
    $data['channels']['name'] = (string)$srv['name'];
}

// Klassenbasierte Einrückung statt Inline-style (CSP: style-src 'self') — deckt
// die üblichen Verschachtelungstiefen ab, klemmt darüber auf die letzte Stufe.
function esse_render_channel(array $ch, int $depth = 0): void {
    $depthCls     = 'mb-depth-'.min($depth, 10);
    $userDepthCls = 'mb-user-depth-'.min($depth, 10);
    $icon         = $depth === 0 ? '🔊' : '📁';
    echo '<div class="channel '.$depthCls.'">';
    echo '<span class="ch-icon">'.$icon.'</span> ';
    echo '<span class="ch-name">'.htmlspecialchars($ch['name']).'</span>';
    echo '</div>';
    foreach (($ch['users'] ?? []) as $user) {
        $uname = is_array($user) ? ($user['name'] ?? '') : (string)$user;
        echo '<div class="user '.$userDepthCls.'">';
        echo '<span class="u-icon">🎧</span> '.htmlspecialchars($uname);
        echo '</div>';
    }
    foreach (($ch['children'] ?? []) as $child) {
        esse_render_channel($child, $depth + 1);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($refresh > 0): ?>
<meta http-equiv="refresh" content="<?= $refresh ?>">
<?php endif; ?>
<title><?= htmlspecialchars((string)$srv['name']) ?> – Channel-Viewer</title>
<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
</head>
<body class="mb-widget-body">
<div class="header">
  <span class="srv-name">🎮 <a href="mumble://<?= htmlspecialchars((string)$srv['hostname']) ?>:<?= (int)$srv['port'] ?>" title="Mit Mumble verbinden"><?= htmlspecialchars((string)$srv['name']) ?></a></span>
  <?php if ($data): ?>
  <span class="online"><?= (int)$data['user_count'] ?> online</span>
  <?php endif; ?>
</div>

<?php if ($data): ?>
<?php esse_render_channel($data['channels']); ?>
<?php else: ?>
<div class="offline">Server nicht erreichbar</div>
<?php endif; ?>

<div class="footer">
  <?= htmlspecialchars((string)$srv['hostname']) ?>:<?= (int)$srv['port'] ?>
  <?php if ($refresh > 0): ?> · Refresh: <?= $refresh ?>s<?php endif; ?>
</div>
</body>
</html>
