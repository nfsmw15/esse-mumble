<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) { \Esse\Router::abort(403); return; }

$mb_csrf = Auth::csrfToken();

// Session-Lock vor den Live-Agent-Calls freigeben (erst NACH csrfToken()), sonst
// blockiert ein hängender Host jeden weiteren Request derselben Browser-Session.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$mumble->refreshStats($mb_sid);
$mb_srv  = $mumble->getServer($mb_sid);
$mb_hid  = (int)$mb_srv['host_id'];

$mb_uptime = static function(int $secs): string {
    if ($secs <= 0) return '–';
    $d = (int)floor($secs / 86400); $h = (int)floor(($secs % 86400) / 3600); $m = (int)floor(($secs % 3600) / 60);
    $parts = []; if ($d) $parts[] = $d.'d'; if ($h) $parts[] = $h.'h'; if ($m) $parts[] = $m.'m';
    return $parts ? implode(' ', $parts) : $secs.'s';
};

$mb_status = (string)$mb_srv['status'];
$mb_cls = match ($mb_status) { 'running' => 'success', 'stopped' => 'secondary', 'error' => 'danger', 'creating' => 'warning', default => 'info' };

?>

<div class="mb-4">
    <a href="/mumble/edit/<?= $mb_sid ?>" class="btn btn-primary">
        <i class="bi bi-gear"></i> Einstellungen
    </a>
    <span class="badge text-bg-<?= $mb_cls ?> ms-2" style="font-size:1em;padding:6px 10px">
        <?= htmlspecialchars($mb_status) ?>
    </span>
</div>

<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-people"></i> Nutzer</div>
            <div class="h4 mb-0"><?= (int)$mb_srv['stats_online'] ?> / <?= (int)$mb_srv['max_users'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-clock"></i> Uptime</div>
            <div class="h4 mb-0"><?= $mb_uptime((int)$mb_srv['stats_uptime']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-server"></i> Adresse</div>
            <div class="h5 mb-0"><code><?= htmlspecialchars((string)$mb_srv['hostname']) ?>:<?= (int)$mb_srv['port'] ?></code></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-link"></i> Verbinden</div>
            <div class="h5 mb-0 mt-1">
                <a href="mumble://<?= htmlspecialchars((string)$mb_srv['hostname']) ?>:<?= (int)$mb_srv['port'] ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Mumble
                </a>
            </div>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-headphones"></i> Channel-Viewer</span>
                <button class="btn btn-sm btn-outline-secondary" id="mb-viewer-refresh"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body p-0"
                 id="mb-viewer-content"
                 data-viewer-url="/mumble/api/viewer?id=<?= $mb_sid ?>"
                 data-kick-url="/mumble/api/kick?id=<?= $mb_sid ?>"
                 data-mute-url="/mumble/api/update-user?id=<?= $mb_sid ?>"
                 data-csrf="<?= htmlspecialchars($mb_csrf) ?>"
                 data-can-manage="<?= $mumble->canManageServer($mb_sid) ? '1' : '0' ?>"
                 data-server-name="<?= htmlspecialchars((string)$mb_srv['name']) ?>">
                <p class="text-muted small p-3 mb-0"><i class="bi bi-hourglass-split"></i> Lade…</p>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="bi bi-graph-up"></i> Statistiken</strong>
            <div class="btn-group btn-group-sm" id="mb-chart-range"
                 data-url="/mumble/api/server-history?id=<?= $mb_sid ?>">
                <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
                <button class="btn btn-outline-secondary" data-range="7d">7T</button>
                <button class="btn btn-outline-secondary" data-range="30d">30T</button>
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-2"><div class="card">
                <div class="card-header py-1 small"><i class="bi bi-people"></i> Nutzer</div>
                <div class="card-body p-1"><canvas id="mb-chart-users" height="100"></canvas></div>
            </div></div>
            <div class="col-6 mb-2"><div class="card">
                <div class="card-header py-1 small"><i class="bi bi-speedometer2"></i> Bandbreite</div>
                <div class="card-body p-1"><canvas id="mb-chart-bw" height="100"></canvas></div>
            </div></div>
            <div class="col-6 mb-2"><div class="card">
                <div class="card-header py-1 small"><i class="bi bi-cpu"></i> CPU &amp; RAM</div>
                <div class="card-body p-1"><canvas id="mb-chart-cpuram" height="100"></canvas></div>
            </div></div>
            <div class="col-6 mb-2"><div class="card">
                <div class="card-header py-1 small"><i class="bi bi-clock"></i> Ping</div>
                <div class="card-body p-1"><canvas id="mb-chart-ping" height="100"></canvas></div>
            </div></div>
        </div>
        <p class="text-muted small" id="mb-chart-note" style="display:none">
            <i class="bi bi-info-circle"></i> Noch keine historischen Daten.
        </p>
    </div>
</div>

<script src="/plugins/esse-mumble/assets/chart.umd.min.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-edit.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-stats.js"></script>
<?php
