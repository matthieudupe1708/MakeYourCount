<?php

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header("Location: $path");
    exit;
}

// Very small flash messaging helper using session storage.
function flash_set(string $type, string $message): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flash'][$type][] = $message;
}

function flash_show(): void {
    if (empty($_SESSION['flash']) || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    foreach ($_SESSION['flash'] as $type => $messages) {
        foreach ($messages as $msg) {
            $class = e($type);
            echo '<div class="flash ' . $class . '">' . e($msg) . '</div>';
        }
    }
    unset($_SESSION['flash']);
}

function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}
