<?php
// index.php
session_start();

require_once __DIR__ . '/includes/layout.php';

render_header('MakeYourCount');
flash_show();
?>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
    <div style="min-width:0;">
      <h2 style="margin:0 0 6px;">MakeYourCount</h2>
      <p class="small" style="margin:0;">
        Mini-tricount auto-hébergé : groupes, dépenses, soldes.
      </p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php if (is_logged_in()): ?>
        <a class="btn primary" href="dashboard.php">Tableau de bord</a>
      <?php else: ?>
        <a class="btn primary" href="login.php">Connexion</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (is_logged_in()): ?>
  <div class="card">
    <h3 style="margin:0 0 10px;">Mon compte</h3>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn primary" href="dashboard.php">Mes groupes</a>
      <a class="btn" href="group_create.php">Créer un groupe</a>
      <a class="btn" href="password_change.php">Changer mon mot de passe</a>
      <a class="btn" href="logout.php">Déconnexion</a>
    </div>

    <p class="small" style="margin-top:10px; margin-bottom:0;">
      Astuce : clique sur une dépense pour voir le détail et l’éditer.
    </p>
  </div>
<?php else: ?>
  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 10px;">Se connecter</h3>
      <p class="small" style="margin:0 0 12px;">
        Accède à tes groupes et ajoute des dépenses.
      </p>
      <a class="btn primary" href="login.php">Connexion</a>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px;">Créer un compte</h3>
      <p class="small" style="margin:0 0 12px;">
        Crée ton compte pour commencer à partager des dépenses.
      </p>
      <a class="btn" href="register.php">Inscription</a>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 style="margin:0 0 10px;">Fonctionnalités</h3>
  <div class="list-soft">
    <div class="list-soft-row">
      <div style="font-weight:700;">Groupes</div>
      <div class="small">Créer / rejoindre avec un code</div>
    </div>
    <div class="list-soft-row">
      <div style="font-weight:700;">Dépenses</div>
      <div class="small">Ajout, détail, édition, participants</div>
    </div>
    <div class="list-soft-row">
      <div style="font-weight:700;">Soldes</div>
      <div class="small">Membres triés du plus débiteur au plus créditeur</div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
