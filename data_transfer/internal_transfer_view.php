<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

$user = $_SESSION['user'] ?? [];
$idUsers = (int)($user['id_users'] ?? 0);
$asalPo = (string)($user['asal_po'] ?? '');
$role = (string)($user['role'] ?? '');

// DELETE (konfirmasi via GET ?delete=ID&confirm=1) - HARUS DI AWAL SEBELUM OUTPUT
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        $whereAuth = ($role === 'super admin') ? '' : ' AND (user_id = ? OR user_id IS NULL) AND (asal_po = ? OR asal_po IS NULL)';
        $sql = "DELETE FROM internal_transfers WHERE id_transfer=?".$whereAuth;
        if ($role === 'super admin') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $delId);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iis', $delId, $idUsers, $asalPo);
        }
        if ($stmt->execute()) { 
            // Redirect hilangkan query delete dan tambahkan flash message
            $q = $_GET; unset($q['delete'], $q['confirm']);
            $q['flash'] = 'Transfer berhasil dihapus.';
            header('Location: internal_transfer_view.php?'.http_build_query($q));
            exit;
        }
        $stmt->close();
    }
}

// Pastikan tabel transfer internal ada
$conn->query(
    "CREATE TABLE IF NOT EXISTS internal_transfers (
        id_transfer INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        dari_rekening_id INT NOT NULL,
        ke_rekening_id INT NOT NULL,
        jumlah DECIMAL(15,2) NOT NULL,
        keterangan TEXT NULL,
        user_id INT NULL,
        asal_po VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

// Ambil referensi rekening
$rekeningOptions = [];
$resRek = $conn->query("SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
if ($resRek) {
    while ($r = $resRek->fetch_assoc()) { $rekeningOptions[(int)$r['id_rekening']] = $r['nama_rekening']; }
}

// Filter GET
$tglDari = $_GET['tgl_dari'] ?? '';
$tglSampai = $_GET['tgl_sampai'] ?? '';
$rekeningFilter = $_GET['rekening'] ?? '';

if ($tglDari && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglDari)) $tglDari = '';
if ($tglSampai && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglSampai)) $tglSampai = '';

if (!$tglDari && !$tglSampai) {
    $tglDari = date('Y-m-d');
    $tglSampai = $tglDari;
} elseif ($tglDari && !$tglSampai) {
    $tglSampai = $tglDari;
} elseif (!$tglDari && $tglSampai) {
    $tglDari = $tglSampai;
}

