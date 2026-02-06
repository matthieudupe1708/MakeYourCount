<?php
// group_view.php
session_start();

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/purge.php';

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

$isOwner = ((int)$group['owner_id'] === (int)$uid);

// Purge automatique (> 6 mois) + snapshot
myc_purge_group_if_needed($pdo, $gid);
$snapshotId = myc_get_latest_snapshot_id($pdo, $gid);

// --- Gestion lien de connexion permanent (générer / régénérer / révoquer) ---
$generatedLink = null;

// --- Actions POST (lien connexion + suppression membre) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'regen_link') {
        $token = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare("
          INSERT INTO group_login_links (group_id, user_id, token_hash)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$gid, $uid, $tokenHash]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $generatedLink = "{$scheme}://{$host}{$basePath}/link_login.php?group_id={$gid}&token={$token}";

        flash_set('success', 'Nouveau lien généré (l’ancien est désormais invalide). Copie-le maintenant.');
    }

    if ($action === 'revoke_link') {
        $stmt = $pdo->prepare("DELETE FROM group_login_links WHERE group_id=? AND user_id=?");
        $stmt->execute([$gid, $uid]);
        flash_set('success', 'Lien révoqué.');
        redirect("group_view.php?id={$gid}");
    }

    if ($action === 'remove_member') {
        if (!$isOwner) {
            http_response_code(403);
            exit('Forbidden');
        }

        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId <= 0) {
            flash_set('error', 'Sélectionne un participant à supprimer.');
            redirect("group_view.php?id={$gid}");
        }

        if ($targetId === (int)$group['owner_id']) {
            flash_set('error', 'Impossible de supprimer le propriétaire du groupe.');
            redirect("group_view.php?id={$gid}");
        }

        $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
        $stmt->execute([$gid, $targetId]);
        if (!$stmt->fetchColumn()) {
            flash_set('error', 'Ce membre ne fait pas partie du groupe.');
            redirect("group_view.php?id={$gid}");
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
              DELETE es FROM expense_shares es
              JOIN expenses e ON e.id = es.expense_id
              WHERE e.group_id = ? AND es.user_id = ?
            ");
            $stmt->execute([$gid, $targetId]);

            $stmt = $pdo->prepare("DELETE FROM group_login_links WHERE group_id=? AND user_id=?");
            $stmt->execute([$gid, $targetId]);

            $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
            $stmt->execute([$gid, $targetId]);

            $pdo->commit();
            flash_set('success', "Participant supprimé : ses dettes ont été retirées, sans modifier les parts des autres ni le total des dépenses.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', "Erreur lors de la suppression du participant.");
        }

        redirect("group_view.php?id={$gid}");
    }
}

// Etat lien existant
$stmt = $pdo->prepare("SELECT created_at FROM group_login_links WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
$linkRow = $stmt->fetch();
$hasLink = (bool)$linkRow;

// Snapshot (soldes / conso / total groupe)
$snapGroupTotal = 0.0;
$snapBalances = [];
$snapPersonalTotals = [];
if ($snapshotId) {
    $stmt = $pdo->prepare("SELECT group_total FROM group_snapshot_group WHERE snapshot_id=?");
    $stmt->execute([$snapshotId]);
    $snapGroupTotal = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT user_id, balance, personal_total FROM group_snapshot_user WHERE snapshot_id=?");
    $stmt->execute([$snapshotId]);
    foreach ($stmt->fetchAll() as $r) {
        $snapBalances[(int)$r['user_id']] = (float)$r['balance'];
        $snapPersonalTotals[(int)$r['user_id']] = (float)$r['personal_total'];
    }
}

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

// --- Totaux LIVE ---
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=?");
$stmt->execute([$gid]);
$liveGroupTotal = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(es.share_amount),0)
  FROM expense_shares es
  JOIN expenses e ON e.id = es.expense_id
  WHERE e.group_id=? AND es.user_id=?
");
$stmt->execute([$gid, $uid]);
$liveMyPersonal = (float)$stmt->fetchColumn();

$groupTotal = $snapGroupTotal + $liveGroupTotal;
$myPersonalTotal = (float)($snapPersonalTotals[$uid] ?? 0) + $liveMyPersonal;

