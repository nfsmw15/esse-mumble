<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();

// $serverId is set by Plugin.php route handler
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Server nicht gefunden oder keine Berechtigung.'];
    header('Location: /mumble/servers'); exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }
    $action = $_POST['_action'] ?? '';
    try {
        switch ($action) {
            case 'start': case 'stop': case 'restart': case 'delete':
                $res = $mumble->performAction($mb_sid, $action);
                if (!$res['ok']) throw new \RuntimeException($res['error'] ?? 'Fehler');
                if ($action === 'delete') {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Server gelöscht.'];
                    header('Location: /mumble/servers'); exit;
                }
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Aktion '.$action.' ausgeführt.'];
                break;
            case 'upgrade':
                $res = $mumble->performUpgrade($mb_sid);
                if (!$res['ok']) throw new \RuntimeException($res['error'] ?? 'Upgrade fehlgeschlagen');
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Image aktualisiert.'];
                break;
            case 'superuser_reset':
                $newPw = trim((string)($_POST['new_supw'] ?? ''));
                $res = $mumble->resetSuperUserPassword($mb_sid, $newPw);
                if (!$res['ok']) throw new \RuntimeException($res['error'] ?? 'Fehler');
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'SuperUser-Passwort zurückgesetzt.'];
                break;
            case 'widget_save':
                $mode    = $_POST['widget_mode'] ?? 'disabled';
                $refresh = max(0, (int)($_POST['widget_refresh'] ?? 30));
                if ($mode === 'disabled') {
                    $mumble->disableWidget($mb_sid);
                    $mumble->saveWidgetSettings($mb_sid, false, $refresh);
                } elseif ($mode === 'token') {
                    if (empty($mumble->getWidgetSettings($mb_sid)['widget_token'])) {
                        $mumble->generateWidgetToken($mb_sid);
                    }
                    $mumble->saveWidgetSettings($mb_sid, false, $refresh);
                } elseif ($mode === 'public') {
                    $mumble->saveWidgetSettings($mb_sid, true, $refresh);
                }
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget-Einstellungen gespeichert.'];
                break;
            case 'widget_regen':
                $mumble->generateWidgetToken($mb_sid);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget-Token neu generiert.'];
                break;
            case 'member_add':
                $uid = (int)($_POST['user_id'] ?? 0);
                if ($uid) $mumble->addMember($mb_sid, $uid);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Mitglied hinzugefügt.'];
                break;
            case 'member_remove':
                $uid = (int)($_POST['user_id'] ?? 0);
                if ($uid) $mumble->removeMember($mb_sid, $uid);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Mitglied entfernt.'];
                break;
            default:
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Unbekannte Aktion.'];
        }
    } catch (\RuntimeException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
    }
    header('Location: /mumble/edit/'.$mb_sid); exit;
}

$mumble->refreshStats($mb_sid);
$mb_srv  = $mumble->getServer($mb_sid);
$mb_csrf = Auth::csrfToken();

$mb_ping            = null;
$mb_container_image = '';
$mb_agent_image     = '';
$mb_can_upgrade     = false;
if (!empty($mb_srv['agent_url']) && !empty($mb_srv['agent_token'])) {
    $mb_agent_tmp = new \EsseMumble\MumbleAgent((string)$mb_srv['agent_url'], (string)$mb_srv['agent_token'], 5);
    $mb_ping_res  = $mb_agent_tmp->ping();
    if ($mb_ping_res['ok'] ?? false) {
        $mb_ping            = $mb_ping_res['data'];
        $mb_container_image = (string)($mb_ping['mumble_image'] ?? '');
        $mb_agent_image     = (string)($mb_ping['latest_image'] ?? '');
        $mb_can_upgrade     = $mb_agent_image !== '' && $mb_container_image !== '' && $mb_agent_image !== $mb_container_image;
    }
}

$mb_uptime = static function(int $secs): string {
    if ($secs <= 0) return '–';
    $d = (int)floor($secs / 86400); $h = (int)floor(($secs % 86400) / 3600); $m = (int)floor(($secs % 3600) / 60);
    $parts = []; if ($d) $parts[] = $d.'d'; if ($h) $parts[] = $h.'h'; if ($m) $parts[] = $m.'m';
    return $parts ? implode(' ', $parts) : $secs.'s';
};

$mb_status = (string)$mb_srv['status'];
$mb_cls = match ($mb_status) {
    'running' => 'success', 'stopped' => 'secondary', 'error' => 'danger', 'creating' => 'warning', default => 'info',
};

