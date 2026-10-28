<?php
/**
 * Data Pembaruan & Fitur Baru (Release Notes / Changelog)
 * PO. Sinar Galaxy Travel
 * 
 * Untuk memicu modal "Apa yang Baru?" otomatis ke semua pengguna:
 * Cukup perbarui nilai 'version' di bawah ini (misal: 'v3.4.0', 'v3.4.1', dst).
 */
return [
    'version' => 'v3.4.0',
    'release_date' => '28 Oktober 2026',
    'title' => 'Pembaruan Sistem: Dashboard Eksekutif, Visual Grafik & Header Universal',
    'badge' => 'Rilis Terbaru',
    'highlights' => [
        [
            'category' => 'Dashboard Utama',
            'badge_color' => 'success',
            'icon' => 'fas fa-chart-line',
            'title' => 'Visual Grafik Finansial Mutakhir di Posisi Teratas',
            'desc' => 'Grafik analitik kini ditempatkan tepat di bawah filter periode untuk pemantauan instan. Menampilkan Tren 6 Bulan (Combo Area Kurva Gradien Total Omzet, Garis Laba Bersih, Garis Beban Pengeluaran, dan Bar Kontribusi Tiket/Paket) dengan tombol interaktif switch view.'
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
];
