<?php
// ══════════════════════════════════════════════════════════════
//  Supabase config — isi dengan nilai dari project Anda
// ══════════════════════════════════════════════════════════════
define('SUPABASE_URL',     getenv('supabase_url')     ?: '');
define('SUPABASE_KEY',     getenv('service_role') ?: '');
define('SUPABASE_ANON',    getenv('anon_public') ?: '');

// ── helper: Supabase REST GET ─────────────────────────────────
function supabase_get(string $table, string $query = '', bool $use_service = false): ?array {
    $url  = SUPABASE_URL . '/rest/v1/' . $table . ($query ? '?' . $query : '');
    $key  = $use_service ? SUPABASE_KEY : SUPABASE_ANON;
    $ctx  = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  =>
                "apikey: {$key}\r\n" .
                "Authorization: Bearer {$key}\r\n" .
                "Accept: application/json\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    return json_decode($raw, true);
}

// ── Fetch profil (baris pertama saja) ────────────────────────
$profileRows = supabase_get('profile', 'limit=1', false);
if (!empty($profileRows[0])) {
    $row = $profileRows[0];
    $profile = [
        'name'    => $row['name']    ?? 'Robertus Danan',
        'handle'  => $row['handle']  ?? '@robertusdanan',
        'tagline' => $row['tagline'] ?? 'Creator · Developer · Dreamer',
    ];
    // Avatar: kolom avatar_data berisi base64 murni (tanpa prefix)
    $avatar_type = $row['avatar_type'] ?? 'jpeg';
    $avatar_b64  = !empty($row['avatar_data']) ? $row['avatar_data'] : 'empty';
} else {
    // Fallback jika Supabase tidak dapat diakses
    $profile     = ['name' => 'Robertus Danan', 'handle' => '@robertusdanan', 'tagline' => 'Creator · Developer · Dreamer'];
    $avatar_type = 'jpeg';
    $avatar_b64  = 'empty';
}

// ── Fetch link cards (aktif, urut sort_order) ─────────────────
$linkRows = supabase_get('link_cards', 'is_active=eq.true&order=sort_order.asc', false);

$links = [];
if (!empty($linkRows)) {
    foreach ($linkRows as $lr) {
        // icon_type: 'saweria' | 'instagram' | 'github' | 'custom'
        $icon_type = $lr['icon_type'] ?? 'custom';

        // Untuk custom icon: pakai URL storage Supabase jika icon_data diisi
        // icon_data bisa berisi: path storage (misal "icons/saweria.png")
        // atau base64 string. Kita deteksi dari panjang/format-nya.
        $custom_icon_src = null;
        if ($icon_type === 'custom' && !empty($lr['icon_data'])) {
            $d = $lr['icon_data'];
            // Kalau pendek (< 256 char) anggap sebagai path storage
            if (strlen($d) < 256) {
                $custom_icon_src = SUPABASE_URL . '/storage/v1/object/public/media/' . ltrim($d, '/');
            } else {
                // base64 inline
                $mime = $lr['icon_mime'] ?? 'png';
                $custom_icon_src = "data:image/{$mime};base64,{$d}";
            }
        }

        $links[] = [
            'id'          => (string)($lr['id'] ?? ''),
            'label'       => $lr['label']      ?? '',
            'sub'         => $lr['sub']        ?? '',
            'url'         => $lr['url']        ?? '#',
            'domain'      => $lr['domain']     ?? '',
            'desc'        => $lr['description'] ?? '',
            'btn'         => $lr['btn_label']  ?? 'Buka Halaman',
            'type'        => $icon_type,
            'custom_icon' => $custom_icon_src,
        ];
    }
}

// Fallback jika tidak ada link sama sekali
if (empty($links)) {
    $links = [
        ['id' => '1', 'label' => 'Saweria',   'sub' => 'Support karya saya',   'url' => 'https://saweria.co/robertusdanan',        'domain' => 'saweria.co/robertusdanan',     'desc' => 'Setiap dukunganmu sangat berarti.', 'btn' => 'Support Sekarang', 'type' => 'saweria',   'custom_icon' => null],
        ['id' => '2', 'label' => 'Instagram', 'sub' => '@robertusdanan',        'url' => 'https://www.instagram.com/robertusdanan', 'domain' => 'instagram.com/robertusdanan',  'desc' => 'Follow untuk konten terbaru.',       'btn' => 'Buka Instagram',   'type' => 'instagram', 'custom_icon' => null],
        ['id' => '3', 'label' => 'GitHub',    'sub' => 'robertusdanan',         'url' => 'https://github.com/robertusdanan',        'domain' => 'github.com/robertusdanan',     'desc' => 'Jelajahi proyek open-source.',       'btn' => 'Buka GitHub',      'type' => 'github',    'custom_icon' => null],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<link rel="icon" href="/favicon.ico"/>
<title><?= htmlspecialchars($profile['handle']) ?></title>
<?php
$og_title       = htmlspecialchars($profile['name']);
$og_description = htmlspecialchars($profile['tagline']);
$og_url         = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'robertusdanan.onrender.com') . '/';
$og_image       = $og_url . 'og.jpg';
?>
<!-- Open Graph / WhatsApp -->
<meta property="og:type"        content="website"/>
<meta property="og:url"         content="<?= $og_url ?>"/>
<meta property="og:title"       content="<?= $og_title ?>"/>
<meta property="og:description" content="<?= $og_description ?>"/>
<meta property="og:image"       content="<?= $og_image ?>"/>
<meta property="og:image:width"  content="1200"/>
<meta property="og:image:height" content="630"/>
<!-- Twitter Card (fallback) -->
<meta name="twitter:card"        content="summary_large_image"/>
<meta name="twitter:title"       content="<?= $og_title ?>"/>
<meta name="twitter:description" content="<?= $og_description ?>"/>
<meta name="twitter:image"       content="<?= $og_image ?>"/>
<meta property="fb:app_id" content="2644601879268512" />
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Sora:wght@300;400;500;600&family=JetBrains+Mono:wght@300;400&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --void:    #03040a;
  --deep:    #070b16;
  --surface: #0c1120;
  --glass:   rgba(10,15,28,0.78);
  --edge:    rgba(100,150,255,0.14);
  --ice:     #a0bfff;
  --frost:   #d8e8ff;
  --mist:    #607090;
  --ghost:   #304060;
  --accent:  #4f80ff;
  --accent2: #9b72f8;
  --ff-head: 'Sora', sans-serif;
  --ff-body: 'Inter', sans-serif;
  --ff-mono: 'JetBrains Mono', monospace;
  --ease-expo:   cubic-bezier(0.16,1,0.3,1);
  --ease-spring: cubic-bezier(0.34,1.56,0.64,1);
}

html, body { width:100%; height:100%; overflow:hidden; background:var(--void); font-family:var(--ff-body); color:var(--frost); }
#cosmos { position:fixed; inset:0; z-index:0; display:block; }

.noise {
  position:fixed; inset:0; z-index:1; opacity:0.022; pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-repeat:repeat;
}

.scan {
  position:fixed; left:0; right:0; top:0; height:2px; z-index:5; pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(79,128,255,0.4),transparent);
  animation:scan-move 10s linear infinite;
}
@keyframes scan-move {
  0%  { top:-2px; opacity:0; }
  5%  { opacity:1; }
  94% { opacity:0.25; }
  100%{ top:100vh; opacity:0; }
}

/* ── STAGE & CARD ── */
.stage {
  position:fixed; inset:0; z-index:10;
  display:flex; align-items:center; justify-content:center; padding:20px;
}
.card {
  width:min(410px,96vw);
  background:var(--glass);
  border:1px solid var(--edge);
  border-radius:6px;
  padding:52px 40px 44px;
  text-align:center;
  backdrop-filter:blur(44px) saturate(1.5);
  -webkit-backdrop-filter:blur(44px) saturate(1.5);
  position:relative; overflow:hidden;
  box-shadow:
    0 0 0 1px rgba(79,128,255,0.05) inset,
    0 80px 180px rgba(0,0,0,0.88),
    0 0 120px rgba(79,128,255,0.03);
  animation:card-in 1.5s var(--ease-expo) both;
}
@keyframes card-in {
  from { opacity:0; transform:translateY(50px) scale(0.95); }
  to   { opacity:1; transform:none; }
}
.card::before {
  content:'';
  position:absolute; top:0; left:0; right:0; height:1px;
  background:linear-gradient(90deg,
    transparent 0%, rgba(79,128,255,0.7) 25%,
    rgba(155,114,248,0.9) 50%, rgba(79,128,255,0.7) 75%, transparent 100%
  );
  animation:bar-glow 5s ease-in-out infinite;
}
@keyframes bar-glow { 0%,100%{opacity:0.5} 50%{opacity:1} }
.card-inner-glow {
  position:absolute; top:-40px; left:50%; transform:translateX(-50%);
  width:220px; height:90px;
  background:radial-gradient(ellipse,rgba(79,128,255,0.1),transparent 70%);
  pointer-events:none;
}

