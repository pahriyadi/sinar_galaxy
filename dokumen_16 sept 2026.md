RANGKUMAN PEMBARUAN & PENAMBAHAN FITUR SISTEM
SINAR GALAXY TRAVEL (SGT) MANAGEMENT SYSTEM

Tanggal Pembaruan: 16 September 2026
Teknologi: PHP 8 Native, MySQL/MariaDB, AdminLTE 3, Bootstrap 4, Vanilla CSS, jQuery, AJAX


DAFTAR ISI
1. Modernisasi Desain & Antarmuka Pengguna (Paper White System)
2. Peningkatan Keamanan Aplikasi (Security Hardening)
3. Optimalisasi SEO & Landing Page
4. Master Data Jenis Barang & Tarif Paket (Ekspedisi Same Day)
5. Simulasi Denah Kursi & Kontrol Jam Keberangkatan (Role-Based)
6. Manajemen Hak Akses Berdasarkan Role & Cabang (RBAC)
7. Struktur File yang Dibuat & Dimodifikasi


1. MODERNISASI DESAIN & ANTARMUKA PENGGUNA (PAPER WHITE SYSTEM)

A. Efisiensi Tampilan Profil & Navigasi
- Penghapusan Duplikasi Profil: Menghilangkan tampilan profil ganda yang sebelumnya muncul sekaligus di sidebar dan topbar/navbar, sehingga tata letak dasbor menjadi lebih bersih, lega, dan efisien.
- Penyelarasan Definisi Bisnis: Kalimat dan terminologi operasional disesuaikan dengan standar operasional travel modern.

B. Konsep "Putih di Atas Kertas" (Paper White Design System)
Seluruh antarmuka admin dan dasbor mengikuti panduan universal:
- Latar Belakang: Putih bersih.
- Border Lembut: Garis abu-abu lembut yang tegas namun nyaman dipandang, menyerupai lembar kerja spreadsheet Excel.
- Flat / Tanpa Bayangan: Menghilangkan bayangan tebal dan efek gradien berlebihan untuk mempercepat proses rendering halaman.
- Warna Aksen Brand: Sentuhan hijau lembut sebagai penanda hover baris tabel, menu aktif, dan tombol aksi utama.
- Anti-Zebra Table: Menghilangkan striping belang-belang abu-abu, digantikan dengan garis tabel presisi dan hover highlight.

C. Standardisasi Rute & Responsivitas Mobile
- Standardisasi Rute Universal: Seluruh teks rute yang sebelumnya bertuliskan "Sumbawa - Mataram PP" telah diubah secara menyeluruh menjadi "Sumbawa - Mataram" agar rapi dan konsisten di seluruh sistem.
- Pembersihan Preloader: Menghapus background gambar dan efek animasi preloader saat refresh halaman sehingga proses pemuatan halaman terasa instan.
- Mobile-First Responsive: Seluruh tombol, formulir modal, dan denah kabin telah disesuaikan agar nyaman digunakan (touch-friendly) di perangkat layar sentuh smartphone Android dan iOS.


2. PENINGKATAN KEAMANAN APLIKASI (SECURITY HARDENING)

Menjelang proses publikasi/deployment ke server hosting produksi, telah dilakukan audit keamanan menyeluruh:

A. Proteksi File & Direktori Sensitif via .htaccess
- Blokir Ekstensi Berbahaya: File database dump, backup, log, dan konfigurasi (.sql, .bak, .backup, .env, .ini, .log, .md, .git) diblokir total dengan respon HTTP 403 Forbidden.
- Blokir Folder Internal: Direktori sistem /inc/, /galaxy_drive/backups/, dan /docs/ tidak dapat diakses langsung melalui browser URL.
- Disable Directory Browsing: Direktif Options -Indexes diaktifkan untuk mencegah pihak luar mengintip isi folder.

B. Implementasi 5 HTTP Security Headers
Setiap respon HTTP kini dilengkapi dengan header keamanan standar industri:
1. X-Content-Type-Options: nosniff (Mencegah serangan MIME-sniffing).
2. X-Frame-Options: SAMEORIGIN (Mencegah serangan Clickjacking dan pembajakan frame).
3. X-XSS-Protection: 1; mode=block (Mitigasi Cross-Site Scripting pada browser).
4. Referrer-Policy: strict-origin-when-cross-origin (Mencegah kebocoran data URL sensitif).
5. Permissions-Policy: geolocation=(), microphone=(), camera=() (Menonaktifkan fitur sensor perangkat yang tidak diperlukan).

