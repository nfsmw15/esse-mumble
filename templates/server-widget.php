<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) { \Esse\Router::abort(403); return; }

$mb_csrf = Auth::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }
    $action = $_POST['_action'] ?? 'widget_save';
    $mode    = $_POST['widget_mode'] ?? 'disabled';
    $refresh = max(0, (int)($_POST['widget_refresh'] ?? 30));

    if ($action === 'widget_regen') {
        $mumble->generateWidgetToken($mb_sid);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget-Token neu generiert.'];
    } else {
        if ($mode === 'disabled') {
            $mumble->disableWidget($mb_sid);
            $mumble->saveWidgetSettings($mb_sid, false, $refresh);
        } elseif ($mode === 'token') {
            $ws = $mumble->getWidgetSettings($mb_sid);
            if (empty($ws['widget_token'])) $mumble->generateWidgetToken($mb_sid);
            $mumble->saveWidgetSettings($mb_sid, false, $refresh);
        } elseif ($mode === 'public') {
            $mumble->saveWidgetSettings($mb_sid, true, $refresh);
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget-Einstellungen gespeichert.'];
    }
    header('Location: /mumble/widget-settings/'.$mb_sid); exit;
}

$mb_widget        = $mumble->getWidgetSettings($mb_sid);
$mb_widget_token  = (string)($mb_widget['widget_token'] ?? '');
$mb_widget_public = (bool)($mb_widget['widget_public'] ?? false);
$mb_widget_refresh= (int)($mb_widget['widget_refresh'] ?? 30);
$mb_widget_scheme = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'https') === 'https') ? 'https' : 'http';
$mb_widget_base   = rtrim($mb_widget_scheme.'://'.(string)($_SERVER['HTTP_HOST'] ?? ''), '/');
$mb_widget_iframe = $mb_widget_token !== ''
    ? $mb_widget_base.'/mumble/widget?token='.$mb_widget_token
    : ($mb_widget_public ? $mb_widget_base.'/mumble/widget?id='.$mb_sid : '');
$mb_embed_token   = $mb_widget_token !== '' ? $mb_widget_token : 'TOKEN';
$mb_banner_url    = $mb_widget_iframe !== '' ? $mb_widget_base.'/mumble/banner?token='.$mb_embed_token : '';

