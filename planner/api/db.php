<?php
// ══════════════════════════════════════════════════════════════════════════
//  db.php — Shared database helpers (not called directly by the browser)
// ══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';

// Returns a PDO database connection (cached across calls in the same request)
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database connection failed. Please contact IT.'], 500);
    }
    return $pdo;
}

// Reads the Bearer token from the Authorization header
function getBearerToken(): ?string {
    $headers = function_exists('apache_request_headers')
        ? apache_request_headers()
        : [];

    // Also check $_SERVER for servers that don't expose apache_request_headers
    if (empty($headers['Authorization']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)$/i', trim($headers['Authorization']), $m)) {
            return $m[1];
        }
    }
    return null;
}

// Validates a token against the sessions table.
// Returns the account row on success, or null if invalid/expired.
function validateToken(string $token): ?array {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT s.account_id, a.name, a.grade, a.homeroom
         FROM   sessions s
         JOIN   accounts a ON a.id = s.account_id
         WHERE  s.token = ? AND s.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Sends a JSON response and stops execution
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sets CORS headers so the HTML file can talk to the API regardless of origin.
// In production you may want to restrict the allowed origin to your school's domain.
function corsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
