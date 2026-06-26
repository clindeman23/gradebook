<?php
// ══════════════════════════════════════════════════════════════════════════
//  admin_students.php — All student management actions
//
//  All requests require:  Authorization: Bearer <admin_token>
//
//  GET  ?action=list               → all students + stats
//  POST { action:'reset_password', account_id, new_password }
//  POST { action:'force_signout',  account_id }
//  POST { action:'delete_account', account_id }
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
corsHeaders();

// ── Validate admin token ───────────────────────────────────────────────────
$token = getBearerToken();
if (!$token) { jsonResponse(['error' => 'Unauthorized'], 401); }

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id FROM admin_sessions WHERE token = ? AND expires_at > NOW()'
);
$stmt->execute([$token]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Admin session expired. Please log in again.'], 401);
}

// ── GET: List all students ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // All accounts with session info joined
    $stmt = $db->query(
        'SELECT
             a.id,
             a.name,
             a.grade,
             a.homeroom,
             a.created_at,
             (SELECT MAX(s.created_at)
              FROM sessions s
              WHERE s.account_id = a.id) AS last_login,
             (SELECT COUNT(*)
              FROM sessions s
              WHERE s.account_id = a.id AND s.expires_at > NOW()) AS active_sessions
         FROM accounts a
         ORDER BY a.name ASC'
    );
    $students = $stmt->fetchAll();

    // Stats
    $total   = count($students);
    $today   = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $activeToday = 0;
    $activeWeek  = 0;
    foreach ($students as $s) {
        if ($s['last_login'] && substr($s['last_login'], 0, 10) === $today) $activeToday++;
        if ($s['last_login'] && $s['last_login'] >= $weekAgo)               $activeWeek++;
    }

    // Recent audit log (last 20 entries)
    $logs = $db->query(
        'SELECT action, target_name, detail, performed_at
         FROM admin_audit_log
         ORDER BY performed_at DESC
         LIMIT 20'
    )->fetchAll();

    jsonResponse([
        'students' => $students,
        'stats'    => [
            'total'        => $total,
            'activeToday'  => $activeToday,
            'activeWeek'   => $activeWeek,
        ],
        'audit_log' => $logs,
    ]);
}

// ── POST: Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $accId  = (int) ($body['account_id'] ?? 0);

    if (!$accId) { jsonResponse(['error' => 'account_id is required'], 400); }

    // Look up the student name for audit log
    $stmt = $db->prepare('SELECT name FROM accounts WHERE id = ?');
    $stmt->execute([$accId]);
    $row  = $stmt->fetch();
    if (!$row) { jsonResponse(['error' => 'Student not found.'], 404); }
    $studentName = $row['name'];

    // ── Reset password ─────────────────────────────────────────────────────
    if ($action === 'reset_password') {
        $newPw = $body['new_password'] ?? '';
        if (!$newPw)           { jsonResponse(['error' => 'New password is required.'], 400); }
        if (strlen($newPw) < 4){ jsonResponse(['error' => 'Password must be at least 4 characters.'], 400); }

        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?')
           ->execute([$hash, $accId]);

        // Invalidate all existing sessions so they must re-login with new password
        $db->prepare('DELETE FROM sessions WHERE account_id = ?')
           ->execute([$accId]);

        auditLog($db, 'reset_password', $studentName, 'Password reset by admin');
        jsonResponse(['ok' => true, 'message' => "Password reset for {$studentName}. All their devices have been signed out."]);
    }

    // ── Force sign out ─────────────────────────────────────────────────────
    if ($action === 'force_signout') {
        $db->prepare('DELETE FROM sessions WHERE account_id = ?')
           ->execute([$accId]);

        auditLog($db, 'force_signout', $studentName, 'Signed out of all devices by admin');
        jsonResponse(['ok' => true, 'message' => "{$studentName} has been signed out of all devices."]);
    }

    // ── Delete account ─────────────────────────────────────────────────────
    if ($action === 'delete_account') {
        // ON DELETE CASCADE handles sessions and planner_data automatically
        $db->prepare('DELETE FROM accounts WHERE id = ?')
           ->execute([$accId]);

        auditLog($db, 'delete_account', $studentName, 'Account permanently deleted by admin');
        jsonResponse(['ok' => true, 'message' => "{$studentName}'s account has been permanently deleted."]);
    }

    jsonResponse(['error' => 'Unknown action.'], 400);
}

jsonResponse(['error' => 'Method not allowed'], 405);

// ── Helper: write to audit log ─────────────────────────────────────────────
function auditLog(PDO $db, string $action, string $targetName, string $detail): void {
    $db->prepare(
        'INSERT INTO admin_audit_log (action, target_name, detail) VALUES (?, ?, ?)'
    )->execute([$action, $targetName, $detail]);
}
