# 🌌 robertusdanan — Profile Page

> **Creator · Developer · Dreamer**

Halaman profil personal dengan latar belakang tata surya 3D interaktif, dibangun menggunakan Three.js murni dan ditenagai data dinamis dari Supabase.

🔗 **Live:** [robertusdanan.github.io](https://robertusdanan.github.io/)

---

## ✨ Fitur

- **Tata Surya 3D** — Animasi real-time Matahari, planet, dan Bulan dengan Three.js r128
- **Latar Bintang Berlapis** — Star field multi-layer dengan Milky Way backdrop
- **100% GitHub** — Tanpa database, tanpa secret, tanpa server. Data = file JSON, gambar = file di repo
- **Admin Panel** — `/admin.html` mengedit profil & link lewat GitHub API (setiap perubahan = 1 commit, riwayat lengkap di git)
- **Desain Glassmorphism** — Kartu profil dengan blur, gradient, dan animasi CSS halus
- **Responsif Mobile** — Layout dan posisi planet menyesuaikan viewport kecil
- **Open Graph / Twitter Card** — Meta tag siap pakai untuk preview sosmed

---

## 🛠️ Tech Stack

| Layer | Teknologi |
|---|---|
| Rendering 3D | [Three.js r128](https://threejs.org/) |
| Data | File JSON di `data/` |
| Aset | Gambar di `assets/` (avatar, ikon, tekstur planet) |
| Hosting + CI/CD | GitHub Pages + GitHub Actions |
| Admin | GitHub Contents API (fine-grained token) |

---

## 📁 Struktur File

```
profile/
├── index.html              # Halaman utama (UI + Three.js)
├── admin.html              # Panel admin (commit lewat GitHub API)
├── data/
│   ├── profile.json        # nama, handle, tagline, avatar
│   └── links.json          # daftar link card
├── assets/
│   ├── avatar-*.jpg        # foto profil
│   ├── icons/              # ikon link kustom
│   └── textures/           # sun.jpg, earth.jpg, ... saturn_ring.png
├── scripts/
│   └── migrate-from-supabase.mjs   # sekali pakai (boleh dihapus setelah migrasi)
├── favicon.ico
├── og.jpg
└── .github/workflows/deploy.yml
```

---

## ⚙️ Setup

1. **Pages**: Settings → Pages → Source: `GitHub Actions`
2. **Push** ke `main` — workflow menyalin `index.html`, `admin.html`, `data/`, `assets/` ke Pages.
3. **Token admin** (sekali, di perangkat Anda):
   GitHub → Settings → Developer settings → *Fine-grained tokens* → Generate:
   - Repository access: **Only select repositories** → repo ini
   - Permissions: **Contents → Read and write**
   - Buka `/admin.html`, tempel token + isi `pemilik/repo`.

Perubahan dari admin ter-deploy ±1 menit setelah commit.

### Migrasi dari Supabase (sekali jalan, butuh Node.js 18+)

```bash
# 1. uji dulu tanpa menulis apa pun
SUPABASE_URL=https://xxxx.supabase.co SUPABASE_ANON=eyJ... node scripts/migrate-from-supabase.mjs --dry-run
# 2. jalankan sungguhan
SUPABASE_URL=https://xxxx.supabase.co SUPABASE_ANON=eyJ... node scripts/migrate-from-supabase.mjs
git add -A && git commit -m "migrate: Supabase → GitHub" && git push
```

PowerShell: `$env:SUPABASE_URL="https://xxxx.supabase.co"; $env:SUPABASE_ANON="eyJ..."; node scripts/migrate-from-supabase.mjs`

Skrip menyalin tabel `profile` & `link_cards`, avatar, dan ikon link (bucket `media`). File di `assets/` tidak pernah ditimpa (termasuk `assets/textures/`), dan aman dijalankan ulang.

---

## 🔒 Keamanan

- Tidak ada lagi key rahasia di situs. Admin Panel hanya bekerja jika Anda memasukkan token pribadi, yang disimpan di browser Anda dan tidak pernah di repo.
- Gunakan token **fine-grained, hanya untuk repo ini**, dengan masa berlaku terbatas.
- Semua isi repo bersifat publik jika repo publik — jangan simpan data pribadi di `data/`.

---

## 🌐 Kontak

- **Instagram:** [@robertusdanan](https://www.instagram.com/robertusdanan)
- **GitHub:** [github.com/robertusdanan](https://github.com/robertusdanan)
- **Support:** [saweria.co/robertusdanan](https://saweria.co/robertusdanan)

---

<p align="center">
  <sub>Made with ❤️ by Robertus Danan</sub>
</p>
