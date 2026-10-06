<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil data user dari session
$user = $_SESSION['user'] ?? [];
$asal_po = $user['asal_po'] ?? 'Sumbawa';
$role = strtolower((string)($user['role'] ?? ''));

// Guard: Hanya super admin yang diizinkan mengakses modul Master Jenis Barang & Tarif Paket
if ($role !== 'super admin') {
    showFloatingNotice('Akses Ditolak: Halaman Master Jenis Barang & Tarif Paket hanya dapat diakses oleh Super Admin.', 'danger');
}

// Handle CRUD (PRG pattern — PHP header redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit']) || (isset($_POST['aksi']) && $_POST['aksi'] === 'edit') || (!empty($_POST['id_barang']) && !isset($_POST['tambah']))) {
        updateJenisBarang($_POST);
    } else {
        tambahJenisBarang($_POST);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deleteJenisBarang($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua data jenis barang & tarif
$data = mysqli_query($conn, "SELECT * FROM data_jenis_barang ORDER BY nama_barang ASC");
$total_barang = $data ? mysqli_num_rows($data) : 0;

// Hitung metrik tarif
$avg_tarif = 0;
$max_tarif = 0;
$qStat = mysqli_query($conn, "SELECT AVG(biaya_standar) as avg_b, MAX(biaya_standar) as max_b FROM data_jenis_barang");
if ($rStat = mysqli_fetch_assoc($qStat)) {
    $avg_tarif = (float)($rStat['avg_b'] ?? 0);
    $max_tarif = (float)($rStat['max_b'] ?? 0);
}

// Sekarang baru HTML output
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
              <i class="fas fa-boxes"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Master Jenis Barang & Tarif Paket</h1>
              <div class="text-muted" style="font-size: 0.83rem;">
                <span>Katalog klasifikasi paket barang dan acuan ongkos kirim standar ekspedisi travel</span>
                <span class="mx-1">•</span>
                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                  <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asal_po) ?>
                </span>
                <span class="badge badge-secondary" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                  <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-5 text-right">
          <button type="button" class="btn btn-default btn-sm btn-flat" onclick="location.reload();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
            <i class="fas fa-sync-alt mr-1 text-muted"></i> Refresh Data
          </button>
          <button type="button" class="btn btn-success btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; padding: 6px 14px;">
            <i class="fas fa-plus mr-1"></i> Tambah Barang & Tarif
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content" style="padding: 0 15px;">
    <!-- 3 KPI Metrics Card (Paper White v2.0) -->
    <div class="row mb-3">
      <div class="col-lg-4 col-12 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Katalog Barang</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_barang) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Jenis</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                <i class="fas fa-box-open" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-check-circle text-success mr-1"></i> Referensi formulir ekspedisi
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Rata-Rata Tarif Standar</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #0284c7; line-height: 1.2; margin-top: 4px;">
                  Rp <?= number_format($avg_tarif, 0, ',', '.') ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-tags" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-calculator mr-1"></i> Biaya rata-rata per paket
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Tarif Standar Tertinggi</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #b45309; line-height: 1.2; margin-top: 4px;">
                  Rp <?= number_format($max_tarif, 0, ',', '.') ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #fffbeb; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; color: #b45309;">
                <i class="fas fa-arrow-up" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-info-circle mr-1"></i> Muatan besar / perlakuan khusus
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card mb-4" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center">
            <i class="fas fa-table mr-2 text-muted"></i>
            <span class="font-weight-bold text-dark" style="font-size: 1rem;">Daftar Master Jenis Barang & Tarif Kirim</span>
          </div>
          <button class="btn btn-primary btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; border-radius: 4px;">
            <i class="fas fa-plus mr-1"></i> Tambah Data
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="tableJenisBarang" class="table table-excel table-hover table-bordered table-sm mb-0" style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #ffffff;">
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 60px; font-weight: 600; color: #202124;">ID</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Jenis Barang / Paket</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; text-align: right; width: 170px; font-weight: 600; color: #202124;">Tarif Standar (Rp)</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Keterangan / Panduan</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 110px; font-weight: 600; color: #202124;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = mysqli_fetch_assoc($data)): ?>
              <tr>
                <td style="padding: 8px; border: 1px solid #b8b8b8; text-align: center; font-weight: 600; color: #5f6368;"><?= $r['id_barang']; ?></td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">
                  <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-size: 0.88rem; padding: 5px 10px;">
                    <i class="fas fa-box mr-1"></i><?= htmlspecialchars($r['nama_barang']); ?>
                  </span>
                </td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; text-align: right; font-weight: 700; color: #0d9f4f; font-size: 0.95rem;">
                  Rp <?= number_format($r['biaya_standar'], 0, ',', '.'); ?>
                </td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; color: #495057;">
                  <?= !empty($r['keterangan']) ? htmlspecialchars($r['keterangan']) : '<span class="text-muted italic">-</span>'; ?>
                </td>
                <td style="padding: 6px 8px; border: 1px solid #b8b8b8; text-align: center;">
                  <button class="btn btn-default btn-xs btn-flat btn-edit-barang mr-1" 
                    data-id="<?= $r['id_barang']; ?>" 
                    data-nama="<?= htmlspecialchars($r['nama_barang']); ?>"
                    data-biaya="<?= (float)$r['biaya_standar']; ?>"
                    data-keterangan="<?= htmlspecialchars($r['keterangan'] ?? ''); ?>"
                    title="Edit Barang" style="border: 1px solid #b8b8b8; background: #ffffff; border-radius: 3px; padding: 3px 8px;">
                    <i class="fas fa-pencil-alt text-primary"></i>
                  </button>
                  <button class="btn btn-default btn-xs btn-flat btn-delete-barang" data-id="<?= $r['id_barang']; ?>" title="Hapus Barang" style="border: 1px solid #fecaca; background: #fff5f5; border-radius: 3px; padding: 3px 8px;">
                    <i class="fas fa-trash-alt text-danger"></i>
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Jenis Barang -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form id="formBarang" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
          <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 14px 20px;">
            <div class="d-flex align-items-center">
              <div style="width: 32px; height: 32px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 10px; color: #0d9f4f;">
                <i class="fas fa-box"></i>
              </div>
              <h5 class="modal-title font-weight-bold text-dark" id="modalFormLabel" style="font-size: 1.15rem; margin: 0;">Tambah Jenis Barang & Tarif</h5>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; color: #5f6368;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 20px; background: #ffffff;">
            <input type="hidden" name="id_barang" id="id_barang">
            <input type="hidden" name="aksi" id="aksi_barang" value="tambah">
            <div class="form-group mb-3">
              <label for="nama_barang" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Nama Jenis Barang / Paket <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control form-control-sm" name="nama_barang" id="nama_barang" placeholder="Contoh: PAKET SEDANG, DOKUMEN, ELEKTRONIK" required style="border: 1px solid #b8b8b8; border-radius: 4px; text-transform: uppercase;">
              <small class="text-muted">Nama barang akan tampil pada dropdown formulir pengiriman paket.</small>
            </div>

            <div class="form-group mb-3">
              <label for="biaya_standar" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Ongkos / Biaya Standar (Rp) <span class="text-danger">*</span>
              </label>
              <input type="number" class="form-control form-control-sm font-weight-bold" name="biaya_standar" id="biaya_standar" min="0" step="1000" placeholder="Contoh: 15000" required style="border: 1.5px solid #0d9f4f; color: #076e34; border-radius: 4px; font-size: 0.95rem;">
              <small class="text-muted">Nominal ini akan otomatis mengisi field biaya kirim saat dipilih.</small>
            </div>

            <div class="form-group mb-0">
              <label for="keterangan" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Keterangan / Deskripsi Ukuran
              </label>
              <textarea class="form-control form-control-sm" name="keterangan" id="keterangan" rows="2" placeholder="Contoh: Maksimal 5 kg, kardus ukuran mie instan, dsb..." style="border: 1px solid #b8b8b8; border-radius: 4px;"></textarea>
            </div>
          </div>
          <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e0e0e0; padding: 12px 20px;">
            <button type="button" class="btn btn-default btn-sm btn-flat font-weight-bold" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; color: #4b5563; border-radius: 4px; padding: 6px 16px;">
              <i class="fas fa-times mr-1"></i> Batal
            </button>
            <button type="submit" id="btnSave" name="tambah" class="btn btn-success btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34; border-radius: 4px; padding: 6px 18px;">
              <i class="fas fa-save mr-1"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<?php include '../inc/footer.php'; ?>

<!-- Script Interaktif Langsung (Menjamin Tombol Edit & Hapus Aktif Tanpa Terkendala Cache Browser / Versi Footer di Hosting) -->
<script>
$(function () {
  // Inisialisasi DataTable jika belum terinisialisasi
  if ($('#tableJenisBarang').length && !$.fn.DataTable.isDataTable('#tableJenisBarang')) {
    var table = $('#tableJenisBarang').DataTable({
      responsive: true,
      lengthChange: true,
      autoWidth: false,
      pageLength: 25,
      order: [[1, 'asc']],
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
      language: {
        search: "Cari Barang / Tarif:",
        lengthMenu: "Tampilkan _MENU_ baris",
        info: "Menampilkan _START_ - _END_ dari _TOTAL_ jenis barang",
        infoEmpty: "Tidak ada data jenis barang",
        zeroRecords: "Data tidak ditemukan",
        paginate: {
          first: "Awal",
          last: "Akhir",
          next: "Lanjut",
          previous: "Kembali"
        }
      }
    });
    if ($('#tableJenisBarang_wrapper .col-md-6:eq(0)').length) {
      table.buttons().container().appendTo('#tableJenisBarang_wrapper .col-md-6:eq(0)');
    }
  }

  // Event handler tombol Edit (lepas handler lama untuk cegah duplikasi)
  $(document).off('click', '.btn-edit-barang').on('click', '.btn-edit-barang', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    var biaya = $(this).data('biaya');
    var ket = $(this).data('keterangan');

    $('#id_barang').val(id);
    $('#aksi_barang').val('edit');
    $('#nama_barang').val(nama);
    $('#biaya_standar').val(biaya);
    $('#keterangan').val(ket);

    $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
    $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Jenis Barang & Tarif');
    $('#modalForm').modal('show');
  });

  // Reset modal form saat ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    if ($('#formBarang').length) {
      $('#formBarang')[0].reset();
    }
    $('#id_barang').val('');
    $('#aksi_barang').val('tambah');
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan');
    $('#modalFormLabel').html('<i class="fas fa-box mr-2 text-success"></i>Tambah Jenis Barang & Tarif');
  });

  // Event handler tombol Hapus (SweetAlert2 dengan fallback confirm)
  $(document).off('click', '.btn-delete-barang').on('click', '.btn-delete-barang', function (e) {
    e.preventDefault();
    var id = $(this).data('id');

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Konfirmasi Hapus Data?',
        text: 'Jenis barang dan tarif standar ini akan dihapus permanen dari master!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal'
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = '?hapus=' + encodeURIComponent(id);
        }
      });
    } else {
      if (confirm('Apakah Anda yakin ingin menghapus data jenis barang ini?')) {
        window.location.href = '?hapus=' + encodeURIComponent(id);
      }
    }
  });
});
</script>
