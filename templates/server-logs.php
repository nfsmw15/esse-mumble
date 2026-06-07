<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);
$mb_uid = (int)Auth::id();

if (!$mb_srv || (!$mumble->canAdminAll() && (int)$mb_srv['owner_user_id'] !== $mb_uid && !$mumble->canManageServer($mb_sid))) {
    \Esse\Router::abort(403); return;
}

$mb_tail = max(50, min(1000, (int)($_GET['tail'] ?? 300)));
$mb_res  = $mumble->fetchLogs($mb_sid, $mb_tail);
$mb_log  = $mb_res['ok']
    ? (string)($mb_res['data']['log'] ?? '(keine Logzeilen)')
    : 'Fehler beim Abruf: '.htmlspecialchars((string)$mb_res['error']);

?>
<div class="mb-3">
    <a href="/mumble/edit/<?= (int)$mb_srv['id'] ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Letzte <?= $mb_tail ?> Zeilen</span>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary" href="/mumble/logs/<?= (int)$mb_srv['id'] ?>?tail=100">100</a>
            <a class="btn btn-outline-secondary" href="/mumble/logs/<?= (int)$mb_srv['id'] ?>?tail=300">300</a>
            <a class="btn btn-outline-secondary" href="/mumble/logs/<?= (int)$mb_srv['id'] ?>?tail=1000">1000</a>
            <a class="btn btn-outline-primary" href="/mumble/logs/<?= (int)$mb_srv['id'] ?>?tail=<?= $mb_tail ?>" title="Aktualisieren">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <pre class="mb-0 p-3" style="max-height:600px;overflow:auto;background:#1e1e1e;color:#dcdcdc;font-size:13px;line-height:1.4;"><?= htmlspecialchars($mb_log) ?></pre>
    </div>
</div>
<?php
