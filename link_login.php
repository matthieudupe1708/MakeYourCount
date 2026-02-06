<?php
// link_login.php
session_start();

require_once __DIR__ . '/config/database.php';

$db = require __DIR__ . '/config/database.php';
$pdo = new PDO(
  "mysql:host={$db['host']};dbname={$db['db']};charset={$db['charset']}",
  $db['user'],
  $db['pass'],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$token = (string)($_GET['token'] ?? '');
$gid   = (int)($_GET['group_id'] ?? 0);

if ($token === '' || $gid <= 0) {
  http_response_code(400);
  exit('Lien invalide.');
}

$tokenHash = hash('sha256', $token);

// Cherche le lien
$stmt = $pdo->prepare("
  SELECT user_id
  FROM group_login_links
  WHERE group_id = ? AND token_hash = ?
  LIMIT 1
");
$stmt->execute([$gid, $tokenHash]);
$row = $stmt->fetch();

if (!$row) {
  http_response_code(403);
  exit('Lien expiré ou invalide.');
}

$uid = (int)$row['user_id'];

// Vérifie que l’utilisateur est toujours membre du groupe
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
$stmt->execute([$gid, $uid]);
if (!$stmt->fetchColumn()) {
  http_response_code(403);
  exit('Accès refusé.');
}

// Connecte (adapte si ton système de session est différent)
$_SESSION['user_id'] = $uid;

// Redirection vers le groupe
header("Location: group_view.php?id={$gid}");
exit;