/* ── AVATAR ── */
.avatar-wrap {
  position:relative; display:inline-flex;
  align-items:center; justify-content:center;
  margin-bottom:28px;
  animation:card-in 1.5s var(--ease-expo) 0.1s both;
}
.orbit-ring {
  position:absolute; inset:-10px; border-radius:50%;
  border:1px solid transparent;
  background:
    linear-gradient(var(--void),var(--void)) padding-box,
    conic-gradient(from 0deg,
      rgba(79,128,255,0.9), rgba(155,114,248,0.9),
      rgba(240,180,41,0.6), rgba(79,128,255,0.9)
    ) border-box;
  animation:spin 9s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }
.orbit-dot {
  position:absolute; top:-4px; left:50%; transform:translateX(-50%);
  width:7px; height:7px; border-radius:50%;
  background:var(--accent2);
  box-shadow:0 0 10px var(--accent2), 0 0 20px var(--accent2);
}
.avatar {
  width:92px; height:92px; border-radius:50%;
  background:linear-gradient(145deg,#0f1a30,#1a2540);
  border:2px solid rgba(79,128,255,0.25);
  display:flex; align-items:center; justify-content:center;
  position:relative; z-index:1; overflow:hidden;
  box-shadow:0 0 40px rgba(79,128,255,0.18), inset 0 1px 0 rgba(255,255,255,0.06);
}
.avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; display:block; }
.avatar-initials {
  font-family:var(--ff-head);
  font-size:26px; font-weight:500;
  color:var(--ice); letter-spacing:2px;
}

/* ── IDENTITY ── */
.name {
  font-family:var(--ff-head);
  font-size:clamp(1.75rem,5vw,2.25rem);
  font-weight:600; color:#fff; letter-spacing:-0.01em; line-height:1.1;
  animation:card-in 1.5s var(--ease-expo) 0.18s both;
}
.name span { color:var(--ice); font-weight:400; }
.handle {
  font-family:var(--ff-mono);
  font-size:0.67rem; letter-spacing:0.25em; text-transform:uppercase;
  color:var(--accent); margin-top:8px; font-weight:300;
  animation:card-in 1.5s var(--ease-expo) 0.25s both;
}
.tagline {
  font-size:0.82rem; font-weight:300; color:var(--mist);
  margin-top:8px; letter-spacing:0.08em;
  animation:card-in 1.5s var(--ease-expo) 0.3s both;
}

/* ── DIVIDER ── */
.divider {
  display:flex; align-items:center; gap:12px;
  margin:28px 0;
  animation:card-in 1.5s var(--ease-expo) 0.36s both;
}
.div-line     { flex:1; height:1px; background:linear-gradient(90deg,transparent,var(--ghost)); }
.div-line.r   { background:linear-gradient(270deg,transparent,var(--ghost)); }
.div-pip      { display:flex; gap:4px; align-items:center; }
.div-pip span { width:3px; height:3px; border-radius:50%; background:var(--ghost); }
.div-pip span.c { width:5px; height:5px; background:var(--accent); box-shadow:0 0 8px var(--accent); }

/* ── LINK BUTTONS ── */
.links { display:flex; flex-direction:column; gap:10px; }
.link-btn {
  position:relative;
  display:flex; align-items:center; gap:15px;
  padding:15px 18px;
  background:rgba(255,255,255,0.02);
  border:1px solid rgba(79,128,255,0.1);
  border-radius:4px;
  color:var(--frost); cursor:pointer; overflow:hidden;
  text-align:left; font-family:inherit; width:100%;
  transition:border-color .3s, background .3s,
             transform .3s var(--ease-spring), box-shadow .3s;
}
.link-btn::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(100deg,
    transparent 0%, rgba(79,128,255,0.06) 40%,
    rgba(155,114,248,0.08) 60%, transparent 100%
  );
  transform:translateX(-120%);
  transition:transform .55s ease;
}
.link-btn:hover::before { transform:translateX(120%); }
.link-btn:hover {
  border-color:rgba(79,128,255,0.35);
  background:rgba(79,128,255,0.05);
  transform:translateX(5px) translateY(-1px);
  box-shadow:0 6px 24px rgba(79,128,255,0.1),-3px 0 0 var(--accent);
}
.link-btn:active { transform:translateX(2px) scale(0.99); }
.ico {
  width:42px; height:42px; border-radius:50%;
  border:1px solid var(--edge); background:rgba(255,255,255,0.03);
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0; overflow:hidden;
  transition:border-color .3s, background .3s, transform .3s var(--ease-spring);
}
.link-btn:hover .ico {
  border-color:rgba(79,128,255,0.4); background:rgba(79,128,255,0.1);
  transform:scale(1.1) rotate(-6deg);
}
.ico img { width:28px; height:28px; object-fit:contain; display:block; }
.btn-text { flex:1; min-width:0; }
.btn-title {
  display:block; font-family:var(--ff-head);
  font-size:0.8rem; font-weight:500;
  color:#fff; letter-spacing:0.06em; text-transform:uppercase;
}
.btn-sub {
  display:block; font-size:0.7rem; color:var(--mist);
  margin-top:2px; font-weight:300;
}
.arrow {
  color:var(--ghost); font-size:16px; flex-shrink:0;
  transition:transform .3s var(--ease-spring), color .3s;
}
.link-btn:hover .arrow { transform:translateX(5px); color:var(--accent); }
.links .link-btn:nth-child(1){ animation:card-in 1.5s var(--ease-expo) 0.45s both; }
.links .link-btn:nth-child(2){ animation:card-in 1.5s var(--ease-expo) 0.55s both; }
.links .link-btn:nth-child(3){ animation:card-in 1.5s var(--ease-expo) 0.65s both; }

.footer {
  position: fixed;
  bottom: 22px;
  left: 0;
  width: 100%;
  text-align: center;
  z-index: 10;
  pointer-events: none;
  font-family: var(--ff-mono);
  font-size: 0.6rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: rgba(79,128,255,0.28);
  animation: card-in 1.5s var(--ease-expo) 1s both;
}

