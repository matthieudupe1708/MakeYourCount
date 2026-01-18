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

// Récupère dépense + groupe + payeur
$stmt = $pdo->prepare("
  SELECT e.*, g.name AS group_name, g.id AS group_id, u.username AS payer_name
  FROM expenses e
  JOIN groups_myc g ON g.id = e.group_id
  JOIN users u ON u.id = e.payer_id
  WHERE e.id = ?
");
$stmt->execute([$eid]);
$ex = $stmt->fetch();
if (!$ex) redirect('dashboard.php');

$gid = (int)$ex['group_id'];

// Vérifie que l'utilisateur est membre du groupe
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) {
  http_response_code(403);
  exit('Forbidden');
}

// Participants (shares)
$stmt = $pdo->prepare("
  SELECT es.user_id, es.share_amount, u.username
  FROM expense_shares es
  JOIN users u ON u.id = es.user_id
  WHERE es.expense_id=?
  ORDER BY u.username
");
$stmt->execute([$eid]);
$shares = $stmt->fetchAll();

$participantCount = count($shares);
$amount = (float)$ex['amount'];
$split = $participantCount > 0 ? round($amount / $participantCount, 2) : 0.0;

function format_date_fr(string $ymd): string {
  // ymd = YYYY-MM-DD
  $dt = DateTime::createFromFormat('Y-m-d', $ymd) ?: new DateTime($ymd);
  if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter(
      'fr_FR',
      IntlDateFormatter::FULL,
      IntlDateFormatter::NONE,
      'Europe/Paris',
      IntlDateFormatter::GREGORIAN,
      "EEEE d MMMM y"
    );
    $out = $fmt->format($dt);
    if (is_string($out)) return $out;
  }
  // fallback simple
  return $dt->format('Y-m-d');
}

$dateKey = $ex['expense_date'] ?: substr((string)$ex['created_at'], 0, 10);
$dateLabel = format_date_fr($dateKey);

render_header('Dépense');
flash_show();
?>
<div class="card">
  <div class="detail-head">
    <div style="min-width:0;">
      <h2 style="margin:0 0 6px;"><?= e($ex['description'] ?: 'Dépense') ?></h2>
      <div class="small">
        <?= e($dateLabel) ?> · Groupe : <b><?= e($ex['group_name']) ?></b>
      </div>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <a class="btn" href="group_view.php?id=<?= (int)$gid ?>">Retour</a>
      <a class="btn primary" href="expense_edit.php?id=<?= (int)$eid ?>">Éditer</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-title">Payé par</div>
      <div class="kpi-value"><?= e($ex['payer_name']) ?></div>
      <div class="small" style="margin-top:6px;">Montant</div>
      <div class="kpi-value price-orange"><?= e(number_format($amount, 2, '.', '')) ?> €</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Participants</div>
      <div class="kpi-value"><?= (int)$participantCount ?></div>
      <div class="small" style="margin-top:6px;">Répartition</div>
      <div class="kpi-value"><?= e(number_format($split, 2, '.', '')) ?> € / pers</div>
      <div class="small" style="margin-top:6px;">(payeur inclus)</div>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin:0 0 10px;">Participants</h3>

  <?php if (!$shares): ?>
    <p class="small">Aucun participant enregistré.</p>
  <?php else: ?>
    <div class="list-soft">
      <?php foreach ($shares as $s): ?>
        <div class="list-soft-row">
          <div style="font-weight:700;"><?= e($s['username']) ?></div>
          <div class="small"><?= e(number_format((float)$s['share_amount'], 2, '.', '')) ?> €</div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
