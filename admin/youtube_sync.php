<?php
// admin/youtube_sync.php — Excellent Simplified Academy
// ✅ Pre-filled. Upload to your admin/ folder. No editing needed.
// ─────────────────────────────────────────────────────────────────────────

define('YT_API_KEY',     'AIzaSyBfnGXY8WU3ukablTQhLCL7DmNwn29GKA4');
define('YT_HANDLE',      'ExcellentSimplifiedacademy');
define('YT_MAX_RESULTS', 20);
define('YT_DEFAULT_SUBJECT', 0); // 0 = uncategorised. Change to a subject id if you want.

// ─────────────────────────────────────────────────────────────────────────

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');
error_reporting(0);
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// ── Admin guard ──────────────────────────────────────────────────────────
$adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$adminId) {
    echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit;
}
$_chk = $conn->prepare("SELECT is_admin FROM users WHERE id=? LIMIT 1");
$_chk->bind_param('i', $adminId); $_chk->execute();
$_row = $_chk->get_result()->fetch_assoc(); $_chk->close();
if (!(int)($_row['is_admin'] ?? 0)) {
    echo json_encode(['success'=>false,'error'=>'Access denied']); exit;
}

// ── Ensure yt_config cache table exists ──────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS yt_config (
  `k` VARCHAR(100) PRIMARY KEY,
  `v` TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Step 1: Resolve @handle → Channel ID (cached after first run) ────────
$channelId = null;
$cr = $conn->prepare("SELECT `v` FROM yt_config WHERE `k`='channel_id' LIMIT 1");
if ($cr) { $cr->execute(); $cr_row = $cr->get_result()->fetch_assoc(); $cr->close(); $channelId = $cr_row['v'] ?? null; }

if (!$channelId) {
    $handleData = yt_fetch(
        'https://www.googleapis.com/youtube/v3/channels?part=id&forHandle='
        . urlencode(YT_HANDLE) . '&key=' . urlencode(YT_API_KEY)
    );
    if (!$handleData) {
        echo json_encode(['success'=>false,'error'=>'Cannot reach YouTube API. Check server internet or API key.']); exit;
    }
    if (isset($handleData['error'])) {
        echo json_encode(['success'=>false,'error'=>'YouTube API: ' . ($handleData['error']['message'] ?? 'unknown')]); exit;
    }
    $channelId = $handleData['items'][0]['id'] ?? null;
    if (!$channelId) {
        echo json_encode(['success'=>false,'error'=>'Could not resolve @' . YT_HANDLE . '. Check your handle.']); exit;
    }
    // Cache it permanently
    $ci = $conn->prepare("INSERT INTO yt_config (`k`,`v`) VALUES ('channel_id',?) ON DUPLICATE KEY UPDATE `v`=VALUES(`v`)");
    $ci->bind_param('s', $channelId); $ci->execute(); $ci->close();
}

