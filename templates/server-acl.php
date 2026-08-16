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
<p class="text-muted mb-3"><i class="bi bi-check-circle-fill text-success"></i> Änderungen werden <strong>sofort</strong> übernommen — kein Server-Neustart nötig.</p>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-header py-2"><i class="bi bi-diagram-3"></i> Channels</div>
            <div class="card-body p-2 mb-scroll-tree" id="mb-channel-tree">
                <div class="text-muted small p-2"><i class="bi bi-hourglass-split"></i> Lade...</div>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div id="mb-acl-placeholder" class="alert alert-info">
            <i class="bi bi-arrow-left"></i>&nbsp; Channel im Baum auswählen um dessen ACL zu bearbeiten.
        </div>

        <div id="mb-acl-editor" class="mb-hidden">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-lock-fill"></i> ACL: <span id="mb-channel-name">-</span></strong>
                    <label class="mb-0 small fw-normal">
                        <input type="checkbox" id="mb-inherit-acl"> Von Eltern-Channel erben
                    </label>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">ACL-Einträge</h6>
                        <button class="btn btn-sm btn-success" id="mb-add-acl"><i class="bi bi-plus-lg"></i> Eintrag</button>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="mb-acl-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Ziel</th>
                                    <th class="text-center" title="Gilt für diesen Channel">Hier</th>
                                    <th class="text-center" title="Gilt für Unter-Channels">Sub</th>
                                    <th>Erteilt</th><th>Verweigert</th>
                                    <th class="text-center">Rechte</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="mb-acl-body"></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Gruppen</h6>
                        <button class="btn btn-sm btn-success" id="mb-add-group"><i class="bi bi-plus-lg"></i> Gruppe</button>
                    </div>
                    <div id="mb-groups-container" class="mb-4"></div>

                    <div class="border-top pt-3">
                        <button class="btn btn-primary" id="mb-save-btn"><i class="bi bi-floppy"></i> Speichern</button>
                        <span id="mb-save-status" class="ms-3 small"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rechte bearbeiten -->
<div class="modal fade" id="mb-perm-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-key"></i> Rechte: <strong id="mb-modal-target">-</strong></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="mb-modal-body"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary btn-sm" id="mb-modal-apply">Übernehmen</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<script src="/plugins/esse-mumble/assets/mumble-acl.js"></script>
<?php