?>
<div class="mb-3">
    <a href="/mumble/edit/<?= $mb_sid ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-toggle-on"></i> Einstellungen</div>
            <div class="card-body">
                <form method="post" action="/mumble/widget-settings/<?= $mb_sid ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="widget_save">
                    <div class="mb-3">
                        <label class="form-label">Sichtbarkeit</label>
                        <select name="widget_mode" class="form-select form-select-sm">
                            <option value="disabled" <?= $mb_widget_token === '' && !$mb_widget_public ? 'selected' : '' ?>>Deaktiviert</option>
                            <option value="token"    <?= $mb_widget_token !== '' && !$mb_widget_public ? 'selected' : '' ?>>Token-geschützt (empfohlen)</option>
                            <option value="public"   <?= $mb_widget_public ? 'selected' : '' ?>>Öffentlich (nur Server-ID)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Auto-Refresh (Sekunden, 0 = aus)</label>
                        <input type="number" name="widget_refresh" class="form-control form-control-sm" min="0" max="300" value="<?= $mb_widget_refresh ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-floppy"></i> Speichern</button>
                </form>
                <?php if ($mb_widget_token !== '' && !$mb_widget_public): ?>
                <form method="post" action="/mumble/widget-settings/<?= $mb_sid ?>" class="mt-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                    <input type="hidden" name="_action" value="widget_regen">
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100">
                        <i class="bi bi-arrow-clockwise"></i> Token neu generieren
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($mb_widget_iframe !== ''): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-link"></i> Einbettungs-Codes</div>
            <div class="card-body">
                <label class="form-label small">Widget-URL</label>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control" id="mb-widget-url" readonly value="<?= htmlspecialchars($mb_widget_iframe) ?>">
                    <button class="btn btn-outline-secondary" id="mb-widget-copy-url"><i class="bi bi-clipboard"></i></button>
                </div>

                <label class="form-label small">iframe-Embed</label>
                <textarea class="form-control form-control-sm mb-1" id="mb-widget-code" readonly rows="3"><?= htmlspecialchars('<iframe src="'.$mb_widget_iframe.'" width="300" height="400" frameborder="0" scrolling="auto" style="border-radius:8px;border:none;"></iframe>') ?></textarea>
                <button class="btn btn-sm btn-outline-secondary w-100 mb-3" id="mb-widget-copy-code">
                    <i class="bi bi-clipboard"></i> iframe kopieren
                </button>

                <label class="form-label small">JavaScript-Embed <small class="text-muted">(kein iframe)</small></label>
                <?php
                $mb_embed_js = '<div data-mumble-token="'.$mb_embed_token.'" data-refresh="30" data-show-empty="1" style="border-radius:8px;min-height:60px"></div>'."\n".'<script src="'.$mb_widget_base.'/plugins/esse-mumble/assets/mumble-embed.js"><\/script>';
                ?>
                <textarea class="form-control form-control-sm mb-1" id="mb-widget-js-code" readonly rows="3"><?= htmlspecialchars($mb_embed_js) ?></textarea>
                <button class="btn btn-sm btn-outline-secondary w-100 mb-3" id="mb-widget-copy-js">
                    <i class="bi bi-clipboard"></i> JS-Code kopieren
                </button>

                <label class="form-label small">Banner-URL <small class="text-muted">(PNG)</small></label>
                <div class="input-group input-group-sm mb-1">
                    <input type="text" class="form-control" id="mb-banner-url" readonly value="<?= htmlspecialchars($mb_banner_url) ?>">
                    <button class="btn btn-outline-secondary" id="mb-widget-copy-banner"><i class="bi bi-clipboard"></i></button>
                </div>
                <p class="small text-muted mb-0">Einbinden: <code>&lt;img src="URL"&gt;</code></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <?php if ($mb_widget_iframe !== ''): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-image"></i> Server-Banner</div>
            <div class="card-body text-center">
                <img src="<?= htmlspecialchars($mb_banner_url) ?>" alt="Server-Banner" class="mb-banner-preview">
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-gear"></i> Channel-Viewer Konfigurator</div>
            <div class="card-body">
                <div class="row" id="mb-wconf"
                     data-token="<?= htmlspecialchars($mb_embed_token, ENT_QUOTES) ?>"
                     data-api="<?= htmlspecialchars($mb_widget_base, ENT_QUOTES) ?>">
                    <div class="col-md-5">
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input wc-opt" id="wc-bg-enabled">
                                <label class="form-check-label small" for="wc-bg-enabled">Hintergrund</label>
                            </div>
                            <div id="wc-bg-options" class="mb-hidden">
                                <div class="d-flex align-items-center mb-1">
                                    <input type="color" id="wc-bg" value="#000000" class="wc-opt me-2 mb-color-swatch">
                                    <input type="text" id="wc-bg-text" value="#000000" class="form-control form-control-sm wc-text" maxlength="7">
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input wc-opt" id="wc-bg-transparent">
                                    <label class="form-check-label small" for="wc-bg-transparent">Transparent</label>
                                </div>
                            </div>
                        </div>
                        <?php foreach ([['wc-cs','#000000','Server-Name'],['wc-cc','#000000','Channel'],['wc-cu','#000000','User']] as [$id,$val,$lbl]): ?>
                        <div class="mb-2">
                            <label class="form-label small mb-0"><?= $lbl ?></label>
                            <div class="d-flex align-items-center">
                                <input type="color" id="<?= $id ?>" value="<?= $val ?>" class="wc-opt me-2 mb-color-swatch">
                                <input type="text" id="<?= $id ?>-text" value="<?= $val ?>" class="form-control form-control-sm wc-text" maxlength="7">
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input wc-opt" id="wc-width-enabled">
                                <label class="form-check-label small" for="wc-width-enabled">Breite festlegen</label>
                            </div>
                            <div id="wc-width-options" class="mb-hidden">
                                <div class="input-group input-group-sm">
                                    <input type="number" id="wc-width-val" value="100" min="10" max="100" class="form-control wc-opt">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input wc-opt" id="wc-height-enabled">
                                <label class="form-check-label small" for="wc-height-enabled">Feste Höhe</label>
                            </div>
                            <div id="wc-height-options" class="mb-hidden">
                                <div class="input-group input-group-sm">
                                    <input type="number" id="wc-height-val" value="400" min="50" max="2000" class="form-control wc-opt">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6"><div class="mb-2"><label class="form-label small mb-0">Schriftgröße</label>
                                <input type="text" id="wc-fs" value="13px" class="form-control form-control-sm wc-opt" maxlength="8"></div></div>
                            <div class="col-6"><div class="mb-2"><label class="form-label small mb-0">Einrückung (px)</label>
                                <input type="number" id="wc-indent" value="14" min="4" max="40" class="form-control form-control-sm wc-opt"></div></div>
                        </div>
                        <div class="mb-2"><label class="form-label small mb-0">Refresh (s, 0 = aus)</label>
                            <input type="number" id="wc-refresh" value="30" min="0" max="300" class="form-control form-control-sm wc-opt"></div>
                        <div class="mb-2"><label class="form-label small mb-0">Icons (Server / Channel / User)</label>
                            <div class="d-flex">
                                <input type="text" id="wc-is" value="🔊" class="form-control form-control-sm wc-opt me-1" maxlength="4">
                                <input type="text" id="wc-ic" value="📢" class="form-control form-control-sm wc-opt me-1" maxlength="4">
                                <input type="text" id="wc-iu" value="🎧" class="form-control form-control-sm wc-opt" maxlength="4">
                            </div>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input wc-opt" id="wc-empty" checked>
                            <label class="form-check-label small" for="wc-empty">Leere Channels anzeigen</label>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label small mb-1">Vorschau</label>
                        <div id="wc-preview" class="mb-widget-preview">
                            <span class="text-muted small"><i class="bi bi-hourglass-split"></i> Lade…</span>
                        </div>
                        <label class="form-label small mb-1 mt-2">Generierter Code</label>
                        <textarea class="form-control form-control-sm mb-1" id="wc-code" readonly rows="5"></textarea>
                        <button class="btn btn-sm btn-outline-secondary w-100" id="wc-copy">
                            <i class="bi bi-clipboard"></i> Code kopieren
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-4">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-lock-fill display-4 d-block mb-3"></i>
                Widget ist deaktiviert. Aktiviere es links um Banner und Konfigurator zu nutzen.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/mumble-edit.js"></script>
<?php
