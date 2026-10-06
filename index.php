<?php
require_once 'inc/koneksi.php';

// Ambil logo, favicon, dan nama brand dinamis dari system_settings
$app_logo = function_exists('getSetting') ? getSetting('app_logo', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$app_favicon = function_exists('getSetting') ? getSetting('app_favicon', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$app_name = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
$app_tagline = function_exists('getSetting') ? getSetting('app_tagline', 'Travel Eksekutif Sumbawa - Mataram') : 'Travel Eksekutif Sumbawa - Mataram';

// Ambil harga tiket dinamis per kelas dari tabel data_travel
$travelClasses = [];
$minPrice = 180000;
$maxPrice = 180000;

if ($conn && !$conn->connect_error) {
    $resTravel = mysqli_query($conn, "SELECT kelas, MIN(harga) as harga, MIN(jumlah_kursi) as kursi FROM data_travel GROUP BY kelas ORDER BY harga ASC");
    if ($resTravel && mysqli_num_rows($resTravel) > 0) {
        $prices = [];
        while ($tr = mysqli_fetch_assoc($resTravel)) {
            $k = strtoupper(trim($tr['kelas']));
            $travelClasses[$k] = [
                'kelas' => $k,
                'harga' => (float)$tr['harga'],
                'kursi' => (int)$tr['kursi']
            ];
            $prices[] = (float)$tr['harga'];
        }
        if (!empty($prices)) {
            $minPrice = min($prices);
            $maxPrice = max($prices);
        }
    }
}

// Dapatkan harga VIP dan Ekonomi jika tersedia
$hargaVIP = isset($travelClasses['VIP']) ? $travelClasses['VIP']['harga'] : $maxPrice;
$kursiVIP = isset($travelClasses['VIP']) ? $travelClasses['VIP']['kursi'] : 9;

$hargaEkonomi = isset($travelClasses['EKONOMI']) ? $travelClasses['EKONOMI']['harga'] : $minPrice;
$kursiEkonomi = isset($travelClasses['EKONOMI']) ? $travelClasses['EKONOMI']['kursi'] : 11;

$minPriceFmt = 'Rp ' . number_format($minPrice, 0, ',', '.');
$maxPriceFmt = 'Rp ' . number_format($maxPrice, 0, ',', '.');
$priceRangeFmt = ($minPrice === $maxPrice) ? $minPriceFmt : ($minPriceFmt . ' - ' . $maxPriceFmt);

$hargaVIPFmt = 'Rp ' . number_format($hargaVIP, 0, ',', '.');
$hargaEkonomiFmt = 'Rp ' . number_format($hargaEkonomi, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO <?= htmlspecialchars($app_name) ?> | Travel & Titip Paket Kilat Sumbawa - Mataram (Resmi)</title>
    
    <!-- Primary Meta Tags -->
    <meta name="title" content="PO <?= htmlspecialchars($app_name) ?> | Travel & Titip Paket Kilat Sumbawa - Mataram (Resmi)">
    <meta name="description" content="Layanan resmi travel antar jemput door to door Sumbawa - Mataram dan ekspedisi titip paket kilat same day. Armada nyaman VIP & Ekonomi, berizin resmi ASDP Danantara.">
    <meta name="keywords" content="sinar galaxy travel, po sinar galaxy, travel sumbawa mataram, travel mataram sumbawa, tiket travel sumbawa, titip paket kilat sumbawa mataram, travel door to door lombok sumbawa, jadwal travel sinar galaxy">
    <meta name="author" content="PO. <?= htmlspecialchars($app_name) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://sinargalaxy.my.id/">
    
    <!-- Geo Meta Tags for Local NTB SEO -->
    <meta name="geo.region" content="ID-NB">
    <meta name="geo.placename" content="Sumbawa Besar, Mataram">
    <meta name="geo.position" content="-8.4932;117.4206">
    <meta name="ICBM" content="-8.4932, 117.4206">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($app_favicon) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($app_favicon) ?>">
    <meta name="theme-color" content="#0d3b66">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://sinargalaxy.my.id/">
    <meta property="og:site_name" content="PO Sinar Galaxy Travel">
    <meta property="og:title" content="PO Sinar Galaxy Travel | Travel & Titip Paket Kilat Sumbawa - Mataram (Resmi)">
    <meta property="og:description" content="Layanan transportasi travel reguler door to door Sumbawa - Mataram dan kirim paket kilat same-day dengan perlindungan asuransi resmi ASDP Danantara.">
    <meta property="og:image" content="https://sinargalaxy.my.id/img/travel_vip.jpg">
    <meta property="og:locale" content="id_ID">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://sinargalaxy.my.id/">
    <meta name="twitter:title" content="PO Sinar Galaxy Travel | Travel Sumbawa - Mataram">
    <meta name="twitter:description" content="Pesan tiket travel door to door & kirim paket kilat Sumbawa - Mataram langsung via WhatsApp resmi PO Sinar Galaxy Travel.">
    <meta name="twitter:image" content="https://sinargalaxy.my.id/img/travel_vip.jpg">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Landing Page CSS -->
    <link rel="stylesheet" href="assets/css/landing.css?v=1.1">
    
    <!-- Structured Data: LocalBusiness / TravelAgency, WebSite, FAQPage -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebSite",
          "@id": "https://sinargalaxy.my.id/#website",
          "url": "https://sinargalaxy.my.id/",
          "name": "PO Sinar Galaxy Travel",
          "description": "Website Resmi Layanan Transportasi Travel & Ekspedisi Titip Paket Kilat Sumbawa - Mataram",
          "publisher": {
            "@id": "https://sinargalaxy.my.id/#organization"
          },
          "inLanguage": "id-ID"
        },
        {
          "@type": ["TravelAgency", "LocalBusiness"],
          "@id": "https://sinargalaxy.my.id/#organization",
          "name": "PO <?= htmlspecialchars($app_name) ?> Travel",
          "legalName": "PO. <?= htmlspecialchars($app_name) ?> Travel",
          "url": "https://sinargalaxy.my.id/",
          "logo": {
            "@type": "ImageObject",
            "url": "https://sinargalaxy.my.id/<?= htmlspecialchars($app_logo) ?>",
            "caption": "Logo Resmi <?= htmlspecialchars($app_name) ?>"
          },
          "image": [
            "https://sinargalaxy.my.id/img/travel_vip.jpg",
            "https://sinargalaxy.my.id/img/travel_ekonomi.jpg",
            "https://sinargalaxy.my.id/<?= htmlspecialchars($app_logo) ?>"
          ],
          "description": "PO <?= htmlspecialchars($app_name) ?> Travel adalah penyedia jasa transportasi travel antar-jemput door to door dan layanan titip paket kilat rute Sumbawa Besar - Mataram Lombok dengan izin resmi Sertifikat Standar No. 14062300203860001 dari ASDP - DANANTARA INDONESIA.",
          "priceRange": "<?= htmlspecialchars($priceRangeFmt) ?>",
          "currenciesAccepted": "IDR",
          "paymentAccepted": "Cash, Transfer Bank",
          "sameAs": [
            "https://www.instagram.com/sinargalaxytravel/"
          ],
          "telephone": "+6281763333330",
          "openingHoursSpecification": [
            {
              "@type": "OpeningHoursSpecification",
              "dayOfWeek": [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday"
              ],
              "opens": "06:00",
              "closes": "22:00"
            }
          ],
          "department": [
            {
              "@type": "LocalBusiness",
              "name": "PO <?= htmlspecialchars($app_name) ?> Travel - Kantor Sumbawa Besar",
              "telephone": "+6281763333330",
              "address": {
                "@type": "PostalAddress",
                "streetAddress": "Gang Mangga 1 No. 7",
                "addressLocality": "Sumbawa Besar",
                "addressRegion": "Nusa Tenggara Barat",
                "addressCountry": "ID"
              }
            },
            {
              "@type": "LocalBusiness",
              "name": "PO <?= htmlspecialchars($app_name) ?> Travel - Kantor Mataram",
              "telephone": "+6282339860600",
              "address": {
                "@type": "PostalAddress",
                "streetAddress": "Airlangga Square, Jl. Airlangga",
                "addressLocality": "Kota Mataram",
                "addressRegion": "Nusa Tenggara Barat",
                "addressCountry": "ID"
              }
            }
          ]
        },
        {
          "@type": "FAQPage",
          "@id": "https://sinargalaxy.my.id/#faq",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "Berapa tarif tiket travel Sumbawa - Mataram di <?= htmlspecialchars($app_name) ?> Travel?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Tarif tiket travel <?= htmlspecialchars($app_name) ?> rute Sumbawa - Mataram adalah <?= htmlspecialchars($hargaEkonomiFmt) ?> untuk Kelas Ekonomi (<?= $kursiEkonomi ?> kursi reclining, AC, musik) dan <?= htmlspecialchars($hargaVIPFmt) ?> untuk Kelas VIP (<?= $kursiVIP ?> kursi captain seat, full AC, charger HP, air mineral). Semua tiket sudah termasuk asuransi resmi dan antar jemput door to door."
              }
            },
            {
              "@type": "Question",
              "name": "Apakah Sinar Galaxy Travel melayani antar jemput door to door?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Ya, Sinar Galaxy Travel melayani penjemputan langsung di alamat asal dan pengantaran tepat ke alamat tujuan (door to door) di seluruh area Sumbawa Besar dan Kota Mataram/Lombok."
              }
            },
            {
              "@type": "Question",
              "name": "Jam berapa jadwal keberangkatan travel Sinar Galaxy setiap harinya?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Keberangkatan tersedia setiap hari dengan 5 jadwal pilihan. Dari Sumbawa: 07.00, 10.00, 14.00, 17.00, dan 20.00 WITA. Dari Mataram: 08.00, 10.00, 14.00, 17.00, dan 20.00 WITA."
              }
            },
            {
              "@type": "Question",
              "name": "Apa saja barang yang dapat dikirim lewat layanan Titip Paket Kilat Same Day?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Layanan titip paket kilat same day menerima pengiriman dokumen dinas penting, berkas kantor, oleh-oleh makanan/kuliner, pakaian & konveksi, barang elektronik & gadget, sembako, dan koper barang bawaan. Paket tiba dengan aman di hari yang sama."
              }
            },
            {
              "@type": "Question",
              "name": "Bagaimana cara pesan tiket atau kirim paket di Sinar Galaxy?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Pemesanan dapat langsung dilakukan melalui WhatsApp Customer Service resmi kami: Cabang Mataram di 0823-3986-0600 atau Cabang Sumbawa di 0817-6333-3330."
              }
            }
          ]
        }
      ]
    }
    </script>
