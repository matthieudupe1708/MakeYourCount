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

// Récupère le hash actuel
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $current = (string)($_POST['current_password'] ?? '');
  $new     = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  if ($current === '' || $new === '' || $confirm === '') {
    flash_set('error', 'Tous les champs sont obligatoires.');
    redirect('password_change.php');
  }

  if (!password_verify($current, $user['password_hash'])) {
    flash_set('error', 'Mot de passe actuel incorrect.');
    redirect('password_change.php');
  }

  if ($new !== $confirm) {
    flash_set('error', 'Les nouveaux mots de passe ne correspondent pas.');
    redirect('password_change.php');
  }

  // Règles minimales (alignées avec ton register)
  if (
    strlen($new) < 12 ||
    !preg_match('/[0-9]/', $new) ||
    !preg_match('/[^A-Za-z0-9]/', $new)
  ) {
    flash_set('error', 'Le nouveau mot de passe ne respecte pas les règles de sécurité.');
    redirect('password_change.php');
  }

  if (password_verify($new, $user['password_hash'])) {
    flash_set('error', 'Le nouveau mot de passe doit être différent de l’ancien.');
    redirect('password_change.php');
  }

  $hash = password_hash($new, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
  $stmt->execute([$hash, $uid]);

  // Sécurité : on invalide la session
  session_destroy();
  session_start();
  flash_set('success', 'Mot de passe modifié. Merci de vous reconnecter.');
  redirect('login.php');
}

render_header('Changer le mot de passe');
flash_show();
?>

<div class="card">
  <h2>Changer mon mot de passe</h2>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>Mot de passe actuel</label>
    <div class="fr-input-wrap fr-input-wrap--icon">
      <input type="password" name="current_password" required class="fr-input">
    </div>

    <label>Nouveau mot de passe</label>
    <div class="fr-password">
      <div class="fr-input-wrap fr-input-wrap--icon">
        <input
          class="fr-password__input fr-input"
          autocapitalize="off"
          autocorrect="off"
          autocomplete="new-password"
          id="password-validation-input"
          name="new_password"
          type="password"
          required
        >

        <button
          type="button"
          class="pw-toggle"
          id="password-toggle-btn"
          aria-label="Afficher le mot de passe"
          aria-pressed="false"
        >
          <!-- oeil -->
          <svg class="pw-toggle__icon" viewBox="0 0 24 24">
            <path d="M12 5C6.5 5 2.1 8.6 1 12c1.1 3.4 5.5 7 11 7s9.9-3.6 11-7c-1.1-3.4-5.5-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
          </svg>
          <!-- oeil barré -->
          <svg class="pw-toggle__icon pw-toggle__icon--off" viewBox="0 0 24 24">
            <path d="M3 4.3 4.3 3 21 19.7 19.7 21z"/>
          </svg>
        </button>
      </div>

      <div class="fr-messages-group" id="password-validation-input-messages">
        <p class="fr-message">Votre mot de passe doit contenir :</p>
        <p class="fr-message fr-message--error" data-fr-valid data-fr-error>12 caractères minimum</p>
        <p class="fr-message fr-message--error" data-fr-valid data-fr-error>1 caractère spécial minimum</p>
        <p class="fr-message fr-message--error" data-fr-valid data-fr-error>1 chiffre minimum</p>
      </div>
    </div>

    <label>Confirmer le nouveau mot de passe</label>
    <input type="password" name="confirm_password" required>

    <div style="margin-top:12px;">
      <button class="btn primary" type="submit">Mettre à jour</button>
      <a class="btn" href="dashboard.php">Annuler</a>
    </div>
  </form>
</div>

<script>
(function () {
  const input = document.getElementById("password-validation-input");
  const btn = document.getElementById("password-toggle-btn");
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
