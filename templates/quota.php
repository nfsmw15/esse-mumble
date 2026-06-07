<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();

if (!$mumble->canManageQuotas()) { \Esse\Router::abort(403); return; }

$mb_csrf  = Auth::csrfToken();
$mb_ranks = $mumble->getAllRanks();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }
    $quotas = $_POST['q'] ?? [];
    foreach ($quotas as $role => $qdata) {
        $mumble->saveQuota(
            (string)$role,
            max(0, (int)($qdata['max_servers'] ?? 0)),
            max(1, (int)($qdata['max_users_cap'] ?? 25)),
            !empty($qdata['can_create'])
        );
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quotas gespeichert.'];
    header('Location: /mumble/quota'); exit;
}

?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<form method="post" action="/mumble/quota">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-list"></i> Berechtigungen je Rolle</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr>
                        <th>Rolle</th>
                        <th title="Maximale Anzahl Server, die ein Mitglied dieser Rolle anlegen darf">Max. Server</th>
                        <th title="Obergrenze für die max_users-Einstellung pro Server">Max. Nutzer/Server</th>
                        <th class="text-center">Server erstellen</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($mb_ranks as $r):
                        $role = (string)$r['id'];
                        $q    = $mumble->getQuotaForRole($role);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$r['name']) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($role) ?></small></td>
                            <td>
                                <input type="number" class="form-control form-control-sm" style="width:90px;"
                                       name="q[<?= htmlspecialchars($role) ?>][max_servers]"
                                       min="0" max="999" value="<?= (int)$q['max_servers'] ?>">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm" style="width:90px;"
                                       name="q[<?= htmlspecialchars($role) ?>][max_users_cap]"
                                       min="1" max="500" value="<?= (int)$q['max_users_cap'] ?>">
                            </td>
                            <td class="text-center align-middle">
                                <input type="checkbox" class="form-check-input"
                                       name="q[<?= htmlspecialchars($role) ?>][can_create]" value="1"
                                    <?= (int)$q['can_create'] === 1 ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Quotas speichern
            </button>
        </div>
    </div>
</form>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Admins (Rolle "admin" und höher) haben keine Quota-Beschränkung und können immer Server anlegen.
    Host-Admins können ebenfalls unbegrenzt Server auf ihren zugewiesenen Hosts anlegen.
</div>
<?php
