<?php
// ============================================================
//  debug.php — Excellent Academy Diagnostic Page
//  Upload to your root folder, visit it in browser, then
//  DELETE IT after you're done (it exposes server info)
// ============================================================

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

$host     = "sql201.infinityfree.com";
$user     = "if0_41390057";
$password = "83pbNoXy2dceA";
$database = "if0_41390057_academy";
$port     = 3306;

function ok($msg)   { return "<span style='color:#22c55e'>✅ $msg</span>"; }
function fail($msg) { return "<span style='color:#ef4444'>❌ $msg</span>"; }
function warn($msg) { return "<span style='color:#f59e0b'>⚠️ $msg</span>"; }
function row($label, $value) {
    echo "<tr><td style='padding:8px 14px;font-weight:600;color:#94a3b8;white-space:nowrap'>$label</td>"
       . "<td style='padding:8px 14px;word-break:break-all'>$value</td></tr>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Debug — Excellent Academy</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
  h1 { font-size: 20px; margin-bottom: 20px; color: #a78bfa; }
  h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .1em; color: #64748b; margin: 24px 0 8px; }
  table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
  tr:not(:last-child) { border-bottom: 1px solid #334155; }
  td { font-size: 14px; }
  .badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:700; }
  .warn-box { background:#451a03; border:1px solid #f59e0b; border-radius:8px; padding:12px 16px; font-size:13px; color:#fcd34d; margin-top:20px; }
</style>
</head>
<body>
<h1>🔍 Excellent Academy — Debug Page</h1>

<!-- ─── 1. PHP SESSION ─── -->
<h2>1. PHP Session</h2>
<table>
<?php
$sid = session_id();
row('Session ID',     $sid ? ok(htmlspecialchars($sid)) : fail('No session started'));
row('user_id',        isset($_SESSION['user_id'])      ? ok((int)$_SESSION['user_id'])                        : fail('Not set — user is NOT logged in'));
row('google_name',    isset($_SESSION['google_name'])  ? ok(htmlspecialchars($_SESSION['google_name']))        : warn('Not set'));
row('google_email',   isset($_SESSION['google_email']) ? ok(htmlspecialchars($_SESSION['google_email']))       : warn('Not set'));
row('google_picture', isset($_SESSION['google_picture']) ? ok('<img src="'.htmlspecialchars($_SESSION['google_picture']).'" style="height:32px;border-radius:50%">') : warn('Not set'));
row('All session data', '<pre style="font-size:12px;color:#94a3b8">'.htmlspecialchars(print_r($_SESSION, true)).'</pre>');
?>
</table>

<!-- ─── 2. COOKIES ─── -->
<h2>2. Cookies Sent by Browser</h2>
<table>
<?php
if (empty($_COOKIE)) {
    row('Cookies', fail('No cookies received — session cookie is not being sent!'));
} else {
    foreach ($_COOKIE as $k => $v) {
        row(htmlspecialchars($k), htmlspecialchars(substr($v, 0, 80)));
    }
}
?>
</table>

<!-- ─── 3. DATABASE CONNECTION ─── -->
<h2>3. Database Connection</h2>
<table>
<?php
$conn = @new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    row('Connection', fail('FAILED: ' . htmlspecialchars($conn->connect_error)));
    row('Host',     htmlspecialchars($host));
    row('DB User',  htmlspecialchars($user));
    row('Database', htmlspecialchars($database));
} else {
    row('Connection', ok('Connected to ' . htmlspecialchars($database) . ' on ' . htmlspecialchars($host)));
    row('Server version', htmlspecialchars($conn->server_info));
?>
</table>

<!-- ─── 4. TABLES ─── -->
<h2>4. Required Tables</h2>
<table>
<?php
    $required = ['users','scores','answers','videos','video_progress','bookmarks','subjects','questions'];
    foreach ($required as $tbl) {
        $res = $conn->query("SHOW TABLES LIKE '$tbl'");
        if ($res && $res->num_rows > 0) {
            row($tbl, ok('Exists'));
        } else {
            row($tbl, fail('MISSING — run the SQL import!'));
        }
    }
?>
</table>

<!-- ─── 5. USERS TABLE COLUMNS ─── -->
<h2>5. Users Table Columns</h2>
<table>
<?php
    $needed = ['id','username','email','google_id','google_name','google_picture','google_email','points','is_admin','last_login','created_at'];
    $res = $conn->query("SHOW COLUMNS FROM users");
    $existing = [];
    if ($res) { while ($r = $res->fetch_assoc()) $existing[] = $r['Field']; }

    if (empty($existing)) {
        row('users table', fail('Cannot read columns — table may not exist'));
    } else {
        foreach ($needed as $col) {
            if (in_array($col, $existing)) {
                row($col, ok('Present'));
            } else {
                row($col, fail('MISSING — re-import the fixed SQL'));
            }
        }
    }
?>
</table>

<!-- ─── 6. USERS IN DB ─── -->
<h2>6. Users in Database</h2>
<table>
<?php
    $res = $conn->query("SELECT id, username, email, google_id, google_name, is_admin, created_at FROM users ORDER BY id ASC LIMIT 10");
    if ($res && $res->num_rows > 0) {
        row('Count', ok($res->num_rows . ' user(s) found'));
        echo "</table><table>";
        row('<b>#</b>', '<b>username / email / google_id / admin</b>');
        $res = $conn->query("SELECT id, username, email, google_id, google_name, is_admin, created_at FROM users ORDER BY id ASC LIMIT 10");
        while ($u = $res->fetch_assoc()) {
            $admin = $u['is_admin'] ? ' <span class="badge" style="background:#7c3aed">ADMIN</span>' : '';
            row('ID ' . $u['id'],
                htmlspecialchars($u['username'] ?? '—') . ' · ' .
                htmlspecialchars($u['email'] ?? '—') . ' · ' .
                '<small style="color:#64748b">' . htmlspecialchars($u['google_id'] ?? 'no google_id') . '</small>' .
                $admin
            );
        }
    } else {
        row('Users', warn('No users found in database'));
    }
?>
</table>

<!-- ─── 7. SESSION vs DB MATCH ─── -->
<h2>7. Session ↔ Database Match</h2>
<table>
<?php
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        $uid = (int)$_SESSION['user_id'];
        $res = $conn->query("SELECT id, username, email, google_name FROM users WHERE id = $uid LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $u = $res->fetch_assoc();
            row('Match', ok("Session user_id=$uid matches DB user: " . htmlspecialchars($u['username'] ?? $u['email'])));
        } else {
            row('Match', fail("Session has user_id=$uid but NO matching user in DB! Session is stale."));
        }
    } else {
        row('Match', warn('No session user_id to check'));
    }
?>
</table>

<?php } // end if connected ?>

<!-- ─── 8. SERVER INFO ─── -->
<h2>8. Server Info</h2>
<table>
<?php
row('PHP version',     phpversion());
row('Server software', htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));
row('Document root',   htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'unknown'));
row('Request URI',     htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown'));
row('HTTPS',           (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? ok('Yes') : warn('No (HTTP only — cookies may have issues)'));
row('session.save_path', htmlspecialchars(ini_get('session.save_path') ?: 'default'));
?>
</table>

<div class="warn-box">
  ⚠️ <strong>IMPORTANT:</strong> Delete this file from your server once you're done debugging!<br>
  It exposes your database credentials and server configuration.
</div>

</body>
</html>
