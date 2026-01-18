<?php

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}
