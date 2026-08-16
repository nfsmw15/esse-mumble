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

<p class="text-muted mb-3">
    <i class="bi bi-check-circle-fill text-success"></i>
    Änderungen werden <strong>sofort live</strong> übernommen.
</p>

<div id="mb-error-box"></div>

<div class="row">
    <!-- Channel-Baum -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3"></i> Channels</span>
                <button class="btn btn-sm btn-success" id="mb-add-root-btn" title="Root-Channel hinzufügen">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body p-2 mb-scroll-tree mb-scroll-tree--min" id="mb-channel-tree">
                <div class="text-muted small p-2"><i class="bi bi-hourglass-split"></i> Lade…</div>
            </div>
        </div>
    </div>

    <!-- Edit-Bereich -->
    <div class="col-md-8">
        <div id="mb-edit-placeholder" class="alert alert-info">
            <i class="bi bi-arrow-left"></i>&nbsp; Channel auswählen zum Bearbeiten,
            oder <a href="#" id="mb-add-root-link"><i class="bi bi-plus-lg"></i> Root-Channel erstellen</a>.
        </div>

        <div id="mb-edit-panel" class="mb-hidden">
            <div class="card">
                <div class="card-header py-2"><strong id="mb-edit-title">Channel bearbeiten</strong></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Name</label>
                        <input type="text" class="form-control form-control-sm" id="mb-ch-name" maxlength="64"></div>
                    <div class="mb-3"><label class="form-label">Beschreibung</label>
                        <textarea class="form-control form-control-sm" id="mb-ch-desc" rows="3" maxlength="2000"></textarea></div>
                    <div class="mb-3"><label class="form-label">Sortierungs-Position</label>
                        <input type="number" class="form-control form-control-sm" id="mb-ch-pos" value="0"></div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-primary" id="mb-save-btn"><i class="bi bi-floppy"></i> Speichern</button>
                        <button class="btn btn-danger" id="mb-delete-btn"><i class="bi bi-trash"></i> Channel löschen</button>
                    </div>
                    <span id="mb-status" class="mt-2 d-block small"></span>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header py-2"><i class="bi bi-plus-lg"></i> Sub-Channel hinzufügen</div>
                <div class="card-body">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="mb-new-sub-name" placeholder="Channel-Name" maxlength="64">
                        <button class="btn btn-success" id="mb-add-sub-btn"><i class="bi bi-plus-lg"></i> Erstellen</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="mb-new-root-panel" class="card mb-hidden">
            <div class="card-header py-2"><i class="bi bi-plus-lg"></i> Root-Channel erstellen</div>
            <div class="card-body">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="mb-new-root-name" placeholder="Channel-Name" maxlength="64">
                    <button class="btn btn-success" id="mb-add-root-confirm"><i class="bi bi-check-lg"></i> Erstellen</button>
                    <button class="btn btn-secondary" id="mb-add-root-cancel">Abbrechen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/mumble-channels.js"></script>
<?php