/* ── MODAL ── */
.overlay {
  position:fixed; inset:0; z-index:200;
  background:rgba(3,4,10,0.9); backdrop-filter:blur(30px);
  display:flex; align-items:center; justify-content:center; padding:20px;
  opacity:0; pointer-events:none;
  transition:opacity .35s ease;
}
.overlay.open { opacity:1; pointer-events:auto; }
.modal {
  background:rgba(10,15,28,0.96);
  border:1px solid var(--edge);
  border-radius:6px;
  padding:48px 36px 36px;
  width:min(390px,100%);
  text-align:center; position:relative; overflow:hidden;
  box-shadow:0 0 0 1px rgba(79,128,255,0.06) inset, 0 80px 140px rgba(0,0,0,0.92);
  transform:translateY(28px) scale(0.95);
  transition:transform .45s var(--ease-spring);
}
.overlay.open .modal { transform:none; }
.modal::before {
  content:''; position:absolute; top:0; left:0; right:0; height:1px;
  background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),var(--accent),transparent);
  opacity:0.8;
}
.modal-glow {
  position:absolute; top:-50px; left:50%; transform:translateX(-50%);
  width:180px; height:100px;
  background:radial-gradient(ellipse,rgba(79,128,255,0.14),transparent 70%);
  pointer-events:none;
}
.modal-ico {
  width:64px; height:64px; border-radius:50%;
  border:1px solid var(--edge); background:rgba(255,255,255,0.03);
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 20px;
  box-shadow:0 0 30px rgba(79,128,255,0.12); overflow:hidden;
}
.modal-ico img { width:38px; height:38px; object-fit:contain; display:block; }
.modal-title {
  font-family:var(--ff-head);
  font-size:1.55rem; font-weight:600; color:#fff;
  letter-spacing:-0.01em; margin-bottom:6px;
}
.modal-domain {
  font-family:var(--ff-mono);
  font-size:0.63rem; color:var(--mist);
  letter-spacing:0.14em; margin-bottom:18px;
}
.saweria-card {
  border:1px solid rgba(155,114,248,0.22);
  background:rgba(155,114,248,0.05);
  border-radius:4px; padding:16px; margin-bottom:18px;
  font-size:0.8rem; color:var(--ice); line-height:1.7; font-weight:300;
}
.heart-pulse { display:inline-block; font-style:normal; animation:hb 1.2s ease-in-out infinite; }
@keyframes hb { 0%,100%{transform:scale(1)} 40%{transform:scale(1.4)} 60%{transform:scale(1.2)} }
.modal-desc { font-size:0.8rem; color:var(--mist); line-height:1.7; margin-bottom:28px; font-weight:300; }
.modal-actions { display:flex; gap:10px; }
.btn-cancel,.btn-go {
  flex:1; padding:12px; border-radius:4px;
  font-family:var(--ff-mono); font-size:0.68rem;
  letter-spacing:0.16em; text-transform:uppercase;
  cursor:pointer; font-weight:300; transition:all .22s ease;
}
.btn-cancel { background:transparent; border:1px solid var(--ghost); color:var(--mist); }
.btn-cancel:hover { border-color:var(--mist); color:var(--frost); }
.btn-go {
  background:transparent; border:1px solid var(--accent);
  color:var(--accent); position:relative; overflow:hidden;
}
.btn-go::before {
  content:''; position:absolute; inset:0; background:var(--accent);
  transform:scaleX(0); transform-origin:left; transition:transform .28s ease;
}
.btn-go:hover::before { transform:scaleX(1); }
.btn-go:hover { color:var(--void); box-shadow:0 0 22px rgba(79,128,255,0.35); }
.btn-go span { position:relative; z-index:1; }
/* ── CLOSE BUTTON ── */
.close-btn {
  position: fixed;
  top: 22px;
  right: 24px;
  z-index: 50;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: rgba(10, 15, 28, 0.72);
  border: 1px solid rgba(79, 128, 255, 0.18);
  color: var(--mist);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  transition: border-color .3s, background .3s, color .3s, transform .3s var(--ease-spring), box-shadow .3s;
  animation: card-in 1.5s var(--ease-expo) 0.8s both;
}
.close-btn:hover {
  border-color: rgba(255, 90, 90, 0.55);
  background: rgba(255, 60, 60, 0.1);
  color: #ff8080;
  transform: rotate(90deg) scale(1.1);
  box-shadow: 0 0 20px rgba(255, 60, 60, 0.18);
}
.close-btn:active {
  transform: rotate(90deg) scale(0.95);
}
.close-btn svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
}
</style>
</head>
<body>

<canvas id="cosmos"></canvas>
<div class="noise"></div>
<div class="scan"></div>
<!-- CLOSE BUTTON -->
<button class="close-btn" onclick="if(!window.close()) window.history.back()" title="Tutup halaman" aria-label="Tutup halaman">
  <svg viewBox="0 0 16 16">
    <line x1="2" y1="2" x2="14" y2="14"/>
    <line x1="14" y1="2" x2="2" y2="14"/>
  </svg>
</button>
<div class="stage">
  <div class="card">
    <div class="card-inner-glow"></div>

    <!-- Avatar -->
    <div class="avatar-wrap">
      <div class="orbit-ring"><div class="orbit-dot"></div></div>
      <div class="avatar">
        <?php if ($avatar_b64 !== 'empty' && !empty($avatar_b64)): ?>
          <img src="data:image/<?= htmlspecialchars($avatar_type) ?>;base64,<?= $avatar_b64 ?>" alt="<?= htmlspecialchars($profile['name']) ?>"/>
        <?php else: ?>
          <span class="avatar-initials">RD</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Identity -->
    <h1 class="name">
      <?php
        $parts = explode(' ', $profile['name'], 2);
        echo htmlspecialchars($parts[0]);
        if (isset($parts[1])) echo ' <span>' . htmlspecialchars($parts[1]) . '</span>';
      ?>
    </h1>

    <p class="tagline"><?= htmlspecialchars($profile['tagline']) ?></p>

    <!-- Divider -->
    <div class="divider">
      <span class="div-line"></span>
      <span class="div-pip"><span></span><span class="c"></span><span></span></span>
      <span class="div-line r"></span>
    </div>

    <!-- Links -->
    <div class="links">
      <?php foreach ($links as $i => $link): ?>
      <button class="link-btn" onclick="openModal(<?= $i ?>)">
        <div class="ico">
          <?php if ($link['type'] === 'saweria'): ?>
            <?php if (!empty($link['custom_icon'])): ?>
              <img src="<?= htmlspecialchars($link['custom_icon']) ?>" style="width:24px;height:24px;object-fit:contain;" alt="<?= htmlspecialchars($link['label']) ?>"/>
            <?php else: ?>
              <!-- Saweria default icon (SVG fallback) -->
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="#ff6b6b"/>
              </svg>
            <?php endif; ?>
          <?php elseif ($link['type'] === 'instagram'): ?>
            <svg width="24" height="24" viewBox="0 0 24 24">
              <defs>
                <linearGradient id="igL<?= $i ?>" x1="0%" y1="100%" x2="100%" y2="0%">
                  <stop offset="0%"   stop-color="#f09433"/>
                  <stop offset="35%"  stop-color="#e6683c"/>
                  <stop offset="55%"  stop-color="#dc2743"/>
                  <stop offset="80%"  stop-color="#cc2366"/>
                  <stop offset="100%" stop-color="#bc1888"/>
                </linearGradient>
              </defs>
              <path fill="url(#igL<?= $i ?>)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
            </svg>
          <?php elseif ($link['type'] === 'github'): ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="rgba(160,191,255,0.88)">
              <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
            </svg>
          <?php else: ?>
            <?php if (!empty($link['custom_icon'])): ?>
              <img src="<?= htmlspecialchars($link['custom_icon']) ?>" style="width:24px;height:24px;object-fit:contain;" alt="<?= htmlspecialchars($link['label']) ?>"/>
            <?php else: ?>
              <!-- Generic link icon -->
              <svg width="22" height="22" viewBox="0 0 24 24" fill="rgba(160,191,255,0.88)">
                <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
              </svg>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="btn-text">
          <span class="btn-title"><?= htmlspecialchars($link['label']) ?></span>
          <span class="btn-sub"><?= htmlspecialchars($link['sub']) ?></span>
        </div>
        <span class="arrow">&#x2192;</span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<p class="footer">&#11835; &nbsp; <?= htmlspecialchars($profile['handle']) ?> &nbsp; &#11835;</p>

<!-- MODAL -->
<div class="overlay" id="overlay" onclick="bgClose(event)">
  <div class="modal" id="modal">
    <div class="modal-glow"></div>
    <div class="modal-ico" id="mIco"></div>
    <div class="modal-title" id="mTitle"></div>
    <div class="modal-domain" id="mDomain"></div>
    <div id="mSaw" class="saweria-card" style="display:none">
      <span class="heart-pulse">&#10084;&#65039;</span>
      &nbsp;<strong>Mohon dukunganmu!</strong><br/>
      Setiap support yang kamu berikan sangat berarti dan terus memotivasi saya untuk berkarya lebih baik.&nbsp;
      <span class="heart-pulse">&#128591;</span>
    </div>
    <div class="modal-desc" id="mDesc"></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Kembali</button>
      <button class="btn-go" id="btnGo" onclick="goLink()"><span id="btnLabel">Buka Halaman</span></button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
const LINKS = <?= json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
// Saweria icon diambil dari data link (kolom custom_icon), tidak lagi hardcode base64

