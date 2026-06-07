<?php
declare(strict_types=1);

use Esse\Auth;
use Esse\DB;

if (!Auth::meetsRole('admin') && !Auth::can('mumble_admin')) {
    http_response_code(403); echo '403 Forbidden'; exit;
}

$mumble = new \EsseMumble\MumbleRepository();

$tu  = DB::table('users');
$tup = DB::table('user_permissions');
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

    // AJAX: toggle a single mumble_* permission for a user
    if ($action === 'toggle_perm') {
        header('Content-Type: application/json');
        $userId   = (int)($_POST['user_id']    ?? 0);
        $permSlug = preg_replace('/[^a-z_]/', '', $_POST['perm'] ?? '');
        $allowed  = ['mumble_admin', 'mumble_hosts', 'mumble_quota', 'mumble_host_admin'];

        if (!$userId || !in_array($permSlug, $allowed, true)) {
            echo json_encode(['error' => 'invalid']); exit;
        }

        $current = DB::fetch(
            "SELECT granted FROM `{$tup}` WHERE user_id = ? AND permission_slug = ?",
            [$userId, $permSlug]
        );
        $nowGranted = !($current && (bool)$current['granted']);

        \EsseMumble\MumbleRepository::setUserPermission($userId, $permSlug, $nowGranted);

        // If revoking mumble_host_admin, clean up host assignments too
        if ($permSlug === 'mumble_host_admin' && !$nowGranted) {
            DB::query("DELETE FROM `{$tha}` WHERE user_id = ?", [$userId]);
        }

        echo json_encode(['granted' => $nowGranted]); exit;
    }

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
$usersWithPerms = \EsseMumble\MumbleRepository::getUsersWithMumblePerms();
$hostAdminMap   = \EsseMumble\MumbleRepository::getHostAdminMap();
$hosts          = DB::fetchAll("SELECT id, name FROM `{$th}` ORDER BY name ASC", []) ?: [];

$mumblePermLabels = [
    'mumble_admin'      => 'Alles verwalten',
    'mumble_hosts'      => 'Hosts verwalten',
    'mumble_quota'      => 'Quotas verwalten',
    'mumble_host_admin' => 'Host-Admin',
];

$csrf = Auth::csrfToken();