C. Proteksi Session & Sanitasi Database
- Mitigasi Session Fixation: Menambahkan regenerasi ID sesi (session_regenerate_id) pada login.php setiap kali pengguna berhasil login.
- Sanitasi Pesan Error Database: Pesan die() pada inc/koneksi.php yang sebelumnya menampilkan rincian teknis database diganti dengan pesan umum yang aman, sementara error teknis dicatat secara tertutup ke file error_log.
- Audit Kode PHP: Seluruh 91 file PHP di codebase telah diaudit dengan syntax linter dengan hasil 0 Syntax Error.


3. OPTIMALISASI SEO & LANDING PAGE

A. Dominasi Mesin Pencari (Google Search Optimization)
Mengatasi kendala di mana pencarian "Sinar Galaxy Travel" sebelumnya lebih mengarahkan pengunjung ke akun media sosial dibanding website resmi:
- Meta Tags Lengkap: Penambahan meta title dinamis, meta description bertarget kata kunci "Travel Sumbawa Mataram Terbaik, Cepat, Antar Jemput Door to Door".
- Open Graph & Twitter Cards: Pratinjau link yang rapi dan profesional saat tautan website dibagikan ke WhatsApp, Facebook, dan media sosial.
- Structured Data (Schema.org): Mengidentifikasi bisnis secara resmi sebagai TravelAgency dan LocalBusiness dengan informasi jam operasional, rating bintang, wilayah layanan, dan kontak customer service.

B. URL Bersih (Clean URL) Tanpa Ekstensi .php
- Konfigurasi rewrite Apache yang portabel untuk localhost XAMPP maupun hosting cPanel. Seluruh tautan menu sistem dapat dibuka bersih tanpa akhiran .php.


4. MASTER DATA JENIS BARANG & TARIF PAKET (EKSPEDISI SAME DAY)

A. Master Barang & Tarif Standar
- Tabel Baru data_jenis_barang: Menyimpan daftar kategori barang kiriman beserta referensi biaya standar:
  * Dokumen / Surat
  * Paket Kecil / Sedang / Besar
  * Makanan / Kuliner
  * Pakaian / Baju
  * Elektronik / HP
  * Sembako, Koper, Stroller, dll.
- Katalog Publik Bersih: Pada tampilan landing page, katalog paket disajikan rapi tanpa nominal harga kaku dan telah dibersihkan dari kategori hewan/sangkar sesuai arahan operasional.

B. Integrasi Select2 & Pengisian Otomatis (Autofill)
- Dropdown jenis barang pada formulir pengiriman paket terintegrasi dengan pencarian Select2.
- Memilih jenis barang otomatis mengisi tarif ongkos kirim standar ke kolom biaya, namun kasir tetap memiliki keleluasaan mengubah nominal jika barang memiliki ukuran atau penanganan khusus.
- Kompatibilitas Penuh: Menjaga integritas dan keutuhan lebih dari 8.700 riwayat transaksi paket terdahulu.


5. SIMULASI DENAH KURSI & KONTROL JAM KEBERANGKATAN (ROLE-BASED)

Fitur utama untuk mempermudah mandor, pengawas, dan kasir dalam mengendalikan okupansi armada secara visual dan real-time:

A. Pusat Kontrol Kursi & Manifest Armada (kontrol_kursi.php)
- Akses Menu: OPERASIONAL -> Tiket & Paket -> Kontrol Kursi & Armada.
- Tombol Cepat Jam Keberangkatan (Quick Schedule Tabs):
  * Jadwal resmi SGT: 07:00, 08:00, 10:00, 14:00, 17:00, 20:00 WITA, serta opsi jam kustom.
  * Memilih jam langsung memperbarui denah kursi via AJAX seketika tanpa perlu memuat ulang halaman.
- Simulasi Visual Denah Kabin Berdasarkan Tipe Armada:
  1. Kelas VIP (9 Kursi): Konfigurasi 1-1-1 / 1-2 Executive Captain Seat dengan lorong tengah.
  2. Kelas EKONOMI (11 Kursi): Konfigurasi standar antarkota (baris depan supir, baris tengah 2 & 3 kursi, dan 5 kursi belakang).
  3. Kelas EKONOMI-PREMIO (15 Kursi): Konfigurasi 4 baris kabin panjang HiAce Premio.
