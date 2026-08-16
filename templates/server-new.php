<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$mumble = new \EsseMumble\MumbleRepository();

if (!$mumble->canCreate()) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Keine Berechtigung zum Anlegen von Mumble-Servern.'];
    header('Location: /mumble/servers'); exit;
}

$mb_uid       = (int)Auth::id();
$mb_quota     = $mumble->getQuotaForRole(Auth::role());
$mb_owned     = $mumble->countServersByOwner($mb_uid);
$mb_csrf      = Auth::csrfToken();
$mb_isAdmin   = $mumble->canAdminAll();
$mb_isHostAdm = $mumble->isHostAdmin();

if ($mb_isHostAdm && !$mb_isAdmin) {
    $mb_adminHostIds = $mumble->getAdminHostIds();
    $mb_hosts = array_values(array_filter(
        $mumble->listHosts(true),
        fn($h) => in_array((int)$h['id'], $mb_adminHostIds)
    ));
} else {
    $mb_hosts = $mumble->listHosts(true);
}

if (!$mb_isAdmin && !$mb_isHostAdm && $mb_owned >= (int)$mb_quota['max_servers']) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Du hast dein Server-Kontingent ('.((int)$mb_quota['max_servers']).') bereits erreicht.'];
    header('Location: /mumble/servers'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { http_response_code(403); exit; }
    try {
        $serverId = $mumble->createServer($_POST);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Server erfolgreich erstellt und gestartet.'];
        header('Location: /mumble/edit/'.$serverId); exit;
    } catch (\RuntimeException $e) {
        $errors[] = $e->getMessage();
    }
}

?>
<div class="mb-3">
    <a href="/mumble/servers" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if (empty($mb_hosts)): ?>
<div class="alert alert-warning">Es ist kein aktiver Mumble-Host konfiguriert. Bitte einen Administrator kontaktieren.</div>
<?php return; endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-headphones"></i> Server-Daten</div>
            <div class="card-body">
                <form action="/mumble/new" method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($mb_csrf) ?>">

                    <div class="mb-3">
                        <label for="mb-name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mb-name" name="name"
                               maxlength="64" required
                               placeholder="z.B. MSG Meisenthal Sprachkanal"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        <div class="form-text">Erlaubte Zeichen: Buchstaben, Zahlen, Leerzeichen, Bindestrich, Unterstrich, Punkt</div>
                    </div>

                    <div class="mb-3">
                        <label for="mb-host" class="form-label">Host <span class="text-danger">*</span></label>
                        <?php if (count($mb_hosts) === 1): ?>
                        <input type="hidden" name="host_id" value="<?= (int)$mb_hosts[0]['id'] ?>">
                        <input type="text" class="form-control" disabled
                               value="<?= htmlspecialchars((string)$mb_hosts[0]['name']) ?> (<?= htmlspecialchars((string)$mb_hosts[0]['hostname']) ?>)">
                        <?php else: ?>
                        <select class="form-select" id="mb-host" name="host_id" required>
                            <?php foreach ($mb_hosts as $h): ?>
                            <option value="<?= (int)$h['id'] ?>" <?= ((int)($_POST['host_id'] ?? 0) === (int)$h['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$h['name']) ?> (<?= htmlspecialchars((string)$h['hostname']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="mb-pw" class="form-label">Server-Passwort <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="mb-pw" name="password"
                               maxlength="64" autocomplete="off" placeholder="leer = öffentlich"
                               value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <?php $mb_maxCap = ($mb_isAdmin || $mb_isHostAdm) ? 9999 : (int)$mb_quota['max_users_cap']; ?>
                        <label for="mb-max" class="form-label">Maximale Nutzer</label>
                        <input type="number" class="form-control" id="mb-max" name="max_users"
                               min="1" max="<?= $mb_maxCap ?>"
                               value="<?= htmlspecialchars((string)($_POST['max_users'] ?? 10)) ?>">
                        <?php if (!$mb_isAdmin && !$mb_isHostAdm): ?>
                        <div class="form-text">Deine Rolle erlaubt bis zu <?= $mb_maxCap ?> gleichzeitige Nutzer pro Server.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="mb-welcome" class="form-label">Begrüßungstext <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="mb-welcome" name="welcome_text"
                                  rows="4" maxlength="2000"><?= htmlspecialchars($_POST['welcome_text'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Server anlegen</button>
                    <a href="/mumble/servers" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Abbrechen</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-info-circle"></i> Hinweise</div>
            <div class="card-body">
                <?php if (!$mb_isAdmin && !$mb_isHostAdm): ?>
                <p class="mb-1"><strong>Eigene Server:</strong></p>
                <?php
                $cap    = (int)$mb_quota['max_servers'];
                $pct    = $cap > 0 ? min(100, (int)round($mb_owned / $cap * 100)) : 0;
                $pctCls = 'mb-w-'.max(0, min(100, (int)round($pct / 5) * 5));
                ?>
                <div class="progress mb-3 mb-progress-h24">
                    <div class="progress-bar bg-info <?= $pctCls ?>" role="progressbar">
                        <?= $mb_owned ?> / <?= $cap ?>
                    </div>
                </div>
                <p class="mb-1"><strong>Max. Nutzer pro Server:</strong></p>
                <p><?= (int)$mb_quota['max_users_cap'] ?></p>
                <hr>
                <?php endif; ?>
                <p class="text-muted small mb-0">Nach dem Anlegen wird der Server automatisch gestartet.</p>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/plugins/esse-mumble/assets/mumble.css">
<?php
