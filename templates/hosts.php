<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();
$mb_canManage = $mumble->canManageHosts();
$mb_isHostAdm = $mumble->isHostAdmin();

if (!$mb_canManage && !$mb_isHostAdm) { \Esse\Router::abort(403); return; }

$mb_csrf = Auth::csrfToken();

// POST-Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }
    $action = $_POST['_action'] ?? $_GET['c'] ?? '';

    try {
        switch ($action) {
            case 'save':
                $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                $mumble->saveHost($_POST, $id);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Host gespeichert.'];
                header('Location: /mumble/hosts'); exit;
            case 'delete':
                $mumble->deleteHost((int)$_POST['id']);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Host gelöscht.'];
                header('Location: /mumble/hosts'); exit;
            case 'update_image':
                $res = $mumble->updateHostImage((int)$_POST['id'], (string)$_POST['image']);
                $_SESSION['flash'] = $res['ok']
                    ? ['type' => 'success', 'message' => 'Agent-Image wird aktualisiert.']
                    : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                header('Location: /mumble/hosts'); exit;
            case 'import_servers':
                $res = $mumble->importServersFromAgent((int)$_POST['id'], (int)Auth::id());
                $_SESSION['flash'] = $res['ok']
                    ? ['type' => 'success', 'message' => 'Importiert: '.$res['imported'].', übersprungen: '.$res['skipped'].'.']
                    : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                header('Location: /mumble/hosts'); exit;
            case 'host_admin_add':
                if (!$mb_canManage) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
                $mumble->addHostAdmin((int)$_POST['host_id'], (int)$_POST['user_id']);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Host-Admin hinzugefügt.'];
                header('Location: /mumble/hosts?edit='.(int)$_POST['host_id']); exit;
            case 'host_admin_remove':
                if (!$mb_canManage) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
                $mumble->removeHostAdmin((int)$_POST['host_id'], (int)$_POST['user_id']);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Host-Admin entfernt.'];
                header('Location: /mumble/hosts?edit='.(int)$_POST['host_id']); exit;
            case 'cron_key_gen':
                if (!$mb_canManage) throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
                $mumble->setCronKey(bin2hex(random_bytes(16)));
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cron-Key generiert.'];
                header('Location: /mumble/hosts'); exit;
        }
    } catch (\RuntimeException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        header('Location: /mumble/hosts'); exit;
    }
}

$mb_allHosts = $mumble->listHosts(false);
$mb_adminHostIds = $mb_isHostAdm ? $mumble->getAdminHostIds() : [];
$mb_hosts = $mb_canManage
    ? $mb_allHosts
    : array_filter($mb_allHosts, fn($h) => in_array((int)$h['id'], $mb_adminHostIds));

$mb_cron_key  = $mumble->getCronKey();
$mb_cron_last = (int)$mumble->getSetting('cron_last_run');
$mb_cron_age  = $mb_cron_last > 0 ? time() - $mb_cron_last : -1;
$mb_cron_cls  = $mb_cron_age < 0 ? 'danger' : ($mb_cron_age < 900 ? 'success' : ($mb_cron_age < 3600 ? 'warning' : 'danger'));
$mb_cron_txt  = $mb_cron_age < 0 ? 'Noch nie gelaufen' : ($mb_cron_age < 60 ? 'vor '.$mb_cron_age.'s' : ($mb_cron_age < 3600 ? 'vor '.gmdate('i\m s\s', $mb_cron_age) : 'vor '.gmdate('H\h i\m', $mb_cron_age)));
$mb_widget_scheme = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'https') === 'https') ? 'https' : 'http';
$mb_widget_base   = rtrim($mb_widget_scheme.'://'.(string)($_SERVER['HTTP_HOST'] ?? ''), '/');

$mb_edit  = null;
if ($mb_canManage && !empty($_GET['edit'])) $mb_edit = $mumble->getHost((int)$_GET['edit']);
$mb_isNew = $mb_canManage && isset($_GET['new']);

$mb_pings = [];
foreach ($mb_hosts as $h) {
    if ((int)$h['is_active'] !== 1) continue;
    $a = new \EsseMumble\MumbleAgent((string)$h['agent_url'], (string)$h['agent_token'], 3);
    $r = $a->ping();
    $mb_pings[(int)$h['id']] = $r['ok'] ? $r['data'] : false;
    if ($r['ok']) {
        $mumble->touchHostLastSeen((int)$h['id']);
        if (!empty($r['data']['latest_image'])) $mumble->updateHostLatestImage((int)$h['id'], (string)$r['data']['latest_image']);
    }
}

