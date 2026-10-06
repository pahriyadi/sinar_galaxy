/**
 * JavaScript Handler untuk Master Data Jenis Pengeluaran & Biaya Standar
 * PO. Sinar Galaxy Travel
 */
$(function () {
  if (!$('#tableJenisPengeluaran').length && !$('#tableJenis').length) return;

  var targetTable = $('#tableJenisPengeluaran').length ? '#tableJenisPengeluaran' : '#tableJenis';

  // Inisialisasi DataTable jika belum
  if (!$.fn.DataTable.isDataTable(targetTable)) {
    var table = $(targetTable).DataTable({
      responsive: true,
      lengthChange: true,
      autoWidth: false,
      pageLength: 25,
      order: [[1, 'asc']],
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
      language: {
        search: "Cari Pos Pengeluaran:",
        lengthMenu: "Tampilkan _MENU_ baris",
        info: "Menampilkan _START_ - _END_ dari _TOTAL_ pos pengeluaran",
        infoEmpty: "Tidak ada data pos pengeluaran",
        zeroRecords: "Data tidak ditemukan",
        paginate: {
          first: "Awal",
          last: "Akhir",
          next: "Lanjut",
          previous: "Kembali"
        }
      }
    });

    if ($(targetTable + '_wrapper .col-md-6:eq(0)').length) {
      table.buttons().container().appendTo(targetTable + '_wrapper .col-md-6:eq(0)');
    }
  }

  // Handle Edit Click (support both .btn-edit-jenis and legacy .btn-edit)
  $(document).off('click', '.btn-edit-jenis, .btn-edit').on('click', '.btn-edit-jenis, .btn-edit', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    var biaya = $(this).data('biaya');
    var ket = $(this).data('keterangan');

    if (nama !== undefined && biaya !== undefined) {
      $('#id_jenis_pengeluaran').val(id);
      $('#aksi_jenis').val('edit');
      $('#nama_pengeluaran').val(nama);
      $('#biaya_standar').val(biaya);
      $('#keterangan').val(ket || '');

      $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
      $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Pos Pengeluaran & Biaya Standar');
      $('#modalForm').modal('show');
    } else {
      // Fallback via AJAX fetch
      $.get('data_jenis_pengeluaran_fetch.php', { id_jenis_pengeluaran: id }, function (res) {
        var obj = typeof res === 'object' ? res : null;
        if (!obj) {
          try {
            obj = JSON.parse(res);
          } catch (err) {
            console.error('Gagal parse fetch:', err);
          }
        }
        if (obj && (obj.id_jenis_pengeluaran || obj.status === 'success')) {
          $('#id_jenis_pengeluaran').val(obj.id_jenis_pengeluaran);
          $('#aksi_jenis').val('edit');
          $('#nama_pengeluaran').val(obj.nama_pengeluaran);
          $('#biaya_standar').val(obj.biaya_standar || 0);
          $('#keterangan').val(obj.keterangan || '');

          $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
          $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Pos Pengeluaran & Biaya Standar');
          $('#modalForm').modal('show');
        }
      });
    }
  });

  // Reset form saat modal ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    if ($('#formJenis').length) {
      $('#formJenis')[0].reset();
    }
    $('#id_jenis_pengeluaran').val('');
    $('#aksi_jenis').val('tambah');
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan');
    $('#modalFormLabel').html('<i class="fas fa-layer-group mr-2 text-success"></i>Tambah Pos Pengeluaran');
  });

  // Handle Delete Click (support both .btn-delete-jenis and legacy .btn-delete)
  $(document).off('click', '.btn-delete-jenis, .btn-delete').on('click', '.btn-delete-jenis, .btn-delete', function (e) {
    e.preventDefault();
    var id = $(this).data('id');

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Konfirmasi Hapus Data?',
        text: 'Pos pengeluaran dan biaya standar ini akan dihapus permanen dari master!',
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
      if (confirm('Apakah Anda yakin ingin menghapus pos pengeluaran ini?')) {
        window.location.href = '?hapus=' + encodeURIComponent(id);
      }
    }
  });
});