// ═══════════════════════════════════════════════════════
// THREE.JS — Hyper-Realistic Solar System Background
// 4D temporal distortion · Volumetric atmosphere · HDR bloom
// ═══════════════════════════════════════════════════════
(function () {
  const canvas   = document.getElementById('cosmos');
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.setClearColor(0x000105, 1);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.setSize(innerWidth, innerHeight);

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(48, innerWidth / innerHeight, 0.1, 6000);
  camera.position.set(0, 0, 42);

  // ── HELPERS ──────────────────────────────────────────
  function cv(w, h) {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    return { c, x: c.getContext('2d') };
  }
  function tx(w, h, fn) {
    const { c, x } = cv(w, h);
    fn(x, w, h);
    return new THREE.CanvasTexture(c);
  }
  function noise2D(x, y, seed) {
    const n = Math.sin(x * 127.1 + y * 311.7 + seed) * 43758.5453;
    return n - Math.floor(n);
  }

  // ── DEEP STAR FIELD (multi-layer parallax) ────────────
  function mkStars(n, spread, sz, op, col, zOff) {
    const g = new THREE.BufferGeometry(), p = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
      p[i*3]   = (Math.random()-.5)*spread;
      p[i*3+1] = (Math.random()-.5)*spread;
      p[i*3+2] = (Math.random()-.5)*spread + (zOff||0) - 50;
    }
    g.setAttribute('position', new THREE.BufferAttribute(p, 3));
    const { c, x } = cv(32, 32);
    const gr = x.createRadialGradient(16,16,0,16,16,16);
    gr.addColorStop(0, col || 'rgba(220,235,255,1)');
    gr.addColorStop(.25, col || 'rgba(180,205,255,.9)');
    gr.addColorStop(.6, col || 'rgba(140,170,255,.4)');
    gr.addColorStop(1, 'rgba(0,0,0,0)');
    x.fillStyle = gr; x.beginPath(); x.arc(16,16,16,0,Math.PI*2); x.fill();
    scene.add(new THREE.Points(g, new THREE.PointsMaterial({
      size: sz, map: new THREE.CanvasTexture(c), sizeAttenuation: true,
      transparent: true, opacity: op, alphaTest: .005, depthWrite: false,
    })));
  }
  mkStars(4000, 1100, .11, .5, null, -200);
  mkStars(1500, 700,  .2,  .45, 'rgba(200,215,255,1)', -100);
  mkStars(500,  300,  .35, .65, null, -50);
  mkStars(120,  150,  .6,  .75, 'rgba(255,248,220,1)');
  mkStars(60,   90,   .95, .82, 'rgba(255,225,185,1)');
  mkStars(20,   50,   1.4, .9,  'rgba(180,220,255,1)');

  // ── MILKY WAY ─────────────────────────────────────────
  {
    const { c, x } = cv(2048, 1024);
    const g1 = x.createRadialGradient(650,512,0,650,512,750);
    g1.addColorStop(0,'rgba(55,75,185,.26)'); g1.addColorStop(.5,'rgba(35,55,145,.1)'); g1.addColorStop(1,'rgba(0,0,0,0)');
    x.fillStyle = g1; x.fillRect(0,0,2048,1024);
    const g2 = x.createRadialGradient(1450,380,0,1450,380,450);
    g2.addColorStop(0,'rgba(110,55,175,.18)'); g2.addColorStop(1,'rgba(0,0,0,0)');
    x.fillStyle = g2; x.fillRect(0,0,2048,1024);
    const g3 = x.createRadialGradient(1100,600,0,1100,600,300);
    g3.addColorStop(0,'rgba(80,120,200,.12)'); g3.addColorStop(1,'rgba(0,0,0,0)');
    x.fillStyle = g3; x.fillRect(0,0,2048,1024);
    const m = new THREE.Mesh(
      new THREE.PlaneGeometry(900, 450),
      new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, opacity: .98, depthWrite: false, blending: THREE.AdditiveBlending })
    );
    m.position.set(0, 0, -350); scene.add(m);
  }

  // ── EARTH TEXTURE (4K equivalent detail) ─────────────
  const earthTex = tx(4096, 2048, (x, w, h) => {
    // Deep ocean with depth variation
    const og = x.createLinearGradient(0, 0, w, h);
    og.addColorStop(0,'#041640'); og.addColorStop(.15,'#061b50'); og.addColorStop(.35,'#07205e');
    og.addColorStop(.55,'#082568'); og.addColorStop(.75,'#061b52'); og.addColorStop(1,'#041640');
    x.fillStyle = og; x.fillRect(0,0,w,h);

    // Ocean depth contours
    for (let i = 0; i < 120; i++) {
      x.strokeStyle = `rgba(8,48,160,${.025+Math.random()*.045})`; x.lineWidth = .8+Math.random()*1.8;
      x.beginPath();
      const y = Math.random()*h;
      x.moveTo(0, y);
      for (let j = 0; j < w; j += 28) x.lineTo(j, y + Math.sin(j*.008)*18 + (Math.random()-.5)*12);
      x.stroke();
    }

    // Continents — more detailed, with sub-biome fills
    const continents = [
      // North America
      { pts:[[310,145],[395,128],[445,140],[475,158],[495,195],[490,245],[470,295],[448,338],[420,360],[388,355],[358,320],[335,280],[315,248],[308,205],[312,172]], c:'#2e5c20', hi:'#3a7228' },
      // South America
      { pts:[[375,360],[428,342],[462,358],[478,400],[480,458],[462,528],[438,578],[405,608],[375,595],[348,552],[338,498],[342,438],[355,388]], c:'#2a581a', hi:'#336e22' },
      // Europe
      { pts:[[605,130],[665,120],[712,130],[735,148],[745,172],[730,195],[700,210],[668,218],[635,210],[612,192],[600,165],[600,148]], c:'#3d6e28', hi:'#4a8030' },
      // Africa
      { pts:[[595,200],[665,185],[730,195],[768,228],[788,285],[798,365],[782,448],[750,520],[710,568],[668,578],[630,562],[595,508],[575,445],[568,368],[574,295],[580,240]], c:'#3d6e24', hi:'#4a8030' },
      // North Africa (Sahara overlay)
      { pts:[[598,200],[735,188],[765,225],[745,280],[685,310],[618,298],[590,265],[590,225]], c:'#c8a830', hi:'#d4bc48' },
      // Asia (west)
      { pts:[[712,112],[825,95],[940,105],[1005,125],[1050,148],[1068,188],[1050,238],[998,268],[935,278],[872,272],[808,255],[752,228],[718,195],[708,162],[708,135]], c:'#3d6e24', hi:'#4a8030' },
      // Asia (east extension)
      { pts:[[940,105],[1060,90],[1160,108],[1220,135],[1248,178],[1240,228],[1205,265],[1155,285],[1095,282],[1040,268],[998,268],[1050,238],[1068,188],[1050,148],[1005,125]], c:'#3e6e24', hi:'#4a8028' },
      // Siberia / Russia
      { pts:[[780,65],[940,48],[1100,55],[1220,72],[1260,100],[1248,135],[1220,135],[1160,108],[1060,90],[940,105],[825,95],[750,110],[730,88]], c:'#355f1e', hi:'#3e7225' },
      // India
      { pts:[[870,255],[938,248],[972,275],[968,335],[940,395],[910,435],[875,428],[848,378],[840,318],[848,272]], c:'#4a7828', hi:'#5a8e30' },
      // Southeast Asia
      { pts:[[1000,238],[1065,232],[1108,252],[1115,298],[1092,328],[1055,322],[1018,295],[998,268]], c:'#3d6e24', hi:'#4a8030' },
      // Australia
      { pts:[[1005,448],[1088,432],[1158,445],[1198,482],[1205,538],[1182,588],[1128,608],[1065,612],[1015,580],[985,538],[978,485],[990,455]], c:'#8a6510', hi:'#a07c1a' },
      // Greenland
      { pts:[[445,62],[518,52],[555,68],[552,108],[528,132],[488,138],[452,122],[438,95]], c:'#c8dce8', hi:'#ddeef8' },
      // Antarctica (partial)
      { pts:[[0,970],[400,955],[800,950],[1200,945],[1600,950],[2048,960],[2048,1024],[0,1024]], c:'#d5e8f0', hi:'#e5f2f8' },
    ];

    continents.forEach(({ pts, c, hi }) => {
      x.beginPath(); x.moveTo(pts[0][0]*w/2048, pts[0][1]*h/1024);
      pts.forEach(([px,py]) => x.lineTo(px*w/2048, py*h/1024));
      x.closePath();
      // Base fill
      x.fillStyle = c; x.fill();
      // Terrain variation
      x.fillStyle = 'rgba(0,0,0,.1)';
      for (let i = 0; i < 12; i++) {
        const mx = pts[Math.floor(Math.random()*pts.length)];
        x.beginPath(); x.arc(mx[0]*w/2048+(Math.random()-.5)*30, mx[1]*h/1024+(Math.random()-.5)*20, 8+Math.random()*20, 0, Math.PI*2); x.fill();
      }
      // Highlight ridges
      x.fillStyle = hi;
      for (let i = 0; i < 5; i++) {
        const mx = pts[Math.floor(Math.random()*pts.length)];
        x.beginPath(); x.arc(mx[0]*w/2048+(Math.random()-.5)*15, mx[1]*h/1024+(Math.random()-.5)*10, 4+Math.random()*10, 0, Math.PI*2); x.fill();
      }
    });

    // Polar ice caps — layered
    const npg = x.createLinearGradient(0,0,0,110);
    npg.addColorStop(0,'rgba(235,248,255,1)'); npg.addColorStop(.6,'rgba(220,242,252,.82)'); npg.addColorStop(1,'rgba(210,238,250,0)');
    x.fillStyle = npg; x.fillRect(0,0,w,110);
    x.fillStyle = 'rgba(215,238,252,.5)';
    for (let i = 0; i < w; i+=2) x.fillRect(i, 90 + Math.sin(i*.04)*22 + Math.random()*18, 2, 45);
    const spg = x.createLinearGradient(0,h-110,0,h);
    spg.addColorStop(0,'rgba(235,248,255,0)'); spg.addColorStop(.4,'rgba(228,245,255,.82)'); spg.addColorStop(1,'rgba(235,248,255,1)');
    x.fillStyle = spg; x.fillRect(0,h-110,w,110);

    // Aurora borealis (subtle polar glow)
    x.globalAlpha = .13;
    ['rgba(80,255,160,.7)','rgba(60,200,255,.6)','rgba(160,80,255,.5)'].forEach((col,i) => {
      const ag = x.createRadialGradient(w*(.25+i*.28), 40, 0, w*(.25+i*.28), 40, 220+i*60);
      ag.addColorStop(0, col); ag.addColorStop(1,'rgba(0,0,0,0)');
      x.fillStyle = ag; x.fillRect(0,0,w,180);
    });
    x.globalAlpha = 1;

    // Cloud layer — more volumetric with fractal-like shapes
    x.globalAlpha = .28;
    for (let i = 0; i < 420; i++) {
      const cx = Math.random()*w, cy = Math.random()*h;
      const rx = 25+Math.random()*210, ry = 6+Math.random()*30;
      const a  = (Math.random()-.5)*.8;
      const g  = x.createRadialGradient(cx,cy,0,cx,cy,rx);
      g.addColorStop(0,'rgba(255,255,255,1)'); g.addColorStop(.4,'rgba(255,255,255,.65)'); g.addColorStop(.75,'rgba(255,255,255,.2)'); g.addColorStop(1,'rgba(255,255,255,0)');
      x.fillStyle = g; x.beginPath(); x.ellipse(cx,cy,rx,ry,a,0,Math.PI*2); x.fill();
    }
    x.globalAlpha = 1;

    // City light bleed on dark side (very subtle warm dots near coasts)
    x.globalAlpha = .055;
    x.fillStyle = 'rgba(255,220,120,1)';
    [[420,220],[640,165],[750,190],[820,165],[870,270],[1050,200],[1080,265],[480,380]].forEach(([cx,cy]) => {
      for (let j = 0; j < 18; j++) x.fillRect(cx*w/2048+(Math.random()-.5)*35, cy*h/1024+(Math.random()-.5)*25, 1.5, 1.5);
    });
    x.globalAlpha = 1;
  });

  // ── EARTH SPECULAR ────────────────────────────────────
  const earthSpec = tx(2048, 1024, (x, w, h) => {
    x.fillStyle = '#060816'; x.fillRect(0,0,w,h);
    // Ocean specular — bright
    x.fillStyle = 'rgba(60,110,255,.55)'; x.fillRect(0,0,w,h);
    // Mask continents
    [[310,145,495,360],[375,360,480,608],[600,120,800,580],[700,65,1260,285],[870,255,972,435],[1005,448,1205,612],[445,62,555,138]].forEach(([x1,y1,x2,y2]) => {
      x.fillStyle = 'rgba(0,0,0,.82)';
      x.fillRect(x1*w/2048, y1*h/1024, (x2-x1)*w/2048, (y2-y1)*h/1024);
    });
  });

  // ── EARTH NORMAL MAP (enhanced bump) ─────────────────
  const earthBump = tx(2048, 1024, (x, w, h) => {
    x.fillStyle = '#7f7f7f'; x.fillRect(0,0,w,h);
    // Himalaya ridge
    for (let i = 0; i < 80; i++) {
      x.fillStyle = `rgba(210,210,210,${.2+Math.random()*.35})`;
      x.beginPath(); x.arc(820*w/2048+Math.random()*120*w/2048, 195*h/1024+Math.random()*50*h/1024, 4+Math.random()*14, 0, Math.PI*2); x.fill();
    }
    // Andes
    for (let i = 0; i < 60; i++) {
      x.fillStyle = `rgba(200,200,200,${.18+Math.random()*.3})`;
      x.beginPath(); x.arc(375*w/2048+Math.random()*18*w/2048, 380*h/1024+Math.random()*180*h/1024, 3+Math.random()*10, 0, Math.PI*2); x.fill();
    }
    // Rocky Mts
    for (let i = 0; i < 40; i++) {
      x.fillStyle = `rgba(195,195,195,${.15+Math.random()*.25})`;
      x.beginPath(); x.arc(330*w/2048+Math.random()*28*w/2048, 160*h/1024+Math.random()*120*h/1024, 2+Math.random()*8, 0, Math.PI*2); x.fill();
    }
  });

  // ── CLOUD TEXTURE (layered cirrus + cumulus) ──────────
  const cloudTex = tx(4096, 2048, (x, w, h) => {
    x.clearRect(0,0,w,h);
    // Cirrus layer
    x.globalAlpha = .4;
    for (let i = 0; i < 300; i++) {
      const cx = Math.random()*w, cy = Math.random()*h;
      const rx = 80+Math.random()*320, ry = 4+Math.random()*14;
      const g  = x.createRadialGradient(cx,cy,0,cx,cy,rx);
      g.addColorStop(0,'rgba(255,255,255,.7)'); g.addColorStop(.5,'rgba(255,255,255,.3)'); g.addColorStop(1,'rgba(255,255,255,0)');
      x.fillStyle = g; x.beginPath(); x.ellipse(cx,cy,rx,ry,(Math.random()-.5)*.6,0,Math.PI*2); x.fill();
    }
    // Cumulus layer
    x.globalAlpha = .55;
    for (let i = 0; i < 220; i++) {
      const cx = Math.random()*w, cy = Math.random()*h;
      const rx = 40+Math.random()*150, ry = 12+Math.random()*45;
      const g  = x.createRadialGradient(cx,cy,0,cx,cy,rx);
      g.addColorStop(0,'rgba(255,255,255,.9)'); g.addColorStop(.38,'rgba(255,255,255,.55)'); g.addColorStop(1,'rgba(255,255,255,0)');
      x.fillStyle = g; x.beginPath(); x.ellipse(cx,cy,rx,ry,(Math.random()-.5)*.3,0,Math.PI*2); x.fill();
    }
    x.globalAlpha = 1;
  });

  // ── EARTH MESH ────────────────────────────────────────
  const eR = 6.8;
  const earth = new THREE.Mesh(
    new THREE.SphereGeometry(eR, 256, 256),
    new THREE.MeshPhongMaterial({
      map:         earthTex,
      bumpMap:     earthBump,
      bumpScale:   .18,
      specularMap: earthSpec,
      specular:    new THREE.Color(.1, .22, .55),
      shininess:   34,
    })
  );
  earth.position.set(22, -15, -1);
  earth.rotation.z = .4;
  scene.add(earth);

  // Cloud shell
  const clouds = new THREE.Mesh(
    new THREE.SphereGeometry(eR * 1.015, 256, 256),
    new THREE.MeshPhongMaterial({ map: cloudTex, transparent: true, opacity: .72, depthWrite: false, shininess: 8 })
  );
  clouds.position.copy(earth.position);
  scene.add(clouds);

  // Atmosphere layers — physically-based falloff
  [
    { r: eR*1.022, op: .42, c: new THREE.Color(.28,.58,1)    },
    { r: eR*1.05,  op: .25, c: new THREE.Color(.2,.5,.92)    },
    { r: eR*1.09,  op: .14, c: new THREE.Color(.15,.42,.82)  },
    { r: eR*1.14,  op: .07, c: new THREE.Color(.1,.32,.72)   },
    { r: eR*1.22,  op: .032,c: new THREE.Color(.08,.22,.62)  },
    { r: eR*1.35,  op: .014,c: new THREE.Color(.06,.16,.52)  },
    { r: eR*1.55,  op: .005,c: new THREE.Color(.04,.1,.42)   },
  ].forEach(({ r, op, c }) => {
    const m = new THREE.Mesh(
      new THREE.SphereGeometry(r, 64, 64),
      new THREE.MeshBasicMaterial({ color: c, transparent: true, opacity: op, side: THREE.FrontSide, depthWrite: false, blending: THREE.AdditiveBlending })
    );
    m.position.copy(earth.position); scene.add(m);
  });

  // Terminator shadow (night side darkening)
  const terminatorGeo = new THREE.SphereGeometry(eR * 1.005, 64, 64);
  const terminatorMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.MultiplyBlending,
    uniforms: { sunDir: { value: new THREE.Vector3(-1, 0.4, -3).normalize() } },
    vertexShader: `
      varying vec3 vNormal;
      void main() { vNormal = normalize(normalMatrix * normal); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }
    `,
    fragmentShader: `
      uniform vec3 sunDir;
      varying vec3 vNormal;
      void main() {
        float d = dot(vNormal, sunDir);
        float t = smoothstep(-.08, .18, d);
        gl_FragColor = vec4(0.0, 0.0, 0.0, (1.0 - t) * .72);
      }
    `,
  });
  const terminator = new THREE.Mesh(terminatorGeo, terminatorMat);
  terminator.position.copy(earth.position);
  scene.add(terminator);

  // ── MOON ──────────────────────────────────────────────
  const moonTex = tx(1024, 512, (x, w, h) => {
    const bg = x.createRadialGradient(w/2,h/2,0,w/2,h/2,w/2);
    bg.addColorStop(0,'#c0bbb0'); bg.addColorStop(.7,'#9a958c'); bg.addColorStop(1,'#726e68');
    x.fillStyle = bg; x.fillRect(0,0,w,h);
    // Mare regions
    [{cx:.35,cy:.4,r:75,op:.55},{cx:.62,cy:.32,r:52,op:.48},{cx:.68,cy:.58,r:40,op:.44},{cx:.22,cy:.62,r:38,op:.5},{cx:.5,cy:.55,r:30,op:.4}].forEach(m => {
      x.fillStyle = `rgba(70,65,60,${m.op})`; x.beginPath(); x.arc(m.cx*w,m.cy*h,m.r,0,Math.PI*2); x.fill();
    });
    for (let i = 0; i < 120; i++) {
      const cx = Math.random()*w, cy = Math.random()*h, r = 2+Math.random()*22;
      x.strokeStyle = `rgba(55,52,48,${.35+Math.random()*.45})`; x.lineWidth = 1;
      x.beginPath(); x.arc(cx,cy,r,0,Math.PI*2); x.stroke();
      x.fillStyle = `rgba(45,42,38,${.1+Math.random()*.18})`; x.fill();
    }
    const ld = x.createRadialGradient(w/2,h/2,w*.22,w/2,h/2,w/2);
    ld.addColorStop(0,'rgba(0,0,0,0)'); ld.addColorStop(.65,'rgba(0,0,0,.08)'); ld.addColorStop(1,'rgba(0,0,0,.52)');
    x.fillStyle = ld; x.fillRect(0,0,w,h);
  });
  const moon = new THREE.Mesh(
    new THREE.SphereGeometry(1.6, 64, 64),
    new THREE.MeshPhongMaterial({ map: moonTex, shininess: 2, specular: new THREE.Color(.04,.04,.04) })
  );
  scene.add(moon);

  // ── SUN ───────────────────────────────────────────────
  const sunTex = tx(1024, 1024, (x, w, h) => {
    const g = x.createRadialGradient(w/2,h/2,0,w/2,h/2,w/2);
    g.addColorStop(0,'#ffffff'); g.addColorStop(.04,'#fffef0'); g.addColorStop(.12,'#fff8b0');
    g.addColorStop(.28,'#ffe040'); g.addColorStop(.5,'#ff9000'); g.addColorStop(.72,'#ff4400'); g.addColorStop(1,'rgba(255,30,0,0)');
    x.fillStyle = g; x.beginPath(); x.arc(w/2,h/2,w/2,0,Math.PI*2); x.fill();
    // Granulation
    for (let i = 0; i < 600; i++) {
      const a = Math.random()*Math.PI*2, r = Math.random()*w*.42;
      x.fillStyle = `rgba(255,${180+Math.floor(Math.random()*75)},0,${.04+Math.random()*.14})`;
      x.beginPath(); x.arc(w/2+r*Math.cos(a),h/2+r*Math.sin(a),1.5+Math.random()*8,0,Math.PI*2); x.fill();
    }
    // Prominences
    for (let i = 0; i < 28; i++) {
      const a = Math.random()*Math.PI*2, r = w*.42;
      x.fillStyle = `rgba(255,${120+Math.floor(Math.random()*80)},0,${.09+Math.random()*.15})`;
      x.beginPath(); x.ellipse(w/2+r*Math.cos(a),h/2+r*Math.sin(a),7+Math.random()*22,4+Math.random()*10,a,0,Math.PI*2); x.fill();
    }
  });
  const sunR = 18;
  const sun = new THREE.Mesh(new THREE.SphereGeometry(sunR,64,64), new THREE.MeshBasicMaterial({ map: sunTex }));
  sun.position.set(-120, 55, -200);
  scene.add(sun);
  // Corona layers
  [
    { r:sunR*1.35, op:.36, c:0xffcc33 }, { r:sunR*2.1,  op:.18, c:0xff9900 },
    { r:sunR*3.4,  op:.08, c:0xff6600 }, { r:sunR*5.5,  op:.035,c:0xff3300 },
    { r:sunR*9,    op:.014,c:0xff2200 }, { r:sunR*15,   op:.005,c:0xff1100 },
  ].forEach(({ r, op, c }) => {
    const m = new THREE.Mesh(new THREE.SphereGeometry(r,24,24), new THREE.MeshBasicMaterial({ color:c, transparent:true, opacity:op, depthWrite:false, blending:THREE.AdditiveBlending }));
    m.position.copy(sun.position); scene.add(m);
  });

  // ── LIGHTING ──────────────────────────────────────────
  const sunLight = new THREE.DirectionalLight(0xfff6e0, 2.4);
  sunLight.position.copy(sun.position);
  sunLight.castShadow = true;
  sunLight.shadow.mapSize.width  = 2048;
  sunLight.shadow.mapSize.height = 2048;
  scene.add(sunLight);
  scene.add(new THREE.AmbientLight(0x030510, 1.2));
  const fill = new THREE.DirectionalLight(0x1835a0, .28);
  fill.position.set(50,-20,30); scene.add(fill);
  // Subtle earth-reflected light (limb fill)
  const earthFill = new THREE.PointLight(0x2244aa, .6, 60);
  earthFill.position.copy(earth.position); scene.add(earthFill);

  // ── MARS ──────────────────────────────────────────────
  const marsTex = tx(512, 256, (x, w, h) => {
    const g = x.createLinearGradient(0,0,w,h);
    g.addColorStop(0,'#aa3610'); g.addColorStop(.3,'#c04415'); g.addColorStop(.6,'#a23210'); g.addColorStop(1,'#aa3610');
    x.fillStyle = g; x.fillRect(0,0,w,h);
    // Valles Marineris
    x.strokeStyle = 'rgba(60,18,5,.75)'; x.lineWidth = 9; x.beginPath();
    x.moveTo(190,118); x.bezierCurveTo(275,113,368,122,455,115); x.stroke();
    // Craters
    for (let i = 0; i < 55; i++) {
      const cx = Math.random()*w, cy = Math.random()*h, r = 2+Math.random()*24;
      x.strokeStyle = `rgba(75,25,8,${.38+Math.random()*.42})`; x.lineWidth = 1.2;
      x.beginPath(); x.arc(cx,cy,r,0,Math.PI*2); x.stroke();
      x.fillStyle = `rgba(55,18,5,${.12+Math.random()*.18})`; x.fill();
    }
    // Olympus Mons halo
    x.fillStyle = 'rgba(145,65,28,.42)'; x.beginPath(); x.arc(120*w/512,95*h/256,38,0,Math.PI*2); x.fill();
    // Ice cap
    x.fillStyle = 'rgba(235,228,218,.72)'; x.beginPath(); x.ellipse(w/2,12,w*.22,14,0,0,Math.PI*2); x.fill();
    const lm = x.createRadialGradient(w/2,h/2,w*.28,w/2,h/2,w/2);
    lm.addColorStop(0,'rgba(0,0,0,0)'); lm.addColorStop(.72,'rgba(0,0,0,.08)'); lm.addColorStop(1,'rgba(0,0,0,.52)');
    x.fillStyle = lm; x.fillRect(0,0,w,h);
  });
  const mars = new THREE.Mesh(new THREE.SphereGeometry(2.3,64,64), new THREE.MeshPhongMaterial({ map:marsTex, shininess:5 }));
  mars.position.set(-32,18,-65); scene.add(mars);

  // ── JUPITER ───────────────────────────────────────────
  const jupTex = tx(2048, 1024, (x, w, h) => {
    const bg = x.createLinearGradient(0,0,0,h);
    bg.addColorStop(0,'#c8a272'); bg.addColorStop(.18,'#d4a85e'); bg.addColorStop(.38,'#b87640');
    bg.addColorStop(.52,'#c88e4e'); bg.addColorStop(.68,'#d4a85e'); bg.addColorStop(1,'#c8a272');
    x.fillStyle = bg; x.fillRect(0,0,w,h);
    [
      {y:20,h:11,c:'rgba(152,82,32,.62)'},{y:40,h:8,c:'rgba(208,158,88,.48)'},{y:55,h:17,c:'rgba(138,62,22,.68)'},
      {y:80,h:22,c:'rgba(198,128,58,.52)'},{y:110,h:26,c:'rgba(142,68,26,.64)'},{y:145,h:17,c:'rgba(192,122,52,.5)'},
      {y:170,h:13,c:'rgba(152,82,32,.58)'},{y:190,h:22,c:'rgba(132,58,20,.62)'},{y:218,h:15,c:'rgba(202,138,62,.47)'},
      {y:242,h:11,c:'rgba(152,82,32,.52)'},{y:258,h:19,c:'rgba(138,62,22,.62)'},{y:285,h:13,c:'rgba(202,138,62,.42)'},
    ].forEach(b => {
      x.fillStyle = b.c; x.beginPath(); x.moveTo(0, b.y*h/340);
      for (let i = 0; i <= w; i+=18) x.lineTo(i, (b.y+Math.sin(i*.018)*4)*h/340);
      for (let i = w; i >= 0; i-=18) x.lineTo(i, (b.y+b.h+Math.sin(i*.018+1.2)*4)*h/340);
      x.closePath(); x.fill();
    });
    // Great Red Spot
    x.fillStyle = 'rgba(182,62,32,.78)'; x.beginPath(); x.ellipse(w*.62,h*.375,34,23,0,0,Math.PI*2); x.fill();
    x.fillStyle = 'rgba(212,88,52,.62)'; x.beginPath(); x.ellipse(w*.62,h*.375,22,14,0,0,Math.PI*2); x.fill();
    x.fillStyle = 'rgba(238,118,72,.42)'; x.beginPath(); x.ellipse(w*.62,h*.375,11,7,0,0,Math.PI*2); x.fill();
    const ld = x.createRadialGradient(w/2,h/2,w*.28,w/2,h/2,w*.52);
    ld.addColorStop(0,'rgba(0,0,0,0)'); ld.addColorStop(.68,'rgba(0,0,0,.06)'); ld.addColorStop(1,'rgba(0,0,0,.5)');
    x.fillStyle = ld; x.fillRect(0,0,w,h);
  });
  const jR = 5.5;
  const jup = new THREE.Mesh(new THREE.SphereGeometry(jR,128,128), new THREE.MeshPhongMaterial({ map:jupTex, shininess:12 }));
  jup.position.set(-60,12,-110); scene.add(jup);

  // ── SATURN + RINGS ────────────────────────────────────
  const satTex = tx(1024, 512, (x, w, h) => {
    const bg = x.createLinearGradient(0,0,0,h);
    bg.addColorStop(0,'#d4b882'); bg.addColorStop(.5,'#c8a868'); bg.addColorStop(1,'#b89055');
    x.fillStyle = bg; x.fillRect(0,0,w,h);
    [18,34,52,72,92,112,132,152,172,192,212,232,252].forEach((y,i) => {
      x.fillStyle = `rgba(${148+i*4},${92+i*3},${36+i*2},${.28+i*.018})`; x.fillRect(0,y*h/512,w,(6+i*.5)*h/512);
    });
    const ld = x.createRadialGradient(w/2,h/2,w*.28,w/2,h/2,w/2);
    ld.addColorStop(0,'rgba(0,0,0,0)'); ld.addColorStop(.68,'rgba(0,0,0,.06)'); ld.addColorStop(1,'rgba(0,0,0,.48)');
    x.fillStyle = ld; x.fillRect(0,0,w,h);
  });
  const sR = 4.2;
  const sat = new THREE.Mesh(new THREE.SphereGeometry(sR,128,128), new THREE.MeshPhongMaterial({ map:satTex, shininess:14 }));
  sat.position.set(65,28,-145); scene.add(sat);

  // Ring texture with Cassini division
  const ringTex = tx(2048, 64, (x, w, h) => {
    const g = x.createLinearGradient(0,0,w,0);
    [
      [0,'rgba(0,0,0,0)'],[.03,'rgba(175,150,108,.12)'],[.08,'rgba(198,170,125,.38)'],[.15,'rgba(215,185,138,.62)'],
      [.22,'rgba(200,170,125,.52)'],[.3,'rgba(178,150,110,.3)'],[.37,'rgba(162,138,100,.12)'],[.43,'rgba(158,133,96,.06)'],
      [.49,'rgba(160,135,98,.1)'],[.55,'rgba(170,145,106,.22)'],[.63,'rgba(195,168,125,.68)'],[.74,'rgba(208,182,136,.84)'],
      [.83,'rgba(192,165,122,.64)'],[.91,'rgba(175,148,110,.4)'],[.96,'rgba(158,132,98,.2)'],[1,'rgba(0,0,0,0)'],
    ].forEach(([pos,c]) => g.addColorStop(pos,c));
    x.fillStyle = g; x.fillRect(0,0,w,h);
    // Cassini division
    x.fillStyle = 'rgba(0,0,0,.72)'; x.fillRect(w*.598,0,w*.038,h);
    // Enke gap
    x.fillStyle = 'rgba(0,0,0,.38)'; x.fillRect(w*.88,0,w*.018,h);
  });
  const ringGeo = new THREE.RingGeometry(sR*1.28, sR*2.65, 512);
  const rPos = ringGeo.attributes.position, rUV = ringGeo.attributes.uv;
  for (let i = 0; i < rPos.count; i++) {
    const v = new THREE.Vector3(rPos.getX(i),rPos.getY(i),rPos.getZ(i));
    rUV.setXY(i, (v.length()-sR*1.28)/(sR*2.65-sR*1.28), 0.5);
  }
  const ringMesh = new THREE.Mesh(ringGeo, new THREE.MeshBasicMaterial({ map:ringTex, side:THREE.DoubleSide, transparent:true, opacity:.92, depthWrite:false }));
  ringMesh.rotation.x = Math.PI*.4; ringMesh.rotation.z = .15;
  ringMesh.position.copy(sat.position); scene.add(ringMesh);

  // ── DISTANT PLANETS ───────────────────────────────────
  const ven = new THREE.Mesh(new THREE.SphereGeometry(1.3,48,48), new THREE.MeshPhongMaterial({ color:0xf0d080, emissive:new THREE.Color(.07,.045,0), shininess:45, transparent:true, opacity:.92 }));
  ven.position.set(-75,32,-115); scene.add(ven);
  const uranus = new THREE.Mesh(new THREE.SphereGeometry(2.4,48,48), new THREE.MeshPhongMaterial({ color:0x80e8e0, shininess:22, transparent:true, opacity:.88 }));
  uranus.position.set(90,-8,-185); scene.add(uranus);
  const neptune = new THREE.Mesh(new THREE.SphereGeometry(2.1,48,48), new THREE.MeshPhongMaterial({ color:0x2038d8, shininess:24, transparent:true, opacity:.78 }));
  neptune.position.set(-120,-20,-240); scene.add(neptune);

  // ── ASTEROID BELT ─────────────────────────────────────
  {
    const g = new THREE.BufferGeometry(), p = new Float32Array(1200*3);
    for (let i = 0; i < 1200; i++) {
      const t2 = Math.random()*Math.PI*2, r = 52+Math.random()*26;
      p[i*3]   = r*Math.cos(t2)-44;
      p[i*3+1] = (Math.random()-.5)*6+12;
      p[i*3+2] = r*Math.sin(t2)*.38-88;
    }
    g.setAttribute('position', new THREE.BufferAttribute(p, 3));
    scene.add(new THREE.Points(g, new THREE.PointsMaterial({ size:.085, color:0x998877, transparent:true, opacity:.38, depthWrite:false })));
  }

  // ── 4D TEMPORAL WAVE SHADER ───────────────────────────
  // Projects a 4th spatial dimension as a time-varying surface perturbation
  const w4dGeo  = new THREE.SphereGeometry(eR*1.08, 64, 64);
  const w4dMat  = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.FrontSide,
    uniforms: {
      time:    { value: 0 },
      w4:      { value: 0 },       // 4th dimension coordinate
      opacity: { value: .0 },
    },
    vertexShader: `
      uniform float time;
      uniform float w4;
      varying float vW4;
      varying vec3 vNorm;
      void main() {
        vNorm = normalize(normalMatrix * normal);
        // 4D rotation: project W axis into visible perturbation
        float phi = atan(position.y, position.x);
        float theta = asin(position.z / length(position));
        float w4proj = sin(phi * 3.0 + theta * 2.0 + time * .6) * cos(w4 * 1.8 + time * .35) * .012;
        vec3 displaced = position * (1.0 + w4proj);
        vW4 = w4proj;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(displaced, 1.0);
      }
    `,
    fragmentShader: `
      uniform float opacity;
      varying float vW4;
      varying vec3 vNorm;
      void main() {
        float fresnel = pow(1.0 - abs(dot(vNorm, vec3(0.0,0.0,1.0))), 3.2);
        float col4d = vW4 * 80.0 + 0.5;
        vec3 c = mix(vec3(.12,.48,1.), vec3(.8,.3,1.), col4d) * fresnel;
        gl_FragColor = vec4(c, fresnel * opacity * .55);
      }
    `,
  });
  const w4dMesh = new THREE.Mesh(w4dGeo, w4dMat);
  w4dMesh.position.copy(earth.position);
  scene.add(w4dMesh);

  // ── MOUSE TRACKING ────────────────────────────────────
  const mouse = { x:0, y:0, tx:0, ty:0, vx:0, vy:0 };
  document.addEventListener('mousemove', e => {
    mouse.tx = (e.clientX/innerWidth-.5)*2;
    mouse.ty = (e.clientY/innerHeight-.5)*2;
  });
  document.addEventListener('touchmove', e => {
    const t = e.touches[0];
    mouse.tx = (t.clientX/innerWidth-.5)*2;
    mouse.ty = (t.clientY/innerHeight-.5)*2;
  }, { passive: true });

  // ── ANIMATION LOOP ────────────────────────────────────
  let T = 0;
  const earthBase   = earth.position.clone();
  const moonOrbit   = { a: 11.5, speed: .006, phase: Math.PI*.3 };
  // 4D rotation state: two extra angle pairs for W-axis
  const ang4D = { xw: 0, yw: 0, zw: 0 };

  (function tick() {
    requestAnimationFrame(tick);
    T += .0042;

    // Spring-damped mouse
    mouse.vx += (mouse.tx - mouse.x) * .08;
    mouse.vy += (mouse.ty - mouse.y) * .08;
    mouse.vx *= .78; mouse.vy *= .78;
    mouse.x  += mouse.vx; mouse.y += mouse.vy;

    // 4D coordinates evolve independently — creating "hyperspin"
    ang4D.xw += .0007;
    ang4D.yw += .00045 + mouse.vx * .004;
    ang4D.zw += .00032 + mouse.vy * .003;
    // W-projection: 4D point rotated onto visible 3D manifold
    const w4val = Math.sin(ang4D.xw) * Math.cos(ang4D.yw) + Math.cos(ang4D.zw) * .4;

    // 4D wave mesh — subtle ripple effect synced to 4D rotation
    w4dMat.uniforms.time.value    = T;
    w4dMat.uniforms.w4.value      = w4val;
    w4dMat.uniforms.opacity.value = .18 + Math.abs(w4val) * .22;
    w4dMesh.position.copy(earth.position);
    w4dMesh.rotation.y = earth.rotation.y * .6;

    // Earth spin — cursor-responsive
    const mouseMag  = Math.sqrt(mouse.vx*mouse.vx + mouse.vy*mouse.vy);
    const earthSpin = .0014 + mouseMag * 1.8;
    earth.rotation.y += earthSpin;
    // 4D tilt bleeds into Earth's visible orientation
    earth.rotation.x += (mouse.y * .18 + Math.sin(ang4D.yw) * .02 - earth.rotation.x) * .035;
    earth.rotation.z  = .4 + mouse.x * .08 + Math.sin(ang4D.xw * .7) * .015;

    // Clouds drift independently
    clouds.rotation.y = earth.rotation.y * .98 + T * .0004;
    clouds.rotation.x = earth.rotation.x;
    clouds.rotation.z = earth.rotation.z;

    // Terminator tracks sun direction
    terminator.position.copy(earth.position);

    // Moon orbits Earth (4D perturbation adds subtle inclination wobble)
    moonOrbit.phase += moonOrbit.speed;
    const moonInc  = Math.sin(ang4D.zw * 1.6) * .18; // W-axis projected inclination
    moon.position.set(
      earthBase.x + Math.cos(moonOrbit.phase) * moonOrbit.a * .9,
      earthBase.y + Math.sin(moonOrbit.phase * .7) * 2.2 + moonInc * 2.8,
      earthBase.z + Math.sin(moonOrbit.phase) * moonOrbit.a * .55
    );
    moon.rotation.y = moonOrbit.phase * .28;

    // Earth fill light pulses with 4D rhythm
    earthFill.intensity = .5 + Math.sin(T * .8 + ang4D.xw) * .12;

    // Planet rotations
    mars.rotation.y    += .0018;
    jup.rotation.y     += .003;
    sat.rotation.y     += .0022;
    ringMesh.rotation.z += .00018;
    ven.rotation.y     += .0008;
    uranus.rotation.y  += .0014;
    neptune.rotation.y += .001;
    sun.rotation.y     += .0003;

    // Sun pulsation — more complex waveform
    const sp = 1 + Math.sin(T*.88)*.016 + Math.sin(T*2.28)*.007 + Math.sin(T*5.1)*.003;
    sun.scale.setScalar(sp);

    // Scene parallax — 4D adds subtle extra-dimensional drift
    const px4 = mouse.x*.014 + Math.sin(ang4D.yw) * .004;
    const py4 = -mouse.y*.009 + Math.cos(ang4D.xw) * .003;
    scene.rotation.y += (px4 - scene.rotation.y) * .018;
    scene.rotation.x += (py4 - scene.rotation.x) * .018;

    // Camera — slow breathing + 4D resonance
    camera.position.z = 42 + Math.sin(T*.35)*.5 + Math.sin(T*.11 + ang4D.zw)*.18;
    camera.position.y = Math.sin(T*.09) * .08;

    renderer.render(scene, camera);
  })();

  window.addEventListener('resize', () => {
    camera.aspect = innerWidth / innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(innerWidth, innerHeight);
  });
})();