?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
        <?php if ($mb_canManage): ?>
    <a href="/mumble/hosts?new=1" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Neuer Host
    </a>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-list"></i> Bekannte Hosts</div>
            <div class="card-body p-0">
                <?php if (empty($mb_hosts)): ?>
                <p class="text-muted text-center py-4 mb-0">Noch kein Host konfiguriert.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr>
                            <th>Name</th><th>Hostname</th><th>Ports</th><th>Status</th><th class="text-end">Aktionen</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($mb_hosts as $h):
                            $ping = $mb_pings[(int)$h['id']] ?? null;
                            $online = $ping !== null ? ($ping !== false) : null;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$h['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars((string)$h['hostname']) ?></code></td>
                                <td><small><?= (int)$h['port_min'] ?>–<?= (int)$h['port_max'] ?></small></td>
                                <td>
                                    <?php if ((int)$h['is_active'] !== 1): ?>
                                        <span class="badge text-bg-secondary">inaktiv</span>
                                    <?php elseif ($online === true): ?>
                                        <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> online</span>
                                        <?php if (!empty($ping['mumble_image'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars((string)$ping['mumble_image']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($ping['update_available']) && !empty($ping['latest_image'])): ?>
                                        <br><span class="badge text-bg-warning"><i class="bi bi-arrow-up-circle"></i> Update: <?= htmlspecialchars((string)$ping['latest_image']) ?></span>
                                        <?php endif; ?>
                                    <?php elseif ($online === false): ?>
                                        <span class="badge text-bg-danger"><i class="bi bi-x-lg"></i> nicht erreichbar</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">unbekannt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (!empty($ping['update_available']) && !empty($ping['latest_image'])): ?>
                                    <form method="post" action="/mumble/hosts" class="d-inline"
                                          onsubmit="return confirm('Agent-Image aktualisieren?');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="update_image">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <input type="hidden" name="image" value="<?= htmlspecialchars((string)$ping['latest_image'], ENT_QUOTES) ?>">
                                        <button class="btn btn-sm btn-warning" title="Image aktualisieren"><i class="bi bi-arrow-up-circle"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-info" href="/mumble/host-stats/<?= (int)$h['id'] ?>" title="Server anzeigen">
                                        <i class="bi bi-list"></i>
                                    </a>
                                    <?php if ($online === true && ($mb_canManage || in_array((int)$h['id'], $mb_adminHostIds))): ?>
                                    <form method="post" action="/mumble/hosts" class="d-inline"
                                          onsubmit="return confirm('Bestehende Server importieren?');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="import_servers">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <button class="btn btn-sm btn-outline-success" title="Server importieren"><i class="bi bi-download"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($mb_canManage): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/mumble/hosts?edit=<?= (int)$h['id'] ?>" title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="post" action="/mumble/hosts" class="d-inline"
                                          onsubmit="return confirm('Host &quot;<?= htmlspecialchars((string)$h['name'], ENT_QUOTES) ?>&quot; löschen?');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <?php if ($mb_isNew || $mb_edit): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-<?= $mb_edit ? 'pencil' : 'plus-lg' ?>"></i>
                Host <?= $mb_edit ? 'bearbeiten' : 'hinzufügen' ?>
            </div>
            <div class="card-body">
                <form method="post" action="/mumble/hosts">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="save">
                    <?php if ($mb_edit): ?>
                    <input type="hidden" name="id" value="<?= (int)$mb_edit['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" maxlength="64" required placeholder="z.B. proxmox-vps-01"
                               value="<?= htmlspecialchars((string)($_POST['name'] ?? $mb_edit['name'] ?? '')) ?>"></div>
                    <div class="mb-3"><label class="form-label">Hostname (für Clients) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="hostname" maxlength="255" required placeholder="mumble1.example.com"
                               value="<?= htmlspecialchars((string)($_POST['hostname'] ?? $mb_edit['hostname'] ?? '')) ?>"></div>
                    <div class="mb-3"><label class="form-label">Agent-URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" name="agent_url" maxlength="255" required placeholder="https://mumble1.example.com:8443"
                               value="<?= htmlspecialchars((string)($_POST['agent_url'] ?? $mb_edit['agent_url'] ?? '')) ?>"></div>
                    <div class="mb-3"><label class="form-label">Agent-Token <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="agent_token" maxlength="128" required autocomplete="off" placeholder="Bearer-Token vom Agent-Setup"
                               value="<?= htmlspecialchars((string)($_POST['agent_token'] ?? $mb_edit['agent_token'] ?? '')) ?>"></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Port-Min</label>
                            <input type="number" class="form-control" name="port_min" min="1024" max="65535"
                                   value="<?= (int)($_POST['port_min'] ?? $mb_edit['port_min'] ?? 64738) ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">Port-Max</label>
                            <input type="number" class="form-control" name="port_max" min="1024" max="65535"
                                   value="<?= (int)($_POST['port_max'] ?? $mb_edit['port_max'] ?? 64838) ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Max. Server auf diesem Host</label>
                        <input type="number" class="form-control" name="max_servers" min="1" max="999"
                               value="<?= (int)($_POST['max_servers'] ?? $mb_edit['max_servers'] ?? 20) ?>"></div>
                    <div class="mb-3"><label class="form-label">Notiz</label>
                        <textarea class="form-control" name="note" rows="2" maxlength="500"><?= htmlspecialchars((string)($_POST['note'] ?? $mb_edit['note'] ?? '')) ?></textarea></div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="mb-is-active" name="is_active" value="1"
                                   <?= (!isset($mb_edit) || (int)($mb_edit['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mb-is-active">Host aktiv</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    <a href="/mumble/hosts" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Abbrechen</a>
                </form>
            </div>
        </div>

        <?php if ($mb_edit): ?>
        <?php $mb_hAdmins = $mumble->getHostAdminUsers((int)$mb_edit['id']); ?>
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-person-gear"></i> Host-Admins</div>
            <div class="card-body">
                <?php if ($mb_hAdmins): ?>
                <div class="mb-3">
                    <?php foreach ($mb_hAdmins as $ha): ?>
                    <span class="badge text-bg-info me-1 mb-1" style="font-size:0.9em;padding:5px 8px">
                        <?= htmlspecialchars((string)$ha['display_name']) ?>
                        <form method="post" action="/mumble/hosts" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                            <input type="hidden" name="_action" value="host_admin_remove">
                            <input type="hidden" name="host_id" value="<?= (int)$mb_edit['id'] ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$ha['id'] ?>">
                            <button type="submit" class="btn btn-link p-0 ms-1 text-white" style="font-size:11px;line-height:1;vertical-align:middle" title="Entfernen">&times;</button>
                        </form>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="small text-muted mb-3">Kein Host-Admin zugewiesen.</p>
                <?php endif; ?>

                <form method="post" action="/mumble/hosts?edit=<?= (int)$mb_edit['id'] ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="host_admin_add">
                    <input type="hidden" name="host_id" value="<?= (int)$mb_edit['id'] ?>">
                    <input type="hidden" name="user_id" id="ha-uid">
                    <label class="form-label small">Nutzer hinzufügen</label>
                    <div class="position-relative">
                        <div class="input-group input-group-sm">
                            <input type="text" id="ha-search" class="form-control" placeholder="Username suchen…" autocomplete="off"
                                   data-search-url="/mumble/api/host-user-search?q=">
                            <button type="submit" id="ha-add-btn" class="btn btn-outline-primary" disabled>
                                <i class="bi bi-plus-lg"></i> Hinzufügen
                            </button>
                        </div>
                        <div id="ha-suggestions" class="list-group mt-1" style="position:absolute;z-index:100;width:100%;display:none;"></div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> Hinweise</div>
            <div class="card-body">
                <p>Ein <strong>Host</strong> ist ein Server (VPS oder Root) auf dem Docker und der <code>mumble-agent</code> laufen.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cron-Status -->
        <?php if ($mb_canManage): ?>
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-clock"></i> Statistik-Cron</div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge text-bg-<?= $mb_cron_cls ?> me-2" style="font-size:1em;padding:6px 10px">
                        <?= $mb_cron_cls === 'success' ? '✓' : ($mb_cron_cls === 'warning' ? '⚠' : '✗') ?>
                    </span>
                    <div>
                        <strong>Letzter Lauf:</strong> <?= htmlspecialchars($mb_cron_txt) ?>
                        <?php if ($mb_cron_last > 0): ?>
                        <br><small class="text-muted"><?= date('d.m.Y H:i:s', $mb_cron_last) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($mb_cron_cls !== 'success'): ?>
                <div class="alert alert-<?= $mb_cron_cls === 'warning' ? 'warning' : 'danger' ?> py-2 mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= $mb_cron_age < 0 ? 'Der Cron-Job hat noch nie gelaufen. Bitte einrichten.' : 'Der Cron-Job ist überfällig — zuletzt vor mehr als '.round($mb_cron_age/60).' Minuten.' ?>
                </div>
                <?php endif; ?>

                <?php if ($mb_cron_key !== ''): ?>
                <p class="small mb-1"><strong>Cron-URL:</strong></p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control font-monospace" id="mb-cron-url" readonly
                           value="<?= htmlspecialchars($mb_widget_base.'/mumble/cron/collect?key='.$mb_cron_key) ?>">
                    <button class="btn btn-outline-secondary" id="mb-cron-copy" type="button"><i class="bi bi-clipboard"></i></button>
                </div>
                <p class="small text-muted mb-3">Cron-Job (alle 5 Minuten): <code>*/5 * * * * curl -s -o /dev/null "URL"</code></p>
                <?php endif; ?>

                <form method="post" action="/mumble/hosts" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="cron_key_gen">
                    <button type="submit" class="btn btn-sm btn-outline-<?= $mb_cron_key === '' ? 'primary' : 'secondary' ?>">
                        <i class="bi bi-key"></i> <?= $mb_cron_key === '' ? 'Cron-Key generieren' : 'Key neu generieren' ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="/plugins/esse-mumble/assets/mumble-hosts.js"></script>
<?php
