<?php
declare(strict_types=1);

/*
 * Verwaltet ausschliesslich die Host-Admin-Delegation (mumble_host_admin +
 * Host-Zuweisung). Die uebrigen Mumble-Rechte (mumble_admin, mumble_hosts,
 * mumble_quota, mumble_view) sind einfache rollenbasierte Rechte ohne
 * Zusatzdaten und werden bewusst nur ueber die zentrale esse-cms-Seite
 * /admin/roles vergeben, um nicht zwei Oberflaechen fuer dasselbe Recht zu
 * haben.
 */

use Esse\Auth;
use Esse\DB;

if (!Auth::meetsRole('admin') && !Auth::can('mumble_admin')) {
    http_response_code(403); echo '403 Forbidden'; exit;
}

$tu  = DB::table('users');
$tha = DB::table('mumble_host_admin');
$th  = DB::table('mumble_host');

$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// -- POST actions --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }

    $action = $_POST['_action'] ?? '';

    // AJAX: assign a user as host admin for a specific host
    if ($action === 'add_host_admin') {
        header('Content-Type: application/json');
        $userId = (int)($_POST['user_id'] ?? 0);
        $hostId = (int)($_POST['host_id'] ?? 0);

        if (!$userId || !$hostId) { echo json_encode(['error' => 'invalid']); exit; }

        // Ensure user has the mumble_host_admin permission first
        \EsseMumble\MumbleRepository::setUserPermission($userId, 'mumble_host_admin', true);

        DB::query(
            "INSERT IGNORE INTO `{$tha}` (host_id, user_id) VALUES (?, ?)",
            [$hostId, $userId]
        );

        $user = DB::fetch("SELECT display_name FROM `{$tu}` WHERE id = ?", [$userId]);
        echo json_encode(['ok' => true, 'display_name' => $user['display_name'] ?? '']); exit;
    }

    // AJAX: remove a user from host admin
    if ($action === 'remove_host_admin') {
        header('Content-Type: application/json');
        $userId = (int)($_POST['user_id'] ?? 0);
        $hostId = (int)($_POST['host_id'] ?? 0);

        if (!$userId || !$hostId) { echo json_encode(['error' => 'invalid']); exit; }

        DB::query("DELETE FROM `{$tha}` WHERE host_id = ? AND user_id = ?", [$hostId, $userId]);

        // If user has no more host assignments, revoke the permission too
        $remaining = (int)DB::value("SELECT COUNT(*) FROM `{$tha}` WHERE user_id = ?", [$userId]);
        if ($remaining === 0) {
            \EsseMumble\MumbleRepository::setUserPermission($userId, 'mumble_host_admin', false);
        }

        echo json_encode(['ok' => true]); exit;
    }
}

// -- GET: load data --
$hostAdminMap = \EsseMumble\MumbleRepository::getHostAdminMap();
$hosts        = DB::fetchAll("SELECT id, name FROM `{$th}` ORDER BY name ASC", []) ?: [];

$csrf = Auth::csrfToken();

$pageTitle = 'Mumble Host-Admins';
$activeNav = 'admin/mumble-permissions';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4" id="mb-page-config" data-csrf="<?= htmlspecialchars($csrf) ?>">
    <h4 class="mb-0"><i class="bi bi-headphones"></i> Mumble Host-Admins</h4>
</div>

<p class="text-secondary small">
    Hier werden Nutzer als delegierte Verwalter (<code>mumble_host_admin</code>) einzelnen
    Mumble-Hosts zugeordnet. Die allgemeinen Mumble-Rechte
    (<em>Fremdserver verwalten</em>, <em>Hosts verwalten</em>, <em>Quotas verwalten</em>)
    werden rollenbasiert unter <a href="/admin/roles">Rollen &amp; Rechte</a> vergeben.
</p>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Host-Admin Zuweisungen -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header py-2">
                <strong><i class="bi bi-server"></i> Host-Admin Zuweisungen</strong>
            </div>
            <?php if (empty($hosts)): ?>
            <div class="card-body text-muted small">Keine Hosts vorhanden.</div>
            <?php else: ?>
            <div class="list-group list-group-flush" id="host-admin-list">
                <?php foreach ($hosts as $host):
                    $admins = $hostAdminMap[(int)$host['id']] ?? [];
                ?>
                <div class="list-group-item" data-hid="<?= (int)$host['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="small"><?= htmlspecialchars((string)$host['name']) ?></strong>
                        <button class="btn btn-xs btn-outline-primary py-0 px-1 btn-add-host-admin mb-fs-75"
                                data-hid="<?= (int)$host['id'] ?>"
                                data-hname="<?= htmlspecialchars((string)$host['name']) ?>">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="ha-badge-list d-flex flex-wrap gap-1">
                        <?php if (empty($admins)): ?>
                        <span class="text-muted small ha-empty">Kein Host-Admin</span>
                        <?php else: foreach ($admins as $adm): ?>
                        <span class="badge bg-secondary d-flex align-items-center gap-1"
                              data-uid="<?= (int)$adm['user_id'] ?>">
                            <?= htmlspecialchars($adm['display_name']) ?>
                            <button type="button"
                                class="btn-close btn-close-white btn-remove-host-admin mb-fs-50"
                                data-hid="<?= (int)$host['id'] ?>"
                                data-uid="<?= (int)$adm['user_id'] ?>"></button>
                        </span>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal: Host-Admin hinzufügen -->
<div class="modal fade" id="modalAddHostAdmin" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Host-Admin hinzufügen</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Host: <strong id="modal-host-name"></strong></p>
                <input type="text" id="ha-user-search" class="form-control form-control-sm"
                       placeholder="Username oder E-Mail…" autocomplete="off">
                <div id="ha-user-results" class="list-group mt-1"></div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/mumble-permissions.js"></script>

<?php
$content = ob_get_clean();

require ESSE_ROOT . '/admin/layout.php';
