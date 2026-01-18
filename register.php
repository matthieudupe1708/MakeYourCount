<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/csrf.php';

$db = require __DIR__ . '/config/database.php';
$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function password_is_strong(string $pw): bool {
    if (strlen($pw) < 12) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) return false; // special
    if (!preg_match('/[0-9]/', $pw)) return false;        // digit
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($username === '' || $pass1 === '' || $pass2 === '') {
        flash_set('error', 'Champs manquants / Missing fields');
        redirect('register.php');
    }
    if ($pass1 !== $pass2) {
        flash_set('error', 'Mots de passe différents / Passwords do not match');
        redirect('register.php');
    }
    if (!password_is_strong($pass1)) {
        flash_set('error', 'Mot de passe trop faible (12 caractères, 1 chiffre, 1 caractère spécial minimum).');
        redirect('register.php');
    }

    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        flash_set('success', 'Compte créé, tu peux te connecter / Account created, you can login');
        redirect('login.php');
    } catch (PDOException $e) {
        flash_set('error', 'Nom déjà utilisé / Username already taken');
        redirect('register.php');
    }
}

render_header(t('register'));
flash_show();
?>
<div class="card">
  <h2><?= e(t('register')) ?></h2>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label><?= e(t('username')) ?></label>
    <input class="fr-input" name="username" autocomplete="username" required>

    <!-- Bloc DSFR password -->
    <div class="fr-password">
      <label class="fr-password__label fr-label" for="password-validation-input">
        <?= e(t('password')) ?>
      </label>
      <div class="fr-input-wrap fr-input-wrap--icon">
        <input
          class="fr-password__input fr-input"
          autocapitalize="off"
          autocorrect="off"
          aria-describedby="password-validation-input-messages"
          aria-required="true"
          name="password"
          autocomplete="new-password"
          id="password-validation-input"
          type="password"
          required
        >

        <button
          type="button"
          class="pw-toggle"
          id="password-toggle-btn"
          aria-label="Afficher le mot de passe"
          aria-pressed="false"
          title="Afficher / Masquer"
        >
          <!-- Icône œil (SVG inline) -->
          <svg class="pw-toggle__icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5C6.5 5 2.1 8.6 1 12c1.1 3.4 5.5 7 11 7s9.9-3.6 11-7c-1.1-3.4-5.5-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
          </svg>

          <!-- Œil barré (caché par défaut) -->
          <svg class="pw-toggle__icon pw-toggle__icon--off" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 4.3 4.3 3 21 19.7 19.7 21l-3-3c-1.4.6-3 .9-4.7.9-5.5 0-9.9-3.6-11-7 0-1 .8-2.2 2.2-3.4L3 4.3zm7.1 7.1a2.5 2.5 0 0 0 3.4 3.4l-3.4-3.4zM12 7c-.6 0-1.2.1-1.7.3l-1.7-1.7C9.6 5.2 10.8 5 12 5c5.5 0 9.9 3.6 11 7-.5 1.5-1.8 3.1-3.7 4.4l-2-2A5 5 0 0 0 12 7zm0 2a3 3 0 0 1 3 3c0 .4-.1.8-.2 1.1l-3.9-3.9c.3-.1.7-.2 1.1-.2z"/>
          </svg>
        </button>
      </div>

      <div class="fr-messages-group" id="password-validation-input-messages" aria-live="polite">
        <p class="fr-message">Votre mot de passe doit contenir :</p>
        <p class="fr-message fr-message--error" data-fr-valid="validé" data-fr-error="en erreur">12 caractères minimum</p>
        <p class="fr-message fr-message--error" data-fr-valid="validé" data-fr-error="en erreur">1 caractère spécial minimum</p>
        <p class="fr-message fr-message--error" data-fr-valid="validé" data-fr-error="en erreur">1 chiffre minimum</p>
      </div>
    </div>

    <label><?= e(t('confirm_password')) ?></label>
    <input class="fr-input" type="password" name="password2" autocomplete="new-password" required>

    <div style="margin-top:12px;">
      <button type="submit"><?= e(t('register')) ?></button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
