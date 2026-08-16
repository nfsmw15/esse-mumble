<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();

$mb_isAdmin   = $mumble->canAdminAll();
$mb_isHostAdm = $mumble->isHostAdmin();
$mb_showHosts = $mb_isAdmin || $mb_isHostAdm;

?>
<?php if ($mb_showHosts): ?>
<!-- Admin / Host-Admin: Host-Karten -->
<div id="db-root" data-admin="<?= $mb_isAdmin ? '1' : '0' ?>">

    <!-- Zusammenfassungs-Karten -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-server"></i> Hosts</div>
                <div class="h4 mb-0" id="db-stat-hosts">–</div>
            </div></div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-headphones"></i> Server laufend</div>
                <div class="h4 mb-0" id="db-stat-servers">–</div>
            </div></div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-people"></i> Nutzer online</div>
                <div class="h4 mb-0" id="db-stat-users">–</div>
            </div></div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-speedometer2"></i> Bandbreite</div>
                <div class="h4 mb-0" id="db-stat-bw">–</div>
            </div></div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-server"></i> Hosts</h5>
        <small class="text-muted" id="db-last-updated"></small>
    </div>
    <div class="row" id="db-hosts">
        <div class="col-12 text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Lade…</div>
    </div>

    <!-- Online-Nutzer-Tabelle -->
    <div class="mt-4 mb-hidden" id="db-users-wrap">
        <h5 class="mb-3"><i class="bi bi-headphones"></i> Online-Nutzer</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nutzer</th><th>Server</th><th>Channel</th>
                        <th>KB/s</th><th>UDP-Ping</th><th>Idle</th><th>OS</th><th>🔇</th>
                    </tr>
                </thead>
                <tbody id="db-users-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Charts -->
    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Statistiken (alle Hosts)</h5>
            <div class="btn-group btn-group-sm" id="db-chart-range">
                <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
                <button class="btn btn-outline-secondary" data-range="7d">7 Tage</button>
                <button class="btn btn-outline-secondary" data-range="30d">30 Tage</button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-people"></i> Nutzer online</div>
            <div class="card-body p-2"><canvas id="chart-users" height="140"></canvas></div></div></div>
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-speedometer2"></i> Bandbreite (B/s)</div>
            <div class="card-body p-2"><canvas id="chart-bw" height="140"></canvas></div></div></div>
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-cpu"></i> CPU &amp; RAM</div>
            <div class="card-body p-2"><canvas id="chart-cpuram" height="140"></canvas></div></div></div>
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-clock"></i> Ping Ø (ms)</div>
            <div class="card-body p-2"><canvas id="chart-ping" height="140"></canvas></div></div></div>
        </div>
        <p class="text-muted small mb-hidden" id="db-chart-note">
            <i class="bi bi-info-circle"></i> Noch keine historischen Daten.
        </p>
    </div>

</div>

<?php else: ?>
<!-- Mitglied: eigene Server -->
<?php
$mb_my_servers  = $mumble->getServers();
$mb_running     = count(array_filter($mb_my_servers, fn($s) => $s['status'] === 'running'));
$mb_offline     = count($mb_my_servers) - $mb_running;
$mb_users_total = array_sum(array_column($mb_my_servers, 'stats_online'));
?>

<div class="row mb-4">
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-headphones"></i> Server laufend</div>
            <div class="h4 mb-0 text-success"><?= $mb_running ?></div>
        </div></div>
    </div>
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-stop-fill"></i> Server offline</div>
            <div class="h4 mb-0 <?= $mb_offline > 0 ? 'text-secondary' : 'text-muted' ?>"><?= $mb_offline ?></div>
        </div></div>
    </div>
    <div class="col-md-4 col-sm-6 mb-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="text-muted small mb-1"><i class="bi bi-people"></i> Nutzer online</div>
            <div class="h4 mb-0"><?= $mb_users_total ?></div>
        </div></div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-headphones"></i> Meine Server</h5>
    <?php if ($mumble->canCreate()): ?>
    <a href="/mumble/new" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Neuer Server</a>
    <?php endif; ?>
</div>
<?php if (empty($mb_my_servers)): ?>
<div class="alert alert-info">Du hast noch keine Server.</div>
<?php else: ?>
<div class="row" id="db-root">
    <?php foreach ($mb_my_servers as $srv):
        $badgeCls = match ($srv['status']) {
            'running' => 'success', 'stopped' => 'secondary', 'error' => 'danger', 'creating' => 'warning', default => 'info',
        };
        $userPct = $srv['max_users'] > 0 ? min(100, round(($srv['stats_online'] / $srv['max_users']) * 100)) : 0;
        $userPctCls = 'mb-w-'.max(0, min(100, (int)round($userPct / 5) * 5));
    ?>
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars((string)$srv['name']) ?></strong>
                <span class="badge text-bg-<?= $badgeCls ?>"><?= htmlspecialchars((string)$srv['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Adresse</small>
                    <small><code><?= htmlspecialchars((string)$srv['hostname']) ?>:<?= (int)$srv['port'] ?></code></small>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Nutzer</small>
                    <small><?= (int)$srv['stats_online'] ?> / <?= (int)$srv['max_users'] ?></small>
                </div>
                <div class="progress mb-2 mb-progress-h5">
                    <div class="progress-bar <?= $userPct >= 90 ? 'bg-danger' : ($userPct >= 70 ? 'bg-warning' : 'bg-success') ?> <?= $userPctCls ?>"></div>
                </div>
            </div>
            <div class="card-footer">
                <div class="btn-group btn-group-sm w-100">
                    <a href="/mumble/edit/<?= (int)$srv['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-speedometer2"></i> Details
                    </a>
                    <a href="/mumble/config/<?= (int)$srv['id'] ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-gear"></i> Einstellungen
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Charts für Mitglieder -->
    <div class="col-12 mt-2">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Statistiken</h5>
            <div class="btn-group btn-group-sm" id="db-chart-range">
                <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
                <button class="btn btn-outline-secondary" data-range="7d">7 Tage</button>
                <button class="btn btn-outline-secondary" data-range="30d">30 Tage</button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-people"></i> Nutzer online</div>
            <div class="card-body p-2"><canvas id="chart-users" height="140"></canvas></div></div></div>
            <div class="col-md-6 mb-4"><div class="card"><div class="card-header py-2 small"><i class="bi bi-speedometer2"></i> Bandbreite (B/s)</div>
            <div class="card-body p-2"><canvas id="chart-bw" height="140"></canvas></div></div></div>
        </div>
        <p class="text-muted small mb-hidden" id="db-chart-note">
            <i class="bi bi-info-circle"></i> Noch keine historischen Daten.
        </p>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/chart.umd.min.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-dashboard.js"></script>
<?php
