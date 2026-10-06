<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Guard: Hanya Super Admin yang diizinkan mengakses modul Manajemen Users
$currUser = $_SESSION['user'] ?? [];
$currRole = strtolower((string)($currUser['role'] ?? ''));
if ($currRole !== 'super admin') {
    showFloatingNotice('Akses Ditolak: Halaman Manajemen Users hanya dapat diakses oleh Super Admin.', 'danger');
}

// Handle CRUD (PRG pattern — PHP header redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit']) || (!empty($_POST['id_users']) && !isset($_POST['tambah']))) {
        updateUsers($_POST);
    } else {
        tambahUsers($_POST);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deleteUsers($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua data users
$data = mysqli_query($conn, "SELECT * FROM data_users");

// Sekarang baru HTML output
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Data Users</h1>
  </section>
  <section class="content">
    <div class="card">
      <div class="card-header">
        <button class="btn btn-primary" data-toggle="modal" data-target="#modalForm">
          <i class="fas fa-plus-circle"></i> Tambah Data
        </button>
      </div>
      <div class="card-body">
        <table id="tableUsers" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Asal PO</th>
              <th>Alamat</th>
              <th>No HP</th>
              <th>Role</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = mysqli_fetch_assoc($data)): ?>
            <tr>
              <td><?= $r['id_users']; ?></td>
              <td><?= $r['username']; ?></td>
              <td><?= $r['asal_po']; ?></td>
              <td><?= $r['alamat']; ?></td>
              <td><?= $r['no_hp']; ?></td>
              <td><?= $r['role']; ?></td>
              <td>
                <button class="btn btn-warning btn-sm btn-edit" data-id="<?= $r['id_users']; ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $r['id_users']; ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

<!-- Modal Tambah/Edit Users -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="formUsers" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalFormLabel">Tambah Users</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_users" id="id_users">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" class="form-control" name="username" id="username" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-group">
            <input type="password" class="form-control" name="password" id="password" required>
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fa fa-eye"></i></button>
            </div>
          </div>
          <small id="passwordHelp" class="form-text text-muted" style="display: none;">Kosongkan jika tidak ingin mengubah password.</small>
        </div>
        <div class="form-group">
          <label for="asal_po">Asal PO</label>
          <input type="text" class="form-control" name="asal_po" id="asal_po" required>
        </div>
        <div class="form-group">
          <label for="alamat">Alamat</label>
          <textarea class="form-control" name="alamat" id="alamat" required></textarea>
        </div>
        <div class="form-group">
          <label for="no_hp">No HP</label>
          <input type="text" class="form-control" name="no_hp" id="no_hp" required>
        </div>
        <div class="form-group">
          <label for="role">Role</label>
          <select class="form-control" name="role" id="role" required>
            <option value="">-- Pilih Role --</option>
            <option value="super admin">Super Admin</option>
            <option value="admin">Admin</option>
            <option value="users">Users</option>
          </select>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.getElementById('togglePassword');
  const input = document.getElementById('password');
  if (toggle && input) {
    toggle.addEventListener('click', function() {
      if (input.type === 'password') {
        input.type = 'text';
        toggle.innerHTML = '<i class="fa fa-eye-slash"></i>';
      } else {
        input.type = 'password';
        toggle.innerHTML = '<i class="fa fa-eye"></i>';
      }
    });
  }
});
</script>