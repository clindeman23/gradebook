<?php
// ══════════════════════════════════════════════════════════════════════════
//  admin_login.php — Authenticate the admin/teacher
//  POST { password }  →  { token } on success, { error } on failure
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$password = $body['password'] ?? '';

if (!$password) {
    jsonResponse(['error' => 'Password is required.'], 400);
}

// Constant-time comparison against the admin password in config.php
if (!hash_equals(ADMIN_PASSWORD, $password)) {
    // Slight delay to slow brute-force attempts
    sleep(1);
    jsonResponse(['error' => 'Incorrect admin password.'], 401);
}

// Issue an admin session token
$db      = getDB();
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + ADMIN_TOKEN_EXPIRES_HOURS * 3600);
$db->prepare('INSERT INTO admin_sessions (token, expires_at) VALUES (?, ?)')
   ->execute([$token, $expires]);

jsonResponse(['token' => $token]);
