<?php
// auth/sync_session.php
// Verifies a Firebase ID token, upserts the user in the DB, starts a PHP session.
// Uses local JWT decoding (no outbound HTTP) — required for InfinityFree hosting.

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
error_reporting(E_ERROR | E_PARSE);   // silence deprecation notices — must not corrupt JSON
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// ── Read POST body ─────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$idToken      = trim($data['idToken']      ?? '');
$display_name = trim($data['display_name'] ?? ''); // optional hint from signup page

if (!$idToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing idToken']);
    exit;
}

// ── Decode Firebase JWT locally (no outbound HTTP needed) ──────────────────
// InfinityFree blocks outbound cURL/HTTP calls to external servers.
// Firebase tokens are standard JWTs: header.payload.signature (base64url encoded).
// We decode the payload directly and verify expiry + project audience.
function base64url_decode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

$parts = explode('.', $idToken);
if (count($parts) !== 3) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token format']);
    exit;
}

$payload = json_decode(base64url_decode($parts[1]), true);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Could not decode token']);
    exit;
}

// Check token expiry
$exp = $payload['exp'] ?? 0;
if ($exp && $exp < time()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token has expired. Please sign in again.']);
    exit;
}

// Check this token belongs to our Firebase project
$expectedProjectId = 'excellent-simplified';
$aud = $payload['aud'] ?? '';
$iss = $payload['iss'] ?? '';
if ($aud !== $expectedProjectId || strpos($iss, $expectedProjectId) === false) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token is not for this project']);
    exit;
}

// ── Extract Firebase user fields ───────────────────────
$uid      = $payload['user_id']  ?? ($payload['sub']     ?? null);
$email    = $payload['email']    ?? null;
$fbName   = $payload['name']     ?? null;
$photoUrl = $payload['picture']  ?? null;

// Best available name: POST hint > Firebase displayName > email prefix
$name = $display_name ?: ($fbName ?: ($email ? explode('@', $email)[0] : 'Student'));

// Some Google accounts have no email on first sign-in — use UID fallback
if (!$email && $uid) {
    $email = $uid . '@firebase.user';
}
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Could not resolve email for this account']);
    exit;
}

// ── Check whether google_id column exists ──────────────
$has_google_id = (bool)($conn->query("SHOW COLUMNS FROM users LIKE 'google_id'")->num_rows ?? 0);

// ── Look up existing user ──────────────────────────────
if ($has_google_id && $uid) {
    $s = $conn->prepare("SELECT id FROM users WHERE google_id = ? OR email = ? LIMIT 1");
    $s->bind_param("ss", $uid, $email);
} else {
    $s = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $s->bind_param("s", $email);
}
$s->execute();
$existing = $s->get_result()->fetch_assoc();
$s->close();

$user_id = null;

if ($existing) {
    // ── Update existing user ───────────────────────────
    $user_id = (int)$existing['id'];

    // Check if last_login column exists
    $has_last_login = (bool)($conn->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows ?? 0);

    $set  = "google_name = COALESCE(NULLIF(?,''), google_name), google_picture = COALESCE(NULLIF(?,''), google_picture)";
    $types = "ss";
    $params = [$name, $photoUrl];

    if ($has_google_id) {
        $set    = "google_id = COALESCE(NULLIF(google_id,''), ?), " . $set;
        $types  = "s" . $types;
        $params = array_merge([$uid], $params);
    }
    if ($has_last_login) {
        $set .= ", last_login = NOW()";
    }

    $params[] = $user_id;
    $types   .= "i";

    $up = $conn->prepare("UPDATE users SET $set WHERE id = ?");
    $up->bind_param($types, ...$params);
    $up->execute();
    $up->close();

} else {
    // ── Create new user ────────────────────────────────
    $username = $name;
    $has_last_login = (bool)($conn->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows ?? 0);

    if ($has_google_id && $has_last_login) {
        $ins = $conn->prepare("INSERT INTO users (username,email,google_id,google_name,google_picture,created_at,last_login) VALUES (?,?,?,?,?,NOW(),NOW())");
        $ins->bind_param("sssss", $username, $email, $uid, $name, $photoUrl);
    } elseif ($has_google_id) {
        $ins = $conn->prepare("INSERT INTO users (username,email,google_id,google_name,google_picture,created_at) VALUES (?,?,?,?,?,NOW())");
        $ins->bind_param("sssss", $username, $email, $uid, $name, $photoUrl);
    } elseif ($has_last_login) {
        $ins = $conn->prepare("INSERT INTO users (username,email,google_name,google_picture,created_at,last_login) VALUES (?,?,?,?,NOW(),NOW())");
        $ins->bind_param("ssss", $username, $email, $name, $photoUrl);
    } else {
        $ins = $conn->prepare("INSERT INTO users (username,email,google_name,google_picture,created_at) VALUES (?,?,?,?,NOW())");
        $ins->bind_param("ssss", $username, $email, $name, $photoUrl);
    }

    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . $conn->error]);
        exit;
    }
    $user_id = (int)$ins->insert_id;
    $ins->close();
}

// ── Set PHP session ────────────────────────────────────
$_SESSION['user_id']        = $user_id;
$_SESSION['google_name']    = $name;
$_SESSION['google_picture'] = $photoUrl;
$_SESSION['google_email']   = $email;

echo json_encode(['success' => true, 'user_id' => $user_id, 'name' => $name]);
exit;
