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
    $action = $_POST['_action'] ?? 'save_settings';

    if ($action === 'cert_upload') {
        $cert = trim((string)($_POST['cert_pem'] ?? ''));
        $key  = trim((string)($_POST['key_pem'] ?? ''));
        $res  = $mumble->setCertificate($mb_sid, $cert, $key);
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => 'Zertifikat hochgeladen.']
            : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
    } elseif ($action === 'cert_remove') {
        $res = $mumble->removeCertificate($mb_sid);
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => 'Zertifikat entfernt.']
            : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
    } else {
        // save_settings
        $data = $_POST;
        unset($data['_csrf'], $data['_action']);
        // Checkboxes → 'true'/'false' strings
        foreach (['allowhtml','rememberchannel','certrequired','suggestpositional','suggestpushtotalk','sendversion','allowping','bonjour'] as $cb) {
            $data[$cb] = isset($_POST[$cb]) ? 'true' : 'false';
        }
        $res = $mumble->saveServerSettings($mb_sid, $data);
        $_SESSION['flash'] = $res['ok']
            ? ['type' => 'success', 'message' => 'Einstellungen gespeichert. Server wird neu gestartet.']
            : ['type' => 'danger', 'message' => 'Fehler: '.($res['error'] ?? '')];
    }
    header('Location: /mumble/config/'.$mb_sid); exit;
}

$mb_cfg = $mumble->getServerSettings($mb_sid) ?? [];

$mb_channels = [];
$mb_viewer = $mumble->getViewer($mb_sid);
if (!empty($mb_viewer['channels'])) {
    function mb_flatten_channels(array $ch, int $depth = 0): array {
        $out = [['id' => (int)($ch['id'] ?? 0), 'name' => str_repeat("\u{00A0}\u{00A0}", $depth).$ch['name']]];
        foreach ($ch['children'] ?? [] as $child) {
            $out = array_merge($out, mb_flatten_channels($child, $depth + 1));
        }
        return $out;
    }
    $mb_channels = mb_flatten_channels($mb_viewer['channels']);
}

function cfg(array $c, string $key, mixed $default = ''): mixed { return $c[$key] ?? $default; }

?>
<div class="mb-3">
    <a href="/mumble/edit/<?= (int)$mb_srv['id'] ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<form method="post" action="/mumble/config/<?= $mb_sid ?>">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
<input type="hidden" name="_action" value="save_settings">

<ul class="nav nav-tabs mb-3" id="cfgTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basis">Basis</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-reg">Registrierung</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ban">Auto-Ban</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-adv">Erweitert</a></li>
</ul>

