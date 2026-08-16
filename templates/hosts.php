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
                // Setzt nur das Default-Image für künftige Server-Neuanlagen ohne
                // explizite Versionswahl. UI-Trigger nur noch als Fallback für alte
                // Agents ohne GET /v1/images (siehe bulk_upgrade für den Regelfall).
                $res = $mumble->updateHostImage((int)$_POST['id'], (string)$_POST['image']);
                $_SESSION['flash'] = $res['ok']
                    ? ['type' => 'success', 'message' => 'Agent-Image wird aktualisiert.']
                    : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                header('Location: /mumble/hosts'); exit;
            case 'set_channel':
                $res = $mumble->setHostChannel((int)$_POST['id'], (string)$_POST['channel']);
                $_SESSION['flash'] = $res['ok']
                    ? ['type' => 'success', 'message' => 'Update-Kanal wird umgestellt (Agent startet kurz neu).']
                    : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                header('Location: /mumble/hosts?edit='.(int)$_POST['id']); exit;
            case 'update_agent':
                $res = $mumble->updateHostAgent((int)$_POST['id']);
                $_SESSION['flash'] = $res['ok']
                    ? ['type' => 'success', 'message' => 'Agent wird auf v'.($res['data']['version'] ?? 'neueste Version').' aktualisiert (kurzer Neustart).']
                    : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                header('Location: /mumble/hosts'); exit;
            case 'bulk_upgrade':
                $res = $mumble->bulkUpgradeHost((int)$_POST['id'], (string)$_POST['image']);
                if (!$res['ok']) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
                } else {
                    $msg = $res['upgraded'].' Server aktualisiert.';
                    if (!empty($res['failed'])) $msg .= ' Fehlgeschlagen: '.implode('; ', $res['failed']);
                    $_SESSION['flash'] = ['type' => empty($res['failed']) ? 'success' : 'warning', 'message' => $msg];
                }
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

