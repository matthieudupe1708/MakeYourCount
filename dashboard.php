<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();

$db = require __DIR__ . '/config/database.php';
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$uid = current_user_id();

// Mes groupes
$stmt = $pdo->prepare("
    SELECT g.id, g.name, g.join_code, g.owner_id
    FROM groups_myc g
    JOIN group_members gm ON gm.group_id = g.id
    WHERE gm.user_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$uid]);
$groups = $stmt->fetchAll();

render_header(t('dashboard'));
flash_show();
?>
  <div class="groups-grid">
    <?php foreach ($groups as $g): ?>
      <?php
        // Adapte les champs à tes colonnes réelles :
        $gid   = (int)$g['id'];
        $name  = $g['name'] ?? '';
        $code  = $g['join_code'] ?? '';
        $count = (int)($g['member_count'] ?? 0); // si tu as déjà ce champ
        // Optionnel : si tu affiches un solde user/groupe sur dashboard
        $myNet = $g['my_net'] ?? null;
      ?>
      <div class="group-card">
        <div class="group-head">
          <div style="min-width:0;">
            <p class="group-title"><?= e($name) ?></p>
            <div class="group-meta">
              <?php if ($code): ?>
                Code : <code><?= e($code) ?></code>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($count > 0): ?>
            <span class="badge"><?= $count ?> membre<?= $count > 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>

        <div class="group-actions">
          <a class="btn primary" href="group_view.php?id=<?= $gid ?>">Ouvrir / Open</a>
          </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2><?= e(t('create_group')) ?></h2>
    <p class="small">Créer un groupe et générer un code de partage.</p>
    <a href="group_create.php"><button type="button"><?= e(t('create_group')) ?></button></a>

    <hr style="border:0;border-top:1px solid var(--line); margin:16px 0;">

    <h3>Rejoindre / Join</h3>
    <form method="post" action="group_create.php">
      <label><?= e(t('join_code')) ?></label>
      <input name="join_code" maxlength="8" placeholder="AB12CD34" required>
      <div style="margin-top:12px;">
        <button type="submit" name="action" value="join">Rejoindre / Join</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
