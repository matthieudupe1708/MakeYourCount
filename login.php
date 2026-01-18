<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/csrf.php';

$db = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    flash_set('error', 'Connexion à la base impossible. Réessaie plus tard.');
    render_header(t('login'));
    flash_show();
    echo '<div class="card"><p class="small">Erreur serveur (DB).</p></div>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($username === '' || $pass === '') {
        flash_set('error', 'Champs manquants / Missing fields');
        redirect('login.php');
    }

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'])) {
        flash_set('error', 'Identifiants invalides / Invalid credentials');
        redirect('login.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    flash_set('success', 'Connecté / Logged in');
    redirect('dashboard.php');
}

render_header(t('login'));
flash_show();
?>
<div class="card">
  <h2><?= e(t('login')) ?></h2>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label><?= e(t('username')) ?></label>
    <input class="fr-input" name="username" autocomplete="username" required>

    <label><?= e(t('password')) ?></label>
    <div class="fr-input-wrap fr-input-wrap--icon">
      <input
        class="fr-input"
        id="login-password-input"
        type="password"
        name="password"
        autocomplete="current-password"
        required
      >

      <button
        type="button"
        class="pw-toggle"
        id="login-password-toggle-btn"
        aria-label="Afficher le mot de passe"
        aria-pressed="false"
        title="Afficher / Masquer"
      >
        <!-- Œil -->
        <svg class="pw-toggle__icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 5C6.5 5 2.1 8.6 1 12c1.1 3.4 5.5 7 11 7s9.9-3.6 11-7c-1.1-3.4-5.5-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
        </svg>

        <!-- Œil barré -->
        <svg class="pw-toggle__icon pw-toggle__icon--off" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 4.3 4.3 3 21 19.7 19.7 21l-3-3c-1.4.6-3 .9-4.7.9-5.5 0-9.9-3.6-11-7 0-1 .8-2.2 2.2-3.4L3 4.3zm7.1 7.1a2.5 2.5 0 0 0 3.4 3.4l-3.4-3.4zM12 7c-.6 0-1.2.1-1.7.3l-1.7-1.7C9.6 5.2 10.8 5 12 5c5.5 0 9.9 3.6 11 7-.5 1.5-1.8 3.1-3.7 4.4l-2-2A5 5 0 0 0 12 7zm0 2a3 3 0 0 1 3 3c0 .4-.1.8-.2 1.1l-3.9-3.9c.3-.1.7-.2 1.1-.2z"/>
        </svg>
      </button>
    </div>

    <div style="margin-top:12px;">
      <button type="submit"><?= e(t('login')) ?></button>
    </div>
  </form>
</div>

<script>
(function () {
  const input = document.getElementById("login-password-input");
  const btn = document.getElementById("login-password-toggle-btn");
  if (!input || !btn) return;

  btn.addEventListener("click", () => {
    const isText = input.type === "text";
    input.type = isText ? "password" : "text";
    btn.classList.toggle("is-on", !isText);
    btn.setAttribute("aria-pressed", String(!isText));
    btn.setAttribute("aria-label", !isText ? "Masquer le mot de passe" : "Afficher le mot de passe");
  });
})();
</script>

<?php render_footer(); ?>
