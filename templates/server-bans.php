<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) { \Esse\Router::abort(403); return; }

$mb_csrf = Auth::csrfToken();
$mb_name = htmlspecialchars((string)$mb_srv['name']);

?>
<div class="mb-3" id="mb-page-config" data-csrf="<?= htmlspecialchars($mb_csrf) ?>">
    <a href="/mumble/edit/<?= $mb_sid ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<p class="text-muted mb-3"><i class="bi bi-check-circle-fill text-success"></i> Bans werden sofort live gesetzt.</p>

<div id="mb-status-box"></div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-slash-circle"></i> Aktive Bans</span>
                <button class="btn btn-sm btn-outline-secondary" id="mb-refresh-btn"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body p-0">
                <div id="mb-ban-list">
                    <div class="text-center text-muted p-3"><i class="bi bi-hourglass-split"></i> Lade...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header py-2"><i class="bi bi-plus-lg"></i> Ban hinzufügen</div>
            <div class="card-body">
                <div class="mb-2"><label class="form-label small">IP-Adresse <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-ip" placeholder="192.168.1.1 oder ::1"></div>
                <div class="mb-2"><label class="form-label small">Subnetz-Bits</label>
                    <input type="number" class="form-control form-control-sm" id="mb-ban-bits" value="32" min="1" max="128">
                    <div class="form-text">32 = einzelne IPv4, 128 = einzelne IPv6</div></div>
                <div class="mb-2"><label class="form-label small">Dauer (Minuten, 0 = permanent)</label>
                    <input type="number" class="form-control form-control-sm" id="mb-ban-duration" value="0" min="0"></div>
                <div class="mb-2"><label class="form-label small">Grund</label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-reason" maxlength="255" placeholder="Grund für den Ban"></div>
                <div class="mb-3"><label class="form-label small">Username (optional)</label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-name" maxlength="255"></div>
                <button class="btn btn-danger w-100" id="mb-add-ban-btn"><i class="bi bi-slash-circle"></i> Bannen</button>
            </div>
        </div>
    </div>
</div>

<script src="/plugins/esse-mumble/assets/mumble-bans.js"></script>
<?php