<div class="tab-content">
    <!-- TAB: Basis -->
    <div class="tab-pane fade show active" id="tab-basis">
    <div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Audio &amp; Verbindung</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Bandbreite pro User</label>
                    <select name="bandwidth" class="form-select form-select-sm">
                        <?php foreach ([72000=>'72 kbit/s (Sprache, schmal)',96000=>'96 kbit/s',130000=>'130 kbit/s (Standard)',192000=>'192 kbit/s (hoch)',320000=>'320 kbit/s (sehr hoch)'] as $v=>$l): ?>
                        <option value="<?= $v ?>"<?= (int)cfg($mb_cfg,'bandwidth',130000)===$v?' selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Timeout (Sekunden)</label>
                    <input type="number" name="timeout" class="form-control form-control-sm" min="5" max="3600" value="<?= (int)cfg($mb_cfg,'timeout',30) ?>">
                    <div class="form-text">Inaktive Verbindungen werden nach dieser Zeit getrennt.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Opus-Schwellwert (%)</label>
                    <input type="number" name="opusthreshold" class="form-control form-control-sm" min="0" max="100" value="<?= (int)cfg($mb_cfg,'opusthreshold',100) ?>">
                    <div class="form-text">Ab diesem Anteil Opus-Clients wird Opus aktiviert.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Limits &amp; Verhalten</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Max. Textnachricht (Zeichen, 0=unbegrenzt)</label>
                    <input type="number" name="textmessagelength" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'textmessagelength',5000) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Max. Bildgröße in Nachrichten (Bytes, 0=unbegrenzt)</label>
                    <input type="number" name="imagemessagelength" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'imagemessagelength',131072) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Max. User pro Channel (0=unbegrenzt)</label>
                    <input type="number" name="usersperchannel" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'usersperchannel',0) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Max. Channel-Tiefe (0=unbegrenzt)</label>
                    <input type="number" name="channelnestinglimit" class="form-control form-control-sm" min="0" max="50" value="<?= (int)cfg($mb_cfg,'channelnestinglimit',10) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Standard-Channel</label>
                    <?php if ($mb_channels): ?>
                    <select name="defaultchannel" class="form-select form-select-sm">
                        <?php foreach ($mb_channels as $ch): ?>
                        <option value="<?= $ch['id'] ?>"<?= (int)cfg($mb_cfg,'defaultchannel',0) === $ch['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($ch['name']) ?> (ID <?= $ch['id'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="number" name="defaultchannel" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'defaultchannel',0) ?>">
                    <div class="form-text">Channel-Liste nicht verfügbar (Server offline?).</div>
                    <?php endif; ?>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="allowhtml" id="cfg_allowhtml" value="1"
                           <?= cfg($mb_cfg,'allowhtml','true')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_allowhtml">HTML in Textnachrichten erlauben</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="rememberchannel" id="cfg_remember" value="1"
                           <?= cfg($mb_cfg,'rememberchannel','true')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_remember">Letzten Channel merken</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="certrequired" id="cfg_cert" value="1"
                           <?= cfg($mb_cfg,'certrequired','false')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_cert">Client-Zertifikat vorschreiben</label>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <!-- TAB: Registrierung -->
    <div class="tab-pane fade" id="tab-reg">
    <div class="card mb-4">
        <div class="card-header">Öffentliche Mumble-Serverliste</div>
        <div class="card-body">
            <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Anzeigename in der Liste</label>
                    <input type="text" name="register_name" class="form-control form-control-sm" maxlength="255" value="<?= htmlspecialchars((string)cfg($mb_cfg,'register_name','')) ?>"></div>
                <div class="mb-3"><label class="form-label">Registrierungs-Passwort</label>
                    <input type="text" name="register_password" class="form-control form-control-sm" maxlength="255" autocomplete="off" value="<?= htmlspecialchars((string)cfg($mb_cfg,'register_password','')) ?>"></div>
                <div class="mb-3"><label class="form-label">Website-URL</label>
                    <input type="url" name="register_url" class="form-control form-control-sm" maxlength="512" value="<?= htmlspecialchars((string)cfg($mb_cfg,'register_url','')) ?>"></div>
            </div>
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Hostname für Registrierung</label>
                    <input type="text" name="register_hostname" class="form-control form-control-sm" maxlength="255" value="<?= htmlspecialchars((string)cfg($mb_cfg,'register_hostname','')) ?>"></div>
                <div class="mb-3"><label class="form-label">Standort (z.B. DE, US)</label>
                    <input type="text" name="register_location" class="form-control form-control-sm" maxlength="64" value="<?= htmlspecialchars((string)cfg($mb_cfg,'register_location','')) ?>"></div>
            </div>
            </div>
        </div>
    </div>
    </div>

    <!-- TAB: Auto-Ban -->
    <div class="tab-pane fade" id="tab-ban">
    <div class="card mb-4">
        <div class="card-header">Automatischer Bann bei Brute-Force</div>
        <div class="card-body">
            <div class="row">
            <div class="col-md-4">
                <div class="mb-3"><label class="form-label">Fehlversuche (0=deaktiviert)</label>
                    <input type="number" name="autoban_attempts" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'autoban_attempts',10) ?>"></div>
            </div>
            <div class="col-md-4">
                <div class="mb-3"><label class="form-label">Zeitfenster (Sekunden)</label>
                    <input type="number" name="autoban_timeframe" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'autoban_timeframe',120) ?>"></div>
            </div>
            <div class="col-md-4">
                <div class="mb-3"><label class="form-label">Bann-Dauer (Sekunden)</label>
                    <input type="number" name="autoban_time" class="form-control form-control-sm" min="0" value="<?= (int)cfg($mb_cfg,'autoban_time',300) ?>"></div>
            </div>
            </div>
        </div>
    </div>
    </div>

    <!-- TAB: Erweitert -->
    <div class="tab-pane fade" id="tab-adv">
    <div class="card mb-4">
        <div class="card-header">Erweiterte Einstellungen</div>
        <div class="card-body">
            <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Empfohlene Client-Version (leer=keine Empfehlung)</label>
                    <input type="text" name="suggestversion" class="form-control form-control-sm" maxlength="32" placeholder="z.B. 1.5.0" value="<?= htmlspecialchars((string)cfg($mb_cfg,'suggestversion','')) ?>"></div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="suggestpositional" id="cfg_pos" value="1"
                           <?= cfg($mb_cfg,'suggestpositional','false')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_pos">Positional Audio empfehlen</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="suggestpushtotalk" id="cfg_ptt" value="1"
                           <?= cfg($mb_cfg,'suggestpushtotalk','false')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_ptt">Push-to-Talk empfehlen</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="sendversion" id="cfg_sendv" value="1"
                           <?= cfg($mb_cfg,'sendversion','true')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_sendv">Server-Version an Clients senden</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="allowping" id="cfg_allowping" value="1"
                           <?= cfg($mb_cfg,'allowping','true')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_allowping">Ping-Anfragen beantworten</label>
                    <div class="form-text">Deaktivieren um den Server aus Server-Browsern zu verstecken.</div>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="bonjour" id="cfg_bonjour" value="1"
                           <?= cfg($mb_cfg,'bonjour','false')==='true'?' checked':'' ?>>
                    <label class="form-check-label" for="cfg_bonjour">Bonjour/ZeroConf-Ankündigung</label>
                </div>
            </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Namens-Regeln</div>
        <div class="card-body">
            <div class="row">
            <div class="col-md-6">
                <div class="mb-0">
                    <label class="form-label">Username-Format</label>
                    <select id="cfg-username-preset" class="form-select form-select-sm mb-1">
                        <option value="default">Standard (Mumble-Default)</option>
                        <option value="alphanum">Nur Buchstaben, Zahlen &amp; _ - .</option>
                        <option value="ascii">Nur ASCII-Zeichen</option>
                        <option value="custom">Eigener Regex</option>
                    </select>
                    <input type="text" name="username" id="cfg-username" class="form-control form-control-sm mb-1"
                           value="<?= htmlspecialchars((string)cfg($mb_cfg,'username','')) ?>"
                           placeholder="Leer = Mumble-Standard">
                    <div class="form-text text-danger"><i class="bi bi-exclamation-triangle"></i> Falscher Regex → niemand kann sich verbinden!</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-0">
                    <label class="form-label">Channel-Name-Format</label>
                    <select id="cfg-channelname-preset" class="form-select form-select-sm mb-1">
                        <option value="default">Standard (Mumble-Default)</option>
                        <option value="alphanum">Nur Buchstaben, Zahlen &amp; Leerzeichen</option>
                        <option value="ascii">Nur ASCII-Zeichen</option>
                        <option value="custom">Eigener Regex</option>
                    </select>
                    <input type="text" name="channelname" id="cfg-channelname" class="form-control form-control-sm mb-1"
                           value="<?= htmlspecialchars((string)cfg($mb_cfg,'channelname','')) ?>"
                           placeholder="Leer = Mumble-Standard">
                    <div class="form-text text-danger"><i class="bi bi-exclamation-triangle"></i> Falscher Regex → Channels können nicht erstellt werden!</div>
                </div>
            </div>
            </div>
        </div>
    </div>
    </div>
