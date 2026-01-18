<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/csrf.php';

require_login();

$db = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit("Erreur DB : " . htmlspecialchars($e->getMessage()));
}

$uid = current_user_id();
$gid = (int)($_GET['group_id'] ?? 0);
if ($gid <= 0) redirect('dashboard.php');

// Vérifie que l'utilisateur est membre du groupe
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('Forbidden');
}

// Liste des membres du groupe
$stmt = $pdo->prepare("
    SELECT u.id, u.username
    FROM users u
    JOIN group_members gm ON gm.user_id=u.id
    WHERE gm.group_id=?
    ORDER BY u.username
");
$stmt->execute([$gid]);
$members = $stmt->fetchAll();

// Map membres autorisés
$memberMap = [];
foreach ($members as $m) {
    $memberMap[(int)$m['id']] = $m['username'];
}

function cents_to_decimal(int $cents): string {
    return number_format($cents / 100, 2, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $payer_id = (int)($_POST['payer_id'] ?? 0);
    $amount_raw = (string)($_POST['amount'] ?? '0');
    $description = trim((string)($_POST['description'] ?? ''));
    $date = $_POST['expense_date'] ?? null;

    // Participants cochés
    $participants = $_POST['participants'] ?? [];
    if (!is_array($participants)) $participants = [];

    // Nettoyage / validation participants
    $participants = array_values(array_unique(array_map('intval', $participants)));
    $participants = array_values(array_filter($participants, fn($id) => isset($memberMap[$id])));

    if (!isset($memberMap[$payer_id])) {
        flash_set('error', "Payeur invalide / Invalid payer");
        redirect("expense_add.php?group_id=$gid");
    }

    // Montant -> cents
    $amount_float = (float)str_replace(',', '.', $amount_raw);
    $amount_cents = (int)round($amount_float * 100);

    if ($amount_cents <= 0) {
        flash_set('error', "Montant invalide / Invalid amount");
        redirect("expense_add.php?group_id=$gid");
    }

    if (count($participants) < 1) {
        flash_set('error', "Choisis au moins 1 participant / Select at least 1 participant");
        redirect("expense_add.php?group_id=$gid");
    }

    // Répartition équitable avec gestion des centimes (pour que la somme des parts = montant)
    $n = count($participants);
    $base = intdiv($amount_cents, $n);
    $rem = $amount_cents - ($base * $n);

    // Exemple: 10.00€ à 3 -> base 3.33€ + 0.01€ sur les 1ers
    $shares_cents = [];
    foreach ($participants as $idx => $pid) {
        $shares_cents[$pid] = $base + ($idx < $rem ? 1 : 0);
    }

    $pdo->beginTransaction();
    try {
        // Insert dépense
        $stmt = $pdo->prepare("
            INSERT INTO expenses (group_id, payer_id, amount, description, expense_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $gid,
            $payer_id,
            cents_to_decimal($amount_cents),
            $description === '' ? null : $description,
            $date ?: null
        ]);
        $eid = (int)$pdo->lastInsertId();

        // Insert parts
        $stmtShare = $pdo->prepare("
            INSERT INTO expense_shares (expense_id, user_id, share_amount)
            VALUES (?, ?, ?)
        ");
        foreach ($shares_cents as $pid => $cents) {
            $stmtShare->execute([$eid, $pid, cents_to_decimal($cents)]);
        }

        $pdo->commit();
        flash_set('success', "Dépense ajoutée / Expense added");
        redirect("group_view.php?id=$gid");
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        exit("Erreur DB : " . htmlspecialchars($e->getMessage()));
    }
}

render_header(t('add_expense'));
flash_show();
?>
<div class="card">
  <h2><?= e(t('add_expense')) ?></h2>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label><?= e(t('payer')) ?></label>
    <select name="payer_id" required>
      <?php foreach ($members as $m): ?>
        <option value="<?= (int)$m['id'] ?>" <?= ((int)$m['id'] === $uid ? 'selected' : '') ?>>
          <?= e($m['username']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label><?= e(t('amount')) ?></label>
    <input name="amount" type="number" step="0.01" min="0.01" required>

    <label><?= e(t('description')) ?></label>
    <input name="description" placeholder="Restaurant, courses..." />

    <label><?= e(t('date')) ?></label>
    <input name="expense_date" type="date">

    <label>Participants</label>

    <div class="participant-chips">
      <?php foreach ($members as $m): ?>
        <label class="chip">
          <input type="checkbox" name="participants[]" value="<?= (int)$m['id'] ?>" unchecked>
          <span class="chip__text"><?= e($m['username']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <p class="small">Clique sur les prénoms concernés (répartition égale entre les sélectionnés).</p>

    <div style="margin-top:12px;">
      <button type="submit"><?= e(t('save')) ?></button>
      <a href="group_view.php?id=<?= $gid ?>" style="margin-left:10px;">Retour / Back</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
