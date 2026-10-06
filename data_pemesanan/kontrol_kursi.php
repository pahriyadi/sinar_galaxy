<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil data user dari session
$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? 0;
$asal_po = $user['asal_po'] ?? 'Sumbawa';
$role = strtolower($user['role'] ?? 'users');

// Parameter filter default
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$jam = $_GET['jam'] ?? '10:00';
$no_plat = $_GET['no_plat'] ?? '';
$tujuan_id = $_GET['tujuan_id'] ?? '';

// Ambil daftar armada travel
$armada_list = [];
$resTravel = mysqli_query($conn, "SELECT id_travel, no_plat, kelas, harga, jumlah_kursi FROM data_travel ORDER BY kelas ASC, no_plat ASC");
while ($r = mysqli_fetch_assoc($resTravel)) {
    $armada_list[] = $r;
}

if (empty($no_plat) && !empty($armada_list)) {
    $no_plat = $armada_list[0]['no_plat'];
}

// Ambil daftar tujuan
$tujuan_list = [];
$resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan ORDER BY id_tujuan_perjalanan ASC");
while ($r = mysqli_fetch_assoc($resTujuan)) {
    $tujuan_list[] = $r;
}

// Tentukan default rute berdasarkan asal_po user
// Jika asal_po = Sumbawa -> Tujuan PO - MATARAM (id = 1)
// Jika asal_po = Mataram -> Tujuan PO - SUMBAWA (id = 2)
if (empty($tujuan_id)) {
    if (stripos($asal_po, 'mataram') !== false) {
        $tujuan_id = 2; // PO - SUMBAWA
    } else {
        $tujuan_id = 1; // PO - MATARAM
    }
}

// Output HTML
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>

