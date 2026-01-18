<?php
// group_view.php
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
$gid = (int)($_GET['id'] ?? 0);
if ($gid <= 0) redirect('dashboard.php');

// Vérifie que l'utilisateur est membre du groupe
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('Forbidden');
}

// Infos groupe
$stmt = $pdo->prepare("SELECT id, name, owner_id, join_code FROM groups_myc WHERE id=?");
$stmt->execute([$gid]);
$group = $stmt->fetch();
if (!$group) redirect('dashboard.php');

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

// --- Calcul soldes (payé - dû) pour trier les membres ---
$stmt = $pdo->prepare("
    SELECT payer_id AS user_id, COALESCE(SUM(amount),0) AS paid
    FROM expenses
    WHERE group_id=?
    GROUP BY payer_id
");
$stmt->execute([$gid]);
$paid = [];
foreach ($stmt->fetchAll() as $r) {
    $paid[(int)$r['user_id']] = (float)$r['paid'];
}

$stmt = $pdo->prepare("
    SELECT es.user_id, COALESCE(SUM(es.share_amount),0) AS owed
    FROM expense_shares es
    JOIN expenses e ON e.id = es.expense_id
    WHERE e.group_id=?
    GROUP BY es.user_id
");
$stmt->execute([$gid]);
$owed = [];
foreach ($stmt->fetchAll() as $r) {
    $owed[(int)$r['user_id']] = (float)$r['owed'];
}

$membersWithBalance = [];
foreach ($members as $m) {
    $id = (int)$m['id'];
    $p = (float)($paid[$id] ?? 0);
    $o = (float)($owed[$id] ?? 0);
    $net = round($p - $o, 2);
    $membersWithBalance[] = [
        'id' => $id,
        'username' => $m['username'],
        'paid' => $p,
        'owed' => $o,
        'net' => $net,
    ];
}

// Tri : du plus négatif au plus positif
usort($membersWithBalance, fn($a, $b) => $a['net'] <=> $b['net']);

// Dépenses du groupe (dernières)
$stmt = $pdo->prepare("
    SELECT e.id, e.amount, e.description, e.expense_date, e.created_at, u.username AS payer_name
    FROM expenses e
    JOIN users u ON u.id = e.payer_id
    WHERE e.group_id=?
    ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC
    LIMIT 50
");
$stmt->execute([$gid]);
$expenses = $stmt->fetchAll();

render_header($group['name']);
flash_show();

// --- Stats: total groupe, total payé par moi, top 3 dépensiers (payé) ---

// Total dépenses groupe
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE group_id=?");
$stmt->execute([$gid]);
$groupTotal = (float)$stmt->fetchColumn();

// Total payé par moi
$stmt = $pdo->prepare("SELECT COALESCE(SUM(es.share_amount), 0)FROM expense_shares es JOIN expenses e ON e.id = es.expense_id WHERE e.group_id = ? AND es.user_id = ?;");
$stmt->execute([$gid, $uid]);
$myPaidTotal = (float)$stmt->fetchColumn();

// Top 3 dépensiers (somme payée par personne)
$stmt = $pdo->prepare("
  SELECT u.id, u.username, COALESCE(SUM(es.share_amount),0) AS total_share
  FROM users u
  JOIN expense_shares es ON es.user_id = u.id
  JOIN expenses e ON e.id = es.expense_id
  WHERE e.group_id = ?
  GROUP BY u.id, u.username
  ORDER BY total_share DESC
  LIMIT 3;
");
$stmt->execute([$gid]);
$topSpenders = $stmt->fetchAll();

?>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin-bottom:6px;"><?= e($group['name']) ?></h2>
      <?php if (!empty($group['join_code'])): ?>
        <p class="small" style="margin:0;">
          Code : <code><?= e($group['join_code']) ?></code>
        </p>
      <?php endif; ?>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="button" href="expense_add.php?group_id=<?= (int)$group['id'] ?>">+ <?= e(t('add_expense')) ?></a>
      <a class="button" href="dashboard.php"><?= e(t('dashboard')) ?></a>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-bottom:10px;">Statistiques</h3>

  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total du groupe</div>
      <div class="stat-value"><?= e(number_format($groupTotal, 2, '.', '')) ?> €</div>
    </div>

    <div class="stat-box">
      <div class="stat-label">Total payé par toi</div>
      <div class="stat-value"><?= e(number_format($myPaidTotal, 2, '.', '')) ?> €</div>
    </div>
  </div>

  <div style="margin-top:12px;">
    <div class="small" style="margin:0 0 8px 2px;">Podium des plus gros dépensiers</div>

    <?php if (!$topSpenders): ?>
      <p class="small" style="margin:0;">Aucune dépense pour l’instant.</p>
    <?php else: ?>
      <div class="podium">
        <?php foreach ($topSpenders as $i => $p): ?>
          <?php
            $rank = $i + 1;
            $name = $p['username'];
            $amt  = (float)$p['total_share'];
            $isMe = ((int)$p['id'] === $uid);
          ?>
          <div class="podium-row">
            <div class="podium-left">
              <div class="podium-rank"><?= $rank ?></div>
              <div class="podium-name">
                <?= e($name) ?><?= $isMe ? ' <span class="small" style="opacity:.8;">(toi)</span>' : '' ?>
              </div>
            </div>
            <div class="podium-amount"><?= e(number_format($amt, 2, '.', '')) ?> €</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>


<div class="card">
  <h3 style="margin-bottom:10px;"><?= e(t('members')) ?></h3>

  <?php if (!$membersWithBalance): ?>
    <p class="small">Aucun membre.</p>
  <?php else: ?>
    <div class="member-list">
      <?php foreach ($membersWithBalance as $m): ?>
        <?php
          $net = (float)$m['net'];
          $cls = 'zero';
          if ($net > 0.009) $cls = 'pos';
          if ($net < -0.009) $cls = 'neg';
          $sign = $net > 0 ? '+' : '';
          $isMe = ((int)$m['id'] === $uid);
        ?>
        <div class="member-row">
          <div class="member-name">
            <?= e($m['username']) ?><?= $isMe ? ' <span class="small" style="opacity:.8;">(toi)</span>' : '' ?>
          </div>
          <div class="member-balance <?= e($cls) ?>">
            <?= e($sign . number_format($net, 2, '.', '')) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="small" style="margin-top:10px; margin-bottom:0;">
      <span class="pos">Positif</span> : le membre a avancé de l'argent.<br>
      <span class="neg">Négatif</span> : le membre doit de l'argent.  
    </p>
  <?php endif; ?>
</div>

<?php
// --- Regroupement par date ---
$expensesByDate = [];
foreach ($expenses as $ex) {
    $dateKey = $ex['expense_date'] ?: substr((string)$ex['created_at'], 0, 10);
    if (!isset($expensesByDate[$dateKey])) $expensesByDate[$dateKey] = [];
    $expensesByDate[$dateKey][] = $ex;
}
?>

<div class="card">
  <h3 style="margin-bottom:10px;"><?= e(t('expenses')) ?></h3>

  <?php if (!$expenses): ?>
    <p class="small">Aucune dépense.</p>
  <?php else: ?>
    <div class="expenses-by-day">
      <?php foreach ($expensesByDate as $date => $items): ?>
        <div>
          <p class="day-title"><?= e($date) ?></p>

          <div class="expense-list">
            <?php foreach ($items as $ex): ?>
              <?php
                $desc = $ex['description'] ?: '—';
                $payer = $ex['payer_name'] ?: '—';
                $amount = number_format((float)$ex['amount'], 2, '.', '');
              ?>
              <a class="expense-row" href="expense_view.php?id=<?= (int)$ex['id'] ?>" style="text-decoration:none; color:inherit;">
                <div class="expense-left">
                  <!-- Icône billet -->
                  <svg class="expense-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm2 0v10h14V7H5zm7 2.25A2.75 2.75 0 1 0 12 14.75 2.75 2.75 0 0 0 12 9.25zM7 9a2 2 0 0 0 2-2H7v2zm0 8h2a2 2 0 0 0-2-2v2zm10 0v-2a2 2 0 0 0-2 2h2zm0-8V7h-2a2 2 0 0 0 2 2z"/>
                  </svg>

                  <div class="expense-text">
                    <div class="expense-desc"><?= e($desc) ?></div>
                    <div class="expense-sub">Payé par <b><?= e($payer) ?></b></div>
                  </div>
                </div>

                <div class="expense-amount"><?= e($amount) ?> €</div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>



<?php render_footer(); ?>
