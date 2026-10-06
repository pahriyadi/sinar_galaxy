# Panduan Proyek Sinar Galaxy (sinargalaxy.my.id)

## 1. Environment & Server
- Sistem aktif (live) di server hosting cPanel `sinargalaxy.my.id`.
- Kode lokal berada di `c:\xampp\htdocs\sinargalaxy.my.id`.
- Remote GitHub: `https://github.com/pahriyadi/sinar_galaxy.git` (branch `main`).

## 2. Aturan Perubahan & Deployment
- Setiap pembaruan kode harus menjaga kompatibilitas dengan PHP hosting dan database MySQL.
- Jangan pernah menyertakan file dump database (`*.sql` besar), file log (`err.txt`, `out.txt`), atau backup arsip ke dalam commit Git (selalu patuhi `.gitignore`).
- Alur deployment harian mengacu pada `PANDUAN_MASTER_DEPLOY_HOSTING_CPANEL.md`:
  - Di lokal: `git add .` -> `git commit -m "..."` -> `git push origin main`
  - Di cPanel Terminal: `git pull origin main`