<div class="content-wrapper" style="background-color: #fcfcfc;">
  <!-- Content Header -->
  <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <div class="d-flex align-items-center">
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
              <i class="fas fa-chair"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.3rem; font-weight: 700;">Kontrol Kursi & Keberangkatan</h1>
              <p class="text-muted m-0" style="font-size: 0.82rem;">
                Simulasi denah kursi real-time per armada travel dan jadwal keberangkatan SGT
                <span class="badge badge-light border ml-1 px-2 py-1 text-uppercase" style="font-size: 0.75rem;">
                  <i class="fas fa-building text-success mr-1"></i> Cabang: <strong><?= htmlspecialchars($asal_po) ?></strong>
                </span>
                <span class="badge badge-light border ml-1 px-2 py-1" style="font-size: 0.75rem;">
                  <i class="fas fa-user-shield text-info mr-1"></i> Role: <strong><?= htmlspecialchars(strtoupper($role)) ?></strong>
                </span>
              </p>
            </div>
          </div>
        </div>
        <div class="col-sm-5 text-right">
          <a href="data_pemesanan_view_v2" class="btn btn-sm btn-outline-secondary font-weight-bold mr-1">
            <i class="fas fa-receipt mr-1"></i> Data Pemesanan
          </a>
          <a href="../laporan/manifest_keberangkatan" class="btn btn-sm btn-outline-success font-weight-bold" target="_blank">
            <i class="fas fa-print mr-1"></i> Cetak Manifest
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <!-- FILTER TRIP CONTROLLER -->
      <div class="card mb-3 border">
        <div class="card-body p-3">
          <form id="filterForm" method="GET" class="row align-items-end">
            
            <!-- Tanggal -->
            <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
              <label class="font-weight-bold text-xs text-muted mb-1">
                <i class="fas fa-calendar-day text-success mr-1"></i> TANGGAL KEBERANGKATAN
              </label>
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <button type="button" class="btn btn-outline-secondary" id="btnPrevDay" title="Hari Sebelumnya">
                    <i class="fas fa-chevron-left"></i>
                  </button>
                </div>
                <input type="date" class="form-control form-control-sm font-weight-bold text-center" name="tanggal" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary" id="btnNextDay" title="Hari Berikutnya">
                    <i class="fas fa-chevron-right"></i>
                  </button>
                  <button type="button" class="btn btn-outline-success font-weight-bold" id="btnToday" title="Hari Ini">
                    Hari Ini
                  </button>
                </div>
              </div>
            </div>

            <!-- Armada Travel -->
            <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
              <label class="font-weight-bold text-xs text-muted mb-1">
                <i class="fas fa-bus text-success mr-1"></i> UNIT ARMADA / PLAT
              </label>
              <select class="form-control form-control-sm font-weight-bold" name="no_plat" id="no_plat" style="font-size: 13px;">
                <?php foreach ($armada_list as $arm): ?>
                  <option value="<?= htmlspecialchars($arm['no_plat']) ?>" <?= $no_plat === $arm['no_plat'] ? 'selected' : '' ?> data-kelas="<?= htmlspecialchars($arm['kelas']) ?>" data-seat="<?= $arm['jumlah_kursi'] ?>">
                    <?= htmlspecialchars($arm['no_plat']) ?> &mdash; <?= htmlspecialchars($arm['kelas']) ?> (<?= $arm['jumlah_kursi'] ?> Seat)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Rute Tujuan -->
            <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
              <label class="font-weight-bold text-xs text-muted mb-1">
                <i class="fas fa-route text-success mr-1"></i> RUTE PERJALANAN
              </label>
              <select class="form-control form-control-sm font-weight-bold" name="tujuan_id" id="tujuan_id" style="font-size: 13px;">
                <?php foreach ($tujuan_list as $tj): ?>
                  <option value="<?= $tj['id_tujuan_perjalanan'] ?>" <?= (int)$tujuan_id === (int)$tj['id_tujuan_perjalanan'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tj['nama_tujuan']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Status Ringkas -->
            <div class="col-lg-3 col-md-6 text-lg-right">
              <button type="button" id="btnRefresh" class="btn btn-sm btn-success font-weight-bold px-3">
                <i class="fas fa-sync-alt mr-1"></i> Refresh Denah
              </button>
            </div>

          </form>
        </div>

        <!-- QUICK PILLS: JAM KEBERANGKATAN -->
        <div class="card-footer bg-light p-2 border-top">
          <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
            <span class="text-xs font-weight-bold text-muted mr-2">
              <i class="fas fa-clock text-success mr-1"></i> JAM KEBERANGKATAN:
            </span>
            <button type="button" class="schedule-pill-btn <?= $jam === '07:00' ? 'active' : '' ?>" data-jam="07:00">
              <i class="fas fa-sun text-warning"></i> 07:00 WITA
            </button>
            <button type="button" class="schedule-pill-btn <?= $jam === '08:00' ? 'active' : '' ?>" data-jam="08:00">
              <i class="fas fa-sun text-warning"></i> 08:00 WITA
            </button>
            <button type="button" class="schedule-pill-btn <?= $jam === '10:00' ? 'active' : '' ?>" data-jam="10:00">
              <i class="fas fa-sun text-warning"></i> 10:00 WITA
            </button>
            <button type="button" class="schedule-pill-btn <?= $jam === '14:00' ? 'active' : '' ?>" data-jam="14:00">
              <i class="fas fa-cloud-sun text-info"></i> 14:00 WITA
            </button>
            <button type="button" class="schedule-pill-btn <?= $jam === '17:00' ? 'active' : '' ?>" data-jam="17:00">
              <i class="fas fa-cloud text-secondary"></i> 17:00 WITA
            </button>
            <button type="button" class="schedule-pill-btn <?= $jam === '20:00' ? 'active' : '' ?>" data-jam="20:00">
              <i class="fas fa-moon text-primary"></i> 20:00 WITA
            </button>
            
            <!-- Custom Jam Input -->
            <div class="d-inline-flex align-items-center ml-auto" style="gap: 4px;">
              <span class="text-xs text-muted">Jam Lain:</span>
              <input type="time" id="customJamInput" class="form-control form-control-sm" style="width: 105px; height: 30px; font-size: 12px;" value="<?= htmlspecialchars($jam) ?>">
              <button type="button" id="btnApplyCustomJam" class="btn btn-xs btn-outline-secondary px-2" style="height: 30px;">
                Terapkan
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN ROW: SIMULASI KABIN KURSI (KIRI) & RINGKASAN/MANIFEST (KANAN) -->
      <div class="row">
        
        <!-- KOLOM KIRI: SIMULASI DENAH KABIN -->
        <div class="col-lg-6 col-md-12 mb-3">
          <div class="card border h-100">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
              <span class="font-weight-bold text-sm text-dark">
                <i class="fas fa-couch text-success mr-2"></i>Denah Visual Kabin Armada
              </span>
              <span id="badgeArmadaInfo" class="badge badge-light border font-weight-bold text-dark px-2 py-1">
                Loading...
              </span>
            </div>
            
            <div class="card-body p-3">
              
              <!-- Petunjuk Legenda Status Kursi -->
              <div class="d-flex justify-content-center flex-wrap mb-3 p-2 bg-light border rounded" style="gap: 10px;">
                <div class="seat-legend-item">
                  <span class="seat-legend-box" style="background: #ffffff; border: 1.5px solid #0d9f4f;"></span>
                  <span>Tersedia (Kosong)</span>
                </div>
                <div class="seat-legend-item">
                  <span class="seat-legend-box" style="background: #f8fafc; border: 1.5px solid #cbd5e1;"></span>
                  <span>Terisi (Lunas)</span>
                </div>
                <div class="seat-legend-item">
                  <span class="seat-legend-box" style="background: #fffbeb; border: 1.5px solid #f59e0b;"></span>
                  <span>Booking (DP)</span>
                </div>
                <div class="seat-legend-item">
                  <span class="seat-legend-box" style="background: #0d9f4f; border: 1.5px solid #076e34;"></span>
                  <span>Terpilih</span>
                </div>
              </div>

              <!-- LOADING SPINNER DENAH -->
              <div id="seatLoading" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-success" role="status">
                  <span class="sr-only">Memuat status kursi...</span>
                </div>
                <p class="text-muted mt-2 font-weight-bold text-sm">Menyinkronkan denah kursi...</p>
              </div>

              <!-- WADAH KABIN KENDARAAN -->
              <div id="cabinContainer" class="cabin-container shadow-none">
                <!-- Kaca Depan -->
                <div class="cabin-windshield">
                  <i class="fas fa-arrows-alt-v mr-1"></i> BAGIAN DEPAN / KACA DEPAN ARMADA
                </div>

                <!-- Kontainer Baris Kursi (Di-generate dinamis via JS) -->
                <div id="seatLayoutRows">
                  <!-- Dynamic JS layout insertion -->
                </div>

                <!-- Bagian Belakang Bagasi -->
                <div class="text-center mt-3 pt-2 border-top" style="font-size: 0.72rem; color: #94a3b8; letter-spacing: 1px;">
                  <i class="fas fa-suitcase mr-1"></i> BAGASI / PINTU BELAKANG
                </div>
              </div>

            </div>

            <div class="card-footer bg-light p-2 border-top text-center">
              <small class="text-muted">
                <i class="fas fa-info-circle text-info mr-1"></i>
                Klik pada kursi <strong>Hijau</strong> untuk melakukan pemesanan tiket baru. Klik pada kursi <strong>Abu/Oranye</strong> untuk melihat data penumpang.
              </small>
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN: RINGKASAN OKUPANSI & TABEL MANIFEST -->
        <div class="col-lg-6 col-md-12 mb-3">
          
          <!-- KARTU RINGKASAN OKUPANSI -->
          <div class="card border mb-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
              <span class="font-weight-bold text-sm text-dark">
                <i class="fas fa-chart-pie text-success mr-2"></i>Status Keterisian & Okupansi
              </span>
              <span id="labelTripTime" class="badge badge-success font-weight-bold px-2 py-1" style="background: #0d9f4f;">
                <?= htmlspecialchars($jam) ?> WITA
              </span>
            </div>
            <div class="card-body p-3">
              <div class="row align-items-center">
                <div class="col-6 border-right text-center">
                  <div class="text-muted text-xs font-weight-bold text-uppercase mb-1">Kursi Terisi</div>
                  <div class="h3 font-weight-bold mb-0 text-dark" id="statTerisi">0</div>
                  <small class="text-muted" id="statTotalKursi">dari 0 Kursi</small>
                </div>
                <div class="col-6 text-center">
                  <div class="text-muted text-xs font-weight-bold text-uppercase mb-1">Sisa Kursi Kosong</div>
                  <div class="h3 font-weight-bold mb-0 text-success" id="statTersedia">0</div>
                  <small class="text-muted" id="statPersen">Okupansi 0%</small>
                </div>
              </div>

              <!-- Progress Bar -->
              <div class="progress mt-3" style="height: 10px; border-radius: 5px; background: #e2e8f0;">
                <div id="progressBarOkupansi" class="progress-bar bg-success" role="progressbar" style="width: 0%; transition: width 0.4s ease;"></div>
              </div>

              <!-- Tombol Cepat Pesan -->
              <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                <div>
                  <span class="text-xs text-muted d-block font-weight-bold">Tarif Standar:</span>
                  <span class="h5 font-weight-bold text-success mb-0" id="labelTarif">Rp 180.000</span>
                </div>
                <button type="button" id="btnDirectBook" class="btn btn-sm btn-success font-weight-bold px-3">
                  <i class="fas fa-plus-circle mr-1"></i> Pesan Tiket di Trip Ini
                </button>
              </div>
            </div>
          </div>

          <!-- KARTU MANIFEST PENUMPANG PADA TRIP INI -->
          <div class="card border">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
              <span class="font-weight-bold text-sm text-dark">
                <i class="fas fa-list-ol text-success mr-2"></i>Daftar Penumpang Trip Ini
              </span>
              <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCetakTripManifest">
                <i class="fas fa-print mr-1"></i> Cetak Manifest
              </button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-bordered table-hover table-sm table-excel m-0">
                  <thead class="bg-light">
                    <tr>
                      <th style="width: 50px;" class="text-center">Seat</th>
                      <th>Nama Penumpang</th>
                      <th>No HP</th>
                      <th>Tujuan</th>
                      <th class="text-center">Status</th>
                      <th style="width: 70px;" class="text-center">Aksi</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyPassengerList">
                    <tr>
                      <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat daftar penumpang...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </section>
</div>

<!-- MODAL DETAIL PENUMPANG & PINDAH KURSI -->
<div class="modal fade" id="modalPassengerDetail" tabindex="-1" role="dialog" aria-labelledby="modalPassengerDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
    <div class="modal-content border">
      <div class="modal-header bg-light py-2 px-3 border-bottom">
        <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalPassengerDetailLabel">
          <i class="fas fa-user-tag text-success mr-2"></i>Detail Penumpang — Kursi <span id="modalSeatNum">#</span>
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-3">
        <div class="text-center mb-3">
          <div style="width: 54px; height: 54px; border-radius: 50%; background: #e6f4ea; color: #0d9f4f; font-size: 1.5rem; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #b7e1cd;">
            <i class="fas fa-user"></i>
          </div>
          <h5 class="font-weight-bold text-dark mt-2 mb-0" id="modalPassengerName">Nama Penumpang</h5>
          <span class="badge badge-light border text-muted px-2 py-1 mt-1" id="modalTicketId">ID Tiket: #</span>
        </div>

        <div class="bg-light p-2 rounded border mb-3">
          <div class="row text-xs py-1 border-bottom">
            <div class="col-4 text-muted font-weight-bold">Nomor HP / WA:</div>
            <div class="col-8 font-weight-bold text-dark" id="modalPassengerPhone">-</div>
          </div>
          <div class="row text-xs py-1 border-bottom">
            <div class="col-4 text-muted font-weight-bold">Rute / Tujuan:</div>
            <div class="col-8 font-weight-bold text-dark" id="modalPassengerRoute">-</div>
          </div>
          <div class="row text-xs py-1 border-bottom">
            <div class="col-4 text-muted font-weight-bold">Keberangkatan:</div>
            <div class="col-8 font-weight-bold text-dark" id="modalDepartureTime">-</div>
          </div>
          <div class="row text-xs py-1 border-bottom">
            <div class="col-4 text-muted font-weight-bold">Status Bayar:</div>
            <div class="col-8" id="modalPaymentStatus">-</div>
          </div>
          <div class="row text-xs py-1">
            <div class="col-4 text-muted font-weight-bold">Petugas Loket:</div>
            <div class="col-8 text-dark" id="modalAgentInfo">-</div>
          </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="d-flex justify-content-between align-items-center" style="gap: 8px;">
          <a href="#" id="btnViewPemesanan" class="btn btn-sm btn-outline-primary flex-fill font-weight-bold" target="_blank">
            <i class="fas fa-receipt mr-1"></i> Lihat Tiket
          </a>
          <button type="button" class="btn btn-sm btn-secondary px-3" data-dismiss="modal">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../inc/footer.php'; ?>

<!-- JAVASCRIPT KONTROL DENAH KURSI & REAKTIF TRIP -->
<script>
$(document).ready(function() {
  let activeSeatsData = [];
  let currentTripData = null;

  // Initial load
  loadSeatMap();

  // Event handler pergantian filter
  $('#tanggal, #no_plat, #tujuan_id').on('change', function() {
    loadSeatMap();
  });

  // Tombol Hari Sebelumnya
  $('#btnPrevDay').on('click', function() {
    let curDate = new Date($('#tanggal').val());
    if (isNaN(curDate)) curDate = new Date();
    curDate.setDate(curDate.getDate() - 1);
    $('#tanggal').val(curDate.toISOString().split('T')[0]).trigger('change');
  });

  // Tombol Hari Berikutnya
  $('#btnNextDay').on('click', function() {
    let curDate = new Date($('#tanggal').val());
    if (isNaN(curDate)) curDate = new Date();
    curDate.setDate(curDate.getDate() + 1);
    $('#tanggal').val(curDate.toISOString().split('T')[0]).trigger('change');
  });

  // Tombol Hari Ini
  $('#btnToday').on('click', function() {
    let today = new Date().toISOString().split('T')[0];
    $('#tanggal').val(today).trigger('change');
  });

  // Tombol Refresh
  $('#btnRefresh').on('click', function() {
    loadSeatMap();
  });

  // Quick Schedule Pills
  $('.schedule-pill-btn').on('click', function() {
    $('.schedule-pill-btn').removeClass('active');
    $(this).addClass('active');
    let jam = $(this).data('jam');
    $('#customJamInput').val(jam);
    loadSeatMap();
  });

  // Apply Custom Jam
  $('#btnApplyCustomJam').on('click', function() {
    let jam = $('#customJamInput').val();
    if (!jam) return;
    $('.schedule-pill-btn').removeClass('active');
    loadSeatMap();
  });

  // Cetak Manifest Trip Ini
  $('#btnCetakTripManifest').on('click', function() {
    let tgl = $('#tanggal').val();
    let plat = $('#no_plat').val();
    let tj = $('#tujuan_id').val();
    window.open('../laporan/manifest_keberangkatan?tanggal=' + encodeURIComponent(tgl) + '&no_plat=' + encodeURIComponent(plat) + '&tujuan_perjalanan=' + encodeURIComponent(tj), '_blank');
  });

  // Tombol Pesan Tiket di Trip Ini
  $('#btnDirectBook').on('click', function() {
    let tgl = $('#tanggal').val();
    let jam = getActiveJam();
    let plat = $('#no_plat').val();
    let tj = $('#tujuan_id').val();
    let datetime = tgl + 'T' + jam;
    window.location.href = 'data_pemesanan_view_v2?action=new&tanggal_berangkat=' + encodeURIComponent(datetime) + '&no_plat=' + encodeURIComponent(plat) + '&tujuan=' + encodeURIComponent(tj);
  });

  function getActiveJam() {
    let activePill = $('.schedule-pill-btn.active');
    if (activePill.length) {
      return activePill.data('jam');
    }
    return $('#customJamInput').val() || '10:00';
  }

  // Fungsi memuat status kursi via AJAX
  function loadSeatMap() {
    let tgl = $('#tanggal').val();
    let jam = getActiveJam();
    let plat = $('#no_plat').val();
    let tujuan = $('#tujuan_id').val();

    $('#seatLoading').show();
    $('#cabinContainer').css('opacity', '0.4');

    $.ajax({
      url: 'get_kursi_status.php',
      type: 'GET',
      data: {
        tanggal: tgl,
        jam: jam,
        no_plat: plat
      },
      dataType: 'json',
      success: function(resp) {
        $('#seatLoading').hide();
        $('#cabinContainer').css('opacity', '1');

        if (resp && resp.success) {
          currentTripData = resp;
          activeSeatsData = resp.seats || [];
          renderCabinLayout(resp);
          renderOccupancyStats(resp);
          renderPassengerTable(resp);
        } else {
          alert(resp.message || 'Gagal memuat status kursi');
        }
      },
      error: function() {
        $('#seatLoading').hide();
        $('#cabinContainer').css('opacity', '1');
        alert('Terjadi kesalahan koneksi saat memuat status kursi.');
      }
    });
  }

  // Render Denah Kabin berdasarkan Tipe Armada
  function renderCabinLayout(resp) {
    let armada = resp.armada;
    let totalSeats = armada.jumlah_kursi;
    let kelas = armada.kelas ? armada.kelas.toUpperCase() : 'EKONOMI';
    let seats = resp.seats;

    $('#badgeArmadaInfo').text(armada.no_plat + ' • ' + kelas + ' (' + totalSeats + ' Kursi)');
    $('#labelTripTime').text(resp.jam + ' WITA');
    $('#labelTarif').text('Rp ' + new Intl.NumberFormat('id-ID').format(armada.harga));

    let html = '';

    // Map kursi index ke objek kursi
    let seatMap = {};
    seats.forEach(function(s) {
      seatMap[s.nomor] = s;
    });

    if (kelas.indexOf('VIP') !== -1 && totalSeats <= 9) {
      // LAYOUT VIP: 9 SEAT (Captain Seat)
      // Baris 1: Supir (kanan) & Kursi 1 (kiri)
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[1]);
      html += '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div>';
      html += '<div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div>';
      html += '</div>';

      // Baris 2: Kursi 2 (kiri) & Kursi 3 (kanan)
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[2]);
      html += '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div>';
      html += renderSeatBox(seatMap[3]);
      html += '</div>';

      // Baris 3: Kursi 4 (kiri) & Kursi 5 (kanan)
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[4]);
      html += '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div>';
      html += renderSeatBox(seatMap[5]);
      html += '</div>';

      // Baris 4 (Belakang): Kursi 6, 7, 8, 9
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[6]);
      html += renderSeatBox(seatMap[7]);
      html += renderSeatBox(seatMap[8]);
      html += renderSeatBox(seatMap[9]);
      html += '</div>';

    } else if (totalSeats > 11) {
      // LAYOUT EKONOMI-PREMIO (15 SEAT)
      // Baris 1: Kursi 1 & Supir
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[1]);
      html += '<div class="seat-aisle"></div>';
      html += '<div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div>';
      html += '</div>';

      // Baris 2: Kursi 2, 3, 4
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[2]);
      html += renderSeatBox(seatMap[3]);
      html += renderSeatBox(seatMap[4]);
      html += '</div>';

      // Baris 3: Kursi 5, 6, 7
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[5]);
      html += renderSeatBox(seatMap[6]);
      html += renderSeatBox(seatMap[7]);
      html += '</div>';

      // Baris 4: Kursi 8, 9, 10
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[8]);
      html += renderSeatBox(seatMap[9]);
      html += renderSeatBox(seatMap[10]);
      html += '</div>';

      // Baris 5 (Belakang): Kursi 11, 12, 13, 14, 15
      html += '<div class="seat-row">';
      for (let k = 11; k <= totalSeats; k++) {
        html += renderSeatBox(seatMap[k]);
      }
      html += '</div>';

    } else {
      // LAYOUT EKONOMI STANDAR (11 SEAT)
      // Baris 1: Kursi 1 & Supir
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[1]);
      html += '<div class="seat-aisle"></div>';
      html += '<div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div>';
      html += '</div>';

      // Baris 2: Kursi 2, 3 + Akses Pintu
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[2]);
      html += renderSeatBox(seatMap[3]);
      html += '<div class="seat-box" style="background:#f8fafc; border:1px dashed #cbd5e1; color:#94a3b8; cursor:default;"><i class="fas fa-door-open"></i><span class="seat-label">PINTU</span></div>';
      html += '</div>';

      // Baris 3: Kursi 4, 5, 6
      html += '<div class="seat-row">';
      html += renderSeatBox(seatMap[4]);
      html += renderSeatBox(seatMap[5]);
      html += renderSeatBox(seatMap[6]);
      html += '</div>';

      // Baris 4 (Belakang): Kursi 7, 8, 9, 10, 11
      html += '<div class="seat-row">';
      for (let k = 7; k <= totalSeats; k++) {
        html += renderSeatBox(seatMap[k]);
      }
      html += '</div>';
    }

    $('#seatLayoutRows').html(html);

    // Bind click events on seats
    bindSeatEvents();
  }

  // Render individual seat item
  function renderSeatBox(seatObj) {
    if (!seatObj) return '<div class="seat-box" style="visibility:hidden;"></div>';

    let num = seatObj.nomor;
    let status = seatObj.status;
    let cls = 'seat-available';
    let icon = 'fa-chair';
    let label = 'Kosong';

    if (status === 'occupied') {
      cls = 'seat-occupied';
      icon = 'fa-user-check';
      label = seatObj.data ? seatObj.data.nama.substring(0, 7) : 'Terisi';
    } else if (status === 'dp') {
      cls = 'seat-dp';
      icon = 'fa-user-clock';
      label = (seatObj.data ? seatObj.data.nama.substring(0, 6) : 'DP') + ' (DP)';
    }

    return '<div class="seat-box ' + cls + '" data-nomor="' + num + '" data-status="' + status + '" title="Kursi #' + num + ' - ' + seatObj.status_label + '">' +
           '<i class="fas ' + icon + '"></i>' +
           '<span class="seat-num">' + num + '</span>' +
           '<span class="seat-label">' + label + '</span>' +
           '</div>';
  }

  // Event handler interaksi kursi
  function bindSeatEvents() {
    $('.seat-box[data-nomor]').off('click').on('click', function() {
      let num = $(this).data('nomor');
      let status = $(this).data('status');
      let seatData = activeSeatsData.find(function(s) { return s.nomor == num; });

      if (status === 'available') {
        // Klik kursi kosong -> Buka konfirmasi pesan tiket di kursi ini
        let tgl = $('#tanggal').val();
        let jam = getActiveJam();
        let plat = $('#no_plat').val();
        let tj = $('#tujuan_id').val();
        let datetime = tgl + 'T' + jam;

        if (confirm('Pesan tiket untuk Kursi #' + num + ' pada keberangkatan ' + tgl + ' jam ' + jam + ' WITA?')) {
          window.location.href = 'data_pemesanan_view_v2?action=new&kursi=' + num + '&tanggal_berangkat=' + encodeURIComponent(datetime) + '&no_plat=' + encodeURIComponent(plat) + '&tujuan=' + encodeURIComponent(tj);
        }

      } else {
        // Klik kursi terisi -> Tampilkan modal info detail penumpang
        if (seatData && seatData.data) {
          let d = seatData.data;
          $('#modalSeatNum').text('#' + num);
          $('#modalPassengerName').text(d.nama);
          $('#modalTicketId').text('ID Tiket: #' + d.id_pemesanan);
          $('#modalPassengerPhone').text(d.no_hp || '-');
          $('#modalPassengerRoute').text(d.tujuan || '-');
          $('#modalDepartureTime').text(d.tanggal_berangkat || '-');
          
          let payBadge = d.is_dp ? '<span class="badge badge-warning">DP / Piutang</span>' : '<span class="badge badge-success">Lunas</span>';
          $('#modalPaymentStatus').html(payBadge + ' (' + (d.status_bayar || 'Lunas') + ')');
          $('#modalAgentInfo').text(d.petugas + ' (' + d.cabang_petugas + ')');

          $('#btnViewPemesanan').attr('href', 'data_pemesanan_view_v2?search=' + encodeURIComponent(d.nama));
          $('#modalPassengerDetail').modal('show');
        }
      }
    });
  }

  // Render Ringkasan Okupansi
  function renderOccupancyStats(resp) {
    let stats = resp.stats;
    $('#statTerisi').text(stats.terisi);
    $('#statTotalKursi').text('dari ' + stats.total + ' Kursi');
    $('#statTersedia').text(stats.tersedia);
    $('#statPersen').text('Okupansi ' + stats.persentase + '%');

    $('#progressBarOkupansi').css('width', stats.persentase + '%');
    if (stats.persentase >= 90) {
      $('#progressBarOkupansi').removeClass('bg-success bg-warning').addClass('bg-danger');
    } else if (stats.persentase >= 60) {
      $('#progressBarOkupansi').removeClass('bg-success bg-danger').addClass('bg-warning');
    } else {
      $('#progressBarOkupansi').removeClass('bg-warning bg-danger').addClass('bg-success');
    }
  }

  // Render Tabel Manifest Penumpang
  function renderPassengerTable(resp) {
    let seats = resp.seats;
    let html = '';
    let bookedCount = 0;

    seats.forEach(function(s) {
      if (s.status !== 'available' && s.data) {
        bookedCount++;
        let d = s.data;
        let badgeClass = s.status === 'dp' ? 'badge-warning' : 'badge-success';
        html += '<tr>';
        html += '<td class="text-center font-weight-bold" style="background:#f8fafc;">#' + s.nomor + '</td>';
        html += '<td class="font-weight-bold text-dark">' + d.nama + '</td>';
        html += '<td><a href="https://wa.me/' + (d.no_hp.replace(/^0/, '62').replace(/[^0-9]/g, '')) + '" target="_blank" class="text-success font-weight-bold"><i class="fab fa-whatsapp mr-1"></i>' + d.no_hp + '</a></td>';
        html += '<td>' + d.tujuan + '</td>';
        html += '<td class="text-center"><span class="badge ' + badgeClass + '">' + d.status_bayar + '</span></td>';
        html += '<td class="text-center">';
        html += '<button type="button" class="btn btn-xs btn-outline-info btn-view-detail" data-nomor="' + s.nomor + '" title="Lihat Detail"><i class="fas fa-eye"></i></button>';
        html += '</td>';
        html += '</tr>';
      }
    });

    if (bookedCount === 0) {
      html = '<tr><td colspan="6" class="text-center py-4 text-muted font-weight-bold"><i class="fas fa-check-circle text-success mr-1"></i> Belum ada penumpang yang memesan di jadwal ini. Semua ' + resp.stats.total + ' kursi masih tersedia.</td></tr>';
    }

    $('#tbodyPassengerList').html(html);

    $('.btn-view-detail').on('click', function() {
      let num = $(this).data('nomor');
      $('.seat-box[data-nomor="' + num + '"]').trigger('click');
    });
  }

});
</script>