// ── Step 2: Get uploads playlist ID ──────────────────────────────────────
$chanData = yt_fetch(
    'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id='
    . urlencode($channelId) . '&key=' . urlencode(YT_API_KEY)
);
if (!$chanData || isset($chanData['error'])) {
    echo json_encode(['success'=>false,'error'=>'Could not fetch channel details.']); exit;
}
$uploadsId = $chanData['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
if (!$uploadsId) {
    echo json_encode(['success'=>false,'error'=>'Could not find uploads playlist.']); exit;
}

// ── Step 3: Fetch latest videos ───────────────────────────────────────────
$plData = yt_fetch(
    'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId='
    . urlencode($uploadsId) . '&maxResults=' . YT_MAX_RESULTS . '&key=' . urlencode(YT_API_KEY)
);
if (!$plData || isset($plData['error'])) {
    echo json_encode(['success'=>false,'error'=>'Could not fetch videos from channel.']); exit;
}
$items = $plData['items'] ?? [];
if (empty($items)) {
    echo json_encode(['success'=>true,'added'=>0,'skipped'=>0,'checked'=>0,'new_titles'=>[],'message'=>'No videos found on your channel yet.']); exit;
}

// ── Step 4: Insert new videos, skip duplicates ───────────────────────────
$added = 0; $skipped = 0; $newTitles = [];

foreach ($items as $item) {
    $sn      = $item['snippet'] ?? [];
    $videoId = $sn['resourceId']['videoId'] ?? null;
    if (!$videoId) continue;

    $title = trim($sn['title'] ?? '');
    if (in_array($title, ['Deleted video','Private video','']) ) continue;

    $desc        = trim($sn['description'] ?? '');
    $thumb       = $sn['thumbnails']['high']['url'] ?? $sn['thumbnails']['medium']['url'] ?? $sn['thumbnails']['default']['url'] ?? '';
    $publishedAt = $sn['publishedAt'] ?? null;
    $ytUrl       = 'https://www.youtube.com/watch?v=' . $videoId;
    $embedUrl    = 'https://www.youtube.com/embed/' . $videoId;

    // ── Duplicate check ──────────────────────────────────────────────────
    $exists    = false;
    $hasYtId   = colExists($conn, 'youtube_id');

    if ($hasYtId) {
        $s = $conn->prepare("SELECT id FROM videos WHERE youtube_id=? LIMIT 1");
        $s->bind_param('s',$videoId); $s->execute();
        $exists = (bool)$s->get_result()->fetch_assoc(); $s->close();
    }
    if (!$exists) {
        foreach (['youtube_link','youtube_url','video_url'] as $col) {
            if (colExists($conn, $col)) {
                $s = $conn->prepare("SELECT id FROM videos WHERE `$col` LIKE ? LIMIT 1");
                $like = '%'.$videoId.'%'; $s->bind_param('s',$like); $s->execute();
                if ($s->get_result()->fetch_assoc()) $exists = true;
                $s->close(); if ($exists) break;
            }
        }
    }
    if ($exists) { $skipped++; continue; }

    // ── Build INSERT ─────────────────────────────────────────────────────
    $cols=[]; $types=''; $vals=[];

    $cols[]='title';         $types.='s'; $vals[]=$title;

    if (colExists($conn,'youtube_link'))  { $cols[]='youtube_link'; $types.='s'; $vals[]=$ytUrl; }
    if (colExists($conn,'youtube_url'))   { $cols[]='youtube_url';  $types.='s'; $vals[]=$ytUrl; }
    if ($hasYtId)                         { $cols[]='youtube_id';   $types.='s'; $vals[]=$videoId; }
    if (colExists($conn,'embed_url'))     { $cols[]='embed_url';    $types.='s'; $vals[]=$embedUrl; }
    if (colExists($conn,'description') && $desc) { $cols[]='description'; $types.='s'; $vals[]=$desc; }
    if (colExists($conn,'thumbnail') && $thumb)  { $cols[]='thumbnail';   $types.='s'; $vals[]=$thumb; }
    if (colExists($conn,'subject_id'))    { $cols[]='subject_id';  $types.='i'; $vals[]=(int)YT_DEFAULT_SUBJECT; }
    if (colExists($conn,'created_at'))    { $cols[]='created_at';  $types.='s'; $vals[]=($publishedAt ? date('Y-m-d H:i:s',strtotime($publishedAt)) : date('Y-m-d H:i:s')); }
    if (colExists($conn,'source'))        { $cols[]='source';      $types.='s'; $vals[]='youtube_auto'; }

    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $stmt = $conn->prepare("INSERT INTO videos (" . implode(',',$cols) . ") VALUES ($ph)");
    $stmt->bind_param($types, ...$vals);
    if ($stmt->execute()) { $added++; $newTitles[]=$title; }
    $stmt->close();
}

echo json_encode([
    'success'    => true,
    'added'      => $added,
    'skipped'    => $skipped,
    'checked'    => count($items),
    'new_titles' => $newTitles,
    'message'    => $added > 0
        ? "✓ Added $added new video" . ($added>1?'s':'') . "!"
        : "Already up to date — " . count($items) . " videos checked, none are new."
]);

// ═════════════════════════════════════════════════════════════════════════
function yt_fetch($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_USERAGENT=>'ExcellentSimplified/1.0',CURLOPT_FOLLOWLOCATION=>true]);
        $body = curl_exec($ch); curl_close($ch);
        if ($body) return json_decode($body,true);
    }
    $ctx  = stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'ExcellentSimplified/1.0']]);
    $body = @file_get_contents($url, false, $ctx);
    return $body ? json_decode($body,true) : null;
}

function colExists($conn, $col) {
    static $cache = [];
    if (isset($cache[$col])) return $cache[$col];
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return $cache[$col] = false;
    $s = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='videos' AND COLUMN_NAME=?");
    $s->bind_param('ss',$db,$col); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $cache[$col] = (int)($r['c']??0) > 0;
}
