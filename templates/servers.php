<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();

$mb_isAdmin     = $mumble->canAdminAll();
$mb_isHostAdmin = $mumble->isHostAdmin();
$mb_uid         = (int)Auth::id();
$mb_adminHostIds = $mb_isHostAdmin ? $mumble->getAdminHostIds() : [];

$mb_hostId = isset($hostId) ? (int)$hostId : null;
$mb_host   = null;

if ($mb_hostId !== null) {
    $mb_host = $mumble->getHost($mb_hostId);
    if (!$mb_host || !$mumble->canSeeHost($mb_hostId)) { \Esse\Router::abort(403); return; }

    $mb_viewAll = true;
    $mb_servers = $mumble->getServersForHost($mb_hostId);
} else {
    $mb_viewAll = ($viewAll ?? false) && ($mb_isAdmin || $mb_isHostAdmin);
    $mb_servers = $mb_viewAll
        ? ($mb_isAdmin ? $mumble->listAllServers() : $mumble->listServersByHostAdmin($mb_uid))
        : $mumble->listServersByOwner($mb_uid);
}

$mb_csrf = Auth::csrfToken();

$mb_ownerIds  = array_unique(array_column($mb_servers, 'owner_user_id'));
$mb_showOwner = $mb_viewAll && count($mb_ownerIds) > 1;

$mb_statusClass = static fn(string $s): string => match ($s) {
    'running'  => 'success', 'stopped' => 'secondary',
    'error'    => 'danger',  'creating' => 'warning', default => 'info',
};
$mb_statusIcon = static fn(string $s): string => match ($s) {
    'running'  => 'bi-check-circle-fill', 'stopped' => 'bi-stop-fill',
    'error'    => 'bi-exclamation-triangle', 'creating' => 'bi-hourglass-split',
    default    => 'bi-question-circle',
};

?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($mb_host): ?>
<div class="mb-3">
    <a href="/mumble/host-stats/<?= (int)$mb_host['id'] ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Host-Statistiken</a>
</div>
<?php endif; ?>

<?php if ($mumble->canCreate()): ?>
<div class="mb-3">
    <a href="/mumble/new" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Neuer Server</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-list"></i>
        <?= $mb_host ? 'Server auf '.htmlspecialchars((string)$mb_host['name']) : 'Mumble-Server' ?>
    </div>
    <div class="card-body">
        <?php if (empty($mb_servers)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-headphones display-4 d-block mb-3"></i>
            <p><?= $mb_host ? 'Auf diesem Host sind keine Mumble-Server vorhanden.' : 'Du hast noch keinen Mumble-Server angelegt.' ?></p>
            <?php if (!$mb_host && $mumble->canCreate()): ?>
            <a class="btn btn-primary" href="/mumble/new"><i class="bi bi-plus-lg"></i> Jetzt ersten Server anlegen</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Server</th>
                        <?php if ($mb_showOwner): ?><th>Besitzer</th><?php endif; ?>
                        <th>Host</th>
                        <th>Adresse</th>
                        <th>Status</th>
                        <th>Online</th>
                        <th class="text-end" style="min-width:220px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mb_servers as $s):
                    $status = (string)$s['status'];
                    $cls    = $mb_statusClass($status);
                    $ico    = $mb_statusIcon($status);
                ?>
                    <tr data-server-id="<?= (int)$s['id'] ?>"
                        data-latest-image="<?= htmlspecialchars((string)($s['host_latest_image'] ?? '')) ?>">
                        <td><?= (int)$s['id'] ?></td>
                        <td><strong><?= htmlspecialchars((string)$s['name']) ?></strong></td>
                        <?php if ($mb_showOwner): ?>
                        <td>
                            <?= htmlspecialchars((string)($s['owner_name'] ?? '–')) ?>
                            <?php if (!empty($s['member_names'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars((string)$s['member_names']) ?></small>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars((string)$s['host_name']) ?></td>
                        <td><code><?= htmlspecialchars((string)$s['hostname']) ?>:<?= (int)$s['port'] ?></code></td>
                        <td>
                            <span class="badge text-bg-<?= $cls ?>">
                                <i class="bi <?= $ico ?>"></i> <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td class="js-online">
                            <span class="js-online-num"><?= (int)$s['stats_online'] ?></span>
                            / <?= (int)$s['max_users'] ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if ($status === 'stopped' || $status === 'error'): ?>
                                <form method="post" action="/mumble/edit/<?= (int)$s['id'] ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                    <input type="hidden" name="_action" value="start">
                                    <button class="btn btn-success" title="Starten"><i class="bi bi-play-fill"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($status === 'running'): ?>
                                <form method="post" action="/mumble/edit/<?= (int)$s['id'] ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                    <input type="hidden" name="_action" value="stop">
                                    <button class="btn btn-warning" title="Stoppen"><i class="bi bi-stop-fill"></i></button>
                                </form>
                                <form method="post" action="/mumble/edit/<?= (int)$s['id'] ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                    <input type="hidden" name="_action" value="restart">
                                    <button class="btn btn-info" title="Neustart"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                                <?php endif; ?>
                                <a class="btn btn-outline-secondary" title="Logs" href="/mumble/logs/<?= (int)$s['id'] ?>">
                                    <i class="bi bi-file-text"></i>
                                </a>
                                <a class="btn btn-outline-secondary" title="Einstellungen" href="/mumble/edit/<?= (int)$s['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($mb_isAdmin || ($mb_isHostAdmin && in_array((int)$s['host_id'], $mb_adminHostIds))): ?>
                                <form method="post" action="/mumble/edit/<?= (int)$s['id'] ?>" class="d-inline"
                                      data-confirm="Server &quot;<?= htmlspecialchars((string)$s['name'], ENT_QUOTES) ?>&quot; wirklich endgültig löschen?">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                    <input type="hidden" name="_action" value="delete">
                                    <button class="btn btn-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="/plugins/esse-mumble/assets/mumble-confirm.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-servers.js"></script>
<?php
