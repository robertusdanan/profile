# 🌌 robertusdanan — Profile Page

> **Creator · Developer · Dreamer**

Halaman profil personal dengan latar belakang tata surya 3D interaktif, dibangun menggunakan Three.js murni dan ditenagai data dari file JSON di repo ini.

🔗 **Live:** [robertusdanan.github.io](https://robertusdanan.github.io/)

---

## ✨ Fitur

- **Tata Surya 3D** — Animasi real-time Matahari, planet, dan Bulan dengan Three.js r128
- **Latar Bintang Berlapis** — Star field multi-layer dengan Milky Way backdrop
- **100% GitHub** — Tanpa database, tanpa secret, tanpa server. Data = file JSON, gambar = file di repo
- **Update lewat GitHub** — edit `data/*.json` langsung di GitHub (atau git); tiap commit otomatis ter-deploy
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

---

## 📁 Struktur File

```
profile/
├── index.html              # Halaman utama (UI + Three.js)
├── data/
│   ├── profile.json        # nama, handle, tagline, avatar
│   └── links.json          # daftar link card
├── assets/
│   ├── avatar.jpg          # foto profil
│   ├── icons/              # ikon link kustom
│   └── textures/           # sun.jpg, earth.jpg, ... saturn_ring.png
├── favicon.ico
├── og.jpg
└── .github/workflows/deploy.yml
```

---

## ⚙️ Setup (sekali)

Settings → Pages → Source: **GitHub Actions**, lalu push ke `main`. Selesai — tidak ada secret, database, maupun server.

---

## ✏️ Cara memperbarui

Buka file di GitHub → ikon ✏️ (Edit) → **Commit changes**. Situs ter-update ±1 menit.

**Ubah profil** — edit `data/profile.json`:
```json
{ "name": "Robertus Danan", "handle": "@robertusdanan", "tagline": "Creator · VibeCoder · TraderBot", "avatar": "avatar.jpg" }
```
`avatar` = nama file di folder `assets/` (isi `null` untuk memakai inisial).

**Ubah / tambah link** — edit `data/links.json`. Satu link:
```json
{
  "id": 8,
  "label": "Judul",
  "sub": "Teks kecil di bawah judul",
  "url": "https://contoh.com",
  "domain": "contoh.com",
  "description": "Teks di jendela konfirmasi",
  "btn_label": "Buka Halaman",
  "icon_type": "custom",
  "icon": "icons/8.png",
  "is_active": true,
  "sort_order": 5
}
```
- `id` harus unik. Urutan tampil mengikuti `sort_order` (kecil → besar).
- `icon_type`: `saweria`, `instagram`, `github` (ikon bawaan, `icon` isi `null`) atau `custom` (pakai gambar di `icon`).
- `is_active: false` = disembunyikan tanpa dihapus.
- Hati-hati koma antar-item JSON (item terakhir tanpa koma). Kalau JSON salah, situs memakai data cadangan.

**Ganti foto / ikon** — di GitHub: buka folder `assets/` (atau `assets/icons/`) → **Add file → Upload files** → commit. Lalu sesuaikan nama file di JSON. Pakai nama file baru (mis. `avatar-2.jpg`) agar tidak tertahan cache browser. Ukuran yang disarankan: avatar ≤ 500 KB, ikon ≤ 100 KB.

---

## 🌐 Kontak

- **Instagram:** [@robertusdanan](https://www.instagram.com/robertusdanan)
- **GitHub:** [github.com/robertusdanan](https://github.com/robertusdanan)
- **Support:** [saweria.co/robertusdanan](https://saweria.co/robertusdanan)

---

<p align="center">
  <sub>Made with ❤️ by Robertus Danan</sub>
</p>