$pageTitle = 'Mumble Rechte';
$activeNav = 'mumble-permissions';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-headphones"></i> Mumble Rechte</h4>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Benutzerrechte -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-person-lock"></i> Benutzerrechte</strong>
                <button class="btn btn-sm btn-outline-primary" id="btn-add-perm">
                    <i class="bi bi-plus-lg"></i> Recht vergeben
                </button>
            </div>
            <div id="add-perm-form" class="card-body border-bottom pb-3" style="display:none">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-5">
                        <label class="form-label small mb-1">Benutzer suchen</label>
                        <input type="text" id="perm-user-search" class="form-control form-control-sm"
                               placeholder="Username oder E-Mail…" autocomplete="off">
                        <div id="perm-user-results" class="list-group mt-1" style="display:none"></div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Recht</label>
                        <select id="perm-select" class="form-select form-select-sm">
                            <?php foreach ($mumblePermLabels as $slug => $label): ?>
                            <option value="<?= $slug ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <button id="btn-grant-perm" class="btn btn-sm btn-primary w-100" disabled>
                            <i class="bi bi-check-lg"></i> Vergeben
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($usersWithPerms)): ?>
            <div class="card-body text-muted small">Noch keine Mumble-Rechte vergeben.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Benutzer</th>
                            <th class="text-center" title="Alles verwalten">Admin</th>
                            <th class="text-center" title="Hosts verwalten">Hosts</th>
                            <th class="text-center" title="Quotas verwalten">Quota</th>
                            <th class="text-center" title="Host-Admin">H-Adm</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usersWithPerms as $u): ?>
                    <tr data-uid="<?= (int)$u['id'] ?>">
                        <td>
                            <div><?= htmlspecialchars((string)$u['display_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars((string)$u['role']) ?></small>
                        </td>
                        <?php foreach (array_keys($mumblePermLabels) as $perm): ?>
                        <td class="text-center">
                            <button type="button"
                                class="btn btn-sm perm-toggle <?= $u[$perm] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                data-uid="<?= (int)$u['id'] ?>"
                                data-perm="<?= $perm ?>"
                                title="<?= htmlspecialchars($mumblePermLabels[$perm]) ?>">
                                <i class="bi bi-<?= $u[$perm] ? 'check-lg' : 'dash' ?>"></i>
                            </button>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Host-Admin Zuweisungen -->
    <div class="col-lg-5">
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
                        <button class="btn btn-xs btn-outline-primary py-0 px-1 btn-add-host-admin"
                                data-hid="<?= (int)$host['id'] ?>"
                                data-hname="<?= htmlspecialchars((string)$host['name']) ?>"
                                style="font-size:.75rem">
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
                                class="btn-close btn-close-white btn-remove-host-admin"
                                style="font-size:.5rem"
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

<?php
$content = ob_get_clean();

$extraScripts = '<script>
const CSRF = ' . json_encode($csrf) . ';
const API  = "/admin/mumble-permissions";

// -- Permission toggle --
document.querySelectorAll(".perm-toggle").forEach(btn => {
    btn.addEventListener("click", async () => {
        const uid  = btn.dataset.uid;
        const perm = btn.dataset.perm;
        const fd   = new FormData();
        fd.append("_action", "toggle_perm");
        fd.append("user_id", uid);
        fd.append("perm",    perm);
        fd.append("_csrf",   CSRF);

        const r   = await fetch(API, { method: "POST", body: fd });
        const res = await r.json();
        if (res.granted) {
            btn.classList.replace("btn-outline-secondary", "btn-success");
            btn.innerHTML = "<i class=\"bi bi-check-lg\"></i>";
        } else {
            btn.classList.replace("btn-success", "btn-outline-secondary");
            btn.innerHTML = "<i class=\"bi bi-dash\"></i>";
            if (perm === "mumble_host_admin") {
                document.querySelectorAll(".btn-remove-host-admin[data-uid=\"" + uid + "\"]").forEach(x => {
                    x.closest(".badge")?.remove();
                });
                document.querySelectorAll("#host-admin-list .ha-badge-list").forEach(list => {
                    if (!list.querySelector(".badge")) {
                        list.innerHTML = "<span class=\"text-muted small ha-empty\">Kein Host-Admin</span>";
                    }
                });
            }
        }
    });
});

// -- Add-perm panel toggle --
document.getElementById("btn-add-perm").addEventListener("click", () => {
    const f = document.getElementById("add-perm-form");
    f.style.display = f.style.display === "none" ? "" : "none";
});

// -- User search for permission grant --
let permSelectedUser = null;
const permSearch  = document.getElementById("perm-user-search");
const permResults = document.getElementById("perm-user-results");
const btnGrant    = document.getElementById("btn-grant-perm");

permSearch.addEventListener("input", async () => {
    permSelectedUser = null;
    btnGrant.disabled = true;
    const q = permSearch.value.trim();
    if (q.length < 2) { permResults.style.display = "none"; return; }
    const r   = await fetch("/mumble/api/user-search?q=" + encodeURIComponent(q) + "&limit=8");
    const res = await r.json();
    permResults.innerHTML = "";
    if (!res.length) { permResults.style.display = "none"; return; }
    res.forEach(u => {
        const a = document.createElement("button");
        a.type = "button";
        a.className = "list-group-item list-group-item-action list-group-item-dark py-1 small";
        a.textContent = u.display_name;
        a.addEventListener("click", () => {
            permSelectedUser = u;
            permSearch.value = u.display_name;
            permResults.style.display = "none";
            btnGrant.disabled = false;
        });
        permResults.appendChild(a);
    });
    permResults.style.display = "";
});

document.getElementById("btn-grant-perm").addEventListener("click", async () => {
    if (!permSelectedUser) return;
    const perm = document.getElementById("perm-select").value;
    const fd   = new FormData();
    fd.append("_action", "toggle_perm");
    fd.append("user_id", permSelectedUser.id);
    fd.append("perm",    perm);
    fd.append("_csrf",   CSRF);

    const r   = await fetch(API, { method: "POST", body: fd });
    const res = await r.json();
    if (res.granted) { location.reload(); }
});

// -- Host-Admin modal --
let haHostId  = null;
let bsModal   = null;

document.querySelectorAll(".btn-add-host-admin").forEach(btn => {
    btn.addEventListener("click", () => {
        haHostId = parseInt(btn.dataset.hid);
        document.getElementById("modal-host-name").textContent = btn.dataset.hname;
        document.getElementById("ha-user-search").value = "";
        document.getElementById("ha-user-results").innerHTML = "";
        if (!bsModal) bsModal = new bootstrap.Modal(document.getElementById("modalAddHostAdmin"));
        bsModal.show();
    });
});

const haSearch  = document.getElementById("ha-user-search");
const haResults = document.getElementById("ha-user-results");

haSearch.addEventListener("input", async () => {
    const q = haSearch.value.trim();
    if (q.length < 2) { haResults.innerHTML = ""; return; }
    const r   = await fetch("/mumble/api/user-search?q=" + encodeURIComponent(q) + "&limit=8");
    const res = await r.json();
    haResults.innerHTML = "";
    res.forEach(u => {
        const a = document.createElement("button");
        a.type = "button";
        a.className = "list-group-item list-group-item-action list-group-item-dark py-1 small";
        a.textContent = u.display_name;
        a.addEventListener("click", async () => {
            const fd = new FormData();
            fd.append("_action", "add_host_admin");
            fd.append("user_id", u.id);
            fd.append("host_id", haHostId);
            fd.append("_csrf",   CSRF);

            const r2   = await fetch(API, { method: "POST", body: fd });
            const res2 = await r2.json();
            if (res2.ok) {
                bsModal.hide();
                const hostRow   = document.querySelector("#host-admin-list [data-hid=\"" + haHostId + "\"]");
                const badgeList = hostRow?.querySelector(".ha-badge-list");
                if (badgeList) {
                    badgeList.querySelector(".ha-empty")?.remove();
                    const badge = document.createElement("span");
                    badge.className = "badge bg-secondary d-flex align-items-center gap-1";
                    badge.dataset.uid = u.id;
                    badge.innerHTML = escHtml(res2.display_name) + "<button type=\"button\" class=\"btn-close btn-close-white btn-remove-host-admin\" style=\"font-size:.5rem\" data-hid=\"" + haHostId + "\" data-uid=\"" + u.id + "\"></button>";
                    badge.querySelector(".btn-remove-host-admin").addEventListener("click", removeHostAdmin);
                    badgeList.appendChild(badge);
                }
                const toggleBtn = document.querySelector(".perm-toggle[data-uid=\"" + u.id + "\"][data-perm=\"mumble_host_admin\"]");
                if (toggleBtn) {
                    toggleBtn.classList.replace("btn-outline-secondary", "btn-success");
                    toggleBtn.innerHTML = "<i class=\"bi bi-check-lg\"></i>";
                }
            }
        });
        haResults.appendChild(a);
    });
});

function escHtml(s) {
    return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}

async function removeHostAdmin(e) {
    const btn = e.currentTarget;
    const uid = parseInt(btn.dataset.uid);
    const hid = parseInt(btn.dataset.hid);
    const fd  = new FormData();
    fd.append("_action", "remove_host_admin");
    fd.append("user_id", uid);
    fd.append("host_id", hid);
    fd.append("_csrf",   CSRF);

    const r   = await fetch(API, { method: "POST", body: fd });
    const res = await r.json();
    if (res.ok) {
        const badge = btn.closest(".badge");
        const list  = badge?.parentElement;
        badge?.remove();
        if (list && !list.querySelector(".badge")) {
            list.innerHTML = "<span class=\"text-muted small ha-empty\">Kein Host-Admin</span>";
        }
        const hostCount = document.querySelectorAll(".btn-remove-host-admin[data-uid=\"" + uid + "\"]").length;
        if (hostCount === 0) {
            const toggleBtn = document.querySelector(".perm-toggle[data-uid=\"" + uid + "\"][data-perm=\"mumble_host_admin\"]");
            if (toggleBtn) {
                toggleBtn.classList.replace("btn-success", "btn-outline-secondary");
                toggleBtn.innerHTML = "<i class=\"bi bi-dash\"></i>";
            }
        }
    }
}

document.querySelectorAll(".btn-remove-host-admin").forEach(btn => {
    btn.addEventListener("click", removeHostAdmin);
});
</script>';

require ESSE_ROOT . '/admin/layout.php';
