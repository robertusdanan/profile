<?php
// ══════════════════════════════════════════════════════════════
//  admin.php — Panel Admin robertusdanan
//  Requires: PHP 8.1+, ext-curl
// ══════════════════════════════════════════════════════════════

session_start();

// ── CONFIG ────────────────────────────────────────────────────
define('SUPABASE_URL',      getenv('supabase_url')     ?: '');
define('SUPABASE_ANON_KEY', getenv('anon_public')      ?: '');
define('SUPABASE_SERVICE',  getenv('service_role')     ?: '');
define('STORAGE_BUCKET',    'media');
define('SESSION_LIFETIME',  8 * 3600); // 8 jam

// ── SUPABASE HTTP CLIENT ──────────────────────────────────────
function sb(string $path, string $method = 'GET', array $body = null, bool $useService = false): array {
    $key = $useService ? SUPABASE_SERVICE : SUPABASE_ANON_KEY;
    $url = SUPABASE_URL . '/rest/v1' . $path;
    $ch  = curl_init($url);
    $headers = [
        'apikey: '               . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true) ?? []];
}

// ── STORAGE UPLOAD ────────────────────────────────────────────
function storageUpload(string $filePath, string $mimeType, string $destPath): ?string {
    $key  = SUPABASE_SERVICE;
    $url  = SUPABASE_URL . '/storage/v1/object/' . STORAGE_BUCKET . '/' . $destPath;
    $ch   = curl_init($url);
    $data = file_get_contents($filePath);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => [
            'apikey: '               . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: '         . $mimeType,
            'x-upsert: true',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return SUPABASE_URL . '/storage/v1/object/public/' . STORAGE_BUCKET . '/' . $destPath;
    }
    return null;
}

// ── AUTH HELPERS ──────────────────────────────────────────────
function isLoggedIn(): bool {
    if (empty($_SESSION['admin_token'])) return false;
    if (!empty($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
        session_destroy();
        return false;
    }
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        // Jika AJAX / fetch request → kembalikan JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sesi habis, silakan login kembali.']);
            exit;
        }
        // Redirect ke halaman login dengan pesan
        session_start();
        $_SESSION['flash_error'] = 'Sesi habis atau tidak valid. Silakan login kembali.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

function attemptLogin(string $username, string $password): bool {
    $res = sb('/admin_users?username=eq.' . urlencode($username) . '&select=id,password_hash', 'GET', null, true);
    if (empty($res['data'][0])) return false;
    $row = $res['data'][0];
    if (!password_verify($password, $row['password_hash'])) return false;
    // Buat session token
    $token = bin2hex(random_bytes(32));
    sb('/admin_sessions', 'POST', [
        'token'    => $token,
        'admin_id' => $row['id'],
    ], true);
    $_SESSION['admin_token'] = $token;
    $_SESSION['admin_user']  = $username;
    $_SESSION['expires_at']  = time() + SESSION_LIFETIME;
    return true;
}

function logout(): void {
    if (!empty($_SESSION['admin_token'])) {
        sb('/admin_sessions?token=eq.' . $_SESSION['admin_token'], 'DELETE', null, true);
    }
    session_destroy();
}

// ── CSRF ──────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function checkCsrf(): void {
    $submitted = $_POST['_csrf'] ?? '';
    $expected  = $_SESSION['csrf'] ?? '';
    if (!$submitted || !$expected || !hash_equals($expected, $submitted)) {
        // Regenerate token agar tidak terjebak permanen
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        http_response_code(403);
        die('Token keamanan tidak valid. Silakan <a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">refresh halaman</a> dan coba lagi.');
    }
}

// ── FLASH MESSAGE ─────────────────────────────────────────────
function setFlash(string $msg, string $type = 'success'): void {
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_type'] = $type;
}

function getFlash(): array {
    $msg  = $_SESSION['flash_msg']  ?? '';
    $type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    return ['msg' => $msg, 'type' => $type];
}

// ══════════════════════════════════════════════════════════════
//  HANDLE ACTIONS
// ══════════════════════════════════════════════════════════════
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$msgType = 'success';

// ── LOGIN ─────────────────────────────────────────────────────
if ($action === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (attemptLogin($u, $p)) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $message = 'Username atau password salah.';
    $msgType = 'error';
}

