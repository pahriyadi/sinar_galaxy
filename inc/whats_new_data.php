<?php
/**
 * Data Pembaruan & Fitur Baru (Release Notes / Changelog)
 * PO. Sinar Galaxy Travel
 * 
 * Untuk memicu modal "Apa yang Baru?" otomatis ke semua pengguna:
 * Cukup perbarui nilai 'version' di bawah ini (misal: 'v3.4.0', 'v3.5.0', dst).
 */
return [
    'version' => 'v3.5.0',
    'release_date' => '30 Oktober 2026',
    'title' => 'Pembaruan Sistem: Pemesanan Rombongan Multi-Kursi, Carter Armada & Optimalisasi Dashboard',
    'badge' => 'Rilis Terbaru',
    'highlights' => [
        [
            'category' => 'Pemesanan Tiket',
            'badge_color' => 'success',
            'icon' => 'fas fa-users',
            'title' => 'Pemesanan Rombongan Multi-Kursi (3-4 Kursi Sekaligus)',
            'desc' => 'Kini kasir/admin dapat memilih beberapa kursi sekaligus untuk satu nama penumpang dalam satu transaksi dari denah interaktif. Tersedia checkbox opsi rombongan dengan visualisasi nomor kursi terpilih yang rapi dan informatif.'
        ],
        [
            'category' => 'Carter Armada',
            'badge_color' => 'primary',
            'icon' => 'fas fa-bus-alt',
            'title' => 'Tombol 1-Klik "Carter 1 Unit" (Booking Seluruh Armada)',
            'desc' => 'Memudahkan pemesanan carter seluruh armada. Cukup klik tombol "Carter 1 Unit" pada denah, sistem akan langsung menandai dan memilih semua kursi kosong yang tersedia secara otomatis.'
        ],
        [
            'category' => 'Kalkulasi Otomatis',
            'badge_color' => 'info',
            'icon' => 'fas fa-calculator',
            'title' => 'Perhitungan Tarif Otomatis & Sinkronisasi Kasir Real-Time',
            'desc' => 'Total biaya dihitung secara instan (Tarif × Jumlah Kursi) dan disinkronkan langsung ke nominal metode pembayaran kasir. Dilengkapi rincian kalkulasi harga interaktif di bawah kolom tarif.'
        ],
        [
            'category' => 'Integritas Data',
            'badge_color' => 'secondary',
            'icon' => 'fas fa-database',
            'title' => 'Integritas Kursi Terpisah & Alokasi Pembayaran Presisi',
            'desc' => 'Pemesanan rombongan otomatis dipecah menjadi data kursi individual di database dengan label rombongan/carter. Menjaga akurasi denah kursi real-time, manifes keberangkatan supir, e-tiket, serta alokasi pembayaran rupiah presisi.'
        ],
        [
            'category' => 'Dashboard Utama',
            'badge_color' => 'success',
            'icon' => 'fas fa-chart-line',
            'title' => 'Visual Grafik Finansial Mutakhir di Posisi Teratas',
            'desc' => 'Grafik analitik ditempatkan tepat di bawah filter periode untuk pemantauan instan. Menampilkan Tren 6 Bulan (Combo Area Kurva Gradien Total Omzet, Garis Laba Bersih, Garis Pengeluaran, dan Bar Kontribusi Tiket/Paket).'
        ],
        [
            'category' => 'Desain Responsif',
            'badge_color' => 'secondary',
            'icon' => 'fas fa-columns',
            'title' => 'Universal 2-Tier Header & Toolbar (Paper White v2.0)',
            'desc' => 'Standarisasi tata letak header di seluruh laporan dan dashboard. Menjamin tombol aksi dan judul halaman tetap sejajar rapi, presisi, dan konsisten baik pada zoom layar 100% maupun 80%.'
        ]
    ]
];