- Indikator 4 Status Kursi:
  * Hijau (Tersedia): Kursi kosong, dapat langsung diklik untuk memesan tiket di kursi tersebut.
  * Abu-Abu / Merah (Terisi Lunas): Menampilkan nama penumpang, klik untuk membuka popup rincian tiket, nomor kontak, dan status bayar.
  * Oranye (Booking DP): Penanda kursi berstatus DP / Piutang agar kasir mengingatkan pelunasan sebelum naik travel.
  * Hijau Solid (Terpilih): Menandai kursi yang sedang aktif dipilih.
- Ringkasan Okupansi & Tabel Manifest: Menampilkan persentase keterisian kursi secara langsung dan daftar penumpang trip lengkap dengan tautan WhatsApp serta tombol cetak manifest perjalanan supir.

B. Pemilih Kursi Interaktif (Interactive Seat Picker) pada Formulir Tiket
- Tombol "Pilih Denah" disematkan tepat di samping kolom nomor kursi pada formulir tiket.
- Petugas kasir tidak lagi mengetik nomor kursi secara manual, melainkan langsung memilih kursi kosong dari jendela denah visual.
- Mencegah Kursi Ganda (Anti Double-Booking): Kursi yang sudah terisi otomatis terkunci sehingga tidak dapat dipilih oleh kasir lain pada jadwal yang sama.
- Terintegrasi Dua Arah: Mengklik kursi kosong pada halaman Kontrol Kursi akan langsung membuka form pemesanan dengan data jadwal dan nomor kursi yang terisi otomatis.

C. Backend API Real-Time (get_kursi_status.php)
- Menyajikan status keterisian setiap nomor kursi 1 sampai selesai secara akurat berdasarkan rentang toleransi waktu keberangkatan (±45 menit dari slot jadwal).


6. MANAJEMEN HAK AKSES BERDASARKAN ROLE & CABANG (RBAC)

Fitur kontrol kursi dan transaksi terhubung dengan peran pengguna dan cabang asal:

- Super Admin / Owner:
  Akses penuh tanpa batas. Dapat melihat, memesan tiket, serta mengatur kursi seluruh armada untuk semua rute (Sumbawa, Mataram, Bandara).

- Admin Cabang Sumbawa:
  Fokus utama pada keberangkatan Sumbawa menuju Mataram. Diberikan mode pantau untuk memonitor ketersediaan armada arah sebaliknya guna koordinasi kepulangan.

- Admin Cabang Mataram:
  Fokus utama pada keberangkatan Mataram menuju Sumbawa. Diberikan mode pantau untuk armada dari arah Sumbawa.

- Users / Kasir Loket:
  Khusus melayani pemesanan pada loket cabang tempat bertugas. Visual denah mempermudah kasir melayani penumpang tanpa risiko bentrok nomor kursi dengan loket lain.


7. STRUKTUR FILE YANG DIBUAT & DIMODIFIKASI

File Baru:
- data_pemesanan/kontrol_kursi.php: Halaman pusat kontrol visual denah kursi dan jam keberangkatan.
- data_pemesanan/get_kursi_status.php: Layanan backend penyedia status ketersediaan kursi real-time.
- dokumen_16 sept 2026.md: Dokumen rekapitulasi seluruh pembaruan sistem.

File Dimodifikasi:
- .htaccess: Penguatan proteksi file sensitif, HTTP headers, dan clean URL.
- login.php: Penambahan regenerasi ID sesi untuk proteksi login.
- index.php: Penyesuaian metadata SEO, penghapusan splash gambar, dan standardisasi rute.
- inc/koneksi.php: Sanitasi pesan error database.
- inc/sidebar.php: Penambahan menu Kontrol Kursi & Armada serta perapihan navigasi.
- assets/css/custom.css: Penerapan styling denah kabin kursi dan tema Paper White.
- assets/css/landing.css: Tampilan responsif mobile dan katalog paket kilat.
- data_pemesanan/data_pemesanan_view_v2.php: Penambahan modal pemilih denah kursi interaktif.

Status Sistem:
Seluruh fitur telah terpasang, teruji dengan linter sintaks PHP (0 error), dan siap digunakan secara operasional.
