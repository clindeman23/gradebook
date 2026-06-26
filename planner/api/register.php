<?php
// ══════════════════════════════════════════════════════════════════════════
//  register.php — Create a new student account
//  POST { name, grade, homeroom, password }
//  Returns { token, profile } on success, or { error } on failure
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$name     = trim($body['name']     ?? '');
$grade    = trim($body['grade']    ?? '');
$homeroom = trim($body['homeroom'] ?? '');
$password = $body['password']      ?? '';

// ── Validate ───────────────────────────────────────────────────────────────
if (!$name)              { jsonResponse(['error' => 'Name is required.'], 400); }
if (strlen($name) > 100) { jsonResponse(['error' => 'Name is too long (max 100 chars).'], 400); }
if (!$password)          { jsonResponse(['error' => 'Password is required.'], 400); }
if (strlen($password) < 4) { jsonResponse(['error' => 'Password must be at least 4 characters.'], 400); }

$db = getDB();

// ── Check for duplicate name ───────────────────────────────────────────────
$stmt = $db->prepare('SELECT id FROM accounts WHERE name = ?');
$stmt->execute([$name]);
if ($stmt->fetch()) {
    jsonResponse([
        'error' => 'An account already exists for "' . $name . '". Try signing in instead, or use a slightly different name.'
    ], 409);
}

// ── Create the account ─────────────────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare(
    'INSERT INTO accounts (name, grade, homeroom, password_hash) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $grade, $homeroom, $hash]);
$accountId = (int) $db->lastInsertId();

// Create an empty planner data record for this account
$stmt = $db->prepare('INSERT INTO planner_data (account_id, data_json) VALUES (?, ?)');
$stmt->execute([$accountId, '{}']);

// ── Issue a session token ─────────────────────────────────────────────────
$token   = bin2hex(random_bytes(32));              // 64-char hex string
$expires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRES_HOURS * 3600);
$stmt    = $db->prepare('INSERT INTO sessions (account_id, token, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$accountId, $token, $expires]);

jsonResponse([
    'token'   => $token,
    'profile' => [
        'name'     => $name,
        'grade'    => $grade,
        'homeroom' => $homeroom,
    ]
]);
