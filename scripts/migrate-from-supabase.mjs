#!/usr/bin/env node
// Migrasi sekali jalan: Supabase → file di repo ini.
//
// Cara pakai (dari folder root repo, butuh Node.js 18+):
//   macOS/Linux : SUPABASE_URL=https://xxxx.supabase.co SUPABASE_ANON=eyJ... node scripts/migrate-from-supabase.mjs
//   PowerShell  : $env:SUPABASE_URL="https://xxxx.supabase.co"; $env:SUPABASE_ANON="eyJ..."; node scripts/migrate-from-supabase.mjs
//
// Opsi:
//   --dry-run   hanya tampilkan apa yang akan dilakukan, tanpa menulis file apa pun
//
// Aturan aman:
//   • File di assets/ TIDAK PERNAH ditimpa (kalau sudah ada → dilewati). Tekstur planet tidak disentuh.
//   • data/profile.json & data/links.json diisi dari Supabase; versi lama dicadangkan ke *.bak.
//   • Hanya butuh anon key (cukup untuk membaca data publik). Service-role key TIDAK diperlukan.
import { mkdir, writeFile, readFile, rename, access } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT    = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DRY     = process.argv.includes('--dry-run');
const BASE    = (process.env.SUPABASE_URL || '').trim().replace(/\/+$/, '');
const ANON    = (process.env.SUPABASE_ANON || '').trim();
const HEADERS = { apikey: ANON, Authorization: `Bearer ${ANON}`, Accept: 'application/json' };
const EXT     = { jpeg: 'jpg', jpg: 'jpg', png: 'png', webp: 'webp', gif: 'gif', svg: 'svg', 'svg+xml': 'svg' };
const stats   = { written: [], skipped: [], missing: [] };

if (!BASE || !ANON) {
  console.error('SUPABASE_URL dan SUPABASE_ANON belum diisi. Lihat petunjuk di bagian atas file ini.');
  process.exit(1);
}
if (typeof fetch !== 'function') { console.error('Butuh Node.js 18 atau lebih baru.'); process.exit(1); }

const exists = p => access(p).then(() => true, () => false);
const safe   = s => String(s).replace(/[^\w.-]+/g, '_');

async function rest(table, query) {
  const r = await fetch(`${BASE}/rest/v1/${table}?${query}`, { headers: HEADERS });
  if (!r.ok) {
    const hint = r.status === 401 || r.status === 403 ? ' (anon key salah / RLS melarang baca)' : '';
    throw new Error(`Tabel "${table}": HTTP ${r.status}${hint}`);
  }
  return r.json();
}

async function download(bucket, objectPath) {
  const enc = objectPath.replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/');
  const r = await fetch(`${BASE}/storage/v1/object/public/${bucket}/${enc}`);
  return r.ok ? Buffer.from(await r.arrayBuffer()) : null;
}

// Simpan aset; tidak pernah menimpa file yang sudah ada.
async function saveAsset(rel, buf) {
  const abs = resolve(ROOT, 'assets', rel);
  if (await exists(abs)) { stats.skipped.push(`assets/${rel}`); return false; }
  if (!DRY) { await mkdir(dirname(abs), { recursive: true }); await writeFile(abs, buf); }
  stats.written.push(`assets/${rel}`);
  return true;
}

// Simpan JSON data; versi lama dicadangkan ke .bak
async function saveData(file, obj) {
  const abs = resolve(ROOT, 'data', file);
  if (!DRY) {
    await mkdir(dirname(abs), { recursive: true });
    if (await exists(abs)) await rename(abs, abs + '.bak');
    await writeFile(abs, JSON.stringify(obj, null, 2) + '\n');
  }
  stats.written.push(`data/${file}`);
}

console.log(DRY ? '— DRY RUN: tidak ada file yang ditulis —\n' : '');

try {

// ── 1. Profil ────────────────────────────────────────────
const [p] = await rest('profile', 'select=*&limit=1');
if (!p) console.warn('! Tabel profile kosong — profile.json diisi nilai kosong.');
const profile = { name: p?.name || '', handle: p?.handle || '', tagline: p?.tagline || '', avatar: null };
if (p?.avatar_data && p.avatar_data !== 'empty') {
  const rel = `avatar.${EXT[(p.avatar_type || 'jpeg').toLowerCase()] || 'jpg'}`;
  await saveAsset(rel, Buffer.from(p.avatar_data, 'base64'));
  profile.avatar = rel;                       // tetap menunjuk ke file (baru atau yang sudah ada)
}
await saveData('profile.json', profile);

// ── 2. Link cards ────────────────────────────────────────
const rows  = await rest('link_cards', 'select=*&order=sort_order.asc');
const links = [];
for (const r of rows) {
  let icon = null;
  if (r.icon_type === 'custom' && r.icon_data) {
    if (r.icon_data.length < 256) {                         // path file di bucket "media"
      const name = safe(r.icon_data.split('/').pop());
      const buf  = await download('media', r.icon_data);
      if (buf) { icon = `icons/${r.id}-${name}`; await saveAsset(icon, buf); }
      else     { stats.missing.push(`media/${r.icon_data} (link "${r.label}")`); }
    } else {                                                // base64 tersimpan langsung di tabel
      icon = `icons/${r.id}.${EXT[(r.icon_mime || 'png').toLowerCase()] || 'png'}`;
      await saveAsset(icon, Buffer.from(r.icon_data, 'base64'));
    }
  }
  links.push({
    id: r.id, label: r.label || '', sub: r.sub || '', url: r.url || '', domain: r.domain || '',
    description: r.description || '', btn_label: r.btn_label || 'Buka Halaman',
    icon_type: r.icon_type || 'custom', icon,
    is_active: r.is_active !== false, sort_order: r.sort_order ?? 0,
  });
}
await saveData('links.json', links);

// ── Ringkasan ────────────────────────────────────────────
console.log(`Profil : ${profile.name || '(kosong)'} ${profile.handle}`);
console.log(`Link   : ${links.length} (${links.filter(l => l.is_active).length} aktif)\n`);
console.log(`${DRY ? 'Akan ditulis' : 'Ditulis'} (${stats.written.length}):`); stats.written.forEach(f => console.log('  +', f));
if (stats.skipped.length) { console.log(`Dilewati karena sudah ada (${stats.skipped.length}):`); stats.skipped.forEach(f => console.log('  =', f)); }
if (stats.missing.length) { console.log(`PERINGATAN — tidak ditemukan di Supabase (${stats.missing.length}):`); stats.missing.forEach(f => console.log('  !', f)); }
console.log(DRY ? '\nJalankan lagi tanpa --dry-run untuk menulis.' :
  '\nSelesai. Periksa hasilnya, lalu:\n  git add -A && git commit -m "migrate: Supabase → GitHub" && git push');
} catch (e) {
  console.error('\nGAGAL: ' + e.message + '\nTidak ada perubahan yang dianggap selesai — perbaiki masalahnya lalu jalankan ulang (aman diulang).');
  process.exit(1);
}