</head>
<body>

<!-- Top Legal Notification Bar -->
<div class="top-bar">
    <div class="container top-bar-inner">
        <div class="top-bar-badge">
            <i class="fas fa-shield-alt"></i> Sertifikat Standar Resmi: No. 14062300203860001 (ASDP Danantara)
        </div>
        <div class="top-bar-contacts">
            <a href="https://wa.me/6282339860600" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> Mataram: 0823-3986-0600
            </a>
            <a href="https://wa.me/6281763333330" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> Sumbawa: 0817-6333-3330
            </a>
            <a href="https://www.instagram.com/sinargalaxytravel/" target="_blank" rel="noopener">
                <i class="fab fa-instagram"></i> @sinargalaxytravel
            </a>
        </div>
    </div>
</div>

<!-- Header & Nav -->
<header>
    <div class="container">
        <nav>
            <a href="https://sinargalaxy.my.id/" class="brand" aria-label="Beranda PO <?= htmlspecialchars($app_name) ?>">
                <img src="<?= htmlspecialchars($app_logo) ?>" alt="Logo PO <?= htmlspecialchars($app_name) ?>" class="brand-logo">
                <div class="brand-text">
                    <h2><?= htmlspecialchars(strtoupper($app_name)) ?></h2>
                    <span><?= htmlspecialchars($app_tagline) ?></span>
                </div>
            </a>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="#layanan" class="nav-link">Layanan Travel</a></li>
                <li><a href="#paket" class="nav-link">Titip Paket Kilat</a></li>
                <li><a href="#jadwal" class="nav-link">Jadwal Harian</a></li>
                <li><a href="#kontak" class="nav-link">Kantor & Kontak</a></li>
                <li><a href="#faq" class="nav-link">Tanya Jawab</a></li>
            </ul>

            <div class="nav-cta-group">
                <a href="#pesan" class="btn-wa-header">
                    <i class="fab fa-whatsapp"></i> Pesan Cepat
                </a>
                <a href="login" class="btn-login" title="Akses Sistem Informasi Manajemen">
                    <i class="fas fa-lock"></i> Login SIM
                </a>
                <button class="mobile-toggle" id="mobileToggle" aria-label="Buka Menu Navigasi">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </nav>
    </div>