// --- Soldes LIVE ---
$stmt = $pdo->prepare("
    SELECT payer_id AS user_id, COALESCE(SUM(amount),0) AS paid
    FROM expenses
    WHERE group_id=?
    GROUP BY payer_id
");
$stmt->execute([$gid]);
$paid = [];
foreach ($stmt->fetchAll() as $r) $paid[(int)$r['user_id']] = (float)$r['paid'];

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

$membersWithBalance = [];
foreach ($members as $m) {
    $id = (int)$m['id'];
    $p = (float)($paid[$id] ?? 0);
    $o = (float)($owed[$id] ?? 0);
    $netFinal = round(($p - $o) + (float)($snapBalances[$id] ?? 0), 2);

    $membersWithBalance[] = ['id' => $id, 'username' => $m['username'], 'net' => $netFinal];
}
usort($membersWithBalance, fn($a, $b) => $a['net'] <=> $b['net']);

// --- Classement conso FINAL (parts live + parts snapshot) ---
$stmt = $pdo->prepare("
  SELECT u.id, u.username, COALESCE(SUM(es.share_amount),0) AS live_share
  FROM users u
  JOIN group_members gm ON gm.user_id = u.id AND gm.group_id = ?
  LEFT JOIN expense_shares es ON es.user_id = u.id
  LEFT JOIN expenses e ON e.id = es.expense_id AND e.group_id = ?
  GROUP BY u.id, u.username
");
$stmt->execute([$gid, $gid]);
$rankingRows = $stmt->fetchAll();

foreach ($rankingRows as &$r) {
    $id = (int)$r['id'];
    $r['total_share'] = round((float)$r['live_share'] + (float)($snapPersonalTotals[$id] ?? 0), 2);
}
unset($r);

usort($rankingRows, fn($a, $b) => ($b['total_share'] <=> $a['total_share']));

$topSpenders = array_slice($rankingRows, 0, 3);
$restRanking = array_slice($rankingRows, 3);

// --- Dépenses, dernières 50 ---
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

$expensesByDate = [];
foreach ($expenses as $ex) {
    $dateKey = $ex['expense_date'] ?: substr((string)$ex['created_at'], 0, 10);
    $expensesByDate[$dateKey][] = $ex;
}

render_header($group['name']);
flash_show();
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
      <a class="btn primary" href="expense_add.php?group_id=<?= (int)$group['id'] ?>">+ <?= e(t('add_expense')) ?></a>
      <a class="btn" href="dashboard.php"><?= e(t('dashboard')) ?></a>
    </div>
  </div>
</div>

<!-- Lien de connexion -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
    <div style="min-width:0;">
      <h3 style="margin:0 0 6px;">Lien de connexion</h3>
      <p class="small" style="margin:0;">
        Lien permanent (sans mot de passe) pour accéder à ce groupe. Si tu régénères, l’ancien devient invalide.
        À garder secret.
      </p>
      <?php if ($hasLink && !$generatedLink): ?>
        <p class="small" style="margin-top:8px; margin-bottom:0;">
          Un lien existe déjà (créé le <?= e($linkRow['created_at']) ?>).
        </p>
      <?php endif; ?>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
      <form method="post" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="regen_link">
        <button
          class="btn <?= $hasLink ? '' : 'primary' ?>"
          type="submit"
          onclick="return confirm('<?= $hasLink
            ? "Régénérer le lien de connexion ?\\n\\nL\\'ancien lien ne fonctionnera plus."
            : "Générer un lien de connexion ?\\n\\nÀ garder secret (équivalent à un mot de passe)."
          ?>');"
        >
          <?= $hasLink ? 'Régénérer' : 'Générer' ?>
        </button>
      </form>

      <?php if ($hasLink): ?>
        <form method="post" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="revoke_link">
          <button
            class="btn"
            type="submit"
            onclick="return confirm('Révoquer le lien de connexion ?\\n\\nIl ne fonctionnera plus et il faudra en générer un nouveau.');"
          >
            Révoquer
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($generatedLink): ?>
    <div style="margin-top:12px;">
      <label class="small" style="display:block; margin-bottom:6px;">Ton lien (à copier maintenant)</label>
      <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input id="myc-link" value="<?= e($generatedLink) ?>" readonly style="flex:1; min-width:260px;">
        <button class="btn" type="button"
          onclick="navigator.clipboard.writeText(document.getElementById('myc-link').value)">
          Copier
        </button>
      </div>
      <p class="small" style="margin-top:8px; margin-bottom:0;">
        Si tu perds ce lien, régénère-en un : l’ancien sera automatiquement invalidé.
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="card">
  <h3 style="margin-bottom:10px;">Statistiques</h3>

  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total du groupe</div>
      <div class="stat-value"><?= e(number_format($groupTotal, 2, '.', '')) ?> €</div>
    </div>

    <div class="stat-box">
      <div class="stat-label">Mes dépenses (ma part)</div>
      <div class="stat-value"><?= e(number_format($myPersonalTotal, 2, '.', '')) ?> €</div>
    </div>
  </div>

  <div style="margin-top:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <div class="small" style="margin:0 0 8px 2px;">Classement des plus gros dépensiers (parts)</div>
      <?php if (count($rankingRows) > 3): ?>
        <button class="btn" type="button" id="toggle-ranking-btn">Voir plus</button>
      <?php endif; ?>
    </div>

    <?php if (!$rankingRows): ?>
      <p class="small" style="margin:0;">Aucune dépense pour l’instant.</p>
    <?php else: ?>
      <div class="podium" id="ranking-top">
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

      <?php if (count($rankingRows) > 3): ?>
        <div class="podium" id="ranking-rest" style="display:none; margin-top:8px;">
          <?php foreach ($restRanking as $idx => $p): ?>
            <?php
              $rank = $idx + 4;
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

        <script>
          (function () {
            const btn = document.getElementById('toggle-ranking-btn');
            const rest = document.getElementById('ranking-rest');
            if (!btn || !rest) return;

            let open = false;
            btn.addEventListener('click', () => {
              open = !open;
              rest.style.display = open ? 'block' : 'none';
              btn.textContent = open ? 'Voir moins' : 'Voir plus';
            });
          })();
        </script>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Membres -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
    <div>
      <h3 style="margin-bottom:6px;"><?= e(t('members')) ?></h3>
      <p class="small" style="margin:0;">Triés du plus débiteur au plus créditeur.</p>
    </div>

    <?php if ($isOwner): ?>
      <form method="post" style="margin:0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;"
        onsubmit="return confirm(
          'Supprimer ce participant du groupe ?\\n\\n' +
          '• Ses dettes (ses parts) seront supprimées.\\n' +
          '• Les parts des autres ne seront pas modifiées.\\n' +
          '• Le total des dépenses du groupe ne changera pas.\\n\\n' +
          'Action irréversible.'
        );"
      >
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="remove_member">

        <select name="user_id" required>
          <option value="" selected disabled>Participant…</option>
          <?php foreach ($members as $m): ?>
            <?php if ((int)$m['id'] === (int)$group['owner_id']) continue; ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['username']) ?></option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Supprimer</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$membersWithBalance): ?>
    <p class="small">Aucun membre.</p>
  <?php else: ?>
    <div class="member-list" style="margin-top:10px;">
      <?php foreach ($membersWithBalance as $m): ?>
        <?php
          $net = (float)$m['net'];
          $cls = 'zero';
          if ($net > 0.009) $cls = 'pos';
          if ($net < -0.009) $cls = 'neg';
          $sign = $net > 0 ? '+' : '';
          $isMe = ((int)$m['id'] === $uid);
          $isTargetOwner = ((int)$m['id'] === (int)$group['owner_id']);
        ?>
        <div class="member-row">
          <div class="member-name">
            <?= e($m['username']) ?>
            <?= $isMe ? ' <span class="small" style="opacity:.8;">(toi)</span>' : '' ?>
            <?= $isTargetOwner ? ' <span class="small" style="opacity:.8;">(owner)</span>' : '' ?>
          </div>
          <div class="member-balance <?= e($cls) ?>">
            <?= e($sign . number_format($net, 2, '.', '')) ?> €
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="small" style="margin-top:10px; margin-bottom:0;">
      Solde = (payé − dû) + historique purgé
    </p>
  <?php endif; ?>
</div>

<!-- Dépenses -->
<div class="card">
  <h3 style="margin-bottom:10px;"><?= e(t('expenses')) ?></h3>

  <?php if (!$expensesByDate): ?>
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
