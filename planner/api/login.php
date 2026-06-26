<?php
// ══════════════════════════════════════════════════════════════════════════
//  login.php — Authenticate a student and return a session token
//  POST { name, password }
//  Returns { token, profile } on success, or { error } on failure
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$name     = trim($body['name']     ?? '');
$password = $body['password']      ?? '';

if (!$name || !$password) {
    jsonResponse(['error' => 'Name and password are required.'], 400);
}

$db = getDB();

// ── Look up the account ────────────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM accounts WHERE name = ?');
$stmt->execute([$name]);
$account = $stmt->fetch();

// Use a constant-time comparison to prevent timing attacks
if (!$account || !password_verify($password, $account['password_hash'])) {
    // Deliberate: same error message whether name or password is wrong
    jsonResponse(['error' => 'Incorrect name or password. Please try again.'], 401);
}

// ── Issue a fresh session token ────────────────────────────────────────────
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRES_HOURS * 3600);
$stmt    = $db->prepare('INSERT INTO sessions (account_id, token, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$account['id'], $token, $expires]);

// ── Optional: clean up expired sessions for this account ──────────────────
$stmt = $db->prepare('DELETE FROM sessions WHERE account_id = ? AND expires_at < NOW()');
$stmt->execute([$account['id']]);

jsonResponse([
    'token'   => $token,
    'profile' => [
        'name'     => $account['name'],
        'grade'    => $account['grade'],
        'homeroom' => $account['homeroom'],
    ]
]);
