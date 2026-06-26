<?php
// ══════════════════════════════════════════════════════════════════════════
//  data.php — Load and save a student's planner data
//
//  GET  (Authorization: Bearer <token>) → { profile, data }
//  POST (Authorization: Bearer <token>, body = JSON state) → { ok: true }
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

// ── Authenticate ───────────────────────────────────────────────────────────
$token = getBearerToken();
$user  = $token ? validateToken($token) : null;

if (!$user) {
    jsonResponse(['error' => 'Unauthorized. Please sign in again.'], 401);
}

$db = getDB();

// ── GET: Load planner data ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT data_json FROM planner_data WHERE account_id = ?');
    $stmt->execute([$user['account_id']]);
    $row  = $stmt->fetch();
    $data = $row ? json_decode($row['data_json'], true) : [];

    jsonResponse([
        'profile' => [
            'name'     => $user['name'],
            'grade'    => $user['grade'],
            'homeroom' => $user['homeroom'],
        ],
        'data' => $data ?? [],
    ]);
}

// ── POST: Save planner data ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');

    // Make sure it's valid JSON before we store it
    $decoded = json_decode($raw);
    if ($decoded === null) {
        jsonResponse(['error' => 'Invalid JSON in request body.'], 400);
    }

    // Upsert: insert if first save, update on every subsequent save
    $stmt = $db->prepare(
        'INSERT INTO planner_data (account_id, data_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), updated_at = NOW()'
    );
    $stmt->execute([$user['account_id'], $raw]);

    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
