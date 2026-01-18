<?php

function get_lang(): string {
    $config = require __DIR__ . '/../config/app.php';

    if (!empty($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    if (empty($_SESSION['lang'])) {
        $_SESSION['lang'] = $config['default_lang'] ?? 'fr';
    }
    return $_SESSION['lang'];
}

function t(string $key): string {
    $lang = get_lang();

    $dict = [
        'fr' => [
            'home_title' => "MakeYourCount",
            'home_sub' => "Mini-Tricount auto-hébergé (MySQL + PHP)",
            'login' => "Connexion",
            'register' => "Créer un compte",
            'logout' => "Déconnexion",
            'dashboard' => "Tableau de bord",
            'my_groups' => "Mes groupes",
            'create_group' => "Créer un groupe",
            'group_name' => "Nom du groupe",
            'create' => "Créer",
            'join_code' => "Code du groupe",
            'members' => "Membres",
            'expenses' => "Dépenses",
            'add_expense' => "Ajouter une dépense",
            'description' => "Description",
            'amount' => "Montant",
            'date' => "Date",
            'payer' => "Payeur",
            'split_equal' => "Répartition égale (tous les membres)",
            'save' => "Enregistrer",
            'settlements' => "Règlements",
            'who_owes' => "Qui doit combien à qui",
            'username' => "Nom d’utilisateur",
            'password' => "Mot de passe",
            'confirm_password' => "Confirmer le mot de passe",
        ],
        'en' => [
            'home_title' => "MakeYourCount",
            'home_sub' => "Self-hosted mini Tricount (MySQL + PHP)",
            'login' => "Login",
            'register' => "Create account",
            'logout' => "Logout",
            'dashboard' => "Dashboard",
            'my_groups' => "My groups",
            'create_group' => "Create group",
            'group_name' => "Group name",
            'create' => "Create",
            'join_code' => "Group code",
            'members' => "Members",
            'expenses' => "Expenses",
            'add_expense' => "Add expense",
            'description' => "Description",
            'amount' => "Amount",
            'date' => "Date",
            'payer' => "Payer",
            'split_equal' => "Equal split (all members)",
            'save' => "Save",
            'settlements' => "Settlements",
            'who_owes' => "Who owes whom",
            'username' => "Username",
            'password' => "Password",
            'confirm_password' => "Confirm password",
        ],
    ];

    return $dict[$lang][$key] ?? $key;
}
