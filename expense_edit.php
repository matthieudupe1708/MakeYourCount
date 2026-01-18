<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/csrf.php';

require_login();

$db = require __DIR__ . '/config/database.php';
$pdo = new PDO(
  "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
  $db['user'],
  $db['pass'],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$uid = current_user_id();
$eid = (int)($_GET['id'] ?? 0);
if ($eid <= 0) redirect('dashboard.php');

// Dépense
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=?");
$stmt->execute([$eid]);
$ex = $stmt->fetch();
if (!$ex) redirect('dashboard.php');

$gid = (int)$ex['group_id'];

// Droits groupe
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) { http_response_code(403); exit('Forbidden'); }

// Membres du groupe
$stmt = $pdo->prepare("
  SELECT u.id, u.username
  FROM users u
  JOIN group_members gm ON gm.user_id=u.id
  WHERE gm.group_id=?
  ORDER BY u.username
");
$stmt->execute([$gid]);
$members = $stmt->fetchAll();

// Participants actuels
$stmt = $pdo->prepare("SELECT user_id FROM expense_shares WHERE expense_id=?");
$stmt->execute([$eid]);
$cur = array_map(fn($r) => (int)$r['user_id'], $stmt->fetchAll());
$curSet = array_fill_keys($cur, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $desc = trim((string)($_POST['description'] ?? ''));
  $date = trim((string)($_POST['expense_date'] ?? ''));
  $payer = (int)($_POST['payer_id'] ?? 0);
  $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
  $parts = $_POST['participants'] ?? [];
  $parts = array_values(array_unique(array_map('intval', is_array($parts) ? $parts : [])));

  if ($desc === '' || $amount <= 0 || $payer <= 0 || !$date) {
    flash_set('error', 'Champs invalides.');
    redirect("expense_edit.php?id={$eid}");
  }
  if (count($parts) === 0) {
    flash_set('error', 'Sélectionne au moins un participant.');
    redirect("expense_edit.php?id={$eid}");
  }

  // sécurité : payer doit être membre du groupe
  $memberIds = array_map(fn($m) => (int)$m['id'], $members);
  if (!in_array($payer, $memberIds, true)) {
    flash_set('error', 'Payeur invalide.');
    redirect("expense_edit.php?id={$eid}");
  }

  // Répartition égale
  $share = round($amount / count($parts), 2);

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare("UPDATE expenses SET description=?, amount=?, payer_id=?, expense_date=? WHERE id=?");
    $stmt->execute([$desc, $amount, $payer, $date, $eid]);

    $pdo->prepare("DELETE FROM expense_shares WHERE expense_id=?")->execute([$eid]);

    $ins = $pdo->prepare("INSERT INTO expense_shares (expense_id, user_id, share_amount) VALUES (?,?,?)");
    foreach ($parts as $pid) {
      $ins->execute([$eid, $pid, $share]);
    }

    $pdo->commit();
    flash_set('success', 'Dépense modifiée.');
    redirect("expense_view.php?id={$eid}");
  } catch (Throwable $t) {
    $pdo->rollBack();
    flash_set('error', 'Erreur lors de la modification.');
    redirect("expense_edit.php?id={$eid}");
  }
}

render_header('Éditer dépense');
flash_show();
?>
<div class="card">
  <div class="detail-head">
    <h2 style="margin:0;">Éditer la dépense</h2>
    <a class="btn" href="expense_view.php?id=<?= (int)$eid ?>">Annuler</a>
  </div>

  <form method="post" style="margin-top:10px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>Descriptif</label>
    <input name="description" value="<?= e($ex['description'] ?? '') ?>" required>

    <div class="grid">
      <div>
        <label>Date</label>
        <input type="date" name="expense_date" value="<?= e($ex['expense_date'] ?: substr((string)$ex['created_at'],0,10)) ?>" required>
      </div>
      <div>
        <label>Montant</label>
        <input name="amount" value="<?= e(number_format((float)$ex['amount'], 2, '.', '')) ?>" required>
      </div>
    </div>

    <label>Payé par</label>
    <select name="payer_id" required>
      <?php foreach ($members as $m): ?>
        <option value="<?= (int)$m['id'] ?>" <?= ((int)$ex['payer_id'] === (int)$m['id']) ? 'selected' : '' ?>>
          <?= e($m['username']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Participants</label>
    <div class="participant-chips">
      <?php foreach ($members as $m): ?>
        <?php $checked = isset($curSet[(int)$m['id']]); ?>
        <label class="chip">
          <input type="checkbox" name="participants[]" value="<?= (int)$m['id'] ?>" <?= $checked ? 'checked' : '' ?>>
          <span class="chip__text"><?= e($m['username']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:12px;">
      <button class="btn primary" type="submit">Enregistrer</button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