// ── LOGOUT ───────────────────────────────────────────────────
if ($action === 'logout') {
    logout();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ── SEMUA AKSI SELANJUTNYA WAJIB LOGIN ───────────────────────
// (blok tunggal ini menggantikan pengecekan terpisah di tiap action)
if ($action !== '' && $action !== 'login') {
    requireLogin();
    checkCsrf();
}

// ── UPDATE PROFILE ────────────────────────────────────────────
if ($action === 'update_profile') {
    $data = [
        'name'    => trim($_POST['name']    ?? ''),
        'handle'  => trim($_POST['handle']  ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
    ];

    if (!empty($_FILES['avatar_file']['tmp_name'])) {
        $file    = $_FILES['avatar_file'];
        $mime    = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (in_array($mime, $allowed, true)) {
            $ext  = explode('/', $mime)[1];
            $dest = 'avatars/avatar.' . $ext;
            $url  = storageUpload($file['tmp_name'], $mime, $dest);
            if ($url) {
                $data['avatar_data'] = $url;
                $data['avatar_type'] = $ext;
            } else {
                setFlash('Gagal upload avatar ke storage.', 'error');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    } elseif (!empty($_POST['avatar_base64'])) {
        $b64raw = trim($_POST['avatar_base64']);
        if (preg_match('/^data:image\/(\w+);base64,/', $b64raw, $m)) {
            $data['avatar_type'] = $m[1];
            $b64raw = preg_replace('/^data:image\/\w+;base64,/', '', $b64raw);
        } else {
            $data['avatar_type'] = $_POST['avatar_type_manual'] ?? 'jpeg';
        }
        $data['avatar_data'] = $b64raw;
    }

    $res = sb('/profile?id=eq.1', 'PATCH', $data, true);
    if ($res['code'] >= 200 && $res['code'] < 300) {
        setFlash('Profil berhasil diperbarui.');
    } else {
        setFlash('Gagal memperbarui profil: ' . json_encode($res['data']), 'error');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?sec=profile');
    exit;
}

// ── ADD LINK CARD ─────────────────────────────────────────────
if ($action === 'add_link') {
    $maxRes   = sb('/link_cards?select=sort_order&order=sort_order.desc&limit=1', 'GET', null, true);
    $maxOrder = (int)($maxRes['data'][0]['sort_order'] ?? 0) + 1;

    $iconData = null;
    $iconMime = 'png';
    $iconType = trim($_POST['icon_type'] ?? 'custom');

    if ($iconType === 'custom') {
        if (!empty($_FILES['icon_file']['tmp_name'])) {
            $file     = $_FILES['icon_file'];
            $mime     = mime_content_type($file['tmp_name']);
            $ext      = explode('/', $mime)[1];
            $dest     = 'icons/icon_' . uniqid() . '.' . $ext;
            $url      = storageUpload($file['tmp_name'], $mime, $dest);
            $iconData = $url ?? base64_encode(file_get_contents($file['tmp_name']));
            $iconMime = $ext;
        } elseif (!empty($_POST['icon_base64'])) {
            $b64raw = trim($_POST['icon_base64']);
            if (preg_match('/^data:image\/(\w+);base64,/', $b64raw, $m)) {
                $iconMime = $m[1];
                $b64raw   = preg_replace('/^data:image\/\w+;base64,/', '', $b64raw);
            }
            $iconData = $b64raw;
        }
    }

    $newLink = [
        'sort_order'  => $maxOrder,
        'label'       => trim($_POST['label']       ?? ''),
        'sub'         => trim($_POST['sub']         ?? ''),
        'url'         => trim($_POST['url']         ?? ''),
        'domain'      => trim($_POST['domain']      ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'btn_label'   => trim($_POST['btn_label']   ?? 'Buka Halaman'),
        'icon_type'   => $iconType,
        'icon_data'   => $iconData,
        'icon_mime'   => $iconMime,
        'is_active'   => true,
    ];

    $res = sb('/link_cards', 'POST', $newLink, true);
    if ($res['code'] >= 200 && $res['code'] < 300) {
        setFlash('Link card berhasil ditambahkan.');
    } else {
        setFlash('Gagal menambah link card: ' . json_encode($res['data']), 'error');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?sec=links');
    exit;
}

// ── UPDATE LINK CARD ──────────────────────────────────────────
if ($action === 'update_link') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        setFlash('ID link card tidak valid.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?sec=links');
        exit;
    }

    $iconData = null;
    $iconMime = null;
    $iconType = trim($_POST['icon_type'] ?? 'custom');

    if ($iconType === 'custom') {
        if (!empty($_FILES['icon_file']['tmp_name'])) {
            $file     = $_FILES['icon_file'];
            $mime     = mime_content_type($file['tmp_name']);
            $ext      = explode('/', $mime)[1];
            $dest     = 'icons/icon_' . $id . '_' . uniqid() . '.' . $ext;
            $url      = storageUpload($file['tmp_name'], $mime, $dest);
            $iconData = $url ?? base64_encode(file_get_contents($file['tmp_name']));
            $iconMime = $ext;
        } elseif (!empty($_POST['icon_base64'])) {
            $b64raw = trim($_POST['icon_base64']);
            if (preg_match('/^data:image\/(\w+);base64,/', $b64raw, $m)) {
                $iconMime = $m[1];
                $b64raw   = preg_replace('/^data:image\/\w+;base64,/', '', $b64raw);
            }
            $iconData = $b64raw;
            $iconMime = $iconMime ?? ($_POST['icon_type_manual'] ?? 'png');
        }
    }

    $updData = [
        'label'       => trim($_POST['label']       ?? ''),
        'sub'         => trim($_POST['sub']         ?? ''),
        'url'         => trim($_POST['url']         ?? ''),
        'domain'      => trim($_POST['domain']      ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'btn_label'   => trim($_POST['btn_label']   ?? 'Buka Halaman'),
        'icon_type'   => $iconType,
        'is_active'   => isset($_POST['is_active']),
    ];
    if ($iconData !== null) {
        $updData['icon_data'] = $iconData;
        $updData['icon_mime'] = $iconMime;
    }

    $res = sb('/link_cards?id=eq.' . $id, 'PATCH', $updData, true);
    if ($res['code'] >= 200 && $res['code'] < 300) {
        setFlash('Link card berhasil diperbarui.');
    } else {
        setFlash('Gagal memperbarui link card: ' . json_encode($res['data']), 'error');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?sec=links');
    exit;
}

// ── DELETE LINK CARD ──────────────────────────────────────────
if ($action === 'delete_link') {
    $id  = (int)($_POST['id'] ?? 0);
    $res = sb('/link_cards?id=eq.' . $id, 'DELETE', null, true);
    if ($res['code'] >= 200 && $res['code'] < 300) {
        setFlash('Link card berhasil dihapus.');
    } else {
        setFlash('Gagal menghapus link card.', 'error');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?sec=links');
    exit;
}

// ── REORDER ───────────────────────────────────────────────────
if ($action === 'reorder') {
    $orders = json_decode($_POST['orders'] ?? '[]', true);
    if (is_array($orders)) {
        foreach ($orders as $item) {
            sb('/link_cards?id=eq.' . (int)$item['id'], 'PATCH', ['sort_order' => (int)$item['order']], true);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  FETCH DATA (untuk render halaman)
// ══════════════════════════════════════════════════════════════
$profile   = [];
$linkCards = [];
if (isLoggedIn()) {
    $pRes      = sb('/profile?id=eq.1&select=*&limit=1', 'GET', null, true);
    $profile   = $pRes['data'][0] ?? [];
    $lRes      = sb('/link_cards?select=*&order=sort_order.asc', 'GET', null, true);
    $linkCards = $lRes['data'] ?? [];
}

// Baca flash message
$flash   = getFlash();
$message = $flash['msg'];
$msgType = $flash['type'];

// Baca flash dari session error (login redirect)
if (empty($message) && !empty($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    $msgType = 'error';
    unset($_SESSION['flash_error']);
}

// Section aktif (setelah redirect)
$activeSec = $_GET['sec'] ?? 'profile';

$csrf = csrfToken();

// ── ICON RENDER HELPER ────────────────────────────────────────
function renderIconSrc(array $link): string {
    $type = $link['icon_type'] ?? 'custom';
    $data = $link['icon_data'] ?? '';
    $mime = $link['icon_mime'] ?? 'png';
    if ($type === 'custom' && !empty($data)) {
        if (str_starts_with($data, 'http')) return htmlspecialchars($data);
        return 'data:image/' . $mime . ';base64,' . $data;
    }
    return '';
}

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Panel · @robertusdanan</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Sora:wght@300;400;500;600&family=JetBrains+Mono:wght@300;400&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --void:#03040a;--deep:#070b16;--surface:#0c1120;
  --glass:rgba(10,15,28,.88);--edge:rgba(100,150,255,.14);
  --ice:#a0bfff;--frost:#d8e8ff;--mist:#607090;--ghost:#304060;
  --accent:#4f80ff;--accent2:#9b72f8;
  --red:#ff5f5f;--green:#4fdb9e;--amber:#f0b41a;
  --ff-head:'Sora',sans-serif;--ff-body:'Inter',sans-serif;
  --ff-mono:'JetBrains Mono',monospace;
  --ease-expo:cubic-bezier(.16,1,.3,1);
  --ease-spring:cubic-bezier(.34,1.56,.64,1);
  --sidebar:240px;
}
html,body{height:100%;background:var(--void);color:var(--frost);font-family:var(--ff-body)}
body::after{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:999;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.03'/%3E%3C/svg%3E");
  opacity:.4;mix-blend-mode:overlay;
}

/* ── LOGIN ── */
.login-wrap{
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:radial-gradient(ellipse at 50% 0%,rgba(79,128,255,.08) 0%,transparent 70%),var(--void);
}
.login-card{
  width:min(400px,94vw);background:rgba(10,15,28,.92);
  border:1px solid var(--edge);border-radius:8px;padding:48px 36px;text-align:center;
  box-shadow:0 0 0 1px rgba(79,128,255,.05) inset,0 60px 140px rgba(0,0,0,.9);
  position:relative;overflow:hidden;
}
.login-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),var(--accent),transparent);
}
.login-logo{
  width:56px;height:56px;border-radius:50%;
  border:1px solid var(--edge);background:rgba(79,128,255,.06);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 28px;font-size:22px;
}
.login-title{font-family:var(--ff-head);font-size:1.5rem;font-weight:600;color:#fff;margin-bottom:6px}
.login-sub{font-size:.75rem;color:var(--mist);font-family:var(--ff-mono);letter-spacing:.1em;margin-bottom:32px}

/* ── FORM ── */
.field{margin-bottom:18px;text-align:left}
.field label{display:block;font-size:.7rem;font-family:var(--ff-mono);letter-spacing:.15em;text-transform:uppercase;color:var(--mist);margin-bottom:8px}
.field input,.field textarea,.field select{
  width:100%;padding:12px 14px;background:rgba(255,255,255,.03);
  border:1px solid var(--edge);border-radius:4px;
  color:var(--frost);font-family:var(--ff-body);font-size:.88rem;
  outline:none;transition:border-color .25s,box-shadow .25s;resize:vertical;
}
.field textarea{min-height:80px}
.field input:focus,.field textarea:focus,.field select:focus{
  border-color:rgba(79,128,255,.45);box-shadow:0 0 0 3px rgba(79,128,255,.08);
}
.field select option{background:var(--deep)}
.field .hint{font-size:.66rem;color:var(--ghost);margin-top:5px;font-family:var(--ff-mono)}

/* ── BUTTONS ── */
.btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:11px 22px;border-radius:4px;
  font-family:var(--ff-mono);font-size:.68rem;
  letter-spacing:.16em;text-transform:uppercase;
  cursor:pointer;font-weight:300;transition:all .22s ease;
  border:1px solid transparent;text-decoration:none;
}
.btn-primary{background:transparent;border-color:var(--accent);color:var(--accent);position:relative;overflow:hidden;}
.btn-primary::before{content:'';position:absolute;inset:0;background:var(--accent);transform:scaleX(0);transform-origin:left;transition:transform .28s ease;}
.btn-primary:hover::before{transform:scaleX(1)}
.btn-primary:hover{color:var(--void)}
.btn-primary span{position:relative;z-index:1}
.btn-danger{background:transparent;border-color:var(--red);color:var(--red)}
.btn-danger:hover{background:rgba(255,95,95,.1)}
.btn-ghost{background:transparent;border-color:var(--ghost);color:var(--mist)}
.btn-ghost:hover{border-color:var(--mist);color:var(--frost)}
.btn-sm{padding:7px 14px;font-size:.62rem}
.btn-icon{padding:8px;min-width:36px;justify-content:center}
.btn-full{width:100%;justify-content:center}

/* ── TOAST ── */
.toast{
  position:fixed;top:24px;right:24px;z-index:1000;
  padding:14px 20px;border-radius:4px;font-size:.8rem;max-width:340px;
  border:1px solid;display:flex;align-items:center;gap:10px;
  animation:slideIn .35s var(--ease-expo) both;box-shadow:0 8px 32px rgba(0,0,0,.5);
}
.toast.success{background:rgba(79,219,158,.08);border-color:rgba(79,219,158,.35);color:var(--green)}
.toast.error  {background:rgba(255,95,95,.08); border-color:rgba(255,95,95,.35); color:var(--red)}
@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}

/* ── LAYOUT ── */
.admin-wrap{display:flex;min-height:100vh}
.sidebar{
  width:var(--sidebar);flex-shrink:0;
  background:rgba(7,11,22,.96);border-right:1px solid var(--edge);
  display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto;
}
.sidebar-header{padding:28px 20px 20px;border-bottom:1px solid var(--edge)}
.sidebar-logo{font-family:var(--ff-head);font-size:.85rem;font-weight:600;color:#fff;letter-spacing:.04em;margin-bottom:3px;}
.sidebar-sub{font-size:.63rem;font-family:var(--ff-mono);color:var(--mist);letter-spacing:.12em}
.sidebar-nav{flex:1;padding:16px 0}
.nav-item{
  display:flex;align-items:center;gap:11px;
  padding:10px 20px;font-size:.78rem;color:var(--mist);cursor:pointer;
  transition:color .2s,background .2s;border-left:2px solid transparent;
}
.nav-item:hover,.nav-item.active{color:var(--ice);background:rgba(79,128,255,.05);border-left-color:var(--accent);}
.nav-item svg{width:16px;height:16px;flex-shrink:0;opacity:.7}
.nav-item:hover svg,.nav-item.active svg{opacity:1}
.nav-sep{height:1px;background:var(--edge);margin:10px 16px}
.sidebar-footer{padding:20px;border-top:1px solid var(--edge)}
.main{flex:1;overflow-y:auto;padding:32px 36px;max-width:820px}
@media(max-width:768px){.main{padding:20px 16px}.sidebar{width:200px}:root{--sidebar:200px}}
@media(max-width:600px){
  .admin-wrap{flex-direction:column}
  .sidebar{width:100%;height:auto;position:relative}
  .sidebar-nav{display:flex;flex-direction:row;overflow-x:auto;padding:8px 0}
  .nav-item{white-space:nowrap;border-left:none;border-bottom:2px solid transparent}
  .nav-item.active{border-bottom-color:var(--accent)}
}

/* ── SECTIONS ── */
.section{display:none}
.section.active{display:block}
.section-title{font-family:var(--ff-head);font-size:1.2rem;font-weight:600;color:#fff;margin-bottom:6px;}
.section-sub{font-size:.75rem;color:var(--mist);margin-bottom:28px;font-family:var(--ff-mono);letter-spacing:.08em}

/* ── PANEL CARD ── */
.panel-card{
  background:rgba(10,15,28,.78);border:1px solid var(--edge);border-radius:6px;
  padding:28px;margin-bottom:24px;position:relative;overflow:hidden;
}
.panel-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(79,128,255,.4),transparent);opacity:.6;
}
.card-title{
  font-family:var(--ff-head);font-size:.85rem;font-weight:600;
  color:var(--ice);margin-bottom:20px;display:flex;align-items:center;gap:8px;
}
.card-title::before{content:'';width:3px;height:14px;background:var(--accent);border-radius:2px}

/* ── LINK ITEMS ── */
.link-item{
  background:rgba(255,255,255,.02);border:1px solid var(--edge);
  border-radius:4px;padding:18px;margin-bottom:12px;
  display:flex;align-items:center;gap:16px;transition:border-color .25s;cursor:grab;
}
.link-item:hover{border-color:rgba(79,128,255,.25)}
.link-item.dragging{opacity:.5;border-style:dashed}
.link-item-ico{
  width:42px;height:42px;border-radius:50%;border:1px solid var(--edge);
  background:rgba(255,255,255,.03);display:flex;align-items:center;
  justify-content:center;flex-shrink:0;overflow:hidden;
}
.link-item-ico img{width:26px;height:26px;object-fit:contain}
.link-item-body{flex:1;min-width:0}
.link-item-title{font-size:.83rem;font-weight:500;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.link-item-sub  {font-size:.7rem;color:var(--mist);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.link-item-actions{display:flex;gap:8px;flex-shrink:0}
.badge{display:inline-block;padding:2px 8px;border-radius:3px;font-size:.6rem;font-family:var(--ff-mono);letter-spacing:.1em;text-transform:uppercase;}
.badge-active  {background:rgba(79,219,158,.1);color:var(--green);border:1px solid rgba(79,219,158,.25)}
.badge-inactive{background:rgba(255,95,95,.1);color:var(--red);border:1px solid rgba(255,95,95,.2)}
.drag-handle{color:var(--ghost);cursor:grab;flex-shrink:0;font-size:18px;line-height:1}
.drag-handle:hover{color:var(--mist)}

/* ── TABS ── */
.tabs{display:flex;gap:0;margin-bottom:24px;border-bottom:1px solid var(--edge)}
.tab{
  padding:10px 20px;font-size:.72rem;font-family:var(--ff-mono);letter-spacing:.12em;
  text-transform:uppercase;cursor:pointer;color:var(--ghost);
  border-bottom:2px solid transparent;transition:color .2s,border-color .2s;
}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab:hover{color:var(--ice)}
.tab-panel{display:none}.tab-panel.active{display:block}

/* ── AVATAR ── */
.avatar-preview{
  width:80px;height:80px;border-radius:50%;border:1px solid var(--edge);
  background:rgba(255,255,255,.03);display:flex;align-items:center;
  justify-content:center;overflow:hidden;margin-bottom:12px;
}
.avatar-preview img{width:100%;height:100%;object-fit:cover;border-radius:50%}

/* ── FILE DROP ── */
.file-drop{
  border:1px dashed var(--ghost);border-radius:4px;padding:20px;text-align:center;
  cursor:pointer;transition:border-color .25s,background .25s;font-size:.78rem;color:var(--mist);
}
.file-drop:hover{border-color:var(--accent);background:rgba(79,128,255,.03);color:var(--ice)}
.file-drop input{display:none}

/* ── GRID ── */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:500px){.grid2{grid-template-columns:1fr}}

/* ── MODAL ── */
.modal-overlay{
  position:fixed;inset:0;z-index:500;background:rgba(3,4,10,.92);
  backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:center;
  padding:20px;opacity:0;pointer-events:none;transition:opacity .3s;
}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{
  width:min(560px,100%);max-height:90vh;overflow-y:auto;
  background:rgba(10,15,28,.98);border:1px solid var(--edge);border-radius:6px;
  padding:36px;transform:translateY(24px);transition:transform .35s var(--ease-expo);position:relative;
}
.modal-overlay.open .modal-box{transform:none}
.modal-box::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),var(--accent),transparent);
}
.modal-title{font-family:var(--ff-head);font-size:1.1rem;font-weight:600;color:#fff;margin-bottom:24px;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:28px}

/* ── ICON SWITCHER ── */
.icon-type-select{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.icon-type-btn{
  padding:6px 14px;border-radius:20px;font-size:.68rem;font-family:var(--ff-mono);
  letter-spacing:.1em;text-transform:uppercase;cursor:pointer;
  border:1px solid var(--edge);color:var(--mist);background:transparent;transition:all .2s;
}
.icon-type-btn.active{border-color:var(--accent);color:var(--accent);background:rgba(79,128,255,.07)}
.icon-preview{
  width:48px;height:48px;border-radius:50%;border:1px solid var(--edge);
  background:rgba(255,255,255,.03);display:flex;align-items:center;
  justify-content:center;overflow:hidden;margin-bottom:10px;
}
.icon-preview img{width:30px;height:30px;object-fit:contain}
.icon-preview svg{width:24px;height:24px}

/* ── SESSION WARNING BANNER ── */
.session-banner{
  background:rgba(240,180,26,.07);border-bottom:1px solid rgba(240,180,26,.2);
  padding:10px 20px;font-size:.72rem;font-family:var(--ff-mono);color:var(--amber);
  display:flex;align-items:center;gap:10px;
}
</style>
</head>
<body>

<?php if (!isLoggedIn()): ?>
<!-- ══════════════ LOGIN PAGE ══════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">⚡</div>
    <div class="login-title">Admin Panel</div>
    <div class="login-sub">@robertusdanan · Dashboard</div>

    <?php if ($message): ?>
    <div class="toast <?= $msgType ?>" style="position:relative;top:auto;right:auto;margin-bottom:20px;animation:none">
      <?= $msgType === 'success' ? '✓' : '✕' ?> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="action" value="login"/>
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" autocomplete="username" autofocus required placeholder="masukkan username"/>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••"/>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        <span>Masuk ke Dashboard</span>
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════ ADMIN DASHBOARD ══════════════ -->

<?php if ($message): ?>
<div class="toast <?= $msgType ?>" id="toast">
  <?= $msgType === 'success' ? '✓' : '✕' ?> <?= htmlspecialchars($message) ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.opacity='0'},4000)</script>
<?php endif; ?>

<div class="admin-wrap">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">Admin Panel</div>
      <div class="sidebar-sub">@robertusdanan</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-item <?= $activeSec === 'profile' ? 'active' : '' ?>" onclick="showSection('profile',this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
        Profil
      </div>
      <div class="nav-item <?= $activeSec === 'links' ? 'active' : '' ?>" onclick="showSection('links',this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
        </svg>
        Link Cards
      </div>
      <div class="nav-sep"></div>
      <div class="nav-item" onclick="window.open('/','_blank')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
          <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
        Lihat Halaman
      </div>
    </nav>
    <div class="sidebar-footer">
      <form method="POST">
        <input type="hidden" name="action" value="logout"/>
        <button type="submit" class="btn btn-ghost btn-sm btn-full">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          <span>Logout</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- ══ SECTION: PROFILE ══ -->
    <div class="section <?= $activeSec === 'profile' ? 'active' : '' ?>" id="sec-profile">
      <div class="section-title">Profil</div>
      <div class="section-sub">Identitas dan foto profil halaman publik</div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile"/>
        <input type="hidden" name="_csrf" value="<?= $csrf ?>"/>

        <div class="panel-card">
          <div class="card-title">Identitas</div>
          <div class="grid2">
            <div class="field">
              <label>Nama Lengkap</label>
              <input type="text" name="name" value="<?= htmlspecialchars($profile['name'] ?? 'Robertus Danan') ?>" required/>
            </div>
            <div class="field">
              <label>Handle</label>
              <input type="text" name="handle" value="<?= htmlspecialchars($profile['handle'] ?? '@robertusdanan') ?>" required placeholder="@username"/>
            </div>
          </div>
          <div class="field">
            <label>Tagline</label>
            <input type="text" name="tagline" value="<?= htmlspecialchars($profile['tagline'] ?? 'Creator · Developer · Dreamer') ?>"/>
            <div class="hint">Contoh: Creator · Developer · Dreamer</div>
          </div>
        </div>

        <div class="panel-card">
          <div class="card-title">Foto Profil</div>
          <?php
            $avData = $profile['avatar_data'] ?? '';
            $avType = $profile['avatar_type'] ?? 'jpeg';
            $avSrc  = '';
            if (!empty($avData)) {
              $avSrc = str_starts_with($avData, 'http')
                ? htmlspecialchars($avData)
                : 'data:image/' . $avType . ';base64,' . $avData;
            }
          ?>
          <div class="avatar-preview" id="avatarPreview">
            <?php if ($avSrc): ?>
            <img src="<?= $avSrc ?>" alt="Avatar" id="avatarImg"/>
            <?php else: ?>
            <span style="font-family:var(--ff-head);font-size:22px;color:var(--ice)">RD</span>
            <?php endif; ?>
          </div>
          <div class="tabs" style="margin-bottom:20px">
            <div class="tab active" onclick="switchAvatarTab('upload',this)">Upload PNG/JPG</div>
            <div class="tab"       onclick="switchAvatarTab('base64',this)">Paste Base64</div>
          </div>
          <div id="avatarTabUpload" class="tab-panel active">
            <div class="file-drop" onclick="document.getElementById('avatarFileInput').click()">
              <input type="file" name="avatar_file" id="avatarFileInput" accept="image/png,image/jpeg,image/webp" onchange="previewAvatar(this)"/>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:block;margin:0 auto 8px">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
              </svg>
              Klik untuk pilih gambar (PNG / JPG / WebP)
            </div>
          </div>
          <div id="avatarTabBase64" class="tab-panel">
            <div class="field">
              <label>Base64 String</label>
              <textarea name="avatar_base64" id="avatarB64Input" rows="4"
                        placeholder="Paste data:image/jpeg;base64,... atau string base64 saja"
                        oninput="previewAvatarB64(this)"></textarea>
            </div>
            <div class="field">
              <label>Format (jika tanpa prefix)</label>
              <select name="avatar_type_manual">
                <option value="jpeg">JPEG</option>
                <option value="png">PNG</option>
                <option value="webp">WebP</option>
              </select>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <span>Simpan Perubahan Profil</span>
        </button>
      </form>
    </div>

    <!-- ══ SECTION: LINK CARDS ══ -->
    <div class="section <?= $activeSec === 'links' ? 'active' : '' ?>" id="sec-links">
      <div class="section-title">Link Cards</div>
      <div class="section-sub">Kelola, urutkan, tambah, dan hapus link card</div>

      <div class="panel-card">
        <div class="card-title" style="justify-content:space-between">
          <span style="display:flex;align-items:center;gap:8px">
            <span class="card-title" style="margin:0">Daftar Link</span>
          </span>
          <button class="btn btn-primary btn-sm" onclick="openAddModal()">
            <span>+ Tambah Link</span>
          </button>
        </div>
        <div style="font-size:.68rem;color:var(--ghost);margin-bottom:16px;font-family:var(--ff-mono)">
          Drag &amp; drop untuk mengatur urutan
        </div>

        <div id="linkList">
          <?php foreach ($linkCards as $lc): ?>
          <?php $icoSrc = renderIconSrc($lc); ?>
          <div class="link-item" draggable="true" data-id="<?= (int)$lc['id'] ?>">
            <span class="drag-handle">⠿</span>
            <div class="link-item-ico">
              <?php if ($lc['icon_type'] === 'instagram'): ?>
                <svg width="22" height="22" viewBox="0 0 24 24">
                  <defs><linearGradient id="ig<?= (int)$lc['id'] ?>" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#f09433"/><stop offset="35%" stop-color="#e6683c"/>
                    <stop offset="55%" stop-color="#dc2743"/><stop offset="80%" stop-color="#cc2366"/>
                    <stop offset="100%" stop-color="#bc1888"/>
                  </linearGradient></defs>
                  <path fill="url(#ig<?= (int)$lc['id'] ?>)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
              <?php elseif ($lc['icon_type'] === 'github'): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="rgba(160,191,255,.85)">
                  <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                </svg>
              <?php elseif ($lc['icon_type'] === 'saweria'): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="var(--accent2)">
                  <path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/>
                </svg>
              <?php elseif (!empty($icoSrc)): ?>
                <img src="<?= $icoSrc ?>" alt="icon"/>
              <?php else: ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
                  <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                  <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
              <?php endif; ?>
            </div>
            <div class="link-item-body">
              <div class="link-item-title"><?= htmlspecialchars($lc['label']) ?></div>
              <div class="link-item-sub"><?= htmlspecialchars($lc['url']) ?></div>
            </div>
            <span class="badge <?= $lc['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
              <?= $lc['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <div class="link-item-actions">
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit"
                      onclick="openEditModal(<?= htmlspecialchars(json_encode($lc), ENT_QUOTES) ?>)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
              <button class="btn btn-danger btn-sm btn-icon" title="Hapus"
                      onclick="confirmDelete(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars($lc['label'], ENT_QUOTES) ?>')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($linkCards)): ?>
          <div style="text-align:center;padding:40px;color:var(--ghost);font-size:.8rem;font-family:var(--ff-mono)">
            Belum ada link card. Klik "+ Tambah Link" untuk mulai.
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- ══ MODAL: ADD / EDIT LINK ══ -->
<div class="modal-overlay" id="linkModal">
  <div class="modal-box">
    <div class="modal-title" id="modalTitle">Tambah Link Card</div>
    <form method="POST" enctype="multipart/form-data" id="linkForm">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>"/>
      <input type="hidden" name="action" value="add_link" id="formAction"/>
      <input type="hidden" name="id" id="linkId"/>

      <div class="grid2">
        <div class="field">
          <label>Label / Judul *</label>
          <input type="text" name="label" id="fLabel" required placeholder="Contoh: YouTube"/>
        </div>
        <div class="field">
          <label>Sub / Username</label>
          <input type="text" name="sub" id="fSub" placeholder="@username atau tagline"/>
        </div>
      </div>
      <div class="field">
        <label>URL Tujuan *</label>
        <input type="url" name="url" id="fUrl" required placeholder="https://..."/>
      </div>
      <div class="field">
        <label>Domain (ditampilkan di modal)</label>
        <input type="text" name="domain" id="fDomain" placeholder="youtube.com/..."/>
      </div>
      <div class="field">
        <label>Deskripsi</label>
        <textarea name="description" id="fDesc" placeholder="Deskripsi singkat yang muncul di popup modal..."></textarea>
      </div>
      <div class="field">
        <label>Label Tombol CTA</label>
        <input type="text" name="btn_label" id="fBtnLabel" value="Buka Halaman"/>
      </div>

      <!-- ICON -->
      <div style="margin-bottom:20px">
        <div style="font-size:.7rem;font-family:var(--ff-mono);letter-spacing:.15em;text-transform:uppercase;color:var(--mist);margin-bottom:12px">Ikon</div>
        <div class="icon-type-select">
          <button type="button" class="icon-type-btn active" onclick="setIconType('custom',this)">Custom</button>
          <button type="button" class="icon-type-btn"       onclick="setIconType('saweria',this)">Saweria</button>
          <button type="button" class="icon-type-btn"       onclick="setIconType('instagram',this)">Instagram</button>
          <button type="button" class="icon-type-btn"       onclick="setIconType('github',this)">GitHub</button>
        </div>
        <input type="hidden" name="icon_type" id="fIconType" value="custom"/>

        <div id="customIconArea">
          <div class="icon-preview" id="iconPreview">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ghost)" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
          </div>
          <div class="tabs" style="margin-bottom:14px">
            <div class="tab active" onclick="switchIconTab('upload',this)">Upload PNG</div>
            <div class="tab"       onclick="switchIconTab('base64',this)">Paste Base64</div>
          </div>
          <div id="iconTabUpload" class="tab-panel active">
            <div class="file-drop" onclick="document.getElementById('iconFileInput').click()">
              <input type="file" name="icon_file" id="iconFileInput" accept="image/png,image/jpeg,image/webp,image/svg+xml" onchange="previewIcon(this)"/>
              Klik untuk pilih ikon (PNG / JPG / SVG)
            </div>
          </div>
          <div id="iconTabBase64" class="tab-panel">
            <div class="field">
              <label>Base64 Ikon</label>
              <textarea name="icon_base64" id="fIconB64" rows="3"
                        placeholder="data:image/png;base64,... atau base64 murni"
                        oninput="previewIconB64(this)"></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- is_active -->
      <div class="field" style="display:flex;align-items:center;gap:12px">
        <input type="checkbox" name="is_active" id="fActive" style="width:auto" checked/>
        <label for="fActive" style="margin:0;cursor:pointer;font-size:.8rem;color:var(--frost);text-transform:none;letter-spacing:0">
          Aktifkan link card ini
        </label>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button type="submit" class="btn btn-primary"><span id="modalSubmitLabel">Tambah Link</span></button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL: DELETE CONFIRM ══ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-title">Hapus Link Card</div>
    <p style="color:var(--mist);font-size:.85rem;margin-bottom:6px">
      Yakin ingin menghapus link card <strong id="deleteTarget" style="color:var(--frost)"></strong>?
    </p>
    <p style="color:var(--ghost);font-size:.75rem">Tindakan ini tidak bisa dibatalkan.</p>
    <form method="POST" style="margin-top:24px">
      <input type="hidden" name="action" value="delete_link"/>
      <input type="hidden" name="_csrf" value="<?= $csrf ?>"/>
      <input type="hidden" name="id" id="deleteId"/>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('deleteModal').classList.remove('open')">Batal</button>
        <button type="submit" class="btn btn-danger"><span>Hapus</span></button>
      </div>
    </form>
  </div>
</div>

<script>
// ── SECTION NAV ────────────────────────────────────────────
function showSection(id, navEl){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  document.getElementById('sec-'+id).classList.add('active');
  if(navEl) navEl.classList.add('active');
}
// Auto-show section dari query param
(function(){
  const sec = new URLSearchParams(location.search).get('sec');
  if(sec){
    const el = document.getElementById('sec-'+sec);
    const nav = document.querySelector('.nav-item[onclick*="\''+sec+'\'"]');
    if(el){
      document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
      document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
      el.classList.add('active');
      if(nav) nav.classList.add('active');
    }
  }
})();

// ── AVATAR TAB ──────────────────────────────────────────────
function switchAvatarTab(tab, el){
  document.querySelectorAll('#sec-profile .tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('#sec-profile .tab-panel').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('avatarTab'+tab[0].toUpperCase()+tab.slice(1)).classList.add('active');
}
function previewAvatar(input){
  if(!input.files[0]) return;
  const r=new FileReader(); r.onload=e=>setAvatarPreview(e.target.result); r.readAsDataURL(input.files[0]);
}
function previewAvatarB64(el){
  const v=el.value.trim(); if(!v) return;
  setAvatarPreview(v.startsWith('data:')?v:'data:image/jpeg;base64,'+v);
}
function setAvatarPreview(src){
  document.getElementById('avatarPreview').innerHTML='<img src="'+src+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%"/>';
}

// ── ICON TYPE ───────────────────────────────────────────────
function setIconType(type, btn){
  document.querySelectorAll('.icon-type-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('fIconType').value = type;
  const custom  = document.getElementById('customIconArea');
  const preview = document.getElementById('iconPreview');
  if(type==='custom'){
    custom.style.display='';
    preview.innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ghost)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
  } else {
    custom.style.display='none';
    const svgs={
      saweria:'<svg width="22" height="22" viewBox="0 0 24 24" fill="#9b72f8"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>',
      instagram:'<svg width="22" height="22" viewBox="0 0 24 24"><defs><linearGradient id="igP" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#f09433"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs><path fill="url(#igP)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
      github:'<svg width="20" height="20" viewBox="0 0 24 24" fill="rgba(160,191,255,.85)"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
    };
    preview.innerHTML=svgs[type]||'';
  }
}

// ── ICON TAB ─────────────────────────────────────────────────
function switchIconTab(tab, el){
  document.querySelectorAll('#linkModal .tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('#linkModal .tab-panel').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('iconTab'+tab[0].toUpperCase()+tab.slice(1)).classList.add('active');
}
function previewIcon(input){
  if(!input.files[0]) return;
  const r=new FileReader(); r.onload=e=>setIconPreview(e.target.result); r.readAsDataURL(input.files[0]);
}
function previewIconB64(el){
  const v=el.value.trim(); if(!v) return;
  setIconPreview(v.startsWith('data:')?v:'data:image/png;base64,'+v);
}
function setIconPreview(src){
  document.getElementById('iconPreview').innerHTML='<img src="'+src+'" style="width:30px;height:30px;object-fit:contain"/>';
}

// ── MODALS ───────────────────────────────────────────────────
function openAddModal(){
  document.getElementById('modalTitle').textContent      ='Tambah Link Card';
  document.getElementById('formAction').value            ='add_link';
  document.getElementById('modalSubmitLabel').textContent='Tambah Link';
  document.getElementById('linkId').value='';
  document.getElementById('linkForm').reset();
  document.getElementById('iconPreview').innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ghost)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
  document.querySelectorAll('.icon-type-btn').forEach((b,i)=>b.classList.toggle('active',i===0));
  setIconType('custom', document.querySelector('.icon-type-btn'));
  document.getElementById('linkModal').classList.add('open');
}

function openEditModal(link){
  document.getElementById('modalTitle').textContent      ='Edit Link Card';
  document.getElementById('formAction').value            ='update_link';
  document.getElementById('modalSubmitLabel').textContent='Simpan Perubahan';
  document.getElementById('linkId').value    = link.id;
  document.getElementById('fLabel').value    = link.label       || '';
  document.getElementById('fSub').value      = link.sub         || '';
  document.getElementById('fUrl').value      = link.url         || '';
  document.getElementById('fDomain').value   = link.domain      || '';
  document.getElementById('fDesc').value     = link.description || '';
  document.getElementById('fBtnLabel').value = link.btn_label   || 'Buka Halaman';
  document.getElementById('fActive').checked = !!link.is_active;

  const type    = link.icon_type || 'custom';
  const matchBtn= [...document.querySelectorAll('.icon-type-btn')].find(b=>b.textContent.trim().toLowerCase()===type)
                  || document.querySelector('.icon-type-btn');
  document.querySelectorAll('.icon-type-btn').forEach(b=>b.classList.remove('active'));
  matchBtn.classList.add('active');
  setIconType(type, matchBtn);

  if(type==='custom' && link.icon_data){
    const src = link.icon_data.startsWith('http') ? link.icon_data
      : 'data:image/'+(link.icon_mime||'png')+';base64,'+link.icon_data;
    setIconPreview(src);
  }
  document.getElementById('linkModal').classList.add('open');
}

function closeModal(){
  document.getElementById('linkModal').classList.remove('open');
}
function confirmDelete(id, label){
  document.getElementById('deleteId').value           = id;
  document.getElementById('deleteTarget').textContent = label;
  document.getElementById('deleteModal').classList.add('open');
}

// Close on overlay click
document.getElementById('linkModal').addEventListener('click',function(e){if(e.target===this)closeModal()});
document.getElementById('deleteModal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open')});

// ── DRAG & DROP REORDER ──────────────────────────────────────
(function(){
  let dragged=null;
  const list=document.getElementById('linkList');
  list.addEventListener('dragstart',e=>{
    dragged=e.target.closest('.link-item');
    if(dragged) dragged.classList.add('dragging');
  });
  list.addEventListener('dragend',e=>{
    if(dragged) dragged.classList.remove('dragging');
    dragged=null; saveOrder();
  });
  list.addEventListener('dragover',e=>{
    e.preventDefault();
    const target=e.target.closest('.link-item');
    if(!target||target===dragged) return;
    const rect=target.getBoundingClientRect();
    if(e.clientY>rect.top+rect.height/2) target.after(dragged); else target.before(dragged);
  });
  function saveOrder(){
    const items=[...list.querySelectorAll('.link-item[data-id]')];
    const orders=items.map((el,i)=>({id:el.dataset.id,order:i+1}));
    const fd=new FormData();
    fd.append('action','reorder');
    fd.append('_csrf','<?= $csrf ?>');
    fd.append('orders',JSON.stringify(orders));
    fetch('',{method:'POST',body:fd}).catch(()=>{});
  }
})();

// ── SESSION EXPIRY WARNING ───────────────────────────────────
(function(){
  const expiresAt = <?= json_encode($_SESSION['expires_at'] ?? 0) ?> * 1000;
  const warnAt    = expiresAt - 5 * 60 * 1000; // 5 menit sebelum expire
  const now       = Date.now();
  if(warnAt > now){
    setTimeout(()=>{
      const b=document.createElement('div');
      b.className='session-banner';
      b.innerHTML='⚠ Sesi Anda akan berakhir dalam 5 menit. Simpan pekerjaan Anda atau <a href="?action=logout" style="color:var(--amber);text-decoration:underline">logout</a> lalu login kembali.';
      document.querySelector('.main').prepend(b);
    }, warnAt - now);
  }
})();
</script>

<?php endif; ?>
</body>
</html>