// CREATE / UPDATE
$flash = $_GET['flash'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flash = ''; // Reset flash untuk POST request
    $act = $_POST['act'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $dari = (int)($_POST['dari_rekening_id'] ?? 0);
    $ke = (int)($_POST['ke_rekening_id'] ?? 0);
    $jumlah = (float)($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $idTransfer = (int)($_POST['id_transfer'] ?? 0);

    $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) && $dari > 0 && $ke > 0 && $dari !== $ke && $jumlah > 0;
    if (!$isValid) {
        $flash = 'Input tidak valid.';
    } else {
        if ($act === 'create') {
            $stmt = $conn->prepare("INSERT INTO internal_transfers (tanggal, dari_rekening_id, ke_rekening_id, jumlah, keterangan, user_id, asal_po) VALUES (?,?,?,?,?,?,?)");
            $apo = (string)$asalPo; $uid = (int)$idUsers;
            $stmt->bind_param('siidsss', $tanggal, $dari, $ke, $jumlah, $keterangan, $uid, $apo);
            if ($stmt->execute()) { $flash = 'Transfer berhasil ditambahkan.'; }
            else { $flash = 'Gagal menambah transfer: '.htmlspecialchars($stmt->error); }
            $stmt->close();
        } elseif ($act === 'update' && $idTransfer > 0) {
            // Batasi update berdasarkan peran
            $whereAuth = ($role === 'super admin') ? '' : ' AND (user_id = ? OR user_id IS NULL) AND (asal_po = ? OR asal_po IS NULL)';
            $sql = "UPDATE internal_transfers SET tanggal=?, dari_rekening_id=?, ke_rekening_id=?, jumlah=?, keterangan=? WHERE id_transfer=?".$whereAuth;
            if ($role === 'super admin') {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('siidsi', $tanggal, $dari, $ke, $jumlah, $keterangan, $idTransfer);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('siidsiis', $tanggal, $dari, $ke, $jumlah, $keterangan, $idTransfer, $idUsers, $asalPo);
            }
            if ($stmt->execute()) { $flash = 'Transfer berhasil diubah.'; }
            else { $flash = 'Gagal mengubah transfer: '.htmlspecialchars($stmt->error); }
            $stmt->close();
        }
    }
}

// Ambil data list
$params = [];
$types = '';
$where = ' WHERE 1=1';
if ($role !== 'super admin') {
    $where .= ' AND (user_id = ? OR user_id IS NULL) AND (asal_po = ? OR asal_po IS NULL)';
    $types .= 'is'; $params[] = $idUsers; $params[] = $asalPo;
}
if ($tglDari) { $where .= ' AND tanggal >= ?'; $types .= 's'; $params[] = $tglDari; }
if ($tglSampai) { $where .= ' AND tanggal <= ?'; $types .= 's'; $params[] = $tglSampai; }
if ($rekeningFilter) { $where .= ' AND (dari_rekening_id = ? OR ke_rekening_id = ?)'; $types .= 'ii'; $params[] = (int)$rekeningFilter; $params[] = (int)$rekeningFilter; }

$sqlList = 'SELECT * FROM internal_transfers'.$where.' ORDER BY tanggal DESC, id_transfer DESC';
$stmt = $conn->prepare($sqlList);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Sekarang baru muat tampilan layout
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><i class="fas fa-exchange-alt"></i> Transfer Internal</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="#">Setting Data</a></li>
            <li class="breadcrumb-item active">Transfer Internal</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Filter</h3></div>
        <div class="card-body">
          <?php if ($flash): ?>
            <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
          <?php endif; ?>
          <form method="get" class="mb-3">
            <div class="form-row">
              <div class="form-group col-md-2">
                <label>Tanggal Dari</label>
                <input type="date" class="form-control" name="tgl_dari" value="<?= htmlspecialchars($tglDari) ?>">
              </div>
              <div class="form-group col-md-2">
                <label>Tanggal Sampai</label>
                <input type="date" class="form-control" name="tgl_sampai" value="<?= htmlspecialchars($tglSampai) ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Filter Rekening</label>
                <select class="form-control" name="rekening">
                  <option value="">-- Semua --</option>
                  <?php foreach ($rekeningOptions as $id=>$nama): ?>
                    <option value="<?= (int)$id ?>" <?= ($rekeningFilter==$id?'selected':'') ?>><?= htmlspecialchars($nama) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-2 align-self-end">
                <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search"></i> Filter</button>
              </div>
              <div class="form-group col-md-2 align-self-end">
                <a class="btn btn-secondary btn-block" href="internal_transfer_view.php"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>

          <div class="card card-body">
            <h5 class="mb-3">Tambah / Ubah Transfer</h5>
            <form method="post">
              <input type="hidden" name="act" id="act" value="create">
              <input type="hidden" name="id_transfer" id="id_transfer" value="">
              <div class="form-row">
                <div class="form-group col-md-2">
                  <label>Tanggal</label>
                  <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= htmlspecialchars($tglDari) ?>" required>
                </div>
                <div class="form-group col-md-3">
                  <label>Dari Rekening</label>
                  <select class="form-control" name="dari_rekening_id" id="dari_rekening_id" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($rekeningOptions as $id=>$nama): ?>
                      <option value="<?= (int)$id ?>"><?= htmlspecialchars($nama) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-3">
                  <label>Ke Rekening</label>
                  <select class="form-control" name="ke_rekening_id" id="ke_rekening_id" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($rekeningOptions as $id=>$nama): ?>
                      <option value="<?= (int)$id ?>"><?= htmlspecialchars($nama) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-2">
                  <label>Jumlah</label>
                  <input type="number" step="0.01" min="0" class="form-control" name="jumlah" id="jumlah" required>
                </div>
                <div class="form-group col-md-2">
                  <label>Keterangan</label>
                  <input type="text" class="form-control" name="keterangan" id="keterangan" placeholder="Opsional">
                </div>
              </div>
              <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Simpan</button>
              <button class="btn btn-warning" type="button" id="btn-reset"><i class="fas fa-eraser"></i> Reset Form</button>
            </form>
          </div>

          <div class="table-responsive mt-3">
            <table class="table table-bordered table-striped">
              <thead class="bg-primary text-white">
                <tr>
                  <th style="width:70px;" class="text-center">Aksi</th>
                  <th style="width:100px;">Tanggal</th>
                  <th>Dari Rekening</th>
                  <th>Ke Rekening</th>
                  <th class="text-right" style="width:140px;">Jumlah</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($list as $row): ?>
                  <tr>
                    <td class="text-center">
                      <button class="btn btn-sm btn-info" title="Edit" onclick='fillForm(<?= json_encode([
                        'id_transfer'=>$row['id_transfer'],
                        'tanggal'=>$row['tanggal'],
                        'dari_rekening_id'=>$row['dari_rekening_id'],
                        'ke_rekening_id'=>$row['ke_rekening_id'],
                        'jumlah'=>$row['jumlah'],
                        'keterangan'=>$row['keterangan']
                      ]) ?>)'><i class="fas fa-edit"></i></button>
                      <a class="btn btn-sm btn-danger" title="Hapus" href="?<?= http_build_query(array_merge($_GET,[ 'delete'=>$row['id_transfer'], 'confirm'=>'1' ])) ?>" onclick="return confirm('Yakin hapus transfer ini?');"><i class="fas fa-trash"></i></a>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))) ?></td>
                    <td><?= htmlspecialchars($rekeningOptions[(int)$row['dari_rekening_id']] ?? '-') ?></td>
                    <td><?= htmlspecialchars($rekeningOptions[(int)$row['ke_rekening_id']] ?? '-') ?></td>
                    <td class="text-right">Rp <?= number_format((float)$row['jumlah'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['keterangan'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (count($list) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../inc/footer.php'; ?>

<script>
function fillForm(data){
  document.getElementById('act').value='update';
  document.getElementById('id_transfer').value=data.id_transfer;
  document.getElementById('tanggal').value=data.tanggal;
  document.getElementById('dari_rekening_id').value=data.dari_rekening_id;
  document.getElementById('ke_rekening_id').value=data.ke_rekening_id;
  document.getElementById('jumlah').value=data.jumlah;
  document.getElementById('keterangan').value=data.keterangan||'';
}
document.getElementById('btn-reset').addEventListener('click', function(){
  document.getElementById('act').value='create';
  document.getElementById('id_transfer').value='';
  document.getElementById('tanggal').value='<?= htmlspecialchars($tglDari) ?>';
  document.getElementById('dari_rekening_id').value='';
  document.getElementById('ke_rekening_id').value='';
  document.getElementById('jumlah').value='';
  document.getElementById('keterangan').value='';
});
</script>