</header>
<div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>

<!-- Hero Section with Interactive Assistant -->
<section class="hero" id="pesan">
    <div class="container hero-layout">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-award"></i> Transportasi Travel Resmi Sumbawa - Mataram
            </div>
            <h1>Pilihan Terbaik Perjalanan & Ekspedisi <span>Sumbawa - Mataram</span></h1>
            <p>Nikmati perjalanan nyaman antar jemput <strong>Door to Door</strong> dengan perlindungan asuransi resmi, armada terawat full AC, serta layanan <strong>Titip Paket Kilat Same-Day</strong> sampai di hari yang sama.</p>
            
            <div class="hero-trust-badges">
                <div class="trust-item"><i class="fas fa-check-circle"></i> Antar Jemput Alamat</div>
                <div class="trust-item"><i class="fas fa-clock"></i> 5 Jadwal Setiap Hari</div>
                <div class="trust-item"><i class="fas fa-shield-alt"></i> Dilindungi Asuransi</div>
                <div class="trust-item"><i class="fas fa-bolt"></i> Paket Kilat Same Day</div>
            </div>
        </div>

        <!-- Assistant Form -->
        <div class="booking-card">
            <div class="booking-header">
                <h3>Asisten Pemesanan Online</h3>
                <p>Pilih rute & hubungi CS resmi untuk konfirmasi instan</p>
            </div>

            <div class="widget-tabs">
                <button type="button" class="widget-tab active" id="tabTiket" onclick="switchBookingType('tiket')">
                    <i class="fas fa-ticket-alt"></i> Tiket Travel
                </button>
                <button type="button" class="widget-tab" id="tabPaket" onclick="switchBookingType('paket')">
                    <i class="fas fa-box"></i> Kirim Paket Kilat
                </button>
            </div>

            <form id="quickBookingForm" onsubmit="handleQuickBooking(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="routeOrigin"><i class="fas fa-map-marker-alt text-danger"></i> Kota Asal</label>
                        <select id="routeOrigin" class="form-control-custom" onchange="syncRoute()">
                            <option value="Sumbawa Besar">Sumbawa Besar</option>
                            <option value="Kota Mataram">Kota Mataram</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="routeDest"><i class="fas fa-location-arrow text-primary"></i> Kota Tujuan</label>
                        <select id="routeDest" class="form-control-custom" readonly>
                            <option value="Kota Mataram">Kota Mataram</option>
                            <option value="Sumbawa Besar">Sumbawa Besar</option>
                        </select>
                    </div>
                </div>

                <!-- Field For Travel Ticket -->
                <div id="fieldTicketSection">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="travelClass">Pilihan Kelas</label>
                            <select id="travelClass" class="form-control-custom">
                                <?php if (!empty($travelClasses)): ?>
                                    <?php foreach ($travelClasses as $k => $tc): ?>
                                        <option value="Kelas <?= htmlspecialchars($tc['kelas']) ?> (Rp <?= number_format($tc['harga'], 0, ',', '.') ?> / <?= $tc['kursi'] ?> Kursi)">
                                            Kelas <?= htmlspecialchars($tc['kelas']) ?> - Rp <?= number_format($tc['harga'], 0, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="Kelas VIP (Rp 180.000 / 9 Kursi Captain Seat)">Kelas VIP - Rp 180.000</option>
                                    <option value="Kelas Ekonomi (Rp 170.000 / 11 Kursi)">Kelas Ekonomi - Rp 170.000</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="passengerCount">Jumlah Penumpang</label>
                            <select id="passengerCount" class="form-control-custom">
                                <option value="1 Orang">1 Orang</option>
                                <option value="2 Orang">2 Orang</option>
                                <option value="3 Orang">3 Orang</option>
                                <option value="4 Orang atau Lebih">4+ Orang (Rombongan)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Field For Package Delivery (Clean products only, no prices, no animals) -->
                <div id="fieldPackageSection" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="packageType">Kategori Barang / Paket</label>
                            <select id="packageType" class="form-control-custom">
                                <option value="Surat / Dokumen Penting">Surat / Dokumen Penting</option>
                                <option value="Makanan & Oleh-oleh Kuliner">Makanan & Oleh-oleh Kuliner</option>
                                <option value="Baju & Pakaian Konveksi">Baju & Pakaian Konveksi</option>
                                <option value="Elektronik & Gadget">Elektronik & Gadget</option>
                                <option value="Paket Sembako & Kebutuhan">Paket Sembako & Kebutuhan</option>
                                <option value="Koper & Tas Besar">Koper & Tas Besar</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="packageQty">Banyaknya / Koli</label>
                            <select id="packageQty" class="form-control-custom">
                                <option value="1 Koli / Paket">1 Koli / Paket</option>
                                <option value="2 Koli / Paket">2 Koli / Paket</option>
                                <option value="3 Koli atau Lebih">3+ Koli / Paket</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="travelDate">Rencana Tanggal & Jadwal</label>
                    <input type="date" id="travelDate" class="form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="btn-order-wa">
                    <i class="fab fa-whatsapp"></i> Hubungi CS via WhatsApp
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="keunggulan">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Keunggulan Layanan</span>
            <h2 class="section-title">Mengapa Memilih PO Sinar Galaxy Travel?</h2>
            <p class="section-subtitle">Dedikasi kami adalah menghadirkan perjalanan darat yang aman, terpercaya, dan bebas repot antara Pulau Sumbawa dan Pulau Lombok.</p>
        </div>

        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-home"></i>
                </div>
                <h3>Door to Door Service</h3>
                <p>Penjemputan tepat di depan pintu rumah atau kantor Anda di Sumbawa / Mataram dan diantar langsung ke alamat tujuan.</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Dilindungi Asuransi Resmi</h3>
                <p>Setiap penumpang dan perjalanan terproteksi secara resmi dengan data KTP sesuai standar keselamatan transportasi.</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-history"></i>
                </div>
                <h3>5x Keberangkatan Setiap Hari</h3>
                <p>Pilihan jam fleksibel dari pagi hingga malam: 07.00/08.00, 10.00, 14.00, 17.00, dan 20.00 WITA setiap hari tanpa libur.</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h3>Titip Paket Kilat Same Day</h3>
                <p>Kirim barang belanjaan, kuliner, dokumen penting, atau barang dagangan dengan jaminan tiba di hari yang sama.</p>
            </div>
        </div>
    </div>
</section>

<!-- Armada & Kelas Travel -->
<section id="layanan" style="background: #f1f5f9;">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Armada & Fasilitas</span>
            <h2 class="section-title">Pilihan Kelas Armada Sinar Galaxy</h2>
            <p class="section-subtitle">Kendaraan prima dan terawat dengan pengemudi profesional berpengalaman lintas pulau Sumbawa - Lombok.</p>
        </div>

        <div class="armada-grid">
            <!-- Kelas VIP -->
            <div class="armada-card">
                <span class="armada-badge-popular"><i class="fas fa-star mr-1"></i> Favorit Penumpang</span>
                <div class="armada-img-wrapper">
                    <img src="img/travel_vip.jpg" alt="Interior Bis VIP PO Sinar Galaxy Travel Mataram Sumbawa" loading="lazy">
                </div>
                <div class="armada-content">
                    <span class="armada-category">Premium Executive</span>
                    <h3>Kelas VIP Exclusive</h3>
                    <div class="armada-price">
                        <span class="amount"><?= $hargaVIPFmt ?></span>
                        <span class="unit">/ kursi (Door to Door)</span>
                    </div>
                    <ul class="armada-specs">
                        <li><i class="fas fa-check-circle"></i> Kapasitas Maksimal <?= $kursiVIP ?> Kursi Luas</li>
                        <li><i class="fas fa-check-circle"></i> Captain Seat Ergonomis & Reclining</li>
                        <li><i class="fas fa-check-circle"></i> Full AC Dingin & Audio Hiburan</li>
                        <li><i class="fas fa-check-circle"></i> USB Slot Charger HP di Setiap Baris</li>
                        <li><i class="fas fa-check-circle"></i> Air Mineral / Snack Perjalanan</li>
                        <li><i class="fas fa-check-circle"></i> Termasuk Biaya Tiket Kapal Penyeberangan & Asuransi</li>
                    </ul>
                    <a href="https://wa.me/6282339860600?text=Halo%20PO%20<?= urlencode($app_name) ?>,%20saya%20ingin%20pesan%20tiket%20Kelas%20VIP%20(<?= urlencode($hargaVIPFmt) ?>)%20Sumbawa%20-%20Mataram" class="btn-card-action" target="_blank" rel="noopener">
                        Pesan Kelas VIP Sekarang <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Kelas Ekonomi -->
            <div class="armada-card">
                <div class="armada-img-wrapper">
                    <img src="img/travel_ekonomi.jpg" alt="Interior Bis Ekonomi PO Sinar Galaxy Travel Nyaman" loading="lazy">
                </div>
                <div class="armada-content">
                    <span class="armada-category">Comfort Reguler</span>
                    <h3>Kelas Ekonomi</h3>
                    <div class="armada-price">
                        <span class="amount"><?= $hargaEkonomiFmt ?></span>
                        <span class="unit">/ kursi (Door to Door)</span>
                    </div>
                    <ul class="armada-specs">
                        <li><i class="fas fa-check-circle"></i> Kapasitas <?= $kursiEkonomi ?> Kursi Nyaman</li>
                        <li><i class="fas fa-check-circle"></i> Kursi Reclining Seat Empuk</li>
                        <li><i class="fas fa-check-circle"></i> Full AC Dingin & Musik</li>
                        <li><i class="fas fa-check-circle"></i> Antar Jemput Door to Door Alamat</li>
                        <li><i class="fas fa-check-circle"></i> Pengemudi Ramah & Berpengalaman</li>
                        <li><i class="fas fa-check-circle"></i> Termasuk Tiket Kapal Feri & Asuransi Resmi</li>
                    </ul>
                    <a href="https://wa.me/6281763333330?text=Halo%20PO%20<?= urlencode($app_name) ?>,%20saya%20ingin%20pesan%20tiket%20Kelas%20Ekonomi%20(<?= urlencode($hargaEkonomiFmt) ?>)%20Sumbawa%20-%20Mataram" class="btn-card-action" target="_blank" rel="noopener">
                        Pesan Kelas Ekonomi Sekarang <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Titip Paket Kilat Same Day Section (Tanpa Harga, Hanya Kategori Produk) -->
<section id="paket" class="paket-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Ekspedisi Same Day</span>
            <h2 class="section-title">Layanan Titip Paket Kilat Sumbawa - Mataram</h2>
            <p class="section-subtitle">Kirim barang atau dokumen penting tiba di hari yang sama tanpa menunggu berhari-hari. Aman, cepat, dan ditangani dengan profesional.</p>
        </div>

        <div class="paket-grid">
            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-file-alt"></i></div>
                <span class="paket-badge-type">Dokumen</span>
                <h4>Surat & Dokumen Penting</h4>
                <p>Berkas dinas, surat kantor, ijazah, dokumen resmi perbankan, dan kartu identitas.</p>
            </div>

            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-utensils"></i></div>
                <span class="paket-badge-type">Kuliner</span>
                <h4>Makanan & Oleh-Oleh</h4>
                <p>Kue basah/kering, kuliner siap santap, makanan khas Sumbawa & Lombok, serta buah-buahan.</p>
            </div>

            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-tshirt"></i></div>
                <span class="paket-badge-type">Fashion</span>
                <h4>Baju & Pakaian Konveksi</h4>
                <p>Paket pakaian jadi, produk olshop busana, kain tenun khas daerah, tekstil, dan aksesoris.</p>
            </div>

            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-mobile-alt"></i></div>
                <span class="paket-badge-type">Elektronik</span>
                <h4>Elektronik & Gadget</h4>
                <p>Handphone, laptop, suku cadang komputer, alat kelistrikan, dan aksesori elektronik.</p>
            </div>

            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-shopping-basket"></i></div>
                <span class="paket-badge-type">Logistik</span>
                <h4>Paket Sembako & Dagangan</h4>
                <p>Kardus barang dagangan, pasokan toko/warung, sembako kebutuhan harian, dan logistik usaha.</p>
            </div>

            <div class="paket-card">
                <div class="paket-icon"><i class="fas fa-suitcase"></i></div>
                <span class="paket-badge-type">Bagasi</span>
                <h4>Koper & Tas Besar</h4>
                <p>Koper pakaian pribadi, tas perlengkapan bepergian, dan barang titipan bagasi penumpang.</p>
            </div>
        </div>

        <div class="paket-cta-box">
            <div class="paket-cta-text">
                <h4>Ingin Mengirim Paket Kilat Hari Ini?</h4>
                <p>Titipkan di kantor perwakilan terdekat (Airlangga Mataram / Gang Mangga Sumbawa) atau koordinasikan penjemputan via WhatsApp.</p>
            </div>
            <a href="https://wa.me/6282339860600?text=Halo%20Sinar%20Galaxy,%20saya%20ingin%20kirim%20paket%20kilat" class="btn-order-wa" style="margin-top:0; width:auto; padding:12px 24px;" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> Chat CS Titip Paket
            </a>
        </div>
    </div>
</section>

<!-- Jadwal Keberangkatan -->
<section id="jadwal">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Jadwal Operasional</span>
            <h2 class="section-title">Jadwal Keberangkatan Setiap Hari</h2>
            <p class="section-subtitle">Pemberangkatan reguler 5 trip setiap hari melayani jalur Sumbawa - Mataram via penyeberangan Feri Pototano - Kayangan.</p>
        </div>

        <div class="schedule-grid">
            <!-- Dari Sumbawa -->
            <div class="schedule-card">
                <div class="schedule-card-header">
                    <h3><i class="fas fa-arrow-circle-right text-primary"></i> Rute: Sumbawa ➔ Mataram</h3>
                    <span class="schedule-badge-daily">Setiap Hari</span>
                </div>
                <div class="schedule-times-list">
                    <div class="time-item">
                        <span class="hour">07.00</span>
                        <span class="zone">Pagi WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">10.00</span>
                        <span class="zone">Pagi WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">14.00</span>
                        <span class="zone">Siang WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">17.00</span>
                        <span class="zone">Sore WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">20.00</span>
                        <span class="zone">Malam WITA</span>
                    </div>
                </div>
            </div>

            <!-- Dari Mataram -->
            <div class="schedule-card">
                <div class="schedule-card-header">
                    <h3><i class="fas fa-arrow-circle-left text-accent"></i> Rute: Mataram ➔ Sumbawa</h3>
                    <span class="schedule-badge-daily">Setiap Hari</span>
                </div>
                <div class="schedule-times-list">
                    <div class="time-item">
                        <span class="hour">08.00</span>
                        <span class="zone">Pagi WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">10.00</span>
                        <span class="zone">Pagi WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">14.00</span>
                        <span class="zone">Siang WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">17.00</span>
                        <span class="zone">Sore WITA</span>
                    </div>
                    <div class="time-item">
                        <span class="hour">20.00</span>
                        <span class="zone">Malam WITA</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Kantor Cabang & Kontak Resmi -->
<section id="kontak" style="background: #f8fafc; border-top: 1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Pusat Layanan Resmi</span>
            <h2 class="section-title">Kantor Cabang & Kontak Customer Service</h2>
            <p class="section-subtitle">Datang langsung ke kantor kami atau hubungi nomor layanan WhatsApp untuk pemesanan tiket dan pengiriman paket.</p>
        </div>

        <div class="branches-grid">
            <!-- Cabang Sumbawa -->
            <div class="branch-card">
                <div class="branch-header">
                    <div class="branch-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <h3>Kantor Sumbawa Besar</h3>
                        <span>Pusat Operasional Sumbawa</span>
                    </div>
                </div>
                <div class="branch-details">
                    <div class="branch-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <div><strong>Alamat:</strong> Jl. Mangga 1 No. 7, Sumbawa Besar, Nusa Tenggara Barat</div>
                    </div>
                    <div class="branch-row">
                        <i class="fas fa-clock"></i>
                        <div><strong>Jam Operasional:</strong> Setiap Hari, 06.00 - 22.00 WITA</div>
                    </div>
                    <div class="branch-row">
                        <i class="fas fa-phone"></i>
                        <div><strong>Telepon / WhatsApp:</strong> 0817-6333-3330</div>
                    </div>
                </div>
                <div class="branch-buttons">
                    <a href="https://wa.me/6281763333330" class="btn-branch-wa" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp"></i> Chat CS Sumbawa (081763333330)
                    </a>
                </div>
            </div>

            <!-- Cabang Mataram -->
            <div class="branch-card">
                <div class="branch-header">
                    <div class="branch-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <h3>Kantor Kota Mataram</h3>
                        <span>Pusat Operasional Lombok</span>
                    </div>
                </div>
                <div class="branch-details">
                    <div class="branch-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <div><strong>Alamat:</strong> Airlangga Square, Kota Mataram, Nusa Tenggara Barat</div>
                    </div>
                    <div class="branch-row">
                        <i class="fas fa-clock"></i>
                        <div><strong>Jam Operasional:</strong> Setiap Hari, 06.00 - 22.00 WITA</div>
                    </div>
                    <div class="branch-row">
                        <i class="fas fa-phone"></i>
                        <div><strong>Telepon / WhatsApp:</strong> 0823-3986-0600</div>
                    </div>
                </div>
                <div class="branch-buttons">
                    <a href="https://wa.me/6282339860600" class="btn-branch-wa" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp"></i> Chat CS Mataram (082339860600)
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section id="faq">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">FAQ & Informasi</span>
            <h2 class="section-title">Pertanyaan yang Sering Diajukan</h2>
            <p class="section-subtitle">Temukan jawaban cepat seputar pemesanan tiket travel dan titip paket di Sinar Galaxy Travel.</p>
        </div>

        <div class="faq-container">
            <div class="faq-item active">
                <button class="faq-question" type="button" aria-expanded="true">
                    <span>Berapa harga tiket travel Sumbawa - Mataram di <?= htmlspecialchars($app_name) ?>?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    Tarif tiket travel <?= htmlspecialchars($app_name) ?> adalah <strong><?= $hargaEkonomiFmt ?></strong> untuk <strong>Kelas Ekonomi</strong> (<?= $kursiEkonomi ?> kursi) dan <strong><?= $hargaVIPFmt ?></strong> untuk <strong>Kelas VIP</strong> (<?= $kursiVIP ?> kursi captain seat). Harga sudah termasuk layanan antar-jemput door to door, tiket penyeberangan kapal feri, dan asuransi perjalanan resmi.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button" aria-expanded="false">
                    <span>Apakah <?= htmlspecialchars($app_name) ?> melayani antar jemput sampai ke alamat rumah (Door to Door)?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    Ya, layanan utama kami adalah <strong>Door to Door</strong>. Driver kami akan menjemput Anda langsung di alamat asal (rumah, kantor, penginapan) dan mengantar Anda sampai di alamat tujuan di seputar Mataram dan Sumbawa Besar tanpa perlu repot ganti kendaraan.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button" aria-expanded="false">
                    <span>Apa saja jenis barang yang dapat dikirim lewat Titip Paket Kilat?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    Kami melayani titip paket kilat same day untuk dokumen dinas/penting, makanan & oleh-oleh khas, pakaian/konveksi, barang elektronik & gadget, koper perjalanan, serta kebutuhan sembako/dagangan. Paket dijamin tiba dengan aman di hari yang sama.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button" aria-expanded="false">
                    <span>Apakah perjalanan di <?= htmlspecialchars($app_name) ?> berizin resmi dan aman?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    Sangat aman. PO <?= htmlspecialchars($app_name) ?> beroperasi di bawah legalitas resmi Sertifikat Standar Nomor <strong>14062300203860001</strong> yang dikeluarkan oleh ASDP - DANANTARA INDONESIA. Setiap penumpang tercatat dan terproteksi perlindungan asuransi dengan menyertakan nomor KTP.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button" aria-expanded="false">
                    <span>Bagaimana cara memesan tiket atau menanyakan ketersediaan kursi?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    Pemesanan sangat mudah! Anda bisa menggunakan formulir asisten pemesanan di bagian atas halaman ini, atau langsung menghubungi nomor WhatsApp CS Kantor Sumbawa (0817-6333-3330) atau Kantor Mataram (0823-3986-0600).
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3>
                    <img src="<?= htmlspecialchars($app_logo) ?>" alt="Logo <?= htmlspecialchars($app_name) ?>" style="height:32px; border-radius:50%;">
                    PO. <?= strtoupper(htmlspecialchars($app_name)) ?>
                </h3>
                <p>Penyedia layanan transportasi travel antar jemput door to door dan titip paket kilat terpercaya rute Sumbawa Besar - Mataram dengan izin operasional resmi dan jaminan kenyamanan.</p>
                <div class="footer-social-links">
                    <a href="https://www.instagram.com/sinargalaxytravel/" class="social-btn instagram" target="_blank" rel="noopener" title="Instagram Resmi @sinargalaxytravel" aria-label="Instagram Resmi Sinar Galaxy Travel">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://wa.me/6282339860600" class="social-btn" target="_blank" rel="noopener" title="WhatsApp CS Mataram" aria-label="WhatsApp CS Mataram">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="https://wa.me/6281763333330" class="social-btn" target="_blank" rel="noopener" title="WhatsApp CS Sumbawa" aria-label="WhatsApp CS Sumbawa">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Layanan Transportasi</h4>
                <ul class="footer-links">
                    <li><a href="#layanan"><i class="fas fa-chevron-right text-muted"></i> Travel Kelas VIP</a></li>
                    <li><a href="#layanan"><i class="fas fa-chevron-right text-muted"></i> Travel Kelas Ekonomi</a></li>
                    <li><a href="#keunggulan"><i class="fas fa-chevron-right text-muted"></i> Door to Door Service</a></li>
                    <li><a href="#paket"><i class="fas fa-chevron-right text-muted"></i> Titip Paket Kilat Same Day</a></li>
                    <li><a href="#jadwal"><i class="fas fa-chevron-right text-muted"></i> Jadwal Keberangkatan</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Kontak & Kantor</h4>
                <ul class="footer-links">
                    <li><a href="https://wa.me/6282339860600" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Mataram: 0823-3986-0600</a></li>
                    <li><a href="https://wa.me/6281763333330" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Sumbawa: 0817-6333-3330</a></li>
                    <li><a href="https://www.instagram.com/sinargalaxytravel/" target="_blank" rel="noopener"><i class="fab fa-instagram"></i> @sinargalaxytravel</a></li>
                    <li><a href="sitemap.xml"><i class="fas fa-sitemap"></i> Peta Situs (Sitemap)</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Legalitas Resmi</h4>
                <div class="footer-legal-badge">
                    <span>Sertifikat Standar ASDP</span>
                    <p><strong>No. 14062300203860001</strong><br>Dikeluarkan oleh ASDP - Danantara Indonesia untuk angkutan sewa & travel antar jemput.</p>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> PO. Sinar Galaxy Travel. Hak Cipta Dilindungi Undang-Undang.</p>
            <div>
                <a href="login" class="footer-sim-link" title="Sistem Informasi Manajemen Sinar Galaxy">
                    <i class="fas fa-user-shield"></i> Portal Petugas & Karyawan (SIM Travel)
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp Button with Quick Menu -->
<div class="wa-float-container">
    <div class="wa-popup-menu" id="waPopupMenu">
        <div class="wa-popup-header">
            <h4>Hubungi Customer Service</h4>
            <p>Pilih cabang kantor tujuan Anda:</p>
        </div>
        <a href="https://wa.me/6282339860600?text=Halo%20Sinar%20Galaxy%20Mataram,%20saya%20ingin%20tanya%20layanan%20travel%20dan%20paket" target="_blank" rel="noopener" class="wa-popup-item">
            <i class="fab fa-whatsapp"></i>
            <div class="wa-popup-item-info">
                <strong>CS Kantor Mataram</strong>
                <span>0823-3986-0600 (Airlangga)</span>
            </div>
        </a>
        <a href="https://wa.me/6281763333330?text=Halo%20Sinar%20Galaxy%20Sumbawa,%20saya%20ingin%20tanya%20layanan%20travel%20dan%20paket" target="_blank" rel="noopener" class="wa-popup-item">
            <i class="fab fa-whatsapp"></i>
            <div class="wa-popup-item-info">
                <strong>CS Kantor Sumbawa</strong>
                <span>0817-6333-3330 (Gang Mangga)</span>
            </div>
        </a>
    </div>
    <div class="wa-float-btn" id="waFloatBtn" title="Chat WhatsApp Customer Service" aria-label="Tombol WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </div>
</div>

<!-- Interactive JS Logic -->
<script>
    let activeBookingType = 'tiket';

    function switchBookingType(type) {
        activeBookingType = type;
        const tabTiket = document.getElementById('tabTiket');
        const tabPaket = document.getElementById('tabPaket');
        const fieldTicketSection = document.getElementById('fieldTicketSection');
        const fieldPackageSection = document.getElementById('fieldPackageSection');

        if (type === 'tiket') {
            tabTiket.classList.add('active');
            tabPaket.classList.remove('active');
            fieldTicketSection.style.display = 'block';
            fieldPackageSection.style.display = 'none';
        } else {
            tabPaket.classList.add('active');
            tabTiket.classList.remove('active');
            fieldTicketSection.style.display = 'none';
            fieldPackageSection.style.display = 'block';
        }
    }

    function syncRoute() {
        const origin = document.getElementById('routeOrigin').value;
        const dest = document.getElementById('routeDest');
        if (origin === 'Sumbawa Besar') {
            dest.value = 'Kota Mataram';
        } else {
            dest.value = 'Sumbawa Besar';
        }
    }

    function handleQuickBooking(e) {
        e.preventDefault();
        const origin = document.getElementById('routeOrigin').value;
        const dest = document.getElementById('routeDest').value;
        const date = document.getElementById('travelDate').value;
        
        // Target phone: if origin is Sumbawa -> contact Sumbawa (081763333330), if Mataram -> Mataram (082339860600)
        let phone = (origin === 'Sumbawa Besar') ? '6281763333330' : '6282339860600';
        let message = '';

        if (activeBookingType === 'tiket') {
            const travelClass = document.getElementById('travelClass').value;
            const pax = document.getElementById('passengerCount').value;
            message = `Halo PO Sinar Galaxy Travel, saya ingin memesan tiket:\n\n` +
                      `• Layanan: Tiket Travel Penumpang\n` +
                      `• Rute: ${origin} ➔ ${dest}\n` +
                      `• Pilihan: ${travelClass}\n` +
                      `• Jumlah: ${pax}\n` +
                      `• Tanggal Keberangkatan: ${date}\n\n` +
                      `Mohon info ketersediaan kursi & jadwal keberangkatan. Terima kasih!`;
        } else {
            const pkgType = document.getElementById('packageType').value;
            const pkgQty = document.getElementById('packageQty').value;
            message = `Halo PO Sinar Galaxy Travel, saya ingin mengirim paket kilat:\n\n` +
                      `• Layanan: Titip Paket Kilat Same-Day\n` +
                      `• Rute Pengiriman: ${origin} ➔ ${dest}\n` +
                      `• Kategori Barang: ${pkgType}\n` +
                      `• Jumlah: ${pkgQty}\n` +
                      `• Tanggal Pengiriman: ${date}\n\n` +
                      `Mohon info titik drop-off / penjemputan paket. Terima kasih!`;
        }

        const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank');
    }

    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', () => {
            const parent = btn.parentElement;
            const isActive = parent.classList.contains('active');

            // Close all
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
                item.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
            });

            // Toggle clicked
            if (!isActive) {
                parent.classList.add('active');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Mobile Navigation Toggle & Backdrop
    const mobileToggle = document.getElementById('mobileToggle');
    const navMenu = document.getElementById('navMenu');
    const mobileNavBackdrop = document.getElementById('mobileNavBackdrop');

    function closeMobileMenu() {
        if (navMenu) navMenu.classList.remove('show');
        if (mobileNavBackdrop) mobileNavBackdrop.classList.remove('show');
        document.body.style.overflow = '';
        if (mobileToggle) {
            const icon = mobileToggle.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    }

    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', () => {
            const isOpen = navMenu.classList.toggle('show');
            if (mobileNavBackdrop) mobileNavBackdrop.classList.toggle('show', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
            const icon = mobileToggle.querySelector('i');
            if (isOpen) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        if (mobileNavBackdrop) {
            mobileNavBackdrop.addEventListener('click', closeMobileMenu);
        }

        // Close on link click
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
    }

    // Floating WhatsApp Button Popup Toggle
    const waFloatBtn = document.getElementById('waFloatBtn');
    const waPopupMenu = document.getElementById('waPopupMenu');
    if (waFloatBtn && waPopupMenu) {
        waFloatBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            waPopupMenu.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!waPopupMenu.contains(e.target) && e.target !== waFloatBtn) {
                waPopupMenu.classList.remove('show');
            }
        });
    }
</script>
</body>
</html>
