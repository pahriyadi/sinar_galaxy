<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Handle CRUD (PRG pattern — PHP header redirect)
if (isset($_POST['tambah'])) {
    $_POST['created_by'] = $_SESSION['user']['username'];
    tambahSaldoAwal($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['edit'])) {
    $_POST['created_by'] = $_SESSION['user']['username'];
    updateSaldoAwal($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deleteSaldoAwal($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua data saldo awal
$data = getAllSaldoAwal();

// Ambil data rekening untuk dropdown
$rekening = getAllRekening();

// Sekarang baru HTML output
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Data Saldo Awal Bank</h1>
  </section>
  <section class="content">
    <div class="card">
      <div class="card-header">
        <button class="btn btn-primary" data-toggle="modal" data-target="#modalForm">
          <i class="fas fa-plus-circle"></i> Tambah Data
        </button>
      </div>
      <div class="card-body">
        <table id="tableSaldoAwal" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nama Rekening</th>
              <th>Nomor Rekening</th>
              <th>Bulan</th>
              <th>Tahun</th>
              <th>Saldo Awal</th>
              <th>Dibuat Oleh</th>
              <th>Tanggal Update</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = mysqli_fetch_assoc($data)): ?>
            <tr>
              <td><?= $r['id_saldo_awal']; ?></td>
              <td><?= $r['nama_rekening']; ?></td>
              <td><?= $r['nomor_rekening']; ?></td>
              <td><?= date('F', mktime(0, 0, 0, $r['bulan'], 1)); ?></td>
              <td><?= $r['tahun']; ?></td>
              <td>Rp <?= number_format($r['saldo_awal'], 0, ',', '.'); ?></td>
              <td><?= $r['created_by']; ?></td>
              <td><?= date('d/m/Y H:i', strtotime($r['updated_at'])); ?></td>
              <td>
                <button class="btn btn-warning btn-sm btn-edit" data-id="<?= $r['id_saldo_awal']; ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $r['id_saldo_awal']; ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

<!-- Modal Tambah/Edit Saldo Awal -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="formSaldoAwal" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalFormLabel">Tambah Saldo Awal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_saldo_awal" id="id_saldo_awal">
        <div class="form-group">
          <label for="id_rekening">Rekening Bank</label>
          <select class="form-control" name="id_rekening" id="id_rekening" required>
            <option value="">Pilih Rekening</option>
            <?php 
            mysqli_data_seek($rekening, 0);
            while ($rek = mysqli_fetch_assoc($rekening)): 
            ?>
            <option value="<?= $rek['id_rekening']; ?>"><?= $rek['nama_rekening']; ?> - <?= $rek['nomor_rekening']; ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="bulan">Bulan</label>
              <select class="form-control" name="bulan" id="bulan" required>
                <option value="">Pilih Bulan</option>
                <option value="1">Januari</option>
                <option value="2">Februari</option>
                <option value="3">Maret</option>
                <option value="4">April</option>
                <option value="5">Mei</option>
                <option value="6">Juni</option>
                <option value="7">Juli</option>
                <option value="8">Agustus</option>
                <option value="9">September</option>
                <option value="10">Oktober</option>
                <option value="11">November</option>
                <option value="12">Desember</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="tahun">Tahun</label>
              <input type="number" class="form-control" name="tahun" id="tahun" min="2020" max="2030" value="<?= date('Y'); ?>" required>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="saldo_awal">Saldo Awal (Rp)</label>
          <input type="number" class="form-control" name="saldo_awal" id="saldo_awal" step="0.01" min="0" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" id="btnSave" name="tambah" class="btn btn-success">Simpan</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<?php include '../inc/footer.php'; ?>
<script src="data_saldo_awal.js"></script>