// Session-Lock vor den Live-Pings freigeben, sonst blockiert ein hängender Host
// jeden weiteren Request derselben Browser-Session (siehe ajax.php).
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$mb_pings  = [];
$mb_images = [];
foreach ($mb_hosts as $h) {
    if ((int)$h['is_active'] !== 1) continue;
    $a = new \EsseMumble\MumbleAgent((string)$h['agent_url'], (string)$h['agent_token'], 3);
    $r = $a->ping();
    $mb_pings[(int)$h['id']] = $r['ok'] ? $r['data'] : false;
    if ($r['ok']) {
        $mumble->touchHostLastSeen((int)$h['id']);
        if (!empty($r['data']['latest_image'])) $mumble->updateHostLatestImage((int)$h['id'], (string)$r['data']['latest_image']);
        $mb_images[(int)$h['id']] = $mumble->getAvailableImages((int)$h['id']);
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
                                        <?php if (!empty($ping['version'])): ?>
                                        <span class="badge text-bg-light border" title="mumble-agent-Version">
                                            Agent v<?= htmlspecialchars((string)$ping['version']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($ping['agent_update_available']) && !empty($ping['agent_latest_version'])): ?>
                                        <br><span class="badge text-bg-info" title="Neue mumble-agent-Version verfügbar (nicht der Mumble-Server selbst)">
                                            <i class="bi bi-cpu"></i> Agent-Update: v<?= htmlspecialchars((string)$ping['agent_latest_version']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($ping['update_channel'])): ?>
                                        <span class="badge text-bg-<?= $ping['update_channel'] === 'prerelease' ? 'warning' : 'secondary' ?>" title="Update-Kanal">
                                            Kanal: <?= htmlspecialchars((string)$ping['update_channel']) ?>
                                        </span>
                                        <?php endif; ?>
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
                                <td class="text-end">
                                    <?php $mb_hImages = $mb_images[(int)$h['id']] ?? ['ok' => false, 'images' => []]; ?>
                                    <?php if ($mb_hImages['ok'] && !empty($mb_hImages['images'])): ?>
                                    <form method="post" action="/mumble/hosts" class="d-flex justify-content-end align-items-center gap-1 flex-wrap mb-1"
                                          data-confirm="ACHTUNG: Alle laufenden Server auf diesem Host werden nacheinander auf die gewählte Version aktualisiert (kurzer Neustart pro Server). Ein Downgrade auf eine ältere Version kann am Datenbankformat scheitern (z.B. 1.6.x → 1.5.x) — der Agent lehnt inkompatible Downgrades pro Server ab. Fortfahren?">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="bulk_upgrade">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <select name="image" class="form-select form-select-sm" style="width:auto;max-width:170px;">
                                            <?php foreach ($mb_hImages['images'] as $mb_img):
                                                $img    = $mb_img['image'];
                                                $mb_tag = str_contains($img, ':') ? substr($img, strrpos($img, ':') + 1) : $img;
                                            ?>
                                            <option value="<?= htmlspecialchars($img) ?>" <?= $img === ($ping['mumble_image'] ?? '') ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($mb_tag) ?><?= $mb_img['latest'] ? ' (neueste)' : '' ?><?= $mb_img['prerelease'] ? ' ⚠pre' : '' ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-warning" title="Alle Server auf diesem Host aktualisieren">
                                            <i class="bi bi-arrow-up-circle"></i>
                                        </button>
                                    </form>
                                    <?php elseif (!empty($ping['update_available']) && !empty($ping['latest_image'])): ?>
                                    <!-- Fallback für alte Agents ohne GET /v1/images: setzt nur das
                                         Default-Image für künftige Server-Neuanlagen (kein Rolling-Update). -->
                                    <form method="post" action="/mumble/hosts" class="d-inline mb-1"
                                          data-confirm="Agent-Image aktualisieren?">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="update_image">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <input type="hidden" name="image" value="<?= htmlspecialchars((string)$ping['latest_image'], ENT_QUOTES) ?>">
                                        <button class="btn btn-sm btn-warning" title="Agent-Image aktualisieren (alter Agent, keine Versionsauswahl)"><i class="bi bi-arrow-up-circle"></i></button>
                                    </form>
                                    <?php elseif ($online === true): ?>
                                    <small class="text-muted d-block mb-1" title="Agent unterstützt noch keine Versionsauswahl (GET /v1/images)">
                                        <i class="bi bi-info-circle"></i> keine Versionsauswahl
                                    </small>
                                    <?php endif; ?>
                                    <?php if (!empty($ping['agent_update_available']) && !empty($ping['agent_latest_version'])): ?>
                                    <form method="post" action="/mumble/hosts" class="d-inline mb-1"
                                          data-confirm="mumble-agent auf v<?= htmlspecialchars((string)$ping['agent_latest_version'], ENT_QUOTES) ?> aktualisieren?&#10;Betrifft nur den Agent-Prozess (kurzer Neustart, ~3-4s) — laufende Mumble-Server sind davon nicht betroffen.">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                                        <input type="hidden" name="_action" value="update_agent">
                                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                        <button class="btn btn-sm btn-info" title="mumble-agent auf v<?= htmlspecialchars((string)$ping['agent_latest_version'], ENT_QUOTES) ?> aktualisieren">
                                            <i class="bi bi-cpu"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-info" href="/mumble/host-stats/<?= (int)$h['id'] ?>" title="Server anzeigen">
                                        <i class="bi bi-list"></i>
                                    </a>
                                    <?php if ($online === true && ($mb_canManage || in_array((int)$h['id'], $mb_adminHostIds))): ?>
                                    <form method="post" action="/mumble/hosts" class="d-inline"
                                          data-confirm="Bestehende Server importieren?">
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
                                          data-confirm="Host &quot;<?= htmlspecialchars((string)$h['name'], ENT_QUOTES) ?>&quot; löschen?">
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
        <?php $mb_editPing = $mb_pings[(int)$mb_edit['id']] ?? null; $mb_curChannel = (string)($mb_editPing['update_channel'] ?? 'stable'); ?>
        <?php if ($mb_editPing): ?>
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-signpost-split"></i> Update-Kanal</div>
            <div class="card-body">
                <p class="small text-muted">
                    Bestimmt, welche Versionen Agent und Plugin für dieses Host als „verfügbar" anzeigen.
                    <strong>prerelease</strong> zeigt auch als instabil markierte Pre-Release-Versionen von
                    mumble-voip/mumble (z.B. v1.6.x). Umstellen löst einen kurzen Neustart (~3–4s) des
                    Agent-Prozesses auf diesem Host aus — keine spontane Änderung, sondern eine bewusste
                    Host-Einstellung.
                </p>
                <form method="post" action="/mumble/hosts" class="d-flex align-items-center gap-2"
                      data-confirm="Update-Kanal umstellen? Der Agent-Prozess auf diesem Host startet dabei kurz neu (~3–4s).">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="set_channel">
                    <input type="hidden" name="id" value="<?= (int)$mb_edit['id'] ?>">
                    <select name="channel" class="form-select form-select-sm w-auto">
                        <option value="stable" <?= $mb_curChannel === 'stable' ? 'selected' : '' ?>>stable</option>
                        <option value="prerelease" <?= $mb_curChannel === 'prerelease' ? 'selected' : '' ?>>prerelease</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-warning">Umstellen</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
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

<script src="/plugins/esse-mumble/assets/mumble-confirm.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-hosts.js"></script>
<?php
