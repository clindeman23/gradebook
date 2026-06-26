<?php
// ══════════════════════════════════════════════════════════════════════════
//  logout.php — Invalidate a session token
//  POST (Authorization: Bearer <token>) → { ok: true }
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

$token = getBearerToken();
if ($token) {
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM sessions WHERE token = ?');
    $stmt->execute([$token]);
}

// Always return success — if the token didn't exist, it's already gone
jsonResponse(['ok' => true]);
