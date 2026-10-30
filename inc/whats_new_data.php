<?php
/**
 * Data Pembaruan & Riwayat Versi Lengkap (Changelog & Release Notes)
 * PO. Sinar Galaxy Travel
 * 
 * Versi paling atas ($releases[0]) adalah versi aktif/terbaru.
 * Menyimpan seluruh riwayat pembaruan sistem secara kronologis agar mudah dilacak.
 */

$releases = [
    [
        'version' => 'v3.5.1',
        'release_date' => '30 Oktober 2026',
        'title' => 'Pemesanan Multi-Kursi, Carter 1 Unit, E-Tiket Gabungan & Sinkronisasi Laporan Real',
        'badge' => 'Rilis Terbaru',
        'badge_class' => 'badge-success',
        'is_latest' => true,
        'highlights' => [
            [
                'category' => 'Pemesanan Tiket',
                'badge_color' => 'success',
                'icon' => 'fas fa-users',
                'title' => 'Pemesanan Rombongan Multi-Kursi & Tombol 1-Klik "Carter 1 Unit"',
                'desc' => 'Kasir/admin kini dapat memilih beberapa kursi sekaligus (3-4 kursi) melalui checkbox multi-seat atau klik tombol "Carter 1 Unit" pada denah interaktif untuk membooking seluruh kursi kosong secara otomatis.'
            ],
            [
                'category' => 'E-Tiket Carter',
                'badge_color' => 'primary',
                'icon' => 'fas fa-layer-group',
                'title' => 'E-Tiket Perorangan Default + Tombol Switch "E-Tiket Gabungan Carter"',
                'desc' => 'Saat E-Tiket dibuka, tampilan tetap menampilkan E-Tiket Perorangan (per kursi). Jika sistem mendeteksi pesanan Carter 1 Unit, otomatis tersedia tombol "E-Tiket Gabungan Carter" untuk menggabungkan seluruh kursi dan menjumlahkan harga tiket murni dalam 1 lembar tiket.'
            ],
            [
                'category' => 'Pendapatan Internal',
                'badge_color' => 'warning',
                'icon' => 'fas fa-shuttle-van',
                'title' => 'Tarif Jemput / Antar Carter Dinamis (Master Armada & Kasir)',
                'desc' => 'Pengaturan Biaya Jemput/Antar Carter (default Rp 150.000) kini terhubung langsung dari Master Data Armada ke form pemesanan. Fleksibel disesuaikan kasir dan dicatat khusus sebagai pendapatan internal tanpa ditampilkan di dalam E-Tiket penumpang.'
            ],
            [
                'category' => 'Laporan Real-Time',
                'badge_color' => 'info',
                'icon' => 'fas fa-file-invoice-dollar',
                'title' => 'Sinkronisasi Penuh Seluruh Laporan & Dashboard (100% Real)',
                'desc' => 'Seluruh transaksi tiket maupun biaya jemput/antar internal carter otomatis masuk secara riil dan akurat ke Dashboard Eksekutif, Manifes Keberangkatan, Jurnal Keuangan, Rekap Keuangan, serta seluruh Laporan Pendapatan (Armada, Rute, Bank, & Kasir).'
            ],
            [
                'category' => 'Integritas Data',
                'badge_color' => 'secondary',
                'icon' => 'fas fa-database',
                'title' => 'Integritas Kursi Terpisah & Alokasi Pembayaran Presisi',
                'desc' => 'Pemesanan multi-kursi & carter dipecah menjadi baris kursi individual berlabel grup di database. Menjaga akurasi denah kursi real-time, manifes supir, serta alokasi metode pembayaran hingga presisi Rp 1.'
            ]
        ]
    ],
    [
        'version' => 'v3.4.0',
        'release_date' => '28 Oktober 2026',
        'title' => 'Dashboard Eksekutif, Visual Grafik Top Position & Universal 2-Tier Header',
        'badge' => 'Stabil',
        'badge_class' => 'badge-primary',
        'is_latest' => false,
        'highlights' => [
            [
                'category' => 'Dashboard Utama',
                'badge_color' => 'success',
                'icon' => 'fas fa-chart-line',
                'title' => 'Visual Grafik Finansial Mutakhir di Posisi Teratas',
                'desc' => 'Grafik analitik ditempatkan tepat di bawah filter periode untuk pemantauan instan. Menampilkan Tren 6 Bulan (Combo Area Kurva Gradien Total Omzet, Garis Laba Bersih, Garis Beban Pengeluaran, dan Bar Kontribusi Tiket/Paket) dengan tombol interaktif switch view.'
            ],
            [
                'category' => 'Kualitas Data',
                'badge_color' => 'primary',
                'icon' => 'fas fa-database',
                'title' => 'Agregasi Finansial Single-Pass & Akurasi Omzet Lengkap',
                'desc' => 'Perhitungan data dioptimalkan dengan Single-Pass Aggregation (bebas beban query loop berulang). Menampilkan pemisahan riil omzet tiket vs kargo paket, multi-metode pembayaran, kas masuk hari ini, serta persentase margin laba bersih dinamis.'
            ],
            [
                'category' => 'Breakdown Analitik',
                'badge_color' => 'info',
                'icon' => 'fas fa-chart-pie',
                'title' => 'Tabs Analitik Bank, Armada & Komposisi Layanan',
                'desc' => 'Dilengkapi Doughnut Chart interaktif untuk porsi penerimaan per rekening bank/kasir, Horizontal Bar Chart omzet unit armada travel teratas, dan diagram perbandingan rasio pendapatan tiket penumpang vs titipan paket kargo.'
            ],
            [
                'category' => 'Desain Responsif',
                'badge_color' => 'secondary',
                'icon' => 'fas fa-columns',
                'title' => 'Universal 2-Tier Header & Toolbar (Paper White v2.0)',
                'desc' => 'Standarisasi tata letak header di seluruh 8 laporan utama dan dashboard. Menjamin tombol aksi dan judul halaman tetap sejajar rapi, presisi, dan tidak mengalami wrapping teks baik pada zoom layar 100% maupun 80%.'
            ],
            [
                'category' => 'Operasional Travel',
                'badge_color' => 'success',
                'icon' => 'fas fa-bus',
                'title' => 'Penetapan Plat Armada Terjadwal & Notifikasi Sticky H-2',
                'desc' => 'Pemesanan tiket H-7 kini dapat dilakukan tanpa memilih plat nomor mobil di awal [BELUM DITENTUKAN]. Dilengkapi notifikasi sticky header otomatis H-2 sebelum keberangkatan untuk mengingatkan admin mengisi plat armada dalam satu klik.'
            ]
        ]
    ],
    [
        'version' => 'v3.3.0',
        'release_date' => '26 Oktober 2026',
        'title' => 'Master Data Jadwal Keberangkatan & Fleksibilitas Multi-Payment',
        'badge' => 'Stabil',
        'badge_class' => 'badge-info',
        'is_latest' => false,
        'highlights' => [
            [
                'category' => 'Master Jadwal',
                'badge_color' => 'success',
                'icon' => 'fas fa-clock',
                'title' => 'Master Jadwal Jam Keberangkatan Armada Dinamis',
                'desc' => 'Penambahan tabel master data jadwal jam keberangkatan travel yang dapat dikelola fleksibel dan terintegrasi otomatis ke dropdown form pemesanan tiket.'
            ],
            [
                'category' => 'Kasir & Finansial',
                'badge_color' => 'primary',
                'icon' => 'fas fa-money-check-alt',
                'title' => 'Penyempurnaan Metode Multi-Payment & Rekening Kasir',
                'desc' => 'Peningkatan presisi pencatatan multi-metode pembayaran (kombinasi Cash, Transfer, DP) lengkap dengan input tanggal pembayaran spesifik per metode.'
            ]
        ]
    ],
    [
        'version' => 'v3.2.0',
        'release_date' => '24 Oktober 2026',
        'title' => 'Sistem E-Resi Kargo Multi-Format & Portal Lacak Publik',
        'badge' => 'Stabil',
        'badge_class' => 'badge-secondary',
        'is_latest' => false,
        'highlights' => [
            [
                'category' => 'Kargo & Paket',
                'badge_color' => 'warning',
                'icon' => 'fas fa-box',
                'title' => 'Cetak E-Resi Kargo Format Boarding Pass Thermal 80mm & A4',
                'desc' => 'Pembaruan struk resi pengiriman barang berstandar modern dengan barcode resi, detail pengirim/penerima, dan rincian ongkir.'
            ],
            [
                'category' => 'Layanan Pelanggan',
                'badge_color' => 'info',
                'icon' => 'fab fa-whatsapp',
                'title' => 'Kirim Resi via WhatsApp & Portal Lacak Online',
                'desc' => 'Integrasi kirim pesan WhatsApp otomatis ke nomor pengirim/penerima dengan tautan pelacakan publik tanpa login.'
            ]
        ]
    ],
    [
        'version' => 'v3.1.0',
        'release_date' => '20 Oktober 2026',
        'title' => 'E-Tiket Maskapai Penerbangan (Airline Boarding Pass)',
        'badge' => 'Stabil',
        'badge_class' => 'badge-secondary',
        'is_latest' => false,
        'highlights' => [
            [
                'category' => 'E-Tiket',
                'badge_color' => 'success',
                'icon' => 'fas fa-ticket-alt',
                'title' => 'Desain Boarding Pass Maskapai dengan Barcode 1D & Stub Penumpang',
                'desc' => 'Tampilan cetak tiket format Thermal 80mm landscape yang autentik seperti boarding pass pesawat udara lengkap dengan potongan sobekan (stub) penumpang dan supir.'
            ],
            [
                'category' => 'Portal Publik',
                'badge_color' => 'primary',
                'icon' => 'fas fa-globe',
                'title' => 'Portal Verifikasi E-Tiket Publik & Share WhatsApp',
                'desc' => 'Penumpang dapat melihat dan mengunduh e-tiket resmi mereka melalui link token aman yang dapat dibagikan langsung ke WhatsApp penumpang.'
            ]
        ]
    ],
    [
        'version' => 'v3.0.0',
        'release_date' => '15 Oktober 2026',
        'title' => 'Peluncuran Standar Desain Paper White v2.0',
        'badge' => 'Fondasi',
        'badge_class' => 'badge-dark',
        'is_latest' => false,
        'highlights' => [
            [
                'category' => 'Sistem UI',
                'badge_color' => 'secondary',
                'icon' => 'fas fa-paint-roller',
                'title' => 'Desain Bersih, Flat & Presisi Tinggi (Paper White v2.0)',
                'desc' => 'Pembaruan antarmuka menyeluruh menggunakan palet warna Paper White (#ffffff, border #b8b8b8, brand emerald #0d9f4f) yang ringan, cepat, dan nyaman untuk operasional kasir harian.'
            ]
        ]
    ]
];

// Return data rilis dengan backwards-compatibility:
return [
    'latest'       => $releases[0],
    'releases'     => $releases,
    'total'        => count($releases),
    // Key legacy agar script lama tetap berjalan lancar:
    'version'      => $releases[0]['version'],
    'release_date' => $releases[0]['release_date'],
    'title'        => $releases[0]['title'],
    'badge'        => $releases[0]['badge'],
    'highlights'   => $releases[0]['highlights']
];
