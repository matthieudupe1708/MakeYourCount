<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_login();

$db = require __DIR__ . '/config/database.php';
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$uid = current_user_id();
$gid = (int)($_GET['group_id'] ?? 0);
if ($gid <= 0) redirect('dashboard.php');

// check membership
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('Forbidden');
}

$stmt = $pdo->prepare("SELECT id, name FROM groups_myc WHERE id=?");
$stmt->execute([$gid]);
$group = $stmt->fetch();

// members
$stmt = $pdo->prepare("
    SELECT u.id, u.username
    FROM users u
    JOIN group_members gm ON gm.user_id=u.id
    WHERE gm.group_id=?
");
$stmt->execute([$gid]);
$members = $stmt->fetchAll();

$names = [];
foreach ($members as $m) $names[(int)$m['id']] = $m['username'];

// paid per user (sum of expenses as payer)
$stmt = $pdo->prepare("
    SELECT payer_id AS user_id, COALESCE(SUM(amount),0) AS paid
    FROM expenses
    WHERE group_id=?
    GROUP BY payer_id
");
$stmt->execute([$gid]);
$paid = [];
foreach ($stmt->fetchAll() as $r) $paid[(int)$r['user_id']] = (float)$r['paid'];

// owed per user (sum of shares)
$stmt = $pdo->prepare("
    SELECT es.user_id, COALESCE(SUM(es.share_amount),0) AS owed
    FROM expense_shares es
    JOIN expenses e ON e.id = es.expense_id
    WHERE e.group_id=?
    GROUP BY es.user_id
");
$stmt->execute([$gid]);
$owed = [];
foreach ($stmt->fetchAll() as $r) $owed[(int)$r['user_id']] = (float)$r['owed'];

// net = paid - owed
$net = [];
foreach ($names as $id => $_) {
    $net[$id] = round(($paid[$id] ?? 0) - ($owed[$id] ?? 0), 2);
}

// build creditors and debtors
$creditors = [];
$debtors = [];
foreach ($net as $id => $v) {
    if ($v > 0.009) $creditors[] = ['id'=>$id, 'amt'=>$v];
    if ($v < -0.009) $debtors[] = ['id'=>$id, 'amt'=>abs($v)];
}

// match
$settles = [];
$i=0; $j=0;
while ($i < count($debtors) && $j < count($creditors)) {
    $d = &$debtors[$i];
    $c = &$creditors[$j];
    $x = min($d['amt'], $c['amt']);
    $x = round($x, 2);
    if ($x > 0) {
        $settles[] = [
            'from' => $d['id'],
            'to'   => $c['id'],
            'amt'  => $x
        ];
        $d['amt'] = round($d['amt'] - $x, 2);
        $c['amt'] = round($c['amt'] - $x, 2);
    }
    if ($d['amt'] <= 0.009) $i++;
    if ($c['amt'] <= 0.009) $j++;
}

render_header(t('settlements'));
flash_show();
?>
<div class="card">
  <h2><?= e(t('settlements')) ?> — <?= e($group['name'] ?? '') ?></h2>
  <p class="small"><?= e(t('who_owes')) ?></p>
</div>

<div class="grid">
  <div class="card">
    <h3>Solde net (payé - dû)</h3>
    <table>
      <thead><tr><th>Membre</th><th>Payé</th><th>Dû</th><th>Net</th></tr></thead>
      <tbody>
      <?php foreach ($names as $id => $username): ?>
        <tr>
          <td><?= e($username) ?></td>
          <td><?= e(number_format((float)($paid[$id] ?? 0), 2)) ?></td>
          <td><?= e(number_format((float)($owed[$id] ?? 0), 2)) ?></td>
          <td><?= e(number_format((float)($net[$id] ?? 0), 2)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Règlements proposés</h3>
    <?php if (!$settles): ?>
      <p class="small">Rien à régler (ou pas de dépenses).</p>
    <?php else: ?>
      <ul>
        <?php foreach ($settles as $s): ?>
          <li>
            <strong><?= e($names[$s['from']] ?? '') ?></strong>
            doit
            <strong><?= e(number_format((float)$s['amt'], 2)) ?></strong>
            à
            <strong><?= e($names[$s['to']] ?? '') ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <p class="small" style="margin-top:12px;">
      <a href="group_view.php?id=<?= $gid ?>">← Retour au groupe</a>
    </p>
  </div>
</div>

<?php render_footer(); ?>