// ═══════════════════════════════════════════
// ICONS & MODAL
// ═══════════════════════════════════════════
const ICON_HTML = {
  saweria:   (d) => d.custom_icon
    ? `<img src="${d.custom_icon}" style="width:38px;height:38px;object-fit:contain;display:block;"/>`
    : `<svg width="38" height="38" viewBox="0 0 24 24" fill="none"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="#ff6b6b"/></svg>`,
  instagram: (d) => `<svg width="34" height="34" viewBox="0 0 24 24"><defs><linearGradient id="igM" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#f09433"/><stop offset="50%" stop-color="#dc2743"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs><path fill="url(#igM)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>`,
  github:    (d) => `<svg width="30" height="30" viewBox="0 0 24 24" fill="rgba(160,191,255,0.9)"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>`,
  custom:    (d) => d.custom_icon
    ? `<img src="${d.custom_icon}" style="width:38px;height:38px;object-fit:contain;display:block;"/>`
    : `<svg width="30" height="30" viewBox="0 0 24 24" fill="rgba(160,191,255,0.9)"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>`,
};

let currentUrl = '';
function openModal(idx) {
  const d = LINKS[idx]; currentUrl = d.url;
  document.getElementById('mIco').innerHTML    = (ICON_HTML[d.type] || ICON_HTML.custom)(d);
  document.getElementById('mTitle').textContent  = d.label;
  document.getElementById('mDomain').textContent = d.domain;
  document.getElementById('mSaw').style.display  = d.type === 'saweria' ? 'block' : 'none';
  document.getElementById('mDesc').textContent   = d.desc;
  document.getElementById('btnLabel').textContent = d.btn;
  document.getElementById('overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow = '';
}
function bgClose(e) { if (e.target === document.getElementById('overlay')) closeModal(); }
function goLink()   { window.open(currentUrl,'_blank','noopener,noreferrer'); closeModal(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>`