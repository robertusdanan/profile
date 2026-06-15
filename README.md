# 🌌 robertusdanan — Profile Page

> **Creator · Developer · Dreamer**

Halaman profil/linktree personal dengan latar belakang tata surya 3D interaktif, dibangun menggunakan Three.js murni dan ditenagai data dinamis dari Supabase.

🔗 **Live:** [robertusdanan.github.io](https://robertusdanan.github.io/)

---

## ✨ Fitur

- **Tata Surya 3D** — Animasi real-time dengan Bumi, Bulan, Matahari, dan semua planet tata surya menggunakan Three.js r128
- **Latar Bintang Berlapis** — Star field multi-layer dengan Milky Way backdrop
- **Data Dinamis via Supabase** — Profil, avatar, dan link card dimuat dari database; fallback statis tersedia jika koneksi gagal
- **Konfig Aman via GitHub Actions** — API key tidak pernah masuk ke repo; di-inject saat deploy melalui secret
- **Desain Glassmorphism** — Kartu profil dengan efek blur, gradient, dan animasi CSS halus
- **Responsif Mobile** — Layout dan posisi planet menyesuaikan viewport kecil secara otomatis
- **Modal Konfirmasi Link** — Setiap tautan dibuka lewat modal dengan preview sebelum redirect
- **Open Graph / Twitter Card** — Meta tag siap pakai untuk preview di WhatsApp, Twitter, dan Facebook

---

## 🛠️ Tech Stack

| Layer | Teknologi |
|---|---|
| Rendering 3D | [Three.js r128](https://threejs.org/) |
| Database | [Supabase](https://supabase.com/) (PostgreSQL + Storage) |
| Hosting | GitHub Pages |
| CI/CD | GitHub Actions |
| Font | Inter · Sora · JetBrains Mono (Google Fonts) |

---

## 📁 Struktur File

```
profile/
├── index.html           # Halaman utama (UI + Three.js + logika app)
├── admin.html           # Panel admin (manajemen profil & link)
├── config.js            # ⚠️  Di-generate GitHub Actions — JANGAN commit
├── config.template.js   # Template konfigurasi dengan placeholder
├── favicon.ico          # Favicon
└── og.jpg               # Open Graph image untuk preview sosmed
```

---

## ⚙️ Cara Deploy

### 1. Fork / Clone repo ini

```bash
git clone https://github.com/robertusdanan/robertusdanan.github.io.git
```

### 2. Buat project di Supabase

Buat dua tabel:

**Tabel `profile`**
| Kolom | Tipe |
|---|---|
| `name` | text |
| `handle` | text |
| `tagline` | text |
| `avatar_type` | text |
| `avatar_data` | text (base64) |

**Tabel `link_cards`**
| Kolom | Tipe |
|---|---|
| `label` | text |
| `sub` | text |
| `url` | text |
| `domain` | text |
| `description` | text |
| `btn_label` | text |
| `icon_type` | text |
| `icon_data` | text |
| `icon_mime` | text |
| `is_active` | boolean |
| `sort_order` | integer |

### 3. Tambahkan GitHub Secrets

Di repo → **Settings → Secrets and variables → Actions**, tambahkan:

| Secret | Keterangan |
|---|---|
| `SUPABASE_URL` | URL project Supabase kamu |
| `SUPABASE_ANON` | Anon/public key Supabase |
| `SUPABASE_SERVICE_ROLE` | Service role key (untuk admin panel) |

### 4. Aktifkan GitHub Pages

Di **Settings → Pages**, pilih source: `GitHub Actions`.

### 5. Push & deploy

```bash
git push origin main
```

GitHub Actions akan otomatis me-replace placeholder di `config.template.js` dan men-deploy ke GitHub Pages.

---

## 🔒 Keamanan

- `config.js` (berisi API key nyata) masuk `.gitignore` dan **tidak pernah di-commit**
- Key di-inject hanya saat build oleh GitHub Actions menggunakan repository secrets
- Supabase Row Level Security (RLS) sangat disarankan untuk tabel `profile` dan `link_cards`

---

## 🌐 Kontak

- **Instagram:** [@robertusdanan](https://www.instagram.com/robertusdanan)
- **GitHub:** [github.com/robertusdanan](https://github.com/robertusdanan)
- **Support:** [saweria.co/robertusdanan](https://saweria.co/robertusdanan)

---

<p align="center">
  <sub>Made with ❤️ by Robertus Danan</sub>
</p>
