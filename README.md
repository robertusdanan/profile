# Linktree — Static Site (GitHub Pages)

Website linktree pribadi berbasis HTML + JS murni, di-deploy ke GitHub Pages.  
Data profil & link diambil langsung dari Supabase di browser.  
**API key tidak pernah di-commit ke repo** — disimpan sebagai GitHub Actions Secrets.

---

## Struktur File

```
├── index.html           ← Halaman utama (HTML + CSS + JS)
├── config.template.js   ← Template config (placeholder, aman di-commit)
├── config.js            ← ⚠️ Di-generate GitHub Actions, ada di .gitignore
├── .gitignore           ← Mencegah config.js ter-commit
├── .github/
│   └── workflows/
│       └── deploy.yml   ← GitHub Actions: inject secrets + deploy ke Pages
└── README.md
```

---

## Setup Deploy ke GitHub Pages

### Langkah 1 — Buat Repository di GitHub

1. Buka [github.com/new](https://github.com/new)
2. Beri nama repo (contoh: `linktree` atau `robertusdanan.github.io`)
3. Set **Public**
4. Klik **Create repository**

### Langkah 2 — Push kode ke GitHub

```bash
cd path/ke/folder/ini

git init
git add .
git commit -m "init: linktree static site"
git branch -M main
git remote add origin https://github.com/USERNAME/REPO_NAME.git
git push -u origin main
```

> Ganti `USERNAME` dan `REPO_NAME` sesuai akun & nama repo kamu.

### Langkah 3 — Tambahkan Secrets di GitHub

1. Buka repo di GitHub → **Settings** → **Secrets and variables** → **Actions**
2. Klik **New repository secret**, tambahkan dua secret berikut:

| Name | Value |
|------|-------|
| `SUPABASE_URL` | URL project Supabase kamu (contoh: `https://abcxyz.supabase.co`) |
| `SUPABASE_ANON` | Anon/public key dari Supabase |

> ⚠️ Secret ini **tidak pernah terlihat** di kode, log Actions, maupun history git.

### Langkah 4 — Aktifkan GitHub Pages

1. Buka **Settings** → **Pages**
2. Di bagian **Source**, pilih **Deploy from a branch**
3. Branch: `gh-pages` | Folder: `/ (root)`
4. Klik **Save**

### Langkah 5 — Trigger Deploy

Setiap `git push` ke branch `main` akan otomatis:
1. Men-generate `config.js` dengan secret yang di-inject
2. Mendeploy ke branch `gh-pages`
3. Site live di: `https://USERNAME.github.io/REPO_NAME/`

Atau trigger manual: **Actions** → **Deploy to GitHub Pages** → **Run workflow**

---

## Cara Kerja Keamanan API Key

```
Kode di repo (public):        config.template.js  ← hanya berisi PLACEHOLDER
                               .gitignore          ← config.js diabaikan git

GitHub Actions (private):     Secrets.SUPABASE_URL & SUPABASE_ANON
                               ↓ di-inject saat build via sed
                               config.js (hanya ada di artifact deploy)
                               ↓
                               di-push ke branch gh-pages (bukan main!)

Browser pengunjung:            Mengunduh config.js dari gh-pages
                               window.APP_CONFIG berisi nilai asli
                               Fetch Supabase langsung dari browser
```

API key memang terbaca di browser (karena ini anon key Supabase yang memang untuk client-side), tapi **tidak ada di source code repo yang bisa di-fork/clone** siapapun.

---

## Pengembangan Lokal

Untuk test lokal tanpa deploy:

1. Salin `config.template.js` → `config.js`
2. Isi nilai SUPABASE_URL dan SUPABASE_ANON yang asli di `config.js`
3. Jalankan server lokal:
   ```bash
   npx serve .
   # atau
   python3 -m http.server 8080
   ```
4. Buka `http://localhost:8080`

> `config.js` sudah ada di `.gitignore`, jadi tidak akan ter-commit.

---

## Update Konten

Semua konten (nama, avatar, link) dikelola melalui Supabase dashboard — tidak perlu edit kode.  
Hanya push ke `main` jika ada perubahan tampilan/fitur.