$mb_supw = $mumble->getSuperUserPassword($mb_sid);

$mb_widget        = $mumble->getWidgetSettings($mb_sid);
$mb_widget_token  = (string)($mb_widget['widget_token'] ?? '');
$mb_widget_public = (bool)($mb_widget['widget_public'] ?? false);
$mb_widget_refresh= (int)($mb_widget['widget_refresh'] ?? 30);
$mb_widget_scheme = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'https') === 'https') ? 'https' : 'http';
$mb_widget_base   = rtrim($mb_widget_scheme.'://'.(string)($_SERVER['HTTP_HOST'] ?? ''), '/');
$mb_widget_iframe = $mb_widget_token !== ''
    ? $mb_widget_base.'/mumble/widget?token='.$mb_widget_token
    : ($mb_widget_public ? $mb_widget_base.'/mumble/widget?id='.$mb_sid : '');

?>
<div class="mb-3">
    <a href="/mumble/servers" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Übersicht</a>
</div>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<div class="row">
    <!-- Linke Spalte -->
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-info-circle"></i> Verbindungsdaten</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th style="width:40%">Adresse</th>
                            <td>
                                <code><?= htmlspecialchars((string)$mb_srv['hostname']) ?>:<?= (int)$mb_srv['port'] ?></code>
                                <a class="btn btn-link btn-sm py-0"
                                   href="mumble://<?= htmlspecialchars((string)$mb_srv['hostname']) ?>:<?= (int)$mb_srv['port'] ?>">
                                    <i class="bi bi-box-arrow-up-right"></i> verbinden
                                </a>
                            </td>
                        </tr>
                        <tr><th>Host</th><td><?= htmlspecialchars((string)$mb_srv['host_name']) ?></td></tr>
                        <tr><th>Status</th><td><span class="badge text-bg-<?= $mb_cls ?>"><?= htmlspecialchars($mb_status) ?></span></td></tr>
                        <tr><th>Passwortgeschützt</th><td>
                            <?= !empty($mb_srv['password'])
                                ? '<i class="bi bi-lock-fill text-success"></i> Ja'
                                : '<i class="bi bi-unlock text-muted"></i> Nein (öffentlich)'; ?>
                        </td></tr>
                        <tr><th>Online</th><td><strong><?= (int)$mb_srv['stats_online'] ?></strong> / <?= (int)$mb_srv['max_users'] ?></td></tr>
                        <tr><th>Uptime</th><td><?= htmlspecialchars($mb_uptime((int)$mb_srv['stats_uptime'])) ?></td></tr>
                        <tr><th>Erstellt</th><td><?= htmlspecialchars((string)$mb_srv['created_at']) ?></td></tr>
                        <?php if ($mumble->canAdminAll()): ?>
                        <tr><th>Container-ID</th><td><code class="small"><?= htmlspecialchars(substr((string)$mb_srv['container_id'], 0, 12)) ?></code></td></tr>
                        <?php endif; ?>
                        <?php if ($mb_container_image !== ''): ?>
                        <tr><th>Image (läuft)</th><td><code class="small"><?= htmlspecialchars($mb_container_image) ?></code></td></tr>
                        <?php endif; ?>
                        <?php if ($mb_can_upgrade): ?>
                        <tr><th>Image (neu)</th><td><code class="small text-success"><?= htmlspecialchars($mb_agent_image) ?></code></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SuperUser -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-key"></i> SuperUser-Zugang</div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Mit dem SuperUser-Account kannst du dich im Mumble-Client als Administrator anmelden
                    (Benutzername: <code>SuperUser</code>).
                </p>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="text" class="form-control" id="mb-supw-field" readonly
                           value="••••••••••••"
                           data-pw="<?= htmlspecialchars($mb_supw ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="button" id="mb-supw-toggle" title="Anzeigen/verstecken">
                        <i class="bi bi-eye" id="mb-supw-icon"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="mb-supw-copy" title="Kopieren">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <form id="mb-form-supw-reset" method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="superuser_reset">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm" name="new_supw"
                                   placeholder="neues Passwort (leer = zufällig generieren)"
                                   maxlength="128" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-sm btn-warning w-100">
                                <i class="bi bi-arrow-clockwise"></i> Zurücksetzen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($mb_srv['welcome_text'])): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Begrüßungstext</div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(htmlspecialchars((string)$mb_srv['welcome_text'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Channel-Viewer -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-diagram-3"></i> Channel-Viewer</span>
                <button class="btn btn-sm btn-outline-secondary" id="mb-viewer-refresh" title="Aktualisieren">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div id="mb-viewer-content" style="min-height:60px;"
                     data-viewer-url="/mumble/api/viewer?id=<?= (int)$mb_srv['id'] ?>"
                     data-kick-url="/mumble/api/kick?id=<?= (int)$mb_srv['id'] ?>"
                     data-mute-url="/mumble/api/update-user?id=<?= (int)$mb_srv['id'] ?>"
                     data-csrf="<?= htmlspecialchars($mb_csrf) ?>"
                     data-can-manage="<?= $mumble->canManageServer($mb_sid) ? '1' : '0' ?>"
                     data-refresh="<?= $mb_widget_refresh ?>"
                     data-server-name="<?= htmlspecialchars((string)$mb_srv['name']) ?>">
                    <div class="p-3 text-muted text-center small">
                        <i class="bi bi-hourglass-split"></i> Lade…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rechte Spalte -->
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-gear"></i> Aktionen</div>
            <div class="card-body">
                <a href="/mumble/server-stats/<?= (int)$mb_srv['id'] ?>" class="btn btn-primary w-100 d-block mb-3">
                    <i class="bi bi-speedometer2"></i> Monitoring
                </a>
                <?php if (in_array($mb_status, ['stopped','error'], true)): ?>
                <form method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="start">
                    <button class="btn btn-success w-100 d-block"><i class="bi bi-play-fill"></i> Server starten</button>
                </form>
                <?php else: ?>
                <form method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="stop">
                    <button class="btn btn-warning w-100 d-block"><i class="bi bi-stop-fill"></i> Server stoppen</button>
                </form>
                <?php endif; ?>
                <?php if ($mb_status === 'running'): ?>
                <form method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="restart">
                    <button class="btn btn-info w-100 d-block"><i class="bi bi-arrow-clockwise"></i> Neustarten</button>
                </form>
                <?php endif; ?>
                <a href="/mumble/logs/<?= (int)$mb_srv['id'] ?>" class="btn btn-secondary w-100 d-block mb-2">
                    <i class="bi bi-file-text"></i> Logs anzeigen
                </a>
                <a href="/mumble/config/<?= (int)$mb_srv['id'] ?>" class="btn btn-outline-dark w-100 d-block mb-2">
                    <i class="bi bi-gear"></i> Einstellungen bearbeiten
                </a>
                <a href="/mumble/acl/<?= (int)$mb_srv['id'] ?>" class="btn btn-outline-primary w-100 d-block mb-2">
                    <i class="bi bi-key"></i> ACL / Rechte verwalten
                </a>
                <a href="/mumble/channels/<?= (int)$mb_srv['id'] ?>" class="btn btn-outline-info w-100 d-block mb-2">
                    <i class="bi bi-diagram-3"></i> Channels verwalten
                </a>
                <a href="/mumble/bans/<?= (int)$mb_srv['id'] ?>" class="btn btn-outline-warning w-100 d-block mb-2">
                    <i class="bi bi-slash-circle"></i> Bans verwalten
                </a>
                <?php if ($mb_can_upgrade): ?>
                <hr>
                <form method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>"
                      onsubmit="return confirm('Container wird aktualisiert.\nKurze Downtime (~10–30 Sekunden). Fortfahren?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="upgrade">
                    <button class="btn btn-outline-warning w-100 d-block mb-2"><i class="bi bi-arrow-up-circle"></i> Image aktualisieren</button>
                </form>
                <?php endif; ?>
                <?php if ($mumble->isOwner($mb_sid) || $mumble->canAdminAll()): ?>
                <form id="mb-form-delete" method="post" action="/mumble/edit/<?= (int)$mb_srv['id'] ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="delete">
                    <button class="btn btn-danger w-100 d-block"><i class="bi bi-trash"></i> Server löschen</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Einstellungen live -->
        <div class="card mb-4"
             data-settings-sid="<?= (int)$mb_srv['id'] ?>"
             data-settings-csrf="<?= htmlspecialchars($mb_csrf, ENT_QUOTES) ?>">
            <div class="card-header"><i class="bi bi-pencil"></i> Einstellungen bearbeiten</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Server-Name</label>
                    <input type="text" class="form-control form-control-sm" id="mb-set-name"
                           maxlength="64" value="<?= htmlspecialchars((string)$mb_srv['name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Server-Passwort <small class="text-muted">(leer = öffentlich)</small></label>
                    <input type="text" class="form-control form-control-sm" id="mb-set-password"
                           maxlength="64" autocomplete="off" value="<?= htmlspecialchars((string)$mb_srv['password']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Maximale Nutzer</label>
                    <input type="number" class="form-control form-control-sm" id="mb-set-max-users"
                           min="1" max="500" value="<?= (int)$mb_srv['max_users'] ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Begrüßungstext</label>
                    <textarea class="form-control form-control-sm" id="mb-set-welcome"
                              rows="3" maxlength="2000"><?= htmlspecialchars((string)$mb_srv['welcome_text']) ?></textarea>
                </div>
                <button class="btn btn-primary w-100" id="mb-settings-save-btn">
                    <i class="bi bi-floppy"></i> Speichern
                </button>
                <div id="mb-settings-status" class="mt-2 text-center small"></div>
            </div>
        </div>

        <!-- Mitglieder -->
        <?php if ($mumble->isOwner($mb_sid)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-people"></i> Server-Mitglieder</div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Mitglieder können den Server verwalten (Start/Stop, Config, SuperUser-PW, Zertifikat).
                    Sie können den Server <strong>nicht</strong> löschen und die maximale User-Zahl nicht ändern.
                </p>
                <?php $mb_members = $mumble->getMembers($mb_sid); ?>
                <?php if ($mb_members): ?>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($mb_members as $m): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                        <span><i class="bi bi-person text-muted me-1"></i> <?= htmlspecialchars((string)$m['display_name']) ?></span>
                        <form method="post" action="/mumble/edit/<?= $mb_sid ?>">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                            <input type="hidden" name="_action" value="member_remove">
                            <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="position-relative">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="mb-member-search"
                               placeholder="Username suchen…" autocomplete="off"
                               data-search-url="/mumble/api/user-search?id=<?= $mb_sid ?>&q=">
                        <button class="btn btn-outline-secondary" type="button" id="mb-member-add-btn" disabled>
                            <i class="bi bi-plus-lg"></i> Hinzufügen
                        </button>
                    </div>
                    <div id="mb-member-suggestions" class="list-group mt-1"
                         style="position:absolute;z-index:100;width:100%;display:none;"></div>
                </div>
                <form method="post" id="mb-member-add-form" action="/mumble/edit/<?= $mb_sid ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="member_add">
                    <input type="hidden" name="user_id" id="mb-member-uid" value="">
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Widget-Kurzinfo -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-code"></i> Widget &amp; Banner</div>
            <div class="card-body">
                <?php if ($mb_widget_iframe !== ''): ?>
                <p class="small text-success mb-3"><i class="bi bi-check-circle-fill"></i> Widget aktiv</p>
                <?php else: ?>
                <p class="small text-muted mb-3">Widget ist deaktiviert.</p>
                <?php endif; ?>
                <a href="/mumble/widget-settings/<?= $mb_sid ?>" class="btn btn-primary w-100">
                    <i class="bi bi-code"></i> Widget &amp; Banner einrichten
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistik-Charts -->
<div class="mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Statistiken</h5>
        <div class="btn-group btn-group-sm" id="mb-chart-range">
            <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
            <button class="btn btn-outline-secondary" data-range="7d">7 Tage</button>
            <button class="btn btn-outline-secondary" data-range="30d">30 Tage</button>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card"><div class="card-header py-2 small"><i class="bi bi-people"></i> Nutzer online</div>
            <div class="card-body p-2"><canvas id="mb-chart-users" height="120"></canvas></div></div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card"><div class="card-header py-2 small"><i class="bi bi-speedometer2"></i> Bandbreite (B/s)</div>
            <div class="card-body p-2"><canvas id="mb-chart-bw" height="120"></canvas></div></div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card"><div class="card-header py-2 small"><i class="bi bi-cpu"></i> CPU &amp; RAM</div>
            <div class="card-body p-2"><canvas id="mb-chart-cpuram" height="120"></canvas></div></div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card"><div class="card-header py-2 small"><i class="bi bi-clock"></i> Ping Ø (ms)</div>
            <div class="card-body p-2"><canvas id="mb-chart-ping" height="120"></canvas></div></div>
        </div>
    </div>
    <p class="text-muted small" id="mb-chart-note" style="display:none">
        <i class="bi bi-info-circle"></i> Noch keine historischen Daten vorhanden.
    </p>
</div>

<script src="/plugins/esse-mumble/assets/chart.umd.min.js"></script>
<script src="/plugins/esse-mumble/assets/mumble-edit.js"></script>
<?php
