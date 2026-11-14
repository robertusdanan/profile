#!/usr/bin/env node
// Salin SEMUA data & aset dari Supabase ke folder repo (sekali jalan).
//
//   SUPABASE_URL=https://xxxx.supabase.co SUPABASE_ANON=eyJ... node scripts/migrate-from-supabase.mjs
//
// Hanya butuh anon key (data profile/link_cards & bucket publik memang sudah publik).
// Hasil: data/profile.json, data/links.json, assets/avatar.*, assets/icons/*, assets/textures/*
// Setelah selesai: git add -A && git commit && git push
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';

const URL_  = (process.env.SUPABASE_URL || '').replace(/\/$/, '');
const ANON  = process.env.SUPABASE_ANON || '';
if (!URL_ || !ANON) { console.error('Set SUPABASE_URL dan SUPABASE_ANON dulu.'); process.exit(1); }

const H = { apikey: ANON, Authorization: `Bearer ${ANON}` };
const EXT = { jpeg: 'jpg', jpg: 'jpg', png: 'png', webp: 'webp', 'svg+xml': 'svg', svg: 'svg', gif: 'gif' };

async function save(path, buf) { await mkdir(dirname(path), { recursive: true }); await writeFile(path, buf); }
async function rest(table, q = '') {
  const r = await fetch(`${URL_}/rest/v1/${table}?${q}`, { headers: H });
  if (!r.ok) throw new Error(`${table}: HTTP ${r.status}`);
  return r.json();
}
async function bucketFile(bucket, name) {
  const r = await fetch(`${URL_}/storage/v1/object/public/${bucket}/${name}`);
  return r.ok ? Buffer.from(await r.arrayBuffer()) : null;
}

// ── profil ──
const [p] = await rest('profile', 'select=*&limit=1');
const profile = { name: p?.name || '', handle: p?.handle || '', tagline: p?.tagline || '', avatar: null };
if (p?.avatar_data && p.avatar_data !== 'empty') {
  const ext = EXT[p.avatar_type] || 'jpg';
  profile.avatar = `avatar.${ext}`;
  await save(`assets/${profile.avatar}`, Buffer.from(p.avatar_data, 'base64'));
  console.log('✓ avatar →', profile.avatar);
}
await save('data/profile.json', JSON.stringify(profile, null, 2) + '\n');
console.log('✓ data/profile.json');

// ── link ──
const rows = await rest('link_cards', 'select=*&order=sort_order.asc');
const links = [];
for (const r of rows) {
  let icon = null;
  if (r.icon_type === 'custom' && r.icon_data) {
    if (r.icon_data.length < 256) {                       // path di bucket "media"
      const buf = await bucketFile('media', r.icon_data.replace(/^\//, ''));
      if (buf) { icon = `icons/${r.id}-${r.icon_data.split('/').pop()}`; await save(`assets/${icon}`, buf); }
      else console.warn('! ikon tidak ditemukan di bucket media:', r.icon_data);
    } else {                                              // base64 inline
      icon = `icons/${r.id}.${EXT[r.icon_mime] || 'png'}`;
      await save(`assets/${icon}`, Buffer.from(r.icon_data, 'base64'));
    }
  }
  links.push({
    id: r.id, label: r.label || '', sub: r.sub || '', url: r.url || '', domain: r.domain || '',
    description: r.description || '', btn_label: r.btn_label || 'Buka Halaman',
    icon_type: r.icon_type || 'custom', icon, is_active: r.is_active !== false, sort_order: r.sort_order ?? 0,
  });
}
await save('data/links.json', JSON.stringify(links, null, 2) + '\n');
console.log(`✓ data/links.json (${links.length} link)`);

// ── tekstur planet (bucket "textures") ──
const TEX = ['sun.jpg','mercury.jpg','venus.jpg','earth.jpg','mars.jpg','jupiter.jpg','saturn.jpg','uranus.jpg','neptune.jpg','moon.jpg','saturn_ring.png'];
for (const t of TEX) {
  const buf = await bucketFile('textures', t);
  if (buf) { await save(`assets/textures/${t}`, buf); console.log('✓ tekstur', t, `(${(buf.length/1024).toFixed(0)} KB)`); }
  else console.warn('! tekstur tidak ada:', t);
}
console.log('\nSelesai. Cek hasilnya lalu: git add -A && git commit -m "migrate: Supabase → GitHub" && git push');
