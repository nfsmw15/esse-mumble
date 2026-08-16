<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();
$mb_hid  = $hostId ?? (int)($_GET['id'] ?? 0);
$mb_host = $mumble->getHost($mb_hid);

if (!$mb_host || !$mumble->canSeeHost($mb_hid)) { \Esse\Router::abort(403); return; }

// Session-Lock vor den Live-Agent-Calls freigeben, sonst blockiert ein hängender
// Host jeden weiteren Request derselben Browser-Session (siehe ajax.php).
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$mb_live    = $mumble->getLiveHostData($mb_hid);
$mb_isAdmin = $mumble->canAdminAll() || $mumble->isHostAdmin();

$mb_uptime = static function(int $secs): string {
    if ($secs <= 0) return '–';
    $d = (int)floor($secs / 86400); $h = (int)floor(($secs % 86400) / 3600); $m = (int)floor(($secs % 3600) / 60);
    $parts = []; if ($d) $parts[] = $d.'d'; if ($h) $parts[] = $h.'h'; if ($m) $parts[] = $m.'m';
    return $parts ? implode(' ', $parts) : $secs.'s';
};

?>
<div class="mb-3">
    <a href="/mumble/dashboard" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<!-- Zusammenfassungs-Karten -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-server"></i> Server laufend</div>
            <div class="h4 mb-0 text-success"><?= $mb_live['running'] ?></div>
            <div class="text-muted small"><?= $mb_live['stopped'] ?> gestoppt</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-people"></i> Nutzer online</div>
            <div class="h4 mb-0"><?= $mb_live['users_total'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-speedometer2"></i> Bandbreite</div>
            <div class="h4 mb-0" id="mb-host-bw"><?php
                $bw = $mb_live['bandwidth'];
                if ($bw < 1024) echo $bw.' B/s';
                elseif ($bw < 1048576) echo round($bw/1024,1).' KB/s';
                else echo round($bw/1048576,2).' MB/s';
            ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-clock"></i> Ping Ø</div>
            <div class="h4 mb-0"><?= $mb_live['ping_avg'] > 0 ? $mb_live['ping_avg'].' ms' : '–' ?></div>
        </div></div>
    </div>
</div>

<!-- Server-Übersicht -->
<?php if (!empty($mb_live['servers'])): ?>
<div class="row mb-4">
    <?php foreach ($mb_live['servers'] as $srv):
        $live    = $srv['live'] ?? [];
        $running = $srv['status'] === 'running';
        $uCount  = (int)($live['user_count'] ?? $srv['stats_online']);
        $maxU    = (int)$srv['max_users'];
        $pct     = $maxU > 0 ? min(100, round($uCount / $maxU * 100)) : 0;
        $pctCls  = 'mb-w-'.max(0, min(100, (int)round($pct / 5) * 5));
        $barCls  = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
    ?>
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong><?= htmlspecialchars((string)$srv['name']) ?></strong>
                <span class="badge text-bg-<?= $running ? 'success' : 'secondary' ?>">
                    <?= $running ? 'online' : 'offline' ?>
                </span>
            </div>
            <div class="card-body py-2">
                <?php if ($mb_isAdmin ?? false): ?>
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Inhaber</small>
                    <small><?= htmlspecialchars((string)($srv['owner_name'] ?? '–')) ?></small>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Nutzer</small>
                    <small><?= $uCount ?> / <?= $maxU ?></small>
                </div>
                <div class="progress mb-2 mb-progress-h4">
                    <div class="progress-bar <?= $barCls ?> <?= $pctCls ?>"></div>
                </div>
                <?php if ($running && !empty($live)): ?>
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Uptime</small>
                    <small><?= $mb_uptime((int)($live['uptime_secs'] ?? 0)) ?></small>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">CPU / RAM</small>
                    <small><?= $live['cpu_percent'] ?? 0 ?>% / <?= $live['mem_mb'] ?? 0 ?> MB</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer py-2">
                <div class="btn-group btn-group-sm w-100">
                    <a href="/mumble/server-stats/<?= (int)$srv['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-speedometer2"></i> Details
                    </a>
                    <a href="/mumble/edit/<?= (int)$srv['id'] ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-gear"></i> Einstellungen
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Statistiken</h5>
    <div class="btn-group btn-group-sm" id="mb-chart-range"
         data-url="/mumble/api/host-history?id=<?= $mb_hid ?>">
        <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
        <button class="btn btn-outline-secondary" data-range="7d">7 Tage</button>
        <button class="btn btn-outline-secondary" data-range="30d">30 Tage</button>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3"><div class="card">
        <div class="card-header py-2 small"><i class="bi bi-people"></i> Nutzer online</div>
        <div class="card-body p-2"><canvas id="mb-chart-users" height="120"></canvas></div>
    </div></div>
    <div class="col-md-6 mb-3"><div class="card">
        <div class="card-header py-2 small"><i class="bi bi-speedometer2"></i> Bandbreite (B/s)</div>
        <div class="card-body p-2"><canvas id="mb-chart-bw" height="120"></canvas></div>
    </div></div>
    <div class="col-md-6 mb-3"><div class="card">
        <div class="card-header py-2 small"><i class="bi bi-cpu"></i> CPU &amp; RAM</div>
        <div class="card-body p-2"><canvas id="mb-chart-cpuram" height="120"></canvas></div>
    </div></div>
    <div class="col-md-6 mb-3"><div class="card">
        <div class="card-header py-2 small"><i class="bi bi-clock"></i> Ping Ø (ms)</div>
        <div class="card-body p-2"><canvas id="mb-chart-ping" height="120"></canvas></div>
    </div></div>
</div>
<p class="text-muted small mb-hidden" id="mb-chart-note">
    <i class="bi bi-info-circle"></i> Noch keine historischen Daten vorhanden.
</p>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/chart.umd.min.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-stats.js"></script>
<?php
