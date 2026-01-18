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

function make_code(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i=0; $i<8; $i++) $out .= $chars[random_int(0, strlen($chars)-1)];
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Join depuis dashboard (pas de csrf ici car page simple ; tu peux l’ajouter si tu veux)
    $action = $_POST['action'] ?? 'create';

    if ($action === 'join') {
        $code = strtoupper(trim((string)($_POST['join_code'] ?? '')));
        $stmt = $pdo->prepare("SELECT id FROM groups_myc WHERE join_code = ?");
        $stmt->execute([$code]);
        $g = $stmt->fetch();

        if (!$g) {
            flash_set('error', 'Code invalide / Invalid code');
            redirect('dashboard.php');
        }

        $gid = (int)$g['id'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
        $stmt->execute([$gid, $uid]);
        flash_set('success', 'Groupe rejoint / Group joined');
        redirect("group_view.php?id=$gid");
    }

    // Create depuis cette page
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        flash_set('error', 'Nom manquant / Missing name');
        redirect('group_create.php');
    }

    // code unique (boucle simple)
    $code = make_code();
    for ($i=0; $i<5; $i++) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO groups_myc (name, owner_id, join_code) VALUES (?, ?, ?)");
            $stmt->execute([$name, $uid, $code]);
            $gid = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt->execute([$gid, $uid]);

            $pdo->commit();
            flash_set('success', 'Groupe créé / Group created');
            redirect("group_view.php?id=$gid");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $code = make_code();
        }
    }

    flash_set('error', 'Impossible de créer le groupe (code unique) / Could not create group');
    redirect('group_create.php');
}

render_header(t('create_group'));
flash_show();
?>
<div class="card">
  <h2><?= e(t('create_group')) ?></h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label><?= e(t('group_name')) ?></label>
    <input name="name" required>
    <div style="margin-top:12px;">
      <button type="submit"><?= e(t('create')) ?></button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