</div>

<button type="submit" class="btn btn-primary">
    <i class="bi bi-floppy"></i> Einstellungen speichern &amp; Server neustarten
</button>
<a href="/mumble/edit/<?= $mb_sid ?>" class="btn btn-secondary ms-2">
    <i class="bi bi-arrow-left"></i> Zurück
</a>
</form>

<!-- Zertifikat -->
<div class="card mt-4 mb-4">
    <div class="card-header"><i class="bi bi-lock-fill"></i> TLS-Zertifikat</div>
    <div class="card-body">
        <?php $hasCert = !empty($mb_cfg['ssl_cert']); ?>
        <?php if ($hasCert): ?>
        <div class="alert alert-success py-2 mb-3">
            <i class="bi bi-check-lg"></i> Eigenes Zertifikat aktiv
            <form method="post" action="/mumble/config/<?= $mb_sid ?>" class="d-inline ms-3" id="mb-form-cert-remove">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
                <input type="hidden" name="_action" value="cert_remove">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Entfernen</button>
            </form>
        </div>
        <?php else: ?>
        <p class="small text-muted mb-3">
            Aktuell wird ein selbst-signiertes Zertifikat verwendet.
        </p>
        <?php endif; ?>
        <form method="post" action="/mumble/config/<?= $mb_sid ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">
            <input type="hidden" name="_action" value="cert_upload">
            <div class="mb-3">
                <label class="form-label">Zertifikat (PEM, inkl. Chain)</label>
                <textarea name="cert_pem" class="form-control form-control-sm" rows="5"
                          placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Privater Schlüssel (PEM)</label>
                <textarea name="key_pem" class="form-control form-control-sm" rows="5"
                          placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
            </div>
            <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-floppy"></i> Zertifikat hochladen &amp; aktivieren
            </button>
        </form>
    </div>
</div>

<script src="/plugins/esse-mumble/assets/mumble-config.js"></script>
<?php
